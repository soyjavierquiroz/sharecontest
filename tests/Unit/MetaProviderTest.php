<?php

namespace Tests\Unit;

use App\Services\Social\FacebookProvider;
use App\Services\Social\InstagramMetaDescriptionParser;
use App\Services\Social\InstagramProvider;
use App\Services\Social\PlatformDetector;
use App\Services\Social\PublicMetadataParser;
use App\Services\Social\SafeSocialHttpClient;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class MetaProviderTest extends TestCase
{
    public function test_instagram_uses_public_open_graph_post_fields_despite_login_copy(): void
    {
        $url = 'https://www.instagram.com/reel/DdMa185s2Vk/';
        $html = <<<'HTML'
            <meta charset="utf-8">
            <meta property="og:title" content="Neringa Križiūtė on Instagram: &quot;🐈‍⬛&quot;">
            <meta property="og:description" content="25K likes, 791 comments - neringakriziute on September 12, 2026: &quot;🐈‍⬛&quot;.">
            <a>Log in</a>
            HTML;

        $provider = new InstagramProvider(app(PlatformDetector::class), $this->httpFor($url, $html), app(PublicMetadataParser::class));
        $data = $provider->inspect($url);

        $this->assertTrue($data->isPublic);
        $this->assertSame('Neringa Križiūtė', $data->author);
        $this->assertSame('neringakriziute', $data->username);
        $this->assertSame('🐈‍⬛', $data->caption);
        $this->assertSame('2026-09-12T00:00:00+00:00', $data->publishedAt?->toIso8601String());
        $this->assertSame(25_000, $data->likes);
        $this->assertSame(791, $data->comments);
        $this->assertNull($data->views);
        $this->assertSame('og:title', $data->rawMetadata['field_sources']['author']);
        $this->assertSame('instagram_meta_description', $data->rawMetadata['field_sources']['likes']);
        $this->assertSame('date', $data->rawMetadata['published_at_precision']);
    }

    public function test_instagram_meta_description_with_localized_connector_is_partial_data_not_a_caption(): void
    {
        $url = 'https://www.instagram.com/reel/example/';
        $html = '<meta property="og:site_name" content="Instagram">'
            .'<meta name="description" content="14K likes, 392 comments - asmagulzarvirk el August 15, 2026">';

        $provider = new InstagramProvider(app(PlatformDetector::class), $this->httpFor($url, $html), app(PublicMetadataParser::class));
        $data = $provider->inspect($url);

        $this->assertTrue($data->isPublic);
        $this->assertNull($data->author);
        $this->assertSame('asmagulzarvirk', $data->username);
        $this->assertSame('2026-08-15T00:00:00+00:00', $data->publishedAt?->toIso8601String());
        $this->assertSame(14_000, $data->likes);
        $this->assertSame(392, $data->comments);
        $this->assertNull($data->caption);
        $this->assertSame('instagram_meta_description', $data->rawMetadata['field_sources']['username']);
        $this->assertSame('instagram_meta_description', $data->rawMetadata['field_sources']['published_at']);
        $this->assertSame('date', $data->rawMetadata['published_at_precision']);
    }

    public function test_instagram_meta_description_parses_compact_metrics_and_caption(): void
    {
        $parsed = app(InstagramMetaDescriptionParser::class)->parse('1.2M likes, 12.3K comments - foo on January 2, 2025: "Texto real"');

        $this->assertSame(1_200_000, $parsed['likes']);
        $this->assertSame(12_300, $parsed['comments']);
        $this->assertSame('foo', $parsed['username']);
        $this->assertSame('2025-01-02', $parsed['published_at']?->toDateString());
        $this->assertSame('Texto real', $parsed['caption']);
        $this->assertSame(1_234, app(InstagramMetaDescriptionParser::class)->metric('1,234'));
        $this->assertNull(app(InstagramMetaDescriptionParser::class)->metric('1,234K'));
    }

    public function test_instagram_meta_description_without_counters_still_extracts_the_real_caption(): void
    {
        $parsed = app(InstagramMetaDescriptionParser::class)->parse('the_amanda_nicole el November 13, 2025: "Needa blue collar man…🔨🧱 @theamandamusic".');

        $this->assertNull($parsed['likes']);
        $this->assertNull($parsed['comments']);
        $this->assertSame('the_amanda_nicole', $parsed['username']);
        $this->assertSame('2025-11-13', $parsed['published_at']?->toDateString());
        $this->assertSame('Needa blue collar man…🔨🧱 @theamandamusic', $parsed['caption']);
    }

    public function test_instagram_rejects_generic_author_but_keeps_real_author(): void
    {
        $this->assertTrue(InstagramProvider::isGenericAuthor('Instagram'));
        $this->assertTrue(InstagramProvider::isGenericAuthor('Instagram Photos and Videos'));
        $this->assertTrue(InstagramProvider::isGenericAuthor('Instagram.com'));
        $this->assertFalse(InstagramProvider::isGenericAuthor('Amanda Nicole'));
    }

    public function test_instagram_keeps_real_caption_when_another_description_is_structured_stats(): void
    {
        $url = 'https://www.instagram.com/reel/real-caption/';
        $html = '<meta charset="utf-8"><meta property="og:title" content="Amanda Nicole on Instagram: &quot;Post&quot;">'
            .'<meta property="og:description" content="Needa blue collar man…🔨🧱 @theamandamusic">'
            .'<meta name="description" content="14K likes, 392 comments - asmagulzarvirk on August 15, 2026">';

        $provider = new InstagramProvider(app(PlatformDetector::class), $this->httpFor($url, $html), app(PublicMetadataParser::class));
        $data = $provider->inspect($url);

        $this->assertSame('Amanda Nicole', $data->author);
        $this->assertSame('Needa blue collar man…🔨🧱 @theamandamusic', $data->caption);
        $this->assertSame('opengraph', $data->rawMetadata['field_sources']['caption']);
    }

    public function test_facebook_correlates_embedded_public_json_to_the_requested_reel(): void
    {
        $url = 'https://www.facebook.com/reel/948990394467707';
        $state = json_encode([
            'data' => [
                'short_form_video_context' => [
                    'video' => ['id' => '948990394467707'],
                    'video_owner' => ['name' => 'Hughes Schools - Cochabamba', 'url' => 'https://www.facebook.com/HughesSchoolsCbba'],
                ],
                'creation_time' => 1_791_149_043,
                'message' => ['text' => 'Dreaming of reaching space?'],
                'feedback' => ['total_comment_count' => 2],
            ],
        ], JSON_THROW_ON_ERROR);
        $html = '<meta charset="utf-8"><meta property="og:title" content="5.9K views · 63 reactions | Hughes Schools">'
            .'<script type="application/json">'.$state.'</script><a>Log in</a>';

        $provider = new FacebookProvider(app(PlatformDetector::class), $this->httpFor($url, $html), app(PublicMetadataParser::class));
        $data = $provider->inspect($url);

        $this->assertTrue($data->isPublic);
        $this->assertSame('Hughes Schools - Cochabamba', $data->author);
        $this->assertSame('HughesSchoolsCbba', $data->username);
        $this->assertSame('Dreaming of reaching space?', $data->caption);
        $this->assertSame(1_791_149_043, $data->publishedAt?->timestamp);
        $this->assertSame(5_900, $data->views);
        $this->assertSame(63, $data->likes);
        $this->assertSame(2, $data->comments);
    }

    private function httpFor(string $url, string $html): SafeSocialHttpClient
    {
        Http::fake([$url => Http::response($html)]);
        $http = Mockery::mock(SafeSocialHttpClient::class);
        $http->shouldReceive('get')->once()->with($url)->andReturnUsing(fn () => [Http::get($url), $url]);

        return $http;
    }
}
