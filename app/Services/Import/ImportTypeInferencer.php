<?php

namespace App\Services\Import;

use App\Services\Ai\FormGenerationService;

/**
 * The hybrid split (documented in README):
 *   - Deterministic parsing (WordFormParser / ExcelFormParser) ALWAYS runs
 *     first and does the structural work — sections, field boundaries,
 *     option lists. It's fast, free, and fully explainable.
 *   - AI is only invoked for the specific fields the deterministic pass
 *     couldn't confidently type (i.e. entries present in `unparsed_blocks`),
 *     asking it to infer just {type, validation} for those labels — not to
 *     regenerate the whole form from scratch. This keeps AI cost and
 *     hallucination risk proportional to actual ambiguity.
 */
class ImportTypeInferencer
{
    public function __construct(private readonly FormGenerationService $ai) {}

    /**
     * @param  array<int, array{text: string, reason: string}>  $ambiguous
     * @return array<string, array{type: string, validation: array}> keyed by label
     */
    public function inferTypes(array $ambiguous): array
    {
        if (empty($ambiguous)) {
            return [];
        }

        $labels = array_column($ambiguous, 'text');
        $prompt = 'For each of these form field labels, infer the single best field type and any '
            .'sensible validation. Labels: '.json_encode($labels);

        // Reuses the same generate() contract but wrapped so a full-form
        // response is coerced into a flat label => {type, validation} map;
        // if the model still can't produce something valid we simply leave
        // those fields as "text" (the safe, always-valid fallback) rather
        // than blocking the import.
        try {
            $result = $this->ai->generate(
                "Return a JSON object of the form {\"sections\":[{\"key\":\"inferred\",\"title\":\"Inferred\",\"type\":\"section\",\"fields\":[...]}]} ".
                "where each field's label is one of these questions and nothing else: {$prompt}"
            );

            $inferred = [];
            foreach ($result['schema']['sections'][0]['fields'] ?? [] as $field) {
                $inferred[$field['label']] = [
                    'type' => $field['type'],
                    'validation' => $field['validation'] ?? [],
                ];
            }

            return $inferred;
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }
}
