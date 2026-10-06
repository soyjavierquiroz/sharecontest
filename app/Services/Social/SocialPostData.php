<?php

namespace App\Services\Social;

use Carbon\CarbonInterface;

class SocialPostData
{
    public function __construct(
        public string $platform,
        public string $url,
        public ?string $canonicalUrl = null,
        public ?string $externalId = null,
        public ?string $author = null,
        public ?string $username = null,
        public ?string $caption = null,
        public ?CarbonInterface $publishedAt = null,
        public ?bool $isPublic = null,
        public ?int $likes = null,
        public ?int $comments = null,
        public ?int $views = null,
        public ?string $provider = null,
        public ?array $rawMetadata = null,
        public ?string $error = null,
    ) {}
}
