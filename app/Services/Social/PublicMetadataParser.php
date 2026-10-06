<?php

namespace App\Services\Social;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Throwable;

class PublicMetadataParser
{
    /** @return array<string, mixed> */
    public function parse(string $html): array
    {
        $meta = [];
        $jsonLd = [];
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        if (!@$doc->loadHTML($html)) return compact('meta', 'jsonLd');

        foreach ($doc->getElementsByTagName('meta') as $node) {
            $key = strtolower($node->getAttribute('property') ?: $node->getAttribute('name') ?: $node->getAttribute('itemprop'));
            $value = trim($node->getAttribute('content'));
            if ($key && $value && !isset($meta[$key])) $meta[$key] = $value;
        }
        foreach ($doc->getElementsByTagName('script') as $node) {
            if (strtolower($node->getAttribute('type')) !== 'application/ld+json') continue;
            $decoded = json_decode($node->textContent, true);
            if (is_array($decoded)) $jsonLd[] = $decoded;
        }
        return compact('meta', 'jsonLd');
    }

    public function date(array $metadata): ?CarbonInterface
    {
        $candidates = [];
        foreach (['article:published_time', 'datepublished', 'uploaddate', 'video:release_date', 'datecreated'] as $key) {
            if (!empty($metadata['meta'][$key])) $candidates[] = $metadata['meta'][$key];
        }
        foreach ($this->flatten($metadata['jsonLd'] ?? []) as $node) {
            foreach (['datePublished', 'uploadDate', 'dateCreated', 'dateModified'] as $key) {
                if (!empty($node[$key]) && is_string($node[$key])) $candidates[] = $node[$key];
            }
        }
        foreach ($candidates as $candidate) {
            try { return Carbon::parse($candidate); } catch (Throwable) { /* Try another public source. */ }
        }
        return null;
    }

    public function username(array $metadata): ?string
    {
        foreach (['profile:username', 'instagram:username', 'twitter:creator'] as $key) {
            if (!empty($metadata['meta'][$key])) return ltrim((string) $metadata['meta'][$key], '@');
        }
        if (preg_match('/^@?([\w.]+)\s+(?:on|en)\s+(?:instagram|facebook)$/i', $metadata['meta']['og:title'] ?? '', $match)) return $match[1];
        return null;
    }

    /** @return array{views:?int,likes:?int,comments:?int} */
    public function metrics(array $metadata): array
    {
        $result = ['views' => null, 'likes' => null, 'comments' => null];
        foreach ($this->flatten($metadata['jsonLd'] ?? []) as $node) {
            foreach ((array) ($node['interactionStatistic'] ?? []) as $statistic) {
                if (!is_array($statistic) || !isset($statistic['userInteractionCount'])) continue;
                $value = $this->integer($statistic['userInteractionCount']);
                $type = strtolower((string) ($statistic['interactionType']['@type'] ?? $statistic['interactionType'] ?? ''));
                if ($value === null) continue;
                if (str_contains($type, 'watch') || str_contains($type, 'view')) $result['views'] = $value;
                if (str_contains($type, 'like')) $result['likes'] = $value;
                if (str_contains($type, 'comment')) $result['comments'] = $value;
            }
        }
        return $result;
    }

    /** @return array{meta: array<string, string>, json_ld: array} */
    public function storageMetadata(array $metadata): array
    {
        $meta = [];
        foreach ($metadata['meta'] ?? [] as $key => $value) $meta[$key] = mb_substr((string) $value, 0, 2000);
        $jsonLd = $metadata['jsonLd'] ?? [];
        return ['meta' => $meta, 'json_ld' => strlen((string) json_encode($jsonLd)) <= 16000 ? $jsonLd : []];
    }

    /** @return array<int, array<string, mixed>> */
    private function flatten(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            if (isset($item['@graph']) && is_array($item['@graph'])) $result = [...$result, ...$this->flatten($item['@graph'])];
            else $result[] = $item;
        }
        return $result;
    }

    private function integer(mixed $value): ?int
    {
        if (is_int($value)) return $value;
        if (is_string($value) && preg_match('/^\d+$/', $value)) return (int) $value;
        return null;
    }
}
