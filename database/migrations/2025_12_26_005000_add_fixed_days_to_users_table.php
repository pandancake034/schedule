<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // We slaan de dagen op als een lijstje (JSON), bijv: ["Thursday", "Friday"]
            $table->json('fixed_days')->nullable()->after('contract_hours');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('fixed_days');
        });
    }
};