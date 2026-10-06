<?php

namespace App\Services\Social;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Throwable;

/** Parses Instagram's compact public meta-description without treating it as a caption. */
class InstagramMetaDescriptionParser
{
    /** @return array{likes:?int,comments:?int,username:?string,published_at:?CarbonInterface,caption:?string}|null */
    public function parse(string $description): ?array
    {
        $parts = $this->statisticsAndDetails($description);
        $result = [
            'likes' => null,
            'comments' => null,
            'username' => null,
            'published_at' => null,
            'caption' => null,
        ];

        if ($parts !== null) {
            [$likes, $comments, $details] = $parts;
            $result['likes'] = $this->metric($likes);
            $result['comments'] = $this->metric($comments);
        } else {
            // Some reels omit public counters but retain this identity/date
            // summary. It is still metadata, not a caption by itself.
            $details = $description;
        }

        $identity = $this->usernameAndDate($details);
        if ($identity === null) {
            return $parts === null ? null : $result;
        }
        [$username, $dateAndCaption] = $identity;
        $result['username'] = $username;

        $dateParts = $this->dateAndCaption($dateAndCaption);
        if ($dateParts === null) {
            return $result;
        }
        [$date, $caption] = $dateParts;
        $result['published_at'] = $date;
        $result['caption'] = $caption;

        return $result;
    }

    public function metric(string $value): ?int
    {
        $value = strtoupper(str_replace([' ', "\u{00A0}"], '', trim($value)));
        if (preg_match('/^\d+$/', $value)) {
            return (int) $value;
        }
        if (preg_match('/^\d{1,3}(?:,\d{3})+$/', $value)) {
            return (int) str_replace(',', '', $value);
        }

        // With a suffix, only one/two decimal places are unambiguous.
        if (! preg_match('/^(\d+(?:[.,]\d{1,2})?)([KMB])$/', $value, $match)) {
            return null;
        }
        $number = (float) str_replace(',', '.', $match[1]);
        $factor = match ($match[2]) {
            'K' => 1_000, 'M' => 1_000_000, 'B' => 1_000_000_000
        };

        return (int) round($number * $factor);
    }

    /** @return array{string,string,string}|null */
    private function statisticsAndDetails(string $description): ?array
    {
        if (! preg_match('/^\s*(.+?)\s+likes?\s*,\s*(.+?)\s+comments?\s*-\s*(.+?)\s*$/iu', $description, $match)) {
            return null;
        }

        return [trim($match[1]), trim($match[2]), trim($match[3])];
    }

    /** @return array{string,string}|null */
    private function usernameAndDate(string $details): ?array
    {
        if (! preg_match('/^@?([\pL\pN._]+)\s+(?:on|el|en|am|auf|le)\s+(.+)$/iu', $details, $match)) {
            return null;
        }

        return [ltrim(trim($match[1]), '@'), trim($match[2])];
    }

    /** @return array{CarbonInterface,?string}|null */
    private function dateAndCaption(string $value): ?array
    {
        if (! preg_match('/^([A-Za-z]+\s+\d{1,2},\s+\d{4})(?:\s*:\s*(.*))?$/u', $value, $match)) {
            return null;
        }
        try {
            $date = Carbon::createFromFormat('!F j, Y', $match[1], 'UTC')->startOfDay();
        } catch (Throwable) {
            return null;
        }

        return [$date, isset($match[2]) ? $this->caption(trim($match[2])) : null];
    }

    private function caption(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        if (preg_match('/^["“](.*)["”]\\.?$/us', $value, $match)) {
            return $match[1] === '' ? null : $match[1];
        }

        return $value;
    }
}
