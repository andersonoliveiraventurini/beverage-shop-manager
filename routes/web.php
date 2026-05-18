<?php

use Illuminate\Support\Facades\Route;

// FA Distribuidora is an internal admin tool — there is no public landing
// page in MVP scope. The root path forwards to the Filament panel so the
// default Laravel welcome view does not leak the framework or violate the
// design system (NFR-01).
Route::redirect('/', '/admin');
