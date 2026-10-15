<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('supplier_quotations', function (Blueprint $table): void {
            $table->uuid('submission_uuid')->nullable()->unique();
            $table->string('sync_status', 24)->default('pending')->index();
            $table->unsignedInteger('sync_attempts')->default(0);
            $table->timestamp('sync_next_attempt_at')->nullable();
            $table->timestamp('sync_locked_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->string('sync_error', 255)->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('supplier_quotations', function (Blueprint $table): void {
            $table->dropUnique(['submission_uuid']);
            $table->dropIndex(['sync_status']);
            $table->dropColumn(['submission_uuid','sync_status','sync_attempts','sync_next_attempt_at','sync_locked_at','synced_at','sync_error']);
        });
    }
};
