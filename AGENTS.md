# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Statamic Auto Alt Text**. Automatically generate descriptive alt text for images in Statamic v5 using AI services (Moondream or OpenAI GPT-4 Vision)

## Architecture

### Core Components

**Frontend (Vue/TypeScript)**

- `resources/js/addon.ts`: Main addon entry point, registers field action
- Vue components are mounted in Statamic's control panel UI

## Laravel Auto-Registration

### Event Listeners

Laravel automatically discovers event listeners in `app/Listeners/` (or `src/Listeners/` for packages when configured):

- Methods starting with `handle` or `__invoke` are registered as listeners
- The event is determined by the type-hint in the method signature
- No manual registration needed in ServiceProvider

## Development Commands

### Code Quality

Use prettier and pint to check and fix code quality.

```bash
prettier --check .
prettier --write .
pint check
pint fix
```

### Testing

#### Unit & Feature Tests

Use pest to run automatic tests. If unsure always use context7 or web search to find the latest docs. Pest 4 is quite new.

```bash
./vendor/bin/pest       # Run all tests
./vendor/bin/pest --filter=SomeTest  # Run specific test
```

#### Integration Testing with Live App

A full Laravel test app is available at `../statamic-auto-alt-text-test` and can be accessed at `http://statamic-auto-alt-text-test.test`.

**Credentials:**

- Email: `claude@claude.ai`
- Password: `claude`
- Login URL: `http://statamic-auto-alt-text-test.test/cp`

You can test the addon in the Statamic control panel using these credentials. For programmatic testing:

**Browser approach** (recommended for complex interactions):

- Use your agent-browser skill

**curl approach** (faster for API-only testing):

- Obtain a session cookie via login, then use curl to test API endpoints
- Example: `curl -b "cookies.txt" http://statamic-auto-alt-text-test.test/api/magic-actions/...`

See the logs at `../statamic-auto-alt-text-test/storage/logs/laravel.log` when debugging errors.
