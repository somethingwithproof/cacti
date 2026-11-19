#!/usr/bin/env python3
"""Resolve independent additions to the three Cacti coverage registration files."""

import argparse
import ast
from collections import Counter
from graphlib import TopologicalSorter, CycleError
from pathlib import Path
import re
import shlex
import subprocess
import sys
import tempfile
import textwrap

PR = 'tests/tools/run_pr_change_tests.sh'
REQUEST = 'tests/tools/run_request_coverage.sh'
TEST = 'tests/tools/test_request_coverage_dispatch.py'
PATHS = (PR, REQUEST, TEST)
NAME = r'[A-Za-z][A-Za-z0-9_-]*'
PHP = rf'(?:{NAME}/)*{NAME}\.php'
LOOP = re.compile(rf'^for fixture in ({NAME}(?: {NAME})*); do$', re.M)
GUARD = re.compile(rf'if \[\[ -f tests/fixtures/Core/({PHP}) \]\]; then\n((?:\trun_probe [^\n]+\n)+)fi\n')
CALL = re.compile(rf'\trun_probe ({PHP}) ({NAME})(?: [A-Za-z0-9_./-]+)*\n')
REQUEST_CALL = re.compile(rf"run_php_probe (tests/(?:fixtures|Unit/fixtures)/{PHP}) '([^'\n]+)'\n")
SAFE_PATTERN = re.compile(r'\^\(([A-Za-z0-9_/|.\\-]+)\)(\$?)')
MARKER = '__CACTI_COVERAGE_REGISTRATIONS_TO_RESTORE__'


class Refusal(ValueError):
    """The three inputs do not establish a safe automatic resolution."""


def require(condition, message):
    if not condition:
        raise Refusal(message)


def ordered(keys):
    """Keep the order from each input; break ties between independent entries by key."""
    graph = TopologicalSorter()
    for sequence in keys:
        require(len(sequence) == len(set(sequence)), 'duplicate registration in one input')
        for index, item in enumerate(sequence):
            graph.add(item, *sequence[max(0, index - 1):index])
    try:
        graph.prepare()
    except CycleError as error:
        raise Refusal('registration orders disagree') from error
    result = []
    while graph.is_active():
        ready = sorted(graph.get_ready())
        result.extend(ready)
        graph.done(*ready)
    return result


def merge_names(original, ours, theirs):
    for branch in (ours, theirs):
        require([name for name in branch if name in original] == original,
                'existing registrations were removed, renamed or reordered')
    return ordered((original, ours, theirs))


def merge_file(versions):
    with tempfile.TemporaryDirectory(prefix='cacti-registration-') as temporary:
        paths = []
        for name, data in zip(('base', 'ours', 'theirs'), versions):
            path = Path(temporary, name)
            path.write_bytes(data)
            paths.append(str(path))
        result = subprocess.run(
            ['git', 'merge-file', '--stdout', '--diff3', '-L', 'ours', '-L', 'base', '-L', 'theirs',
             '--', paths[1], paths[0], paths[2]], capture_output=True)
    require(0 <= result.returncode <= 127, 'git merge-file failed')
    return result.returncode, result.stdout


def python_class(source):
    tree = ast.parse(source)
    classes = [node for node in tree.body if isinstance(node, ast.ClassDef)
               and node.name == 'RequestDispatchTest']
    require(len(classes) <= 1, 'ambiguous dispatch test class')
    return classes[0] if classes else None


def fixture_slot(path, source):
    """Return the span and names of the runner loop or dispatch harness fixture list."""
    if path == PR:
        loops = list(LOOP.finditer(source))
        if not loops:
            return None
        require(len(loops) == 1, 'ambiguous fixture loop')
        loop = loops[0]
        body = ('\n\tif [[ -f "tests/fixtures/Core/$fixture.php" ]]; then\n'
                '\t\trun_probe "$fixture.php" "$fixture"\n\tfi\ndone')
        require(source[loop.end():].startswith(body + '\n'), 'unknown fixture loop body')
        top_level_registration(path, source, loop[0] + '\n')
        return loop.span(1), loop[1].split(' ')
    if path == TEST:
        cls = python_class(source)
        if cls is None:
            return None
        candidates = []
        for method in cls.body:
            if not isinstance(method, ast.FunctionDef) or method.name != 'dispatch':
                continue
            for node in ast.walk(method):
                if (isinstance(node, ast.For) and isinstance(node.target, ast.Name)
                        and node.target.id == 'script' and isinstance(node.iter, ast.List)):
                    candidates.append(node)
        if not candidates:
            return None
        require(len(candidates) == 1, 'ambiguous dispatch fixture collection')
        node = candidates[0]
        prototype = ast.parse("path = repository / 'tests/fixtures' / script\n"
                              "path.parent.mkdir(parents=True, exist_ok=True)\n").body
        require(not node.orelse and len(node.body) == 3
                and all(ast.dump(a) == ast.dump(b) for a, b in zip(node.body, prototype)),
                'dispatch loop has ordering-sensitive setup')
        write = node.body[2]
        require(isinstance(write, ast.Expr) and isinstance(write.value, ast.Call)
                and ast.unparse(write.value.func) == 'path.write_text' and not write.value.keywords
                and len(write.value.args) == 1 and isinstance(write.value.args[0], ast.Constant)
                and isinstance(write.value.args[0].value, str),
                'dispatch loop does more than create isolated fixture placeholders')
        expr = node.iter
        require(all(isinstance(e, ast.Constant) and isinstance(e.value, str)
                    and re.fullmatch(PHP, e.value) for e in expr.elts), 'nonliteral dispatch fixture')
        raw = ast.get_source_segment(source, expr)
        require(re.fullmatch(r"""\[\s*(?:['"][A-Za-z0-9_./-]+['"]\s*,?\s*)*\]""", raw),
                'fixture list contains comments or expressions')
        # AST columns count UTF-8 bytes. Slice line bytes before converting to characters.
        lines = source.splitlines(keepends=True)
        start = sum(map(len, lines[:expr.lineno - 1])) + len(
            lines[expr.lineno - 1].encode()[:expr.col_offset].decode())
        return (start, start + len(raw)), [e.value for e in expr.elts]
    return None


def mask_slots(path, versions):
    slots = [fixture_slot(path, s) for s in versions]
    if any(slot is None for slot in slots):
        return versions, None
    names = [slot[1] for slot in slots]
    if names[1] == names[0] or names[2] == names[0]:
        return versions, None
    merged = merge_names(*names)
    # Preserve an input's original spelling whenever it already has the desired order.
    replacement = None
    for source, (span, entries) in zip(versions, slots):
        if entries == merged:
            replacement = source[slice(*span)]
            break
    if replacement is None:
        if path == PR:
            replacement = ' '.join(merged)
        else:
            require(len({s.split('/')[-1] for s in merged}) == len(merged),
                    'different fixture paths have the same reported name')
            indent = ' ' * (slots[0][0][0] - versions[0].rfind('\n', 0, slots[0][0][0]))
            replacement = '[' + (',\n' + indent).join(repr(s) for s in merged) + ']'
    return [s[:span[0]] + MARKER + s[span[1]:] for s, (span, _) in zip(versions, slots)], replacement


def python_methods(source):
    lines = source.splitlines(keepends=True)
    entries = {}
    cls = python_class(source)
    if cls is None:
        return entries
    for node in cls.body:
        if isinstance(node, ast.FunctionDef):
            require(node.name not in entries, 'duplicate Python method: ' + node.name)
            entries[node.name] = ''.join(lines[node.lineno - 1:node.end_lineno])
    return entries


def safe_method(node):
    """Restrict automatic composition to declarative, isolated dispatcher assertions."""
    if (not isinstance(node, ast.FunctionDef) or not node.name.startswith('test_')
            or node.decorator_list or node.returns or node.args.defaults or node.args.kwonlyargs
            or node.args.vararg or node.args.kwarg or node.args.posonlyargs
            or [a.arg for a in node.args.args] != ['self'] or node.args.args[0].annotation):
        return False
    allowed = (ast.Expr, ast.For, ast.With, ast.withitem, ast.Call, ast.Attribute,
               ast.Name, ast.Load, ast.Store, ast.Constant, ast.Tuple, ast.List, ast.keyword)
    for statement in node.body:
        for item in ast.walk(statement):
            if not isinstance(item, allowed):
                return False
            if isinstance(item, ast.Attribute) and not (
                    isinstance(item.value, ast.Name) and item.value.id == 'self'
                    and item.attr in ('dispatch', 'subTest') and isinstance(item.ctx, ast.Load)):
                return False
            if isinstance(item, ast.Call) and not isinstance(item.func, ast.Attribute):
                return False
    return True


def top_level_registration(path, source, block):
    """Accept shell additions only in a recognizable top-level registration region."""
    require(source.count(block) == 1, 'registration text is not unique')
    prefix = source[:source.index(block)]
    for line in prefix.splitlines():
        if line.lstrip().startswith('#'):
            continue
        require(not re.search(r'(?<!<)<<(?!<)', line) and not line.endswith('\\'),
                'cannot establish shell context across a heredoc or continuation')
        try:
            shlex.split(line, comments=True)
        except ValueError as error:
            raise Refusal('cannot establish shell context across multiline quotes') from error
    helper = 'run_probe' if path == PR else 'run_php_probe'
    definition = re.search(rf'^{helper}\(\) \{{\n', prefix, re.M)
    if definition:
        require(prefix.count(helper + '() {') == 1, 'ambiguous runner helper')
        finish = prefix.find('\n}\n', definition.end())
        require(finish >= 0, 'registration inside a helper')
        prefix = prefix[finish + 3:]
    stack = []
    for line in prefix.splitlines():
        value = line.strip()
        if not value or value.startswith('#'):
            continue
        require(not value.endswith('\\') and not re.search(r'(?<!<)<<(?!<)', value),
                'unrecognized multiline shell context')
        if value.startswith('for ') and value.endswith('; do'):
            stack.append('done')
        elif value.startswith('if [[') and value.endswith('; then'):
            stack.append('fi')
        elif value in ('done', 'fi'):
            require(stack and stack.pop() == value, 'unbalanced runner registration region')
        elif re.fullmatch(r'(?:run_probe|run_php_probe) [^\n]+', value):
            tokens = shlex.shlex(value, posix=True, punctuation_chars=';&|')
            tokens.whitespace_split = True
            require(not any(token in (';', '&&', '||', '|', '&') for token in tokens),
                    'compound command in registration region')
        elif re.fullmatch(r"[A-Za-z_]\w*=(?:[0-9]+|'[^']*'|\$\(\([A-Za-z_0-9 +]+\)\))", value):
            continue
        else:
            raise Refusal('unrecognized shell registration region')
    require(not stack, 'new registration is nested in another command')


def units(path, text, source):
    """Recognize complete registration units, retaining every original command byte."""
    entries = {}
    if path == TEST:
        methods = python_methods(source)
        lines = text.splitlines(keepends=True)
        for node in ast.parse(textwrap.dedent(text)).body:
            require(safe_method(node), 'overlap is not a plain dispatcher test addition')
            block = ''.join(lines[node.lineno - 1:node.end_lineno])
            require(methods.get(node.name) == block and source.count(block) == 1 and node.name not in entries,
                    'ambiguous or partial Python method')
            entries[node.name] = block
        # AST omits comments and decorators; refuse any unaccounted non-whitespace.
        remaining = text
        for block in entries.values():
            remaining = remaining.replace(block, '', 1)
        require(not remaining.strip(), 'unrecognized text between test methods')
        return entries
    pattern = GUARD if path == PR else REQUEST_CALL
    remaining = text
    while remaining:
        remaining = remaining.lstrip('\n')
        if not remaining:
            break
        entry = pattern.match(remaining)
        require(entry, 'overlap includes nonregistration shell code')
        key = entry[1]
        top_level_registration(path, source, entry[0])
        if path == PR:
            for line in entry[2].splitlines(keepends=True):
                call = CALL.fullmatch(line)
                require(call and call[1] == key, 'guard and fixture invocation differ')
        require(key not in entries, 'duplicate fixture registration')
        entries[key] = entry[0]
        remaining = remaining[entry.end():]
    return entries


def request_prefixes(registration):
    """Prove disjointness for literal anchored alternatives only (also valid as prefixes)."""
    pattern = SAFE_PATTERN.fullmatch(REQUEST_CALL.fullmatch(registration)[2])
    require(pattern, 'cannot prove request selectors are independent')
    terms = pattern[1].split('|')
    require(all(re.fullmatch(r'(?:[A-Za-z0-9_/-]|\\\.)+', t) for t in terms),
            'request selector contains a nonliteral expression')
    return [term.replace(r'\.', '.') for term in terms]


def combine_hunk(path, before, ours, theirs, sources):
    prefix = []
    suffix = []
    while before and ours and theirs and before[0] == ours[0] == theirs[0]:
        prefix.append(before.pop(0))
        ours.pop(0)
        theirs.pop(0)
    while before and ours and theirs and before[-1] == ours[-1] == theirs[-1]:
        suffix.append(before.pop())
        ours.pop()
        theirs.pop()
    require(not ''.join(before).strip(), 'overlapping change modifies an existing statement')
    left = units(path, ''.join(ours), sources[1])
    right = units(path, ''.join(theirs), sources[2])
    require(left and right, 'overlap is not two sets of complete additions')
    for key in left.keys() & right.keys():
        require(left[key] == right[key], 'two different registrations use the same name')
    if path == REQUEST:
        for key in left.keys() - right.keys():
            for other in right.keys() - left.keys():
                for a in request_prefixes(left[key]):
                    for b in request_prefixes(right[other]):
                        require(not (a.startswith(b) or b.startswith(a)),
                                'request selectors overlap or have unproven ordering')
    blocks = {**left, **right}
    separator = '\n' if path == TEST else ''
    return (''.join(prefix) + ('\n' if ours[0] == '\n' or theirs[0] == '\n' else '')
            + separator.join(blocks[k] for k in ordered((list(left), list(right))))
            + ('\n' if ours[-1] == '\n' or theirs[-1] == '\n' else '') + ''.join(reversed(suffix)))


def check_names(path, source):
    if path == TEST:
        names = Counter(python_methods(source).keys())
        cls = python_class(source)
        for method in cls.body if cls else []:
            if not isinstance(method, ast.FunctionDef) or method.name != 'dispatch':
                continue
            for node in ast.walk(method):
                if (isinstance(node, ast.For) and isinstance(node.target, ast.Name)
                        and node.target.id == 'script' and isinstance(node.iter, ast.List)):
                    names.update('fixture:' + e.value.rsplit('/', 1)[-1] for e in node.iter.elts
                                 if isinstance(e, ast.Constant) and isinstance(e.value, str))
        return names
    names = Counter()
    command = 'run_probe' if path == PR else 'run_php_probe'
    for line in source.splitlines():
        if not line.lstrip().startswith(command + ' '):
            continue
        parts = shlex.split(line, comments=True)
        if len(parts) < 3:
            continue
        if path == REQUEST:
            names[parts[1]] += 1
        elif re.fullmatch(NAME, parts[2]):
            names[parts[2]] += 1
    if path == PR:
        for loop in LOOP.finditer(source):
            for fixture in loop[1].split():
                names[fixture] += 1
                names['fixture:' + fixture + '.php'] += 1
        names.update('fixture:' + name for name in re.findall(
            rf'^if \[\[ -f tests/fixtures/Core/({PHP}) \]\]; then$', source, re.M))
    return names


def check_dynamic_labels(base, merged, labels):
    """A static registration must not overwrite another probe's computed evidence file."""
    new = {key for key in set(labels) - set(check_names(PR, base)) if not key.startswith('fixture:')}
    if not new:
        return
    for line in merged.splitlines():
        parts = line.split()
        if len(parts) < 3 or parts[0] != 'run_probe' or '$' not in parts[2]:
            continue
        if parts[1:3] == ['"$fixture.php"', '"$fixture"']:
            continue  # Each name from this loop is already checked as a static label.
        label = parts[2].strip('"')
        fragments = re.split(r'(\$[A-Za-z_]\w*|\$\{[A-Za-z_]\w*\})', label)
        require(all(re.fullmatch(r'[A-Za-z0-9_.-]*', part) for part in fragments[::2]),
                'unknown computed evidence label')
        pattern = ''.join('.*' if index % 2 else re.escape(part)
                          for index, part in enumerate(fragments))
        require(not any(re.fullmatch(pattern, item) for item in new),
                'new registration collides with a computed evidence label')


def resolve(path, versions):
    require(path in PATHS, 'unsupported path')
    require(all(b'\0' not in v and not re.search(rb'^(?:<{7}|>{7}|={7}|\|{7})(?: |\r?$)', v, re.M)
                for v in versions), 'binary input or pre-existing merge markers')
    status, result = merge_file(versions)
    sources = [v.decode('utf-8') for v in versions]
    if status:
        require(all('\r' not in s and MARKER not in s for s in sources),
                'unsupported line endings or reserved marker')
        masked, replacement = mask_slots(path, sources)
        if replacement is not None:
            status, result = merge_file([s.encode() for s in masked])
        lines = result.decode().splitlines(keepends=True)
        out = []
        i = 0
        resolved_hunks = 0
        while i < len(lines):
            if lines[i] != '<<<<<<< ours\n':
                out.append(lines[i])
                i += 1
                continue
            middle = lines.index('||||||| base\n', i + 1)
            divider = lines.index('=======\n', middle + 1)
            end = lines.index('>>>>>>> theirs\n', divider + 1)
            out.append(combine_hunk(path, lines[middle + 1:divider], lines[i + 1:middle],
                                    lines[divider + 1:end], sources))
            i = end + 1
            resolved_hunks += 1
        require(not status or resolved_hunks > 0, 'unrecognized Git conflict format')
        merged = ''.join(out)
        if replacement is not None:
            require(merged.count(MARKER) == 1, 'fixture list no longer has a unique location')
            merged = merged.replace(MARKER, replacement)
        result = merged.encode()
    output = result.decode()
    prior = check_names(path, sources[0])
    for source in (*sources[1:], output):
        for name, count in check_names(path, source).items():
            require(count <= max(prior[name], 1), 'duplicate fixture, output label or method: ' + name)
    if path == PR:
        check_dynamic_labels(sources[0], output, check_names(PR, output))
    if path != TEST:
        syntax = subprocess.run(['bash', '--noprofile', '--norc', '-n'], input=result,
                                env={}, capture_output=True)
        require(syntax.returncode == 0, 'merged shell script does not parse')
    return result


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('path', choices=PATHS)
    parser.add_argument('versions', nargs=3, type=Path, metavar='FILE', help='base, ours, theirs')
    args = parser.parse_args()
    try:
        data = resolve(args.path, [p.read_bytes() for p in args.versions])
    except (ValueError, SyntaxError, OSError) as error:
        print(f'Automatic resolution refused: {error}', file=sys.stderr)
        return 1
    sys.stdout.buffer.write(data)
    return 0


if __name__ == '__main__':
    sys.exit(main())
