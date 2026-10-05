<?php

namespace Functional\Gamification\Exceptions;

use Functional\Gamification\Enums\XpRuleKey;
use InvalidArgumentException;

class NonBonusXpRuleKeyException extends InvalidArgumentException
{
    private function __construct(public readonly XpRuleKey $ruleKey)
    {
        parent::__construct("The XP rule key \"{$ruleKey->value}\" is not a bonus key and cannot be reconverged.");
    }

    public static function for(XpRuleKey $ruleKey): self
    {
        return new self($ruleKey);
    }
}
