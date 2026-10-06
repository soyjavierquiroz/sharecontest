<?php
namespace App\Console\Commands;
use App\Jobs\RefreshSubmissionJob;
use App\Models\Submission;
use Illuminate\Console\Command;
class RefreshContestSubmissions extends Command { protected $signature = 'contest:refresh {--all} {--status=} {--top=}'; protected $description = 'Queue a refresh for ShareContest submissions'; public function handle(): int { $query = Submission::query(); if ($status = $this->option('status')) $query->where('status', $status); if ($top = $this->option('top')) $query->orderByDesc('views')->limit((int) $top); $ids = $query->pluck('id'); foreach ($ids as $id) RefreshSubmissionJob::dispatch($id); $this->info("Queued {$ids->count()} submissions."); return self::SUCCESS; } }
