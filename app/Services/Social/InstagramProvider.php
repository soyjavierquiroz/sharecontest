<?php

namespace App\Services\Social;

class InstagramProvider extends AbstractOpenGraphProvider
{
    protected function platform(): string
    {
        return 'instagram';
    }

    public static function isGenericAuthor(?string $author): bool
    {
        if ($author === null) {
            return false;
        }
        $normalized = mb_strtolower(trim($author));
        $normalized = str_replace('&', 'and', $normalized);
        $normalized = preg_replace('/[^\pL\pN]+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return in_array($normalized, [
            'instagram', 'instagram com', 'instagram photo and video',
            'instagram photos and videos', 'instagram photos videos',
            'instagram the photo and video sharing app',
        ], true);
    }

    /** @param array<string, mixed> $metadata */
    protected function hydratePlatformPublicData(SocialPostData $data, array $metadata): void
    {
        $meta = $metadata['meta'] ?? [];
        $title = (string) ($meta['og:title'] ?? '');
        $rawMetadata = $data->rawMetadata ?? [];
        $fieldSources = is_array($rawMetadata['field_sources'] ?? null) ? $rawMetadata['field_sources'] : [];

        if (self::isGenericAuthor($data->author)) {
            $data->author = null;
        }
        if ($data->author !== null) {
            $fieldSources['author'] = ! empty($meta['author']) ? 'meta:author' : 'opengraph';
        }

        if ($data->author === null && preg_match('/^(.+?)\s+(?:on|en|auf|em)\s+Instagram\s*:/iu', $title, $match)) {
            $candidate = trim($match[1]);
            if (! self::isGenericAuthor($candidate)) {
                $data->author = $candidate;
                $fieldSources['author'] = 'og:title';
            }
        }

        if ($data->username !== null) {
            $fieldSources['username'] = $this->usernameSource($meta);
        }
        if ($data->publishedAt !== null) {
            $fieldSources['published_at'] = 'public_metadata';
        }
        if ($data->likes !== null) {
            $fieldSources['likes'] = 'public_metadata';
        }
        if ($data->comments !== null) {
            $fieldSources['comments'] = 'public_metadata';
        }

        $descriptionParser = app(InstagramMetaDescriptionParser::class);
        $structuredDescription = null;
        $captionCandidate = null;
        foreach ($this->descriptionCandidates($meta) as $description) {
            $parsed = $descriptionParser->parse($description['value']);
            if ($parsed !== null) {
                $structuredDescription ??= $parsed;

                continue;
            }
            $captionCandidate ??= $description;
        }

        if ($structuredDescription !== null) {
            $data->likes ??= $structuredDescription['likes'];
            $data->comments ??= $structuredDescription['comments'];
            $data->username ??= $structuredDescription['username'];
            if ($data->publishedAt === null && $structuredDescription['published_at'] !== null) {
                $data->publishedAt = $structuredDescription['published_at'];
                $rawMetadata['published_at_precision'] = 'date';
                $rawMetadata['published_at_source'] = 'instagram_meta_description';
                $fieldSources['published_at'] = 'instagram_meta_description';
            }
            if ($structuredDescription['likes'] !== null && ! isset($fieldSources['likes'])) {
                $fieldSources['likes'] = 'instagram_meta_description';
            }
            if ($structuredDescription['comments'] !== null && ! isset($fieldSources['comments'])) {
                $fieldSources['comments'] = 'instagram_meta_description';
            }
            if ($structuredDescription['username'] !== null && ! isset($fieldSources['username'])) {
                $fieldSources['username'] = 'instagram_meta_description';
            }

            // This is metadata, not text written by the post author.
            $data->caption = $captionCandidate['value'] ?? $structuredDescription['caption'];
            if ($data->caption !== null) {
                $fieldSources['caption'] = $captionCandidate['source'] ?? 'instagram_meta_description';
            } else {
                unset($fieldSources['caption']);
            }
            $rawMetadata['structured_meta_description'] = true;
        } elseif ($data->caption !== null) {
            $fieldSources['caption'] = $this->captionSource($meta, $data->caption);
        }

        if ($data->author === null) {
            unset($fieldSources['author']);
        }
        $rawMetadata['field_sources'] = $fieldSources;
        $rawMetadata['public_extraction'] = 'instagram';
        $data->rawMetadata = $rawMetadata;
    }

    /** @return array<int, array{source:string,value:string}> */
    private function descriptionCandidates(array $meta): array
    {
        $candidates = [];
        foreach (['og:description' => 'opengraph', 'description' => 'meta_description'] as $key => $source) {
            $value = trim((string) ($meta[$key] ?? ''));
            if ($value !== '') {
                $candidates[] = ['source' => $source, 'value' => $value];
            }
        }

        return $candidates;
    }

    private function usernameSource(array $meta): string
    {
        foreach (['profile:username', 'instagram:username', 'twitter:creator'] as $key) {
            if (! empty($meta[$key])) {
                return 'meta:'.$key;
            }
        }

        return 'public_metadata';
    }

    private function captionSource(array $meta, string $caption): string
    {
        return ($meta['og:description'] ?? null) === $caption ? 'opengraph' : 'meta_description';
    }
}
