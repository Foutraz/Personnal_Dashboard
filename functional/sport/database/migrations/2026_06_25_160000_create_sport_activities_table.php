<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration creating the sport activities table.
     */
    public function up(): void
    {
        Schema::create('sport_activities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('integration_connection_id')->constrained('integration_connections');
            $table->foreignUlid('user_id')->constrained('users');
            $table->unsignedBigInteger('strava_id');
            $table->string('name');
            $table->string('sport_type');
            $table->float('distance');
            $table->unsignedInteger('moving_time');
            $table->unsignedInteger('elapsed_time');
            $table->float('total_elevation_gain')->default(0);
            $table->float('average_speed')->nullable();
            $table->float('max_speed')->nullable();
            $table->float('average_heartrate')->nullable();
            $table->float('max_heartrate')->nullable();
            $table->float('kilojoules')->nullable();
            $table->string('gear_id')->nullable();
            $table->text('map_polyline')->nullable();
            $table->timestamp('started_at');
            $table->json('raw')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['integration_connection_id', 'strava_id']);
            $table->index(['user_id', 'started_at']);
        });
    }

    /**
     * Reverse the migration dropping the sport activities table.
     */
    public function down(): void
    {
        Schema::dropIfExists('sport_activities');
    }
};
