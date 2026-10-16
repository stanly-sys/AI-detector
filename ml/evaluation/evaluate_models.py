"""Run production inference against a private labeled CSV dataset.

CSV columns: image,label[,category], labels are artificial or human.
The evaluator launches one model per child process to measure each model's
peak resident memory independently, using the same detector/preprocessing path.
"""
from __future__ import annotations

import argparse
import csv
import json
import os
import subprocess
import sys
import tempfile
import time
import shutil
from collections import defaultdict
from pathlib import Path

from metrics import compute_metrics

DETECTOR = Path(__file__).resolve().parents[1] / "detector.py"
PYTHON = os.environ.get("PYTHON", sys.executable)
MODEL_IDS = ("sdxl_detector", "community_forensics")
UPLOAD_ROOT = Path("/tmp/ai-detector-uploads")


def run_one(image: Path, model_id: str) -> tuple[dict, float, float | None]:
    started = time.perf_counter()
    staged_path = None
    try:
        UPLOAD_ROOT.mkdir(mode=0o700, parents=True, exist_ok=True)
        descriptor, staged_name = tempfile.mkstemp(prefix="evaluation-", suffix=image.suffix.lower(), dir=UPLOAD_ROOT)
        os.close(descriptor)
        staged_path = Path(staged_name)
        shutil.copyfile(image, staged_path)
        command = [PYTHON, str(DETECTOR), str(staged_path), model_id]
        completed = subprocess.run(command, capture_output=True, text=True, timeout=300, check=False)
        wall_ms = (time.perf_counter() - started) * 1000
        if completed.returncode != 0:
            return {"status": "error", "error": "Inference process failed"}, wall_ms, None
        data = json.loads(completed.stdout)
        model = data["models"][model_id]
        return model, wall_ms, data.get("process_peak_memory_mb")
    except subprocess.TimeoutExpired:
        return {"status": "timeout", "error": "Inference exceeded 300 seconds"}, 300_000.0, None
    except Exception:
        return {"status": "error", "error": "Could not parse inference output"}, (time.perf_counter() - started) * 1000, None
    finally:
        if staged_path is not None:
            staged_path.unlink(missing_ok=True)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("dataset_csv", type=Path)
    parser.add_argument("--image-root", type=Path, default=Path("."))
    parser.add_argument("--output", type=Path, default=Path("results/model-evaluation.json"))
    args = parser.parse_args()
    csv_path = args.dataset_csv.resolve()
    image_root = args.image_root.resolve()
    output_path = args.output.resolve()
    if not csv_path.is_file():
        parser.error("dataset CSV does not exist")
    rows = []
    with csv_path.open(newline="", encoding="utf-8-sig") as stream:
        for line_no, row in enumerate(csv.DictReader(stream), start=2):
            label = (row.get("label") or "").strip().lower()
            if label not in {"artificial", "human"}:
                parser.error(f"row {line_no}: label must be artificial or human")
            image = (image_root / (row.get("image") or "")).resolve()
            if not image.is_relative_to(image_root) or not image.is_file():
                parser.error(f"row {line_no}: image must exist under --image-root")
            rows.append({"image": image, "relative_image": image.relative_to(image_root).as_posix(), "label": label, "category": (row.get("category") or "unspecified").strip()})
    if not rows:
        parser.error("dataset has no labeled samples")

    reports = {}
    executions = []
    for model_id in MODEL_IDS:
        predictions = []
        category_predictions = defaultdict(list)
        wall_times = []
        memory_values = []
        failures = 0
        for row in rows:
            result, wall_ms, memory_mb = run_one(row["image"], model_id)
            wall_times.append(wall_ms)
            if result.get("status") == "success":
                item = {"label": row["label"], "prediction": result["prediction"]}
                predictions.append(item)
                category_predictions[row["category"]].append(item)
                if result.get("inference_time_ms") is not None:
                    executions.append((model_id, float(result["inference_time_ms"])))
                if isinstance(memory_mb, (int, float)):
                    memory_values.append(float(memory_mb))
            else:
                failures += 1
            row.setdefault("results", {})[model_id] = {"status": result.get("status", "error"), "model_version": result.get("model_version"), "prediction": result.get("prediction"), "ai_score": result.get("ai_score"), "human_score": result.get("human_score"), "inference_time_ms": result.get("inference_time_ms"), "category": row["category"]}
        reports[model_id] = {
            "model_version": next((row["results"][model_id].get("model_version") for row in rows if row["results"][model_id].get("model_version")), None),
            **compute_metrics(predictions),
            "metrics_by_category": {category: compute_metrics(items) for category, items in sorted(category_predictions.items())},
            "failed_or_unavailable": failures,
            "average_inference_time_ms": sum(t for m, t in executions if m == model_id) / sum(1 for m, _ in executions if m == model_id) if any(m == model_id for m, _ in executions) else None,
            "total_inference_time_ms": sum(t for m, t in executions if m == model_id),
            "average_child_wall_time_ms": sum(wall_times) / len(wall_times) if wall_times else None,
            "peak_rss_mb_max": max(memory_values) if memory_values else None,
            "memory_note": "Linux child-process peak RSS; null when the runtime did not report it.",
        }

    serial_rows = [{"image": row["relative_image"], "label": row["label"], "category": row["category"], "models": row["results"]} for row in rows]
    report = {"schema_version": 1, "dataset_csv": csv_path.name, "sample_count": len(rows), "metrics_definition": "artificial is the positive class", "models": reports, "samples": serial_rows}
    output_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_text(json.dumps(report, indent=2, allow_nan=False) + "\n", encoding="utf-8")
    print(json.dumps({"output": str(output_path), "models": reports}, indent=2, allow_nan=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
