<?php

namespace Technical\Osdd\Contracts;

use Technical\Osdd\Dto\NavigationItem;

interface ProvidesNavigationItem
{
    /**
     * Build the sidebar navigation entry for this module.
     */
    public function navigationItem(): NavigationItem;
}
