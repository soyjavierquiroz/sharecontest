<?php
namespace App\Jobs;
use App\Models\Submission;
use App\Services\RefreshSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
class RefreshSubmissionJob implements ShouldQueue { use Dispatchable, InteractsWithQueue, Queueable, SerializesModels; public int $timeout = 60; public int $tries = 3; public function __construct(public int $submissionId) {} public function handle(RefreshSubmission $refresh): void { $submission = Submission::with('contest')->find($this->submissionId); if ($submission) $refresh->handle($submission); } }
