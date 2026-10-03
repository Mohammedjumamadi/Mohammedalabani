"""محلل الفيديو: أخذ عينات ذكي من الإطارات، تحليل الوجوه بنموذج الصور، اتساق زمني، والمسار الصوتي."""

from __future__ import annotations

import time

import cv2
import numpy as np
from PIL import Image

from ..config import load_calibration, settings
from ..faces import Box, crop_with_margin, detect_faces
from ..media import MediaError, decode_audio, frame_to_jpeg_b64, has_audio_stream, probe
from ..registry import ModelUnavailable, registry
from .audio import SAMPLE_RATE, score_waveform
from .common import ensemble_model_info, indicator, severity

MODEL_NAME = "aimv-video-ensemble"
MODEL_VERSION = "1.0.0"
MAX_SUSPICIOUS_FRAMES = 6


class _Reader:
    def __init__(self, path: str) -> None:
        self.cap = cv2.VideoCapture(path)
        if not self.cap.isOpened():
            raise MediaError("Cannot open the video")
        self.fps = self.cap.get(cv2.CAP_PROP_FPS) or 25.0
        frames = self.cap.get(cv2.CAP_PROP_FRAME_COUNT) or 0
        self.duration = frames / self.fps if frames > 0 else 0.0
        self.width = int(self.cap.get(cv2.CAP_PROP_FRAME_WIDTH))
        self.height = int(self.cap.get(cv2.CAP_PROP_FRAME_HEIGHT))
        if self.duration <= 0 or self.width <= 0:
            raise MediaError("The video has no readable frames")

    def at(self, seconds: float) -> np.ndarray | None:
        self.cap.set(cv2.CAP_PROP_POS_MSEC, max(0.0, seconds) * 1000)
        ok, frame = self.cap.read()
        return cv2.cvtColor(frame, cv2.COLOR_BGR2RGB) if ok else None

    def sequence(self, start: float, count: int) -> list[np.ndarray]:
        """إطارات متتالية بمعدل الفيديو الأصلي، لفحص الاستمرارية القصيرة."""
        self.cap.set(cv2.CAP_PROP_POS_MSEC, max(0.0, start) * 1000)
        frames = []
        for _ in range(count):
            ok, frame = self.cap.read()
            if not ok:
                break
            frames.append(cv2.cvtColor(frame, cv2.COLOR_BGR2RGB))
        return frames

    def close(self) -> None:
        self.cap.release()


def _sample_times(duration: float, cal: dict) -> list[float]:
    """عدد الإطارات يتناسب مع المدة (ثانية لكل إطار) بين حدّين، موزعة بالتساوي وبعيدة عن الأطراف."""
    count = int(np.clip(round(duration), cal["min_frames"], cal["max_frames"]))
    return np.linspace(0.04 * duration, 0.96 * duration, count).tolist()


def _score_face_frames(face_model, frames: list[tuple[float, np.ndarray, Box | None]]) -> dict[int, float]:
    """يقيّم الإطارات التي فيها وجوه دفعة واحدة. المفتاح: فهرس الإطار في القائمة."""
    indices = [i for i, (_, _, box) in enumerate(frames) if box is not None]
    scores: dict[int, float] = {}
    for start in range(0, len(indices), 8):
        chunk = indices[start:start + 8]
        images = [Image.fromarray(crop_with_margin(frames[i][1], frames[i][2])) for i in chunk]
        inputs = face_model.processor(images=images, return_tensors="pt")
        for i, p in zip(chunk, face_model.fake_probabilities_batch(dict(inputs)).tolist()):
            scores[i] = float(p)
    return scores


def _local_consistency(reader: _Reader, center: float, face_box_hint: Box | None, audio: np.ndarray | None) -> list[dict]:
    """فحص قصير على إطارات متتالية: ارتعاش الوجه، وارتباط حركة الفم بشدة الصوت (تجريبي)."""
    count = int(min(48, reader.fps * 2))
    start = max(0.0, min(center - 1.0, reader.duration - count / reader.fps))
    frames = reader.sequence(start, count)
    if len(frames) < 12:
        return []

    boxes = []
    for frame in frames:
        faces = detect_faces(frame)
        boxes.append(faces[0] if faces else None)
    valid = [(i, b) for i, b in enumerate(boxes) if b is not None]
    if len(valid) < 10:
        return []

    out: list[dict] = []

    # ارتعاش الوجه: طفرة في تغيّر مظهر الوجه بين إطارين متتاليين مقارنة بالمعتاد
    crops = {i: cv2.resize(cv2.cvtColor(crop_with_margin(frames[i], b, 0.1), cv2.COLOR_RGB2GRAY), (64, 64)).astype(np.float32) / 255 for i, b in valid}
    diffs = [float(np.abs(crops[b] - crops[a]).mean()) for a, b in zip(sorted(crops), sorted(crops)[1:]) if b - a == 1]
    if len(diffs) >= 8:
        median = max(float(np.median(diffs)), 1e-4)
        spike = float(max(diffs) / median)
        out.append(indicator(
            "face_flicker", "visual", score=float(np.clip((spike - 3) / 7 * 100, 0, 100)), experimental=True,
            start=start, end=start + len(frames) / reader.fps,
            details={"spike_ratio": round(spike, 2), "frames_compared": len(diffs)},
        ))

    # حركة الفم مقابل شدة الصوت
    if audio is not None and len(audio) > 0:
        motion = []
        for (i, box), (j, _) in zip(valid, valid[1:]):
            if j - i != 1:
                continue
            y1 = box.y + int(box.h * 0.62)
            mouth_a = cv2.cvtColor(frames[i][y1:box.y + box.h, box.x:box.x + box.w], cv2.COLOR_RGB2GRAY)
            mouth_b = cv2.cvtColor(frames[j][y1:box.y + box.h, box.x:box.x + box.w], cv2.COLOR_RGB2GRAY)
            if mouth_a.shape != mouth_b.shape or mouth_a.size == 0:
                continue
            motion.append((i, float(np.abs(mouth_b.astype(np.float32) - mouth_a.astype(np.float32)).mean())))

        if len(motion) >= 10:
            samples_per_frame = SAMPLE_RATE / reader.fps
            rms = []
            for i, _ in motion:
                a = int((start * reader.fps + i) * samples_per_frame)
                seg = audio[a:a + int(samples_per_frame)]
                rms.append(float(np.sqrt(np.mean(seg ** 2))) if len(seg) else 0.0)
            m = np.asarray([v for _, v in motion])
            r = np.asarray(rms)
            if m.std() > 1e-6 and r.std() > 1e-6:
                corr = float(np.corrcoef(m, r)[0, 1])
                out.append(indicator(
                    "lip_sync_experimental", "temporal", sev="info" if corr >= 0.3 else "low", experimental=True,
                    start=start, end=start + len(frames) / reader.fps,
                    details={"correlation": round(corr, 3), "frames": len(motion), "in_final_score": False},
                ))
    return out


def analyze_video(path: str) -> dict:
    started = time.perf_counter()
    cal = load_calibration()["video"]

    reader = _Reader(path)
    try:
        info = probe(path)
        duration = min(reader.duration, settings.max_video_seconds)

        face_model = registry.get("image_face")  # يرمي ModelUnavailable إن لم يتوفر
        try:
            gen_model = registry.get("image_gen")
        except ModelUnavailable:
            gen_model = None

        # --- المرور الأول: عينة موزعة بالتساوي
        frames: list[tuple[float, np.ndarray, Box | None]] = []
        for t in _sample_times(duration, cal):
            rgb = reader.at(t)
            if rgb is None:
                continue
            faces = detect_faces(rgb)
            frames.append((t, rgb, faces[0] if faces else None))
        if len(frames) < 3:
            raise MediaError("Could not read enough frames from the video")

        scores = _score_face_frames(face_model, frames)

        # --- المرور الثاني (عينة ذكية): نكثّف الإطارات حول أعلى الإطارات اشتباهًا بدل تحليل كل الإطارات
        if scores:
            top = sorted(scores, key=lambda i: -scores[i])[:2]
            extra = []
            for i in top:
                for dt in (-0.5, 0.5):
                    t = float(np.clip(frames[i][0] + dt, 0, duration - 0.05))
                    if all(abs(t - ft) > 0.2 for ft, _, _ in frames + extra):
                        rgb = reader.at(t)
                        if rgb is not None:
                            faces = detect_faces(rgb)
                            extra.append((t, rgb, faces[0] if faces else None))
            if extra:
                frames.extend(extra)
                frames.sort(key=lambda item: item[0])
                scores = _score_face_frames(face_model, frames)

        face_idx = sorted(scores)
        indicators: list[dict] = []
        gen_scores: list[float] = []
        used_models = [face_model]

        if len(face_idx) >= 1:
            video_p = float(np.mean([scores[i] for i in face_idx]))
            basis = "faces"
        elif gen_model is not None:
            # لا وجوه: نستخدم نموذج الصور المولّدة على عينة من الإطارات
            sample = frames[:: max(1, len(frames) // 8)][:8]
            images = [Image.fromarray(f[1]) for f in sample]
            inputs = gen_model.processor(images=images, return_tensors="pt")
            gen_scores = gen_model.fake_probabilities_batch(dict(inputs)).tolist()
            video_p = float(np.mean(gen_scores))
            basis = "whole_frames"
            used_models = [gen_model]
            indicators.append(indicator("no_face_detected", "visual", sev="info"))
        else:
            raise ModelUnavailable("No model can analyze faceless video")

        # --- المسار الصوتي
        audio_p = None
        audio = None
        audio_info: dict = {}
        if has_audio_stream(info):
            try:
                audio = decode_audio(path, SAMPLE_RATE, max_seconds=min(int(duration) + 1, settings.max_audio_seconds))
                if len(audio) >= SAMPLE_RATE:
                    audio_result = score_waveform(audio)
                    audio_info = {"speech_ratio": round(audio_result["speech_ratio"], 3), "windows": len(audio_result["windows"])}
                    if audio_result["speech_ratio"] >= 0.15:
                        audio_p = audio_result["probability"]
                        used_models.append(audio_result["model"])
                        indicators.append(indicator("audio_track_score", "model", score=audio_p * 100, details=audio_info))
            except (ModelUnavailable, MediaError):
                audio_p = None

        if audio_p is not None:
            probability = (cal["w_video"] * video_p + cal["w_audio"] * audio_p) * 100 / (cal["w_video"] + cal["w_audio"])
        else:
            probability = video_p * 100

        # --- مؤشرات النموذج والإطارات المشبوهة
        indicators.insert(0, indicator(
            "model_frame_score", "model", score=video_p * 100,
            details={"basis": basis, "frames_analyzed": len(frames), "frames_with_faces": len(face_idx)},
        ))

        frame_threshold = float(cal["frame_threshold"])
        suspicious_ids = sorted((i for i in face_idx if scores[i] >= frame_threshold), key=lambda i: -scores[i])[:MAX_SUSPICIOUS_FRAMES]
        out_frames: list[dict] = []
        for order, i in enumerate(sorted(suspicious_ids, key=lambda k: frames[k][0])):
            t, rgb, box = frames[i]
            shown = rgb.copy()
            if box:
                cv2.rectangle(shown, (box.x, box.y), (box.x + box.w, box.y + box.h), (220, 38, 38), max(2, rgb.shape[1] // 250))
            out_frames.append({"index": order, "timestamp": round(t, 3), "suspicion": round(scores[i] * 100, 2), "image_b64": frame_to_jpeg_b64(shown)})
            indicators.append(indicator(
                "suspicious_frames", "temporal", score=scores[i] * 100, start=t, end=t, frame_index=order,
                details={"threshold": frame_threshold},
            ))

        # --- الاتساق الزمني بين الإطارات المحللة
        ordered = [scores[i] for i in face_idx]
        if len(ordered) >= 5:
            mad = float(np.mean(np.abs(np.diff(ordered))))
            indicators.append(indicator(
                "temporal_inconsistency", "temporal", score=float(np.clip(mad / 0.5 * 100, 0, 100)), experimental=True,
                details={"mean_abs_change": round(mad, 4), "std": round(float(np.std(ordered)), 4), "frames": len(ordered)},
            ))

        # --- فحص متتالٍ قصير حول أكثر الإطارات اشتباهًا (أو منتصف المقطع)
        center = frames[max(scores, key=scores.get)][0] if scores else duration / 2
        try:
            indicators.extend(_local_consistency(reader, center, None, audio))
        except Exception:  # noqa: BLE001 - الفحوص المساعدة لا توقف التحليل
            pass

        return {
            "media_type": "video",
            "manipulation_probability": round(float(probability), 2),
            "authenticity_probability": round(100 - float(probability), 2),
            "confidence": round(max(probability, 100 - probability), 2),
            "model": ensemble_model_info(MODEL_NAME, MODEL_VERSION, used_models),
            "processing_time": round(time.perf_counter() - started, 2),
            "indicators": indicators,
            "frames": out_frames,
            "technical": {
                "duration_seconds": round(reader.duration, 2), "analyzed_seconds": round(duration, 2),
                "fps": round(reader.fps, 2), "width": reader.width, "height": reader.height,
                "frames_sampled": len(frames), "frames_with_faces": len(face_idx), "basis": basis,
                "has_audio": has_audio_stream(info), "audio": audio_info,
                "weights": {"video": cal["w_video"], "audio": cal["w_audio"]} if audio_p is not None else {"video": 1.0},
                "severity_of_result": severity(probability),
            },
        }
    finally:
        reader.close()
