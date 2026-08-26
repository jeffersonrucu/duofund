<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->string('plan_mode')->nullable()->after('current');   // monthly | date | null
            $table->decimal('monthly_target', 15, 2)->nullable()->after('plan_mode');
            $table->date('target_date')->nullable()->after('monthly_target');
        });
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->dropColumn(['plan_mode', 'monthly_target', 'target_date']);
        });
    }
};
