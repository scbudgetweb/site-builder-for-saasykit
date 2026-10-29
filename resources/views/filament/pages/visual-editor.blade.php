{{--
    The visual builder (VisualEditor): the canvas, an iframe showing the draft in the site's theme at phone, tablet or
    desktop width, full screen with its own toolbar. The canvas is on the same site, so this page's script reaches into it: it outlines the block under
    the pointer, opens a block's settings when it's clicked, and swaps in the page body's new HTML after each change
    (site-builder-canvas-updated). Links and forms on the canvas do nothing. Livewire redraws the toolbar, never the
    canvas (wire:ignore); only the script changes it.
--}}
<x-filament-panels::page>
    <script>
        window.siteBuilderCanvas ??= (config) => ({
            device: 'desktop',
            widths: { phone: '390px', tablet: '820px', desktop: '100%' },
            nodes: config.nodes,
            doc: null,
            hoverBox: null,
            selectBox: null,
            hoveredUid: null,
            selectedUid: null,
            clipboard: config.clipboard,
            menu: { open: false, x: 0, y: 0, uid: null, rowUid: null, columnUid: null, isNested: false, mode: 'main' },
            hoveredRow: null,
            drag: null,

            init() {
                this.$refs.frame.addEventListener('load', () => this.attach());
                this.$watch('device', () => window.setTimeout(() => this.redraw(), 250));

                // The design fields edit the screen size the canvas shows, and choosing one in a field shows it here.
                if (window.Alpine.store('sbDevice')) {
                    this.$watch('device', (device) => (window.Alpine.store('sbDevice').current = device));
                    // The panel's Hover state edits hover values; the canvas keeps its screen size.
                    this.$watch('$store.sbDevice.current', (device) => device && device !== 'hover' && device !== this.device && (this.device = device));
                }

                // Live preview: shortly after something in a block's panel changes, the canvas shows it (unsaved).
                let timer = null;
                const preview = (event) => {
                    if (! event.target.closest?.('.sb-floating-panel')) {
                        return;
                    }

                    window.clearTimeout(timer);
                    timer = window.setTimeout(() => this.$wire.previewBlock(), 400);
                };

                window.siteBuilderPreviewListener && ['input', 'change'].forEach((type) => document.removeEventListener(type, window.siteBuilderPreviewListener, true));
                window.siteBuilderPreviewListener = preview;
                ['input', 'change'].forEach((type) => document.addEventListener(type, preview, true));

                window.siteBuilderKeyListener && document.removeEventListener('keydown', window.siteBuilderKeyListener);
                window.siteBuilderKeyListener = (event) => this.shortcut(event);
                document.addEventListener('keydown', window.siteBuilderKeyListener);
                document.addEventListener('click', () => this.closeMenu());
            },

            // Ctrl+Z / ⌘Z undoes the last change, Ctrl+Y / ⇧⌘Z redoes it. Not while typing, or with a panel open,
            // where the browser's own undo works on what's being typed.
            shortcut(event) {
                if (event.key === 'Escape') {
                    this.closeMenu();
                }

                if (! (event.metaKey || event.ctrlKey) || event.altKey) {
                    return;
                }

                const key = event.key.toLowerCase();
                const isUndo = key === 'z' && ! event.shiftKey;
                const isRedo = key === 'y' || (key === 'z' && event.shiftKey);

                if (! isUndo && ! isRedo) {
                    return;
                }

                if (event.target?.closest?.('input, textarea, select, [contenteditable="true"], .fi-modal') || (this.$wire.mountedActions ?? []).length > 0) {
                    return;
                }

                event.preventDefault();
                this.closeMenu();
                isUndo ? this.$wire.undo() : this.$wire.redo();
            },

            // The canvas menu (right-click on a block): its settings, and copying and pasting its style.
            openMenu(event, element) {
                const frame = this.$refs.frame.getBoundingClientRect();
                const row = event.target.closest?.('[data-sb-node="row"]');
                const column = event.target.closest?.('[data-sb-node="column"]');

                this.menu = {
                    open: true,
                    x: Math.min(frame.left + event.clientX, window.innerWidth - 300),
                    y: Math.max(8, Math.min(frame.top + event.clientY, window.innerHeight - 560)),
                    uid: element.dataset.sbUid,
                    // The row and column the click was in (a block in a Section, or the Section itself).
                    rowUid: row && this.nodes[row.dataset.sbUid] ? row.dataset.sbUid : null,
                    // A one-column row names its column on the row.
                    columnUid: column && this.nodes[column.dataset.sbUid] ? column.dataset.sbUid : (row && this.nodes[row.dataset.sbColumn] ? row.dataset.sbColumn : null),
                    isNested: Boolean(element.parentElement?.closest('[data-sb-node="row"]')),
                    mode: 'main',
                };
            },

            menuRun(method, ...args) {
                this.closeMenu();
                this.$wire[method](...args);
            },

            menuPanel(name, uid) {
                this.closeMenu();
                this.openPanel(name, uid);
            },

            closeMenu() {
                this.menu.open = false;
            },

            menuNode() {
                return this.nodes[this.menu.uid] ?? {};
            },

            attach() {
                const doc = this.$refs.frame.contentDocument;

                if (! doc || ! doc.body) {
                    return;
                }

                this.doc = doc;

                const style = doc.createElement('style');
                style.textContent = `
                    [data-sb-node="block"] * { cursor: pointer; }
                    .sb-canvas-box { position: fixed; z-index: 2147483646; pointer-events: none; border-radius: 4px; display: none; }
                    /* Each level's colour, as in the panels (SettingsPanel::LEVEL_COLOURS): Section, row, block. */
                    .sb-canvas-box { --sb-level: ${config.levels.block}; }
                    .sb-canvas-box[data-level="section"] { --sb-level: ${config.levels.section}; }
                    .sb-canvas-box.is-hover { outline: 2px dashed var(--sb-level); outline-offset: -2px; }
                    .sb-canvas-box.is-selected { outline: 2px solid var(--sb-level); outline-offset: -2px; }
                    .sb-canvas-label { position: absolute; top: 0; left: 0; transform: translateY(-100%); padding: 2px 8px; border-radius: 4px 4px 0 0; background: var(--sb-level); color: #fff; font: 600 12px/18px system-ui, sans-serif; white-space: nowrap; }
                    .sb-canvas-box.is-top .sb-canvas-label { transform: none; border-radius: 0 0 4px 0; }
                    .sb-canvas-control { position: fixed; z-index: 2147483647; display: none; align-items: center; justify-content: center; border: 0; color: #fff; background: ${config.colour}; box-shadow: 0 2px 6px rgb(0 0 0 / 0.25); cursor: pointer; font: 600 12px/1 system-ui, sans-serif; }
                    .sb-canvas-plus { width: 26px; height: 26px; border-radius: 9999px; font-size: 18px; }
                    .sb-canvas-handle { height: 22px; padding: 0 8px; border-radius: 4px; cursor: grab; gap: 4px; }
                    .sb-canvas-handle[data-kind="block"] { background: ${config.levels.block}; }
                    .sb-canvas-handle[data-kind="row"], .sb-canvas-settings { background: ${config.levels.row}; }
                    .sb-canvas-settings { width: 22px; height: 22px; border-radius: 4px; font-size: 14px; }
                    .sb-canvas-drop { position: fixed; z-index: 2147483646; pointer-events: none; display: none; background: ${config.colour}; border-radius: 2px; }
                    .sb-canvas-drop.is-into { background: color-mix(in srgb, ${config.colour} 15%, transparent); outline: 2px dashed ${config.colour}; }
                    .sb-canvas-ghost { position: fixed; z-index: 2147483647; pointer-events: none; padding: 4px 10px; border-radius: 6px; background: ${config.colour}; color: #fff; font: 600 12px/18px system-ui, sans-serif; box-shadow: 0 4px 12px rgb(0 0 0 / 0.3); }
                    html.sb-canvas-dragging, html.sb-canvas-dragging * { cursor: grabbing !important; user-select: none; }
                `;
                doc.head.appendChild(style);

                this.hoverBox = this.makeBox('is-hover');
                this.selectBox = this.makeBox('is-selected');
                this.controls = {
                    before: this.makeControl('sb-canvas-plus', '+', @js(__('Add a block above'))),
                    after: this.makeControl('sb-canvas-plus', '+', @js(__('Add a block below'))),
                    blockHandle: this.makeControl('sb-canvas-handle', '⠿ ' + @js(__('Move')), @js(__('Drag to move this block')), 'block'),
                    rowHandle: this.makeControl('sb-canvas-handle', '⠿ ' + @js(__('Row')), @js(__('Drag to move this row')), 'row'),
                    rowSettings: this.makeControl('sb-canvas-settings', '⚙', @js(__('Row settings')), 'row'),
                };
                this.dropLine = doc.createElement('div');
                this.dropLine.className = 'sb-canvas-drop';
                doc.body.appendChild(this.dropLine);

                doc.addEventListener('mouseover', (event) => {
                    // Moving onto a + or a handle keeps the block it belongs to.
                    if (this.drag || event.target.closest?.('.sb-canvas-control')) {
                        return;
                    }

                    this.hoveredRow = event.target.closest?.('[data-sb-node="row"]') ?? null;
                    this.hover(this.nodeFrom(event.target));
                }, true);
                doc.addEventListener('pointerdown', (event) => this.startDrag(event), true);
                doc.addEventListener('mouseleave', () => this.hover(null));
                doc.addEventListener('click', (event) => {
                    // Nothing on the canvas navigates or submits: a click selects.
                    event.preventDefault();
                    event.stopPropagation();

                    const control = event.target.closest?.('.sb-canvas-control');

                    if (control) {
                        control.classList.contains('sb-canvas-plus') && this.addBlock(this.hoveredUid, control === this.controls.before ? 'before' : 'after');
                        control === this.controls.rowSettings && this.openPanel('editRow', this.hoveredRow?.dataset.sbUid);

                        return;
                    }

                    if (event.target.closest?.('[data-sb-add-first]')) {
                        this.addBlock(null, 'after');

                        return;
                    }

                    if (event.target.closest?.('[data-sb-empty]')) {
                        this.addBlock(this.columnOf(event.target), 'in');

                        return;
                    }

                    const element = this.nodeFrom(event.target);

                    if (element) {
                        this.select(element.dataset.sbUid);
                    }
                }, true);
                doc.addEventListener('submit', (event) => event.preventDefault(), true);
                doc.addEventListener('contextmenu', (event) => {
                    const element = this.nodeFrom(event.target);

                    if (element) {
                        event.preventDefault();
                        this.openMenu(event, element);
                    }
                }, true);
                doc.addEventListener('keydown', (event) => this.shortcut(event));
                doc.addEventListener('mousedown', () => this.closeMenu());
                doc.defaultView.addEventListener('scroll', () => this.closeMenu(), { passive: true });
                doc.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        this.select(null);
                    }
                });
                doc.defaultView.addEventListener('scroll', () => this.redraw(), { passive: true });
                doc.defaultView.addEventListener('resize', () => this.redraw());
            },

            makeBox(state) {
                const box = this.doc.createElement('div');
                box.className = `sb-canvas-box ${state}`;
                box.setAttribute('aria-hidden', 'true');
                box.innerHTML = '<span class="sb-canvas-label"></span>';
                this.doc.body.appendChild(box);

                return box;
            },

            // The innermost block the server lists. Blocks inside a linked library block aren't listed, so they select the link.
            nodeFrom(target) {
                let element = target instanceof this.doc.defaultView.Element ? target.closest('[data-sb-node]') : null;

                while (element) {
                    if (this.nodes[element.dataset.sbUid]?.kind === 'block') {
                        return element;
                    }

                    element = element.parentElement?.closest('[data-sb-node]') ?? null;
                }

                return null;
            },

            elementFor(uid) {
                return uid ? this.doc?.querySelector(`[data-sb-uid="${CSS.escape(uid)}"]`) : null;
            },

            // A block's wrapper is display: contents, so it's measured by what's inside it.
            rectOf(element) {
                const rects = [...(element.style.display === 'contents' ? element.children : [element])]
                    .map((child) => child.getBoundingClientRect())
                    .filter((rect) => rect.width > 0 || rect.height > 0);

                if (rects.length === 0) {
                    return null;
                }

                return {
                    top: Math.min(...rects.map((rect) => rect.top)),
                    left: Math.min(...rects.map((rect) => rect.left)),
                    right: Math.max(...rects.map((rect) => rect.right)),
                    bottom: Math.max(...rects.map((rect) => rect.bottom)),
                };
            },

            place(box, uid) {
                const element = this.elementFor(uid);
                const rect = element ? this.rectOf(element) : null;

                if (! box || ! rect) {
                    box && (box.style.display = 'none');

                    return;
                }

                Object.assign(box.style, {
                    display: 'block',
                    top: `${rect.top}px`,
                    left: `${rect.left}px`,
                    width: `${rect.right - rect.left}px`,
                    height: `${rect.bottom - rect.top}px`,
                });
                box.classList.toggle('is-top', rect.top < 20);
                box.dataset.level = this.nodes[uid]?.isSection ? 'section' : 'block';
                box.firstChild.textContent = this.nodes[uid]?.label ?? '';
            },

            hover(element) {
                this.hoveredUid = element?.dataset.sbUid ?? null;
                this.place(this.hoverBox, this.hoveredUid === this.selectedUid ? null : this.hoveredUid);
                this.placeControls();
            },

            makeControl(className, text, label, kind = null) {
                const control = this.doc.createElement('button');
                control.type = 'button';
                control.className = `sb-canvas-control ${className}`;
                control.textContent = text;
                control.title = label;
                control.setAttribute('aria-label', label);
                kind && (control.dataset.kind = kind);
                this.doc.body.appendChild(control);

                return control;
            },

            // The + buttons at the top and bottom of the hovered block, its drag handle, and its row's.
            placeControls() {
                const element = this.elementFor(this.hoveredUid);
                const rect = element && ! this.drag ? this.rectOf(element) : null;
                const show = (control, left, top) => Object.assign(control.style, { display: 'flex', left: `${left}px`, top: `${top}px` });

                Object.values(this.controls ?? {}).forEach((control) => (control.style.display = 'none'));

                if (! rect) {
                    return;
                }

                // A library item is one block: only what's inside it can be added to or moved.
                const isTopLevel = ! element.parentElement?.closest('[data-sb-node="row"]');

                if (config.topLevel || ! isTopLevel) {
                    const middle = (rect.left + rect.right) / 2 - 13;
                    show(this.controls.before, middle, Math.max(2, rect.top - 13));
                    show(this.controls.after, middle, Math.min(this.doc.defaultView.innerHeight - 28, rect.bottom - 13));
                    show(this.controls.blockHandle, Math.max(2, rect.right - 70), Math.max(2, rect.top + 4));
                }

                const row = this.hoveredRow && this.nodes[this.hoveredRow.dataset.sbUid] ? this.hoveredRow.getBoundingClientRect() : null;

                if (row) {
                    show(this.controls.rowSettings, Math.max(2, row.left + 4), Math.max(2, row.top + 4));
                    show(this.controls.rowHandle, Math.max(2, row.left + 30), Math.max(2, row.top + 4));
                }
            },

            // The column a target is in: a side-by-side column, or a stacked row's one column.
            columnOf(target) {
                const column = target.closest?.('[data-sb-node="column"]');

                return column?.dataset.sbUid ?? target.closest?.('[data-sb-node="row"]')?.dataset.sbColumn ?? null;
            },

            addBlock(target, position) {
                this.closeMenu();
                this.$wire.mountAction('addBlock', target ? { target, position } : {});
            },

            // Dragging a block or a row by its handle: a line shows where it'll go; dropping asks the server, which
            // checks the move is allowed.
            startDrag(event) {
                const handle = event.target.closest?.('.sb-canvas-handle');

                if (! handle || event.button !== 0) {
                    return;
                }

                const kind = handle.dataset.kind;
                const uid = kind === 'row' ? this.hoveredRow?.dataset.sbUid : this.hoveredUid;

                if (! uid) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                this.closeMenu();

                const ghost = this.doc.createElement('div');
                ghost.className = 'sb-canvas-ghost';
                ghost.textContent = this.nodes[uid]?.label ?? '';
                this.doc.body.appendChild(ghost);

                this.drag = { kind, uid, ghost, target: null };
                this.doc.documentElement.classList.add('sb-canvas-dragging');
                this.placeControls();
                this.place(this.hoverBox, null);

                const move = (moveEvent) => this.dragTo(moveEvent);
                const stop = () => {
                    this.doc.removeEventListener('pointermove', move, true);
                    this.doc.removeEventListener('pointerup', stop, true);
                    this.endDrag();
                };

                this.doc.addEventListener('pointermove', move, true);
                this.doc.addEventListener('pointerup', stop, true);
                this.dragTo(event);
            },

            dragTo(event) {
                const view = this.doc.defaultView;
                Object.assign(this.drag.ghost.style, { left: `${event.clientX + 12}px`, top: `${event.clientY + 12}px` });

                // Near the top or bottom edge, the page scrolls.
                if (event.clientY < 48) {
                    view.scrollBy(0, -18);
                } else if (event.clientY > view.innerHeight - 48) {
                    view.scrollBy(0, 18);
                }

                const under = this.doc.elementFromPoint(event.clientX, event.clientY);
                this.drag.target = under ? this.dropTargetFor(under, event.clientY) : null;
                this.showDropLine(this.drag.target);
            },

            // Where a drop at this point would go: before or after a block (or row), or into a column (a row: into a
            // Section). Never into the thing being dragged.
            dropTargetFor(under, y) {
                const dragged = this.elementFor(this.drag.uid);

                if (! under || (dragged && (dragged === under || dragged.contains(under)))) {
                    return null;
                }

                if (this.drag.kind === 'row') {
                    const row = under.closest('[data-sb-node="row"]');

                    if (row && this.nodes[row.dataset.sbUid]) {
                        const rect = row.getBoundingClientRect();

                        return { uid: row.dataset.sbUid, position: y < rect.top + rect.height / 2 ? 'before' : 'after', element: row };
                    }

                    const section = this.nodeFrom(under);

                    return section && this.nodes[section.dataset.sbUid]?.isSection ? { uid: section.dataset.sbUid, position: 'in', element: section } : null;
                }

                if (under.closest('[data-sb-empty]')) {
                    const column = this.columnOf(under);

                    return column ? { uid: column, position: 'in', element: under.closest('[data-sb-empty]') } : null;
                }

                const block = this.nodeFrom(under);

                if (block) {
                    const rect = this.rectOf(block);

                    return rect ? { uid: block.dataset.sbUid, position: y < rect.top + (rect.bottom - rect.top) / 2 ? 'before' : 'after', element: block } : null;
                }

                const column = under.closest('[data-sb-node="column"]');

                return column ? { uid: column.dataset.sbUid, position: 'in', element: column } : null;
            },

            showDropLine(target) {
                const rect = target ? (target.element.style.display === 'contents' ? this.rectOf(target.element) : target.element.getBoundingClientRect()) : null;

                if (! rect) {
                    this.dropLine.style.display = 'none';

                    return;
                }

                const isInto = target.position === 'in';
                this.dropLine.classList.toggle('is-into', isInto);
                Object.assign(this.dropLine.style, {
                    display: 'block',
                    left: `${rect.left}px`,
                    width: `${rect.right - rect.left}px`,
                    top: `${isInto ? rect.top : (target.position === 'before' ? rect.top - 2 : rect.bottom - 2)}px`,
                    height: isInto ? `${rect.bottom - rect.top}px` : '4px',
                });
            },

            endDrag() {
                const { uid, ghost, target } = this.drag;

                ghost.remove();
                this.dropLine.style.display = 'none';
                this.doc.documentElement.classList.remove('sb-canvas-dragging');
                this.drag = null;

                if (target) {
                    this.$wire.dropNode(uid, target.uid, target.position);
                }
            },

            // Saves and closes the open panel before another opens; false when it stays open because its changes don't
            // pass validation (it shows them). A panel the server still has open but that isn't on screen can't be
            // saved or closed by hand, and would stop the canvas responding: it's closed, with a note in the console.
            async closeOpenPanel() {
                const isOpen = () => (this.$wire.mountedActions ?? []).length > 0;

                if (! isOpen()) {
                    return true;
                }

                await this.$wire.callMountedAction();

                if (! isOpen()) {
                    return true;
                }

                if (document.querySelector('.fi-modal.fi-modal-open')) {
                    return false;
                }

                console.warn('[Site builder] Closed a panel that was open out of sight:', (this.$wire.mountedActions ?? []).map((action) => action.name));
                await this.$wire.unmountAction(true);

                return ! isOpen();
            },

            // A row's or column's settings: an open panel is saved and closed first.
            async openPanel(name, uid) {
                if (! uid || ! this.nodes[uid] || ! await this.closeOpenPanel()) {
                    return;
                }

                this.$wire.mountAction(name, { uid });
            },

            // With a panel open, its changes are saved first.
            async select(uid) {
                if (uid && ! await this.closeOpenPanel()) {
                    return;
                }

                this.selectedUid = uid;
                this.place(this.selectBox, uid);

                if (uid) {
                    this.$wire.mountAction('editBlock', { uid });
                }
            },

            redraw() {
                this.place(this.hoverBox, this.hoveredUid === this.selectedUid ? null : this.hoveredUid);
                this.place(this.selectBox, this.selectedUid);
                this.placeControls();
            },

            // After a change, or a live preview: the page body's new HTML. Alpine in the canvas starts up the new
            // elements by itself. A preview keeps the block being edited selected.
            // With a uid, only that top-level block is swapped; if it isn't on the canvas, the whole page is asked for.
            update(detail) {
                const target = detail.uid
                    ? this.doc?.querySelector(`[data-sb-node="block"][data-sb-uid="${CSS.escape(detail.uid)}"]`)
                    : this.doc?.querySelector('[data-page]');

                if (target) {
                    target.outerHTML = detail.html;
                } else if (detail.uid) {
                    this.$wire.refreshCanvas();
                }

                this.nodes = detail.nodes;

                // A font a block has just chosen: its stylesheet, once.
                (detail.fonts ?? []).forEach((href) => {
                    if (this.doc && ! [...this.doc.querySelectorAll('link[rel="stylesheet"]')].some((link) => link.href === href)) {
                        const link = this.doc.createElement('link');
                        link.rel = 'stylesheet';
                        link.href = href;
                        this.doc.head.appendChild(link);
                    }
                });

                if (! detail.isPreview) {
                    this.selectedUid = null;
                    this.hoveredUid = null;
                }

                this.redraw();
            },
        });

        // Dragging a floating panel by its header, and remembering where (and how wide) it was left, for this browser.
        window.siteBuilderPanel ??= (() => {
            const root = document.documentElement;
            const keys = ['--sb-panel-top', '--sb-panel-left', '--sb-panel-right', '--sb-panel-width', '--sb-panel-wide-width'];

            try {
                const saved = JSON.parse(window.localStorage.getItem('site-builder.panel') ?? '{}');
                keys.forEach((key) => saved[key] && root.style.setProperty(key, saved[key]));
            } catch (error) {}

            const remember = () => {
                try {
                    window.localStorage.setItem('site-builder.panel', JSON.stringify(Object.fromEntries(keys.map((key) => [key, root.style.getPropertyValue(key)]))));
                } catch (error) {}
            };

            document.addEventListener('pointerdown', (event) => {
                const header = event.target.closest?.('.sb-floating-panel .fi-modal-header');

                if (! header || event.target.closest('button, a, input, select, textarea') || event.button !== 0) {
                    return;
                }

                const panel = header.closest('.sb-floating-panel');
                const start = panel.getBoundingClientRect();
                const offsetX = event.clientX - start.left;
                const offsetY = event.clientY - start.top;

                const move = (moveEvent) => {
                    const left = Math.min(Math.max(0, moveEvent.clientX - offsetX), window.innerWidth - 80);
                    const top = Math.min(Math.max(0, moveEvent.clientY - offsetY), window.innerHeight - 48);

                    root.style.setProperty('--sb-panel-left', `${left}px`);
                    root.style.setProperty('--sb-panel-right', 'auto');
                    root.style.setProperty('--sb-panel-top', `${top}px`);
                };

                const stop = () => {
                    document.removeEventListener('pointermove', move);
                    document.removeEventListener('pointerup', stop);
                    remember();
                };

                event.preventDefault();
                document.addEventListener('pointermove', move);
                document.addEventListener('pointerup', stop);
            });

            // Resized from its corner: the next panel of the same kind opens at that width.
            document.addEventListener('pointerup', (event) => {
                const panel = event.target.closest?.('.sb-floating-panel');

                if (! panel || ! panel.style.width) {
                    return;
                }

                root.style.setProperty(panel.hasAttribute('data-sb-wide') ? '--sb-panel-wide-width' : '--sb-panel-width', panel.style.width);
                panel.style.width = '';
                remember();
            });

            // A block's panel opened from a Section's (Add or Edit in a column) stacks over it: the
            // Section's panel hides until it closes, then shows again where it was.
            const markCovered = () => {
                const open = [...document.querySelectorAll('.fi-modal.fi-modal-open > .fi-modal-window-ctn > .sb-floating-panel')];

                open.forEach((panel, index) => panel.toggleAttribute('data-sb-covered', index < open.length - 1));
            };

            new MutationObserver(() => window.requestAnimationFrame(markCovered))
                .observe(document.body, { subtree: true, childList: true, attributes: true, attributeFilter: ['class'] });

            return true;
        })();
    </script>

    {{-- Full screen: no admin sidebar or top bar on this page, and the canvas fills the window. --}}
    <style>
        .fi-sidebar, .fi-sidebar-close-overlay, .fi-topbar-ctn, .fi-layout-sidebar-toggle-btn-ctn { display: none !important; }
        .fi-main-ctn { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        .fi-main { max-width: none !important; padding: 0 !important; }
        .fi-page-header-main-ctn, .fi-page-main, .fi-page-content { padding: 0 !important; row-gap: 0 !important; gap: 0 !important; }

        /* A block's settings float over the canvas: no dark overlay, the canvas stays clickable, and the
           panel can be dragged by its header and resized from its corner. It opens where it was last left. */
        .fi-modal:has(> .fi-modal-window-ctn > .sb-floating-panel) { pointer-events: none; }
        .fi-modal:has(> .fi-modal-window-ctn > .sb-floating-panel) > .fi-modal-close-overlay { display: none; }
        .fi-modal:has(> .fi-modal-window-ctn > .sb-floating-panel) > .fi-modal-window-ctn { display: block; padding: 0; pointer-events: none; }
        .fi-modal-window.sb-floating-panel {
            pointer-events: auto;
            position: fixed;
            top: var(--sb-panel-top, 4.5rem);
            left: var(--sb-panel-left, auto);
            right: var(--sb-panel-right, 1.5rem);
            width: var(--sb-panel-width, 30rem);
            max-width: calc(100vw - 2rem);
            height: auto;
            max-height: calc(100dvh - 6rem);
            margin: 0 !important;
            overflow: auto;
            resize: both;
            box-shadow: 0 20px 50px -12px rgb(0 0 0 / 0.35), 0 0 0 1px rgb(128 128 128 / 0.2);
        }
        .fi-modal-window.sb-floating-panel[data-sb-wide] { width: var(--sb-panel-wide-width, min(56rem, calc(100vw - 3rem))); }
        .sb-floating-panel .fi-modal-header { cursor: move; user-select: none; }
        .fi-modal-window.sb-floating-panel[data-sb-covered] { visibility: hidden; }
        .sb-canvas-menu .fi-dropdown-list-item { white-space: normal; text-align: start; }

        /* Layered panels: the breadcrumb title, and the structure lists (rows, columns, blocks) to open. */
        .sb-panel-trail { display: inline-flex; align-items: center; gap: 0.5rem; }
        .sb-panel-trail-back { display: inline-flex; padding: 0.25rem; border-radius: 0.375rem; color: var(--gray-500); }
        .sb-panel-trail-back:hover { background: rgb(0 0 0 / 0.05); color: var(--gray-900); }
        .dark .sb-panel-trail-back:hover { background: rgb(255 255 255 / 0.08); color: #fff; }
        .sb-panel-trail-icon, .sb-structure-icon { width: 1.125rem; height: 1.125rem; flex: 0 0 auto; }
        .sb-structure { display: flex; flex-direction: column; gap: 0.5rem; }
        .sb-structure-column { display: flex; align-items: center; gap: 0.75rem; width: 100%; padding: 0.75rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; text-align: start; background: rgb(0 0 0 / 0.03); box-shadow: inset 0 0 0 1px rgb(0 0 0 / 0.06); }
        .dark .sb-structure-column { background: rgb(255 255 255 / 0.04); box-shadow: inset 0 0 0 1px rgb(255 255 255 / 0.08); }
        .sb-structure-column:not(:disabled):hover { box-shadow: inset 0 0 0 2px var(--primary-500); }
        .sb-structure-column:disabled { opacity: 0.6; cursor: not-allowed; }
        .sb-structure-label { flex: 1 1 auto; font-weight: 500; }
        .sb-structure-muted { font-size: 0.8125rem; opacity: 0.65; }
        .sb-structure-empty { font-size: 0.875rem; opacity: 0.7; }
        .sb-canvas-menu .fi-dropdown-list-item-label { white-space: normal; }
    </style>

    <div
        x-data="siteBuilderCanvas({ nodes: @js($this->canvasNodes()), colour: '#2563eb', levels: @js(\ScBudgetWeb\SiteBuilder\Blocks\Panel\SettingsPanel::LEVEL_COLOURS), clipboard: @js($this->styleClipboardLabel()), topLevel: @js($this->allowsTopLevelChanges()) })"
        x-on:site-builder-canvas-updated.window="update($event.detail)"
        x-on:site-builder-style-copied.window="clipboard = $event.detail.label"
        style="display: flex; flex-direction: column; height: 100dvh;"
    >
        <header style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem 1rem; padding: 0.5rem 1rem; border-bottom: 1px solid rgb(128 128 128 / 0.25);">
            <x-filament::icon-button
                tag="a"
                :href="$this::getResource()::getUrl('index')"
                icon="heroicon-o-arrow-left"
                color="gray"
                :label="__('Back to pages')"
                :tooltip="__('Back to pages')"
            />

            <div style="flex: 1 1 14rem; min-width: 0;">
                <div style="font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $this->getTitle() }}</div>
                <div style="font-size: 0.8125rem; opacity: 0.7; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $this->statusText() }}">{{ $this->statusText() }}</div>
            </div>

            <x-filament::tabs :label="__('Screen size')">
                <x-filament::tabs.item alpine-active="device === 'phone'" x-on:click="device = 'phone'" icon="heroicon-o-device-phone-mobile">
                    {{ __('Phone') }}
                </x-filament::tabs.item>
                <x-filament::tabs.item alpine-active="device === 'tablet'" x-on:click="device = 'tablet'" icon="heroicon-o-device-tablet">
                    {{ __('Tablet') }}
                </x-filament::tabs.item>
                <x-filament::tabs.item alpine-active="device === 'desktop'" x-on:click="device = 'desktop'" icon="heroicon-o-computer-desktop">
                    {{ __('Desktop') }}
                </x-filament::tabs.item>
            </x-filament::tabs>

            <div style="display: flex; align-items: center; gap: 0.125rem;">
                {{ $this->undoAction }}
                {{ $this->redoAction }}
                {{ $this->revisionsAction }}
            </div>

            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem;">
                {{ $this->editAsFormAction }}
                {{ $this->discardAction }}
                {{ $this->publishAction }}
            </div>
        </header>

        <div wire:ignore style="flex: 1 1 auto; min-height: 0; display: flex; justify-content: center; padding: 0.75rem; background: rgb(128 128 128 / 0.12);">
            <iframe
                x-ref="frame"
                src="{{ $this->canvasUrl() }}"
                title="{{ __('The page, as visitors will see it') }}"
                x-bind:style="{ width: widths[device] }"
                style="width: 100%; max-width: 100%; height: 100%; border: 0; border-radius: 0.5rem; background: #fff; box-shadow: 0 1px 3px rgb(0 0 0 / 0.15); transition: width 200ms ease;"
            ></iframe>
        </div>

        {{-- The canvas menu: right-click a block. Uses Filament's dropdown look. Only Alpine draws it, so Livewire's
             updates leave it alone (wire:ignore on a wrapper, so that covers the menu's own attributes too). --}}
        <div wire:ignore>
        <div
            x-show="menu.open"
            x-cloak
            x-on:click.stop
            class="fi-dropdown"
            {{-- An object, not a string: a string replaces the whole style, including x-show's display: none, so the
                 menu showed (top left) whenever this re-ran while closed. --}}
            style="position: fixed; z-index: 60;"
            x-bind:style="{ left: menu.x + 'px', top: menu.y + 'px' }"
            x-data="{ groups: @js(collect(\ScBudgetWeb\SiteBuilder\Blocks\StyleClipboard::GROUPS)->map(fn (string $label): string => __($label))->all()) }"
        >
            {{-- Filament caps a dropdown at 14rem with !important; this menu's labels are longer, so they wrap too. --}}
            <div class="fi-dropdown-panel sb-canvas-menu" style="position: static; width: 18rem !important; max-width: calc(100vw - 2rem) !important; max-height: calc(100vh - 2rem); overflow-y: auto;">
                <div class="fi-dropdown-list" style="padding: 0.25rem;">
                    <template x-if="menu.mode === 'main'">
                        <div>
                            <div style="padding: 0.375rem 0.5rem; font-size: 0.75rem; font-weight: 600; opacity: 0.6;" x-text="menuNode().label"></div>
                            <button type="button" class="fi-dropdown-list-item" x-on:click="closeMenu(); select(menu.uid)">
                                <span class="fi-dropdown-list-item-label">{{ __('Edit settings') }}</span>
                            </button>
                            <button type="button" class="fi-dropdown-list-item" x-on:click="addBlock(menu.uid, 'before')">
                                <span class="fi-dropdown-list-item-label">{{ __('Add a block above') }}</span>
                            </button>
                            <button type="button" class="fi-dropdown-list-item" x-on:click="addBlock(menu.uid, 'after')">
                                <span class="fi-dropdown-list-item-label">{{ __('Add a block below') }}</span>
                            </button>
                            <button type="button" class="fi-dropdown-list-item" x-on:click="menuRun('duplicateNode', menu.uid)">
                                <span class="fi-dropdown-list-item-label">{{ __('Duplicate') }}</span>
                            </button>
                            <button type="button" class="fi-dropdown-list-item" x-on:click="menuRun('stepNode', menu.uid, 'up')">
                                <span class="fi-dropdown-list-item-label">{{ __('Move up') }}</span>
                            </button>
                            <button type="button" class="fi-dropdown-list-item" x-on:click="menuRun('stepNode', menu.uid, 'down')">
                                <span class="fi-dropdown-list-item-label">{{ __('Move down') }}</span>
                            </button>
                            <template x-if="menu.isNested">
                                <div>
                                    <button type="button" class="fi-dropdown-list-item" x-on:click="menuRun('stepNode', menu.uid, 'left')">
                                        <span class="fi-dropdown-list-item-label">{{ __('Move to the column on the left') }}</span>
                                    </button>
                                    <button type="button" class="fi-dropdown-list-item" x-on:click="menuRun('stepNode', menu.uid, 'right')">
                                        <span class="fi-dropdown-list-item-label">{{ __('Move to the column on the right') }}</span>
                                    </button>
                                </div>
                            </template>
                            <button type="button" class="fi-dropdown-list-item" x-on:click="menuRun('deleteNode', menu.uid)">
                                <span class="fi-dropdown-list-item-label">{{ __('Delete') }}</span>
                            </button>
                            <template x-if="menuNode().isSection">
                                <button type="button" class="fi-dropdown-list-item" x-on:click="menuRun('addRow', menu.uid)">
                                    <span class="fi-dropdown-list-item-label">{{ __('Add a row') }}</span>
                                </button>
                            </template>
                            <div style="margin: 0.25rem 0; border-top: 1px solid rgb(128 128 128 / 0.2);"></div>
                            <template x-if="menuNode().hasStyle">
                                <div>
                                    <button type="button" class="fi-dropdown-list-item" x-on:click="menu.mode = 'copy'">
                                        <span class="fi-dropdown-list-item-label">{{ __('Copy style') }} ›</span>
                                    </button>
                                    <button type="button" class="fi-dropdown-list-item" x-bind:disabled="! clipboard" x-bind:style="clipboard ? '' : 'opacity: 0.5;'" x-on:click="closeMenu(); $wire.pasteStyle(menu.uid)">
                                        <span class="fi-dropdown-list-item-label" x-text="clipboard ? @js(__('Paste style')) + ': ' + clipboard : @js(__('Paste style (copy one first)'))"></span>
                                    </button>
                                    <button type="button" class="fi-dropdown-list-item" x-on:click="menu.mode = 'all'">
                                        <span class="fi-dropdown-list-item-label" x-text="@js(__('Give all :type blocks this style', ['type' => '%s'])).replace('%s', menuNode().label) + ' ›'"></span>
                                    </button>
                                </div>
                            </template>
                            <template x-if="! menuNode().hasStyle">
                                <div style="padding: 0.375rem 0.5rem; font-size: 0.75rem; opacity: 0.6; white-space: normal;">{{ __('A library block is styled in the block library.') }}</div>
                            </template>
                            <template x-if="menu.rowUid">
                                <div>
                                    <div style="margin: 0.25rem 0; border-top: 1px solid rgb(128 128 128 / 0.2);"></div>
                                    <div style="padding: 0.375rem 0.5rem; font-size: 0.75rem; font-weight: 600; opacity: 0.6;" x-text="nodes[menu.rowUid]?.label"></div>
                                    <button type="button" class="fi-dropdown-list-item" x-on:click="menuPanel('editRow', menu.rowUid)">
                                        <span class="fi-dropdown-list-item-label">{{ __('Row settings') }}</span>
                                    </button>
                                    <button type="button" class="fi-dropdown-list-item" x-on:click="menuRun('addRow', menu.rowUid)">
                                        <span class="fi-dropdown-list-item-label">{{ __('Add a row below') }}</span>
                                    </button>
                                    <button type="button" class="fi-dropdown-list-item" x-on:click="menuRun('duplicateNode', menu.rowUid)">
                                        <span class="fi-dropdown-list-item-label">{{ __('Duplicate row') }}</span>
                                    </button>
                                    <button type="button" class="fi-dropdown-list-item" x-on:click="menuRun('stepNode', menu.rowUid, 'up')">
                                        <span class="fi-dropdown-list-item-label">{{ __('Move row up') }}</span>
                                    </button>
                                    <button type="button" class="fi-dropdown-list-item" x-on:click="menuRun('stepNode', menu.rowUid, 'down')">
                                        <span class="fi-dropdown-list-item-label">{{ __('Move row down') }}</span>
                                    </button>
                                    <button type="button" class="fi-dropdown-list-item" x-on:click="menuRun('deleteNode', menu.rowUid)">
                                        <span class="fi-dropdown-list-item-label">{{ __('Delete row') }}</span>
                                    </button>
                                    <template x-if="menu.columnUid">
                                        <button type="button" class="fi-dropdown-list-item" x-on:click="menuPanel('editColumn', menu.columnUid)">
                                            <span class="fi-dropdown-list-item-label" x-text="(nodes[menu.columnUid]?.label ?? '') + ': ' + @js(__('settings'))"></span>
                                        </button>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>

                    <template x-if="menu.mode !== 'main'">
                        <div>
                            <button type="button" class="fi-dropdown-list-item" x-on:click="menu.mode = 'main'">
                                <span class="fi-dropdown-list-item-label" x-text="'‹ ' + (menu.mode === 'copy' ? @js(__('Copy style')) : @js(__('Give all :type blocks this style', ['type' => '%s'])).replace('%s', menuNode().label))"></span>
                            </button>
                            <template x-for="(label, group) in groups" x-bind:key="group">
                                <button type="button" class="fi-dropdown-list-item" x-on:click="closeMenu(); menu.mode === 'copy' ? $wire.copyStyle(menu.uid, group) : $wire.applyStyleToAll(menu.uid, group)">
                                    <span class="fi-dropdown-list-item-label" x-text="label"></span>
                                </button>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
        </div>
    </div>
</x-filament-panels::page>
