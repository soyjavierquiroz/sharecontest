<?php

namespace App\Services\Social;

use InvalidArgumentException;

class PlatformDetector
{
    private const HOSTS = [
        'tiktok' => ['tiktok.com', 'www.tiktok.com', 'vm.tiktok.com', 'vt.tiktok.com'],
        'instagram' => ['instagram.com', 'www.instagram.com'],
        'facebook' => ['facebook.com', 'www.facebook.com', 'm.facebook.com', 'fb.watch'],
    ];

    public function detect(string $url): string
    {
        $parts = $this->parse($url);
        $host = strtolower($parts['host']);
        foreach (self::HOSTS as $platform => $hosts) {
            if (in_array($host, $hosts, true)) return $platform;
        }
        throw new InvalidArgumentException('Solo se permiten enlaces de TikTok, Instagram o Facebook.');
    }

    public function normalize(string $url): string
    {
        $parts = $this->parse($url);
        $platform = $this->detect($url);
        $host = strtolower($parts['host']);
        $path = '/'.ltrim($parts['path'] ?? '', '/');
        $path = $path === '/' ? '/' : rtrim($path, '/');
        $canonicalHost = match ($platform) {
            'tiktok' => 'www.tiktok.com',
            'instagram' => 'www.instagram.com',
            default => $host === 'fb.watch' ? 'fb.watch' : 'www.facebook.com',
        };
        return 'https://'.$canonicalHost.$path;
    }

    public function externalId(string $url): ?string
    {
        $platform = $this->detect($url);
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        return match ($platform) {
            'tiktok' => preg_match('~/video/(\d+)~', $path, $m) ? $m[1] : null,
            'instagram' => preg_match('~/(?:p|reel|reels|tv)/([^/?]+)~', $path, $m) ? $m[1] : null,
            'facebook' => preg_match('~/(?:videos|reel)/([^/?]+)~', $path, $m) ? $m[1] : (preg_match('~/(\d+)(?:/|$)~', $path, $m) ? $m[1] : null),
        };
    }

    public function isAllowedHost(string $host): bool
    {
        return in_array(strtolower(rtrim($host, '.')), array_merge(...array_values(self::HOSTS)), true);
    }

    private function parse(string $url): array
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) throw new InvalidArgumentException('La URL no es válida.');
        $parts = parse_url($url);
        if (!in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            throw new InvalidArgumentException('Solo se permiten URLs HTTP/HTTPS.');
        }
        return $parts;
    }
}
