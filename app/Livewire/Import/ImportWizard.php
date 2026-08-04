<?php

namespace App\Livewire\Import;

use App\Jobs\ParseImportedDocument;
use App\Models\Form;
use App\Models\FormImport;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Three-step flow required by the brief: upload -> queued parse -> preview
 * and mapping screen where the user can fix a wrongly detected field type
 * before anything is committed as a real form.
 */
class ImportWizard extends Component
{
    use WithFileUploads;

    #[Validate('required|file|mimes:docx,xlsx|max:20480')]
    public $file;

    public ?string $importUuid = null;
    public ?string $status = null;
    public array $draftSchema = [];
    public array $unparsedBlocks = [];
    public ?string $error = null;

    public function upload(): void
    {
        $this->validate();

        $extension = $this->file->getClientOriginalExtension();
        $path = $this->file->store('imports', 'local');

        $import = FormImport::create([
            'uploaded_by' => auth()->id(),
            'original_filename' => $this->file->getClientOriginalName(),
            'disk_path' => $path,
            'file_type' => $extension === 'docx' ? 'docx' : 'xlsx',
            'status' => 'queued',
        ]);

        ParseImportedDocument::dispatch($import->id);

        $this->importUuid = $import->uuid;
        $this->status = 'queued';
    }

    /** Polled via wire:poll while the import is queued/processing. */
    public function checkStatus(): void
    {
        if (! $this->importUuid) {
            return;
        }

        $import = FormImport::where('uuid', $this->importUuid)->first();
        if (! $import) {
            return;
        }

        $this->status = $import->status;

        if ($import->status === 'needs_review') {
            $this->draftSchema = $import->draft_schema;
            $this->unparsedBlocks = $import->unparsed_blocks ?? [];
        }

        if ($import->status === 'failed') {
            $this->error = $import->error;
        }
    }

    /** User fixes a wrongly-detected type on the mapping screen before committing. */
    public function updateFieldType(int $sectionIndex, int $fieldIndex, string $type): void
    {
        $this->draftSchema['sections'][$sectionIndex]['fields'][$fieldIndex]['type'] = $type;

        $needsOptions = config("formbuilder.field_types.{$type}.needs_options", false);
        if ($needsOptions && empty($this->draftSchema['sections'][$sectionIndex]['fields'][$fieldIndex]['options'])) {
            $this->draftSchema['sections'][$sectionIndex]['fields'][$fieldIndex]['options'] = [
                ['value' => 'option_1', 'label' => 'Option 1'],
            ];
        }
    }

    public function removeField(int $sectionIndex, int $fieldIndex): void
    {
        unset($this->draftSchema['sections'][$sectionIndex]['fields'][$fieldIndex]);
        $this->draftSchema['sections'][$sectionIndex]['fields'] =
            array_values($this->draftSchema['sections'][$sectionIndex]['fields']);
    }

    public function commit(): void
    {
        $import = FormImport::where('uuid', $this->importUuid)->firstOrFail();

        $form = Form::create([
            'tenant_id' => auth()->user()?->tenant_id,
            'owner_id' => auth()->id(),
            'title' => pathinfo($import->original_filename, PATHINFO_FILENAME),
            'status' => 'draft',
            'source' => $import->file_type === 'docx' ? 'imported_docx' : 'imported_xlsx',
        ]);

        $form->publishVersion(
            $this->draftSchema,
            via: $import->file_type === 'docx' ? 'import_docx' : 'import_xlsx',
            userId: auth()->id(),
            summary: "Imported from {$import->original_filename}",
        );

        $import->update(['status' => 'committed', 'form_id' => $form->id]);

        $this->redirectRoute('forms.builder', $form, navigate: true);
    }

    public function render()
    {
        return view('livewire.import.import-wizard');
    }
}
