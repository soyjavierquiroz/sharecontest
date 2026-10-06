<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('contests', function (Blueprint $table) { $table->id(); $table->string('name'); $table->string('slug')->unique(); $table->string('required_hashtag'); $table->timestampTz('starts_at')->nullable(); $table->timestampTz('ends_at')->nullable(); $table->boolean('is_active')->default(true); $table->timestampsTz(); }); } public function down(): void { Schema::dropIfExists('contests'); } };
