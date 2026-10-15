<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('supplier_companies', function (Blueprint $table): void {
            $table->string('flowtrack_brand_name', 255)->nullable();
            $table->string('flowtrack_brand_logo_url', 2048)->nullable();
        });
    }
    public function down(): void {
        Schema::table('supplier_companies', fn (Blueprint $table) => $table->dropColumn(['flowtrack_brand_name', 'flowtrack_brand_logo_url']));
    }
};
