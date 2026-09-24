import unittest
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from model import _onehot_from_code
from riasec import DIMENSIONS


class OneHotFromCodeTests(unittest.TestCase):
    def test_full_name_letter_mapping_does_not_raise(self):
        vec = _onehot_from_code("E")
        self.assertEqual(len(vec), len(DIMENSIONS))
        self.assertEqual(vec[DIMENSIONS.index("E")], 1.0)


if __name__ == "__main__":
    unittest.main()
