import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "ml"))
from model_contract import from_fake_logit_score


class CommunityForensicsContractTests(unittest.TestCase):
    def test_fake_score_is_mapped_to_artificial_and_complement_human(self):
        result = from_fake_logit_score(0.8)
        self.assertAlmostEqual(result["ai_score"], 0.8)
        self.assertAlmostEqual(result["human_score"], 0.2)
        self.assertEqual(result["prediction"], "artificial")

    def test_tie_defaults_to_artificial(self):
        self.assertEqual(from_fake_logit_score(0.5)["prediction"], "artificial")


if __name__ == "__main__":
    unittest.main()
