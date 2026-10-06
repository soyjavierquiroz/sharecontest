<?php

namespace App\Services;

use App\Models\Submission;
use App\Services\Social\SocialProviderFactory;
use Illuminate\Support\Facades\Log;

class RefreshSubmission
{
    public function __construct(private SocialProviderFactory $providers) {}
    public function handle(Submission $submission): Submission
    {
        $data = $this->providers->for($submission->platform)->inspect($submission->original_url);
        $caption = $data->caption ?? $submission->caption;
        $publishedAt = $data->publishedAt ?? $submission->published_at;
        $author = $data->author ?? $submission->author;
        $username = $data->username ?? $submission->username;
        $views = $data->views ?? $submission->views;
        $likes = $data->likes ?? $submission->likes;
        $comments = $data->comments ?? $submission->comments;
        $hasUsefulMetadata = $author !== null || $username !== null || $caption !== null || $publishedAt !== null || $views !== null || $likes !== null || $comments !== null;
        $status = match (true) {
            $data->isPublic === false => 'invalid',
            $data->isPublic !== true || !$hasUsefulMetadata => 'review_required',
            default => 'valid',
        };
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
            'raw_metadata' => $data->rawMetadata ?? $submission->raw_metadata,
            'last_checked_at' => now(),
        ])->save();
        if ($data->views !== null || $data->likes !== null || $data->comments !== null) $submission->metricSnapshots()->create(['views' => $data->views, 'likes' => $data->likes, 'comments' => $data->comments, 'captured_at' => now(), 'provider' => $data->provider]);
        if ($data->error) Log::warning('Social provider inspection needs review', ['platform' => $submission->platform, 'submission_id' => $submission->id, 'provider' => $data->provider, 'error' => $data->error]);
        return $submission->refresh();
    }
}
