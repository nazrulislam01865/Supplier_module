<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('supplier_companies', function (Blueprint $table): void {
            $table->string('flowtrack_brand_favicon_url', 2048)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_companies', function (Blueprint $table): void {
            $table->dropColumn('flowtrack_brand_favicon_url');
        });
    }
};
