<?php

namespace App\Services\Social;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

class TikTokProvider implements SocialPostProviderInterface
{
    public function __construct(private PlatformDetector $detector) {}

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
            $data->canonicalUrl = $body['author_url'] ?? $data->canonicalUrl;
            if (!empty($body['create_time'])) { try { $data->publishedAt = Carbon::createFromTimestamp((int) $body['create_time']); } catch (Throwable) {} }
            $data->rawMetadata = $body;
        } catch (Throwable $e) { $data->error = 'No se pudo consultar TikTok oEmbed: '.$e->getMessage(); }
        return $data;
    }
}
