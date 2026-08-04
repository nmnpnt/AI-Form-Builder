<div class="grid grid-cols-12 gap-4 h-full" x-data="{ showJson: false }">

    {{-- Field palette: click-to-add --}}
    <aside class="col-span-2 bg-white rounded-lg border p-3 space-y-1 h-fit sticky top-4">
        <h3 class="text-xs font-semibold text-gray-500 uppercase mb-2">Add field</h3>
        <p class="text-xs text-gray-400 mb-2">
            Adds to: <span class="font-medium text-gray-600">
                {{ collect($schema['sections'])->firstWhere('key', $activeSectionKey)['title'] ?? 'first section' }}
            </span>
        </p>
        @foreach($this->fieldTypes() as $type => $meta)
            <button
                type="button"
                draggable="true"
                data-field-type="{{ $type }}"
                @dragstart="$event.dataTransfer.setData('field-type', '{{ $type }}')"
                wire:click="addField('{{ $activeSectionKey }}', '{{ $type }}')"
                class="w-full text-left text-sm px-2 py-1.5 rounded hover:bg-indigo-50 hover:text-indigo-700 cursor-grab"
            >
                {{ \Illuminate\Support\Str::headline($type) }}
            </button>
        @endforeach
    </aside>

    {{-- Canvas --}}
    <main class="col-span-7 space-y-4">
        <div class="flex justify-between items-center">
            <h2 class="text-lg font-semibold">{{ $form->title }}</h2>
            <div class="space-x-2">
                <button wire:click="addSection('section')" class="text-sm px-3 py-1.5 border rounded">+ Section</button>
                <button wire:click="addSection('step')" class="text-sm px-3 py-1.5 border rounded">+ Step</button>
                <button @click="showJson = !showJson" class="text-sm px-3 py-1.5 border rounded">Toggle JSON</button>
                <button wire:click="save" class="text-sm px-3 py-1.5 bg-indigo-600 text-white rounded">Save</button>
            </div>
        </div>

        @if (session('status'))
            <div class="text-sm text-green-700 bg-green-50 border border-green-200 rounded px-3 py-2">{{ session('status') }}</div>
        @endif

        @foreach($schema['sections'] as $section)
            <div class="bg-white rounded-lg border p-4 {{ $activeSectionKey === $section['key'] ? 'ring-1 ring-indigo-300' : '' }}"
                 x-data
                 wire:click="setActiveSection('{{ $section['key'] }}')"
                 @drop.prevent="$wire.addField('{{ $section['key'] }}', $event.dataTransfer.getData('field-type'))"
                 @dragover.prevent>
                <div class="flex justify-between items-center mb-3">
                    <input type="text" wire:model.blur="schema.sections.{{ $loop->index }}.title"
                           class="font-medium text-gray-800 border-0 focus:ring-0 p-0 bg-transparent" />
                    <span class="text-xs text-gray-400 uppercase">{{ $section['type'] }}</span>
                </div>

                <div class="space-y-2 min-h-[60px]" data-sortable-section="{{ $section['key'] }}">
                    @forelse($section['fields'] as $field)
                        <div wire:key="field-{{ $field['key'] }}" data-field-key="{{ $field['key'] }}"
                             wire:click="$set('selectedFieldKey', '{{ $field['key'] }}')"
                             class="flex items-center justify-between border rounded px-3 py-2 cursor-pointer
                                    {{ $selectedFieldKey === $field['key'] ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200' }}">
                            <div class="flex items-center gap-2">
                                <span data-drag-handle class="cursor-grab text-gray-300 select-none">⠿</span>
                                <div>
                                    <span class="text-sm font-medium">{{ $field['label'] }}</span>
                                    <span class="text-xs text-gray-400 ml-2">{{ $field['type'] }}@if($field['required'] ?? false) · required @endif</span>
                                </div>
                            </div>
                            <div class="space-x-2 text-xs">
                                <button wire:click.stop="duplicateField('{{ $section['key'] }}', '{{ $field['key'] }}')" class="text-gray-500 hover:text-indigo-600">Duplicate</button>
                                <button wire:click.stop="deleteField('{{ $section['key'] }}', '{{ $field['key'] }}')" class="text-gray-500 hover:text-red-600">Delete</button>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400 italic">Drag a field here, or click one on the left.</p>
                    @endforelse
                </div>
            </div>
        @endforeach

        @if ($showJson)
            <div class="bg-white rounded-lg border p-4">
                <div class="flex justify-between items-center mb-2">
                    <h3 class="text-sm font-semibold">Raw JSON schema</h3>
                    <button wire:click="syncFromJson" class="text-xs px-2 py-1 bg-gray-800 text-white rounded">Apply JSON &rarr; Canvas</button>
                </div>
                <textarea wire:model="schemaJson" rows="16"
                          class="w-full font-mono text-xs border rounded p-2"></textarea>
                @if (count($jsonErrors))
                    <ul class="mt-2 text-xs text-red-600 list-disc pl-4">
                        @foreach ($jsonErrors as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        <div class="bg-white rounded-lg border p-4">
            <h3 class="text-sm font-semibold mb-2">Version history</h3>
            <div class="space-y-1 max-h-48 overflow-y-auto">
                @foreach ($form->versions as $version)
                    <div class="flex justify-between items-center text-xs px-2 py-1.5 rounded {{ $version->id === $form->current_version_id ? 'bg-indigo-50' : '' }}">
                        <span>
                            v{{ $version->version_number }}
                            <span class="text-gray-400">· {{ $version->created_via }}</span>
                            @if ($version->id === $form->current_version_id)
                                <span class="text-indigo-600 font-medium">(current)</span>
                            @endif
                            @if ($version->change_summary)
                                <span class="text-gray-400">— {{ $version->change_summary }}</span>
                            @endif
                        </span>
                        @if ($version->id !== $form->current_version_id)
                            <form method="POST" action="{{ route('forms.rollback', [$form, $version]) }}">
                                @csrf
                                <button class="text-indigo-600">Roll back to this</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </main>

    {{-- Field settings panel --}}
    <aside class="col-span-3 space-y-4">
        <div class="bg-white rounded-lg border p-4 h-fit">
            @php
                $selected = collect($schema['sections'])
                    ->flatMap(fn($s) => collect($s['fields'])->map(fn($f) => array_merge($f, ['_section' => $s['key']])))
                    ->firstWhere('key', $selectedFieldKey);
            @endphp

            @if ($selected)
                @livewire('form-builder.field-settings', ['sectionKey' => $selected['_section'], 'field' => $selected], key('settings-'.$selected['key']))
            @else
                <p class="text-sm text-gray-400">Select a field to edit its settings.</p>
            @endif
        </div>

        @livewire('ai-generate.prompt-generator', ['form' => $form], key('ai-editor-'.$form->id))
    </aside>
</div>
