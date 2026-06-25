<?php

namespace Functional\Finance\Exceptions;

use RuntimeException;

class SellExceedsHoldingsException extends RuntimeException
{
    /**
     * Build the exception describing a sell larger than the held quantity.
     */
    public static function forPosition(string $assetSymbol): self
    {
        return new self("The sold quantity for {$assetSymbol} exceeds the held quantity.");
    }
}
