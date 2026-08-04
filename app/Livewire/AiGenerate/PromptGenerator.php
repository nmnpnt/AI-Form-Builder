<?php

namespace App\Livewire\AiGenerate;

use App\Jobs\EditFormWithAi;
use App\Jobs\GenerateFormFromPrompt;
use App\Models\AiGenerationLog;
use App\Models\Form;
use Livewire\Component;

/**
 * Two modes:
 *  - Standalone (no $form bound): "create a new form from a prompt" —
 *    dispatched from the dashboard, redirects to the builder once done.
 *  - Bound to an existing Form: "edit this form" ("add an emergency contact
 *    section", "make phone required", "translate labels to Hindi") —
 *    dispatches App\Livewire\FormBuilder\Builder's ai-schema-updated event
 *    once the job finishes so the canvas refreshes without a reload.
 */
class PromptGenerator extends Component
{
    public ?Form $form = null;
    public string $prompt = '';
    public ?string $activeLogUuid = null;
    public ?string $status = null;
    public ?string $error = null;

    public function mount(?Form $form = null): void
    {
        $this->form = $form;
    }

    public function generate(): void
    {
        $this->validate(['prompt' => 'required|string|min:5|max:2000']);

        $log = AiGenerationLog::create([
            'type' => $this->form ? 'edit' : 'generate',
            'prompt' => $this->prompt,
            'form_id' => $this->form?->id,
            'requested_by' => auth()->id(),
            'status' => 'pending',
        ]);

        if ($this->form) {
            EditFormWithAi::dispatch($this->form->id, $log->id, auth()->id());
        } else {
            GenerateFormFromPrompt::dispatch($log->id, auth()->id(), auth()->user()?->tenant_id);
        }

        $this->activeLogUuid = $log->uuid;
        $this->status = 'pending';
        $this->prompt = '';
    }

    /** Polled from the Blade view via wire:poll while a job is in flight. */
    public function checkStatus(): void
    {
        if (! $this->activeLogUuid) {
            return;
        }

        $log = AiGenerationLog::where('uuid', $this->activeLogUuid)->first();

        if (! $log) {
            return;
        }

        $this->status = $log->status;

        if ($log->status === 'succeeded') {
            if ($this->form) {
                $this->dispatch('ai-schema-updated', schema: $log->output_schema)->to(\App\Livewire\FormBuilder\Builder::class);
            } else {
                $this->redirectRoute('forms.builder', $log->form, navigate: true);
            }
        }

        if ($log->status === 'failed') {
            $this->error = $log->error;
        }
    }

    public function render()
    {
        return view('livewire.ai-generate.prompt-generator');
    }
}
