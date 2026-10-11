import importlib.util
import json
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location('sonar_profile', ROOT / '.github/scripts/check-sonar-profile.py')
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)


class ProfilePolicyTest(unittest.TestCase):
    def setUp(self):
        self.policy = json.loads((ROOT / '.github/sonar/php-profile.json').read_text())
        self.profile = {'name': self.policy['name'], 'key': 'fixture-profile'}
        parameters = {**self.policy['configure'], 'php:S103': {'maximumLineLength': '120'}}
        self.response = {
            'rules': [{'key': key} for key in self.policy['required_rules']],
            'actives': {key: [{
                'qProfile': 'fixture-profile',
                'params': [{'key': name, 'value': value} for name, value in values.items()],
            }] for key, values in parameters.items()},
        }

    def test_accepts_reviewed_profile(self):
        MODULE.validate_profile(self.policy, self.profile, self.response)

    def test_rejects_missing_reliability_rule(self):
        self.response['rules'] = [r for r in self.response['rules'] if r['key'] != 'php:S1784']
        with self.assertRaises(ValueError):
            MODULE.validate_profile(self.policy, self.profile, self.response)

    def test_rejects_extra_deactivation(self):
        self.policy['deactivate']['php:S1784'] = 'unsafe fixture'
        with self.assertRaises(ValueError):
            MODULE.validate_profile(self.policy, self.profile, self.response)

    def test_rejects_relaxed_line_limit(self):
        self.response['actives']['php:S103'][0]['params'][0]['value'] = '200'
        with self.assertRaises(ValueError):
            MODULE.validate_profile(self.policy, self.profile, self.response)

    def test_rejects_unreviewed_naming_pattern(self):
        self.response['actives']['php:S100'][0]['params'][0]['value'] = '.*'
        with self.assertRaises(ValueError):
            MODULE.validate_profile(self.policy, self.profile, self.response)


if __name__ == '__main__':
    unittest.main()
