<?php
namespace Tests\Feature;
use App\Models\Contest;
use App\Models\Submission;
use App\Services\RefreshSubmission;
use App\Services\Social\SocialPostData;
use App\Services\Social\SocialPostProviderInterface;
use App\Services\Social\SocialProviderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class SubmissionStatusTest extends TestCase {
    use RefreshDatabase;
    public function test_known_caption_validates_required_hashtag(): void { $contest=Contest::create(['name'=>'Test','slug'=>'test','required_hashtag'=>'#MiConcurso2026','is_active'=>true]); $s=Submission::create(['contest_id'=>$contest->id,'platform'=>'tiktok','original_url'=>'https://www.tiktok.com/@a/video/1','status'=>'pending']); $provider=new class implements SocialPostProviderInterface { public function inspect(string $url): SocialPostData { return new SocialPostData('tiktok',$url,isPublic:true,caption:'Hola #miconcurso2026',provider:'fake'); } }; $factory=$this->mock(SocialProviderFactory::class); $factory->shouldReceive('for')->andReturn($provider); app(RefreshSubmission::class)->handle($s->load('contest')); $this->assertSame('valid',$s->fresh()->status); $this->assertTrue($s->fresh()->hashtag_valid); }
    public function test_missing_metadata_requires_review_not_rejection(): void { $contest=Contest::create(['name'=>'Test','slug'=>'test2','required_hashtag'=>'#MiConcurso2026','is_active'=>true]); $s=Submission::create(['contest_id'=>$contest->id,'platform'=>'instagram','original_url'=>'https://www.instagram.com/reel/x','status'=>'pending']); $provider=new class implements SocialPostProviderInterface { public function inspect(string $url): SocialPostData { return new SocialPostData('instagram',$url,error:'Login wall'); } }; $factory=$this->mock(SocialProviderFactory::class); $factory->shouldReceive('for')->andReturn($provider); app(RefreshSubmission::class)->handle($s->load('contest')); $this->assertSame('review_required',$s->fresh()->status); $this->assertNull($s->fresh()->hashtag_valid); }
}
