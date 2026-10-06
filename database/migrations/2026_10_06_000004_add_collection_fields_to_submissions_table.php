<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('submissions', function (Blueprint $table) {
            $table->string('username')->nullable()->after('author');
            $table->jsonb('raw_metadata')->nullable()->after('provider');
        });
    }
    public function down(): void {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['username', 'raw_metadata']);
        });
    }
};
