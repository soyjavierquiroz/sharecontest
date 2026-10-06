<?php

namespace App\Services\Social;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

class TikTokProvider implements SocialPostProviderInterface
{
    public function __construct(private PlatformDetector $detector, private SafeSocialHttpClient $http, private PublicMetadataParser $parser) {}

    public function inspect(string $url): SocialPostData
    {
        $data = new SocialPostData('tiktok', $url, $this->detector->normalize($url), $this->detector->externalId($url), provider: 'tiktok-oembed');
        try {
            $response = Http::timeout(8)->connectTimeout(4)->acceptJson()->get('https://www.tiktok.com/oembed', ['url' => $url]);
            if (!$response->successful()) { $data->error = 'TikTok oEmbed respondió HTTP '.$response->status(); return $data; }
            $body = $response->json();
            if (!is_array($body)) { $data->error = 'Respuesta oEmbed inválida.'; return $data; }
            $data->isPublic = true;
            $data->author = $body['author_name'] ?? null;
            $data->caption = $body['title'] ?? null;
            $data->username = isset($body['author_unique_id']) ? ltrim((string) $body['author_unique_id'], '@') : null;
            if (!empty($body['create_time'])) { try { $data->publishedAt = Carbon::createFromTimestamp((int) $body['create_time']); } catch (Throwable) {} }
            $data->rawMetadata = $body;
            $this->enrichFromPublicHtml($data, $url);
        } catch (Throwable $e) { $data->error = 'No se pudo consultar TikTok oEmbed: '.$e->getMessage(); }
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
            $data->caption ??= $meta['og:description'] ?? $meta['description'] ?? null;
            $data->author ??= $meta['author'] ?? null;
            $data->username ??= $this->parser->username($metadata);
            $data->publishedAt ??= $this->parser->date($metadata);
            $data->views = $metrics['views'] ?? $data->views;
            $data->likes = $metrics['likes'] ?? $data->likes;
            $data->comments = $metrics['comments'] ?? $data->comments;
            $data->rawMetadata = ['oembed' => $data->rawMetadata, ...$this->parser->storageMetadata($metadata)];
        } catch (Throwable) {
            // oEmbed already gave us the public best-effort result; HTML enrichment is optional.
        }
    }
}
