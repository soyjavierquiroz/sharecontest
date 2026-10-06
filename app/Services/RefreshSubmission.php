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
        $hashtagValid = $caption !== null ? str_contains(mb_strtolower($caption), mb_strtolower($submission->contest->required_hashtag)) : null;
        $status = match (true) {
            $data->isPublic === false => 'invalid',
            $data->isPublic !== true || $caption === null || $hashtagValid === null => 'review_required',
            $hashtagValid === false => 'invalid',
            default => 'valid',
        };
        $submission->fill([
            'canonical_url' => $data->canonicalUrl ?? $submission->canonical_url,
            'external_id' => $data->externalId ?? $submission->external_id,
            'author' => $data->author ?? $submission->author,
            'caption' => $caption,
            'published_at' => $data->publishedAt ?? $submission->published_at,
            'is_public' => $data->isPublic,
            'hashtag_valid' => $hashtagValid,
            'views' => $data->views ?? $submission->views,
            'likes' => $data->likes ?? $submission->likes,
            'comments' => $data->comments ?? $submission->comments,
            'status' => $status,
            'validation_message' => $data->error ?: ($status === 'valid' ? 'Participación validada.' : ($status === 'invalid' ? 'No cumple los requisitos del concurso.' : 'Pendiente de verificación manual.')),
            'provider' => $data->provider,
            'last_checked_at' => now(),
        ])->save();
        if ($data->views !== null || $data->likes !== null || $data->comments !== null) $submission->metricSnapshots()->create(['views' => $data->views, 'likes' => $data->likes, 'comments' => $data->comments, 'captured_at' => now(), 'provider' => $data->provider]);
        if ($data->error) Log::warning('Social provider inspection needs review', ['platform' => $submission->platform, 'submission_id' => $submission->id, 'provider' => $data->provider, 'error' => $data->error]);
        return $submission->refresh();
    }
}
