<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('supplier_companies', function (Blueprint $table): void {
            $table->string('flowtrack_short_code', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_companies', fn (Blueprint $table) => $table->dropColumn('flowtrack_short_code'));
    }
};
