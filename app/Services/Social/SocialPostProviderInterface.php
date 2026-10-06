<?php

namespace App\Services\Social;

interface SocialPostProviderInterface
{
    public function inspect(string $url): SocialPostData;
}
