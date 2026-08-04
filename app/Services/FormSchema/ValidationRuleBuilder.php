<?php

namespace App\Services\FormSchema;

/**
 * Turns a form's JSON schema into a real Laravel validation rule array so
 * that submissions are validated server-side from the exact same source of
 * truth that renders the public form — the browser's HTML5 `required` /
 * `pattern` attributes are UX sugar only, never trusted for persistence.
 */
class ValidationRuleBuilder
{
    /** @return array<string, array<int, string>> */
    public function build(array $schema): array
    {
        $rules = [];

        foreach ($schema['sections'] ?? [] as $section) {
            foreach ($section['fields'] ?? [] as $field) {
                if (($field['type'] ?? null) === 'section_heading') {
                    continue; // display-only, never part of the payload
                }

                $rules[$field['key']] = $this->rulesForField($field);

                // Each selected checkbox value must be one of the field's
                // defined options — without this, `array` alone would accept
                // any submitted values, not just the ones offered.
                if ($field['type'] === 'checkbox' && ! empty($field['options'])) {
                    $rules[$field['key'].'.*'] = ['in:'.implode(',', array_column($field['options'], 'value'))];
                }
            }
        }

        return $rules;
    }

    private function rulesForField(array $field): array
    {
        $type = $field['type'];
        $meta = config("formbuilder.field_types.{$type}", []);
        $validation = $field['validation'] ?? [];
        $rules = [];

        $rules[] = ($field['required'] ?? false) ? 'required' : 'nullable';

        $rules = array_merge($rules, match ($meta['value_type'] ?? 'string') {
            'numeric' => ['numeric'],
            'date' => ['date'],
            'array' => ['array'],
            'file' => ['file'],
            default => ['string'],
        });

        foreach ($meta['implicit_rules'] ?? [] as $implicit) {
            $rules[] = $implicit;
        }

        if (($field['type'] ?? null) === 'phone') {
            // A conservative, widely-compatible phone pattern rather than a
            // strict E.164-only rule — international formats vary too much
            // to hard-code without false-rejecting real numbers.
            $rules[] = 'regex:/^[0-9+\-\s()]{6,20}$/';
        }

        if (! empty($validation['min'])) {
            $rules[] = "min:{$validation['min']}";
        }
        if (! empty($validation['max'])) {
            $rules[] = "max:{$validation['max']}";
        }
        if (! empty($validation['regex'])) {
            $rules[] = 'regex:'.$validation['regex'];
        }

        if (($field['type'] ?? null) === 'file') {
            if (! empty($validation['file_types'])) {
                $rules[] = 'mimes:'.implode(',', (array) $validation['file_types']);
            }
            if (! empty($validation['max_size_kb'])) {
                $rules[] = "max:{$validation['max_size_kb']}";
            }
        }

        if (in_array($field['type'] ?? null, ['dropdown', 'radio'], true) && ! empty($field['options'])) {
            $rules[] = 'in:'.implode(',', array_column($field['options'], 'value'));
        }

        if (($field['type'] ?? null) === 'checkbox' && ! empty($field['options'])) {
            // each selected value must be one of the allowed options
            $rules[] = 'array';
        }

        return $rules;
    }
}
