<?php

namespace App\Services\Social;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

class TikTokProvider implements SocialPostProviderInterface
{
    private const OEMBED_MAX_BYTES = 200_000;

    public function __construct(private PlatformDetector $detector, private SafeSocialHttpClient $http, private PublicMetadataParser $parser) {}

    public function inspect(string $url): SocialPostData
    {
        $data = new SocialPostData('tiktok', $url, $this->detector->normalize($url), $this->detector->externalId($url), provider: 'tiktok-oembed');
        try {
            $response = Http::timeout(8)->connectTimeout(4)->withOptions(['allow_redirects' => ['max' => 3]])->acceptJson()->get('https://www.tiktok.com/oembed', ['url' => $url]);
            if (!$response->successful()) { $data->error = 'TikTok oEmbed respondió HTTP '.$response->status(); }
            $body = $response->json();
            if (!$response->successful() || !is_array($body) || strlen($response->body()) > self::OEMBED_MAX_BYTES) {
                $data->error ??= 'Respuesta oEmbed inválida.';
            } else {
                $data->isPublic = true;
                $data->author = $body['author_name'] ?? null;
                $data->caption = $body['title'] ?? null;
                $data->username = isset($body['author_unique_id']) ? ltrim((string) $body['author_unique_id'], '@') : null;
                if (!empty($body['create_time'])) {
                    try {
                        $data->publishedAt = Carbon::createFromTimestampUTC((int) $body['create_time']);
                        $body['published_at_source'] = 'public_metadata';
                        $body['published_at_confidence'] = 'direct';
                    } catch (Throwable) { /* Public metadata can be incomplete. */ }
                }
                $data->rawMetadata = ['oembed' => $body];
            }
        } catch (Throwable $e) { $data->error = 'No se pudo consultar TikTok oEmbed: '.$e->getMessage(); }

        $this->enrichFromPublicHtml($data, $url);
        if ($data->publishedAt === null && ($inferred = $this->timestampFromVideoId($data->externalId))) {
            $data->publishedAt = $inferred;
            $data->rawMetadata = [...($data->rawMetadata ?? []), 'published_at_source' => 'tiktok_video_id', 'published_at_confidence' => 'inferred'];
        }
        return $data;
    }

    private function enrichFromPublicHtml(SocialPostData $data, string $url): void
    {
        try {
            [$response] = $this->http->get($url);
            if (!$response->successful()) return;
            $metadata = $this->parser->parse($response->body());
            $meta = $metadata['meta'];
            $metrics = $this->parser->metrics($metadata);
            $date = $this->parser->dateWithSource($metadata);
            $data->isPublic = true;
            $data->error = null;
            $data->caption ??= $meta['og:description'] ?? $meta['description'] ?? null;
            $data->author ??= $meta['author'] ?? null;
            $data->username ??= $this->parser->username($metadata);
            if ($data->publishedAt === null && $date['date'] !== null) $data->publishedAt = $date['date'];
            $data->views = $metrics['views'] ?? $data->views;
            $data->likes = $metrics['likes'] ?? $data->likes;
            $data->comments = $metrics['comments'] ?? $data->comments;
            $rawMetadata = [...($data->rawMetadata ?? []), ...$this->parser->storageMetadata($metadata)];
            $shares = $this->parser->firstIntegerForKnownFields($metadata, ['shareCount', 'share_count']);
            if ($shares !== null) $rawMetadata['share_count'] = $shares;
            if ($data->publishedAt !== null && ($date['date'] !== null || isset($rawMetadata['oembed']['published_at_source']))) {
                $rawMetadata['published_at_source'] = 'public_metadata';
                $rawMetadata['published_at_confidence'] = 'direct';
            }
            $data->rawMetadata = $rawMetadata;
        } catch (Throwable $e) {
            // oEmbed already gave us the public best-effort result; HTML enrichment is optional.
        }
    }

    public function timestampFromVideoId(?string $videoId): ?Carbon
    {
        if (PHP_INT_SIZE < 8 || !is_string($videoId) || !ctype_digit($videoId) || strlen($videoId) < 15 || strlen($videoId) > 19) return null;
        if (strlen($videoId) === 19 && $videoId > (string) PHP_INT_MAX) return null;
        $timestamp = intdiv((int) $videoId, 4_294_967_296);
        try {
            $date = Carbon::createFromTimestampUTC($timestamp);
            $latestYear = (int) now('UTC')->year + 1;
            return $date->year >= 2016 && $date->year <= $latestYear ? $date : null;
        } catch (Throwable) {
            return null;
        }
    }
}
