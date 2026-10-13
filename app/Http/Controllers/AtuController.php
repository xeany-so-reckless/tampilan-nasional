<?php

namespace App\Http\Controllers;

use App\Models\AtuReport;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AtuController extends Controller
{
    // Urutan size, sesuai kolom D-G di template Excel ATU
    private array $sizeOrder = ['AK', 'AM', 'AB', 'AJ'];

    // Kolom Excel per size
    private array $kolomSize = ['AK' => 'D', 'AM' => 'E', 'AB' => 'F', 'AJ' => 'G'];

    private array $namaBulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    // Sama persis dengan plantMap() di UniformityController, supaya daftar plant konsisten
    // di semua modul Slaughter House.
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

    // Sama persis dengan plantCodes() di UniformityController.
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
        return view('atu.index');
    }

    /**
     * Endpoint pre-check kode otorisasi, sebelum file picker dibuka di frontend.
     * Sama persis pola & alasannya dengan UniformityController::cekKode().
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
     * Cek kode otorisasi + rate limit percobaan gagal. Identik dengan
     * UniformityController::cekOtorisasiPlant().
     */
    private function cekOtorisasiPlant(Request $request, string $plant): ?array
    {
        $rateLimitKey = 'atu-upload-kode-otorisasi:' . $plant . ':' . $request->ip();

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
     * Upload Excel ATU. 1 file = 1 plant, bisa berisi BANYAK minggu (blok ditumpuk ke bawah).
     *
     * Ada 2 tahap, memakai endpoint yang sama, dibedakan parameter "simpan":
     *  - simpan = 0 (default): HANYA membaca file dan mengembalikan pratinjau (daftar minggu
     *    yang terbaca + bulan laporannya + apakah akan menimpa data lama). Tidak ada yang disimpan.
     *  - simpan = 1: membaca file lagi dan menyimpan. Semua minggu disimpan dalam 1 transaksi
     *    (tersimpan semua atau tidak sama sekali).
     *
     * Kode otorisasi divalidasi di KEDUA tahap, jadi tahap simpan tidak bisa dipanggil langsung
     * tanpa kode yang benar.
     *
     * Tanggal tiap minggu dibaca dari judul blok ("Tgl. 13 sd 19 September 2026"). Kalau ada
     * blok yang tanggalnya tidak terbaca atau rentangnya bukan 7 hari, seluruh upload ditolak.
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
            $blokList = $this->bacaBlokExcel($request->file('file')->getRealPath());

            // Ringkasan minggu (dipakai untuk pratinjau maupun respons sukses)
            $ringkasan = [];
            foreach ($blokList as $i => $blok) {
                $ringkasan[] = [
                    'urutan'          => $i + 1,
                    'week_label'      => $blok['week_label'],
                    'tanggal_mulai'   => $blok['mulai']->format('Y-m-d'),
                    'tanggal_selesai' => $blok['selesai']->format('Y-m-d'),
                    'bulan'           => $blok['bulan'],
                    'tahun'           => $blok['tahun'],
                    'bulan_nama'      => $this->namaBulan[$blok['bulan']] . ' ' . $blok['tahun'],
                    'akan_ditimpa'    => AtuReport::where('plant', $plant)
                        ->where('tanggal_mulai', $blok['mulai']->format('Y-m-d'))
                        ->where('tanggal_selesai', $blok['selesai']->format('Y-m-d'))
                        ->exists(),
                ];
            }

            if (!$simpan) {
                return response()->json([
                    'mode'   => 'pratinjau',
                    'plant'  => $plant,
                    'minggu' => $ringkasan,
                ]);
            }

            $jumlahBaris = 0;

            DB::transaction(function () use ($blokList, $plant, $region, &$jumlahBaris) {
                foreach ($blokList as $blok) {
                    $mulai   = $blok['mulai']->format('Y-m-d');
                    $selesai = $blok['selesai']->format('Y-m-d');

                    // Replace data lama untuk plant + minggu ini saja, supaya upload ulang tidak duplikat
                    AtuReport::where('plant', $plant)
                        ->where('tanggal_mulai', $mulai)
                        ->where('tanggal_selesai', $selesai)
                        ->delete();

                    $rows = [];
                    foreach ($this->sizeOrder as $index => $size) {
                        $m = $blok['per_size'][$size];
                        $pertama = ($index === 0);

                        // ATU & Total RM Defect+ATU = sel gabungan D:G di Excel (berlaku utk seluruh plant,
                        // bukan per size). Disimpan di baris AK saja; AM/AB/AJ diisi 0 supaya penjumlahan
                        // di frontend tidak ganda.
                        $rows[] = [
                            'week_label'              => $blok['week_label'],
                            'bulan'                   => $blok['bulan'],
                            'tahun'                   => $blok['tahun'],
                            'tanggal_mulai'           => $mulai,
                            'tanggal_selesai'         => $selesai,
                            'region'                  => $region,
                            'plant'                   => $plant,
                            'size'                    => $size,
                            'jumlah_lb'               => $m['jumlah_lb'],
                            'defect_farm_ekor'        => $m['defect_farm_ekor'],
                            'defect_farm_persen'      => $m['defect_farm_persen'],
                            'defect_proses_ekor'      => $m['defect_proses_ekor'],
                            'defect_proses_persen'    => $m['defect_proses_persen'],
                            'atu_ekor'                => $pertama ? $blok['plant_level']['atu_ekor'] : 0,
                            'atu_persen'              => $pertama ? $blok['plant_level']['atu_persen'] : 0,
                            'total_defect_atu_ekor'   => $pertama ? $blok['plant_level']['total_defect_atu_ekor'] : 0,
                            'total_defect_atu_persen' => $pertama ? $blok['plant_level']['total_defect_atu_persen'] : 0,
                            'created_at'              => now(),
                            'updated_at'              => now(),
                        ];
                    }

                    AtuReport::insert($rows);
                    $jumlahBaris += count($rows);
                }
            });

            // Bulan tujuan tampilan setelah upload: bulan yang paling banyak memuat minggu (seri -> yang lebih baru)
            $hitung = [];
            foreach ($ringkasan as $r) {
                $key = $r['tahun'] . '-' . str_pad((string) $r['bulan'], 2, '0', STR_PAD_LEFT);
                $hitung[$key] = ($hitung[$key] ?? 0) + 1;
            }
            uksort($hitung, fn ($a, $b) => ($hitung[$b] <=> $hitung[$a]) ?: strcmp($b, $a));
            [$tahunTujuan, $bulanTujuan] = array_map('intval', explode('-', array_key_first($hitung)));

            return response()->json([
                'message'  => 'Upload berhasil diproses.',
                'plant'    => $plant,
                'minggu'   => $ringkasan,
                'berhasil' => $jumlahBaris,
                'bulan'    => $bulanTujuan,
                'tahun'    => $tahunTujuan,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal memproses file: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Baca seluruh blok minggu di sheet aktif. Tiap blok diawali baris judul
     * "REKAP AYAM REJECT ALL PLANT" di kolom B; isi blok dibaca RELATIF terhadap blok itu
     * (pencarian berdasarkan label di kolom C), jadi posisi baris boleh bergeser.
     *
     * Melempar exception berisi pesan yang bisa langsung ditampilkan ke user.
     */
    private function bacaBlokExcel(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet       = $spreadsheet->getActiveSheet();
        $barisMax    = $sheet->getHighestRow();

        // 1) Cari baris awal tiap blok
        $awalBlok = [];
        for ($r = 1; $r <= $barisMax; $r++) {
            $teks = $this->strOrNull($sheet->getCell('B' . $r)->getCalculatedValue());
            if ($teks !== null && stripos($teks, 'REKAP AYAM REJECT') !== false) {
                $awalBlok[] = $r;
            }
        }

        if (count($awalBlok) === 0) {
            throw new \RuntimeException('Tidak ditemukan blok data. Pastikan file memakai template "REKAP AYAM REJECT ALL PLANT".');
        }

        $hasil        = [];
        $rentangDipakai = [];

        foreach ($awalBlok as $i => $awal) {
            $nomor = $i + 1;
            $akhir = isset($awalBlok[$i + 1]) ? $awalBlok[$i + 1] - 1 : $barisMax;

            // 2) Tanggal dari judul ("Tgl. ... sd ...") - dicari di beberapa baris pertama blok
            $judulTanggal = null;
            for ($r = $awal + 1; $r <= min($awal + 3, $akhir); $r++) {
                $teks = $this->strOrNull($sheet->getCell('B' . $r)->getCalculatedValue());
                if ($teks !== null && stripos($teks, 'Tgl') !== false) {
                    $judulTanggal = $teks;
                    break;
                }
            }
            if ($judulTanggal === null) {
                throw new \RuntimeException("Blok ke-{$nomor}: judul tanggal (\"Tgl. ... sd ...\") tidak ditemukan.");
            }

            [$mulai, $selesai] = $this->parseRentangTanggal($judulTanggal, $nomor);

            // Carbon 3 mengembalikan float (6.0), jadi dibulatkan ke integer sebelum dibandingkan.
            // 13 s/d 19 = 6 hari selisih = 7 hari kalender (inklusif).
            if ((int) round($mulai->diffInDays($selesai)) !== 6) {
                throw new \RuntimeException(
                    "Blok ke-{$nomor}: rentang tanggal \"{$judulTanggal}\" bukan 7 hari "
                    . '(terbaca ' . $mulai->format('d/m/Y') . ' - ' . $selesai->format('d/m/Y') . ').'
                );
            }

            $kunciRentang = $mulai->format('Y-m-d') . '|' . $selesai->format('Y-m-d');
            if (isset($rentangDipakai[$kunciRentang])) {
                throw new \RuntimeException("Blok ke-{$nomor}: rentang tanggal sama dengan blok ke-{$rentangDipakai[$kunciRentang]} (minggu dobel dalam 1 file).");
            }
            $rentangDipakai[$kunciRentang] = $nomor;

            // 3) Cari baris tiap metrik berdasarkan label di kolom C
            $barisMetrik = [];
            $barisWeek   = null;
            for ($r = $awal; $r <= $akhir; $r++) {
                $label = $this->normalisasiLabel($sheet->getCell('C' . $r)->getCalculatedValue());
                $kunci = $this->kenaliMetrik($label);
                if ($kunci !== null && !isset($barisMetrik[$kunci])) {
                    $barisMetrik[$kunci] = $r;
                }
                if ($label === 'plant') {
                    $barisWeek = $r + 1; // label "Week xx" ada tepat di bawah nama plant
                }
            }

            $wajib = [
                'jumlah_lb', 'defect_farm_ekor', 'defect_farm_persen', 'defect_proses_ekor',
                'defect_proses_persen', 'atu_ekor', 'atu_persen', 'total_defect_atu_ekor', 'total_defect_atu_persen',
            ];
            foreach ($wajib as $k) {
                if (!isset($barisMetrik[$k])) {
                    throw new \RuntimeException("Blok ke-{$nomor}: baris \"{$k}\" tidak ditemukan di kolom C.");
                }
            }

            // 4) Baca angka per size (kolom D-G)
            $perSize = [];
            foreach ($this->sizeOrder as $size) {
                $kolom = $this->kolomSize[$size];
                $ambil = fn (string $k) => $this->numOrNull($sheet->getCell($kolom . $barisMetrik[$k])->getCalculatedValue()) ?? 0.0;

                $perSize[$size] = [
                    'jumlah_lb'            => $ambil('jumlah_lb'),
                    'defect_farm_ekor'     => $ambil('defect_farm_ekor'),
                    'defect_farm_persen'   => $this->normalisasiPersen($ambil('defect_farm_persen')),
                    'defect_proses_ekor'   => $ambil('defect_proses_ekor'),
                    'defect_proses_persen' => $this->normalisasiPersen($ambil('defect_proses_persen')),
                ];
            }

            // 5) Baris ATU & Total = sel gabungan, nilainya hanya di kolom D
            $bacaD = fn (string $k) => $this->numOrNull($sheet->getCell('D' . $barisMetrik[$k])->getCalculatedValue()) ?? 0.0;
            $levelPlant = [
                'atu_ekor'                => $bacaD('atu_ekor'),
                'atu_persen'              => $this->normalisasiPersen($bacaD('atu_persen')),
                'total_defect_atu_ekor'   => $bacaD('total_defect_atu_ekor'),
                'total_defect_atu_persen' => $this->normalisasiPersen($bacaD('total_defect_atu_persen')),
            ];

            $weekLabel = $barisWeek !== null
                ? $this->strOrNull($sheet->getCell('D' . $barisWeek)->getCalculatedValue())
                : null;

            [$bulan, $tahun] = $this->tentukanBulanTahun($mulai, $selesai);

            $hasil[] = [
                'week_label'  => $weekLabel ?? ('Week ' . $mulai->isoWeek()),
                'mulai'       => $mulai,
                'selesai'     => $selesai,
                'bulan'       => $bulan,
                'tahun'       => $tahun,
                'per_size'    => $perSize,
                'plant_level' => $levelPlant,
            ];
        }

        return $hasil;
    }

    /**
     * Baca rentang tanggal dari judul blok. Format yang didukung:
     *  - "Tgl. 13 sd 19 September 2026"            (bulan hanya di akhir)
     *  - "Tgl.27 September sd 03 Oktober 2026"      (lintas bulan)
     *  - "Tgl.29 Desember 2025 sd 03 Januari 2026"  (lintas tahun)
     */
    private function parseRentangTanggal(string $judul, int $nomorBlok): array
    {
        $pola = '/Tgl\.?\s*(\d{1,2})(?:\s+([A-Za-z]+))?(?:\s+(\d{4}))?\s+sd\s+(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})/iu';

        if (!preg_match($pola, $judul, $m)) {
            throw new \RuntimeException("Blok ke-{$nomorBlok}: tanggal tidak terbaca dari judul \"{$judul}\".");
        }

        $hariMulai   = (int) $m[1];
        $bulanMulaiT = $m[2] ?? '';
        $tahunMulaiT = $m[3] ?? '';
        $hariAkhir   = (int) $m[4];
        $bulanAkhir  = $this->bulanDariNama($m[5]);
        $tahunAkhir  = (int) $m[6];

        if ($bulanAkhir === null) {
            throw new \RuntimeException("Blok ke-{$nomorBlok}: nama bulan \"{$m[5]}\" tidak dikenali.");
        }

        if ($bulanMulaiT !== '') {
            $bulanMulai = $this->bulanDariNama($bulanMulaiT);
            if ($bulanMulai === null) {
                throw new \RuntimeException("Blok ke-{$nomorBlok}: nama bulan \"{$bulanMulaiT}\" tidak dikenali.");
            }
        } else {
            $bulanMulai = $bulanAkhir; // "Tgl. 13 sd 19 September" -> bulan awal = bulan akhir
        }

        if ($tahunMulaiT !== '') {
            $tahunMulai = (int) $tahunMulaiT;
        } else {
            // Tanpa tahun awal: kalau bulan awal lebih besar dari bulan akhir, berarti lintas tahun (Des -> Jan)
            $tahunMulai = $bulanMulai > $bulanAkhir ? $tahunAkhir - 1 : $tahunAkhir;
        }

        if (!checkdate($bulanMulai, $hariMulai, $tahunMulai) || !checkdate($bulanAkhir, $hariAkhir, $tahunAkhir)) {
            throw new \RuntimeException("Blok ke-{$nomorBlok}: tanggal pada judul \"{$judul}\" tidak valid.");
        }

        return [
            Carbon::create($tahunMulai, $bulanMulai, $hariMulai)->startOfDay(),
            Carbon::create($tahunAkhir, $bulanAkhir, $hariAkhir)->startOfDay(),
        ];
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
        $teks = preg_replace('/\s+/u', ' ', $teks);
        return preg_replace('/^%\s*/u', '% ', $teks);
    }

    // Kenali metrik dari label kolom C (label asli Excel mengandung baris baru, mis. "Defect Farm\n(Ekor)")
    private function kenaliMetrik(string $label): ?string
    {
        if ($label === '') {
            return null;
        }
        if (str_starts_with($label, 'jumlah lb')) return 'jumlah_lb';
        if (str_starts_with($label, '% defect farm')) return 'defect_farm_persen';
        if (str_starts_with($label, 'defect farm')) return 'defect_farm_ekor';
        if (str_starts_with($label, '% defect proses')) return 'defect_proses_persen';
        if (str_starts_with($label, 'defect proses')) return 'defect_proses_ekor';
        if (str_starts_with($label, '% atu')) return 'atu_persen';
        if (str_starts_with($label, 'atu')) return 'atu_ekor';
        if (str_starts_with($label, '% rm')) return 'total_defect_atu_persen';
        if (str_starts_with($label, 'total rm')) return 'total_defect_atu_ekor';
        return null;
    }

    /**
     * Data agregat untuk chart & tabel, difilter per bulan+tahun (default bulan berjalan),
     * opsional region/plant.
     */
    public function data(Request $request)
    {
        $bulan = $request->filled('bulan') ? (int) $request->input('bulan') : now()->month;
        $tahun = $request->filled('tahun') ? (int) $request->input('tahun') : now()->year;

        $query = AtuReport::where('bulan', $bulan)->where('tahun', $tahun);

        if ($request->filled('region')) {
            $query->where('region', $request->input('region'));
        }

        if ($request->filled('plant')) {
            $query->where('plant', $request->input('plant'));
        }

        return response()->json(
            $query->orderBy('tanggal_mulai')->get()
        );
    }

    /**
     * Daftar region & plant (untuk dropdown filter + upload), dan daftar bulan/tahun
     * yang sudah ada datanya.
     */
    public function filterOptions()
    {
        $rows = AtuReport::select('region', 'plant')->distinct()->get();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->region][] = $row->plant;
        }
        foreach ($grouped as $region => $plants) {
            $grouped[$region] = array_values(array_unique($plants));
            sort($grouped[$region]);
        }

        $bulanTersedia = AtuReport::select('bulan', 'tahun')
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

    /**
     * Tentukan bulan+tahun laporan untuk 1 minggu berdasarkan bulan yang jumlah harinya
     * PALING BANYAK dalam rentang minggu itu.
     * Contoh: 27 Sep - 3 Okt -> 4 hari September, 3 hari Oktober -> September.
     */
    private function tentukanBulanTahun(Carbon $mulai, Carbon $selesai): array
    {
        $hitungPerBulan = []; // key "tahun-bulan" => jumlah hari

        foreach (CarbonPeriod::create($mulai, $selesai) as $tanggal) {
            $key = $tanggal->year . '-' . $tanggal->month;
            $hitungPerBulan[$key] = ($hitungPerBulan[$key] ?? 0) + 1;
        }

        arsort($hitungPerBulan);
        $keyTerpilih = array_key_first($hitungPerBulan);
        [$tahun, $bulan] = explode('-', $keyTerpilih);

        return [(int) $bulan, (int) $tahun];
    }

    /**
     * Sebagian file mengisi persentase sebagai desimal (0.05 = 5%), sebagian sebagai angka persen
     * langsung (5 = 5%). Nilai >1 dianggap angka persen langsung dan dikonversi ke desimal.
     */
    private function normalisasiPersen(float $value): float
    {
        return $value > 1 ? $value / 100 : $value;
    }

    private function strOrNull($value)
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function numOrNull($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        return (float) $value;
    }
}