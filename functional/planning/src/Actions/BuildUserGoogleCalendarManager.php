<?php

namespace Functional\Planning\Actions;

use Foutraz\GoogleCalendar\GoogleCalendarManager;
use Functional\Planning\Services\GoogleCalendarTokenRefresher;
use Technical\Integrations\Models\IntegrationConnection;
use Technical\Integrations\Services\ConnectionTokenResolver;

class BuildUserGoogleCalendarManager
{
    public function __construct(private GoogleCalendarManager $manager) {}

    /**
     * Build a Google Calendar manager authenticated with a connection's fresh access token.
     */
    public function __invoke(IntegrationConnection $connection): GoogleCalendarManager
    {
        $resolver = new ConnectionTokenResolver(new GoogleCalendarTokenRefresher($this->manager));

        return $this->manager->setToken($resolver->freshAccessToken($connection));
    }
}
