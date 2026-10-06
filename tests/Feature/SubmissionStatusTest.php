<?php

namespace Tests\Feature;

use App\Models\Contest;
use App\Models\Submission;
use App\Services\RefreshSubmission;
use App\Services\Social\SocialPostData;
use App\Services\Social\SocialPostProviderInterface;
use App\Services\Social\SocialProviderFactory;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_caption_without_hashtag_is_collected_and_not_invalidated(): void
    {
        $submission = $this->submission('tiktok');
        $this->refreshWith(new class implements SocialPostProviderInterface
        {
            public function inspect(string $url): SocialPostData
            {
                return new SocialPostData('tiktok', $url, isPublic: true, caption: 'Texto público sin reglas de concurso.', provider: 'fake');
            }
        }, $submission);

        $stored = $submission->fresh();
        $this->assertSame('valid', $stored->status);
        $this->assertNull($stored->hashtag_valid);
        $this->assertNull($stored->views);
        $this->assertNull($stored->likes);
        $this->assertNull($stored->comments);
    }

    public function test_publication_date_is_saved_when_the_provider_supplies_it(): void
    {
        $submission = $this->submission('instagram');
        $publishedAt = Carbon::parse('2026-02-03T04:05:06Z');
        $this->refreshWith(new class($publishedAt) implements SocialPostProviderInterface
        {
            public function __construct(private Carbon $publishedAt) {}

            public function inspect(string $url): SocialPostData
            {
                return new SocialPostData('instagram', $url, isPublic: true, author: 'author', publishedAt: $this->publishedAt, views: 0, provider: 'fake');
            }
        }, $submission);

        $stored = $submission->fresh();
        $this->assertSame('valid', $stored->status);
        $this->assertTrue($stored->published_at->equalTo($publishedAt));
        $this->assertSame(0, $stored->views);
        $this->assertNull($stored->hashtag_valid);
    }

    public function test_unknown_publication_date_remains_null(): void
    {
        $submission = $this->submission('facebook');
        $this->refreshWith(new class implements SocialPostProviderInterface
        {
            public function inspect(string $url): SocialPostData
            {
                return new SocialPostData('facebook', $url, isPublic: true, author: 'author', provider: 'fake');
            }
        }, $submission);

        $this->assertNull($submission->fresh()->published_at);
    }

    public function test_refresh_does_not_erase_existing_metrics_when_a_later_extraction_has_none(): void
    {
        $submission = $this->submission('tiktok');
        $submission->update(['views' => 50000, 'likes' => 4000, 'comments' => 99, 'is_public' => true]);
        $this->refreshWith(new class implements SocialPostProviderInterface
        {
            public function inspect(string $url): SocialPostData
            {
                return new SocialPostData('tiktok', $url, isPublic: true, author: 'Ruta', provider: 'fake');
            }
        }, $submission);

        $stored = $submission->fresh();
        $this->assertSame(50000, $stored->views);
        $this->assertSame(4000, $stored->likes);
        $this->assertSame(99, $stored->comments);
        $this->assertCount(0, $stored->metricSnapshots);
    }

    public function test_accessible_publication_without_metrics_is_not_invalid(): void
    {
        $submission = $this->submission('tiktok');
        $this->refreshWith(new class implements SocialPostProviderInterface
        {
            public function inspect(string $url): SocialPostData
            {
                return new SocialPostData('tiktok', $url, isPublic: true, author: 'Ruta', caption: 'Post público', provider: 'fake');
            }
        }, $submission);

        $stored = $submission->fresh();
        $this->assertSame('valid', $stored->status);
        $this->assertTrue($stored->is_public);
        $this->assertNull($stored->views);
        $this->assertNull($stored->likes);
        $this->assertNull($stored->comments);
    }

    public function test_external_errors_require_review_and_do_not_reject(): void
    {
        $submission = $this->submission('instagram');
        $this->refreshWith(new class implements SocialPostProviderInterface
        {
            public function inspect(string $url): SocialPostData
            {
                return new SocialPostData('instagram', $url, error: 'Login wall');
            }
        }, $submission);

        $stored = $submission->fresh();
        $this->assertSame('review_required', $stored->status);
        $this->assertNull($stored->hashtag_valid);
        $this->assertNull($stored->published_at);
    }

    public function test_instagram_refresh_removes_legacy_generic_values_but_preserves_a_precise_timestamp(): void
    {
        $submission = $this->submission('instagram');
        $preciseDate = Carbon::parse('2026-08-15T14:35:00Z');
        $legacyCaption = '14K likes, 392 comments - asmagulzarvirk el August 15, 2026';
        $submission->update([
            'author' => 'Instagram',
            'caption' => $legacyCaption,
            'published_at' => $preciseDate,
            'raw_metadata' => ['field_sources' => ['published_at' => 'public_metadata']],
        ]);
        $this->refreshWith(new class implements SocialPostProviderInterface
        {
            public function inspect(string $url): SocialPostData
            {
                return new SocialPostData('instagram', $url, isPublic: true, username: 'asmagulzarvirk', publishedAt: Carbon::parse('2026-08-15T00:00:00Z'), likes: 14_000, comments: 392, provider: 'fake', rawMetadata: [
                    'structured_meta_description' => true,
                    'published_at_precision' => 'date',
                    'published_at_source' => 'instagram_meta_description',
                    'field_sources' => ['username' => 'instagram_meta_description', 'published_at' => 'instagram_meta_description'],
                ]);
            }
        }, $submission);

        $stored = $submission->fresh();
        $this->assertNull($stored->author);
        $this->assertNull($stored->caption);
        $this->assertSame('asmagulzarvirk', $stored->username);
        $this->assertSame(14_000, $stored->likes);
        $this->assertSame(392, $stored->comments);
        $this->assertTrue($stored->published_at->equalTo($preciseDate));
        $this->assertSame('public_metadata', $stored->raw_metadata['field_sources']['published_at']);
    }

    private function submission(string $platform): Submission
    {
        $contest = Contest::create(['name' => 'Test', 'slug' => 'test-'.$platform, 'required_hashtag' => '#MiConcurso2026', 'is_active' => true]);

        return Submission::create(['contest_id' => $contest->id, 'platform' => $platform, 'original_url' => 'https://example.test/post', 'status' => 'pending']);
    }

    private function refreshWith(SocialPostProviderInterface $provider, Submission $submission): void
    {
        $factory = $this->mock(SocialProviderFactory::class);
        $factory->shouldReceive('for')->andReturn($provider);
        app(RefreshSubmission::class)->handle($submission->load('contest'));
    }
}
