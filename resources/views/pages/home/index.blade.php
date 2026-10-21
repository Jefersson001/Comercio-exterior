<section class="dashboard-home mx-auto max-w-6xl" @if($hasOverview) wire:poll.60s.keep-alive @endif>
    @if($hasOverview)
        @include('pages.home.overview')
    @else
        @include('pages.home.welcome-panel')
    @endif
</section>
