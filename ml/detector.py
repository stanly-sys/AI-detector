"""Validate the staged image and emit per-model JSON results."""
import json
import os
import sys
import warnings
try:
    import resource
except ImportError:
    resource = None

from PIL import Image, ImageFile, UnidentifiedImageError

MAX_PIXELS = 40_000_000
MAX_SIDE = 10_000
UPLOAD_ROOT = "/tmp/ai-detector-uploads/"


def main() -> int:
    if len(sys.argv) != 3:
        print("Expected image path and model selection", file=sys.stderr)
        return 2
    image_path = os.path.realpath(sys.argv[1])
    selection = sys.argv[2]
    if not image_path.startswith(UPLOAD_ROOT) or selection not in {"both", "sdxl_detector", "community_forensics"}:
        print("Rejected image path or model selection", file=sys.stderr)
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
        from inference import run_models
        results = run_models(image_path, selection)
        peak_memory_mb = (resource.getrusage(resource.RUSAGE_SELF).ru_maxrss / 1024.0) if resource is not None else None
        print(json.dumps({"models": results, "process_peak_memory_mb": peak_memory_mb}, allow_nan=False))
        return 0
    except (OSError, ValueError, UnidentifiedImageError, Image.DecompressionBombError, Image.DecompressionBombWarning) as exc:
        print(f"Image rejected or could not be decoded: {type(exc).__name__}", file=sys.stderr)
        return 3
    except Exception as exc:
        print(f"Inference orchestration failed: {type(exc).__name__}: {exc}", file=sys.stderr)
        return 4


if __name__ == "__main__":
    raise SystemExit(main())
