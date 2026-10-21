@props(['name' => 'ship'])
<svg {{ $attributes->class(['size-5']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
    @switch($name)
        @case('factory')
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 21V10l6 3V8l6 3V3h4v18H3Zm4-4h1m4 0h1m4 0h1"/>
            @break
        @case('products')
            <path stroke-linejoin="round" d="m12 3 9 5v8l-9 5-9-5V8l9-5ZM3 8l9 5 9-5m-9 5v8M7.5 5.5l9 5"/>
            @break
        @case('users')
            <circle cx="9" cy="8" r="3"/><path stroke-linecap="round" d="M3 21v-3a6 6 0 0 1 12 0v3m2-16a3 3 0 0 1 0 6m4 10v-3a6 6 0 0 0-3-5"/>
            @break
        @case('categories')
            <rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>
            @break
        @default
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 17 2 10l10-4 10 4-2 7M8 7V3h8v4M12 7v10M2 21c2 0 3-2 5-2s3 2 5 2 3-2 5-2 3 2 5 2"/>
    @endswitch
</svg>
