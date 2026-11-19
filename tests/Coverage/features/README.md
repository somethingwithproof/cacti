# PHP coverage registrations

Add a JSON array in `tests/Coverage/features/<feature>.json` alongside the
fixture change. For example, the two-factor and password-reset pages use
[constant-time-guards.json](constant-time-guards.json).

Each entry selects a PHP fixture under `tests/` by its repository-relative path.

- `native: true` runs the fixture every time the native changed-line gate runs.
  It must write Xdebug evidence to `CACTI_COVERAGE_FILE`.
- `request_paths` selects paths for the request coverage gate. Each glob matches
  the full repository-relative path, case-sensitively, and `*` can match a slash.
  Editing the fixture or its manifest also selects the entry. The fixture can
  write request evidence through `CACTI_PR_RAW_COVERAGE`.
- `cases` lists argument arrays. For example, `[[], ["edit", "", "two words"]]`
  runs the fixture twice and preserves all three arguments in the second call.
  Omitting `cases` runs the fixture once without arguments.

Select either gate or both. Browser, Docker, and other existing shell
registrations continue to use their existing runners.
