"""كشف الوجوه بمصنّف OpenCV Haar المرفق مع المكتبة (لا يحتاج تنزيلًا). يقتصر على الوجوه الأمامية تقريبًا."""

from __future__ import annotations

from dataclasses import dataclass
from functools import lru_cache

import cv2
import numpy as np


@dataclass(frozen=True)
class Box:
    x: int
    y: int
    w: int
    h: int

    @property
    def area(self) -> int:
        return self.w * self.h

    def iou(self, other: "Box") -> float:
        x1, y1 = max(self.x, other.x), max(self.y, other.y)
        x2, y2 = min(self.x + self.w, other.x + other.w), min(self.y + self.h, other.y + other.h)
        inter = max(0, x2 - x1) * max(0, y2 - y1)
        union = self.area + other.area - inter
        return inter / union if union else 0.0


@lru_cache(maxsize=1)
def _cascades() -> list[cv2.CascadeClassifier]:
    names = ["haarcascade_frontalface_default.xml", "haarcascade_frontalface_alt2.xml"]
    return [cv2.CascadeClassifier(cv2.data.haarcascades + name) for name in names]


def detect_faces(rgb: np.ndarray, min_size_ratio: float = 0.08) -> list[Box]:
    """يعيد الوجوه مرتبة من الأكبر إلى الأصغر. يعمل على نسخة رمادية مصغّرة للسرعة."""
    height, width = rgb.shape[:2]
    scale = 640 / max(height, width) if max(height, width) > 640 else 1.0
    gray = cv2.cvtColor(rgb, cv2.COLOR_RGB2GRAY)
    if scale != 1.0:
        gray = cv2.resize(gray, (int(width * scale), int(height * scale)), interpolation=cv2.INTER_AREA)
    gray = cv2.equalizeHist(gray)

    min_side = max(40, int(min(gray.shape[:2]) * min_size_ratio))
    for cascade in _cascades():
        faces = cascade.detectMultiScale(gray, scaleFactor=1.1, minNeighbors=5, minSize=(min_side, min_side))
        if len(faces):
            boxes = [Box(int(x / scale), int(y / scale), int(w / scale), int(h / scale)) for x, y, w, h in faces]
            return sorted(boxes, key=lambda b: b.area, reverse=True)
    return []


def crop_with_margin(rgb: np.ndarray, box: Box, margin: float = 0.3) -> np.ndarray:
    height, width = rgb.shape[:2]
    dx, dy = int(box.w * margin), int(box.h * margin)
    x1, y1 = max(0, box.x - dx), max(0, box.y - dy)
    x2, y2 = min(width, box.x + box.w + dx), min(height, box.y + box.h + dy)
    return rgb[y1:y2, x1:x2]
