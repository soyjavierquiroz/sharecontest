<?php

namespace App\Services\Social;

use Carbon\Carbon;
use Throwable;

abstract class AbstractOpenGraphProvider implements SocialPostProviderInterface
{
    abstract protected function platform(): string;

    public function __construct(protected PlatformDetector $detector, protected SafeSocialHttpClient $http) {}

    public function inspect(string $url): SocialPostData
    {
        $data = new SocialPostData($this->platform(), $url, $this->detector->normalize($url), $this->detector->externalId($url), provider: 'opengraph');
        try {
            [$response, $finalUrl] = $this->http->get($url);
            $data->canonicalUrl = $this->detector->normalize($finalUrl);
            if ($response->status() === 404) { $data->isPublic = false; $data->error = 'Publicación no encontrada.'; return $data; }
            if (!$response->successful()) { $data->error = 'La plataforma respondió HTTP '.$response->status(); return $data; }
            $html = $response->body();
            if (preg_match('/\b(log in|login|inicia sesi[oó]n|challenge_required)\b/i', $html)) { $data->error = 'La plataforma requiere inicio de sesión.'; return $data; }
            $meta = $this->meta($html);
            $data->isPublic = true;
            $data->caption = $meta['og:description'] ?? $meta['description'] ?? null;
            $data->author = $meta['author'] ?? $meta['og:site_name'] ?? null;
            if (!empty($meta['article:published_time'])) { try { $data->publishedAt = Carbon::parse($meta['article:published_time']); } catch (Throwable) {} }
            $data->rawMetadata = $meta;
            return $data;
        } catch (Throwable $e) {
            $data->error = 'No se pudo consultar la plataforma: '.$e->getMessage();
            return $data;
        }
    }

    private function meta(string $html): array
    {
        $meta = [];
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        if (@$doc->loadHTML($html)) foreach ($doc->getElementsByTagName('meta') as $node) {
            $key = strtolower($node->getAttribute('property') ?: $node->getAttribute('name'));
            $value = trim($node->getAttribute('content'));
            if ($key && $value && !isset($meta[$key])) $meta[$key] = $value;
        }
        return $meta;
    }
}
