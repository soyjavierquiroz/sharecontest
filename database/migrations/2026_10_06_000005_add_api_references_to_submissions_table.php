<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->string('source', 24)->default('web')->after('contest_id');
            $table->string('external_reference')->nullable()->after('source');
            $table->string('campaign_reference')->nullable()->after('external_reference');
            $table->index('external_reference');
            $table->index('campaign_reference');
            $table->unique(['source', 'external_reference']);
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropUnique(['source', 'external_reference']);
            $table->dropIndex(['external_reference']);
            $table->dropIndex(['campaign_reference']);
            $table->dropColumn(['source', 'external_reference', 'campaign_reference']);
        });
    }
};
