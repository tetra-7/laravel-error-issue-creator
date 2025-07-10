<?php

return [

    /**
     * GitHub personal access token (scope: "repo") used to create issues
     */
    'github_token'    => env('GITHUB_TOKEN'),

    /**
     * Target repository in "owner/repo" format
     */
    'repository'      => env('GITHUB_REPO'),

    /**
     * Labels to apply to each newly created issue.
     * Read from a comma-separated env var, defaulting to ["bug"].
     */
    'labels'          => explode(',', env('GITHUB_LABELS', 'bug')),

    /**
     * Which HTTP status codes should trigger an issue.
     * Read from a comma-separated env var, defaults to [500].
     */
    'monitor_statuses' => array_map(
        'intval',
        explode(',', env('GITHUB_MONITOR_STATUSES', '500'))
    ),

    /**
     * Time-to-live (in seconds) for caching processed errors to prevent duplicate issues.
     */
    'cache_ttl'       => env('ERROR_ISSUE_CACHE_TTL', 3600),

];