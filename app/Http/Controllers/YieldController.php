<?php

namespace App\Http\Controllers;

use App\Models\YieldReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class YieldController extends Controller
{
    private array $namaBulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    // Nama hari bahasa Indonesia, index mengikuti Carbon::dayOfWeek (0 = Minggu ... 6 = Sabtu)
    private array $namaHari = [
        0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu',
        4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu',
    ];

    // Toleransi pembulatan saat cek silang persen vs (Qty / Berat Bersih): 0.001 = 0,1 poin persen
    private const TOLERANSI_PERSEN = 0.001;
    // Toleransi selisih kg saat cek Yield FG Total = FG+BP + BP Evisc
    private const TOLERANSI_KG = 0.5;

    /**
     * Peta kolom database -> alamat sel di template Excel Yield (kolom ALL: C = Qty Kg, D = %).
     * Template dijamin sama untuk semua plant, jadi dibaca dari alamat tetap.
     * Kalau ada baris yang bergeser, pengecekan label (lihat $labelWajib) akan menolak upload.
     */
    private array $petaSel = [
        // Livebirds
        'sppa_lb_ekor'       => 'C7',
        'sppa_lb_kg'         => 'C8',
        'abw'                => 'C9',
        'ayam_mati_ekor'     => 'C10',
        'susut_kg'           => 'C11',
        'susut_persen'       => 'C12',
        'berat_bersih'       => 'C13',
        // RM Input Griller (H0) = titik nol
        'rm_h0_kg'           => 'C15',
        'rm_h0_persen'       => 'D15',
        // H1 Griller
        'rm_griller_kg'      => 'C18',
        'h1_kg'              => 'C19',
        'h1_persen'          => 'D19',
        // H2 Parting
        'rm_parting_kg'      => 'C27',
        'h2_kg'              => 'C33',
        'h2_persen'          => 'D33',
        // H3 Cut Up
        'rm_cutup_kg'        => 'C36',
        'h3_kg'              => 'C44',
        'h3_persen'          => 'D44',
        // H4 Boneless
        'rm_boneless_kg'     => 'C47',
        'h4_kg'              => 'C54',
        'h4_persen'          => 'D54',
        // Total
        'fg_bp_kg'           => 'C57',
        'fg_bp_persen'       => 'D57',
        'bp_evisc_kg'        => 'C58',
        'bp_evisc_persen'    => 'D58',
        'final_yield_kg'     => 'C59',
        'final_yield_persen' => 'D59',
        'lost_kg'            => 'C60',
        'lost_persen'        => 'D60',
    ];

    /**
     * Label kunci di kolom B (nomor baris => teks awal yang diharapkan, tidak peka huruf besar/kecil).
     * Dipakai sebagai pengaman: kalau baris template bergeser/berubah, upload ditolak
     * daripada membaca angka dari baris yang salah. Pakai awalan karena sebagian label
     * memuat angka target yang bisa berubah, mis. "Grand Total Qty FG+BP (71,5%)".
     */
    private array $labelWajib = [
        7  => 'SPPA LB (EKOR)',
        8  => 'SPPA LB (KG)',
        9  => 'ABW',
        10 => 'AYAM MATI',
        11 => 'SUSUT KG',
        12 => 'SUSUT %',
        13 => 'BERAT BERSIH',
        15 => 'RM Input Griller',
        18 => 'RM Griller (H1)',
        19 => 'H1',
        27 => 'RM Parting (H2)',
        33 => 'H2',
        36 => 'RM Cut Up (H3)',
        44 => 'H3',
        47 => 'RM Boneless (H4)',
        54 => 'RM Boneless (H4)',
        57 => 'Grand Total Qty FG+BP',
        58 => 'BP EVISC',
        59 => 'YIELD FG TOTAL',
        60 => 'Lost',
    ];

    // Sama persis dengan plantMap() di AtuController / UniformityController, supaya daftar plant
    // konsisten di semua modul Slaughter House.
    private function plantMap(): array
    {
        return [
            'Banten' => [
                ['plant' => 'Cikande 1'],
                ['plant' => 'Cikande 3'],
                ['plant' => 'Lebak'],
            ],
            'Jabar' => [
                ['plant' => 'Bandung'],
                ['plant' => 'Majalengka'],
                ['plant' => 'Subang'],
            ],
            'Jateng' => [
                ['plant' => 'Salatiga 1'],
                ['plant' => 'Salatiga 2'],
                ['plant' => 'Sragen'],
                ['plant' => 'Banyumas'],
                ['plant' => 'Pemalang'],
                ['plant' => 'Kebumen'],
            ],
            'Jatim' => [
                ['plant' => 'Ngoro'],
                ['plant' => 'Madiun'],
                ['plant' => 'Bondowoso'],
                ['plant' => 'Jombang'],
            ],
            'Luar Jawa' => [
                ['plant' => 'Medan'],
                ['plant' => 'Palembang'],
                ['plant' => 'Bali'],
                ['plant' => 'Banjar Baru'],
                ['plant' => 'Balikpapan'],
                ['plant' => 'Makassar'],
            ],
        ];
    }

    // Sama persis dengan plantCodes() di AtuController / UniformityController.
    private function plantCodes(): array
    {
        return [
            'Cikande 1'   => 'CKD-LXLS',
            'Cikande 3'   => 'CKD-3AL4',
            'Lebak'       => 'LBK-UQME',

            'Bandung'     => 'BDG-MJ4K',
            'Majalengka'  => 'MJL-9AXU',
            'Subang'      => 'SBG-A9D4',

            'Salatiga 1'  => 'SLT-8HY9',
            'Salatiga 2'  => 'SLT-QHJ6',
            'Sragen'      => 'SRG-KLUD',
            'Banyumas'    => 'BYM-7BHB',
            'Pemalang'    => 'PML-QUND',
            'Kebumen'     => 'KBM-S9D7',

            'Ngoro'       => 'NGR-KYCQ',
            'Madiun'      => 'MDN-SSMC',
            'Bondowoso'   => 'BDW-2NY2',
            'Jombang'     => 'JBG-A3DU',

            'Medan'       => 'MED-R6YJ',
            'Palembang'   => 'PLB-4QNU',
            'Bali'        => 'BLI-JF7B',
            'Banjar Baru' => 'BJB-UM5M',
            'Balikpapan'  => 'BLP-Z2FL',
            'Makassar'    => 'MKS-R2PY',
        ];
    }

    public function index()
    {
        return view('yield.index');
    }

    /**
     * Endpoint pre-check kode otorisasi, sebelum file picker dibuka di frontend.
     * Sama dengan AtuController::cekKode().
     */
    public function cekKode(Request $request)
    {
        $request->validate([
            'plant'          => 'required|string',
            'kode_otorisasi' => 'required|string',
        ]);

        $plantInfo = $this->cariPlantDiMap($request->input('plant'));
        if (!$plantInfo) {
            return response()->json([
                'message' => 'Plant "' . $request->input('plant') . '" tidak ditemukan di daftar plant.',
            ], 422);
        }

        $errorOtorisasi = $this->cekOtorisasiPlant($request, $plantInfo['plant']);
        if ($errorOtorisasi) {
            return response()->json(['message' => $errorOtorisasi['message']], $errorOtorisasi['status']);
        }

        return response()->json(['message' => 'Kode otorisasi valid.']);
    }

    /**
     * Cek kode otorisasi + rate limit percobaan gagal. Sama dengan AtuController::cekOtorisasiPlant(),
     * tapi kunci rate limit-nya terpisah ("yield-...") supaya salah kode di modul lain
     * tidak mengunci upload Yield (dan sebaliknya).
     */
    private function cekOtorisasiPlant(Request $request, string $plant): ?array
    {
        $rateLimitKey = 'yield-upload-kode-otorisasi:' . $plant . ':' . $request->ip();

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $detikTunggu = RateLimiter::availableIn($rateLimitKey);
            return [
                'status'  => 429,
                'message' => 'Terlalu banyak percobaan kode yang salah untuk plant ini. Coba lagi dalam ' . ceil($detikTunggu / 60) . ' menit.',
            ];
        }

        $kodeValidPlant = $this->plantCodes()[$plant] ?? null;
        $kodeInput      = trim((string) $request->input('kode_otorisasi'));

        if ($kodeValidPlant === null || !hash_equals($kodeValidPlant, $kodeInput)) {
            RateLimiter::hit($rateLimitKey, 900); // lock 15 menit setelah 5x gagal
            return [
                'status'  => 403,
                'message' => 'Kode otorisasi tidak sesuai untuk plant ini.',
            ];
        }

        RateLimiter::clear($rateLimitKey);

        return null;
    }

    /**
     * Upload Excel Yield. 1 file = 1 plant = 1 hari.
     *
     * Ada 2 tahap, memakai endpoint yang sama, dibedakan parameter "simpan":
     *  - simpan = 0 (default): HANYA membaca & memvalidasi file, mengembalikan pratinjau
     *    (tanggal, angka kunci, dan apakah akan menimpa data lama). Tidak ada yang disimpan.
     *  - simpan = 1: membaca file lagi dan menyimpan (plant + tanggal yang sama ditimpa).
     *
     * Kode otorisasi divalidasi di KEDUA tahap.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file'           => 'required|mimes:xlsx,xls',
            'plant'          => 'required|string',
            'kode_otorisasi' => 'required|string',
            'simpan'         => 'nullable|in:0,1',
        ]);

        $plantInfo = $this->cariPlantDiMap($request->input('plant'));
        if (!$plantInfo) {
            return response()->json([
                'message' => 'Plant "' . $request->input('plant') . '" tidak ditemukan di daftar plant.',
            ], 422);
        }

        $region = $plantInfo['region'];
        $plant  = $plantInfo['plant'];

        $errorOtorisasi = $this->cekOtorisasiPlant($request, $plant);
        if ($errorOtorisasi) {
            return response()->json(['message' => $errorOtorisasi['message']], $errorOtorisasi['status']);
        }

        $simpan = $request->input('simpan') === '1';

        try {
            $hasil      = $this->bacaExcel($request->file('file')->getRealPath(), $plant);
            $tanggal    = $hasil['tanggal'];
            $tanggalStr = $tanggal->format('Y-m-d');
            $nilai      = $hasil['nilai'];

            $ringkasan = [
                'tanggal'            => $tanggalStr,
                'tanggal_label'      => $this->namaHari[$tanggal->dayOfWeek] . ', ' . $tanggal->day . ' ' . $this->namaBulan[$hasil['bulan']] . ' ' . $hasil['tahun'],
                'bulan'              => $hasil['bulan'],
                'tahun'              => $hasil['tahun'],
                'bulan_nama'         => $this->namaBulan[$hasil['bulan']] . ' ' . $hasil['tahun'],
                'berat_bersih'       => $nilai['berat_bersih'],
                'abw'                => $nilai['abw'],
                'titik_nol_persen'   => $nilai['rm_h0_persen'],
                'fg_bp_persen'       => $nilai['fg_bp_persen'],
                'bp_evisc_persen'    => $nilai['bp_evisc_persen'],
                'final_yield_persen' => $nilai['final_yield_persen'],
                'akan_ditimpa'       => YieldReport::where('plant', $plant)->where('tanggal', $tanggalStr)->exists(),
            ];

            if (!$simpan) {
                return response()->json([
                    'mode'      => 'pratinjau',
                    'plant'     => $plant,
                    'ringkasan' => $ringkasan,
                ]);
            }

            DB::transaction(function () use ($plant, $region, $tanggalStr, $hasil, $nilai) {
                // Replace data lama untuk plant + tanggal ini saja, supaya upload ulang tidak duplikat
                YieldReport::where('plant', $plant)->where('tanggal', $tanggalStr)->delete();

                YieldReport::create(array_merge([
                    'region'  => $region,
                    'plant'   => $plant,
                    'tanggal' => $tanggalStr,
                    'bulan'   => $hasil['bulan'],
                    'tahun'   => $hasil['tahun'],
                ], $nilai));
            });

            return response()->json([
                'message'   => 'Upload berhasil diproses.',
                'plant'     => $plant,
                'ringkasan' => $ringkasan,
                'bulan'     => $hasil['bulan'],
                'tahun'     => $hasil['tahun'],
            ]);
        } catch (\RuntimeException $e) {
            // Pesan validasi template/data yang sengaja dibuat agar bisa langsung ditampilkan ke user
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'message' => 'Gagal memproses file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Baca 1 file Excel template Yield (1 plant, 1 hari) dari alamat sel tetap, lalu validasi.
     *
     * Melempar \RuntimeException berisi pesan yang bisa langsung ditampilkan ke user.
     * Mengembalikan: ['tanggal' => Carbon, 'bulan' => int, 'tahun' => int, 'nilai' => [kolom => float]]
     */
    private function bacaExcel(string $path, string $plantDipilih): array
    {
        $spreadsheet = IOFactory::load($path);

        try {
            // Utamakan sheet yang namanya sama dengan plant; kalau tidak ada, pakai sheet aktif
            $sheet = null;
            foreach ($spreadsheet->getAllSheets() as $ws) {
                if ($this->samaPlant($ws->getTitle(), $plantDipilih)) {
                    $sheet = $ws;
                    break;
                }
            }
            $sheet ??= $spreadsheet->getActiveSheet();

            // 1) Pastikan ini template Yield
            $judul = $this->strOrNull($this->nilaiSel($sheet, 'B1'));
            if ($judul === null || stripos($judul, 'MONITORING YIELD') === false) {
                throw new \RuntimeException('Template tidak dikenali. Pastikan file memakai template "FORM PERHITUNGAN INDEX GRILLER (MONITORING YIELD)".');
            }

            // 2) Pastikan label baris kunci masih di tempatnya (template tidak bergeser)
            $selisihLabel = [];
            foreach ($this->labelWajib as $baris => $diharapkan) {
                $terbaca = $this->normalisasiLabel($this->nilaiSel($sheet, 'B' . $baris));
                if (!str_starts_with($terbaca, $this->normalisasiLabel($diharapkan))) {
                    $selisihLabel[] = "B{$baris} (seharusnya \"{$diharapkan}\", terbaca \"" . ($terbaca === '' ? 'kosong' : $terbaca) . '")';
                }
            }
            if (count($selisihLabel) > 0) {
                throw new \RuntimeException(
                    'Susunan template tidak sesuai (baris bergeser atau label berubah): '
                    . implode('; ', array_slice($selisihLabel, 0, 3))
                    . (count($selisihLabel) > 3 ? '; dan ' . (count($selisihLabel) - 3) . ' lainnya' : '')
                    . '. Pakai template resmi tanpa menambah/menghapus baris lalu upload ulang.'
                );
            }

            // 3) Plant di file harus sama dengan plant yang dipilih saat upload
            $plantExcel = $this->strOrNull($this->nilaiSel($sheet, 'C2')) ?? $sheet->getTitle();
            if (!$this->samaPlant($plantExcel, $plantDipilih)) {
                throw new \RuntimeException("Plant di file (\"{$plantExcel}\") tidak sama dengan plant yang dipilih (\"{$plantDipilih}\"). Periksa kembali pilihan plant atau file yang diupload.");
            }

            // 4) Tanggal laporan (C3) + cek terhadap bulan di judul (B1)
            $tanggal = $this->bacaTanggal($this->nilaiSel($sheet, 'C3'));
            $bulan   = $tanggal->month;
            $tahun   = $tanggal->year;

            $bulanJudul = $this->bulanTahunDariJudul($judul);
            if ($bulanJudul === null) {
                throw new \RuntimeException("Bulan pada judul (\"{$judul}\") tidak terbaca. Pastikan judul memuat nama bulan dan tahun, mis. \"... SEPTEMBER 2026\".");
            }
            if ($bulanJudul[0] !== $bulan || $bulanJudul[1] !== $tahun) {
                throw new \RuntimeException(
                    'Tanggal laporan (' . $tanggal->day . ' ' . $this->namaBulan[$bulan] . ' ' . $tahun . ') tidak sesuai dengan bulan pada judul ('
                    . $this->namaBulan[$bulanJudul[0]] . ' ' . $bulanJudul[1] . '). Perbaiki tanggal atau judul di Excel lalu upload ulang.'
                );
            }

            // 5) Baca semua angka. Sel inti wajib berisi angka; sel lain yang kosong/error dianggap 0
            //    (mis. #DIV/0! pada hari tanpa produksi di seksi tertentu).
            $inti  = ['berat_bersih' => 'Berat Bersih', 'final_yield_kg' => 'Yield FG Total (Qty)', 'final_yield_persen' => 'Yield FG Total (%)'];
            $nilai = [];
            foreach ($this->petaSel as $kolom => $alamat) {
                $nilai[$kolom] = isset($inti[$kolom])
                    ? $this->angkaInti($sheet, $alamat, $inti[$kolom])
                    : $this->angka($sheet, $alamat);
            }

            // 6) Cek silang
            if ($nilai['berat_bersih'] <= 0) {
                throw new \RuntimeException('Berat Bersih (C13) bernilai 0, artinya tidak ada data produksi di file ini.');
            }

            $totalKomponen = $nilai['fg_bp_kg'] + $nilai['bp_evisc_kg'];
            if (abs($nilai['final_yield_kg'] - $totalKomponen) > self::TOLERANSI_KG) {
                throw new \RuntimeException(
                    'Yield FG Total (C59 = ' . number_format($nilai['final_yield_kg'], 2, ',', '.') . ' kg) tidak sama dengan FG+BP (C57) + BP Evisc (C58) = '
                    . number_format($totalKomponen, 2, ',', '.') . ' kg. Periksa rumus di Excel.'
                );
            }

            $cekPersen = [
                ['rm_h0_kg',      'rm_h0_persen',       'RM Input Griller (H0)',  'D15'],
                ['fg_bp_kg',      'fg_bp_persen',       'FG + BP',                'D57'],
                ['bp_evisc_kg',   'bp_evisc_persen',    'BP Evisc',               'D58'],
                ['final_yield_kg', 'final_yield_persen', 'Yield FG Total',        'D59'],
            ];
            foreach ($cekPersen as [$kolomKg, $kolomPersen, $nama, $alamatPersen]) {
                $diharapkan = $nilai[$kolomKg] / $nilai['berat_bersih'];
                if (abs($nilai[$kolomPersen] - $diharapkan) > self::TOLERANSI_PERSEN) {
                    throw new \RuntimeException(
                        "Persen {$nama} ({$alamatPersen}) terbaca " . number_format($nilai[$kolomPersen] * 100, 2, ',', '.')
                        . '%, tidak sesuai dengan Qty ÷ Berat Bersih = ' . number_format($diharapkan * 100, 2, ',', '.')
                        . '%. Pastikan persen berformat desimal (0,93 = 93%) dan rumus tidak diubah.'
                    );
                }
            }

            return [
                'tanggal' => $tanggal,
                'bulan'   => $bulan,
                'tahun'   => $tahun,
                'nilai'   => $nilai,
            ];
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    /**
     * Ambil nilai sel apa adanya (sudah dihitung). Kalau rumus gagal dihitung, coba nilai
     * cache dari file; kalau tetap gagal dikembalikan penanda error ("#ERROR!").
     */
    private function nilaiSel(Worksheet $sheet, string $alamat)
    {
        $cell = $sheet->getCell($alamat);

        try {
            return $cell->getCalculatedValue();
        } catch (\Throwable $e) {
            try {
                return $cell->getOldCalculatedValue() ?? '#ERROR!';
            } catch (\Throwable $e2) {
                return '#ERROR!';
            }
        }
    }

    // Nilai teks yang diawali "#" dianggap error Excel (#DIV/0!, #N/A, #VALUE!, dst.)
    private function selError($value): bool
    {
        return is_string($value) && str_starts_with(trim($value), '#');
    }

    // Angka biasa: kosong atau error = 0. Teks selain itu ditolak supaya tidak diam-diam jadi 0.
    private function angka(Worksheet $sheet, string $alamat): float
    {
        $v = $this->nilaiSel($sheet, $alamat);

        if ($v === null || $v === '' || $this->selError($v)) {
            return 0.0;
        }
        if (!is_numeric($v)) {
            throw new \RuntimeException("Sel {$alamat} berisi \"" . trim((string) $v) . '" (seharusnya angka).');
        }

        return (float) $v;
    }

    // Angka inti: tidak boleh kosong/error
    private function angkaInti(Worksheet $sheet, string $alamat, string $nama): float
    {
        $v = $this->nilaiSel($sheet, $alamat);

        if ($v === null || $v === '' || $this->selError($v)) {
            throw new \RuntimeException("Sel {$alamat} ({$nama}) kosong atau berisi error. Perbaiki di Excel lalu upload ulang.");
        }
        if (!is_numeric($v)) {
            throw new \RuntimeException("Sel {$alamat} ({$nama}) berisi \"" . trim((string) $v) . '" (seharusnya angka).');
        }

        return (float) $v;
    }

    /**
     * Tanggal laporan dari sel C3. Mendukung: tanggal Excel (angka serial), teks "01/09/2026",
     * "01-09-2026", atau "1 September 2026".
     */
    private function bacaTanggal($v): Carbon
    {
        if ($v === null || $v === '' || $this->selError($v)) {
            throw new \RuntimeException('Tanggal laporan (sel C3) kosong atau error.');
        }

        // Tanggal asli Excel (angka serial)
        if (is_numeric($v) && (float) $v > 20000 && (float) $v < 100000) {
            $dt = ExcelDate::excelToDateTimeObject((float) $v);
            return Carbon::createFromFormat('Y-m-d', $dt->format('Y-m-d'))->startOfDay();
        }

        // Tanggal yang diketik sebagai teks
        $teks = trim((string) $v);
        $d = $m = $y = null;

        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $teks, $mm)) {
            [$d, $m, $y] = [(int) $mm[1], (int) $mm[2], (int) $mm[3]];
        } elseif (preg_match('/^(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})$/u', $teks, $mm)) {
            $m = $this->bulanDariNama($mm[2]);
            [$d, $y] = [(int) $mm[1], (int) $mm[3]];
        }

        if ($d === null || $m === null || !checkdate($m, $d, $y)) {
            throw new \RuntimeException("Tanggal laporan pada sel C3 (\"{$teks}\") tidak terbaca. Isi dengan format tanggal Excel, mis. 01/09/2026.");
        }

        return Carbon::create($y, $m, $d)->startOfDay();
    }

    /**
     * Cari "NamaBulan YYYY" pertama yang valid di judul. Mis. "... YIELD) SEPTEMBER 2026" -> [9, 2026].
     */
    private function bulanTahunDariJudul(string $judul): ?array
    {
        if (preg_match_all('/([A-Za-z]+)\s+(\d{4})/u', $judul, $semua, PREG_SET_ORDER)) {
            foreach ($semua as $m) {
                $bulan = $this->bulanDariNama($m[1]);
                if ($bulan !== null) {
                    return [$bulan, (int) $m[2]];
                }
            }
        }
        return null;
    }

    private function bulanDariNama(string $nama): ?int
    {
        $nama = mb_strtolower(trim($nama));
        foreach ($this->namaBulan as $angka => $label) {
            if (mb_strtolower($label) === $nama) {
                return $angka;
            }
        }
        return null;
    }

    private function normalisasiLabel($value): string
    {
        $teks = mb_strtolower(trim((string) $value));
        return preg_replace('/\s+/u', ' ', $teks);
    }

    // Banding nama plant tanpa peduli huruf besar/kecil dan spasi ganda ("JOMBANG" = "Jombang")
    private function samaPlant($a, $b): bool
    {
        return $this->normalisasiLabel($a) === $this->normalisasiLabel($b);
    }

    private function strOrNull($value)
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /**
     * Data mentah per hari untuk chart & tabel, difilter per bulan+tahun (default bulan berjalan),
     * opsional region/plant. Agregasi (rata-rata bulanan berbobot) dilakukan di frontend.
     */
    public function data(Request $request)
    {
        $bulan = $request->filled('bulan') ? (int) $request->input('bulan') : now()->month;
        $tahun = $request->filled('tahun') ? (int) $request->input('tahun') : now()->year;

        $query = YieldReport::where('bulan', $bulan)->where('tahun', $tahun);

        if ($request->filled('region')) {
            $query->where('region', $request->input('region'));
        }

        if ($request->filled('plant')) {
            $query->where('plant', $request->input('plant'));
        }

        return response()->json(
            $query->orderBy('plant')->orderBy('tanggal')->get()
        );
    }

    /**
     * Daftar region & plant (untuk dropdown filter + upload), dan daftar bulan/tahun
     * yang sudah ada datanya.
     */
    public function filterOptions()
    {
        $rows = YieldReport::select('region', 'plant')->distinct()->get();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->region][] = $row->plant;
        }
        foreach ($grouped as $region => $plants) {
            $grouped[$region] = array_values(array_unique($plants));
            sort($grouped[$region]);
        }

        $bulanTersedia = YieldReport::select('bulan', 'tahun')
            ->distinct()
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->get()
            ->map(fn ($r) => [
                'bulan' => $r->bulan,
                'tahun' => $r->tahun,
                'label' => ($this->namaBulan[$r->bulan] ?? $r->bulan) . ' ' . $r->tahun,
            ]);

        $semuaPlant = [];
        foreach ($this->plantMap() as $region => $plants) {
            $semuaPlant[$region] = array_column($plants, 'plant');
        }

        return response()->json([
            'regions'              => array_keys($grouped),
            'plants_by_region'     => $grouped,
            'bulan_tersedia'       => $bulanTersedia,
            'all_plants_by_region' => $semuaPlant,
        ]);
    }

    private function cariPlantDiMap(string $plantName): ?array
    {
        foreach ($this->plantMap() as $region => $plants) {
            foreach ($plants as $p) {
                if (strcasecmp($p['plant'], $plantName) === 0) {
                    return ['region' => $region, 'plant' => $p['plant']];
                }
            }
        }
        return null;
    }
}