<?php
namespace Database\Seeders;
use App\Models\Contest;
use Illuminate\Database\Seeder;
class ContestSeeder extends Seeder { public function run(): void { Contest::updateOrCreate(['slug'=>'sharecontest-2026'], ['name'=>'ShareContest 2026','required_hashtag'=>config('contest.hashtag'),'is_active'=>true]); } }
