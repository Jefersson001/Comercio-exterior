@php($model = $attributes->wire('model')->value())
<div class="relation-field" x-data="relationSelect" x-id="['relation-list', 'relation-option']"
    @if($model) x-effect="syncValue($wire.get(@js($model)))" @endif
    @keydown.escape.stop.prevent="close(true)">
    <select x-ref="native" :class="{ 'is-enhanced': ready }" :tabindex="ready ? -1 : 0"
        @focus="if (ready) show()" @invalid="show()"
        {{ $attributes->class(['relation-native']) }}>{{ $slot }}</select>
    <button x-cloak x-show="ready" x-ref="trigger" type="button" class="relation-trigger" role="combobox"
        aria-haspopup="listbox" :aria-expanded="open" :aria-controls="$id('relation-list')" :aria-label="label"
        :aria-required="$refs.native.required" :aria-invalid="$refs.native.getAttribute('aria-invalid') || 'false'"
        :disabled="disabled" @click="open ? close() : show()"
        @keydown.arrow-down.prevent="show()" @keydown.arrow-up.prevent="show()">
        <span x-text="selectedLabel"></span>
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 8 4 4 4-4"/></svg>
    </button>
    <template x-teleport="body">
        <div x-cloak x-show="open" class="relation-popover" :style="position" @click.outside="close()"
            @keydown.escape.stop.prevent="close(true)" @focusout="if (! $el.contains($event.relatedTarget)) close()"
            style="display: none;">
            <input x-ref="search" x-model="query" type="search" class="relation-search" placeholder="Buscar una opción…"
                :aria-label="'Buscar: ' + label" role="combobox" aria-autocomplete="list" aria-expanded="true"
                :aria-controls="$id('relation-list')" :aria-activedescendant="filtered.length ? $id('relation-option') + '-' + highlighted : null"
                @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                @keydown.enter.prevent="choose(filtered[highlighted])" @keydown.tab="close(true)">
            <div x-ref="list" class="relation-options" role="listbox" :id="$id('relation-list')" :aria-label="label">
                <template x-for="(option, index) in filtered" :key="option.value">
                    <button type="button" role="option" tabindex="-1" class="relation-option"
                        :id="$id('relation-option') + '-' + index" :data-index="index"
                        :aria-selected="option.value === value" :data-highlighted="index === highlighted" :disabled="option.disabled"
                        @mousedown.prevent @mouseenter="highlighted = index" @click="choose(option)">
                        <span x-text="option.text"></span><span x-show="option.value === value" aria-hidden="true">✓</span>
                    </button>
                </template>
                <p x-show="! filtered.length" role="status" class="relation-empty">No se encontraron coincidencias.</p>
            </div>
            <p class="relation-help">↑ ↓ para recorrer · Enter para seleccionar · Esc para cerrar</p>
        </div>
    </template>
</div>
