<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('submission_metric_snapshots', function (Blueprint $table) { $table->id(); $table->foreignId('submission_id')->constrained()->cascadeOnDelete(); $table->unsignedBigInteger('views')->nullable(); $table->unsignedBigInteger('likes')->nullable(); $table->unsignedBigInteger('comments')->nullable(); $table->timestampTz('captured_at'); $table->string('provider')->nullable(); $table->timestampsTz(); }); } public function down(): void { Schema::dropIfExists('submission_metric_snapshots'); } };
