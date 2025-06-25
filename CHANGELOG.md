# Changelog

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
