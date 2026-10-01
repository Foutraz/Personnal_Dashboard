<?php

return [
    'templates' => [
        'sport_distance' => [
            'name' => 'Sport distance',
            'description' => 'Cover :target this week.',
        ],
        'sport_elevation' => [
            'name' => 'Sport elevation',
            'description' => 'Climb :target this week.',
        ],
        'sport_activity_count' => [
            'name' => 'Sport activities',
            'description' => '{1} Log :target workout this week.|[2,*] Log :target workouts this week.',
        ],
        'sport_moving_time' => [
            'name' => 'Moving time',
            'description' => 'Stay on the move for :target this week.',
        ],
        'moto_distance' => [
            'name' => 'Motorbike distance',
            'description' => 'Ride :target on your motorbike this week.',
        ],
        'moto_ride_count' => [
            'name' => 'Motorbike rides',
            'description' => '{1} Go on :target motorbike ride this week.|[2,*] Go on :target motorbike rides this week.',
        ],
        'exploration_cells' => [
            'name' => 'Exploration',
            'description' => '{1} Explore :target new cell this week.|[2,*] Explore :target new cells this week.',
        ],
    ],
    'units' => [
        'km' => ':measure km',
        'm' => ':measure m',
        'h' => ':measure h',
        'count' => ':measure',
    ],
    'statuses' => [
        'proposed' => 'Proposed',
        'accepted' => 'In progress',
        'completed' => 'Completed',
        'failed' => 'Missed',
        'declined' => 'Skipped',
        'expired' => 'Expired',
    ],
    'board' => [
        'title' => 'Challenges of the week',
        'subtitle' => 'Week of :start to :end',
        'week_date_format' => 'M j',
        'baseline' => 'Your typical week: :baseline',
        'reward' => '+:xp XP',
        'progress' => ':current / :target',
        'progress_label' => 'Progress of the :name challenge',
        'updated' => 'Progress updated :time',
        'accept' => 'Take on the challenge',
        'accept_label' => 'Take on the challenge: :name',
        'decline' => 'Skip',
        'decline_label' => 'Skip the challenge: :name',
        'decline_confirm' => 'Skip this challenge? You will not be able to take it on this week.',
        'already_reached' => 'Target already reached: take on the challenge to validate it',
        'previous_title' => 'Last week',
        'grace_pending' => 'Closing is waiting for the latest syncs',
        'empty' => 'No challenges this week. Challenges are proposed every Monday from your last :weeks weeks of activity.',
        'accepted_announcement' => 'Challenge accepted: :name',
        'declined_announcement' => 'Challenge skipped: :name',
    ],
    'notification' => [
        'proposed_title' => '{1} :count new challenge this week|[2,*] :count new challenges this week',
        'proposed_body' => 'Discover your challenges of the week and take on the ones that motivate you.',
        'completed_title' => 'Challenge completed: :name',
        'completed_body' => 'You reached :target and earned :xp XP.',
    ],
    'errors' => [
        'not_respondable' => 'This challenge can no longer be accepted or skipped.',
    ],
];
