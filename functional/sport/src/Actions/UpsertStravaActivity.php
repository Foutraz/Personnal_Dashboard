<?php

namespace Functional\Sport\Actions;

use Foutraz\Strava\Dto\Activity;
use Functional\Sport\Enums\SportType;
use Functional\Sport\Models\SportActivity;
use Technical\Integrations\Models\IntegrationConnection;

class UpsertStravaActivity
{
    /**
     * Idempotently persist a Strava activity for the given connection.
     */
    public function __invoke(IntegrationConnection $connection, Activity $activity): SportActivity
    {
        return SportActivity::query()->updateOrCreate(
            [
                'integration_connection_id' => $connection->id,
                'strava_id' => $activity->id,
            ],
            [
                'user_id' => $connection->user_id,
                'name' => $activity->name,
                'sport_type' => SportType::fromStrava($activity->sportType),
                'distance' => $activity->distance,
                'moving_time' => $activity->movingTime,
                'elapsed_time' => $activity->elapsedTime,
                'total_elevation_gain' => $activity->totalElevationGain,
                'average_speed' => $activity->averageSpeed,
                'max_speed' => $activity->maxSpeed,
                'average_heartrate' => $activity->averageHeartrate,
                'max_heartrate' => $activity->maxHeartrate,
                'kilojoules' => $activity->kilojoules,
                'gear_id' => $activity->gearId,
                'map_polyline' => $activity->mapPolyline,
                'started_at' => $activity->startDate,
                'raw' => null,
            ]
        );
    }
}
