<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('supplier_order_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_company_id')->constrained('supplier_companies')->cascadeOnDelete();
            $table->foreignId('supplier_resource_id')->constrained('supplier_resources')->cascadeOnDelete();
            $table->foreignId('supplier_user_id')->nullable()->constrained('supplier_users')->nullOnDelete();
            $table->uuid('event_id')->unique();
            $table->unsignedBigInteger('task_id');
            $table->string('stage_name', 100);
            $table->string('task_title', 255);
            $table->string('actor_name', 255);
            $table->string('decision', 30);
            $table->string('task_status', 100);
            $table->timestamp('created_at');
            $table->index(['supplier_company_id', 'created_at', 'id'], 'supplier_order_activities_feed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_order_activities');
    }
};
