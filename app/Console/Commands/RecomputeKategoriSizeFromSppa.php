<?php

namespace App\Console\Commands;

use App\Models\UniformityBatch;
use App\Models\UniformityReport;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecomputeKategoriSizeFromSppa extends Command
{
    protected $signature = 'uniformity:recompute-kategori
                            {--dry-run : Hanya tampilkan ringkasan, jangan ubah database}';

    protected $description = 'Hitung ulang kategori_size semua data batch dari kolom Rata-rata SPPA (bukan RPA), lalu re-agregasi ke uniformity_reports';

    private array $sizeOrder = ['AK', 'AM', 'AB', 'AJ'];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('MODE DRY-RUN: tidak ada perubahan yang akan disimpan ke database.');
        }

        $totalBatch = UniformityBatch::count();
        $this->info("Total baris batch yang akan diproses: {$totalBatch}");

        if ($totalBatch === 0) {
            $this->info('Tidak ada data untuk diproses.');
            return self::SUCCESS;
        }

        $diubah = 0;
        $tidakBisaDihitung = 0;
        $plantTanggalTerdampak = [];

        DB::transaction(function () use (&$diubah, &$tidakBisaDihitung, &$plantTanggalTerdampak, $dryRun) {
            UniformityBatch::chunkById(200, function ($batches) use (&$diubah, &$tidakBisaDihitung, &$plantTanggalTerdampak, $dryRun) {
                foreach ($batches as $batch) {
                    $kategoriBaru = $this->hitungKategoriSize($batch->rata_sppa);

                    if ($kategoriBaru === null) {
                        $tidakBisaDihitung++;
                        $this->line("  [SKIP] ID {$batch->id} ({$batch->plant}, {$batch->tanggal}): rata_sppa kosong/tidak valid, kategori dibiarkan '{$batch->kategori_size}'.");
                        continue;
                    }

                    if ($kategoriBaru !== $batch->kategori_size) {
                        $diubah++;
                        $plantTanggalTerdampak[$batch->plant . '|' . $batch->tanggal->format('Y-m-d')] = true;

                        if (!$dryRun) {
                            $batch->kategori_size = $kategoriBaru;
                            $batch->save();
                        }
                    }
                }
            });
        });

        $this->info("Selesai proses batch. Baris yang berubah kategori: {$diubah}. Baris yang dilewati (SPPA kosong): {$tidakBisaDihitung}.");

        if ($dryRun) {
            $this->warn('Dry-run selesai. Tidak ada yang disimpan. Jalankan tanpa --dry-run untuk eksekusi sungguhan.');
            $this->info('Plant+tanggal yang TERDAMPAK jika dijalankan sungguhan: ' . count($plantTanggalTerdampak));
            return self::SUCCESS;
        }

        // Re-agregasi ke uniformity_reports untuk semua plant+tanggal yang ADA datanya
        // (bukan cuma yang berubah, supaya konsisten total 100% dari batch yang terbaru)
        $this->info('Menghitung ulang agregat ke uniformity_reports...');

        $kombinasi = UniformityBatch::select('plant', 'tanggal', 'region')
            ->distinct()
            ->get();

        $bar = $this->output->createProgressBar($kombinasi->count());
        $bar->start();

        foreach ($kombinasi as $k) {
            $tanggal = Carbon::parse($k->tanggal)->format('Y-m-d');
            $plant = $k->plant;
            $region = $k->region;

            $rows = UniformityBatch::where('plant', $plant)->where('tanggal', $tanggal)->get();

            $agregat = [];
            foreach ($this->sizeOrder as $size) {
                $agregat[$size] = ['under' => 0, 'standart' => 0, 'over' => 0, 'total' => 0];
            }
            foreach ($rows as $b) {
                if (!in_array($b->kategori_size, $this->sizeOrder)) {
                    continue; // baris yang kategorinya masih kosong/invalid, tidak ikut diagregasi
                }
                $agregat[$b->kategori_size]['under']    += $b->ekor_under;
                $agregat[$b->kategori_size]['standart'] += $b->ekor_standart;
                $agregat[$b->kategori_size]['over']     += $b->ekor_over;
                $agregat[$b->kategori_size]['total']    += $b->total_ekor;
            }

            $weekLabel = 'Week ' . Carbon::parse($tanggal)->isoWeek();

            UniformityReport::where('plant', $plant)->where('tanggal', $tanggal)->delete();

            $reportRows = [];
            foreach ($this->sizeOrder as $size) {
                $a = $agregat[$size];
                $total = $a['total'];
                $reportRows[] = [
                    'week_label'      => $weekLabel,
                    'tanggal'         => $tanggal,
                    'region'          => $region,
                    'plant'           => $plant,
                    'size'            => $size,
                    'total_lb'        => $total,
                    'lb_standart'     => $a['standart'],
                    'lb_under'        => $a['under'],
                    'lb_over'         => $a['over'],
                    'persen_standart' => $total > 0 ? $a['standart'] / $total : 0,
                    'persen_under'    => $total > 0 ? $a['under'] / $total : 0,
                    'persen_over'     => $total > 0 ? $a['over'] / $total : 0,
                    'target'          => 0.8,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];
            }
            UniformityReport::insert($reportRows);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Selesai. Semua uniformity_reports sudah dihitung ulang berdasarkan kategori size dari Rata-rata SPPA.');

        return self::SUCCESS;
    }

    /**
     * Sama persis dengan logic di UniformityController.
     * AK: <1.40 | AM: 1.40-1.80 | AB: 1.81-2.30 | AJ: >=2.31
     */
    private function hitungKategoriSize(?float $beratKg): ?string
    {
        if ($beratKg === null || $beratKg <= 0) {
            return null;
        }
        if ($beratKg < 1.40) {
            return 'AK';
        }
        if ($beratKg <= 1.80) {
            return 'AM';
        }
        if ($beratKg <= 2.30) {
            return 'AB';
        }
        return 'AJ';
    }
}
