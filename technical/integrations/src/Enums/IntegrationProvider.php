<?php

namespace Technical\Integrations\Enums;

enum IntegrationProvider: string
{
    case Strava = 'strava';
    case LibertyRider = 'liberty_rider';
    case OutlookCalendar = 'outlook_calendar';
    case GoogleCalendar = 'google_calendar';
    case Withings = 'withings';

    /**
     * Get the human-readable label of the provider.
     */
    public function label(): string
    {
        return match ($this) {
            self::Strava => 'Strava',
            self::LibertyRider => 'Liberty Rider',
            self::OutlookCalendar => 'Outlook Calendar',
            self::GoogleCalendar => 'Google Calendar',
            self::Withings => 'Withings',
        };
    }
}
