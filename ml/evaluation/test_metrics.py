import unittest

from metrics import compute_metrics


class MetricsTests(unittest.TestCase):
    def test_confusion_metrics_and_artificial_positive_class(self):
        result = compute_metrics([
            {"label": "artificial", "prediction": "artificial"},
            {"label": "artificial", "prediction": "human"},
            {"label": "human", "prediction": "artificial"},
            {"label": "human", "prediction": "human"},
        ])
        self.assertEqual(result["confusion_matrix"], {"true_artificial": 1, "false_human": 1, "false_artificial": 1, "true_human": 1})
        self.assertEqual(result["accuracy"], 0.5)
        self.assertEqual(result["f1_ai"], 0.5)

    def test_empty_and_undefined_denominators_are_null(self):
        result = compute_metrics([])
        self.assertEqual(result["sample_count"], 0)
        self.assertIsNone(result["accuracy"])
        self.assertIsNone(result["false_positive_rate"])

    def test_absent_positive_class_keeps_precision_and_recall_undefined(self):
        result = compute_metrics([{"label": "human", "prediction": "human"}])
        self.assertIsNone(result["precision_ai"])
        self.assertIsNone(result["recall_ai"])
        self.assertIsNone(result["f1_ai"])


if __name__ == "__main__":
    unittest.main()
