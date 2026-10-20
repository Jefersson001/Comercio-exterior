<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

trait InteractsWithMscContext
{
    #[Locked]
    public bool $foreignTrade = false;

    protected function initializeMscContext(bool $foreignTrade): void
    {
        $this->foreignTrade = $foreignTrade;
        $this->authorizeMscContext();
    }

    protected function authorizeMscContext(): void
    {
        Gate::authorize($this->foreignTrade ? 'access-foreign-trade' : 'access-international-trade');
    }

    public function hydrateInteractsWithMscContext(): void
    {
        $this->authorizeMscContext();
    }

    /** @param array<string, mixed> $parameters */
    public function mscRoute(string $action, array $parameters = []): string
    {
        return route(($this->foreignTrade ? 'foreign-trade.msc.' : 'msc.').$action, $parameters);
    }
}
