<?php

use App\Http\Controllers\SubmissionExportController;
use App\Livewire\AiGenerate\PromptGenerator;
use App\Livewire\FormBuilder\Builder;
use App\Livewire\Import\ImportWizard;
use App\Livewire\PublicForm\Fill;
use App\Livewire\Submissions\SubmissionList;
use App\Models\Form;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------
// Public, unauthenticated: the fill page every form gets.
// ---------------------------------------------------------------------
Route::get('/f/{form:slug}', Fill::class)->name('forms.fill');

// ---------------------------------------------------------------------
// Marketing/landing.
// ---------------------------------------------------------------------
Route::get('/', function () {
    return Auth::check() ? redirect()->route('dashboard') : view('welcome');
})->name('home');

// ---------------------------------------------------------------------
// Authenticated app. (Breeze/Jetstream auth scaffolding assumed — see
// README "Auth" section; swap the `auth` middleware group for whatever
// starter kit you install.)
// ---------------------------------------------------------------------
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        $forms = Form::query()
            ->where('owner_id', auth()->id())
            ->latest()
            ->paginate(15);

        return view('dashboard', compact('forms'));
    })->name('dashboard');

    Route::post('/forms', function () {
        $form = Form::create([
            'tenant_id' => auth()->user()->tenant_id,
            'owner_id' => auth()->id(),
            'title' => request('title', 'Untitled form'),
            'status' => 'draft',
            'source' => 'manual',
        ]);
        $form->publishVersion(['sections' => [
            ['key' => 'section_1', 'title' => 'Section 1', 'type' => 'section', 'fields' => []],
        ]], via: 'manual', userId: auth()->id());

        return redirect()->route('forms.builder', $form);
    })->name('forms.store');

    Route::get('/forms/{form}/builder', Builder::class)->name('forms.builder');
    Route::get('/forms/{form}/submissions', SubmissionList::class)->name('forms.submissions');
    Route::get('/forms/{form}/submissions/export', SubmissionExportController::class)->name('forms.submissions.export');

    Route::post('/forms/{form}/publish', function (Form $form) {
        $form->update(['status' => 'published']);

        return back()->with('status', 'Form published.');
    })->name('forms.publish');

    Route::post('/forms/{form}/rollback/{version}', function (Form $form, \App\Models\FormVersion $version) {
        abort_unless($version->form_id === $form->id, 404);
        $form->rollbackTo($version, auth()->id());

        return redirect()->route('forms.builder', $form)->with('status', 'Rolled back.');
    })->name('forms.rollback');

    Route::get('/generate', PromptGenerator::class)->name('forms.generate');
    Route::get('/import', ImportWizard::class)->name('forms.import');
});
