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

/**
 * "Don't block a web request on a long LLM call" — the Livewire component
 * dispatches this job and immediately returns a log UUID the frontend polls
 * (or receives over a broadcast event) for status.
 */
class GenerateFormFromPrompt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // retries are handled inside FormGenerationService's repair loop, not at the queue level

    public function __construct(
        public readonly int $logId,
        public readonly ?int $ownerId = null,
        public readonly ?int $tenantId = null,
    ) {}

    public function handle(FormGenerationService $service): void
    {
        $log = AiGenerationLog::findOrFail($this->logId);
        $log->update(['status' => 'processing']);

        try {
            $result = $service->generate($log->prompt);

            $form = Form::create([
                'tenant_id' => $this->tenantId,
                'owner_id' => $this->ownerId,
                'title' => $this->deriveTitle($log->prompt),
                'status' => 'draft',
                'source' => 'ai_generated',
            ]);

            $form->publishVersion($result['schema'], via: 'ai_generate', userId: $this->ownerId, summary: 'Generated from prompt');

            $log->update([
                'status' => 'succeeded',
                'form_id' => $form->id,
                'model' => $result['model'],
                'prompt_tokens' => $result['prompt_tokens'],
                'completion_tokens' => $result['completion_tokens'],
                'latency_ms' => $result['latency_ms'],
                'output_schema' => $result['schema'],
            ]);
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }
    }

    private function deriveTitle(string $prompt): string
    {
        return Str::limit(Str::headline(Str::words($prompt, 6, '')), 60, '');
    }
}
