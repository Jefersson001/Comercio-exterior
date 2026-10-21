@props(['title', 'description', 'rows', 'total', 'eyebrow' => 'Distribución', 'tone' => 'blue', 'id'])
<article class="dashboard-panel" data-dashboard-section="{{ $id }}" data-tone="{{ $tone }}">
    <div class="dashboard-panel-heading">
        <div><p class="dashboard-eyebrow">{{ $eyebrow }}</p><h3>{{ $title }}</h3><p class="dashboard-muted mt-1 text-xs">{{ $description }}</p></div>
        <span class="dashboard-total">{{ number_format($total, 0, ',', '.') }}</span>
    </div>
    <div class="dashboard-breakdown">
        @foreach($rows as $row)
            <div class="dashboard-breakdown-row" data-label="{{ $row['label'] }}" data-count="{{ $row['count'] }}">
                <div class="flex items-center justify-between gap-4">
                    @if(isset($row['href']))<a class="dashboard-row-label" href="{{ $row['href'] }}">{{ $row['label'] }}<span class="sr-only">: {{ $row['count'] }} registros</span></a>
                    @else<span class="dashboard-row-label">{{ $row['label'] }}</span>@endif
                    <span class="flex shrink-0 items-baseline gap-3"><span class="dashboard-percentage">{{ $total > 0 ? str_replace('.', ',', (string) round($row['count'] / $total * 100, 1)) : 0 }}%</span><strong>{{ number_format($row['count'], 0, ',', '.') }}</strong></span>
                </div>
                <div class="dashboard-bar" aria-hidden="true"><span style="width: {{ $total > 0 ? min(100, round($row['count'] / $total * 100, 2)) : 0 }}%"></span></div>
            </div>
        @endforeach
    </div>
    @if($total === 0)<p class="dashboard-empty">Todavía no hay registros. Las cantidades se actualizarán cuando agregues información.</p>@endif
</article>
