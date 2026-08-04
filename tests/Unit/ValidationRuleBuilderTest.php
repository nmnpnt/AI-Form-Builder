<?php

use App\Services\FormSchema\ValidationRuleBuilder;

beforeEach(function () {
    config()->set('formbuilder.field_types', [
        'text' => ['value_type' => 'string', 'supports' => ['required', 'min', 'max']],
        'email' => ['value_type' => 'string', 'supports' => ['required'], 'implicit_rules' => ['email']],
        'number' => ['value_type' => 'numeric', 'supports' => ['required', 'min', 'max']],
        'checkbox' => ['value_type' => 'array', 'supports' => ['required'], 'needs_options' => true],
        'section_heading' => ['value_type' => 'display_only', 'supports' => []],
    ]);

    $this->builder = new ValidationRuleBuilder;
});

it('marks required fields as required and others as nullable', function () {
    $rules = $this->builder->build([
        'sections' => [[
            'key' => 's1', 'fields' => [
                ['key' => 'name', 'type' => 'text', 'required' => true, 'validation' => []],
                ['key' => 'nickname', 'type' => 'text', 'required' => false, 'validation' => []],
            ],
        ]],
    ]);

    expect($rules['name'])->toContain('required')
        ->and($rules['nickname'])->toContain('nullable');
});

it('adds the implicit email rule for email fields', function () {
    $rules = $this->builder->build([
        'sections' => [[
            'key' => 's1', 'fields' => [
                ['key' => 'email', 'type' => 'email', 'required' => true, 'validation' => []],
            ],
        ]],
    ]);

    expect($rules['email'])->toContain('email');
});

it('skips section_heading display-only fields entirely', function () {
    $rules = $this->builder->build([
        'sections' => [[
            'key' => 's1', 'fields' => [
                ['key' => 'heading', 'type' => 'section_heading', 'label' => 'Intro', 'required' => false, 'validation' => []],
            ],
        ]],
    ]);

    expect($rules)->not->toHaveKey('heading');
});

it('applies min/max from the schema validation block', function () {
    $rules = $this->builder->build([
        'sections' => [[
            'key' => 's1', 'fields' => [
                ['key' => 'age', 'type' => 'number', 'required' => true, 'validation' => ['min' => 18, 'max' => 65]],
            ],
        ]],
    ]);

    expect($rules['age'])->toContain('min:18')->toContain('max:65');
});
