@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto px-4 space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-xl font-semibold">Your forms</h1>
        <form method="POST" action="{{ route('forms.store') }}" class="flex gap-2">
            @csrf
            <input type="text" name="title" placeholder="New form title" class="border rounded px-3 py-1.5 text-sm">
            <button class="px-3 py-1.5 bg-indigo-600 text-white text-sm rounded">+ New form</button>
        </form>
    </div>

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
                    @else
                        <form method="POST" action="{{ route('forms.publish', $form) }}" class="inline">
                            @csrf
                            <button class="text-green-600">Publish</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <p class="px-4 py-6 text-sm text-gray-400 text-center">No forms yet — create one, generate one with AI, or import a document.</p>
        @endforelse
    </div>

    {{ $forms->links() }}
</div>
@endsection
