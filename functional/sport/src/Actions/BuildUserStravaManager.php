<?php

namespace Functional\Sport\Actions;

use Foutraz\Strava\StravaManager;
use Functional\Sport\Services\StravaTokenRefresher;
use Technical\Integrations\Models\IntegrationConnection;
use Technical\Integrations\Services\ConnectionTokenResolver;

class BuildUserStravaManager
{
    public function __construct(private StravaManager $manager) {}

    /**
     * Build a Strava manager authenticated with a connection's fresh access token.
     */
    public function __invoke(IntegrationConnection $connection): StravaManager
    {
        $resolver = new ConnectionTokenResolver(new StravaTokenRefresher($this->manager));

        return $this->manager->setToken($resolver->freshAccessToken($connection));
    }
}
