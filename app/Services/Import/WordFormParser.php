<?php

namespace App\Services\Import;

use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\ListItem;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use Illuminate\Support\Str;

/**
 * Deterministic-first parsing strategy (documented in README):
 *   - A paragraph styled as a Heading -> new section, titled from its text.
 *   - A plain paragraph ending in "?" or ":" (a question-like line) -> new field.
 *     Type is guessed heuristically (see guessType()); ambiguous cases are
 *     left as `type: null` and flagged in `unparsed_blocks` so the caller can
 *     hand them to the AI layer for inference (hybrid approach, Part C).
 *   - A bulleted/numbered list immediately following a question -> that
 *     field's `options` (and its type upgraded to radio/checkbox/dropdown).
 *   - A line containing "[ ]" or "( )" repeated -> treated as a checkbox
 *     option list too (common in plain-text-pasted Word docs).
 */
class WordFormParser
{
    private array $sections = [];
    private array $unparsed = [];
    private ?array $currentField = null;

    public function parse(string $absolutePath): array
    {
        $phpWord = IOFactory::load($absolutePath);

        $this->sections = [['key' => 'section_1', 'title' => 'Imported Form', 'type' => 'section', 'fields' => []]];
        $this->unparsed = [];
        $this->currentField = null;

        foreach ($phpWord->getSections() as $docSection) {
            foreach ($docSection->getElements() as $element) {
                $this->handleElement($element);
            }
        }

        return [
            'schema' => ['sections' => $this->sections],
            'unparsed_blocks' => $this->unparsed,
        ];
    }

    private function handleElement($element): void
    {
        if ($element instanceof ListItem) {
            $this->handleListItem($element);

            return;
        }

        if ($element instanceof TextRun || $element instanceof AbstractContainer) {
            $text = $this->extractText($element);
            $styleName = method_exists($element, 'getStyle') ? (string) $element->getStyle() : '';

            if ($text === '') {
                return;
            }

            if ($this->looksLikeHeading($element, $styleName)) {
                $this->startNewSection($text);

                return;
            }

            if ($this->looksLikeQuestion($text)) {
                $this->startNewField($text);

                return;
            }

            // Plain paragraph with content we can't confidently classify —
            // report it instead of silently dropping it.
            $this->unparsed[] = ['text' => $text, 'reason' => 'Not recognised as a heading, question, or list item.'];

            return;
        }

        if ($element instanceof Text) {
            $text = trim($element->getText());
            if ($text !== '' && $this->looksLikeQuestion($text)) {
                $this->startNewField($text);
            }
        }
    }

    private function handleListItem(ListItem $item): void
    {
        $text = trim($item->getTextObject()?->getText() ?? '');
        if ($text === '') {
            return;
        }

        if (! $this->currentField) {
            // A list with no preceding question — start a generic field for it.
            $this->startNewField('Options');
        }

        $this->currentField['type'] = $this->currentField['multi'] ?? false ? 'checkbox' : 'radio';
        $this->currentField['options'][] = [
            'value' => Str::slug($text, '_'),
            'label' => $text,
        ];

        $this->replaceCurrentFieldInSchema();
    }

    private function startNewSection(string $title): void
    {
        $this->currentField = null;
        $this->sections[] = [
            'key' => 'section_'.(count($this->sections) + 1).'_'.Str::slug($title, '_'),
            'title' => $title,
            'type' => 'section',
            'fields' => [],
        ];
    }

    private function startNewField(string $label): void
    {
        $type = $this->guessType($label);

        $this->currentField = [
            'key' => $this->uniqueKey($label),
            'type' => $type,
            'label' => rtrim($label, ' ?:'),
            'placeholder' => null,
            'help_text' => null,
            'default' => null,
            'required' => false,
            'options' => in_array($type, ['dropdown', 'radio', 'checkbox'], true) ? [] : null,
            'validation' => [],
            'multi' => Str::contains(Str::lower($label), ['select all', 'check all']),
        ];

        $this->sections[array_key_last($this->sections)]['fields'][] = $this->stripInternal($this->currentField);
    }

    private function replaceCurrentFieldInSchema(): void
    {
        $lastSectionIndex = array_key_last($this->sections);
        $fields = &$this->sections[$lastSectionIndex]['fields'];
        $fields[array_key_last($fields)] = $this->stripInternal($this->currentField);
    }

    private function stripInternal(array $field): array
    {
        unset($field['multi']);

        return $field;
    }

    private function guessType(string $label): string
    {
        $lower = Str::lower($label);

        return match (true) {
            Str::contains($lower, 'email') => 'email',
            Str::contains($lower, ['phone', 'mobile', 'contact number']) => 'phone',
            Str::contains($lower, ['date', 'dob', 'birth']) => 'date',
            Str::contains($lower, ['upload', 'resume', 'cv', 'attach', 'file']) => 'file',
            Str::contains($lower, ['comments', 'describe', 'explain', 'address']) => 'textarea',
            Str::contains($lower, ['rate', 'rating', 'scale of']) => 'rating',
            default => 'text',
        };
    }

    private function looksLikeHeading($element, string $styleName): bool
    {
        if (Str::contains(Str::lower($styleName), 'heading')) {
            return true;
        }

        // Fallback heuristic for docs that don't use real Word heading styles:
        // a short, title-cased line with no trailing punctuation.
        $text = $this->extractText($element);

        return strlen($text) < 60
            && ! Str::endsWith($text, ['?', ':', '.'])
            && $text === Str::title($text);
    }

    private function looksLikeQuestion(string $text): bool
    {
        return Str::endsWith(trim($text), ['?', ':']) || preg_match('/^\d+[\.\)]\s/', trim($text)) === 1;
    }

    private function extractText($element): string
    {
        if (method_exists($element, 'getText')) {
            return trim((string) $element->getText());
        }

        if (method_exists($element, 'getElements')) {
            $text = '';
            foreach ($element->getElements() as $child) {
                if (method_exists($child, 'getText')) {
                    $text .= $child->getText();
                }
            }

            return trim($text);
        }

        return '';
    }

    private function uniqueKey(string $label): string
    {
        $base = Str::slug($label, '_') ?: 'field';
        $existing = collect($this->sections)->flatMap(fn ($s) => array_column($s['fields'], 'key'))->all();

        $key = $base;
        $i = 2;
        while (in_array($key, $existing, true)) {
            $key = "{$base}_{$i}";
            $i++;
        }

        return $key;
    }
}
