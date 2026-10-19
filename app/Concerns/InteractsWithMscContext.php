<?php

namespace App\Concerns;

use Livewire\Attributes\Locked;

trait InteractsWithMscContext
{
    #[Locked]
    public bool $foreignTrade = false;

    protected function initializeMscContext(bool $foreignTrade): void
    {
        $this->foreignTrade = $foreignTrade;
    }

    /** @param array<string, mixed> $parameters */
    public function mscRoute(string $action, array $parameters = []): string
    {
        return route(($this->foreignTrade ? 'foreign-trade.msc.' : 'msc.').$action, $parameters);
    }
}
