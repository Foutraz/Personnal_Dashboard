<?php

namespace Functional\Gamification\Exceptions;

use Functional\Gamification\Enums\ChallengeTemplateKey;
use RuntimeException;

class MissingChallengeTemplateConfigException extends RuntimeException
{
    public function __construct(public readonly ChallengeTemplateKey $template)
    {
        parent::__construct("No settings are configured for the challenge template \"{$template->value}\" at \"{$template->configPath()}\".");
    }
}
