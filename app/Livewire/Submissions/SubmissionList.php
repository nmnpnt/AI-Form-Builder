<?php

namespace App\Livewire\Submissions;

use App\Models\Form;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SubmissionList extends Component
{
    use WithPagination;

    public Form $form;

    #[Url(as: 'q')]
    public string $search = '';

    public function mount(Form $form): void
    {
        $this->form = $form;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function columns(): array
    {
        return collect($this->form->compiledSchema()['sections'] ?? [])
            ->flatMap(fn ($s) => $s['fields'])
            ->reject(fn ($f) => $f['type'] === 'section_heading')
            ->pluck('label', 'key')
            ->all();
    }

    public function render()
    {
        $submissions = $this->form->submissions()
            ->when($this->search, function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(function ($q) use ($term) {
                    $q->where('submitter_email', 'like', $term)
                        ->orWhereRaw('JSON_SEARCH(payload, "one", ?) IS NOT NULL', [$term]);
                });
            })
            ->latest()
            ->paginate(20);

        return view('livewire.submissions.submission-list', compact('submissions'));
    }
}
