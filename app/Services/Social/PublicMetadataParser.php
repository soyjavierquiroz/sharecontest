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
        $embeddedJson = [];
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        if (!@$doc->loadHTML($html)) return compact('meta', 'jsonLd', 'embeddedJson');

        foreach ($doc->getElementsByTagName('meta') as $node) {
            $key = strtolower($node->getAttribute('property') ?: $node->getAttribute('name') ?: $node->getAttribute('itemprop'));
            $value = trim($node->getAttribute('content'));
            if ($key && $value && !isset($meta[$key])) $meta[$key] = $value;
        }
        foreach ($doc->getElementsByTagName('script') as $node) {
            $type = strtolower($node->getAttribute('type'));
            $contents = trim($node->textContent);
            if ($type === 'application/ld+json') {
                $decoded = json_decode($contents, true);
                if (is_array($decoded)) $jsonLd[] = $decoded;
                continue;
            }
            if (($type === 'application/json' || in_array($node->getAttribute('id'), ['SIGI_STATE', '__UNIVERSAL_DATA_FOR_REHYDRATION__'], true)) && strlen($contents) <= 1_500_000) {
                $decoded = json_decode($contents, true);
                if (is_array($decoded)) $embeddedJson[] = $decoded;
                continue;
            }
            if (strlen($contents) <= 1_500_000 && preg_match('/(?:SIGI_STATE|__UNIVERSAL_DATA_FOR_REHYDRATION__)\\s*=\\s*({.*})\\s*;?\\s*$/s', $contents, $match)) {
                $decoded = json_decode($match[1], true);
                if (is_array($decoded)) $embeddedJson[] = $decoded;
            }
        }
        return compact('meta', 'jsonLd', 'embeddedJson');
    }

    public function date(array $metadata): ?CarbonInterface
    {
        return $this->dateWithSource($metadata)['date'];
    }

    /** @return array{date:?CarbonInterface, source:?string} */
    public function dateWithSource(array $metadata): array
    {
        $candidates = [];
        foreach (['article:published_time', 'datepublished', 'uploaddate', 'video:release_date', 'datecreated', 'createtime', 'create_time'] as $key) {
            if (!empty($metadata['meta'][$key])) $candidates[] = $metadata['meta'][$key];
        }
        foreach ($this->flatten($metadata['jsonLd'] ?? []) as $node) {
            foreach (['datePublished', 'uploadDate', 'dateCreated', 'dateModified'] as $key) {
                if (!empty($node[$key]) && is_string($node[$key])) $candidates[] = $node[$key];
            }
        }
        $candidates = [...$candidates, ...$this->valuesForKeys($metadata['embeddedJson'] ?? [], [
            'createTime', 'create_time', 'datePublished', 'uploadDate', 'date_created', 'dateCreated',
        ])];
        foreach ($candidates as $candidate) {
            try {
                if (is_int($candidate) || (is_string($candidate) && preg_match('/^\\d{9,11}$/', $candidate))) {
                    return ['date' => Carbon::createFromTimestampUTC((int) $candidate), 'source' => 'public_metadata'];
                }
                if (is_string($candidate)) return ['date' => Carbon::parse($candidate)->utc(), 'source' => 'public_metadata'];
            } catch (Throwable) { /* Try another public source. */ }
        }
        return ['date' => null, 'source' => null];
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
        $json = [...($metadata['jsonLd'] ?? []), ...($metadata['embeddedJson'] ?? [])];
        $result['views'] ??= $this->firstIntegerForKeys($json, ['playCount', 'viewCount', 'view_count']);
        $result['likes'] ??= $this->firstIntegerForKeys($json, ['diggCount', 'likeCount', 'like_count']);
        $result['comments'] ??= $this->firstIntegerForKeys($json, ['commentCount', 'comment_count']);
        $result['views'] ??= $this->firstMetaInteger($metadata['meta'] ?? [], ['playcount', 'viewcount', 'view_count']);
        $result['likes'] ??= $this->firstMetaInteger($metadata['meta'] ?? [], ['diggcount', 'likecount', 'like_count']);
        $result['comments'] ??= $this->firstMetaInteger($metadata['meta'] ?? [], ['commentcount', 'comment_count']);
        return $result;
    }

    public function firstIntegerForKnownFields(array $metadata, array $keys): ?int
    {
        $json = [...($metadata['jsonLd'] ?? []), ...($metadata['embeddedJson'] ?? [])];
        return $this->firstIntegerForKeys($json, $keys) ?? $this->firstMetaInteger($metadata['meta'] ?? [], $keys);
    }

    /** @return array{meta: array<string, string>, json_ld: array, embedded_json: array} */
    public function storageMetadata(array $metadata): array
    {
        $meta = [];
        foreach ($metadata['meta'] ?? [] as $key => $value) $meta[$key] = mb_substr((string) $value, 0, 2000);
        $jsonLd = $metadata['jsonLd'] ?? [];
        $embeddedJson = $metadata['embeddedJson'] ?? [];
        return [
            'meta' => $meta,
            'json_ld' => strlen((string) json_encode($jsonLd)) <= 16000 ? $jsonLd : [],
            'embedded_json' => strlen((string) json_encode($embeddedJson)) <= 16000 ? $embeddedJson : [],
        ];
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

    /** @return array<int, mixed> */
    private function valuesForKeys(array $items, array $keys): array
    {
        $wanted = array_flip(array_map(fn (string $key) => strtolower(str_replace('_', '', $key)), $keys));
        $values = [];
        $walk = function (mixed $value) use (&$walk, &$values, $wanted): void {
            if (!is_array($value)) return;
            foreach ($value as $key => $item) {
                if (is_string($key) && isset($wanted[strtolower(str_replace('_', '', $key))])) $values[] = $item;
                if (is_array($item)) $walk($item);
            }
        };
        $walk($items);
        return $values;
    }

    private function firstIntegerForKeys(array $items, array $keys): ?int
    {
        foreach ($this->valuesForKeys($items, $keys) as $value) {
            $integer = $this->integer($value);
            if ($integer !== null) return $integer;
        }
        return null;
    }

    private function firstMetaInteger(array $meta, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (isset($meta[$key]) && ($integer = $this->integer($meta[$key])) !== null) return $integer;
        }
        return null;
    }
}
