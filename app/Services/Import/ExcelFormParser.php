<?php

namespace App\Services\Import;

use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Supports two documented layouts (per the brief: "support at least one
 * clearly documented layout, and ideally a plain header-row sheet too"):
 *
 * Layout A — "Field Spec" sheet (recommended, most information-preserving):
 *   Columns: Label | Type | Required | Options | Help Text
 *   One row per field. `Type` should be one of the field_types config keys;
 *   if blank or unrecognised, we fall back to a text guess (see WordFormParser
 *   for the same heuristics — reused here) and flag it in unparsed_blocks
 *   so the AI layer / preview screen can resolve it.
 *   `Options` is a single cell, values separated by "|" (e.g. "Yes|No|Maybe").
 *
 * Layout B — Plain header row:
 *   Row 1 is just column headers with no Type/Required/Options columns at
 *   all (e.g. a spreadsheet someone already uses to collect data). Each
 *   header becomes a single text field; type is guessed from the header
 *   name using the same heuristics as Layout A's fallback.
 *
 * Layout is auto-detected: if the header row contains a column literally
 * named "Type" (case-insensitive), we treat it as Layout A; otherwise B.
 */
class ExcelFormParser
{
    public function parse(string $absolutePath): array
    {
        $spreadsheet = IOFactory::load($absolutePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false); // plain 0-indexed rows/columns throughout

        if (empty($rows)) {
            return ['schema' => ['sections' => []], 'unparsed_blocks' => [['reason' => 'Sheet is empty.']]];
        }

        $header = array_map(fn ($v) => Str::lower(trim((string) $v)), array_shift($rows));

        return $this->isFieldSpecLayout($header)
            ? $this->parseFieldSpecLayout($header, $rows)
            : $this->parseHeaderRowLayout($header);
    }

    private function isFieldSpecLayout(array $header): bool
    {
        return in_array('type', $header, true) && (in_array('label', $header, true) || in_array('question', $header, true));
    }

    private function parseFieldSpecLayout(array $header, array $rows): array
    {
        $col = fn (string $name) => array_search($name, $header, true);

        $firstOf = fn (...$candidates) => collect($candidates)->first(fn ($c) => $c !== false, false);

        $labelCol = $firstOf($col('label'), $col('question'));
        $typeCol = $col('type');
        $requiredCol = $col('required');
        $optionsCol = $col('options');
        $helpCol = $firstOf($col('help text'), $col('help'));

        $fields = [];
        $unparsed = [];
        $knownTypes = array_keys(config('formbuilder.field_types'));

        foreach (array_values($rows) as $row) {
            $values = array_values($row);
            $label = trim((string) ($values[$labelCol] ?? ''));

            if ($label === '') {
                continue;
            }

            $rawType = Str::lower(trim((string) ($values[$typeCol] ?? '')));
            $type = in_array($rawType, $knownTypes, true) ? $rawType : $this->guessTypeFromLabel($label);

            if ($rawType !== '' && ! in_array($rawType, $knownTypes, true)) {
                $unparsed[] = ['text' => $label, 'reason' => "Unrecognised type \"{$rawType}\" — guessed \"{$type}\" instead."];
            }

            $optionsRaw = trim((string) ($values[$optionsCol] ?? ''));
            $options = $optionsRaw !== ''
                ? collect(explode('|', $optionsRaw))->map(fn ($o) => trim($o))->filter()->map(
                    fn ($o) => ['value' => Str::slug($o, '_'), 'label' => $o]
                )->values()->all()
                : (in_array($type, ['dropdown', 'radio', 'checkbox'], true) ? [] : null);

            $fields[] = [
                'key' => $this->uniqueKey($label, $fields),
                'type' => $type,
                'label' => $label,
                'placeholder' => null,
                'help_text' => trim((string) ($values[$helpCol] ?? '')) ?: null,
                'default' => null,
                'required' => $this->truthy($values[$requiredCol] ?? null),
                'options' => $options,
                'validation' => [],
            ];
        }

        return [
            'schema' => ['sections' => [['key' => 'section_1', 'title' => 'Imported Form', 'type' => 'section', 'fields' => $fields]]],
            'unparsed_blocks' => $unparsed,
        ];
    }

    private function parseHeaderRowLayout(array $header): array
    {
        $fields = [];

        foreach ($header as $columnHeader) {
            $label = trim((string) $columnHeader);
            if ($label === '') {
                continue;
            }

            $type = $this->guessTypeFromLabel($label);

            $fields[] = [
                'key' => $this->uniqueKey($label, $fields),
                'type' => $type,
                'label' => Str::headline($label),
                'placeholder' => null,
                'help_text' => null,
                'default' => null,
                'required' => false,
                'options' => in_array($type, ['dropdown', 'radio', 'checkbox'], true) ? [] : null,
                'validation' => [],
            ];
        }

        return [
            'schema' => ['sections' => [['key' => 'section_1', 'title' => 'Imported Form', 'type' => 'section', 'fields' => $fields]]],
            'unparsed_blocks' => [[
                'reason' => 'Detected a plain header-row sheet (no Type/Required/Options columns) — '
                    .'all fields defaulted to guessed types; please review on the mapping screen.',
            ]],
        ];
    }

    private function guessTypeFromLabel(string $label): string
    {
        $lower = Str::lower($label);

        return match (true) {
            Str::contains($lower, 'email') => 'email',
            Str::contains($lower, ['phone', 'mobile']) => 'phone',
            Str::contains($lower, ['date', 'dob', 'birth']) => 'date',
            Str::contains($lower, ['upload', 'resume', 'cv', 'file']) => 'file',
            Str::contains($lower, ['comments', 'description', 'address', 'notes']) => 'textarea',
            default => 'text',
        };
    }

    private function truthy($value): bool
    {
        return in_array(Str::lower(trim((string) $value)), ['yes', 'y', 'true', '1', 'required'], true);
    }

    private function uniqueKey(string $label, array $existingFields): string
    {
        $base = Str::slug($label, '_') ?: 'field';
        $existingKeys = array_column($existingFields, 'key');

        $key = $base;
        $i = 2;
        while (in_array($key, $existingKeys, true)) {
            $key = "{$base}_{$i}";
            $i++;
        }

        return $key;
    }
}
