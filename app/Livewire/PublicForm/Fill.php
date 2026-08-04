<?php

namespace App\Livewire\PublicForm;

use App\Models\Form;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Services\FormSchema\ValidationRuleBuilder;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The publicly-reachable "fill this form" page (routes/web.php: /f/{slug}).
 * Renders purely from Form::compiledSchema() and validates purely through
 * ValidationRuleBuilder — this component has no knowledge of specific field
 * types beyond generic Blade `@switch`, so adding a new field type never
 * requires touching this file.
 */
class Fill extends Component
{
    use WithFileUploads;

    public Form $form;
    public array $answers = [];
    public bool $submitted = false;

    public function mount(Form $form): void
    {
        abort_unless($form->status === 'published' && $form->accepts_submissions, 404);

        if ($form->closes_at && $form->closes_at->isPast()) {
            abort(410, 'This form is no longer accepting responses.');
        }

        if ($form->submission_limit && $form->submissions()->count() >= $form->submission_limit) {
            abort(410, 'This form has reached its response limit.');
        }

        $this->form = $form;

        foreach ($this->schema()['sections'] as $section) {
            foreach ($section['fields'] as $field) {
                $this->answers[$field['key']] = $field['default'] ?? ($field['type'] === 'checkbox' ? [] : null);
            }
        }
    }

    public function schema(): array
    {
        return $this->form->compiledSchema();
    }

    public function submit(): void
    {
        // Basic abuse protection (Part D: rate limiting & spam protection).
        $key = 'form-submit:'.$this->form->id.':'.request()->ip();
        [$max, $decaySeconds] = array_pad(explode(',', config('formbuilder.submission_rate_limit', '30,1')), 2, 1);

        if (RateLimiter::tooManyAttempts($key, (int) $max)) {
            $this->addError('form', 'Too many submissions — please try again in a minute.');

            return;
        }
        RateLimiter::hit($key, 60 * (int) $decaySeconds);

        $schema = $this->schema();
        $rules = app(ValidationRuleBuilder::class)->build($schema);

        // Prefix with "answers." so Livewire's error bag lines up with the
        // wire:model="answers.{key}" bindings used in the Blade view.
        $prefixedRules = collect($rules)->mapWithKeys(fn ($r, $key) => ["answers.{$key}" => $r])->all();

        $this->validate($prefixedRules); // throws + surfaces errors to the Blade view automatically

        $fileFields = collect($schema['sections'])
            ->flatMap(fn ($s) => $s['fields'])
            ->where('type', 'file')
            ->pluck('key');

        $payload = collect($this->answers)->except($fileFields)->all();

        $submission = Submission::create([
            'form_id' => $this->form->id,
            'form_version_id' => $this->form->current_version_id,
            'payload' => $payload,
            'submitter_ip' => request()->ip(),
            'submitter_email' => $payload['email'] ?? null,
            'user_agent' => request()->userAgent(),
        ]);

        foreach ($fileFields as $key) {
            $file = $this->answers[$key] ?? null;
            if (! $file) {
                continue;
            }

            $path = $file->store("submissions/{$this->form->id}", 'local');

            SubmissionFile::create([
                'submission_id' => $submission->id,
                'field_key' => $key,
                'original_name' => $file->getClientOriginalName(),
                'disk_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ]);
        }

        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.public-form.fill');
    }
}
