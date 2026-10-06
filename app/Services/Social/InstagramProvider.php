<?php

namespace App\Services\Social;

use Carbon\Carbon;
use Throwable;

class InstagramProvider extends AbstractOpenGraphProvider
{
    protected function platform(): string { return 'instagram'; }

    /** @param array<string, mixed> $metadata */
    protected function hydratePlatformPublicData(SocialPostData $data, array $metadata): void
    {
        $meta = $metadata['meta'] ?? [];
        $title = (string) ($meta['og:title'] ?? '');
        $description = (string) ($meta['og:description'] ?? '');

        if (preg_match('/^(.+?)\s+(?:on|en|auf|em)\s+Instagram\s*:/iu', $title, $match)
            && ($data->author === null || strcasecmp($data->author, 'Instagram') === 0)) {
            $data->author = trim($match[1]);
        }

        if (preg_match('/-\s*@?([\pL\pN._]+)\s+(?:on|en|auf|em)\s+/iu', $description, $match)) {
            $data->username ??= $match[1];
        }

        if (preg_match('/-\s*@?[\pL\pN._]+\s+(?:on|en|auf|em)\s+(.+?)(?::\s*["“]|$)/iu', $description, $match)) {
            try {
                $data->publishedAt ??= Carbon::parse(trim($match[1]), 'UTC')->startOfDay();
            } catch (Throwable) {
                // The public page can be localized to an unsupported date format.
            }
        }

        if (preg_match('/([\d.,\s]+[KMB]?)\s+likes?\s*,\s*([\d.,\s]+[KMB]?)\s+comments?/iu', $description, $match)) {
            $data->likes ??= $this->compactInteger($match[1]);
            $data->comments ??= $this->compactInteger($match[2]);
        }

        if (preg_match('/:\s*["“](.*?)["”]\.?(?:\s*)$/us', $description, $match)) {
            $data->caption = $match[1];
        }

        $data->rawMetadata = [...($data->rawMetadata ?? []), 'public_extraction' => 'instagram-og'];
    }

    private function compactInteger(string $value): ?int
    {
        $value = strtoupper(str_replace([' ', "\u{00A0}"], '', trim($value)));
        if (!preg_match('/^(\d+(?:[.,]\d+)?)([KMB])?$/', $value, $match)) return null;

        $number = (float) str_replace(',', '.', $match[1]);
        $factor = match ($match[2] ?? '') { 'K' => 1_000, 'M' => 1_000_000, 'B' => 1_000_000_000, default => 1 };

        return (int) round($number * $factor);
    }
}
