<?php

use App\Services\FormSchema\SchemaValidator;

beforeEach(function () {
    config()->set('formbuilder.field_types', [
        'text' => ['value_type' => 'string', 'supports' => ['required', 'min', 'max']],
        'dropdown' => ['value_type' => 'string', 'supports' => ['required'], 'needs_options' => true],
        'checkbox' => ['value_type' => 'array', 'supports' => ['required'], 'needs_options' => true],
    ]);

    $this->validator = new SchemaValidator;
});

it('accepts a minimal valid schema', function () {
    $result = $this->validator->validate([
        'sections' => [[
            'key' => 'section_1',
            'title' => 'Section 1',
            'fields' => [[
                'key' => 'full_name',
                'type' => 'text',
                'label' => 'Full name',
                'required' => true,
            ]],
        ]],
    ]);

    expect($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBeEmpty();
});

it('rejects a schema with no sections key', function () {
    $result = $this->validator->validate(['foo' => 'bar']);

    expect($result['valid'])->toBeFalse();
});

it('rejects a hallucinated / unknown field type', function () {
    $result = $this->validator->validate([
        'sections' => [[
            'key' => 's1', 'title' => 'S1', 'fields' => [[
                'key' => 'weird', 'type' => 'holographic_slider', 'label' => 'Weird',
            ]],
        ]],
    ]);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'][0])->toContain('not a recognised field type');
});

it('rejects duplicate field keys across the whole form', function () {
    $result = $this->validator->validate([
        'sections' => [
            ['key' => 's1', 'title' => 'S1', 'fields' => [
                ['key' => 'email', 'type' => 'text', 'label' => 'Email'],
            ]],
            ['key' => 's2', 'title' => 'S2', 'fields' => [
                ['key' => 'email', 'type' => 'text', 'label' => 'Email again'],
            ]],
        ],
    ]);

    expect($result['valid'])->toBeFalse()
        ->and(collect($result['errors'])->some(fn ($e) => str_contains($e, 'duplicated')))->toBeTrue();
});

it('requires non-empty options for dropdown/checkbox fields', function () {
    $result = $this->validator->validate([
        'sections' => [[
            'key' => 's1', 'title' => 'S1', 'fields' => [[
                'key' => 'dept', 'type' => 'dropdown', 'label' => 'Department', 'options' => [],
            ]],
        ]],
    ]);

    expect($result['valid'])->toBeFalse();
});

it('rejects validation rules not applicable to the field type', function () {
    $result = $this->validator->validate([
        'sections' => [[
            'key' => 's1', 'title' => 'S1', 'fields' => [[
                'key' => 'agree', 'type' => 'checkbox', 'label' => 'Agree', 'options' => [
                    ['value' => 'yes', 'label' => 'Yes'],
                ],
                'validation' => ['min' => 5], // "min" isn't in checkbox's supported list above
            ]],
        ]],
    ]);

    expect($result['valid'])->toBeFalse();
});

it('throws InvalidSchemaException from validateOrFail on an invalid schema', function () {
    expect(fn () => $this->validator->validateOrFail(['sections' => []]))
        ->toThrow(\App\Exceptions\InvalidSchemaException::class);
});
