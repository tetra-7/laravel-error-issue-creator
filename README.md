# Laravel Error Issue Creator

A Laravel package that automatically opens a GitHub issue whenever an HTTP 500 error occurs in production, then prevents duplicate issues by tracking occurrences and updating the same issue.

## Features

- **Auto-discovery**: ServiceProvider is automatically registered by Laravel.
- **HTTP 500 interception**: Hooks into the exception handler to catch server errors.
- **GitHub integration**: Uses the GitHub API to create issues or add comments.
- **Deduplication**: Only one issue per unique error; subsequent occurrences post comments with a counter.
- **Configurable**: Customize token, repository, labels, cache TTL via published configuration.

## Requirements

- PHP ^7.4 or ^8.0
- Laravel ^8.0, ^9.0, ^10.0, ^11.0 or ^12.0
- A GitHub Personal Access Token with **issues: read & write** permission

## Installation

Install via Composer:

```bash
composer require dhippo-vendor/laravel-error-issue-creator
```

Once published on Packagist, remove any local path repository entries.

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish \
    --provider="DhippoVendor\ErrorIssueCreator\ErrorIssueServiceProvider" \
    --tag=config
```

Edit `config/error-issue-creator.php`:

```php
return [
    // GitHub personal access token (scope: issues: read & write)
    'github_token' => env('GITHUB_TOKEN'),

    // GitHub repository (format: owner/repository)
    'repository'   => env('GITHUB_REPO'),

    // Labels to apply on new issues (comma-separated or array via env)
    'labels'       => explode(',', env('GITHUB_LABELS', 'bug')),

    // Cache TTL in seconds to prevent duplicate issues
    'cache_ttl'    => env('ERROR_ISSUE_CACHE_TTL', 3600),
];
```

Add to your `.env`:

```dotenv
GITHUB_TOKEN=ghp_xxx...
GITHUB_REPO=your-org/your-repo
GITHUB_LABELS=bug,production-error,high-priority
ERROR_ISSUE_CACHE_TTL=3600
APP_ENV=production
```

With `GITHUB_LABELS`, you can specify any labels you prefer. Provide a comma-separated list in your `.env`, and the package will apply all listed labels to new issues.

## Usage

1. In production mode, any HTTP 500 error triggers the package.
2. **First occurrence**: opens a new GitHub issue titled `[500] Error message…` with the configured labels.
3. **Subsequent occurrences** within the cache TTL: adds a comment on the existing issue, updating the occurrence count.

### Testing in development

Define a route to throw a fixed exception:

```php
Route::get('/test-error-dup', function () {
    throw new Exception('Fixed error for dedupe test');
});
```

Call `/test-error-dup` multiple times within the TTL. You should see one issue created, then comments appended with occurrence counts.

## Advanced Customization

- **Labels**: Controlled by the `GITHUB_LABELS` env variable.
- **Cache duration**: Override `ERROR_ISSUE_CACHE_TTL` in seconds.
- **Error filter**: Modify `ErrorIssueServiceProvider` to catch different exception types or status codes.

## Troubleshooting

- **No issues created**: Ensure `APP_ENV=production`, the PAT has correct scopes, and config is published.
- **Multiple issues**: Verify the exception message, file, and line remain identical for deduplication.
- **Queue errors**: Default `QUEUE_CONNECTION=sync`. For async, run `php artisan queue:work`.


## License

This package is open-sourced under the [MIT License](LICENSE).
