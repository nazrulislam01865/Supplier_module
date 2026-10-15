<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('supplier_users', function (Blueprint $table): void {
            $table->string('username', 60)->nullable()->unique('supplier_username_unique');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_users', fn (Blueprint $table) => $table->dropUnique('supplier_username_unique'));
        Schema::table('supplier_users', fn (Blueprint $table) => $table->dropColumn('username'));
    }
};
