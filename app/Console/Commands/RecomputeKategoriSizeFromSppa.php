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
                            {--dry-run : Hanya tampilkan ringkasan, jangan ubah database}
                            {--force : Lewati pertanyaan konfirmasi saat eksekusi sungguhan}';

    protected $description = 'Hitung ulang kategori_size semua data batch dari kolom Rata-rata SPPA (AK/AM/AB/AJ/ASJ), lalu re-agregasi ke uniformity_reports';

    // HARUS sama dengan $sizeOrder di UniformityController
    private array $sizeOrder = ['AK', 'AM', 'AB', 'AJ', 'ASJ'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('MODE DRY-RUN: tidak ada perubahan yang akan disimpan ke database.');
        }

        $totalBatch = UniformityBatch::count();
        $this->info("Total baris batch yang akan diproses: {$totalBatch}");

        if ($totalBatch === 0) {
            $this->info('Tidak ada data untuk diproses.');
            return self::SUCCESS;
        }

        // ---- Mode simulasi: hanya hitung & tampilkan dampak, tidak menulis apa pun ----
        if ($dryRun) {
            $stat = $this->prosesBatch(true);
            $this->tampilkanRingkasan($stat);

            $jumlahKombinasi = $this->kombinasiPlantTanggal()->count();
            $this->info('Plant+tanggal yang kategorinya berubah: ' . count($stat['plantTanggalTerdampak']));
            $this->info("Plant+tanggal yang akan di-agregasi ulang jika dijalankan sungguhan: {$jumlahKombinasi}");
            $this->warn('Dry-run selesai. Tidak ada yang disimpan. Jalankan tanpa --dry-run untuk eksekusi sungguhan.');
            return self::SUCCESS;
        }

        // ---- Eksekusi sungguhan ----
        if (!$this->option('force') && !$this->confirm('Sudah backup tabel uniformity_batches & uniformity_reports? Lanjutkan hitung ulang?')) {
            $this->warn('Dibatalkan. Tidak ada perubahan.');
            return self::SUCCESS;
        }

        $stat = null;

        // SEMUA langkah (update kategori + hapus & buat ulang report) dalam 1 transaksi:
        // kalau error di tengah, database kembali seperti semula.
        DB::transaction(function () use (&$stat) {
            $stat = $this->prosesBatch(false);
            $this->tampilkanRingkasan($stat);
            $this->reagregasiSemua();
        });

        $this->info('Selesai. Semua uniformity_reports sudah dihitung ulang (AK, AM, AB, AJ, ASJ) berdasarkan Rata-rata SPPA.');

        return self::SUCCESS;
    }

    /**
     * Hitung ulang kategori tiap batch. Kalau $dryRun = true, tidak ada yang disimpan.
     * Mengembalikan statistik: jumlah berubah, dilewati, rincian perpindahan, plant+tanggal terdampak.
     */
    private function prosesBatch(bool $dryRun): array
    {
        $stat = [
            'diubah'                => 0,
            'dilewati'              => 0,
            'transisi'              => [], // contoh: ['AJ -> ASJ' => 12]
            'plantTanggalTerdampak' => [],
        ];

        UniformityBatch::chunkById(200, function ($batches) use (&$stat, $dryRun) {
            foreach ($batches as $batch) {
                $kategoriBaru = $this->hitungKategoriSize($batch->rata_sppa);

                if ($kategoriBaru === null) {
                    $stat['dilewati']++;
                    $this->line("  [SKIP] ID {$batch->id} ({$batch->plant}, {$batch->tanggal->format('Y-m-d')}): rata_sppa kosong/tidak valid, kategori dibiarkan '{$batch->kategori_size}'.");
                    continue;
                }

                if ($kategoriBaru !== $batch->kategori_size) {
                    $stat['diubah']++;
                    $kunci = ($batch->kategori_size ?? '(kosong)') . ' -> ' . $kategoriBaru;
                    $stat['transisi'][$kunci] = ($stat['transisi'][$kunci] ?? 0) + 1;
                    $stat['plantTanggalTerdampak'][$batch->plant . '|' . $batch->tanggal->format('Y-m-d')] = true;

                    if (!$dryRun) {
                        $batch->kategori_size = $kategoriBaru;
                        $batch->save();
                    }
                }
            }
        });

        return $stat;
    }

    private function tampilkanRingkasan(array $stat): void
    {
        $this->info("Selesai proses batch. Baris yang berubah kategori: {$stat['diubah']}. Baris yang dilewati (SPPA kosong): {$stat['dilewati']}.");

        if (!empty($stat['transisi'])) {
            ksort($stat['transisi']);
            $rows = [];
            foreach ($stat['transisi'] as $perpindahan => $jumlah) {
                $rows[] = [$perpindahan, $jumlah];
            }
            $this->table(['Perpindahan kategori', 'Jumlah batch'], $rows);
        }
    }

    private function kombinasiPlantTanggal()
    {
        return UniformityBatch::select('plant', 'tanggal', 'region')
            ->distinct()
            ->get();
    }

    /**
     * Re-agregasi ke uniformity_reports untuk semua plant+tanggal yang punya batch
     * (bukan cuma yang berubah, supaya semuanya konsisten 5 baris size dari batch terbaru).
     * Harus dipanggil di dalam transaksi.
     */
    private function reagregasiSemua(): void
    {
        $this->info('Menghitung ulang agregat ke uniformity_reports...');

        $kombinasi = $this->kombinasiPlantTanggal();

        $bar = $this->output->createProgressBar($kombinasi->count());
        $bar->start();

        foreach ($kombinasi as $k) {
            $tanggal = Carbon::parse($k->tanggal)->format('Y-m-d');
            $plant   = $k->plant;
            $region  = $k->region;

            $rows = UniformityBatch::where('plant', $plant)->where('tanggal', $tanggal)->get();

            $agregat = [];
            foreach ($this->sizeOrder as $size) {
                $agregat[$size] = ['under' => 0, 'standart' => 0, 'over' => 0, 'total' => 0];
            }
            foreach ($rows as $b) {
                if (!in_array($b->kategori_size, $this->sizeOrder, true)) {
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
                $a     = $agregat[$size];
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
    }

    /**
     * HARUS identik dengan UniformityController::hitungKategoriSize().
     * AK: <1.40 | AM: 1.40-1.79 | AB: 1.80-2.19 | AJ: 2.20-2.59 | ASJ: >=2.60
     */
    private function hitungKategoriSize(?float $beratKg): ?string
    {
        if ($beratKg === null || $beratKg <= 0) {
            return null;
        }
        if ($beratKg < 1.40) {
            return 'AK';
        }
        if ($beratKg < 1.80) {
            return 'AM';
        }
        if ($beratKg < 2.20) {
            return 'AB';
        }
        if ($beratKg < 2.60) {
            return 'AJ';
        }
        return 'ASJ'; // 2.60 ke atas, tanpa batas atas
    }
}