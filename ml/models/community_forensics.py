"""Adapter for the official OwensLab Community Forensics 224 checkpoint.

The upstream model is a timm ViT-Small (patch 16, 224 input) with one logit.
This small wrapper mirrors the upstream ViTClassifier module names so its
SafeTensors state dict loads strictly without executing remote repository code.
"""
from __future__ import annotations

import json
import os
import sys
from pathlib import Path
from time import perf_counter

import timm
import torch
from PIL import Image
from safetensors.torch import load_file
from torchvision import transforms
from model_contract import from_fake_logit_score

MODEL_ID = "OwensLab/commfor-model-224"
MODEL_REVISION = "26afc31e6b40c312c3fd42c05a758be62446215b"
MODEL_PATH = Path(os.environ.get("COMMUNITY_FORENSICS_PATH", "/opt/models/community-forensics"))
MODEL_VERSION = MODEL_REVISION


class ViTClassifier(torch.nn.Module):
    """Architecture and state-dict keys matching upstream models.ViTClassifier."""

    def __init__(self) -> None:
        super().__init__()
        self.vit = timm.create_model(
            "vit_small_patch16_224.augreg_in21k_ft_in1k",
            pretrained=False,
            num_classes=1,
        )

    def forward(self, image: torch.Tensor) -> torch.Tensor:
        return self.vit(image)


def _preprocessor():
    # Same deterministic upstream test path: Resize(256), CenterCrop(224),
    # [0,1] tensor conversion, then ImageNet mean/std normalization.
    return transforms.Compose([
        transforms.Resize(256),
        transforms.CenterCrop(224),
        transforms.ToTensor(),
        transforms.Normalize(mean=(0.485, 0.456, 0.406), std=(0.229, 0.224, 0.225)),
    ])


def predict(image: Image.Image) -> dict:
    started = perf_counter()
    weights = MODEL_PATH / "model.safetensors"
    config_path = MODEL_PATH / "config.json"
    if not weights.is_file() or not config_path.is_file():
        return {"model_name": "Community Forensics", "model_version": MODEL_VERSION, "status": "unavailable", "error": "Model weights are not installed."}
    model = None
    state = None
    try:
        config = json.loads(config_path.read_text(encoding="utf-8"))
        if config != {"device": "cuda", "freeze_backbone": False, "input_size": 224, "model_size": "small", "patch_size": 16}:
            raise ValueError("Unexpected Community Forensics model configuration")
        model = ViTClassifier()
        state = load_file(str(weights), device="cpu")
        model.load_state_dict(state, strict=True)
        del state
        state = None
        model.eval()
        tensor = _preprocessor()(image.convert("RGB")).unsqueeze(0)
        with torch.inference_mode():
            logit = model(tensor).reshape(-1)[0]
            # The official evaluation applies sigmoid to this binary fake logit.
        ai_score = float(torch.sigmoid(logit).item())
        scores = from_fake_logit_score(ai_score)
        return {
            "model_name": "Community Forensics",
            "model_version": MODEL_VERSION,
            "status": "success",
            **scores,
            "inference_time_ms": round((perf_counter() - started) * 1000, 2),
            "score_semantics": "sigmoid model score; not calibrated probability",
        }
    except Exception as exc:
        # Keep the detail in server logs; only expose a generic state to clients.
        print(f"Community Forensics inference failed: {type(exc).__name__}: {exc}", file=sys.stderr)
        return {"model_name": "Community Forensics", "model_version": MODEL_VERSION, "status": "error", "error": "Model inference failed."}
    finally:
        if state is not None:
            del state
        if model is not None:
            del model
        import gc
        gc.collect()

