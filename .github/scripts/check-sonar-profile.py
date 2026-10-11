#!/usr/bin/env python3
"""Read-only verification of the project's PHP profile before analysis."""
import base64
import json
import os
from pathlib import Path
import urllib.parse
import urllib.request


def validate_profile(policy, profile, response):
    if profile['name'] != policy['name']:
        raise ValueError('The project must use the documented Cacti PHP profile')
    if set(policy['deactivate']) != {'php:S1808', 'php:S105'}:
        raise ValueError('Only the incompatible PSR-2 formatting rules may be omitted')
    active = {rule['key'] for rule in response['rules']}
    if not set(policy['required_rules']) <= active:
        raise ValueError('Required PHP rules are missing from the profile')
    if set(policy['deactivate']) & active:
        raise ValueError('PSR-2 rules conflict with Cacti PHP-CS-Fixer conventions')
    expected = {**policy['configure'], 'php:S103': {'maximumLineLength': '120'}}
    for rule, parameters in expected.items():
        activations = [item for item in response['actives'].get(rule, [])
                       if item['qProfile'] == profile['key']]
        if len(activations) != 1:
            raise ValueError(f'Cannot verify activation of {rule}')
        actual = {item['key']: item['value'] for item in activations[0]['params']}
        if any(actual.get(key) != value for key, value in parameters.items()):
            raise ValueError(f'Profile parameters differ from the reviewed policy: {rule}')


def main():
    policy = json.loads((Path(__file__).resolve().parents[1] / 'sonar/php-profile.json').read_text())
    token = os.environ.get('SONAR_TOKEN')
    if not token:
        raise SystemExit('SONAR_TOKEN is required for read-only profile verification')

    def get(endpoint, parameters):
        query = urllib.parse.urlencode(parameters)
        request = urllib.request.Request(
            f'https://sonarcloud.io/api/{endpoint}?{query}',
            headers={'Authorization': 'Basic ' + base64.b64encode((token + ':').encode()).decode()},
        )
        with urllib.request.urlopen(request, timeout=45) as response:
            return json.load(response)

    profiles = get('qualityprofiles/search', {
        'organization': policy['organization'], 'project': policy['project'], 'language': 'php',
    })['profiles']
    if len(profiles) != 1:
        raise SystemExit('Cannot identify the project PHP profile')
    rules = get('rules/search', {
        'organization': policy['organization'], 'qprofile': profiles[0]['key'],
        'activation': 'true', 'f': 'actives', 'ps': '500',
    })
    if rules['total'] != len(rules['rules']):
        raise SystemExit('Profile verification requires the complete rule inventory')
    validate_profile(policy, profiles[0], rules)
    print('Cacti PHP profile verified; required rules and line limit retained.')


if __name__ == '__main__':
    main()
