<?php

namespace App\Jobs;

use App\Models\AiGenerationLog;
use App\Models\Form;
use App\Services\Ai\FormGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class EditFormWithAi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $formId,
        public readonly int $logId,
        public readonly ?int $userId = null,
    ) {}

    public function handle(FormGenerationService $service): void
    {
        $log = AiGenerationLog::findOrFail($this->logId);
        $log->update(['status' => 'processing']);

        try {
            $form = Form::findOrFail($this->formId);
            $currentSchema = $form->currentVersion?->schema ?? ['sections' => []];

            $result = $service->edit($currentSchema, $log->prompt);

            $form->publishVersion(
                $result['schema'],
                via: 'ai_edit',
                userId: $this->userId,
                summary: Str::limit($log->prompt, 120),
            );

            $log->update([
                'status' => 'succeeded',
                'input_schema' => $currentSchema,
                'model' => $result['model'],
                'prompt_tokens' => $result['prompt_tokens'],
                'completion_tokens' => $result['completion_tokens'],
                'latency_ms' => $result['latency_ms'],
                'output_schema' => $result['schema'],
            ]);

            // So a listening Livewire component (Builder) can refresh its
            // in-memory schema without a full page reload.
            event(new \App\Events\FormSchemaUpdated($form->id, $result['schema']));
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }
    }
}
