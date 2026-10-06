<?php

namespace App\Services\Social;

use Carbon\Carbon;

class FacebookProvider extends AbstractOpenGraphProvider
{
    protected function platform(): string { return 'facebook'; }

    /** @param array<string, mixed> $metadata */
    protected function hydratePlatformPublicData(SocialPostData $data, array $metadata): void
    {
        $nodes = $this->nodesForVideo($metadata['embeddedJson'] ?? [], $data->externalId);
        foreach ($nodes as $node) {
            $author = $this->stringAt($node, ['short_form_video_context', 'video_owner', 'name']);
            if ($author !== null && ($data->author === null || strcasecmp($data->author, 'Facebook') === 0)) $data->author = $author;
            $data->caption = $this->stringAt($node, ['message', 'text']) ?? $data->caption;
            $data->publishedAt ??= $this->timestampAt($node, ['creation_time']);
            $data->comments ??= $this->integerAt($node, ['feedback', 'total_comment_count']);

            if ($data->username === null) {
                $ownerUrl = $this->stringAt($node, ['short_form_video_context', 'video_owner', 'url']);
                $data->username = $this->facebookUsername($ownerUrl);
            }
        }

        $title = (string) (($metadata['meta'] ?? [])['og:title'] ?? '');
        $data->views ??= $this->metricFromTitle($title, 'views?');
        // Facebook exposes this public value as reactions, the closest available
        // equivalent for the application's likes column.
        $data->likes ??= $this->metricFromTitle($title, 'reactions?');

        if ($nodes !== [] || $data->views !== null || $data->likes !== null) {
            $data->rawMetadata = [...($data->rawMetadata ?? []), 'public_extraction' => 'facebook-embedded-json-and-og'];
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function nodesForVideo(array $items, ?string $externalId): array
    {
        if ($externalId === null) return [];

        $nodes = [];
        $walk = function (mixed $value) use (&$walk, &$nodes, $externalId): void {
            if (!is_array($value)) return;
            if ($this->isNodeForVideo($value, $externalId)) $nodes[] = $value;
            foreach ($value as $child) $walk($child);
        };
        $walk($items);

        return $nodes;
    }

    /** @param array<string, mixed> $node */
    private function isNodeForVideo(array $node, string $externalId): bool
    {
        return ($node['video']['id'] ?? null) === $externalId
            || ($node['short_form_video_context']['video']['id'] ?? null) === $externalId
            || ($node['attachments'][0]['media']['id'] ?? null) === $externalId;
    }

    /** @param array<string, mixed> $node @param array<int, string> $path */
    private function stringAt(array $node, array $path): ?string
    {
        $value = $this->valueAt($node, $path);
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string, mixed> $node @param array<int, string> $path */
    private function integerAt(array $node, array $path): ?int
    {
        $value = $this->valueAt($node, $path);
        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : null);
    }

    /** @param array<string, mixed> $node @param array<int, string> $path */
    private function timestampAt(array $node, array $path): ?Carbon
    {
        $timestamp = $this->integerAt($node, $path);
        return $timestamp !== null && $timestamp > 0 ? Carbon::createFromTimestampUTC($timestamp) : null;
    }

    /** @param array<string, mixed> $node @param array<int, string> $path */
    private function valueAt(array $node, array $path): mixed
    {
        $value = $node;
        foreach ($path as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) return null;
            $value = $value[$key];
        }
        return $value;
    }

    private function facebookUsername(?string $url): ?string
    {
        if ($url === null || !preg_match('~facebook\.com/([^/?#]+)~i', $url, $match)) return null;
        return $match[1];
    }

    private function metricFromTitle(string $title, string $label): ?int
    {
        if (!preg_match('/([\d.,\s]+[KMB]?)\s+'.$label.'/iu', $title, $match)) return null;

        $value = strtoupper(str_replace([' ', "\u{00A0}"], '', $match[1]));
        if (!preg_match('/^(\d+(?:[.,]\d+)?)([KMB])?$/', $value, $parts)) return null;
        $number = (float) str_replace(',', '.', $parts[1]);
        $factor = match ($parts[2] ?? '') { 'K' => 1_000, 'M' => 1_000_000, 'B' => 1_000_000_000, default => 1 };

        return (int) round($number * $factor);
    }
}
