<?php

namespace Tests\Feature;

use App\Models\Submission;
use App\Services\Social\SocialPostData;
use App\Services\Social\SocialPostProviderInterface;
use App\Services\Social\SocialProviderFactory;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiInspectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('contest.api_token', 'test-api-token');
        config()->set('contest.api_rate_limit', 60);
    }

    public function test_request_without_token_is_unauthorized(): void
    {
        $this->postJson('/api/v1/inspect', ['url' => $this->url()])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_request_with_an_incorrect_token_is_unauthorized(): void
    {
        $this->withHeader('Authorization', 'Bearer wrong')->postJson('/api/v1/inspect', ['url' => $this->url()])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_request_without_url_is_invalid(): void
    {
        $this->api()->postJson('/api/v1/inspect', [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_REQUEST')
            ->assertJsonStructure(['error' => ['fields' => ['url']]]);
    }

    public function test_unsupported_url_is_invalid(): void
    {
        $this->api()->postJson('/api/v1/inspect', ['url' => 'https://example.test/post'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_REQUEST');
    }

    public function test_inspection_without_rules_is_not_requested(): void
    {
        $this->fakeProvider(new SocialPostData('tiktok', $this->url(), isPublic: true, caption: 'A public post', provider: 'fake'));

        $this->api()->postJson('/api/v1/inspect', ['url' => $this->url()])
            ->assertOk()
            ->assertJsonPath('validation.status', 'not_requested')
            ->assertJsonPath('data.metrics.views', null)
            ->assertHeaderMissing('set-cookie');
    }

    public function test_found_hashtag_passes(): void
    {
        $this->fakeProvider(new SocialPostData('tiktok', $this->url(), isPublic: true, caption: 'Join #HakawiChallenge now', provider: 'fake'));

        $this->inspect(['required_hashtags' => ['#hakawichallenge']])
            ->assertJsonPath('validation.checks.hashtags.status', 'passed')
            ->assertJsonPath('validation.checks.hashtags.found.0', '#hakawichallenge');
    }

    public function test_missing_hashtag_with_caption_fails(): void
    {
        $this->fakeProvider(new SocialPostData('tiktok', $this->url(), isPublic: true, caption: 'No matching tag', provider: 'fake'));

        $this->inspect(['required_hashtags' => ['#HakawiChallenge']])
            ->assertJsonPath('validation.checks.hashtags.status', 'failed')
            ->assertJsonPath('validation.status', 'invalid');
    }

    public function test_hashtag_without_caption_is_unknown(): void
    {
        $this->fakeProvider(new SocialPostData('tiktok', $this->url(), isPublic: true, caption: null, provider: 'fake'));

        $this->inspect(['required_hashtags' => ['#HakawiChallenge']])
            ->assertJsonPath('validation.checks.hashtags.status', 'unknown')
            ->assertJsonPath('validation.status', 'review_required');
    }

    public function test_found_required_mention_passes(): void
    {
        $this->fakeProvider(new SocialPostData('tiktok', $this->url(), isPublic: true, caption: 'Thanks @Hakawi!', provider: 'fake'));

        $this->inspect(['required_mentions' => ['@hakawi']])
            ->assertJsonPath('validation.checks.mentions.status', 'passed');
    }

    public function test_mention_without_observable_text_is_unknown(): void
    {
        $this->fakeProvider(new SocialPostData('instagram', $this->url('instagram'), isPublic: true, caption: null, provider: 'fake'));

        $this->inspect(['required_mentions' => ['@hakawi']], $this->url('instagram'))
            ->assertJsonPath('validation.checks.mentions.status', 'unknown');
    }

    public function test_publication_date_in_inclusive_range_passes(): void
    {
        $this->fakeProvider(new SocialPostData('tiktok', $this->url(), isPublic: true, publishedAt: Carbon::parse('2026-10-01T12:00:00Z'), provider: 'fake'));

        $this->inspect(['published_from' => '2026-10-01', 'published_until' => '2026-10-31'])
            ->assertJsonPath('validation.checks.published_at.status', 'passed');
    }

    public function test_missing_publication_date_is_unknown(): void
    {
        $this->fakeProvider(new SocialPostData('tiktok', $this->url(), isPublic: true, provider: 'fake'));

        $this->inspect(['published_from' => '2026-10-01'])
            ->assertJsonPath('validation.checks.published_at.status', 'unknown');
    }

    public function test_any_failed_check_makes_overall_validation_invalid(): void
    {
        $this->fakeProvider(new SocialPostData('tiktok', $this->url(), isPublic: true, caption: '#ok', provider: 'fake'));

        $this->inspect(['allowed_platforms' => ['instagram'], 'required_hashtags' => ['#ok']])
            ->assertJsonPath('validation.checks.platform.status', 'failed')
            ->assertJsonPath('validation.status', 'invalid');
    }

    public function test_unknown_without_failure_requires_review(): void
    {
        $this->fakeProvider(new SocialPostData('tiktok', $this->url(), isPublic: true, caption: null, provider: 'fake'));

        $this->inspect(['allowed_platforms' => ['tiktok'], 'required_hashtags' => ['#ok']])
            ->assertJsonPath('validation.status', 'review_required');
    }

    public function test_all_checks_pass_make_overall_validation_valid(): void
    {
        $this->fakeProvider(new SocialPostData('tiktok', $this->url(), isPublic: true, caption: '#HakawiChallenge @hakawi', publishedAt: Carbon::parse('2026-10-15T12:00:00Z'), provider: 'fake'));

        $this->inspect([
            'allowed_platforms' => ['tiktok'],
            'required_hashtags' => ['#hakawichallenge'],
            'required_mentions' => ['@Hakawi'],
            'published_from' => '2026-10-01',
            'published_until' => '2026-10-31',
        ])->assertJsonPath('validation.status', 'valid');
    }

    public function test_repeated_external_reference_refreshes_instead_of_creating_a_duplicate(): void
    {
        $this->fakeProvider(new SocialPostData('tiktok', $this->url(), isPublic: true, caption: 'First', provider: 'fake'));
        $payload = ['url' => $this->url(), 'external_reference' => 'participation-9282', 'campaign_reference' => 'campaign-81'];

        $first = $this->api()->postJson('/api/v1/inspect', $payload)->assertOk();
        $second = $this->api()->postJson('/api/v1/inspect', $payload)->assertOk();

        $this->assertSame($first->json('data.sharecontest_id'), $second->json('data.sharecontest_id'));
        $this->assertSame(1, Submission::where('source', 'api')->where('external_reference', 'participation-9282')->count());
    }

    public function test_unknown_metrics_remain_null_in_the_response(): void
    {
        $this->fakeProvider(new SocialPostData('tiktok', $this->url(), isPublic: true, author: 'Author', provider: 'fake'));

        $this->inspect()
            ->assertJsonPath('data.metrics.views', null)
            ->assertJsonPath('data.metrics.likes', null)
            ->assertJsonPath('data.metrics.comments', null);
    }

    private function inspect(array $rules = [], ?string $url = null)
    {
        return $this->api()->postJson('/api/v1/inspect', [
            'url' => $url ?? $this->url(),
            'rules' => $rules,
        ])->assertOk();
    }

    private function api()
    {
        return $this->withHeader('Authorization', 'Bearer test-api-token');
    }

    private function url(string $platform = 'tiktok'): string
    {
        return match ($platform) {
            'instagram' => 'https://www.instagram.com/reel/ABC123/',
            default => 'https://www.tiktok.com/@demo/video/1234567890123456789',
        };
    }

    private function fakeProvider(SocialPostData $data): void
    {
        $provider = new class($data) implements SocialPostProviderInterface
        {
            public function __construct(private SocialPostData $data) {}

            public function inspect(string $url): SocialPostData
            {
                return $this->data;
            }
        };
        $factory = $this->mock(SocialProviderFactory::class);
        $factory->shouldReceive('for')->zeroOrMoreTimes()->andReturn($provider);
    }
}
