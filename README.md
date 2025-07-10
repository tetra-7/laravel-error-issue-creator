# Laravel Error Issue Creator

A Laravel package by **Tetra 7** that automatically opens a GitHub issue whenever an HTTP 500 error occurs in production, then prevents duplicate issues by tracking occurrences and updating the same issue.

## Features

* **Auto-discovery**: ServiceProvider is registered automatically by Laravel.
* **HTTP 500 interception**: Hooks into the exception handler to catch server errors.
* **GitHub integration**: Uses the GitHub API to create issues or add comments.
* **Deduplication**: Only one issue per unique error; subsequent occurrences post comments with a counter.
* **Configurable**: Customize token, repository, labels, cache TTL via published configuration.

## Requirements

* PHP ^7.4 or ^8.0
* Laravel ^8.0, ^9.0, ^10.0, ^11.0 or ^12.0
* A GitHub Personal Access Token with **issues: read & write** permission

## Installation

Install via Composer:

```bash
composer require tetra7/laravel-error-issue-creator
```

> Once your package is published on Packagist under `tetra7/laravel-error-issue-creator`, you can remove any local path repository entries in your `composer.json`.

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish \
    --provider="Tetra7\ErrorIssueCreator\ErrorIssueServiceProvider" \
    --tag=config
```

Edit `config/error-issue-creator.php` as needed:

```php
return [

    // GitHub personal access token (scope: issues: read & write)
    'github_token'    => env('GITHUB_TOKEN'),

    // GitHub repository (format: owner/repository)
    'repository'      => env('GITHUB_REPO'),

    // Labels to apply on new issues
    // Provide a comma-separated list in .env, e.g. "bug,production-error"
    'labels'          => explode(',', env('GITHUB_LABELS', 'bug')),

    // Cache TTL in seconds to prevent duplicate issues
    'cache_ttl'       => env('ERROR_ISSUE_CACHE_TTL', 3600),

];
```

Add the following to your `.env`:

```dotenv
GITHUB_TOKEN=ghp_xxx...
GITHUB_REPO=your-org/your-repo
GITHUB_LABELS=bug,production-error,high-priority
ERROR_ISSUE_CACHE_TTL=3600
APP_ENV=production
```

## Usage

1. **Production mode** (`APP_ENV=production`):
   Any HTTP 500 error triggers the package.

2. **First occurrence**:
   Opens a new GitHub issue titled

   ```
   [500] Error message…
   ```

   with the configured labels.

3. **Subsequent occurrences** within the cache TTL:
   Adds a comment on the existing issue, updating the occurrence count.

## Testing in Development

Define a test route that throws a consistent exception:

```php
// routes/web.php

Route::get('/test-error-dup', function () {
    throw new Exception('Fixed error for dedupe test');
});
```

Hit `/test-error-dup` multiple times (within the TTL). You should see:

* **First request**: one issue created.
* **Next requests**: comments appended to that same issue, with increasing counters.

## Advanced Customization

* **Labels**: controlled by the `GITHUB_LABELS` env variable.
* **Cache duration**: override `ERROR_ISSUE_CACHE_TTL` (in seconds).
* **Error filter**: modify `ErrorIssueServiceProvider` to catch other exception types or additional HTTP status codes by adding a `monitor_statuses` config option.

## Troubleshooting

* **No issues created**:

    * Verify `APP_ENV=production`.
    * Confirm your Personal Access Token has **issues: read & write** scopes.
    * Make sure you’ve published and edited the package config.

* **Multiple issues for the same error**:

    * Ensure the exception message, file, and line remain identical on each occurrence.
    * Check your `cache_ttl` hasn’t expired between requests.

* **No 404/403 logging** (if you’ve added those statuses):

    * Laravel by default doesn’t report HTTP exceptions. Either remove `HttpExceptionInterface` from your app’s `$dontReport`, or switch your package’s callback from `reportable()` to `renderable()` so it fires for 404s.

* **Queue issues**:

    * By default, the package uses `QUEUE_CONNECTION=sync`.
    * For asynchronous processing, set `QUEUE_CONNECTION=database` or `redis` and run a worker:

      ```bash
      php artisan queue:work
      ```

## Contributing

Pull requests welcome! Please adhere to PSR-12 coding standards, write tests for new features, and update this README if you change any behavior.

## License

This package is open-sourced under the [MIT License](LICENSE).
