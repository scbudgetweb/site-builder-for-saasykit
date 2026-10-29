{{--
    The design fields' Alpine code (DeviceValue, BoxSides and SiteColour), added to the admin panel once by SiteBuilderPlugin, before
    Alpine starts. Livewire doesn't run scripts that arrive in a modal, so the fields can't carry their own.

    $store.sbDevice.current is the screen size being edited, or "hover" (the value while the block is pointed at), shared
    by every field (and, in the visual builder, by the canvas, which ignores hover). A value is saved as
    {desktop, tablet, phone, hover}; an empty one shows the value it falls back to: tablet falls back to desktop, phone
    to tablet then desktop, hover to desktop. A field that's the same on every screen still has a hover value.
--}}
<script>
    document.addEventListener('alpine:init', () => {
        if (window.Alpine.store('sbDevice')) {
            return;
        }

        window.Alpine.store('sbDevice', { current: 'desktop' });

        const lengthPattern = /^(-?\d*\.?\d+)([a-z%]*)$/i;
        // Hover (while the block is pointed at) falls back to the desktop value, as screen sizes do.
        const fallbackOrder = { desktop: [], tablet: ['desktop'], phone: ['tablet', 'desktop'], hover: ['desktop'] };
        const stateFor = (current, responsive) => (current === 'hover' ? 'hover' : (responsive ? current : 'desktop'));

        const split = (value, units) => {
            const match = String(value ?? '').match(lengthPattern);

            return match ? { number: match[1], unit: units.includes(match[2]) ? match[2] : units[0] } : { number: '', unit: null };
        };

        window.Alpine.data('sbDeviceValue', ({ state, units, responsive }) => ({
            state,
            units,
            responsive,
            number: '',
            unit: units[0],

            init() {
                this.read();
                this.$watch('$store.sbDevice.current', () => this.read());
                this.$watch('state', () => this.read());
            },

            device() {
                return stateFor(this.$store.sbDevice.current, this.responsive);
            },

            values() {
                return this.state && typeof this.state === 'object' ? this.state : {};
            },

            has(device) {
                return (this.values()[device] ?? '') !== '';
            },

            read() {
                const parts = split(this.values()[this.device()], this.units);

                this.number = parts.number;
                this.unit = parts.unit ?? this.unit;
            },

            write() {
                const values = { ...this.values() };

                if (this.number === '' || this.number === null) {
                    delete values[this.device()];
                } else {
                    values[this.device()] = `${this.number}${this.unit}`;
                }

                this.state = Object.keys(values).length > 0 ? values : null;
            },

            fallback() {
                const from = fallbackOrder[this.device()].find((device) => this.has(device));

                return from ? this.values()[from] : '';
            },
        }));

        window.Alpine.data('sbBoxSides', ({ state, units, parts, responsive }) => ({
            state,
            units,
            parts,
            responsive,
            numbers: {},
            unit: units[0],
            linked: false,

            init() {
                this.read();
                this.$watch('$store.sbDevice.current', () => this.read());
                this.$watch('state', () => this.read());
            },

            device() {
                return stateFor(this.$store.sbDevice.current, this.responsive);
            },

            values() {
                return this.state && typeof this.state === 'object' ? this.state : {};
            },

            sidesFor(device) {
                const sides = this.values()[device];

                return sides && typeof sides === 'object' ? sides : {};
            },

            has(device) {
                return Object.values(this.sidesFor(device)).some((value) => (value ?? '') !== '');
            },

            read() {
                const sides = this.sidesFor(this.device());
                const numbers = {};

                this.parts.forEach((part) => {
                    const parsed = split(sides[part], this.units);

                    numbers[part] = parsed.number;
                    this.unit = parsed.unit ?? this.unit;
                });

                this.numbers = numbers;
            },

            toggleLinked() {
                this.linked = ! this.linked;

                if (this.linked) {
                    const first = this.parts.map((part) => this.numbers[part]).find((number) => number !== '' && number !== null);

                    if (first !== undefined) {
                        this.parts.forEach((part) => (this.numbers[part] = first));
                        this.write();
                    }
                }
            },

            write(changed = null) {
                if (this.linked && changed) {
                    this.parts.forEach((part) => (this.numbers[part] = this.numbers[changed]));
                }

                const values = { ...this.values() };
                const sides = {};

                this.parts.forEach((part) => {
                    const number = this.numbers[part];

                    if (number !== '' && number !== null && number !== undefined) {
                        sides[part] = `${number}${this.unit}`;
                    }
                });

                if (Object.keys(sides).length > 0) {
                    values[this.device()] = sides;
                } else {
                    delete values[this.device()];
                }

                this.state = Object.keys(values).length > 0 ? values : null;
            },

            fallback(part) {
                const from = fallbackOrder[this.device()].find((device) => (this.sidesFor(device)[part] ?? '') !== '');

                return from ? this.sidesFor(from)[part] : '';
            },
        }));

        // SiteColour: saved as #rrggbb, #rrggbbaa (see-through) or "gc:<id>" (a global colour). The picker is Filament's
        // own colour picker element, loaded from its module the first time a panel opens.
        const hexPattern = /^#?([0-9a-f]{6})([0-9a-f]{2})?$/i;

        const hexToRgba = (hex) => {
            const match = String(hex ?? '').match(hexPattern);

            if (! match) {
                return null;
            }

            const [red, green, blue] = [0, 2, 4].map((at) => parseInt(match[1].slice(at, at + 2), 16));
            const alpha = match[2] ? Math.round((parseInt(match[2], 16) / 255) * 100) / 100 : 1;

            return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
        };

        const rgbaToHex = (rgba) => {
            const parts = String(rgba ?? '').match(/[\d.]+/g);

            if (! parts || parts.length < 3) {
                return null;
            }

            const hex = parts.slice(0, 3).map((part) => Math.max(0, Math.min(255, Math.round(Number(part)))).toString(16).padStart(2, '0')).join('');
            const alpha = parts[3] === undefined ? 1 : Math.max(0, Math.min(1, Number(parts[3])));

            return `#${hex}${alpha < 1 ? Math.round(alpha * 255).toString(16).padStart(2, '0') : ''}`;
        };

        window.Alpine.data('sbSiteColour', ({ state, globals, alpha, pickerSrc, deletedLabel }) => ({
            state,
            globals,
            open: false,
            text: '',
            emitted: null,

            init() {
                this.read();
                this.$watch('state', () => {
                    this.read();

                    if (this.state !== this.emitted) {
                        this.syncPicker();
                    }
                });
            },

            read() {
                this.text = this.global() ? '' : (this.state ?? '');
            },

            global() {
                if (typeof this.state !== 'string' || ! this.state.startsWith('gc:')) {
                    return null;
                }

                const id = this.state.slice(3);

                return this.globals.find((colour) => colour.id === id) ?? { id, name: deletedLabel, colour: null, preview: 'transparent' };
            },

            preview() {
                return this.global()?.preview ?? (this.state || 'transparent');
            },

            async show() {
                this.open = true;

                if (! customElements.get('rgba-string-color-picker')) {
                    await import(pickerSrc);
                }

                if (! this.$refs.host.firstElementChild) {
                    const picker = document.createElement(alpha ? 'rgba-string-color-picker' : 'hex-color-picker');

                    picker.addEventListener('color-changed', window.Alpine.debounce((event) => {
                        this.emitted = alpha ? rgbaToHex(event.detail.value) : event.detail.value;
                        this.set(this.emitted);
                    }, 120));

                    this.$refs.host.appendChild(picker);
                }

                this.syncPicker();
            },

            toggle() {
                this.open ? (this.open = false) : this.show();
            },

            syncPicker() {
                const picker = this.$refs.host?.firstElementChild;
                const hex = this.global()?.colour ?? this.state;

                if (picker && hexPattern.test(hex ?? '')) {
                    picker.color = alpha ? hexToRgba(hex) : hex.slice(0, 7);
                }
            },

            typed() {
                const value = this.text.trim();

                if (value === '') {
                    this.state = null;

                    return;
                }

                const match = value.match(hexPattern);

                if (match) {
                    this.state = `#${match[1]}${alpha && match[2] ? match[2] : ''}`.toLowerCase();
                } else {
                    this.read();
                }
            },

            pickGlobal(id) {
                this.set(`gc:${id}`);
            },

            unlink() {
                this.set(this.global()?.colour ?? null);
            },

            clear() {
                this.set(null);
            },

            // A change from the picker, a swatch or a button: tells the panel, as typing does, so the visual builder's
            // canvas previews it.
            set(value) {
                this.state = value;
                this.$nextTick(() => this.$root.dispatchEvent(new Event('change', { bubbles: true })));
            },
        }));
    });
</script>

<style>
    /*
     * Each level's colour along the top of its card and panel (SettingsPanel::LEVEL_COLOURS), in every
     * editor: a Section or block card and panel ([data-sb-level] on the card or modal), and a row or column card in a
     * Section's form (the level is on its list, so only the list's own cards, not ones further in, take it).
     */
@foreach (\ScBudgetWeb\SiteBuilder\Blocks\Panel\SettingsPanel::LEVEL_COLOURS as $level => $colour)
    .fi-fo-builder-item[data-sb-level="{{ $level }}"],
    .fi-modal-window[data-sb-level="{{ $level }}"],
    [data-sb-level="{{ $level }}"] .fi-fo-repeater-item:not([data-sb-level="{{ $level }}"] [data-sb-level] .fi-fo-repeater-item) { border-top: 3px solid {{ $colour }}; }
@endforeach

    .sb-colour-row { display: flex; align-items: center; gap: 0.5rem; padding-inline-start: 0.625rem; min-height: 2.25rem; }
    .sb-colour-row .fi-input { padding-inline-start: 0; }
    .sb-colour-dot {
        flex: 0 0 auto; width: 1.25rem; height: 1.25rem; border-radius: 9999px; cursor: pointer;
        box-shadow: inset 0 0 0 1px rgb(0 0 0 / 0.15);
        /* A chequerboard under the colour, so a see-through colour shows as one. */
        background: linear-gradient(var(--sb-colour-preview), var(--sb-colour-preview)), repeating-conic-gradient(#d4d4d8 0 25%, #fff 0 50%) 0 0 / 0.5rem 0.5rem;
    }
    .sb-colour-dot-empty { background: repeating-conic-gradient(#d4d4d8 0 25%, #fff 0 50%) 0 0 / 0.5rem 0.5rem; opacity: 0.6; }
    .sb-colour-global { display: inline-flex; flex: 1 1 auto; align-items: center; gap: 0.375rem; font-size: 0.875rem; padding-block: 0.375rem; }
    .sb-colour-global-icon, .sb-colour-clear-icon { width: 1rem; height: 1rem; opacity: 0.6; }
    .sb-colour-unlink { margin-inline-start: auto; font-size: 0.75rem; color: var(--primary-600); }
    .sb-colour-unlink:hover { text-decoration: underline; }
    .sb-colour-clear { flex: 0 0 auto; padding: 0.25rem; margin-inline-end: 0.375rem; border-radius: 0.375rem; }
    .sb-colour-clear:hover { background: rgb(0 0 0 / 0.05); }
    .sb-colour-panel { margin-top: 0.5rem; padding: 0.75rem; border-radius: 0.5rem; box-shadow: 0 0 0 1px rgb(0 0 0 / 0.1), 0 4px 12px rgb(0 0 0 / 0.08); background: #fff; }
    .dark .sb-colour-panel { background: var(--gray-900); box-shadow: 0 0 0 1px rgb(255 255 255 / 0.1); }
    .sb-colour-picker > * { width: 100%; }
    .sb-colour-label { margin: 0.75rem 0 0.375rem; font-size: 0.75rem; font-weight: 500; opacity: 0.7; }
    .sb-colour-swatches { display: flex; flex-wrap: wrap; gap: 0.375rem; }
    .sb-colour-swatch {
        width: 1.75rem; height: 1.75rem; border-radius: 0.375rem; cursor: pointer; box-shadow: inset 0 0 0 1px rgb(0 0 0 / 0.15);
        background: linear-gradient(var(--sb-colour-preview), var(--sb-colour-preview)), repeating-conic-gradient(#d4d4d8 0 25%, #fff 0 50%) 0 0 / 0.5rem 0.5rem;
    }
    .sb-colour-swatch[aria-pressed='true'] { outline: 2px solid var(--primary-500); outline-offset: 2px; }
</style>
