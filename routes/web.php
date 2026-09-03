<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UniformityController;

Route::get('/', function () {
    return view('welcome');
});

// ==========================================================
// Slaughter House - Rekap Uniformity Mingguan
// ==========================================================
Route::prefix('slaughter/uniformity')->name('slaughter.uniformity.')->group(function () {
    // Halaman utama (blade view)
    Route::get('/', [UniformityController::class, 'index'])->name('index');

    // Upload file Excel (replace data minggu berjalan)
    Route::post('/upload', [UniformityController::class, 'upload'])->name('upload');

    // Ambil data untuk chart (bisa difilter ?region=...&plant=...)
    Route::get('/data', [UniformityController::class, 'data'])->name('data');

    // Ambil daftar region & plant untuk isi dropdown filter
    Route::get('/filter-options', [UniformityController::class, 'filterOptions'])->name('filter-options');
});