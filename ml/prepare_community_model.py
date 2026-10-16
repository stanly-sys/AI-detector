"""Download and verify the pinned Community Forensics SafeTensors bundle.

Run only in a preparation container where the models volume is writable.
Normal application containers mount the same volume read-only.
"""
import hashlib
import json
import os
from pathlib import Path

from huggingface_hub import hf_hub_download
from safetensors import safe_open

REPO_ID = "OwensLab/commfor-model-224"
REVISION = "26afc31e6b40c312c3fd42c05a758be62446215b"
ROOT = Path(os.environ.get("MODEL_ROOT", "/opt/models"))
TARGET = ROOT / "community-forensics"


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def main() -> None:
    TARGET.mkdir(parents=True, exist_ok=True)
    for name in ("config.json", "model.safetensors"):
        hf_hub_download(repo_id=REPO_ID, filename=name, revision=REVISION, local_dir=str(TARGET))
    config = json.loads((TARGET / "config.json").read_text(encoding="utf-8"))
    expected = {"device": "cuda", "freeze_backbone": False, "input_size": 224, "model_size": "small", "patch_size": 16}
    if config != expected:
        raise SystemExit("Unexpected upstream model config; refusing to install")
    weights = TARGET / "model.safetensors"
    if not 80_000_000 <= weights.stat().st_size <= 100_000_000:
        raise SystemExit("Model file size is outside the expected ~86.7 MB range")
    with safe_open(str(weights), framework="pt", device="cpu") as checkpoint:
        shapes = {key: checkpoint.get_slice(key).get_shape() for key in checkpoint.keys() if key in {"vit.head.weight", "vit.head.bias"}}
    if shapes.get("vit.head.weight") != [1, 384] or shapes.get("vit.head.bias") != [1]:
        raise SystemExit("Unexpected classifier head in SafeTensors checkpoint")
    sums = "".join(f"{sha256(TARGET / name)}  {name}\n" for name in ("config.json", "model.safetensors"))
    (TARGET / "SHA256SUMS").write_text(sums, encoding="ascii")
    print(f"Prepared {REPO_ID}@{REVISION} in {TARGET}")
    print(sums, end="")


if __name__ == "__main__":
    main()
