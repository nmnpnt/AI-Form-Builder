<?php

namespace App\Services\Ai;

use App\Services\FormSchema\SchemaValidator;
use Illuminate\Support\Facades\Http;

/**
 * Prompt strategy (documented fully in README.md):
 *
 *  1. System prompt pins the exact JSON contract (field types allowed,
 *     required keys, nothing else in the response — no prose, no markdown
 *     fences) so parsing is a plain json_decode().
 *  2. We validate the response with SchemaValidator. If invalid (bad JSON,
 *     hallucinated field type, missing keys), we re-prompt once with the
 *     specific validation errors appended ("repair loop") before giving up.
 *  3. Never persist a broken schema — a failed repair bubbles up as an
 *     exception and the caller (the queued job) marks the log as failed
 *     rather than writing a form_version.
 */
class FormGenerationService
{
    public function __construct(private readonly SchemaValidator $validator) {}

    /** @return array{schema: array, model: string, prompt_tokens: int, completion_tokens: int, latency_ms: int} */
    public function generate(string $prompt): array
    {
        return $this->callWithRepair($this->systemPromptForCreate(), $prompt);
    }

    /** @return array{schema: array, model: string, prompt_tokens: int, completion_tokens: int, latency_ms: int} */
    public function edit(array $existingSchema, string $instruction): array
    {
        $userMessage = "Current form schema (JSON):\n".json_encode($existingSchema)
            ."\n\nInstruction: {$instruction}\n\n"
            .'Return the COMPLETE updated schema (not just the diff), following the same JSON contract.';

        return $this->callWithRepair($this->systemPromptForEdit(), $userMessage);
    }

    private function callWithRepair(string $systemPrompt, string $userMessage): array
    {
        $maxRetries = (int) config('formbuilder.ai.max_retries', 3);
        $lastErrors = [];
        $totals = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'latency_ms' => 0];

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ];

            if ($lastErrors) {
                $messages[] = [
                    'role' => 'user',
                    'content' => 'Your previous response was invalid: '.implode(' | ', $lastErrors)
                        .' Return ONLY corrected JSON, no explanation.',
                ];
            }

            $result = $this->callModel($messages);
            $totals['prompt_tokens'] += $result['prompt_tokens'];
            $totals['completion_tokens'] += $result['completion_tokens'];
            $totals['latency_ms'] += $result['latency_ms'];

            $decoded = json_decode($this->stripCodeFences($result['content']), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $lastErrors = ['Response was not valid JSON: '.json_last_error_msg()];

                continue;
            }

            $validation = $this->validator->validate($decoded);

            if ($validation['valid']) {
                return array_merge($totals, ['schema' => $decoded, 'model' => $result['model']]);
            }

            $lastErrors = $validation['errors'];
        }

        throw new \RuntimeException(
            "AI generation failed schema validation after {$maxRetries} attempts: ".implode(' | ', $lastErrors)
        );
    }

    /** @return array{content: string, model: string, prompt_tokens: int, completion_tokens: int, latency_ms: int} */
    private function callModel(array $messages): array
    {
        $start = microtime(true);

        $response = Http::withToken(config('formbuilder.ai.api_key'))
            ->timeout((int) config('formbuilder.ai.timeout', 60))
            ->post(config('formbuilder.ai.api_url'), [
                'model' => config('formbuilder.ai.model'),
                'messages' => $messages,
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
            ])
            ->throw()
            ->json();

        return [
            'content' => $response['choices'][0]['message']['content'] ?? '',
            'model' => $response['model'] ?? config('formbuilder.ai.model'),
            'prompt_tokens' => $response['usage']['prompt_tokens'] ?? 0,
            'completion_tokens' => $response['usage']['completion_tokens'] ?? 0,
            'latency_ms' => (int) ((microtime(true) - $start) * 1000),
        ];
    }

    private function stripCodeFences(string $content): string
    {
        return trim(preg_replace('/^```(json)?|```$/m', '', trim($content)));
    }

    private function systemPromptForCreate(): string
    {
        $types = implode(', ', array_keys(config('formbuilder.field_types')));

        return <<<PROMPT
You are a form-schema generator. Given a natural-language description of a
form, output ONLY a single JSON object (no prose, no markdown fences)
matching exactly this contract:

{
  "sections": [
    {
      "key": "snake_case_unique_key",
      "title": "Human readable title",
      "type": "section",
      "fields": [
        {
          "key": "snake_case_unique_field_key",
          "type": "one of: {$types}",
          "label": "Human readable label",
          "placeholder": "string or null",
          "help_text": "string or null",
          "default": null,
          "required": true,
          "options": [{"value": "opt_1", "label": "Option 1"}] ,
          "validation": {"min": null, "max": null, "regex": null, "file_types": null, "max_size_kb": null}
        }
      ]
    }
  ]
}

Rules:
- Every field "key" must be unique across the whole form, snake_case, starting with a letter.
- Only use field types from the allowed list above — never invent a new type.
- "options" is required (non-empty) for dropdown, radio, and checkbox fields, and must be null otherwise.
- Pick sensible validation for the field's real-world meaning (e.g. email fields need no extra regex, phone numbers get a permissive pattern, file uploads get realistic file_types and max_size_kb).
- Group logically related fields into sections with clear titles.
- Return ONLY the JSON object. No commentary, no markdown code fences.
PROMPT;
    }

    private function systemPromptForEdit(): string
    {
        return $this->systemPromptForCreate()
            ."\n\nYou will be given an existing schema and an instruction describing a change "
            .'(e.g. add a section, change a field to required, translate labels to another language, '
            .'remove a field). Apply the instruction and return the COMPLETE resulting schema in the '
            .'same JSON contract — never a partial diff.';
    }
}
