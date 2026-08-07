<?php

namespace App\Livewire\FormBuilder;

use App\Models\Form;
use App\Services\FormSchema\SchemaValidator;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The canvas + settings panel + raw JSON editor, kept in two-way sync per
 * the brief. `schema` (the PHP array) is the single in-memory source of
 * truth for this component; the JSON editor textarea is just a serialised
 * view of it. Edits from either side funnel through setSchema()/syncFromJson()
 * so they can never drift apart.
 */
class Builder extends Component
{
    public ?Form $form = null;
    public ?string $newFormTitle = null;
    public array $schema = ['sections' => []];
    public string $schemaJson = '';
    public ?string $selectedFieldKey = null;
    public ?string $activeSectionKey = null;
    public bool $showJson = false;
    public array $jsonErrors = [];

    public function mount(?Form $form = null): void
    {
        $this->form = $form;
        $this->schema = $form?->currentVersion?->schema ?? ['sections' => [
            ['key' => 'section_1', 'title' => 'Section 1', 'type' => 'section', 'fields' => []],
        ]];
        $this->newFormTitle = $form?->title ?? 'Untitled form';
        $this->activeSectionKey = $this->schema['sections'][0]['key'] ?? null;
        $this->syncJsonFromSchema();
    }
    public function fieldTypes(): array
    {
        return config('formbuilder.field_types');
    }

    /** Livewire lifecycle hook: fires whenever $schema changes via wire:model
     *  (e.g. the section title input), not just via our own mutator methods —
     *  keeps the raw JSON editor from silently going stale. */
    public function updatedSchema(): void
    {
        $this->syncJsonFromSchema();
    }

    // ---- Canvas actions -------------------------------------------------

    public function addField(string $sectionKey, string $type): void
    {
        $section = $this->findSection($sectionKey);
        if (! $section) {
            return;
        }

        $this->activeSectionKey = $sectionKey;
        $key = $this->uniqueFieldKey($type);

        $this->mutateSection($sectionKey, function (&$section) use ($key, $type) {
            $section['fields'][] = [
                'key' => $key,
                'type' => $type,
                'label' => Str::headline($type).' field',
                'placeholder' => null,
                'help_text' => null,
                'default' => null,
                'required' => false,
                'options' => (config("formbuilder.field_types.{$type}.needs_options") ?? false)
                    ? [['value' => 'option_1', 'label' => 'Option 1']]
                    : null,
                'validation' => [],
            ];
        });

        $this->selectedFieldKey = $key;
        $this->syncJsonFromSchema();
    }

    public function duplicateField(string $sectionKey, string $fieldKey): void
    {
        $this->mutateSection($sectionKey, function (&$section) use ($fieldKey) {
            foreach ($section['fields'] as $field) {
                if ($field['key'] === $fieldKey) {
                    $copy = $field;
                    $copy['key'] = $this->uniqueFieldKey($field['type']);
                    $copy['label'] = $field['label'].' (copy)';
                    $section['fields'][] = $copy;
                    break;
                }
            }
        });
        $this->syncJsonFromSchema();
    }

    public function deleteField(string $sectionKey, string $fieldKey): void
    {
        $this->mutateSection($sectionKey, function (&$section) use ($fieldKey) {
            $section['fields'] = array_values(array_filter(
                $section['fields'],
                fn ($f) => $f['key'] !== $fieldKey
            ));
        });
        if ($this->selectedFieldKey === $fieldKey) {
            $this->selectedFieldKey = null;
        }
        $this->syncJsonFromSchema();
    }

    /** Called by the SortableJS `end` event via wire:sort or a JS bridge. */
    public function reorderFields(string $sectionKey, array $orderedKeys): void
    {
        $this->mutateSection($sectionKey, function (&$section) use ($orderedKeys) {
            $byKey = collect($section['fields'])->keyBy('key');
            $section['fields'] = collect($orderedKeys)
                ->map(fn ($key) => $byKey->get($key))
                ->filter()
                ->values()
                ->all();
        });
        $this->syncJsonFromSchema();
    }

    public function addSection(string $type = 'section'): void
    {
        $key = 'section_'.(count($this->schema['sections']) + 1).'_'.Str::random(4);
        $this->schema['sections'][] = [
            'key' => $key,
            'title' => 'New '.($type === 'step' ? 'Step' : 'Section'),
            'type' => $type,
            'fields' => [],
        ];
        $this->activeSectionKey = $key;
        $this->syncJsonFromSchema();
    }

    public function setActiveSection(string $sectionKey): void
    {
        $this->activeSectionKey = $sectionKey;
    }

    /** Inline field-settings update from the settings panel (label, required, validation, etc). */
    public function updateField(string $sectionKey, string $fieldKey, array $changes): void
    {
        $this->mutateSection($sectionKey, function (&$section) use ($fieldKey, $changes) {
            foreach ($section['fields'] as &$field) {
                if ($field['key'] === $fieldKey) {
                    $field = array_replace_recursive($field, $changes);
                    break;
                }
            }
        });
        $this->syncJsonFromSchema();
    }

    // ---- JSON editor two-way sync ---------------------------------------

    public function syncFromJson(): void
    {
        $decoded = json_decode($this->schemaJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->jsonErrors = ['Invalid JSON: '.json_last_error_msg()];

            return;
        }

        $result = app(SchemaValidator::class)->validate($decoded);
        $this->jsonErrors = $result['errors'];

        if ($result['valid']) {
            $this->schema = $decoded;
        }
    }

    private function syncJsonFromSchema(): void
    {
        $this->schemaJson = json_encode($this->schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->jsonErrors = [];
    }

    // ---- Save ------------------------------------------------------------

    public function save(): void
    {
        $result = app(SchemaValidator::class)->validate($this->schema);

        if (! $result['valid']) {
            $this->jsonErrors = $result['errors'];
            $this->addError('schema', 'Fix the schema errors before saving.');

            return;
        }

        if (! $this->form) {
            $this->form = Form::create([
                'tenant_id' => auth()->user()?->tenant_id,
                'owner_id' => auth()->id(),
                'title' => $this->newFormTitle ?: 'Untitled form',
                'status' => 'draft',
                'source' => 'manual',
            ]);
            $this->form->publishVersion($this->schema, via: 'manual', userId: auth()->id());

            session()->flash('status', 'Form saved.');
            $this->redirectRoute('forms.builder', $this->form, navigate: true);

            return;
        }

        $this->form->publishVersion(
            $this->schema,
            via: 'manual',
            userId: auth()->id(),
        );

        $this->dispatch('form-saved');
        session()->flash('status', 'Form saved.');
    }

    public function updatedNewFormTitle(): void
    {
        if ($this->form) {
            $this->form->update(['title' => $this->newFormTitle]);
        }
    }

    #[On('ai-schema-updated')]
    public function onAiSchemaUpdated(array $schema): void
    {
        $this->schema = $schema;
        $this->syncJsonFromSchema();
    }

    #[On('field-settings-changed')]
    public function onFieldSettingsChanged(string $sectionKey, string $fieldKey, array $changes): void
    {
        $this->mutateSection($sectionKey, function (&$section) use ($fieldKey, $changes) {
            foreach ($section['fields'] as &$field) {
                if ($field['key'] === $fieldKey) {
                    $field = $changes;
                    break;
                }
            }
        });
        $this->syncJsonFromSchema();
    }

    // ---- helpers -----------------------------------------------------

    private function findSection(string $sectionKey): ?array
    {
        foreach ($this->schema['sections'] as $section) {
            if ($section['key'] === $sectionKey) {
                return $section;
            }
        }

        return null;
    }

    private function mutateSection(string $sectionKey, callable $mutator): void
    {
        foreach ($this->schema['sections'] as &$section) {
            if ($section['key'] === $sectionKey) {
                $mutator($section);
                break;
            }
        }
    }

    private function uniqueFieldKey(string $type): string
    {
        $existing = collect($this->schema['sections'])
            ->flatMap(fn ($s) => array_column($s['fields'], 'key'))
            ->all();

        $i = 1;
        do {
            $candidate = Str::snake($type).'_'.$i;
            $i++;
        } while (in_array($candidate, $existing, true));

        return $candidate;
    }

    public function render()
    {
        return view('livewire.form-builder.builder');
    }

        public function removeSection(string $sectionKey): void
    {
        if (count($this->schema['sections']) <= 1) {
            $this->addError('schema', 'A form needs at least one section.');
            return;
        }

        $this->schema['sections'] = array_values(array_filter(
            $this->schema['sections'],
            fn ($s) => $s['key'] !== $sectionKey
        ));

        if ($this->activeSectionKey === $sectionKey) {
            $this->activeSectionKey = $this->schema['sections'][0]['key'] ?? null;
        }

        if ($this->selectedFieldKey && ! collect($this->schema['sections'])
            ->flatMap(fn ($s) => array_column($s['fields'], 'key'))
            ->contains($this->selectedFieldKey)) {
            $this->selectedFieldKey = null;
        }

        $this->syncJsonFromSchema();
    }
}
