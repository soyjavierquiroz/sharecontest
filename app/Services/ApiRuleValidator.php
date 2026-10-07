<?php

namespace App\Services;

use App\Models\Submission;

class ApiRuleValidator
{
    /** @param array<string, mixed>|null $rules @return array{status:string,checks:array<string, array<string, mixed>>} */
    public function validate(Submission $submission, ?array $rules): array
    {
        if ($rules === null || $rules === []) {
            return ['status' => 'not_requested', 'checks' => []];
        }

        $checks = [];

        if (array_key_exists('allowed_platforms', $rules)) {
            $allowed = array_values(array_unique(array_map(
                fn ($platform) => mb_strtolower(trim((string) $platform)),
                $rules['allowed_platforms'] ?? [],
            )));
            $checks['platform'] = [
                'status' => in_array($submission->platform, $allowed, true) ? 'passed' : 'failed',
                'allowed' => $allowed,
                'detected' => $submission->platform,
            ];
        }

        if (array_key_exists('required_hashtags', $rules)) {
            $required = $this->normalizeTokens($rules['required_hashtags'] ?? [], '#');
            $found = $submission->caption === null ? [] : $this->tokensIn($submission->caption, '#');
            $missing = array_values(array_diff($required, $found));
            $checks['hashtags'] = [
                'status' => $submission->caption === null ? 'unknown' : ($missing === [] ? 'passed' : 'failed'),
                'required' => $required,
                'found' => array_values(array_intersect($required, $found)),
                'missing' => $missing,
            ];
        }

        if (array_key_exists('required_mentions', $rules)) {
            $required = $this->normalizeTokens($rules['required_mentions'] ?? [], '@');
            $found = $submission->caption === null ? [] : $this->tokensIn($submission->caption, '@');
            $missing = array_values(array_diff($required, $found));
            $checks['mentions'] = [
                'status' => $submission->caption === null ? 'unknown' : ($missing === [] ? 'passed' : 'failed'),
                'required' => $required,
                'found' => array_values(array_intersect($required, $found)),
                'missing' => $missing,
            ];
        }

        if (array_key_exists('published_from', $rules) || array_key_exists('published_until', $rules)) {
            $precision = ($submission->raw_metadata['published_at_precision'] ?? null) === 'date' ? 'date' : 'datetime';
            $published = $submission->published_at;
            $publishedDate = $published?->toDateString();
            $from = $rules['published_from'] ?? null;
            $until = $rules['published_until'] ?? null;
            $passes = $publishedDate !== null
                && ($from === null || $publishedDate >= $from)
                && ($until === null || $publishedDate <= $until);
            $checks['published_at'] = [
                'status' => $published === null ? 'unknown' : ($passes ? 'passed' : 'failed'),
                'published_at' => $published === null ? null : ($precision === 'date' ? $publishedDate : $published->toIso8601String()),
                'precision' => $published === null ? null : $precision,
                'from' => $from,
                'until' => $until,
            ];
        }

        $statuses = array_column($checks, 'status');
        $status = in_array('failed', $statuses, true) ? 'invalid'
            : (in_array('unknown', $statuses, true) ? 'review_required' : 'valid');

        return ['status' => $status, 'checks' => $checks];
    }

    /** @param array<int, mixed> $values @return array<int, string> */
    private function normalizeTokens(array $values, string $prefix): array
    {
        $tokens = [];
        foreach ($values as $value) {
            $token = mb_strtolower(trim((string) $value));
            $token = ltrim($token, $prefix);
            if ($token !== '') {
                $tokens[] = $prefix.$token;
            }
        }

        return array_values(array_unique($tokens));
    }

    /** @return array<int, string> */
    private function tokensIn(string $text, string $prefix): array
    {
        preg_match_all('/(?<![\pL\pN_])'.preg_quote($prefix, '/').'([\pL\pN_]+)/u', mb_strtolower($text), $matches);

        return array_values(array_unique(array_map(fn ($token) => $prefix.$token, $matches[1] ?? [])));
    }
}
