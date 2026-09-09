<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUniformityBatchesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel ini menyimpan data MENTAH per baris/rit dari file Excel yang
     * diupload (1 file = 1 plant = 1 hari, tapi isinya banyak baris/batch).
     * Dipakai sebagai sumber halaman "Detail" (Lembar A) dan juga sebagai
     * sumber hitung agregat yang disimpan ke uniformity_reports.
     */
    public function up(): void
    {
        Schema::create('uniformity_batches', function (Blueprint $table) {
            $table->id();

            // Info lokasi & waktu (dipilih user saat upload, bukan dibaca dari Excel)
            $table->string('region');
            $table->string('plant');
            $table->date('tanggal');

            // No urut baris di Excel (kolom A "No") - hanya untuk keperluan urutan/debug
            $table->unsignedInteger('no_urut')->nullable();

            // Data per baris dari Excel
            $table->string('nama_farm')->nullable();        // kolom C
            $table->unsignedInteger('jumlah_sample')->nullable(); // kolom D
            $table->string('ekspedisi')->nullable();        // kolom E
            $table->double('rata_sppa')->nullable();        // kolom F
            $table->double('rata_rpa')->nullable();         // kolom G
            $table->double('size_ayam')->nullable();        // kolom H (acuan hitung kategori)
            $table->double('berat_min')->nullable();        // kolom J
            $table->double('berat_max')->nullable();        // kolom K

            // Kategori size HASIL HITUNG SISTEM dari size_ayam (bukan dari Excel)
            // Nilai: AK, AM, AB, AJ
            $table->string('kategori_size', 5)->nullable();

            // Jumlah ekor (angka mentah, kolom L, N, P di Excel)
            $table->unsignedInteger('ekor_under')->default(0);
            $table->unsignedInteger('ekor_standart')->default(0); // "in range"
            $table->unsignedInteger('ekor_over')->default(0);
            $table->unsignedInteger('total_ekor')->default(0);    // = sum ketiganya, dihitung sistem

            $table->timestamps();

            // Query utama: ambil semua batch untuk 1 plant dalam rentang tanggal
            $table->index(['plant', 'tanggal'], 'uniformity_batches_plant_tanggal_index');

            // Query untuk agregasi per kategori size (dipakai isi uniformity_reports)
            $table->index(['plant', 'tanggal', 'kategori_size'], 'uniformity_batches_plant_tanggal_kategori_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uniformity_batches');
    }
}
