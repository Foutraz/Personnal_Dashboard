<?php

return [
    'templates' => [
        'sport_distance' => [
            'name' => 'Distance sportive',
            'description' => 'Parcourez :target cette semaine.',
        ],
        'sport_elevation' => [
            'name' => 'Dénivelé sportif',
            'description' => 'Grimpez :target de dénivelé cette semaine.',
        ],
        'sport_activity_count' => [
            'name' => 'Activités sportives',
            'description' => '{1} Enregistrez :target activité sportive cette semaine.|[2,*] Enregistrez :target activités sportives cette semaine.',
        ],
        'sport_moving_time' => [
            'name' => 'Temps en mouvement',
            'description' => 'Bougez pendant :target cette semaine.',
        ],
        'moto_distance' => [
            'name' => 'Distance moto',
            'description' => 'Parcourez :target à moto cette semaine.',
        ],
        'moto_ride_count' => [
            'name' => 'Sorties moto',
            'description' => '{1} Effectuez :target sortie à moto cette semaine.|[2,*] Effectuez :target sorties à moto cette semaine.',
        ],
        'exploration_cells' => [
            'name' => 'Exploration',
            'description' => '{1} Explorez :target nouvelle cellule cette semaine.|[2,*] Explorez :target nouvelles cellules cette semaine.',
        ],
    ],
    'units' => [
        'km' => ':measure km',
        'm' => ':measure m',
        'h' => ':measure h',
        'count' => ':measure',
    ],
    'statuses' => [
        'proposed' => 'Proposé',
        'accepted' => 'En cours',
        'completed' => 'Réussi',
        'failed' => 'Manqué',
        'declined' => 'Passé',
        'expired' => 'Expiré',
    ],
    'board' => [
        'title' => 'Défis de la semaine',
        'subtitle' => 'Semaine du :start au :end',
        'week_date_format' => 'j M',
        'baseline' => 'Votre semaine type : :baseline',
        'reward' => '+:xp XP',
        'progress' => ':current / :target',
        'progress_label' => 'Progression du défi :name',
        'updated' => 'Progression mise à jour :time',
        'accept' => 'Relever le défi',
        'accept_label' => 'Relever le défi :name',
        'decline' => 'Passer',
        'decline_label' => 'Passer le défi :name',
        'decline_confirm' => 'Passer ce défi ? Vous ne pourrez plus le relever cette semaine.',
        'already_reached' => 'Objectif déjà atteint : relevez le défi pour le valider',
        'previous_title' => 'Semaine dernière',
        'grace_pending' => 'Clôture en attente des dernières synchronisations',
        'empty' => "Aucun défi cette semaine. Les défis sont proposés chaque lundi à partir de vos 4 dernières semaines d'activité.",
        'accepted_announcement' => 'Défi accepté : :name',
        'declined_announcement' => 'Défi passé : :name',
    ],
    'notification' => [
        'proposed_title' => '{1} :count nouveau défi cette semaine|[2,*] :count nouveaux défis cette semaine',
        'proposed_body' => 'Découvrez vos défis de la semaine et relevez ceux qui vous motivent.',
        'completed_title' => 'Défi réussi : :name',
        'completed_body' => 'Vous avez atteint :target et gagné :xp XP.',
    ],
    'errors' => [
        'not_respondable' => 'Ce défi ne peut plus être accepté ni refusé.',
    ],
];
