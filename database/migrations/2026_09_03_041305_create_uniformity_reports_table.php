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
        Schema::create('uniformity_reports', function (Blueprint $table) {
            $table->id();

            // Info minggu (1 file excel = 1 minggu, data lama akan di-replace saat upload baru)
            $table->string('week_label')->nullable();      // contoh: "Week 34"
            $table->date('tanggal_mulai')->nullable();      // contoh: 2026-08-17
            $table->date('tanggal_selesai')->nullable();    // contoh: 2026-08-23

            // Info lokasi
            $table->string('region');   // contoh: Banten, Jabar, Jateng, Jatim, Luar Jawa
            $table->string('plant');    // contoh: Salatiga, Bandung, Medan
            $table->string('size', 5);  // AK, AM, AB, AJ

            // Angka jumlah ekor
            $table->double('total_lb')->default(0);
            $table->double('lb_standart')->default(0);
            $table->double('lb_under')->default(0);
            $table->double('lb_over')->default(0);

            // Angka persentase (disimpan sebagai desimal, misal 0.42 = 42%)
            $table->double('persen_standart')->default(0);
            $table->double('persen_under')->default(0);
            $table->double('persen_over')->default(0);
            $table->double('target')->default(0.8);

            $table->timestamps();

            // Supaya query filter per plant/size lebih cepat
            $table->index(['region', 'plant', 'size']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uniformity_reports');
    }
};
