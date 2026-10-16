"""Binary fake-image detection metrics without fabricated defaults."""

def compute_metrics(rows: list[dict]) -> dict:
    """Rows have label and prediction in {'artificial','human'}."""
    tp = tn = fp = fn = 0
    for row in rows:
        truth, predicted = row["label"], row["prediction"]
        if truth not in {"artificial", "human"} or predicted not in {"artificial", "human"}:
            raise ValueError("Labels and predictions must be artificial or human")
        if truth == "artificial" and predicted == "artificial": tp += 1
        elif truth == "human" and predicted == "human": tn += 1
        elif truth == "human" and predicted == "artificial": fp += 1
        else: fn += 1
    total = tp + tn + fp + fn
    precision = tp / (tp + fp) if tp + fp else None
    recall = tp / (tp + fn) if tp + fn else None
    f1_denominator = 2 * tp + fp + fn
    return {
        "sample_count": total,
        "accuracy": (tp + tn) / total if total else None,
        "precision_ai": precision if total else None,
        "recall_ai": recall if total else None,
        "f1_ai": 2 * tp / f1_denominator if f1_denominator else None,
        "false_positive_rate": fp / (fp + tn) if fp + tn else None,
        "false_negative_rate": fn / (fn + tp) if fn + tp else None,
        "confusion_matrix": {"true_artificial": tp, "false_human": fn, "false_artificial": fp, "true_human": tn},
    }
