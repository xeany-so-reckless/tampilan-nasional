<?php

namespace App\Http\Controllers;

use App\Models\UniformityBatch;
use App\Models\UniformityReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class UniformityController extends Controller
{
    // Urutan size yang dipakai (dihitung dari "Rata-rata RPA", bukan dibaca dari Excel)
    private array $sizeOrder = ['AK', 'AM', 'AB', 'AJ'];

    // Baris pertama data batch di sheet Excel format baru (row 1-4 = judul & header)
    private int $dataStartRow = 5;

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

    public function index()
    {
        return view('uniformity.index');
    }

    public function detailPage()
    {
        return view('uniformity.detail');
    }

    /**
     * Upload & parsing file Excel. 1 file = 1 plant, bisa 1 hari atau banyak hari.
     * - Kalau user isi tanggal manual: semua baris pakai tanggal itu, kolom B diabaikan.
     * - Kalau tanggal dikosongkan: tanggal dibaca PER BARIS dari kolom B Excel.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file'    => 'required|mimes:xlsx,xls',
            'plant'   => 'required|string',
            'tanggal' => 'nullable|date',
        ]);

        $plantInfo = $this->cariPlantDiMap($request->input('plant'));
        if (!$plantInfo) {
            return response()->json([
                'message' => 'Plant "' . $request->input('plant') . '" tidak ditemukan di daftar plant.',
            ], 422);
        }

        $region  = $plantInfo['region'];
        $plant   = $plantInfo['plant'];

        // Kalau user isi tanggal manual, semua baris pakai tanggal itu (kolom B Excel diabaikan).
        // Kalau kosong, tanggal dibaca PER BARIS dari kolom B Excel (1 file bisa punya banyak tanggal).
        $tanggalManual = $request->filled('tanggal')
            ? Carbon::parse($request->input('tanggal'))->format('Y-m-d')
            : null;

        try {
            $path = $request->file('file')->getRealPath();
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();

            $batchRows = [];
            $dilewati  = [];
            $highestRow = $sheet->getHighestRow();

            for ($row = $this->dataStartRow; $row <= $highestRow; $row++) {
                $noCell = $sheet->getCell('A' . $row)->getValue();

                if (is_string($noCell) && stripos(trim($noCell), 'AVERAGE') !== false) {
                    break;
                }

                if ($noCell === null || $noCell === '') {
                    continue;
                }

                $namaFarm     = $this->strOrNull($sheet->getCell('C' . $row)->getCalculatedValue());
                $jumlahSample = $this->numOrNull($sheet->getCell('D' . $row)->getCalculatedValue());
                $ekspedisi    = $this->strOrNull($sheet->getCell('E' . $row)->getCalculatedValue());
                $rataSppa     = $this->numOrNull($sheet->getCell('F' . $row)->getCalculatedValue());
                $rataRpa      = $this->numOrNull($sheet->getCell('G' . $row)->getCalculatedValue());
                // "Size Ayam" ternyata teks rentang (contoh "1.5-1.7"), disimpan sebagai info saja,
                // TIDAK dipakai untuk hitung kategori (pakai strOrNull, bukan numOrNull)
                $sizeAyam     = $this->strOrNull($sheet->getCell('H' . $row)->getCalculatedValue());
                $beratMin     = $this->numOrNull($sheet->getCell('J' . $row)->getCalculatedValue());
                $beratMax     = $this->numOrNull($sheet->getCell('K' . $row)->getCalculatedValue());
                $ekorUnder    = $this->numOrNull($sheet->getCell('L' . $row)->getCalculatedValue());
                $ekorStandart = $this->numOrNull($sheet->getCell('N' . $row)->getCalculatedValue());
                $ekorOver     = $this->numOrNull($sheet->getCell('P' . $row)->getCalculatedValue());

                $semuaEkorKosong = ($ekorUnder === null || $ekorUnder == 0)
                    && ($ekorStandart === null || $ekorStandart == 0)
                    && ($ekorOver === null || $ekorOver == 0);

                                if ($rataSppa === null && $semuaEkorKosong) {
                    continue;
                }

                // Tentukan tanggal baris ini: manual (kalau diisi) atau dari kolom B
                $tanggalBaris = $tanggalManual ?? $this->bacaTanggalBaris($sheet, $row);

                if ($tanggalBaris === null) {
                    $dilewati[] = [
                        'baris'  => $row,
                        'alasan' => 'Kolom "Tanggal" (B' . $row . ') kosong/tidak valid, dan tidak ada tanggal manual dipilih.',
                    ];
                    continue;
                }

                                // Kategori size DIHITUNG SISTEM dari kolom "Rata-rata SPPA", kolom "Kategori Size" di Excel diabaikan
                $kategoriSize = $this->hitungKategoriSize($rataSppa);

                if ($kategoriSize === null) {
                    $dilewati[] = [
                        'baris'  => $row,
                        'alasan' => 'Kolom "Rata-rata SPPA" (F' . $row . ') kosong/tidak valid, kategori size tidak bisa dihitung.',
                    ];
                    continue;
                }

                $ekorUnder    = (int) ($ekorUnder ?? 0);
                $ekorStandart = (int) ($ekorStandart ?? 0);
                $ekorOver     = (int) ($ekorOver ?? 0);

                $batchRows[] = [
                    'region'        => $region,
                    'plant'         => $plant,
                    'tanggal'       => $tanggalBaris,
                    'no_urut'       => is_numeric($noCell) ? (int) $noCell : null,
                    'nama_farm'     => $namaFarm,
                    'jumlah_sample' => $jumlahSample !== null ? (int) $jumlahSample : null,
                    'ekspedisi'     => $ekspedisi,
                    'rata_sppa'     => $rataSppa,
                    'rata_rpa'      => $rataRpa,
                    'size_ayam'     => $sizeAyam,
                    'berat_min'     => $beratMin,
                    'berat_max'     => $beratMax,
                    'kategori_size' => $kategoriSize,
                    'ekor_under'    => $ekorUnder,
                    'ekor_standart' => $ekorStandart,
                    'ekor_over'     => $ekorOver,
                    'total_ekor'    => $ekorUnder + $ekorStandart + $ekorOver,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];
            }

            if (count($batchRows) === 0) {
                return response()->json([
                    'message'  => 'Tidak ada baris data valid yang ditemukan di file ini.',
                    'dilewati' => $dilewati,
                ], 422);
            }

            // Kelompokkan baris berdasarkan tanggal (bisa 1 tanggal, bisa banyak)
            $rowsByTanggal = [];
            foreach ($batchRows as $b) {
                $rowsByTanggal[$b['tanggal']][] = $b;
            }

            DB::transaction(function () use ($rowsByTanggal, $plant, $region) {
                foreach ($rowsByTanggal as $tanggal => $rowsHariIni) {
                    // 1) Replace data mentah lama untuk plant + tanggal ini saja
                    UniformityBatch::where('plant', $plant)->where('tanggal', $tanggal)->delete();
                    foreach (array_chunk($rowsHariIni, 200) as $chunk) {
                        UniformityBatch::insert($chunk);
                    }

                    // 2) Hitung agregat per kategori size untuk tanggal ini
                    $agregat = [];
                    foreach ($this->sizeOrder as $size) {
                        $agregat[$size] = ['under' => 0, 'standart' => 0, 'over' => 0, 'total' => 0];
                    }
                    foreach ($rowsHariIni as $b) {
                        $size = $b['kategori_size'];
                        $agregat[$size]['under']    += $b['ekor_under'];
                        $agregat[$size]['standart'] += $b['ekor_standart'];
                        $agregat[$size]['over']     += $b['ekor_over'];
                        $agregat[$size]['total']    += $b['total_ekor'];
                    }

                    $weekLabel = $this->weekLabelFromDate($tanggal);

                    // 3) Replace data agregat lama untuk plant + tanggal ini saja
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
                }
            });

            return response()->json([
                'message'          => 'Upload berhasil diproses.',
                'plant'            => $plant,
                'tanggal_diproses' => array_keys($rowsByTanggal),
                'berhasil'         => count($batchRows),
                'dilewati'         => $dilewati,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal memproses file: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function data(Request $request)
    {
        $query = UniformityReport::query();

        $week = $request->input('week');
        if (!$week) {
            $week = UniformityReport::orderByDesc('tanggal')->value('week_label');
        }
        if ($week) {
            $query->where('week_label', $week);
        }

        if ($request->filled('region')) {
            $query->where('region', $request->input('region'));
        }

        if ($request->filled('plant')) {
            $query->where('plant', $request->input('plant'));
        }

        return response()->json($query->get());
    }

    public function filterOptions()
    {
        $rows = UniformityReport::select('region', 'plant')->distinct()->get();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->region][] = $row->plant;
        }
        foreach ($grouped as $region => $plants) {
            $grouped[$region] = array_values(array_unique($plants));
            sort($grouped[$region]);
        }

        $weeks = UniformityReport::select('week_label')
            ->selectRaw('MAX(tanggal) as tanggal_terakhir')
            ->groupBy('week_label')
            ->orderByDesc('tanggal_terakhir')
            ->get();

        $semuaPlant = [];
        foreach ($this->plantMap() as $region => $plants) {
            $semuaPlant[$region] = array_column($plants, 'plant');
        }

        return response()->json([
            'regions'              => array_keys($grouped),
            'plants_by_region'     => $grouped,
            'weeks'                => $weeks,
            'all_plants_by_region' => $semuaPlant,
        ]);
    }

    public function uploadStatus(Request $request)
    {
        $request->validate([
            'start' => 'required|date',
            'end'   => 'required|date',
        ]);

        $start = Carbon::parse($request->input('start'))->startOfDay();
        $end   = Carbon::parse($request->input('end'))->startOfDay();

        $tanggalList = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $tanggalList[] = $d->format('Y-m-d');
        }

        $existing = UniformityReport::whereBetween('tanggal', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->select('plant', 'tanggal')
            ->distinct()
            ->get()
            ->groupBy('plant')
            ->map(function ($rows) {
                return $rows->pluck('tanggal')->map(fn ($t) => $t->format('Y-m-d'))->all();
            });

        $result = [];
        foreach ($this->plantMap() as $region => $plants) {
            foreach ($plants as $p) {
                $sudahUpload = $existing->get($p['plant'], []);
                $result[$region][] = [
                    'plant'   => $p['plant'],
                    'tanggal' => array_map(function ($tgl) use ($sudahUpload) {
                        return [
                            'tanggal'      => $tgl,
                            'sudah_upload' => in_array($tgl, $sudahUpload),
                        ];
                    }, $tanggalList),
                ];
            }
        }

        return response()->json($result);
    }

    public function detail(Request $request)
    {
        $request->validate([
            'plant' => 'required|string',
            'start' => 'required|date',
            'end'   => 'required|date',
        ]);

        $query = UniformityBatch::query()
            ->where('plant', $request->input('plant'))
            ->whereBetween('tanggal', [
                Carbon::parse($request->input('start'))->format('Y-m-d'),
                Carbon::parse($request->input('end'))->format('Y-m-d'),
            ]);

        if ($request->filled('kategori_size')) {
            $kategori = (array) $request->input('kategori_size');
            $query->whereIn('kategori_size', $kategori);
        }

        $rows = $query->orderBy('tanggal')->orderBy('no_urut')->get();

        return response()->json($rows);
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
     * Baca tanggal dari kolom B di baris tertentu. Menangani beberapa
     * kemungkinan format: objek DateTime (paling umum di Excel), serial
     * angka Excel, atau teks tanggal biasa.
     */
    private function bacaTanggalBaris($sheet, int $row): ?string
    {
        $value = $sheet->getCell('B' . $row)->getValue();

        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format('Y-m-d');
        }

        if (is_numeric($value)) {
            try {
                $dateTime = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
                return Carbon::instance($dateTime)->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

        /**
     * Hitung kategori size dari berat rata-rata (kolom "Rata-rata RPA", dalam Kg).
     * AK: <1.40 | AM: 1.40-1.79 | AB: 1.80-2.19 | AJ: >=2.20
     */
        private function hitungKategoriSize(?float $beratKg): ?string
    {
        if ($beratKg === null || $beratKg <= 0) {
            return null;
        }
        if ($beratKg <= 1.39) {
            return 'AK';
        }
        if ($beratKg <= 1.79) {
            return 'AM';
        }
        if ($beratKg <= 2.19) {
            return 'AB';
        }
        return 'AJ'; // 2.20 ke atas, tanpa batas atas
    }

    private function weekLabelFromDate(string $tanggal): string
    {
        $date = Carbon::parse($tanggal);
        return 'Week ' . $date->isoWeek();
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