<?php

namespace Functional\Planning\Actions;

use Foutraz\Outlook\OutlookManager;
use Functional\Planning\Services\OutlookTokenRefresher;
use Technical\Integrations\Models\IntegrationConnection;
use Technical\Integrations\Services\ConnectionTokenResolver;

class BuildUserOutlookManager
{
    public function __construct(private OutlookManager $manager) {}

    /**
     * Build an Outlook manager authenticated with a connection's fresh access token.
     */
    public function __invoke(IntegrationConnection $connection): OutlookManager
    {
        $resolver = new ConnectionTokenResolver(new OutlookTokenRefresher($this->manager));

        return $this->manager->setToken($resolver->freshAccessToken($connection));
    }
}
