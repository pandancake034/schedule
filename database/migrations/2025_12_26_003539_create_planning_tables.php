<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel Beschikbaarheid
        Schema::create('availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('day_of_week'); 
            // Hier voegen we 'DAY' toe voor mensen die vast op do/vr werken
            $table->enum('shift_preference', ['AM', 'PM', 'BOTH', 'DAY']);
            $table->timestamps();
        });

        // Tabel Rooster
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            // Hier voegen we 'DAY' toe zodat de database dit accepteert
            $table->enum('shift_type', ['AM', 'PM', 'DAY']);
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unique(['date', 'shift_type', 'user_id']); 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('availabilities');
    }
};