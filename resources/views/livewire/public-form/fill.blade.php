<div class="max-w-2xl mx-auto py-10 px-4">
    @if ($submitted)
        <div class="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
            <h2 class="text-lg font-semibold text-green-800">Thanks — your response was recorded.</h2>
        </div>
    @else
        <h1 class="text-2xl font-bold mb-1">{{ $form->title }}</h1>
        @if ($form->description)
            <p class="text-gray-500 mb-6">{{ $form->description }}</p>
        @endif

        @error('form')
            <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded p-3 mb-4">{{ $message }}</div>
        @enderror

        <form wire:submit="submit" class="space-y-8">
            @foreach ($this->schema()['sections'] as $section)
                <fieldset class="space-y-4 border-t pt-6 first:border-0 first:pt-0">
                    <legend class="text-lg font-semibold text-gray-800">{{ $section['title'] }}</legend>

                    @foreach ($section['fields'] as $field)
                        @php $key = $field['key']; @endphp

                        @if ($field['type'] === 'section_heading')
                            <h3 class="text-md font-medium text-gray-700 pt-2">{{ $field['label'] }}</h3>
                            @continue
                        @endif

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ $field['label'] }}
                                @if ($field['required'] ?? false)<span class="text-red-500">*</span>@endif
                            </label>

                            @switch($field['type'])
                                @case('textarea')
                                    <textarea wire:model="answers.{{ $key }}" rows="4"
                                              placeholder="{{ $field['placeholder'] }}"
                                              class="w-full border rounded px-3 py-2"></textarea>
                                    @break

                                @case('dropdown')
                                    <select wire:model="answers.{{ $key }}" class="w-full border rounded px-3 py-2">
                                        <option value="">Select…</option>
                                        @foreach ($field['options'] as $opt)
                                            <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                                        @endforeach
                                    </select>
                                    @break

                                @case('radio')
                                    <div class="space-y-1">
                                        @foreach ($field['options'] as $opt)
                                            <label class="flex items-center gap-2 text-sm">
                                                <input type="radio" wire:model="answers.{{ $key }}" value="{{ $opt['value'] }}">
                                                {{ $opt['label'] }}
                                            </label>
                                        @endforeach
                                    </div>
                                    @break

                                @case('checkbox')
                                    <div class="space-y-1">
                                        @foreach ($field['options'] as $opt)
                                            <label class="flex items-center gap-2 text-sm">
                                                <input type="checkbox" wire:model="answers.{{ $key }}" value="{{ $opt['value'] }}">
                                                {{ $opt['label'] }}
                                            </label>
                                        @endforeach
                                    </div>
                                    @break

                                @case('file')
                                    <input type="file" wire:model="answers.{{ $key }}" class="w-full text-sm">
                                    @break

                                @case('rating')
                                    <div class="flex gap-1">
                                        @for ($i = 1; $i <= ($field['validation']['max'] ?? 5); $i++)
                                            <button type="button" wire:click="$set('answers.{{ $key }}', {{ $i }})"
                                                    class="w-8 h-8 rounded {{ ($answers[$key] ?? 0) >= $i ? 'bg-yellow-400' : 'bg-gray-200' }}">
                                                {{ $i }}
                                            </button>
                                        @endfor
                                    </div>
                                    @break

                                @case('date')
                                    <input type="date" wire:model="answers.{{ $key }}" class="w-full border rounded px-3 py-2">
                                    @break

                                @case('number')
                                    <input type="number" wire:model="answers.{{ $key }}" class="w-full border rounded px-3 py-2">
                                    @break

                                @default
                                    <input type="{{ $field['type'] === 'email' ? 'email' : 'text' }}"
                                           wire:model="answers.{{ $key }}"
                                           placeholder="{{ $field['placeholder'] }}"
                                           class="w-full border rounded px-3 py-2">
                            @endswitch

                            @if ($field['help_text'])
                                <p class="text-xs text-gray-400 mt-1">{{ $field['help_text'] }}</p>
                            @endif
                            @error('answers.'.$key)
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </fieldset>
            @endforeach

            <button type="submit" class="w-full bg-indigo-600 text-white rounded py-2 font-medium">
                Submit
            </button>
        </form>
    @endif
</div>
