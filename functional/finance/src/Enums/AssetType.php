<?php

namespace Functional\Finance\Enums;

enum AssetType: string
{
    case Stock = 'stock';
    case Etf = 'etf';
    case Crypto = 'crypto';
    case Other = 'other';

    /**
     * Get the human-readable label of the asset type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Stock => 'Action',
            self::Etf => 'ETF',
            self::Crypto => 'Crypto',
            self::Other => 'Autre',
        };
    }

    /**
     * Get the neon accent color associated with the asset type.
     */
    public function color(): string
    {
        return match ($this) {
            self::Stock => 'cyan',
            self::Etf => 'violet',
            self::Crypto => 'lime',
            self::Other => 'cyan',
        };
    }
}
