<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('atu_reports', function (Blueprint $table) {
            $table->id();

            // Info minggu & bulan (1 file excel = 1 plant = 1 minggu).
            // "bulan"/"tahun" dipakai untuk filter tampilan per bulan kalender,
            // ditentukan dari tanggal_mulai/tanggal_selesai minggu tsb saat upload.
            $table->string('week_label')->nullable();      // contoh: "Week 1", "Week 2" (relatif dalam 1 bulan, 1-4/5)
            $table->unsignedTinyInteger('bulan');           // 1-12
            $table->unsignedSmallInteger('tahun');          // contoh: 2026
            $table->date('tanggal_mulai')->nullable();      // Senin minggu tsb
            $table->date('tanggal_selesai')->nullable();    // Minggu minggu tsb

            // Info lokasi
            $table->string('region');   // contoh: Banten, Jabar, Jateng, Jatim, Luar Jawa
            $table->string('plant');    // contoh: Salatiga 1, Bandung, Medan
            $table->string('size', 5);  // AK, AM, AB, AJ

            // Jumlah LB (Live Bird) yang jadi basis perhitungan persentase
            $table->double('jumlah_lb')->default(0);

            // Defect Farm
            $table->double('defect_farm_ekor')->default(0);
            $table->double('defect_farm_persen')->default(0); // desimal, 0.05 = 5%

            // Defect Proses
            $table->double('defect_proses_ekor')->default(0);
            $table->double('defect_proses_persen')->default(0);

            // ATU
            $table->double('atu_ekor')->default(0);
            $table->double('atu_persen')->default(0);

            // Total RM Defect + ATU (gabungan)
            $table->double('total_defect_atu_ekor')->default(0);
            $table->double('total_defect_atu_persen')->default(0);

            $table->timestamps();

            // Supaya query filter bulan/tahun/region/plant/size lebih cepat
            $table->index(['tahun', 'bulan', 'region', 'plant', 'size']);

            // 1 plant hanya boleh punya 1 baris per size per minggu (dipakai untuk replace saat re-upload)
            $table->unique(['plant', 'tanggal_mulai', 'tanggal_selesai', 'size'], 'atu_reports_plant_week_size_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('atu_reports');
    }
};
