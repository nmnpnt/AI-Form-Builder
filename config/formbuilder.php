<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Field Type Registry
    |--------------------------------------------------------------------------
    |
    | Single source of truth for every field type the builder, the schema
    | validator, the AI prompt contract, and the Word/Excel importers all
    | agree on. Add a field type here once and it shows up everywhere —
    | this is what keeps "10+ field types" from becoming 10 special cases
    | scattered across the codebase.
    |
    | `value_type` drives how the dynamic Laravel validation rule builder
    | (App\Services\FormSchema\ValidationRuleBuilder) treats the field.
    | `supports` lists which validation knobs are legal for this type,
    | so the schema validator can reject nonsensical combinations
    | (e.g. a `min`/`max` length rule on a checkbox).
    |
    */
    'field_types' => [
        'text' => ['value_type' => 'string', 'supports' => ['required', 'min', 'max', 'regex']],
        'textarea' => ['value_type' => 'string', 'supports' => ['required', 'min', 'max']],
        'number' => ['value_type' => 'numeric', 'supports' => ['required', 'min', 'max']],
        'email' => ['value_type' => 'string', 'supports' => ['required'], 'implicit_rules' => ['email']],
        'phone' => ['value_type' => 'string', 'supports' => ['required', 'regex']],
        'date' => ['value_type' => 'date', 'supports' => ['required', 'min', 'max']],
        'dropdown' => ['value_type' => 'string', 'supports' => ['required'], 'needs_options' => true],
        'radio' => ['value_type' => 'string', 'supports' => ['required'], 'needs_options' => true],
        'checkbox' => ['value_type' => 'array', 'supports' => ['required', 'min', 'max'], 'needs_options' => true],
        'file' => ['value_type' => 'file', 'supports' => ['required', 'file_types', 'max_size_kb']],
        'rating' => ['value_type' => 'numeric', 'supports' => ['required', 'min', 'max'], 'default_max' => 5],
        'section_heading' => ['value_type' => 'display_only', 'supports' => []],
        'url' => ['value_type' => 'string', 'supports' => ['required'], 'implicit_rules' => ['url']],
    ],

    // How a step/section groups fields together in the JSON schema.
    'grouping' => ['section', 'step'],

    'schema_cache_ttl' => env('FORM_BUILDER_SCHEMA_CACHE_TTL', 3600),
    'max_upload_mb' => env('FORM_BUILDER_MAX_UPLOAD_MB', 15),

    // "max,decayMinutes" — Part D spam/abuse protection on public submit.
    'submission_rate_limit' => env('FORM_SUBMISSION_RATE_LIMIT', '30,1'),

    /*
    |--------------------------------------------------------------------------
    | AI provider (Part B)
    |--------------------------------------------------------------------------
    | Any OpenAI-chat-completions-compatible endpoint works unmodified
    | (OpenAI, Groq, OpenRouter, a local Ollama server with the OpenAI
    | shim). Swap via .env only — no code changes.
    */
    'ai' => [
        'api_url' => env('AI_API_URL', 'https://api.openai.com/v1/chat/completions'),
        'api_key' => env('AI_API_KEY'),
        'model' => env('AI_MODEL', 'gpt-4o-mini'),
        'max_retries' => env('AI_MAX_RETRIES', 3),
        'timeout' => env('AI_TIMEOUT', 60),
    ],
];
