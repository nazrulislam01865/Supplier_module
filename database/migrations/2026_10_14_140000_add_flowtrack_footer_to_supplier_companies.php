<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('supplier_companies', function (Blueprint $table): void {
            $table->json('flowtrack_portal_footer')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('supplier_companies', function (Blueprint $table): void {
            $table->dropColumn('flowtrack_portal_footer');
        });
    }
};
