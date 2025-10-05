# Changelog

## v0.5.3 - 2025-10-05

### What's new

- Add comprehensive testing infrastructure with Pest 4
- Add GitHub Actions CI workflow for automated testing
- Add browser tests for UI interactions
- Add feature tests for asset event handling

### What's fixed

- Remove parallel processing to improve reliability
- Save assets quietly to prevent premature alt text generation
- Improve OpenAI service parameter handling (which should have been included in v0.5.2)

**Full Changelog**: https://github.com/el-schneider/statamic-auto-alt-text/compare/v0.5.2...v0.5.3

## v0.5.2 - 2025-09-22

### What's new

- Add `params` config option to support reasoning models and enhance versatility

This release adds support for configurable parameters in OpenAI API calls, designed to work with reasoning models like OpenAI's o1 and gpt-5 series that require setting `max_completion_tokens` instead of `max_tokens`.

**Full Changelog**: https://github.com/el-schneider/statamic-auto-alt-text/compare/v0.5.1...v0.5.2

## v0.5.1 - 2025-09-06

### What's Changed

#### Bug Fixes

- Fixed issue with stale data when triggering job on asset with existing caption

#### Maintenance

- Updated gitignore configuration

**Full Changelog**: https://github.com/el-schneider/statamic-auto-alt-text/compare/v0.5.0...v0.5.1

## v0.5.0 - 2025-07-13

### What's new

- Antlers templating support for dynamic prompts with asset data
- Configurable system message to OpenAI service for better prompt control

**Full Changelog**: https://github.com/el-schneider/statamic-auto-alt-text/compare/v0.4.0...v0.5.0

## v0.4.0 - Add Asset Filtering - 2025-06-25

### What's new

- **new**: Individual asset ignore field for granular exclusion control
- **new**: Global asset exclusion patterns to filter assets by filename or path
- **new**: Container-specific filtering for targeted exclusion rules

**Full Changelog**: https://github.com/el-schneider/statamic-auto-alt-text/compare/v0.3.2...v0.4.0

## v0.3.2 - 2025-06-20

### What's new

- add `---dispatch-jobs` flag to allow asynchronous processing of alt text generation

**Full Changelog**: https://github.com/el-schneider/statamic-auto-alt-text/compare/v0.3.1...v0.3.2

## v0.3.1 - 2025-06-12

### What's fixed

- fix wrong permission checks, #1

**Full Changelog**: https://github.com/el-schneider/statamic-auto-alt-text/compare/v0.3.0...v0.3.1

## v0.3.0 - 2025-06-04

### What's new

- **breaking**: Command is now nested under statamic namespace - use `php please auto-alt:generate` instead of `php artisan auto-alt:generate`
- **improvement**: OpenAI default model changed from `gpt-4-turbo` to `gpt-4.1-mini` for better efficiency
- **new**: Added support for OpenAI 'detail' parameter (low/high/auto) for vision API
- **improvement**: Increased default max image dimension from 1920px to 2048px
- **improvement**: Use base64 images with OpenAI for convenience and better reliability

**Full Changelog**: https://github.com/el-schneider/statamic-auto-alt-text/compare/v0.2.0...v0.3.0
