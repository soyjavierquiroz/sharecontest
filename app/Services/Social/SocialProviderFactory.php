<?php

namespace App\Services\Social;

class SocialProviderFactory
{
    public function __construct(private TikTokProvider $tiktok, private InstagramProvider $instagram, private FacebookProvider $facebook) {}
    public function for(string $platform): SocialPostProviderInterface
    {
        return match ($platform) { 'tiktok' => $this->tiktok, 'instagram' => $this->instagram, 'facebook' => $this->facebook, default => throw new \InvalidArgumentException('Plataforma desconocida.') };
    }
}
