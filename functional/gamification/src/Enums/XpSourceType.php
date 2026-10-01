<?php

namespace Functional\Gamification\Enums;

enum XpSourceType: string
{
    case Period = 'period';
    case StreakMilestone = 'streak_milestone';
    case Badge = 'badge';
    case Challenge = 'challenge';
}
