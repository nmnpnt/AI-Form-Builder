<div wire:poll.2s="checkStatus" class="bg-white border rounded-lg p-4 space-y-3">
    <h3 class="text-sm font-semibold text-gray-700">
        {{ $form ? 'Ask AI to edit this form' : 'Generate a form with AI' }}
    </h3>

    <textarea wire:model="prompt" rows="3"
              placeholder="{{ $form
                  ? 'e.g. add an emergency contact section, make phone required, translate labels to Hindi'
                  : 'e.g. internship application with education history, skills, and resume upload' }}"
              class="w-full border rounded px-3 py-2 text-sm"></textarea>
    @error('prompt') <p class="text-xs text-red-500">{{ $message }}</p> @enderror

    <button wire:click="generate" wire:loading.attr="disabled"
            class="px-4 py-1.5 bg-indigo-600 text-white text-sm rounded disabled:opacity-50">
        {{ $form ? 'Apply edit' : 'Generate form' }}
    </button>

    @if ($status && $status !== 'succeeded')
        <p class="text-xs text-gray-500">
            Status: <span class="font-medium">{{ $status }}</span>
            @if (in_array($status, ['pending', 'processing']))
                — running as a background job, this can take a few seconds…
            @endif
        </p>
    @endif

    @if ($error)
        <p class="text-xs text-red-600">{{ $error }}</p>
    @endif
</div>
