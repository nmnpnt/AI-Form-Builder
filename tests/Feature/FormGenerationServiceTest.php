<?php

use App\Services\Ai\FormGenerationService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('formbuilder.field_types', [
        'text' => ['value_type' => 'string', 'supports' => ['required']],
    ]);
    config()->set('formbuilder.ai.max_retries', 3);
});

it('retries and repairs when the model returns an invalid field type, then succeeds', function () {
    $badSchema = json_encode(['sections' => [[
        'key' => 's1', 'title' => 'S1', 'fields' => [
            ['key' => 'weird', 'type' => 'holographic_slider', 'label' => 'Weird'],
        ],
    ]]]);

    $goodSchema = json_encode(['sections' => [[
        'key' => 's1', 'title' => 'S1', 'fields' => [
            ['key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true],
        ],
    ]]]);

    Http::fake([
        '*' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => $badSchema]]], 'model' => 'gpt-4o-mini', 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20]])
            ->push(['choices' => [['message' => ['content' => $goodSchema]]], 'model' => 'gpt-4o-mini', 'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 8]]),
    ]);

    $service = app(FormGenerationService::class);
    $result = $service->generate('a simple contact form');

    expect($result['schema']['sections'][0]['fields'][0]['type'])->toBe('text')
        ->and($result['prompt_tokens'])->toBe(22)
        ->and($result['completion_tokens'])->toBe(28);

    Http::assertSentCount(2);
});

it('throws after exhausting retries if the model never returns a valid schema', function () {
    $badSchema = json_encode(['sections' => [[
        'key' => 's1', 'title' => 'S1', 'fields' => [
            ['key' => 'weird', 'type' => 'holographic_slider', 'label' => 'Weird'],
        ],
    ]]]);

    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => $badSchema]]], 'model' => 'gpt-4o-mini', 'usage' => []])]);

    $service = app(FormGenerationService::class);

    expect(fn () => $service->generate('a simple contact form'))
        ->toThrow(\RuntimeException::class);

    Http::assertSentCount(3); // config('formbuilder.ai.max_retries')
});
