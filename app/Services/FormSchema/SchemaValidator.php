<?php

namespace App\Services\FormSchema;

use App\Exceptions\InvalidSchemaException;

/**
 * Canonical schema shape (also documented in README.md):
 *
 * {
 *   "sections": [
 *     {
 *       "key": "personal_info",
 *       "title": "Personal Information",
 *       "type": "section",            // "section" | "step"
 *       "fields": [
 *         {
 *           "key": "full_name",         // unique, snake_case, stable identifier
 *           "type": "text",             // must exist in config('formbuilder.field_types')
 *           "label": "Full name",
 *           "placeholder": null,
 *           "help_text": null,
 *           "default": null,
 *           "required": true,
 *           "options": null,            // [{ "value": "...", "label": "..." }, ...] for dropdown/radio/checkbox
 *           "validation": {
 *             "min": null, "max": null, "regex": null,
 *             "file_types": null, "max_size_kb": null
 *           }
 *         }
 *       ]
 *     }
 *   ]
 * }
 *
 * This class is the ONLY place that decides whether a schema is valid. The
 * builder's raw JSON editor, the AI generation/edit pipeline, and the
 * Word/Excel importer's "commit" step all call validateOrFail() before
 * anything is persisted — "never persist a broken schema" per the brief.
 */
class SchemaValidator
{
    /** @return array{valid: bool, errors: string[]} */
    public function validate(array $schema): array
    {
        $errors = [];
        $knownTypes = array_keys(config('formbuilder.field_types'));
        $seenKeys = [];

        if (! array_key_exists('sections', $schema) || ! is_array($schema['sections'])) {
            return ['valid' => false, 'errors' => ['Schema must have a top-level "sections" array.']];
        }

        if (count($schema['sections']) === 0) {
            $errors[] = 'Schema must contain at least one section.';
        }

        foreach ($schema['sections'] as $si => $section) {
            $sectionLabel = "sections[{$si}]";

            foreach (['key', 'title', 'fields'] as $required) {
                if (! array_key_exists($required, $section)) {
                    $errors[] = "{$sectionLabel} is missing required key \"{$required}\".";
                }
            }

            if (! is_array($section['fields'] ?? null)) {
                $errors[] = "{$sectionLabel}.fields must be an array.";

                continue;
            }

            foreach ($section['fields'] as $fi => $field) {
                $fieldLabel = "{$sectionLabel}.fields[{$fi}]";
                $errors = array_merge($errors, $this->validateField($field, $fieldLabel, $knownTypes, $seenKeys));
            }
        }

        return ['valid' => count($errors) === 0, 'errors' => $errors];
    }

    public function validateOrFail(array $schema): void
    {
        $result = $this->validate($schema);

        if (! $result['valid']) {
            throw new InvalidSchemaException($result['errors']);
        }
    }

    private function validateField(mixed $field, string $label, array $knownTypes, array &$seenKeys): array
    {
        $errors = [];

        if (! is_array($field)) {
            return ["{$label} must be an object."];
        }

        foreach (['key', 'type', 'label'] as $required) {
            $value = $field[$required] ?? null;
            if ($value === null || $value === '') {
                $errors[] = "{$label} is missing required key \"{$required}\".";
            }
        }

        $key = $field['key'] ?? null;
        if ($key) {
            if (! preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
                $errors[] = "{$label}.key \"{$key}\" must be snake_case, starting with a letter.";
            }
            if (in_array($key, $seenKeys, true)) {
                $errors[] = "{$label}.key \"{$key}\" is duplicated — field keys must be unique per form.";
            }
            $seenKeys[] = $key;
        }

        $type = $field['type'] ?? null;
        if ($type && ! in_array($type, $knownTypes, true)) {
            // This is exactly the "hallucinated field type" case called out in
            // the brief for AI generation — surfaced as a validation error so
            // the caller can trigger its repair/retry loop rather than saving it.
            $errors[] = "{$label}.type \"{$type}\" is not a recognised field type.";

            return $errors; // can't validate type-specific rules below
        }

        $meta = config("formbuilder.field_types.{$type}", []);

        if (($meta['needs_options'] ?? false) && empty($field['options'])) {
            $errors[] = "{$label} of type \"{$type}\" requires a non-empty \"options\" array.";
        }

        if (isset($field['options']) && is_array($field['options'])) {
            foreach ($field['options'] as $oi => $opt) {
                if (! isset($opt['value']) || ! isset($opt['label'])) {
                    $errors[] = "{$label}.options[{$oi}] must have both \"value\" and \"label\".";
                }
            }
        }

        if (isset($field['validation']) && is_array($field['validation'])) {
            $allowed = $meta['supports'] ?? [];
            foreach (array_keys($field['validation']) as $rule) {
                if ($field['validation'][$rule] !== null && ! in_array($rule, $allowed, true)) {
                    $errors[] = "{$label}.validation.\"{$rule}\" is not applicable to field type \"{$type}\".";
                }
            }
        }

        return $errors;
    }
}
