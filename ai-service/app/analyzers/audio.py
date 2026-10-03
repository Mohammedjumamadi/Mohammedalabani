"""محلل الصوت: نموذج wav2vec2 مفتوح المصدر على نوافذ مختارة بذكاء، مع فحوص طيفية مساعدة."""

from __future__ import annotations

import time

import numpy as np

from ..config import load_calibration, settings
from ..media import MediaError, decode_audio
from ..registry import LoadedModel, registry
from .common import ensemble_model_info, indicator, severity

MODEL_NAME = "aimv-audio-detector"
MODEL_VERSION = "1.0.0"
SAMPLE_RATE = 16000


def _speech_intervals(y: np.ndarray) -> tuple[list[tuple[int, int]], float]:
    import librosa

    intervals = [(int(a), int(b)) for a, b in librosa.effects.split(y, top_db=30, frame_length=1024, hop_length=256)]
    speech = sum(b - a for a, b in intervals)
    return intervals, speech / max(1, len(y))


def _choose_windows(y: np.ndarray, intervals: list[tuple[int, int]], window: int, max_windows: int) -> list[tuple[int, int]]:
    """نوافذ غير متداخلة بطول ثابت. نفضّل النوافذ التي يغلب عليها الكلام، ونوزع الاختيار بالتساوي على المدة."""
    total = len(y)
    starts = list(range(0, max(1, total - window // 2), window))
    voiced_mask = np.zeros(total, dtype=bool)
    for a, b in intervals:
        voiced_mask[a:b] = True

    candidates = []
    for s in starts:
        e = min(total, s + window)
        if e - s < SAMPLE_RATE:  # أقل من ثانية: لا يُعتد به
            continue
        if voiced_mask[s:e].mean() >= 0.4:
            candidates.append((s, e))

    if not candidates:  # لا كلام واضح: نأخذ نوافذ موزعة على أي حال ونُنبّه المستخدم لاحقًا
        candidates = [(s, min(total, s + window)) for s in starts if min(total, s + window) - s >= SAMPLE_RATE]

    if len(candidates) > max_windows:
        idx = np.linspace(0, len(candidates) - 1, max_windows).round().astype(int)
        candidates = [candidates[i] for i in sorted(set(idx.tolist()))]
    return candidates


def _score_windows(lm: LoadedModel, y: np.ndarray, windows: list[tuple[int, int]], window: int) -> list[float]:
    scores: list[float] = []
    batch_size = 4
    for i in range(0, len(windows), batch_size):
        chunk = []
        for s, e in windows[i:i + batch_size]:
            segment = y[s:e]
            if len(segment) < window:
                segment = np.pad(segment, (0, window - len(segment)))  # تعبئة بالصمت لنفس الطول
            chunk.append(segment)
        inputs = lm.processor(chunk, sampling_rate=SAMPLE_RATE, return_tensors="pt", padding=True)
        scores.extend(lm.fake_probabilities_batch(dict(inputs)).tolist())
    return scores


def score_waveform(y: np.ndarray, *, cal: dict | None = None) -> dict:
    """يقيّم موجة صوتية (16kHz أحادية) ويعيد الاحتمال ونتائج النوافذ. تستخدمه وحدة الفيديو أيضًا للمسار الصوتي."""
    cal = cal or load_calibration()["audio"]
    lm = registry.get("audio_voice")
    window = int(cal["window_seconds"] * SAMPLE_RATE)

    intervals, speech_ratio = _speech_intervals(y)
    windows = _choose_windows(y, intervals, window, int(cal["max_windows"]))
    if not windows:
        raise MediaError("Audio is too short to analyze (need at least one second)")

    scores = _score_windows(lm, y, windows, window)
    return {
        "probability": float(np.mean(scores)),
        "scores": scores,
        "windows": windows,
        "speech_ratio": float(speech_ratio),
        "intervals": intervals,
        "model": lm,
    }


def support_indicators(y16: np.ndarray, path: str | None, intervals: list[tuple[int, int]]) -> list[dict]:
    """فحوص مساعدة معلنة كتجريبية: طيف، توقفات، طبقة الصوت، وانقطاعات. لا تدخل في النتيجة النهائية."""
    import librosa

    out: list[dict] = []

    # --- الطيف: قطع حاد في الترددات العالية (يُقاس على معدل العينة الأصلي إن أمكن)
    try:
        y_hi = decode_audio(path, 44100, max_seconds=30) if path else y16
        sr_hi = 44100 if path else SAMPLE_RATE
        spectrum = np.abs(librosa.stft(y_hi, n_fft=2048, hop_length=1024)).mean(axis=1)
        cumulative = np.cumsum(spectrum) / max(spectrum.sum(), 1e-9)
        rolloff_bin = int(np.searchsorted(cumulative, 0.99))
        rolloff_hz = rolloff_bin * sr_hi / 2048
        nyquist = sr_hi / 2
        ratio = rolloff_hz / nyquist
        flatness = float(librosa.feature.spectral_flatness(y=y_hi).mean())
        score = float(np.clip((0.5 - ratio) / 0.5 * 100, 0, 100))
        out.append(indicator(
            "spectral_artifacts", "audio", score=score, experimental=True,
            details={"rolloff99_hz": round(rolloff_hz), "rolloff_ratio": round(ratio, 3), "spectral_flatness": round(flatness, 5)},
        ))
    except Exception:  # noqa: BLE001 - فحص مساعد لا يوقف التحليل
        pass

    # --- التوقفات: انتظام غير معتاد في أطوال الصمت
    gaps = [(intervals[i + 1][0] - intervals[i][1]) / SAMPLE_RATE for i in range(len(intervals) - 1)]
    gaps = [g for g in gaps if g >= 0.15]
    if len(gaps) >= 4:
        cv = float(np.std(gaps) / max(np.mean(gaps), 1e-6))
        score = float(np.clip((0.35 - cv) / 0.35 * 100, 0, 100))  # انتظام شديد = درجة أعلى
        out.append(indicator(
            "unnatural_pauses", "audio", score=score, experimental=True,
            details={"pauses": len(gaps), "mean_pause_s": round(float(np.mean(gaps)), 3), "pause_cv": round(cv, 3)},
        ))

    # --- طبقة الصوت: قفزات كبيرة بين إطارات متتالية
    try:
        segment = y16[: 10 * SAMPLE_RATE]
        f0, voiced, _ = librosa.pyin(segment, fmin=65, fmax=500, sr=SAMPLE_RATE, frame_length=1024)
        f0 = np.where(voiced, f0, np.nan)
        semitones = 12 * np.log2(f0 / 100.0)
        jumps = np.abs(np.diff(semitones))
        jumps = jumps[~np.isnan(jumps)]
        if len(jumps) >= 30:
            share = float((jumps > 8).mean())
            out.append(indicator(
                "pitch_instability", "audio", score=float(np.clip(share / 0.1 * 100, 0, 100)), experimental=True,
                details={"large_jump_share": round(share, 4), "voiced_frames": int(len(jumps))},
            ))
    except Exception:  # noqa: BLE001
        pass

    # --- الانقطاعات: قمم شاذة في التدفق الطيفي قد تدل على قص ودمج
    try:
        mel = librosa.feature.melspectrogram(y=y16, sr=SAMPLE_RATE, n_fft=1024, hop_length=256, n_mels=64)
        db = librosa.power_to_db(mel, ref=np.max)
        flux = np.sqrt((np.diff(db, axis=1) ** 2).mean(axis=0))
        z = (flux - np.median(flux)) / max(1.4826 * np.median(np.abs(flux - np.median(flux))), 1e-6)
        peaks = np.where(z > 12)[0]
        minutes = max(len(y16) / SAMPLE_RATE / 60, 1 / 60)
        per_minute = len(peaks) / minutes
        out.append(indicator(
            "splice_discontinuity", "audio", score=float(np.clip(per_minute / 20 * 100, 0, 100)), experimental=True,
            start=float(peaks[0] * 256 / SAMPLE_RATE) if len(peaks) else None,
            details={"abrupt_changes": int(len(peaks)), "per_minute": round(per_minute, 2)},
        ))
    except Exception:  # noqa: BLE001
        pass

    return out


def analyze_audio(path: str) -> dict:
    started = time.perf_counter()
    cal = load_calibration()["audio"]

    y = decode_audio(path, SAMPLE_RATE, max_seconds=settings.max_audio_seconds)
    duration = len(y) / SAMPLE_RATE
    if duration < 1.0:
        raise MediaError("Audio is too short to analyze (need at least one second)")

    result = score_waveform(y, cal=cal)
    probability = result["probability"] * 100
    lm: LoadedModel = result["model"]

    indicators = [indicator(
        "model_voice_score", "model", score=probability,
        details={"windows_analyzed": len(result["windows"]), "model": lm.spec.hf_id},
    )]

    # نوافذ مشبوهة بتوقيتاتها (أعلى 4)
    threshold = cal["window_threshold"]
    suspicious = sorted(
        [(window, p) for window, p in zip(result["windows"], result["scores"]) if p >= threshold],
        key=lambda item: -item[1],
    )[:4]
    for (start, end), p in suspicious:
        indicators.append(indicator("suspicious_segments", "temporal", score=p * 100, start=start / SAMPLE_RATE, end=end / SAMPLE_RATE))

    if result["speech_ratio"] < 0.15:
        indicators.append(indicator("audio_no_speech", "audio", sev="low", details={"speech_ratio": round(result["speech_ratio"], 3)}))
    if duration < 3:
        indicators.append(indicator("low_quality_audio", "audio", sev="low", description="short_clip", details={"duration_s": round(duration, 2)}))

    indicators.extend(support_indicators(y, path, result["intervals"]))

    return {
        "media_type": "audio",
        "manipulation_probability": round(probability, 2),
        "authenticity_probability": round(100 - probability, 2),
        "confidence": round(max(probability, 100 - probability), 2),
        "model": ensemble_model_info(MODEL_NAME, MODEL_VERSION, [lm]),
        "processing_time": round(time.perf_counter() - started, 2),
        "indicators": indicators,
        "frames": [],
        "technical": {
            "duration_seconds": round(duration, 2), "sample_rate_analyzed": SAMPLE_RATE,
            "windows_analyzed": len(result["windows"]), "speech_ratio": round(result["speech_ratio"], 3),
            "window_scores": [round(s, 4) for s in result["scores"]], "severity_of_result": severity(probability),
        },
    }
