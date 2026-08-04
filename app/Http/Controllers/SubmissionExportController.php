<?php

namespace App\Http\Controllers;

use App\Models\Form;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionExportController extends Controller
{
    /**
     * Streamed CSV so a form with tens of thousands of submissions doesn't
     * get buffered into memory before it starts downloading.
     */
    public function __invoke(Form $form): StreamedResponse
    {
        $columns = collect($form->compiledSchema()['sections'] ?? [])
            ->flatMap(fn ($s) => $s['fields'])
            ->reject(fn ($f) => $f['type'] === 'section_heading')
            ->pluck('label', 'key');

        $filename = str($form->title)->slug().'-submissions-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($form, $columns) {
            $out = fopen('php://output', 'w');

            fputcsv($out, array_merge(['Submitted At', 'Submitter Email'], $columns->values()->all()));

            $form->submissions()->orderBy('created_at')->chunk(500, function ($chunk) use ($out, $columns) {
                foreach ($chunk as $submission) {
                    $row = [
                        $submission->created_at->toDateTimeString(),
                        $submission->submitter_email,
                    ];

                    foreach ($columns->keys() as $key) {
                        $value = $submission->value($key);
                        $row[] = is_array($value) ? implode('; ', $value) : $value;
                    }

                    fputcsv($out, $row);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
