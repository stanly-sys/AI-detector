"""Run one bounded CPU inference against the pre-mounted local model."""
import json
import os
import sys
import warnings

from PIL import Image, ImageFile, UnidentifiedImageError
from transformers import pipeline

MODEL_PATH = "/opt/models/sdxl-detector"
MAX_PIXELS = 40_000_000
MAX_SIDE = 10_000

def main() -> int:
    if len(sys.argv) != 2:
        print("Expected one image path", file=sys.stderr)
        return 2
    image_path = os.path.realpath(sys.argv[1])
    if not image_path.startswith("/tmp/ai-detector-uploads/"):
        print("Rejected image path", file=sys.stderr)
        return 2
    Image.MAX_IMAGE_PIXELS = MAX_PIXELS
    ImageFile.LOAD_TRUNCATED_IMAGES = False
    warnings.simplefilter("error", Image.DecompressionBombWarning)
    try:
        with Image.open(image_path) as source:
            if source.format not in {"JPEG", "PNG", "WEBP"}:
                raise ValueError("Unsupported image format")
            if source.width > MAX_SIDE or source.height > MAX_SIDE or source.width * source.height > MAX_PIXELS:
                raise ValueError("Image dimensions exceed limits")
            source.verify()
        with Image.open(image_path) as source:
            source.load()
            image = source.convert("RGB")
        detector = pipeline("image-classification", model=MODEL_PATH, device=-1, use_fast=False)
        print(json.dumps(detector(image), allow_nan=False))
        return 0
    except (OSError, ValueError, UnidentifiedImageError, Image.DecompressionBombError, Image.DecompressionBombWarning) as exc:
        print(f"Image rejected or could not be decoded: {type(exc).__name__}", file=sys.stderr)
        return 3

if __name__ == "__main__":
    raise SystemExit(main())
