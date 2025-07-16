<?php

namespace Tetra7\ErrorIssueCreator\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Job responsible for creating or updating a GitHub issue when an error occurs.
 *
 * - Creates a new issue on first occurrence of an error.
 * - Posts a comment to an existing issue on subsequent occurrences,
 *   incrementing the occurrence count.
 */
class CreateGitHubIssueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Serializable payload containing error details and cache state.
     *
     * @var array{
     *     key: string,
     *     message: string,
     *     file: string,
     *     line: int,
     *     trace: string,
     *     status: int,
     *     cached: array{issue:int,count:int}|null
     * }
     */
    protected array $data;

    /**
     * Initialize the job with error data.
     *
     * @param  array{
     *     key: string,
     *     message: string,
     *     file: string,
     *     line: int,
     *     trace: string,
     *     status: int,
     *     cached: array{issue:int,count:int}|null
     * }  $data  Error details and optional cache data.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job: create or comment on a GitHub issue.
     */
    public function handle(): void
    {
        Log::info('[ErrorIssueCreator] Handling error', [
            'message' => $this->data['message'],
            'file'    => $this->data['file'],
            'line'    => $this->data['line'],
            'key'     => $this->data['key'],
            'status'  => $this->data['status'],
        ]);

        // Load GitHub configuration
        $token  = config('error-issue-creator.github_token');
        $repo   = config('error-issue-creator.repository');
        $labels = config('error-issue-creator.labels', []);

        $key     = $this->data['key'];
        $cached  = $this->data['cached'];
        $message = $this->data['message'];
        $status  = $this->data['status'];

        $baseUrl = "https://api.github.com/repos/{$repo}";

        if (! $cached) {
            // First occurrence: create a new GitHub issue
            $title = "[{$status}] " . Str::limit($message, 80);
            $body  = $this->formatIssueBody($this->data, 1);

            $response = Http::withToken($token)
                ->post("{$baseUrl}/issues", [
                    'title'  => $title,
                    'body'   => $body,
                    'labels' => $labels,
                ]);

            $issueNumber = $response->json('number');

            // Cache the issue number and initial count
            Cache::put($key, ['issue' => $issueNumber, 'count' => 1], config('error-issue-creator.cache_ttl'));

            Log::info("[ErrorIssueCreator] Created issue #{$issueNumber}, HTTP status: {$response->status()}");
        } else {
            // Subsequent occurrence: add a comment and increment counter
            $issueNumber = $cached['issue'];
            $newCount    = $cached['count'] + 1;

            $comment = "> This error has occurred **{$newCount}×** so far.\n\n"
                . "Timestamp: " . Carbon::now()->toDateTimeString();

            $response = Http::withToken($token)
                ->post("{$baseUrl}/issues/{$issueNumber}/comments", [
                    'body' => $comment,
                ]);

            // Update the cache with the new count
            Cache::put($key, ['issue' => $issueNumber, 'count' => $newCount], config('error-issue-creator.cache_ttl'));

            Log::info("[ErrorIssueCreator] Added comment to issue #{$issueNumber}, count={$newCount}, HTTP status: {$response->status()}");
        }
    }

    /**
     * Build the markdown body for the initial issue.
     *
     * @param  array  $data   Error details.
     * @param  int    $count  Occurrence count.
     * @return string         Formatted Markdown.
     */
    private function formatIssueBody(array $data, int $count): string
    {
        $status = $data['status'];

        return <<<MD
**Status:** {$status}

**Error Message:** {$data['message']}

**Location:** {$data['file']} : {$data['line']}

**Occurrences:** {$count}

**Stack Trace:**
```
{$data['trace']}
```
MD;
    }
}
