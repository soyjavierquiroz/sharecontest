<?php
namespace App\Http\Controllers;
use App\Models\Contest;
use App\Models\Submission;
use App\Services\RefreshSubmission;
use App\Services\Social\PlatformDetector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
class SubmissionController extends Controller {
    public function store(Request $request, PlatformDetector $detector, RefreshSubmission $refresh): RedirectResponse {
        $input = $request->validate(['url'=>['required','string','max:2048'],'participant_name'=>['nullable','string','max:120'],'participant_email'=>['nullable','email','max:255']]);
        try { $platform = $detector->detect($input['url']); $canonical = $detector->normalize($input['url']); $externalId = $detector->externalId($input['url']); } catch (InvalidArgumentException $e) { return back()->withInput()->withErrors(['url'=>$e->getMessage()]); }
        $contest = Contest::where('is_active', true)->firstOrFail();
        $duplicate = Submission::where('contest_id',$contest->id)->where(function ($q) use ($canonical, $externalId) { $q->where('canonical_url',$canonical); if ($externalId) $q->orWhere('external_id',$externalId); })->first();
        if ($duplicate) return redirect()->route('submissions.show',$duplicate)->with('notice','Esta publicación ya está registrada.');
        $submission = DB::transaction(fn() => Submission::create(['contest_id'=>$contest->id,'participant_name'=>$input['participant_name'] ?? null,'participant_email'=>$input['participant_email'] ?? null,'platform'=>$platform,'original_url'=>$input['url'],'canonical_url'=>$canonical,'external_id'=>$externalId,'status'=>'pending']));
        $refresh->handle($submission->load('contest'));
        return redirect()->route('submissions.show',$submission);
    }
    public function show(Submission $submission) { return view('submission-result', compact('submission')); }
}
