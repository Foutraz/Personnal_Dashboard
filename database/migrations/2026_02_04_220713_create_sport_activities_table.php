<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sport_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->nullable()->constrained('users');
            $table->string('provider')->nullable();
            $table->string('provider_id')->nullable();
            $table->string('external_url')->nullable();
            $table->string('type');
            $table->string('libelle')->nullable();
            $table->text('description')->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->string('timezone')->default('Europe/Paris');
            $table->integer('duration')->comment('Total duration in seconds');
            $table->integer('moving_time')->nullable()->comment('Mouvement time in seconds');
            $table->float('distance')->comment('Distance in km');
            $table->float('average_speed')->nullable()->comment('Average speed in km/h');
            $table->float('max_speed')->nullable()->comment('Max speed in km/h');
            $table->integer('elevation_gain')->nullable();
            $table->integer('elevation_loss')->nullable();
            $table->integer('max_altitude')->nullable();
            $table->integer('min_altitude')->nullable();
            $table->decimal('start_latitude', 10, 7)->nullable();
            $table->decimal('start_longitude', 10, 7)->nullable();
            $table->decimal('end_latitude', 10, 7)->nullable();
            $table->decimal('end_longitude', 10, 7)->nullable();
            $table->string('weather_condition')->nullable();
            $table->float('temperature')->nullable()->comment('Temperature in °C');
            $table->float('average_heart_rate')->nullable();
            $table->float('max_heart_rate')->nullable();
            $table->float('min_heart_rate')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('last_updated_from_provider_at')->nullable();
            $table->string('sync_status')->default('pending');
            $table->timestamps();
            $table->index(['owner_id', 'start_time']);
            $table->unique(['provider', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sport_activities');
    }
};
