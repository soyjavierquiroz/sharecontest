<?php

namespace App\Presenters;

use App\Models\Submission;
use Illuminate\Database\Eloquent\Builder;

class SubmissionDataStatus
{
    public static function for(Submission $submission): string
    {
        if ($submission->status === 'invalid' || $submission->is_public === false) return 'error';
        $identity = $submission->author !== null || $submission->username !== null || $submission->caption !== null;
        $metric = $submission->views !== null || $submission->likes !== null || $submission->comments !== null;
        $useful = $identity || $submission->published_at !== null || $metric;
        if (!$useful) return 'no_data';
        return $identity && $submission->published_at !== null && $metric ? 'complete' : 'partial';
    }

    public static function label(string $status): string
    {
        return match ($status) {
            'complete' => 'DATOS OBTENIDOS',
            'partial' => 'DATOS PARCIALES',
            'error' => 'ERROR',
            default => 'SIN DATOS',
        };
    }

    public static function filter(Builder $query, string $status): void
    {
        $useful = fn (Builder $q) => $q->whereNotNull('author')->orWhereNotNull('username')->orWhereNotNull('caption')->orWhereNotNull('published_at')->orWhereNotNull('views')->orWhereNotNull('likes')->orWhereNotNull('comments');
        $identity = fn (Builder $q) => $q->whereNotNull('author')->orWhereNotNull('username')->orWhereNotNull('caption');
        $metric = fn (Builder $q) => $q->whereNotNull('views')->orWhereNotNull('likes')->orWhereNotNull('comments');
        match ($status) {
            'complete' => $query->where(fn (Builder $q) => $identity($q))->whereNotNull('published_at')->where(fn (Builder $q) => $metric($q))->where('status', '!=', 'invalid')->where(fn (Builder $q) => $q->whereNull('is_public')->orWhere('is_public', true)),
            'partial' => $query->where(fn (Builder $q) => $useful($q))->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere(fn (Builder $nested) => $nested->whereNull('author')->whereNull('username')->whereNull('caption'))->orWhere(fn (Builder $nested) => $nested->whereNull('views')->whereNull('likes')->whereNull('comments')))->where('status', '!=', 'invalid')->where(fn (Builder $q) => $q->whereNull('is_public')->orWhere('is_public', true)),
            'no_data' => $query->where(fn (Builder $q) => $q->whereNull('author')->whereNull('username')->whereNull('caption')->whereNull('published_at')->whereNull('views')->whereNull('likes')->whereNull('comments'))->where('status', '!=', 'invalid')->where(fn (Builder $q) => $q->whereNull('is_public')->orWhere('is_public', true)),
            'error' => $query->where(fn (Builder $q) => $q->where('status', 'invalid')->orWhere('is_public', false)),
            default => null,
        };
    }
}
