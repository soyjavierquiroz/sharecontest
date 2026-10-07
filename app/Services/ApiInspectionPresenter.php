<?php

namespace App\Services;

use App\Models\Submission;

class ApiInspectionPresenter
{
    /** @return array<string, mixed> */
    public function present(Submission $submission): array
    {
        $precision = ($submission->raw_metadata['published_at_precision'] ?? null) === 'date' ? 'date' : 'datetime';
        $publishedAt = $submission->published_at;
        $sources = $this->sources($submission);

        $data = [
            'sharecontest_id' => $submission->id,
            'platform' => $submission->platform,
            'original_url' => $submission->original_url,
            'canonical_url' => $submission->canonical_url,
            'external_id' => $submission->external_id,
            'author' => $submission->author,
            'username' => $submission->username,
            'caption' => $submission->caption,
            'published_at' => $publishedAt === null ? null : ($precision === 'date' ? $publishedAt->toDateString() : $publishedAt->toIso8601String()),
            'published_at_precision' => $publishedAt === null ? null : $precision,
            'is_public' => $submission->is_public,
            'metrics' => [
                'views' => $submission->views,
                'likes' => $submission->likes,
                'comments' => $submission->comments,
            ],
            'data_quality' => $this->dataQuality($submission),
            'checked_at' => $submission->last_checked_at?->toIso8601String(),
        ];
        if ($sources !== []) {
            $data['sources'] = $sources;
        }

        return $data;
    }

    private function dataQuality(Submission $submission): string
    {
        $identity = $submission->author !== null || $submission->username !== null || $submission->caption !== null;
        $metricsComplete = $submission->views !== null && $submission->likes !== null && $submission->comments !== null;
        $hasAnyData = $identity || $submission->published_at !== null
            || $submission->views !== null || $submission->likes !== null || $submission->comments !== null;

        if (! $hasAnyData) {
            return 'none';
        }

        return $identity && $submission->published_at !== null && $metricsComplete ? 'complete' : 'partial';
    }

    /** @return array<string, string> */
    private function sources(Submission $submission): array
    {
        $all = $submission->raw_metadata['field_sources'] ?? [];
        if (! is_array($all)) {
            return [];
        }

        $sources = [];
        foreach (['author', 'username', 'caption', 'published_at', 'views', 'likes', 'comments'] as $field) {
            if (isset($all[$field]) && is_string($all[$field])) {
                $sources[$field] = $all[$field];
            }
        }

        return $sources;
    }
}
