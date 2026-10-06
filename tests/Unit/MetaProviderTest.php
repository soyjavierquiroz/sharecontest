<?php

namespace Tests\Unit;

use App\Services\Social\FacebookProvider;
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
            . '<script type="application/json">'.$state.'</script><a>Log in</a>';

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
