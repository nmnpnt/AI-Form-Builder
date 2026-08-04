<?php

namespace App\Livewire\FormBuilder;

use Livewire\Component;

/**
 * Nested component for the right-hand settings panel. It doesn't hold its
 * own copy of the schema — every change is dispatched straight back up to
 * the parent Builder's updateField(), so Builder::$schema stays the single
 * source of truth and the JSON editor never falls out of sync.
 */
class FieldSettings extends Component
{
    public string $sectionKey;
    public array $field;

    public function fieldMeta(): array
    {
        return config("formbuilder.field_types.{$this->field['type']}", []);
    }

    public function supports(string $rule): bool
    {
        return in_array($rule, $this->fieldMeta()['supports'] ?? [], true);
    }

    public function addOption(): void
    {
        $this->field['options'][] = [
            'value' => 'option_'.(count($this->field['options'] ?? []) + 1),
            'label' => 'Option '.(count($this->field['options'] ?? []) + 1),
        ];
        $this->push();
    }

    public function removeOption(int $index): void
    {
        unset($this->field['options'][$index]);
        $this->field['options'] = array_values($this->field['options']);
        $this->push();
    }

    /**
     * Push the full local field state up to the parent Builder, which is
     * listening for this event via #[On('field-settings-changed')] and
     * calls its own updateField() — keeping Builder::$schema authoritative.
     */
    public function push(): void
    {
        $this->dispatch('field-settings-changed', sectionKey: $this->sectionKey, fieldKey: $this->field['key'], changes: $this->field);
    }

    public function render()
    {
        return view('livewire.form-builder.field-settings');
    }
}
