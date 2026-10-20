<nav class="workspace-pager" aria-label="Paginación de resultados">
    <p>
        <strong>{{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }}</strong>
        de <strong>{{ $paginator->total() }}</strong> registros
    </p>
    @if($paginator->hasPages())
        <div class="pager-controls">
            <button type="button" class="pager-button" wire:click="previousPage('{{ $paginator->getPageName() }}')"
                wire:loading.attr="disabled" @disabled($paginator->onFirstPage()) aria-label="Página anterior">←</button>
            <span class="sm:hidden">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
            <div class="pager-controls pager-pages">
                @foreach($elements as $element)
                    @if(is_string($element))
                        <span class="px-1" aria-hidden="true">…</span>
                    @else
                        @foreach($element as $page => $url)
                            <button type="button" class="pager-button" wire:key="pager-{{ $paginator->getPageName() }}-{{ $page }}"
                                wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" wire:loading.attr="disabled"
                                @if($page === $paginator->currentPage()) aria-current="page" @endif aria-label="Página {{ $page }}">{{ $page }}</button>
                        @endforeach
                    @endif
                @endforeach
            </div>
            <button type="button" class="pager-button" wire:click="nextPage('{{ $paginator->getPageName() }}')"
                wire:loading.attr="disabled" @disabled(! $paginator->hasMorePages()) aria-label="Página siguiente">→</button>
        </div>
    @endif
</nav>
