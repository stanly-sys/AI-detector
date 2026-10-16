"""Sequential multi-model inference with isolated per-model outcomes."""
from __future__ import annotations

import gc
import os
import sys
import time
from PIL import Image

MODEL_SELECTIONS = {"both", "sdxl_detector", "community_forensics"}
MODEL_IDS = ("sdxl_detector", "community_forensics")
SDXL_PATH = os.environ.get("SDXL_DETECTOR_PATH", "/opt/models/sdxl-detector")


def _not_selected(name: str) -> dict:
    return {"model_name": name, "status": "not_selected", "error": "This model was not selected for this analysis."}


def _predict_sdxl(image: Image.Image) -> dict:
    started = time.perf_counter()
    if not os.path.isdir(SDXL_PATH):
        return {"model_name": "SDXL Detector", "model_version": "Organika/sdxl-detector", "status": "unavailable", "error": "Model weights are not installed."}
    detector = None
    try:
        # Preserve the existing pipeline and labels; keep loading local/offline.
        from transformers import pipeline
        detector = pipeline("image-classification", model=SDXL_PATH, device=-1, use_fast=False)
        output = detector(image)
        scores = {}
        for item in output:
            label = str(item.get("label", "")).strip().lower()
            score = float(item.get("score", float("nan")))
            if label in {"artificial", "human"} and 0.0 <= score <= 1.0:
                scores[label] = score
        if not {"artificial", "human"}.issubset(scores):
            raise ValueError("SDXL detector did not return the expected labels")
        ai_score, human_score = scores["artificial"], scores["human"]
        version = "Organika/sdxl-detector (local checkpoint)"
        return {"model_name": "SDXL Detector", "model_version": version, "status": "success", "ai_score": ai_score, "human_score": human_score, "prediction": "artificial" if ai_score >= human_score else "human", "inference_time_ms": round((time.perf_counter() - started) * 1000, 2), "score_semantics": "pipeline score; not independently calibrated"}
    except Exception as exc:
        print(f"SDXL inference failed: {type(exc).__name__}: {exc}", file=sys.stderr)
        return {"model_name": "SDXL Detector", "model_version": "Organika/sdxl-detector", "status": "error", "error": "Model inference failed."}
    finally:
        if detector is not None:
            del detector
        gc.collect()


def run_models(image_path: str, selection: str) -> dict:
    if selection not in MODEL_SELECTIONS:
        raise ValueError("Unsupported model selection")
    with Image.open(image_path) as source:
        source.load()
        image = source.convert("RGB")

    results = {}
    for model_id, label in (("sdxl_detector", "SDXL Detector"), ("community_forensics", "Community Forensics")):
        if selection not in {"both", model_id}:
            results[model_id] = _not_selected(label)
            continue
        if model_id == "sdxl_detector":
            results[model_id] = _predict_sdxl(image)
        else:
            try:
                from models.community_forensics import predict
                results[model_id] = predict(image)
            except Exception as exc:
                print(f"Community Forensics adapter unavailable: {type(exc).__name__}: {exc}", file=sys.stderr)
                results[model_id] = {"model_name": label, "status": "unavailable", "error": "Model runtime dependencies are unavailable."}
    return results
