<?php

namespace Tests\Unit;

use App\Services\Social\PlatformDetector;
use App\Services\Social\PublicMetadataParser;
use App\Services\Social\SafeSocialHttpClient;
use App\Services\Social\TikTokProvider;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class TikTokProviderTest extends TestCase
{
    public function test_tiktok_timestamp_is_inferred_from_a_valid_video_id(): void
    {
        $timestamp = 1_725_000_000;
        $id = (string) (($timestamp << 32) + 7);

        $date = app(TikTokProvider::class)->timestampFromVideoId($id);

        $this->assertNotNull($date);
        $this->assertSame($timestamp, $date->timestamp);
        $this->assertSame(0, $date->utcOffset());
    }

    public function test_invalid_tiktok_video_id_does_not_produce_a_date(): void
    {
        $provider = app(TikTokProvider::class);

        $this->assertNull($provider->timestampFromVideoId('not-a-video-id'));
        $this->assertNull($provider->timestampFromVideoId('123'));
        $this->assertNull($provider->timestampFromVideoId((string) ((2_000_000_000 << 32) + 1)));
    }

    public function test_direct_public_metadata_date_takes_priority_over_video_id_fallback(): void
    {
        $url = 'https://www.tiktok.com/@ruta/video/7408818589700000007';
        Http::fake([
            'https://www.tiktok.com/oembed*' => Http::response([
                'author_name' => 'La Ruta del dinero',
                'author_unique_id' => 'larutadeldinero52',
                'title' => 'Datos directos',
                'create_time' => 1_700_000_000,
            ]),
            $url => Http::response('<html><head><meta property="og:description" content="Descripción"></head></html>'),
        ]);
        $http = Mockery::mock(SafeSocialHttpClient::class);
        $http->shouldReceive('get')->once()->with($url)->andReturnUsing(fn () => [Http::get($url), $url]);
        $provider = new TikTokProvider(app(PlatformDetector::class), $http, app(PublicMetadataParser::class));

        $data = $provider->inspect($url);

        $this->assertSame(1_700_000_000, $data->publishedAt?->timestamp);
        $this->assertSame('public_metadata', $data->rawMetadata['published_at_source']);
        $this->assertSame('direct', $data->rawMetadata['published_at_confidence']);
    }

    public function test_embedded_public_json_populates_metrics_without_turning_missing_values_into_zero(): void
    {
        $url = 'https://www.tiktok.com/@ruta/video/7408818589700000007';
        Http::fake([
            'https://www.tiktok.com/oembed*' => Http::response(['author_name' => 'La Ruta del dinero', 'title' => 'Post']),
            $url => Http::response('<script id="SIGI_STATE" type="application/json">{"ItemModule":{"one":{"stats":{"playCount":1234,"diggCount":98}}}}</script>'),
        ]);
        $http = Mockery::mock(SafeSocialHttpClient::class);
        $http->shouldReceive('get')->once()->with($url)->andReturnUsing(fn () => [Http::get($url), $url]);
        $provider = new TikTokProvider(app(PlatformDetector::class), $http, app(PublicMetadataParser::class));

        $data = $provider->inspect($url);

        $this->assertSame(1234, $data->views);
        $this->assertSame(98, $data->likes);
        $this->assertNull($data->comments);
    }
}
