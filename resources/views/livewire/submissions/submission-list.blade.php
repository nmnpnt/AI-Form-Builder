<div class="max-w-6xl mx-auto py-8 px-4">
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-xl font-semibold">{{ $form->title }} — Submissions</h1>
        <div class="flex gap-2 items-center">
            <input type="text" wire:model.live.debounce.400ms="search"
                   placeholder="Search email or answer…"
                   class="border rounded px-3 py-1.5 text-sm w-64">
            <a href="{{ route('forms.submissions.export', $form) }}"
               class="text-sm px-3 py-1.5 bg-gray-800 text-white rounded">Export CSV</a>
        </div>
    </div>

    <div class="overflow-x-auto bg-white border rounded-lg">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-3 py-2">Submitted</th>
                    @foreach ($this->columns() as $key => $label)
                        <th class="px-3 py-2">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($submissions as $submission)
                    <tr wire:key="submission-{{ $submission->id }}">
                        <td class="px-3 py-2 whitespace-nowrap text-gray-500">{{ $submission->created_at->format('Y-m-d H:i') }}</td>
                        @foreach ($this->columns() as $key => $label)
                            <td class="px-3 py-2">
                                @php $v = $submission->value($key); @endphp
                                {{ is_array($v) ? implode(', ', $v) : $v }}
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($this->columns()) + 1 }}" class="px-3 py-6 text-center text-gray-400">No submissions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $submissions->links() }}</div>
</div>
