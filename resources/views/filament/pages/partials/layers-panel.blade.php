{{--
    The visual builder's layers panel (its state and methods are the canvas's, siteBuilderCanvas): every Section, row,
    column and block on the page as a tree, from VisualEditor::layers(). Click one to scroll to it and open its
    settings; point at one to outline it on the canvas; drag blocks and rows to move them (dropNode()); the eye hides
    one on every screen (toggleLayerHidden()); double-click or the pencil renames it (its admin label, renameLayer()).
    Its own styles here, as the admin theme doesn't build the package's classes.
--}}
<style>
    .sb-layers { width: 18rem; flex: none; display: flex; flex-direction: column; min-height: 0; border-radius: 0.5rem; background: #fff; box-shadow: 0 1px 3px rgb(0 0 0 / 0.15); font-size: 0.8125rem; }
    .dark .sb-layers { background: rgb(24 24 27); }
    .sb-layers-head { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.625rem 0.75rem; border-bottom: 1px solid rgb(128 128 128 / 0.2); font-weight: 600; font-size: 0.875rem; }
    .sb-layers-list { flex: 1 1 auto; overflow-y: auto; padding: 0.25rem; }
    .sb-layer { position: relative; display: flex; align-items: center; gap: 0.25rem; min-height: 2rem; padding-right: 0.25rem; border-radius: 0.375rem; }
    .sb-layer:hover, .sb-layer.is-hovered { background: rgb(128 128 128 / 0.1); }
    .sb-layer.is-selected { background: color-mix(in srgb, var(--primary-500) 14%, transparent); }
    .sb-layer.is-hidden .sb-layer-text { opacity: 0.45; text-decoration: line-through; }
    .sb-layer.is-dragged { opacity: 0.4; }
    .sb-layer.is-drop-before::before, .sb-layer.is-drop-after::after { content: ''; position: absolute; left: 0.5rem; right: 0.5rem; height: 2px; border-radius: 1px; background: var(--primary-500); }
    .sb-layer.is-drop-before::before { top: -1px; }
    .sb-layer.is-drop-after::after { bottom: -1px; }
    .sb-layer.is-drop-in { box-shadow: inset 0 0 0 2px var(--primary-500); }
    .sb-layer-icon-button { display: inline-flex; align-items: center; justify-content: center; width: 1.5rem; height: 1.5rem; flex: none; border-radius: 0.25rem; opacity: 0.6; }
    .sb-layer-icon-button:hover, .sb-layer-icon-button:focus-visible { opacity: 1; background: rgb(128 128 128 / 0.15); }
    .sb-layer-icon-button svg { width: 0.875rem; height: 0.875rem; }
    .sb-layer-actions { display: flex; opacity: 0; }
    .sb-layer:hover .sb-layer-actions, .sb-layer:focus-within .sb-layer-actions, .sb-layer.is-hidden .sb-layer-actions { opacity: 1; }
    .sb-layer.is-hidden .sb-layer-actions .sb-layer-rename { opacity: 0; }
    .sb-layer:hover .sb-layer-actions .sb-layer-rename, .sb-layer:focus-within .sb-layer-actions .sb-layer-rename { opacity: 0.6; }
    .sb-layer-swatch { width: 0.5rem; height: 0.5rem; flex: none; border-radius: 2px; }
    .sb-layer-label { display: flex; flex: 1 1 auto; min-width: 0; align-items: baseline; gap: 0.375rem; padding: 0.25rem; text-align: start; cursor: pointer; }
    .sb-layer-text { flex: 0 0 auto; max-width: 70%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500; }
    .sb-layer-detail { flex: 1 1 auto; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; opacity: 0.55; }
    .sb-layer-input { flex: 1 1 auto; min-width: 0; padding: 0.125rem 0.375rem; border-radius: 0.25rem; border: 1px solid var(--primary-500); background: transparent; font: inherit; }
    .sb-layers-hint { padding: 0.5rem 0.75rem; border-top: 1px solid rgb(128 128 128 / 0.2); font-size: 0.75rem; opacity: 0.65; }
</style>

<aside class="sb-layers" x-show="layersOpen" x-cloak aria-label="{{ __('Layers') }}">
    <div class="sb-layers-head">
        <span>{{ __('Layers') }}</span>
        <button type="button" class="sb-layer-icon-button" x-on:click="toggleLayers()" aria-label="{{ __('Close layers') }}" title="{{ __('Close layers') }}">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <div class="sb-layers-list" role="tree" aria-label="{{ __('Layers') }}" x-on:mouseleave="hoverLayer(null)">
        <template x-if="layers.length === 0">
            <p class="sb-structure-empty" style="padding: 0.75rem;">{{ __('Nothing here yet. Add a block with +.') }}</p>
        </template>

        <template x-for="layer in visibleLayers()" :key="layer.uid">
            <div class="sb-layer" role="treeitem"
                 x-bind:aria-level="layer.depth + 1"
                 x-bind:aria-expanded="layer.hasChildren ? String(! collapsed[layer.uid]) : null"
                 x-bind:aria-selected="selectedUid === layer.uid ? 'true' : 'false'"
                 x-bind:class="{
                     'is-selected': selectedUid === layer.uid,
                     'is-hovered': hoveredUid === layer.uid,
                     'is-hidden': layer.hidden,
                     'is-dragged': layerDrag?.uid === layer.uid,
                     'is-drop-before': layerDrop?.uid === layer.uid && layerDrop.position === 'before',
                     'is-drop-after': layerDrop?.uid === layer.uid && layerDrop.position === 'after',
                     'is-drop-in': layerDrop?.uid === layer.uid && layerDrop.position === 'in',
                 }"
                 x-bind:style="`padding-left: ${0.25 + layer.depth * 0.875}rem`"
                 x-bind:draggable="canDragLayer(layer) && renaming !== layer.uid ? 'true' : 'false'"
                 x-on:dragstart="layerDragStart($event, layer)"
                 x-on:dragover="layerDragOver($event, layer)"
                 x-on:dragleave="layerDrop?.uid === layer.uid && (layerDrop = null)"
                 x-on:drop.prevent="dropOnLayer($event, layer)"
                 x-on:dragend="layerDragEnd()"
                 x-on:mouseenter="hoverLayer(layer.uid)">
                <button type="button" class="sb-layer-icon-button"
                        x-bind:style="layer.hasChildren ? '' : 'visibility: hidden'"
                        x-on:click="toggleCollapsed(layer.uid)"
                        x-bind:aria-label="collapsed[layer.uid] ? @js(__('Open')) : @js(__('Fold'))">
                    <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"
                         x-bind:style="collapsed[layer.uid] ? 'transform: rotate(-90deg)' : ''" style="transition: transform 150ms;"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                </button>

                <span class="sb-layer-swatch" aria-hidden="true"
                      x-bind:style="`background: ${layer.isSection ? levels.section : levels[layer.kind]}`"></span>

                <template x-if="renaming !== layer.uid">
                    <button type="button" class="sb-layer-label"
                            x-on:click="openLayer(layer)"
                            x-on:dblclick.prevent="startRename(layer)"
                            x-bind:title="layer.detail ? `${layer.label}: ${layer.detail}` : layer.label">
                        <span class="sb-layer-text" x-text="layer.label"></span>
                        <span class="sb-layer-detail" x-text="layer.detail"></span>
                    </button>
                </template>
                <template x-if="renaming === layer.uid">
                    <input id="sb-layer-rename" type="text" class="sb-layer-input" maxlength="60"
                           x-model="renameText"
                           x-on:keydown.enter.prevent="finishRename(true)"
                           x-on:keydown.escape.prevent.stop="finishRename(false)"
                           x-on:blur="finishRename(true)"
                           aria-label="{{ __('Admin label') }}"
                           placeholder="{{ __('Its usual name') }}">
                </template>

                <span class="sb-layer-actions">
                    <button type="button" class="sb-layer-icon-button sb-layer-rename" x-on:click="startRename(layer)"
                            aria-label="{{ __('Rename') }}" title="{{ __('Rename (its admin label)') }}">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Z"/></svg>
                    </button>
                    <button type="button" class="sb-layer-icon-button" x-show="! layer.isLinked"
                            x-on:click="$wire.toggleLayerHidden(layer.uid)"
                            x-bind:aria-pressed="layer.hidden ? 'true' : 'false'"
                            x-bind:aria-label="layer.hidden ? @js(__('Show on every screen')) : @js(__('Hide on every screen'))"
                            x-bind:title="layer.hidden ? @js(__('Hidden on every screen: show it')) : (layer.partlyHidden ? @js(__('Hidden on some screens (Design › Visibility): hide it on every screen')) : @js(__('Hide on every screen')))">
                        <svg x-show="! layer.hidden && ! layer.partlyHidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                        <svg x-show="layer.hidden || layer.partlyHidden" x-bind:style="layer.partlyHidden ? 'opacity: 0.6' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                    </button>
                </span>
            </div>
        </template>
    </div>

    <p class="sb-layers-hint">{{ __('Click to edit, double-click to rename, drag to move.') }}</p>
</aside>
