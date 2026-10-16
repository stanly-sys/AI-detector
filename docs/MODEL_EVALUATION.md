# Evaluating detector models

Use a private, labeled CSV with columns `image,label` and optional `category`. Labels must be `artificial` or `human`; image paths are relative to the image root. Keep evaluation images and generated reports outside the web root and do not commit private datasets.

Example, run inside the application container after both model checkpoints are mounted:

```sh
python /opt/ai-detector/evaluation/evaluate_models.py /data/eval/labels.csv \
  --image-root /data/eval/images \
  --output /data/eval/results/model-evaluation.json
```

The evaluator invokes each model in its own child process for each sample, following the production detector path. It reports sample count, accuracy, precision, recall, F1, false-positive and false-negative rates, confusion counts, category metrics, model version, per-sample scores/statuses, inference time, child wall time, and maximum reported child RSS. Artificial is the positive class. Undefined ratios are `null`; failed/unavailable samples are counted separately and excluded from classification metrics. RSS is process peak resident memory, not a device-only allocation measure.

Build a representative held-out dataset with balanced real and generated images across relevant generators, edits, resolutions, and content categories. Keep duplicate and near-duplicate source images in one split. Report sample counts and category coverage alongside aggregate results. Do not select a model based on this report alone without reviewing label quality and error examples.
