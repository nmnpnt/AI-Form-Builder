<div class="space-y-3 text-sm">
    <h3 class="font-semibold text-gray-800">{{ \Illuminate\Support\Str::headline($field['type']) }} field</h3>

    <div>
        <label class="block text-xs text-gray-500 mb-1">Label</label>
        <input type="text" wire:model.blur="field.label" wire:change="push"
               class="w-full border rounded px-2 py-1 text-sm">
    </div>

    <div>
        <label class="block text-xs text-gray-500 mb-1">Key</label>
        <input type="text" wire:model.blur="field.key"
               class="w-full border rounded px-2 py-1 text-sm font-mono text-xs bg-gray-50" readonly>
        <p class="text-xs text-gray-400 mt-1">Stable identifier used in submissions &amp; validation.</p>
    </div>

    <div>
        <label class="block text-xs text-gray-500 mb-1">Placeholder</label>
        <input type="text" wire:model.blur="field.placeholder" wire:change="push"
               class="w-full border rounded px-2 py-1 text-sm">
    </div>

    <div>
        <label class="block text-xs text-gray-500 mb-1">Help text</label>
        <textarea wire:model.blur="field.help_text" wire:change="push" rows="2"
                  class="w-full border rounded px-2 py-1 text-sm"></textarea>
    </div>

    <label class="flex items-center gap-2">
        <input type="checkbox" wire:model="field.required" wire:change="push">
        <span class="text-xs text-gray-600">Required</span>
    </label>

    @if (in_array($field['type'], ['dropdown', 'radio', 'checkbox']))
        <div>
            <label class="block text-xs text-gray-500 mb-1">Options</label>
            @foreach ($field['options'] ?? [] as $i => $option)
                <div class="flex gap-1 mb-1">
                    <input type="text" wire:model.blur="field.options.{{ $i }}.label" wire:change="push"
                           placeholder="Label" class="w-1/2 border rounded px-2 py-1 text-xs">
                    <input type="text" wire:model.blur="field.options.{{ $i }}.value" wire:change="push"
                           placeholder="Value" class="w-1/2 border rounded px-2 py-1 text-xs">
                    <button wire:click="removeOption({{ $i }})" class="text-red-500 text-xs">&times;</button>
                </div>
            @endforeach
            <button wire:click="addOption" class="text-xs text-indigo-600 mt-1">+ Add option</button>
        </div>
    @endif

    @if ($this->supports('min') || $this->supports('max'))
        <div class="grid grid-cols-2 gap-2">
            @if ($this->supports('min'))
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Min</label>
                    <input type="number" wire:model.blur="field.validation.min" wire:change="push"
                           class="w-full border rounded px-2 py-1 text-sm">
                </div>
            @endif
            @if ($this->supports('max'))
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Max</label>
                    <input type="number" wire:model.blur="field.validation.max" wire:change="push"
                           class="w-full border rounded px-2 py-1 text-sm">
                </div>
            @endif
        </div>
    @endif

    @if ($this->supports('regex'))
        <div>
            <label class="block text-xs text-gray-500 mb-1">Regex pattern</label>
            <input type="text" wire:model.blur="field.validation.regex" wire:change="push"
                   class="w-full border rounded px-2 py-1 text-sm font-mono text-xs">
        </div>
    @endif

    @if ($field['type'] === 'file')
        <div>
            <label class="block text-xs text-gray-500 mb-1">Allowed types (comma-separated)</label>
            <input type="text" wire:model.blur="field.validation.file_types" wire:change="push"
                   placeholder="pdf,doc,docx" class="w-full border rounded px-2 py-1 text-sm">
            <label class="block text-xs text-gray-500 mt-2 mb-1">Max size (KB)</label>
            <input type="number" wire:model.blur="field.validation.max_size_kb" wire:change="push"
                   class="w-full border rounded px-2 py-1 text-sm">
        </div>
    @endif

    <div>
        <label class="block text-xs text-gray-500 mb-1">Default value</label>
        <input type="text" wire:model.blur="field.default" wire:change="push"
               class="w-full border rounded px-2 py-1 text-sm">
    </div>
</div>
