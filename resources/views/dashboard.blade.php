{{-- Uses Breeze's <x-app-layout> component (resources/views/layouts/app.blade.php,
     published by `php artisan breeze:install blade`). This file assumes Breeze
     (or an equivalent starter kit providing that component) is already installed —
     see README §1 "Auth". --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Your forms
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 space-y-6">
            <div class="flex justify-end">
                <a href="{{ route('forms.new') }}" class="px-3 py-1.5 bg-indigo-600 text-white text-sm rounded">+ New form</a>
            </div>

            <x-flash-message />

            <div class="bg-white border rounded-lg divide-y">
                @forelse ($forms as $form)
                    <div class="flex justify-between items-center px-4 py-3">
                        <div>
                            <p class="font-medium">{{ $form->title }}</p>
                            <p class="text-xs text-gray-400">
                                {{ ucfirst($form->status) }} · source: {{ $form->source }} · {{ $form->submissions()->count() }} submissions
                            </p>
                        </div>
                        <div class="text-sm space-x-3">
                            <a href="{{ route('forms.builder', $form) }}" class="text-indigo-600">Edit</a>
                            <a href="{{ route('forms.submissions', $form) }}" class="text-indigo-600">Submissions</a>
                            @if ($form->status === 'published')
                                <a href="{{ route('forms.fill', $form) }}" target="_blank" class="text-gray-500">View live</a>
                            @elseif ($form->current_version_id)
                                <form method="POST" action="{{ route('forms.publish', $form) }}" class="inline">
                                    @csrf
                                    <button class="text-green-600">Publish</button>
                                </form>
                            @else
                                <span class="text-gray-400 text-xs italic">not saved yet</span>
                            @endif
                            <form id="delete-form-{{ $form->id }}" method="POST" action="{{ route('forms.destroy', $form) }}" class="hidden">
                                @csrf
                                @method('DELETE')
                            </form>
                            <button type="button" class="text-red-500"
                                    onclick="window.dispatchEvent(new CustomEvent('confirm-action', { detail: {
                                        message: 'Delete this form? This cannot be undone from here.',
                                        onConfirm: () => document.getElementById('delete-form-{{ $form->id }}').submit()
                                    } }))">Delete</button>
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-6 text-sm text-gray-400 text-center">No forms yet — create one, generate one with AI, or import a document.</p>
                @endforelse
            </div>

            {{ $forms->links() }}
        </div>
    </div>
</x-app-layout>