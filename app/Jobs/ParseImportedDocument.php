<?php

namespace App\Jobs;

use App\Models\FormImport;
use App\Services\Import\ExcelFormParser;
use App\Services\Import\ImportTypeInferencer;
use App\Services\Import\WordFormParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ParseImportedDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly int $importId) {}

    public function handle(WordFormParser $wordParser, ExcelFormParser $excelParser, ImportTypeInferencer $inferencer): void
    {
        $import = FormImport::findOrFail($this->importId);
        $import->update(['status' => 'processing']);

        try {
            $absolutePath = Storage::disk('local')->path($import->disk_path);

            $result = $import->file_type === 'docx'
                ? $wordParser->parse($absolutePath)
                : $excelParser->parse($absolutePath);

            // AI is only asked about the specifically ambiguous fields —
            // see ImportTypeInferencer for why.
            $inferred = $inferencer->inferTypes($result['unparsed_blocks'] ?? []);
            if ($inferred) {
                $result['schema'] = $this->applyInferredTypes($result['schema'], $inferred);
            }

            $import->update([
                'status' => 'needs_review',
                'draft_schema' => $result['schema'],
                'unparsed_blocks' => $result['unparsed_blocks'],
            ]);
        } catch (\Throwable $e) {
            $import->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }
    }

    private function applyInferredTypes(array $schema, array $inferredByLabel): array
    {
        foreach ($schema['sections'] as &$section) {
            foreach ($section['fields'] as &$field) {
                if (isset($inferredByLabel[$field['label']])) {
                    $field['type'] = $inferredByLabel[$field['label']]['type'];
                    $field['validation'] = $inferredByLabel[$field['label']]['validation'];
                }
            }
        }

        return $schema;
    }
}
