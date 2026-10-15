<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('supplier_companies', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 80)->unique(); // independent, opaque external reference
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->json('profile')->nullable();
            $table->text('encrypted_banking')->nullable();
            $table->timestamps();
        });
        Schema::create('supplier_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_company_id')->constrained('supplier_companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role', 24)->default('admin');
            $table->boolean('active')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->index(['supplier_company_id', 'active']);
        });
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary(); $table->mediumText('value'); $table->integer('expiration');
        });
        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary(); $table->string('owner'); $table->integer('expiration');
        });
        Schema::create('supplier_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_company_id')->constrained('supplier_companies')->cascadeOnDelete();
            $table->string('type', 32); // rfq, order, document_request, bill, notification
            $table->string('external_reference', 120);
            $table->string('status', 32)->default('open');
            $table->json('payload'); // supplier-approved projection only, never raw FlowTracker models
            $table->timestamp('due_at')->nullable();
            $table->timestamps();
            $table->unique(['supplier_company_id','type','external_reference'], 'supplier_resource_unique');
            $table->index(['supplier_company_id','type','status','due_at'], 'supplier_resource_scope_idx');
        });
        Schema::create('supplier_quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_company_id')->constrained('supplier_companies')->cascadeOnDelete();
            $table->foreignId('supplier_resource_id')->constrained('supplier_resources')->cascadeOnDelete();
            $table->foreignId('supplier_user_id')->constrained('supplier_users')->cascadeOnDelete();
            $table->string('status', 24)->default('draft');
            $table->json('data')->nullable();
            $table->bigInteger('total_minor_units')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['supplier_company_id', 'supplier_resource_id'], 'supplier_quotation_unique');
        });
        Schema::create('supplier_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_company_id')->constrained('supplier_companies')->cascadeOnDelete();
            $table->foreignId('supplier_resource_id')->nullable()->constrained('supplier_resources')->nullOnDelete();
            $table->foreignId('supplier_quotation_id')->nullable()->constrained('supplier_quotations')->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('supplier_users');
            $table->string('document_type', 64);
            $table->string('original_name');
            $table->string('storage_path');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size');
            $table->string('scan_status', 24)->default('pending');
            $table->date('expires_at')->nullable();
            $table->timestamps();
            $table->index(['supplier_company_id', 'supplier_resource_id'], 'supplier_docs_company_resource_idx');
        });
        Schema::create('supplier_support_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_company_id')->constrained('supplier_companies')->cascadeOnDelete();
            $table->foreignId('supplier_user_id')->constrained('supplier_users');
            $table->string('category', 50);
            $table->string('reference', 120)->nullable();
            $table->text('message');
            $table->string('status', 24)->default('open');
            $table->timestamps();
            $table->index(['supplier_company_id','created_at']);
        });
    }
    public function down(): void
    {
        foreach (['supplier_support_requests','supplier_documents','supplier_quotations','supplier_resources','cache_locks','cache','sessions','password_reset_tokens','supplier_users','supplier_companies'] as $name) Schema::dropIfExists($name);
    }
};
