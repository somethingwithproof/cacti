import importlib.util
import json
import io
import os
from pathlib import Path
import runpy
import unittest
from unittest.mock import patch
import urllib.error
import urllib.parse

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

    def test_rejects_wrong_profile(self):
        self.profile['name'] = 'Unreviewed profile'
        with self.assertRaises(ValueError):
            MODULE.validate_profile(self.policy, self.profile, self.response)

    def test_rejects_reactivated_formatting_rules(self):
        self.response['rules'].append({'key': 'php:S105'})
        with self.assertRaises(ValueError):
            MODULE.validate_profile(self.policy, self.profile, self.response)

    def test_rejects_missing_or_ambiguous_activation(self):
        for activations in [[], self.response['actives']['php:S103'] * 2]:
            with self.subTest(activations=activations):
                self.response['actives']['php:S103'] = activations
                with self.assertRaises(ValueError):
                    MODULE.validate_profile(self.policy, self.profile, self.response)

    def responses(self, profiles=None, complete=True):
        rules = {**self.response, 'total': len(self.response['rules']) + (0 if complete else 1)}
        return [io.BytesIO(json.dumps(body).encode()) for body in [
            {'profiles': [self.profile] if profiles is None else profiles}, rules,
        ]]

    def test_main_queries_project_profile_and_all_active_rules(self):
        with patch.dict(os.environ, {'SONAR_TOKEN': 'fixture-token'}), \
                patch.object(MODULE.urllib.request, 'urlopen', side_effect=self.responses()) as get, \
                patch('sys.stdout', new_callable=io.StringIO) as output:
            MODULE.main()
        requests = [urllib.parse.urlparse(call.args[0].full_url) for call in get.call_args_list]
        self.assertEqual([r.path for r in requests], [
            '/api/qualityprofiles/search', '/api/rules/search',
        ])
        self.assertEqual(urllib.parse.parse_qs(requests[0].query)['project'], [self.policy['project']])
        self.assertEqual(urllib.parse.parse_qs(requests[1].query)['qprofile'], [self.profile['key']])
        self.assertTrue(all(call.kwargs['timeout'] == 45 for call in get.call_args_list))
        self.assertIn('verified', output.getvalue())
        self.assertNotIn('fixture-token', output.getvalue())

    def test_main_requires_credential_before_network_access(self):
        with patch.dict(os.environ, {}, clear=True), \
                patch.object(MODULE.urllib.request, 'urlopen') as get:
            with self.assertRaisesRegex(SystemExit, 'SONAR_TOKEN is required'):
                MODULE.main()
            get.assert_not_called()

    def test_main_rejects_missing_or_multiple_profiles(self):
        for profiles in [[], [self.profile, self.profile]]:
            with self.subTest(profiles=profiles), \
                    patch.dict(os.environ, {'SONAR_TOKEN': 'fixture-token'}), \
                    patch.object(MODULE.urllib.request, 'urlopen', side_effect=self.responses(profiles)):
                with self.assertRaisesRegex(SystemExit, 'Cannot identify'):
                    MODULE.main()

    def test_main_rejects_partial_inventory(self):
        with patch.dict(os.environ, {'SONAR_TOKEN': 'fixture-token'}), \
                patch.object(MODULE.urllib.request, 'urlopen', side_effect=self.responses(complete=False)):
            with self.assertRaisesRegex(SystemExit, 'complete rule inventory'):
                MODULE.main()

    def test_main_fails_closed_on_api_failure(self):
        with patch.dict(os.environ, {'SONAR_TOKEN': 'fixture-token'}), \
                patch.object(MODULE.urllib.request, 'urlopen', side_effect=urllib.error.URLError('offline')):
            with self.assertRaises(urllib.error.URLError):
                MODULE.main()

    def test_command_line_entry_point_verifies_profile(self):
        with patch.dict(os.environ, {'SONAR_TOKEN': 'fixture-token'}), \
                patch.object(MODULE.urllib.request, 'urlopen', side_effect=self.responses()), \
                patch('sys.stdout', new_callable=io.StringIO):
            runpy.run_path(str(ROOT / '.github/scripts/check-sonar-profile.py'), run_name='__main__')


if __name__ == '__main__':
    unittest.main()
