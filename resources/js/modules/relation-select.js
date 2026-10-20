const register = (Alpine) => Alpine.data('relationSelect', () => ({
    open: false,
    ready: false,
    query: '',
    options: [],
    value: '',
    highlighted: 0,
    disabled: false,
    label: '',
    position: {},
    observer: null,
    reposition: null,
    init() {
        this.refresh();
        this.ready = true;
        this.observer = new MutationObserver(() => this.refresh());
        this.observer.observe(this.$refs.native, { childList: true, subtree: true, attributes: true });
        this.reposition = () => { if (this.open) this.place(); };
        window.addEventListener('resize', this.reposition);
        window.addEventListener('scroll', this.reposition, true);
        this.$watch('query', () => { this.highlighted = 0; });
    },
    destroy() {
        this.observer?.disconnect();
        window.removeEventListener('resize', this.reposition);
        window.removeEventListener('scroll', this.reposition, true);
    },
    get filtered() {
        const normalize = (text) => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase();
        const query = normalize(this.query.trim());
        return this.options.filter((option) => normalize(option.text).includes(query));
    },
    get selectedLabel() {
        return this.options.find((option) => option.value === this.value)?.text || 'Selecciona una opción';
    },
    refresh() {
        const select = this.$refs.native;
        if (!select) return;
        this.options = Array.from(select.options).map((option) => ({ value: option.value, text: option.textContent.trim(), disabled: option.disabled }));
        this.value = select.value;
        this.disabled = select.matches(':disabled');
        this.label = select.getAttribute('aria-label') || select.labels?.[0]?.textContent.trim() || 'Seleccionar opción';
    },
    syncValue(value) {
        this.$nextTick(() => {
            const native = this.$refs.native;
            // Livewire owns the native control. Re-read its options after each morph.
            if (value !== undefined) native.value = value == null ? '' : String(value);
            this.refresh();
        });
    },
    place() {
        const rect = this.$refs.trigger.getBoundingClientRect();
        const width = Math.min(Math.max(rect.width, 260), window.innerWidth - 24);
        const availableBelow = window.innerHeight - rect.bottom - 12;
        const above = availableBelow < 230 && rect.top > availableBelow;
        const height = Math.min(330, Math.max(150, above ? rect.top - 18 : availableBelow));
        this.position = {
            left: `${Math.max(12, Math.min(rect.left, window.innerWidth - width - 12))}px`,
            width: `${width}px`,
            maxHeight: `${height}px`,
            ...(above ? { bottom: `${window.innerHeight - rect.top + 6}px`, top: 'auto' } : { top: `${rect.bottom + 6}px`, bottom: 'auto' }),
        };
    },
    show() {
        this.refresh();
        if (this.disabled) return;
        this.query = '';
        this.open = true;
        this.highlighted = Math.max(0, this.options.findIndex((option) => option.value === this.value));
        this.place();
        this.$nextTick(() => { this.$refs.search.focus(); this.scrollHighlighted(); });
    },
    close(restoreFocus = false) {
        this.open = false;
        if (restoreFocus) this.$refs.trigger.focus();
    },
    move(direction) {
        const count = this.filtered.length;
        if (!count) return;
        this.highlighted = (this.highlighted + direction + count) % count;
        this.$nextTick(() => this.scrollHighlighted());
    },
    scrollHighlighted() {
        this.$refs.list?.querySelector(`[data-index="${this.highlighted}"]`)?.scrollIntoView({ block: 'nearest' });
    },
    choose(option) {
        if (!option || option.disabled || this.$refs.native.matches(':disabled')) return;
        this.$refs.native.value = option.value;
        this.value = option.value;
        this.$refs.native.dispatchEvent(new Event('change', { bubbles: true }));
        this.close(true);
    },
}));

if (window.Alpine) register(window.Alpine);
else document.addEventListener('alpine:init', () => register(window.Alpine), { once: true });
