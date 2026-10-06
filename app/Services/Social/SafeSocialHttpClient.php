<?php

namespace App\Services\Social;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SafeSocialHttpClient
{
    public function __construct(private PlatformDetector $detector) {}

    /** @return array{0: Response, 1: string} */
    public function get(string $url): array
    {
        $current = $url;
        for ($i = 0; $i < 4; $i++) {
            $this->assertSafe($current);
            $response = Http::timeout(8)->connectTimeout(4)->withOptions(['allow_redirects' => false])
                ->withHeaders(['User-Agent' => 'ShareContest/1.0 (+public metadata validation)', 'Accept' => 'text/html,application/json;q=0.9,*/*;q=0.8'])
                ->get($current);
            if (!$response->redirect()) return [$response, $current];
            $location = $response->header('Location');
            if (!$location) return [$response, $current];
            $current = $this->absoluteUrl($current, $location);
        }
        throw new RuntimeException('Demasiadas redirecciones.');
    }

    private function assertSafe(string $url): void
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';
        if (!in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || !$this->detector->isAllowedHost($host)) {
            throw new RuntimeException('URL de redirección no permitida.');
        }
        $ip = gethostbyname($host);
        if ($ip !== $host && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new RuntimeException('Host no seguro.');
        }
    }

    private function absoluteUrl(string $base, string $location): string
    {
        if (filter_var($location, FILTER_VALIDATE_URL)) return $location;
        $parts = parse_url($base);
        if (str_starts_with($location, '//')) return $parts['scheme'].':'.$location;
        if (str_starts_with($location, '/')) return $parts['scheme'].'://'.$parts['host'].$location;
        return $parts['scheme'].'://'.$parts['host'].'/'.ltrim($location, '/');
    }
}
