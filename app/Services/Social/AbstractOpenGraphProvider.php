<?php

namespace App\Services\Social;

use Throwable;

abstract class AbstractOpenGraphProvider implements SocialPostProviderInterface
{
    abstract protected function platform(): string;

    public function __construct(protected PlatformDetector $detector, protected SafeSocialHttpClient $http, protected PublicMetadataParser $parser) {}

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
            $metadata = $this->parser->parse($html);
            $meta = $metadata['meta'];
            $metrics = $this->parser->metrics($metadata);
            $data->isPublic = true;
            $data->caption = $meta['og:description'] ?? $meta['description'] ?? null;
            $data->author = $meta['author'] ?? $meta['og:site_name'] ?? null;
            $data->username = $this->parser->username($metadata);
            $data->publishedAt = $this->parser->date($metadata);
            $data->views = $metrics['views'];
            $data->likes = $metrics['likes'];
            $data->comments = $metrics['comments'];
            $data->rawMetadata = $this->parser->storageMetadata($metadata);
            return $data;
        } catch (Throwable $e) {
            $data->error = 'No se pudo consultar la plataforma: '.$e->getMessage();
            return $data;
        }
    }
}
