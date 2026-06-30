<?php

namespace Functional\Finance\Actions;

use Foutraz\GoCardlessBank\GoCardlessManager;
use Functional\Finance\Services\GoCardlessTokenRefresher;
use Technical\Integrations\Models\IntegrationConnection;
use Technical\Integrations\Services\ConnectionTokenResolver;

class BuildUserGoCardlessManager
{
    public function __construct(private GoCardlessManager $manager) {}

    /**
     * Build a GoCardless manager authenticated with a connection's fresh access token.
     */
    public function __invoke(IntegrationConnection $connection): GoCardlessManager
    {
        $resolver = new ConnectionTokenResolver(new GoCardlessTokenRefresher($this->manager));

        return $this->manager->setToken($resolver->freshAccessToken($connection));
    }
}
