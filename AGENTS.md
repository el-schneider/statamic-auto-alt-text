## Project Overview

**Statamic Auto Alt Text**. Automatically generates descriptive alt text for images in Statamic v6 using AI services (Moondream or OpenAI). `main` targets Statamic v6; the v1 line for Statamic v5 lives on `backport/*` branches.

## Development Commands

### Code Quality

```bash
npm run check   # prettier --check, pint --test
npm run fix     # the same two, writing
```

### Testing

```bash
./vendor/bin/pest
./vendor/bin/pest --filter=SomeTest
```

Browser tests live under `tests/Browser/` (Pest browser plugin).

### Integration Testing

Verifying control panel changes in a browser needs a Statamic app with this addon installed as a path repository.

## Contributing

- Comments say why, not what changed. History belongs in the PR.
- UI changes: verify in a real browser (agent-browser, Chrome DevTools) and say what you checked. No browser automation available — ask, don't guess.
- Add nothing you can derive or reuse.
- Fix the cause, not the reported symptom.
- No abstraction with a single caller.
- Let failures surface. No try/catch for tidiness.

## Off-Limits Files

- **`resources/dist/`** — Built by CI on push to `main`. Do NOT commit build output.
- **`CHANGELOG.md`** — Updated by CI on release. Do NOT edit.
