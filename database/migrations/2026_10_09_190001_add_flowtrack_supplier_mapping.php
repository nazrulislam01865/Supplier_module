<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('supplier_companies', function (Blueprint $table): void {
            // External source ID only; no foreign database connection or cross-DB FK.
            $table->unsignedBigInteger('flowtrack_supplier_id')->nullable()->unique('supplier_flowtrack_mapping_unique');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_companies', fn (Blueprint $table) => $table->dropUnique('supplier_flowtrack_mapping_unique'));
        Schema::table('supplier_companies', fn (Blueprint $table) => $table->dropColumn('flowtrack_supplier_id'));
    }
};
