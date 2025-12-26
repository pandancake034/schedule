<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Standaard 5 dagen, 40 uur als er niets wordt ingevuld
            $table->integer('contract_days')->default(5)->after('email');
            $table->integer('contract_hours')->default(40)->after('contract_days');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['contract_days', 'contract_hours']);
        });
    }
};