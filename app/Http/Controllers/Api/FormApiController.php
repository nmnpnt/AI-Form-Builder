<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\Submission;
use App\Services\FormSchema\ValidationRuleBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormApiController extends Controller
{
    public function schema(Form $form): JsonResponse
    {
        abort_unless($form->status === 'published', 404);

        return response()->json([
            'form' => ['title' => $form->title, 'description' => $form->description],
            'schema' => $form->compiledSchema(),
        ]);
    }

    public function submissions(Form $form): JsonResponse
    {
        $this->authorizeApiAccess($form);

        return response()->json(
            $form->submissions()->latest()->paginate(50)
        );
    }

    public function store(Request $request, Form $form): JsonResponse
    {
        abort_unless($form->status === 'published' && $form->accepts_submissions, 404);
        abort_if($form->closes_at?->isPast(), 410, 'This form is no longer accepting responses.');
        abort_if($form->submission_limit && $form->submissions()->count() >= $form->submission_limit, 410, 'This form has reached its response limit.');

        $rules = app(ValidationRuleBuilder::class)->build($form->compiledSchema());
        $payload = $request->validate($rules);

        $submission = Submission::create([
            'form_id' => $form->id,
            'form_version_id' => $form->current_version_id,
            'payload' => $payload,
            'submitter_ip' => $request->ip(),
            'submitter_email' => $payload['email'] ?? null,
            'user_agent' => $request->userAgent(),
        ]);

        $this->fireWebhookIfConfigured($form, $submission);

        return response()->json(['id' => $submission->id, 'uuid' => $submission->uuid], 201);
    }

    /**
     * Minimal placeholder for the webhook differentiator: if a form's
     * tenant/settings define a webhook URL, POST the new submission to it.
     * Kept synchronous+best-effort here for brevity; in production this
     * should be its own queued job with retries/backoff.
     */
    private function fireWebhookIfConfigured(Form $form, Submission $submission): void
    {
        $webhookUrl = $form->tenant?->settings['webhook_url'] ?? null;

        if (! $webhookUrl) {
            return;
        }

        \Illuminate\Support\Facades\Http::timeout(5)->post($webhookUrl, [
            'event' => 'submission.created',
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'payload' => $submission->payload,
        ]);
    }

    private function authorizeApiAccess(Form $form): void
    {
        // TODO: replace with real per-form API token auth (see routes/api.php note).
        abort_unless($form->status === 'published', 404);
    }
}
