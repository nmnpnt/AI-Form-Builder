<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubmissionExportController;
use App\Livewire\AiGenerate\PromptGenerator;
use App\Livewire\FormBuilder\Builder;
use App\Livewire\Import\ImportWizard;
use App\Livewire\PublicForm\Fill;
use App\Livewire\Submissions\SubmissionList;
use App\Models\Form;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/f/{form:slug}', Fill::class)->name('forms.fill');

Route::get('/', function () {
    return Auth::check() ? redirect()->route('dashboard') : view('welcome');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        $forms = Form::query()
            ->where('owner_id', auth()->id())
            ->latest()
            ->paginate(15);

        return view('dashboard', compact('forms'));
    })->name('dashboard');

    Route::get('/forms/new', Builder::class)->name('forms.new');
    // Route::post('/forms', function () {
    //     $form = Form::create([
    //         'tenant_id' => auth()->user()->tenant_id,
    //         'owner_id' => auth()->id(),
    //         'title' => request('title', 'Untitled form'),
    //         'status' => 'draft',
    //         'source' => 'manual',
    //     ]);
    //     // No publishVersion() here on purpose — nothing counts as "saved"
    //     // until the user explicitly clicks Save in the builder.

    //     return redirect()->route('forms.builder', $form);
    // })->name('forms.store');

    Route::get('/forms/{form}/builder', Builder::class)->name('forms.builder');
    Route::get('/forms/{form}/submissions', SubmissionList::class)->name('forms.submissions');
    Route::get('/forms/{form}/submissions/export', SubmissionExportController::class)->name('forms.submissions.export');

    Route::post('/forms/{form}/publish', function (Form $form) {
        abort_if(! $form->current_version_id, 422, 'Save the form at least once before publishing it.');
        $form->update(['status' => 'published']);

        return back()->with('status', 'Form published.');
    })->name('forms.publish');

    Route::post('/forms/{form}/rollback/{version}', function (Form $form, \App\Models\FormVersion $version) {
        abort_unless($version->form_id === $form->id, 404);
        $form->rollbackTo($version, auth()->id());

        return redirect()->route('forms.builder', $form)->with('status', 'Rolled back.');
    })->name('forms.rollback');

    Route::delete('/forms/{form}', function (Form $form) {
        abort_unless($form->owner_id === auth()->id(), 403);
        $form->delete();

        return redirect()->route('dashboard')->with('status', 'Form deleted.');
    })->name('forms.destroy');

    Route::get('/generate', PromptGenerator::class)->name('forms.generate');
    Route::get('/import', ImportWizard::class)->name('forms.import');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';