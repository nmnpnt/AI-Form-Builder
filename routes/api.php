<?php

use App\Http\Controllers\Api\FormApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Submissions API (Part D differentiator)
|--------------------------------------------------------------------------
| Authenticated with a per-form API token (forms.api_token, not modelled in
| the migrations above for brevity — add a nullable `api_token` string
| column to `forms` if you take this further) so external systems can pull
| a form's schema or push/read submissions without a browser session.
*/
Route::middleware(['throttle:60,1'])->prefix('api/v1')->group(function () {
    Route::get('/forms/{form:slug}/schema', [FormApiController::class, 'schema'])->name('api.forms.schema');
    Route::get('/forms/{form:slug}/submissions', [FormApiController::class, 'submissions'])->name('api.forms.submissions');
    Route::post('/forms/{form:slug}/submissions', [FormApiController::class, 'store'])->name('api.forms.submissions.store');
});
