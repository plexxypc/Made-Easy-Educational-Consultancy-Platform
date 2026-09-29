<div class="space-y-4">
    <h1 class="text-2xl font-semibold text-gray-900">
        Hello{{ $name !== '' ? ', '.$name : '' }}!
    </h1>

    <p class="text-sm text-gray-600">
        This page is rendered by a Livewire component. Type a name or press the button to confirm server round-trips work.
    </p>

    <div>
        <x-input-label for="name" value="Your name" />
        <x-text-input id="name" type="text" class="mt-1 block w-full" wire:model.live.debounce.250ms="name" />
    </div>

    <div class="flex items-center gap-4">
        <x-primary-button type="button" wire:click="increment">
            Click me
        </x-primary-button>

        <span class="text-sm text-gray-700">
            Clicked <span data-testid="click-count">{{ $click_count }}</span> {{ Str::plural('time', $click_count) }}
        </span>
    </div>
</div>
