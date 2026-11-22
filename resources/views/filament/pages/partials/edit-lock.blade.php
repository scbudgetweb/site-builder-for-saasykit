{{--
    The edit lock (HoldsEditLock) for $editor: a heartbeat that keeps the lock while the editor is open, freeing it
    as the page closes (or Livewire navigates away), and, for anyone else, a banner naming who's editing. Once their
    lock lapses the banner offers Start editing, which reloads with the latest content.
--}}
@php($holder = $editor->editLockHolder)
<div
    x-data="{
        state: @js($holder === null ? 'held' : 'locked'),
        beat: null,
        leave: null,
        init() {
            this.beat = setInterval(() => this.$wire.keepEditLock().then((state) => {
                if (state === 'lost') {
                    this.$wire.$refresh()
                }

                this.state = state === 'lost' ? 'locked' : state
            }), {{ \ScBudgetWeb\SiteBuilder\Pages\EditLocks::HEARTBEAT * 1000 }})

            this.leave = () => {
                if (this.state === 'held') {
                    navigator.sendBeacon(@js($editor->editLockReleaseUrl()))
                }
            }

            window.addEventListener('pagehide', this.leave)
            document.addEventListener('livewire:navigating', this.leave)
        },
        destroy() {
            clearInterval(this.beat)
            window.removeEventListener('pagehide', this.leave)
            document.removeEventListener('livewire:navigating', this.leave)
        },
    }"
>
    @if ($holder !== null)
        <div role="status" style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem 0.75rem; padding: 0.625rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; background: color-mix(in oklab, var(--warning-500) 12%, transparent); border: 1px solid color-mix(in oklab, var(--warning-500) 45%, transparent);">
            <x-filament::icon icon="heroicon-o-lock-closed" style="width: 1.25rem; height: 1.25rem; flex-shrink: 0; color: var(--warning-600);" />
            <span style="flex: 1 1 16rem;">
                <strong>{{ $editor->editLockMessage() }}</strong>
                ({{ __('since :time', ['time' => \Illuminate\Support\Carbon::parse($holder['since'])->format('H:i')]) }}).
                <span x-show="state !== 'free'">{{ __('You can look, but not change it until they close it.') }}</span>
                <span x-show="state === 'free'" x-cloak>{{ __('They\'ve closed it.') }}</span>
            </span>
            <x-filament::button x-show="state === 'free'" x-cloak x-on:click="window.location.reload()" size="sm" icon="heroicon-o-pencil-square">
                {{ __('Start editing') }}
            </x-filament::button>
        </div>
    @endif
</div>
