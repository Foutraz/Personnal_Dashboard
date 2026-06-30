<?php

namespace Functional\Health\Actions;

use Foutraz\Withings\WithingsManager;
use Functional\Health\Services\WithingsTokenRefresher;
use Technical\Integrations\Models\IntegrationConnection;
use Technical\Integrations\Services\ConnectionTokenResolver;

class BuildUserWithingsManager
{
    public function __construct(private WithingsManager $manager) {}

    /**
     * Build a Withings manager authenticated with a connection's fresh access token.
     */
    public function __invoke(IntegrationConnection $connection): WithingsManager
    {
        $resolver = new ConnectionTokenResolver(new WithingsTokenRefresher($this->manager));

        return $this->manager->setToken($resolver->freshAccessToken($connection));
    }
}
