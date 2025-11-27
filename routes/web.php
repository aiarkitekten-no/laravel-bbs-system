<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('terminal');
});

Route::get('/terminal', function () {
    return view('terminal');
})->name('terminal');

// Legacy welcome page (for testing)
Route::get('/welcome', function () {
    return view('welcome');
});
