<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('supplier_companies', function (Blueprint $table) {
            $table->unsignedBigInteger('dashboard_revision')->default(0);
        });
        Schema::table('supplier_users', function (Blueprint $table) {
            $table->timestamp('notifications_read_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_users', fn (Blueprint $table) => $table->dropColumn('notifications_read_at'));
        Schema::table('supplier_companies', fn (Blueprint $table) => $table->dropColumn('dashboard_revision'));
    }
};
