{{--
    Mount this ONCE per layout. Trigger it from anywhere with:

    window.dispatchEvent(new CustomEvent('confirm-action', {
        detail: {
            message: 'Delete this thing?',
            onConfirm: () => { ...whatever should happen if confirmed... }
        }
    }))

    onConfirm can submit a hidden form, call a Livewire method via
    window.Livewire.find(id).call(...), or anything else — it's just a
    plain JS closure, not serialized, so it can do anything inline JS can.
--}}
<div
    x-data="{ open: false, message: '', onConfirm: null }"
    x-on:confirm-action.window="
        open = true;
        message = $event.detail.message;
        onConfirm = $event.detail.onConfirm;
    "
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center"
>
    <div class="absolute inset-0 bg-black/40" x-on:click="open = false"></div>
    <div class="relative bg-white rounded-lg shadow-lg p-6 max-w-sm w-full mx-4"
         x-show="open" x-transition.opacity.duration.200ms>
        <p class="text-sm text-gray-700 mb-5" x-text="message"></p>
        <div class="flex justify-end gap-2">
            <button type="button" x-on:click="open = false"
                    class="px-3 py-1.5 text-sm rounded border hover:bg-gray-50">Cancel</button>
            <button type="button"
                    x-on:click="if (onConfirm) onConfirm(); open = false"
                    class="px-3 py-1.5 text-sm rounded bg-red-600 text-white hover:bg-red-700">Confirm</button>
        </div>
    </div>
</div>