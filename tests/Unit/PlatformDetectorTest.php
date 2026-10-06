<?php
namespace Tests\Unit;
use App\Services\Social\PlatformDetector;
use InvalidArgumentException;
use Tests\TestCase;
class PlatformDetectorTest extends TestCase {
    public function test_detects_supported_platforms(): void { $d = app(PlatformDetector::class); $this->assertSame('tiktok',$d->detect('https://www.tiktok.com/@demo/video/123')); $this->assertSame('instagram',$d->detect('https://instagram.com/reel/ABC123/')); $this->assertSame('facebook',$d->detect('https://fb.watch/example/')); }
    public function test_rejects_invalid_and_ssrf_hosts(): void { $d = app(PlatformDetector::class); foreach (['file:///etc/passwd','http://127.0.0.1/x','https://evil.example/video'] as $url) { try { $d->detect($url); $this->fail('Expected invalid URL.'); } catch (InvalidArgumentException) { $this->assertTrue(true); } } }
    public function test_extracts_public_ids(): void { $d = app(PlatformDetector::class); $this->assertSame('123',$d->externalId('https://www.tiktok.com/@demo/video/123')); $this->assertSame('ABC123',$d->externalId('https://www.instagram.com/reel/ABC123/')); }
}
