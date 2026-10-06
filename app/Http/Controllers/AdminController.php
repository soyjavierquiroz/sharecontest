<?php

namespace App\Http\Controllers;

use App\Jobs\RefreshSubmissionJob;
use App\Models\Submission;
use App\Presenters\SubmissionDataStatus;
use App\Services\RefreshSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $query = Submission::query();
        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->trim().'%';
            $query->where(fn ($q) => $q->where('author', 'ilike', $term)->orWhere('username', 'ilike', $term)->orWhere('original_url', 'ilike', $term));
        }
        if (in_array($request->string('platform')->toString(), ['tiktok', 'instagram', 'facebook'], true)) {
            $query->where('platform', $request->string('platform'));
        }
        if (in_array($request->string('data_status')->toString(), ['complete', 'partial', 'no_data', 'error'], true)) {
            SubmissionDataStatus::filter($query, $request->string('data_status')->toString());
        }
        $sort = $request->string('sort', 'created_at')->toString();
        $column = in_array($sort, ['created_at', 'published_at', 'views', 'likes', 'last_checked_at'], true) ? $sort : 'created_at';
        $submissions = $query->orderByDesc($column)->orderByDesc('id')->paginate(30)->withQueryString();
        $submissions->getCollection()->each(fn (Submission $submission) => $submission->setAttribute('data_status', SubmissionDataStatus::for($submission)));

        $all = Submission::all(['id', 'platform', 'status', 'is_public', 'author', 'username', 'caption', 'published_at', 'views', 'likes', 'comments']);
        $stats = [
            'total' => $all->count(),
            'complete' => $this->countByDataStatus($all, 'complete'),
            'partial' => $this->countByDataStatus($all, 'partial'),
            'no_data' => $this->countByDataStatus($all, 'no_data'),
            'tiktok' => $all->where('platform', 'tiktok')->count(),
            'instagram' => $all->where('platform', 'instagram')->count(),
            'facebook' => $all->where('platform', 'facebook')->count(),
        ];

        return view('admin.index', compact('submissions', 'stats'));
    }

    public function show(Submission $submission)
    {
        $submission->setAttribute('data_status', SubmissionDataStatus::for($submission));
        $rawMetadata = json_encode($this->withoutSecrets($submission->raw_metadata ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return view('admin.show', compact('submission', 'rawMetadata'));
    }

    public function refresh(Submission $submission, RefreshSubmission $refresh): RedirectResponse
    {
        $refresh->handle($submission);

        return back()->with('notice', 'Publicación actualizada.');
    }

    public function refreshAll(): RedirectResponse
    {
        $count = 0;
        Submission::query()->select('id')->orderBy('id')->chunkById(100, function (Collection $submissions) use (&$count): void {
            foreach ($submissions as $submission) {
                RefreshSubmissionJob::dispatch($submission->id);
                $count++;
            }
        });

        return back()->with('notice', "Se encolaron {$count} actualizaciones.");
    }

    public function refreshSelected(Request $request): RedirectResponse
    {
        $validated = $request->validate(['submissions' => ['required', 'array', 'min:1'], 'submissions.*' => ['integer', 'exists:submissions,id']]);
        foreach ($validated['submissions'] as $id) RefreshSubmissionJob::dispatch($id);

        return back()->with('notice', 'Se encolaron '.count($validated['submissions']).' actualizaciones.');
    }

    public function destroy(Submission $submission): RedirectResponse
    {
        $submission->delete();

        return redirect()->route('admin.index')->with('notice', 'Publicación eliminada.');
    }

    private function countByDataStatus(Collection $submissions, string $status): int
    {
        return $submissions->filter(fn (Submission $submission) => SubmissionDataStatus::for($submission) === $status)->count();
    }

    private function withoutSecrets(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        $safe = [];
        foreach ($value as $key => $item) {
            if (preg_match('/token|secret|password|cookie|authorization/i', (string) $key)) continue;
            $safe[$key] = $this->withoutSecrets($item);
        }
        return $safe;
    }
}
