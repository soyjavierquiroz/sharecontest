<?php

namespace App\Services;

use App\Models\Submission;
use App\Services\Social\InstagramMetaDescriptionParser;
use App\Services\Social\InstagramProvider;
use App\Services\Social\SocialProviderFactory;
use Illuminate\Support\Facades\Log;

class RefreshSubmission
{
    public function __construct(private SocialProviderFactory $providers) {}

    public function handle(Submission $submission): Submission
    {
        $data = $this->providers->for($submission->platform)->inspect($submission->original_url);
        $incomingRawMetadata = $data->rawMetadata ?? [];
        $storedRawMetadata = $submission->raw_metadata ?? [];
        $caption = $data->caption ?? $submission->caption;
        if ($submission->platform === 'instagram'
            && ($incomingRawMetadata['structured_meta_description'] ?? false)
            && $submission->caption !== null
            && app(InstagramMetaDescriptionParser::class)->parse($submission->caption) !== null) {
            // Remove captions created by the former Instagram parser, while
            // retaining an actual caption when the new response has none.
            $caption = $data->caption;
        }

        $publishedAt = $data->publishedAt ?? $submission->published_at;
        $incomingDateIsOnlyADate = ($incomingRawMetadata['published_at_precision'] ?? null) === 'date';
        $storedDateIsOnlyADate = ($storedRawMetadata['published_at_precision'] ?? null) === 'date';
        if ($incomingDateIsOnlyADate && $submission->published_at !== null && ! $storedDateIsOnlyADate) {
            $publishedAt = $submission->published_at;
        }

        $author = $data->author ?? $submission->author;
        if ($submission->platform === 'instagram' && InstagramProvider::isGenericAuthor($author)) {
            $author = null;
        }
        $username = $data->username ?? $submission->username;
        $views = $data->views ?? $submission->views;
        $likes = $data->likes ?? $submission->likes;
        $comments = $data->comments ?? $submission->comments;
        $hasUsefulMetadata = $author !== null || $username !== null || $caption !== null || $publishedAt !== null || $views !== null || $likes !== null || $comments !== null;
        $status = match (true) {
            $data->isPublic === false => 'invalid',
            $data->isPublic !== true || ! $hasUsefulMetadata => 'review_required',
            default => 'valid',
        };
        $rawMetadata = [...$storedRawMetadata, ...$incomingRawMetadata];
        $rawMetadata['field_sources'] = [
            ...(is_array($storedRawMetadata['field_sources'] ?? null) ? $storedRawMetadata['field_sources'] : []),
            ...(is_array($incomingRawMetadata['field_sources'] ?? null) ? $incomingRawMetadata['field_sources'] : []),
        ];
        if ($author === null) {
            unset($rawMetadata['field_sources']['author']);
        }
        if ($caption === null) {
            unset($rawMetadata['field_sources']['caption']);
        }
        if ($incomingDateIsOnlyADate && $submission->published_at !== null && ! $storedDateIsOnlyADate) {
            if (isset($storedRawMetadata['published_at_precision'])) {
                $rawMetadata['published_at_precision'] = $storedRawMetadata['published_at_precision'];
            } else {
                unset($rawMetadata['published_at_precision']);
            }
            if (isset($storedRawMetadata['published_at_source'])) {
                $rawMetadata['published_at_source'] = $storedRawMetadata['published_at_source'];
            } else {
                unset($rawMetadata['published_at_source']);
            }
            $rawMetadata['field_sources']['published_at'] = $storedRawMetadata['field_sources']['published_at'] ?? 'public_metadata';
        }

        $submission->fill([
            'canonical_url' => $data->canonicalUrl ?? $submission->canonical_url,
            'external_id' => $data->externalId ?? $submission->external_id,
            'author' => $author,
            'username' => $username,
            'caption' => $caption,
            'published_at' => $publishedAt,
            'is_public' => $data->isPublic ?? $submission->is_public,
            'hashtag_valid' => null,
            'views' => $views,
            'likes' => $likes,
            'comments' => $comments,
            'status' => $status,
            'validation_message' => $data->error ?: ($status === 'valid' ? 'Datos públicos obtenidos.' : ($status === 'invalid' ? 'La publicación no está disponible o no es válida.' : 'Datos parciales: no pudimos obtener toda la información pública.')),
            'provider' => $data->provider,
            'raw_metadata' => $rawMetadata,
            'last_checked_at' => now(),
        ])->save();
        if ($data->views !== null || $data->likes !== null || $data->comments !== null) {
            $submission->metricSnapshots()->create(['views' => $data->views, 'likes' => $data->likes, 'comments' => $data->comments, 'captured_at' => now(), 'provider' => $data->provider]);
        }
        if ($data->error) {
            Log::warning('Social provider inspection needs review', ['platform' => $submission->platform, 'submission_id' => $submission->id, 'provider' => $data->provider, 'error' => $data->error]);
        }

        return $submission->refresh();
    }
}
