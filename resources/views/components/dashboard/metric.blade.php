@props(['label', 'value', 'hint', 'icon' => 'ship', 'href', 'tone' => 'blue'])
<a href="{{ $href }}" class="dashboard-metric" data-tone="{{ $tone }}">
    <div class="flex items-start justify-between gap-3"><span class="dashboard-icon"><x-dashboard.icon :name="$icon" /></span><span class="dashboard-metric-arrow" aria-hidden="true">↗</span></div>
    <p class="dashboard-metric-label">{{ $label }}</p>
    <strong class="dashboard-metric-value">{{ number_format($value, 0, ',', '.') }}</strong>
    <p class="dashboard-muted mt-2 text-xs">{{ $hint }}</p>
</a>
