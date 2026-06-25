<?php

namespace Functional\Finance\Enums;

enum TransactionType: string
{
    case Buy = 'buy';
    case Sell = 'sell';

    /**
     * Get the human-readable label of the transaction type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Buy => 'Achat',
            self::Sell => 'Vente',
        };
    }

    /**
     * Get the neon accent color associated with the transaction type.
     */
    public function color(): string
    {
        return match ($this) {
            self::Buy => 'lime',
            self::Sell => 'violet',
        };
    }
}
