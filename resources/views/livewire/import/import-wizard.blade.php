<div class="max-w-3xl mx-auto py-8 px-4 space-y-6">
    <h1 class="text-xl font-semibold">Import a Word or Excel form</h1>

    @if (! $importUuid)
        <div class="bg-white border rounded-lg p-6 space-y-3">
            <input type="file" wire:model="file" accept=".docx,.xlsx" class="text-sm">
            @error('file') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
            <button wire:click="submitImport" wire:loading.attr="disabled"
                    class="px-4 py-1.5 bg-indigo-600 text-white text-sm rounded">
                Upload &amp; parse
            </button>
            <p class="text-xs text-gray-400">
                .docx: headings become sections, question-like lines become fields, lists become options.<br>
                .xlsx: a "Label / Type / Required / Options" sheet, or a plain header-row sheet.
            </p>
        </div>
    @elseif ($status !== 'needs_review' && $status !== 'failed')
        <div wire:poll.2s="checkStatus" class="bg-white border rounded-lg p-6 text-sm text-gray-600">
            Status: <span class="font-medium">{{ $status }}</span> — parsing runs as a background job for large files.
        </div>
    @elseif ($status === 'failed')
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700">
            Import failed: {{ $error }}
        </div>
    @else
        {{-- Preview & mapping screen --}}
        @if (count($unparsedBlocks))
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-amber-800">
                <p class="font-medium mb-1">{{ count($unparsedBlocks) }} block(s) needed a closer look:</p>
                <ul class="list-disc pl-5 space-y-0.5">
                    @foreach ($unparsedBlocks as $block)
                        <li>{{ $block['text'] ?? '' }} — {{ $block['reason'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @foreach ($draftSchema['sections'] ?? [] as $si => $section)
            <div class="bg-white border rounded-lg p-4">
                <h3 class="font-medium text-gray-800 mb-3">{{ $section['title'] }}</h3>
                <div class="space-y-2">
                    @foreach ($section['fields'] as $fi => $field)
                        <div class="flex items-center justify-between border rounded px-3 py-2">
                            <div>
                                <p class="text-sm font-medium">{{ $field['label'] }}</p>
                                <p class="text-xs text-gray-400">key: {{ $field['key'] }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <select wire:change="updateFieldType({{ $si }}, {{ $fi }}, $event.target.value)"
                                        class="text-xs border rounded px-2 py-1">
                                    @foreach (array_keys(config('formbuilder.field_types')) as $type)
                                        <option value="{{ $type }}" @selected($field['type'] === $type)>{{ $type }}</option>
                                    @endforeach
                                </select>
                                <button wire:click="removeField({{ $si }}, {{ $fi }})" class="text-xs text-red-500">Remove</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <button wire:click="commitImport" class="px-4 py-2 bg-green-600 text-white text-sm rounded">
            Looks good — create the form
        </button>
    @endif
</div>
