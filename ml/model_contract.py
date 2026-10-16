"""Pure score mapping shared by model adapters and unit tests."""
import math


def from_fake_logit_score(ai_score: float) -> dict:
    score = float(ai_score)
    if not math.isfinite(score) or not 0.0 <= score <= 1.0:
        raise ValueError("AI score must be finite and within [0, 1]")
    return {
        "ai_score": score,
        "human_score": 1.0 - score,
        "prediction": "artificial" if score >= 0.5 else "human",
    }
