"""The conflict resolver accepts only the documented additive registration shapes."""
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

from resolve_coverage_conflict import PR, REQUEST, TEST, Refusal, resolve


def merge(path, base, ours, theirs):
    return resolve(path, [s.encode() for s in (base, ours, theirs)]).decode()


def loop(names):
    return ('for fixture in ' + names + '; do\n'
            '\tif [[ -f "tests/fixtures/Core/$fixture.php" ]]; then\n'
            '\t\trun_probe "$fixture.php" "$fixture"\n\tfi\ndone\n')


def guard(name, label):
    return (f'if [[ -f tests/fixtures/Core/{name}.php ]]; then\n'
            f'\trun_probe {name}.php {label}\nfi\n')


def request(fixture, trigger):
    return f"run_php_probe tests/fixtures/{fixture}.php '^({trigger}\\.php|tests/fixtures/{fixture}\\.php)$'\n"


def method(name):
    return f"    def test_{name}(self):\n        self.dispatch('{name}.php', '{name}.php')\n\n"


def harness(names):
    values = ', '.join(repr(name + '.php') for name in names.split())
    return ('    def dispatch(self, changed, expected):\n'
            f'        for script in [{values}]:\n'
            '            path = repository / \'tests/fixtures\' / script\n'
            '            path.parent.mkdir(parents=True, exist_ok=True)\n'
            "            path.write_text('<?php')\n")


class ResolverTest(unittest.TestCase):
    def test_competing_fixture_lists_preserve_each_branch_order(self):
        self.assertEqual(merge(PR, loop('A D'), loop('A B D'), loop('A D C E')), loop('A B D C E'))

    def test_independent_additions_in_same_gap_are_deterministic(self):
        self.assertEqual(merge(PR, loop('A'), loop('A B'), loop('A C')), loop('A B C'))
        self.assertEqual(merge(PR, loop('A'), loop('A C'), loop('A B')), loop('A B C'))

    def test_conflicting_removal_or_renaming_is_refused(self):
        with self.assertRaises(Refusal):
            merge(PR, loop('A D'), loop('A B'), loop('A C D'))

    def test_contradictory_order_is_refused(self):
        with self.assertRaises(Refusal):
            merge(PR, loop('A'), loop('A B C'), loop('A C B'))

    def test_guarded_registrations_remain_complete(self):
        base = loop('Base')
        expected = base + guard('A', 'label-a') + guard('B', 'label-b')
        self.assertEqual(merge(PR, base, base + guard('A', 'label-a'), base + guard('B', 'label-b')), expected)

    def test_label_clash_across_different_guarded_scripts_is_refused(self):
        base = loop('Base')
        with self.assertRaises(Refusal):
            merge(PR, base, base + guard('A', 'same'), base + guard('B', 'same'))

    def test_new_guard_cannot_clash_with_loop_label(self):
        base = loop('Base')
        with self.assertRaises(Refusal):
            merge(PR, base, base + guard('A', 'Base'), base + guard('B', 'other'))

    def test_request_registrations_keep_original_patterns(self):
        base = request('A', 'main')
        first, second = request('B', 'one'), request('C', 'two')
        self.assertEqual(merge(REQUEST, base, base + first, base + second), base + first + second)

    def test_unproven_request_order_is_refused(self):
        base = request('A', 'main')
        for second in [request('C', 'one'), request('C', 'one.*')]:
            with self.subTest(second=second), self.assertRaises(Refusal):
                merge(REQUEST, base, base + request('B', 'one'), base + second)

    def test_same_fixture_with_different_options_is_refused(self):
        base = request('A', 'main')
        with self.assertRaises(Refusal):
            merge(REQUEST, base, request('A', 'one'), request('A', 'two'))

    def test_nested_shell_context_is_refused(self):
        start = "if [[ -f test ]]; then\n"
        end = 'fi\n'
        with self.assertRaises(Refusal):
            merge(PR, start + end, start + guard('A', 'a') + end, start + guard('B', 'b') + end)

    def test_nonregistration_edits_in_the_same_hunk_are_refused(self):
        with self.assertRaises(Refusal):
            merge(PR, 'tool first\n', 'tool second\n', 'tool third\n')

    def test_disjoint_changes_still_go_through_git(self):
        base = 'first=1\n' + ('# unchanged\n' * 12) + 'last=1\n'
        self.assertEqual(merge(PR, base, base.replace('first=1', 'first=2'), base.replace('last=1', 'last=3')),
                         base.replace('first=1', 'first=2').replace('last=1', 'last=3'))

    def test_dispatch_methods_and_fixture_scaffolding_are_additive(self):
        top = 'class RequestDispatchTest:\n' + method('base')
        base = top + harness('Base')
        result = merge(TEST, base, top + method('one') + harness('Base One'), top + method('two') + harness('Base Two'))
        self.assertIn(method('one').strip(), result)
        self.assertIn(method('two').strip(), result)
        self.assertEqual(result.count("'One.php'"), 1)
        self.assertEqual(result.count("'Two.php'"), 1)

    def test_duplicate_method_names_are_refused(self):
        base = 'class RequestDispatchTest:\n' + method('base') + harness('Base')
        with self.assertRaises(Refusal):
            merge(TEST, base, base + method('one'), base + method('one').replace("'one.php'", "'different.php'"))

    def test_non_declarative_test_additions_are_refused(self):
        base = 'class RequestDispatchTest:\n' + method('base') + harness('Base')
        with self.assertRaises(Refusal):
            merge(TEST, base, base + '    def test_a(self):\n        configure_runtime()\n',
                  base + method('b'))

    def test_different_guard_locations_cannot_register_the_same_fixture_twice(self):
        base = loop('Base') + '# unrelated\n' * 15
        with self.assertRaises(Refusal):
            merge(PR, base, guard('X', 'a') + base, base + guard('X', 'b'))

    def test_new_static_label_cannot_shadow_a_computed_label(self):
        base = loop('Base') + '\nif [[ -f tests/fixtures/Core/Mode.php ]]; then\n'
        base += '\trun_probe Mode.php "result-$mode" "$mode"\nfi\n'
        with self.assertRaises(Refusal):
            merge(PR, base, base + guard('A', 'result-one'), base + guard('B', 'other'))

    def test_lookalike_loop_in_a_heredoc_is_refused(self):
        def doc(names):
            return "cat <<'DOC'\n" + loop(names) + 'DOC\n'
        with self.assertRaises(Refusal):
            merge(PR, doc('Base'), doc('Base A'), doc('Base B'))

    def test_different_commands_in_an_existing_test_are_refused(self):
        top = 'class RequestDispatchTest:\n'
        base = top + method('base') + harness('Base')
        with self.assertRaises(Refusal):
            merge(TEST, base, base.replace("'base.php'", "'changed.php'"),
                  base.replace("'base.php'", "'other.php'"))

    def test_an_order_sensitive_fixture_loop_is_refused(self):
        def content(names):
            return ('class RequestDispatchTest:\n' + harness(names)).replace(
                "path.write_text('<?php')", 'mutate_shared_state(script)')
        with self.assertRaises(Refusal):
            merge(TEST, content('Base'), content('Base A'), content('Base B'))

    def test_distinct_paths_with_the_same_fixture_basename_are_refused(self):
        top = 'class RequestDispatchTest:\n'
        with self.assertRaises(Refusal):
            merge(TEST, top + harness('Base'), top + harness('Base First/Shared'),
                  top + harness('Base Other/Shared'))

    def test_quoted_duplicate_request_paths_are_detected_in_disjoint_hunks(self):
        base = request('Original', 'original') + '# gap\n' * 10
        first, second = request('New', 'one'), request('New', 'two')
        second = second.replace(' tests/fixtures/New.php ', " 'tests/fixtures/New.php' ")
        with self.assertRaises(Refusal):
            merge(REQUEST, base, first + base, base + second)

    def test_cli_failure_has_empty_stdout_and_leaves_files_intact(self):
        with tempfile.TemporaryDirectory() as tmp:
            inputs = []
            for name, s in [('base', 'one'), ('ours', 'two'), ('theirs', 'three')]:
                p = Path(tmp, name)
                p.write_text(s + '\n')
                inputs.append(p)
            before = [p.read_bytes() for p in inputs]
            r = subprocess.run([sys.executable, str(Path(__file__).with_name('resolve_coverage_conflict.py')),
                                PR, *inputs], capture_output=True)
            self.assertNotEqual(r.returncode, 0)
            self.assertEqual(r.stdout, b'')
            self.assertTrue(r.stderr)
            self.assertEqual([p.read_bytes() for p in inputs], before)


if __name__ == '__main__':
    unittest.main()
