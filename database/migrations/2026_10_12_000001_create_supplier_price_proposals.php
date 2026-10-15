<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('supplier_companies', function (Blueprint $table): void {
            $table->unsignedBigInteger('catalog_revision')->default(0);
        });
        Schema::create('supplier_price_proposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_company_id')->constrained('supplier_companies')->cascadeOnDelete();
            $table->foreignId('supplier_resource_id')->nullable()->constrained('supplier_resources')->nullOnDelete();
            $table->foreignId('supplier_user_id')->constrained('supplier_users');
            $table->uuid('request_uuid')->unique();
            $table->string('base_version', 64);
            $table->json('proposed_prices');
            $table->string('status', 24)->default('pending');
            $table->string('delivery_status', 24)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();
            $table->index(['delivery_status', 'next_attempt_at']);
            $table->index(['supplier_company_id', 'status']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('supplier_price_proposals');
        Schema::table('supplier_companies', function (Blueprint $table): void { $table->dropColumn('catalog_revision'); });
    }
};
