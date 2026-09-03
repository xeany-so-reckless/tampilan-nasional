<?php

namespace App\Http\Controllers;

use App\Models\UniformityReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class UniformityController extends Controller
{
    // Urutan size yang dipakai di tiap blok kolom plant (4 kolom per plant)
    private array $sizeOrder = ['AK', 'AM', 'AB', 'AJ'];

    // Baris-baris data di sheet Excel (sesuai contoh file "Combine_Rekap_Uniformity_All_Plant.xlsx")
    private int $rowTotalLb       = 8;
    private int $rowStandart      = 9;
    private int $rowUnder         = 10;
    private int $rowOver          = 11;
    private int $rowPersenStandart = 12;
    private int $rowPersenUnder    = 13;
    private int $rowPersenOver     = 14;
    private int $rowTarget         = 15;
    private int $rowWeekInfo       = 3; // cell D3 berisi teks "Week 34, Tgl 17-08-2026 sd 23-08-2026"

    /**
     * Peta kolom awal tiap plant per region.
     * NOTE: Ada 2 plant bernama "Salatiga" di kolom AJ dan AN pada file contoh
     * (kemungkinan salah satu typo/plant berbeda). Untuk sementara saya beri
     * label "Salatiga 1" dan "Salatiga 2" - tolong cek & sesuaikan namanya.
     */
    private function plantMap(): array
    {
        return [
            'Banten' => [
                ['col' => 'D', 'plant' => 'Cikande 1'],
                ['col' => 'H', 'plant' => 'Cikande 3'],
                ['col' => 'L', 'plant' => 'Lebak'],
            ],
            'Jabar' => [
                ['col' => 'T', 'plant' => 'Bandung'],
                ['col' => 'X', 'plant' => 'Majalengka'],
                ['col' => 'AB', 'plant' => 'Subang'],
            ],
            'Jateng' => [
                ['col' => 'AJ', 'plant' => 'Salatiga 1'],
                ['col' => 'AN', 'plant' => 'Salatiga 2'],
                ['col' => 'AR', 'plant' => 'Sragen'],
                ['col' => 'AV', 'plant' => 'Banyumas'],
                ['col' => 'AZ', 'plant' => 'Pemalang'],
                ['col' => 'BD', 'plant' => 'Kebumen'],
            ],
            'Jatim' => [
                ['col' => 'BL', 'plant' => 'Ngoro'],
                ['col' => 'BP', 'plant' => 'Madiun'],
                ['col' => 'BT', 'plant' => 'Bondowoso'],
                ['col' => 'BX', 'plant' => 'Jombang'],
            ],
            'Luar Jawa' => [
                ['col' => 'CK', 'plant' => 'Medan'],
                ['col' => 'CO', 'plant' => 'Palembang'],
                ['col' => 'CS', 'plant' => 'Bali'],
                ['col' => 'CW', 'plant' => 'Banjar Baru'],
                ['col' => 'DA', 'plant' => 'Balikpapan'],
                ['col' => 'DE', 'plant' => 'Makassar'],
            ],
        ];
    }

    /**
     * Halaman utama Rekap Uniformity Mingguan.
     */
    public function index()
    {
        return view('uniformity.index');
    }

    /**
     * Upload & parsing file Excel. 1 file = 1 minggu -> replace data lama.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        try {
            $path = $request->file('file')->getRealPath();
            $spreadsheet = IOFactory::load($path);

            // Ambil sheet "Uniformity" kalau ada, kalau tidak pakai sheet aktif pertama
            $sheet = $spreadsheet->sheetNameExists('Uniformity')
                ? $spreadsheet->getSheetByName('Uniformity')
                : $spreadsheet->getActiveSheet();

            // Ambil info minggu dari cell D3, contoh: "Week 34, Tgl 17-08-2026 sd 23-08-2026"
            $weekInfoRaw = trim((string) $sheet->getCell('D' . $this->rowWeekInfo)->getValue());
            [$weekLabel, $tanggalMulai, $tanggalSelesai] = $this->parseWeekInfo($weekInfoRaw);

            $berhasil = 0;
            $dilewati = [];
            $rowsToInsert = [];

            foreach ($this->plantMap() as $region => $plants) {
                foreach ($plants as $p) {
                    $startColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($p['col']);

                    foreach ($this->sizeOrder as $offset => $size) {
                        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startColIndex + $offset);

                        $totalLb  = $this->numOrNull($sheet->getCell($colLetter . $this->rowTotalLb)->getCalculatedValue());
                        $standart = $this->numOrNull($sheet->getCell($colLetter . $this->rowStandart)->getCalculatedValue());
                        $under    = $this->numOrNull($sheet->getCell($colLetter . $this->rowUnder)->getCalculatedValue());
                        $over     = $this->numOrNull($sheet->getCell($colLetter . $this->rowOver)->getCalculatedValue());

                        // Kalau semua metrik ekor kosong (bukan 0, tapi benar-benar kosong), lewati
                        if ($totalLb === null && $standart === null && $under === null && $over === null) {
                            $dilewati[] = [
                                'plant'  => $p['plant'] . ' (' . $size . ')',
                                'alasan' => 'Data kosong pada kolom ' . $colLetter,
                            ];
                            continue;
                        }

                        $persenStandart = $this->numOrNull($sheet->getCell($colLetter . $this->rowPersenStandart)->getCalculatedValue()) ?? 0;
                        $persenUnder    = $this->numOrNull($sheet->getCell($colLetter . $this->rowPersenUnder)->getCalculatedValue()) ?? 0;
                        $persenOver     = $this->numOrNull($sheet->getCell($colLetter . $this->rowPersenOver)->getCalculatedValue()) ?? 0;
                        $target         = $this->numOrNull($sheet->getCell($colLetter . $this->rowTarget)->getCalculatedValue()) ?? 0.8;

                        $rowsToInsert[] = [
                            'week_label'      => $weekLabel,
                            'tanggal_mulai'   => $tanggalMulai,
                            'tanggal_selesai' => $tanggalSelesai,
                            'region'          => $region,
                            'plant'           => $p['plant'],
                            'size'            => $size,
                            'total_lb'        => $totalLb ?? 0,
                            'lb_standart'     => $standart ?? 0,
                            'lb_under'        => $under ?? 0,
                            'lb_over'         => $over ?? 0,
                            'persen_standart' => $persenStandart,
                            'persen_under'    => $persenUnder,
                            'persen_over'     => $persenOver,
                            'target'          => $target,
                            'created_at'      => now(),
                            'updated_at'      => now(),
                        ];
                        $berhasil++;
                    }
                }
            }

            // 1 file = 1 minggu -> hapus semua data lama sebelum insert data baru
            DB::transaction(function () use ($rowsToInsert, $weekLabel) {
    UniformityReport::where('week_label', $weekLabel)->delete();
    foreach (array_chunk($rowsToInsert, 200) as $chunk) {
        UniformityReport::insert($chunk);
    }
});

            return response()->json([
                'message'  => 'Upload berhasil diproses.',
                'week'     => $weekLabel,
                'berhasil' => $berhasil,
                'dilewati' => $dilewati,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal memproses file: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Ambil data untuk chart. Bisa difilter via query param:
     * - ?region=Jateng            -> semua plant di region tsb
     * - ?plant=Salatiga 1         -> data satu plant spesifik
     * - tanpa filter              -> semua data (dipakai utk hitung agregat Nasional)
     */
    public function data(Request $request)
{
    $query = UniformityReport::query();

    // Kalau user tidak pilih minggu spesifik, otomatis ambil minggu terbaru
    $week = $request->input('week');
    if (!$week) {
        $week = UniformityReport::max('tanggal_mulai')
            ? UniformityReport::orderByDesc('tanggal_mulai')->value('week_label')
            : null;
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

    /**
     * Daftar region & plant (dikelompokkan per region) untuk isi dropdown filter.
     */
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

    $weeks = UniformityReport::select('week_label', 'tanggal_mulai')
        ->distinct()
        ->orderByDesc('tanggal_mulai')
        ->get();

    return response()->json([
        'regions' => array_keys($grouped),
        'plants_by_region' => $grouped,
        'weeks' => $weeks, // [{week_label: "Week 35", tanggal_mulai: "2026-08-24"}, ...]
    ]);
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

    /**
     * Parse teks "Week 34, Tgl 17-08-2026 sd 23-08-2026" jadi
     * [week_label, tanggal_mulai, tanggal_selesai] (format Y-m-d).
     */
    private function parseWeekInfo(string $text): array
    {
        $weekLabel = null;
        $tanggalMulai = null;
        $tanggalSelesai = null;

        if (preg_match('/Week\s*\d+/i', $text, $m)) {
            $weekLabel = $m[0];
        }

        if (preg_match('/(\d{2}-\d{2}-\d{4})\s*sd\s*(\d{2}-\d{2}-\d{4})/i', $text, $m)) {
            $tanggalMulai = \DateTime::createFromFormat('d-m-Y', $m[1])?->format('Y-m-d');
            $tanggalSelesai = \DateTime::createFromFormat('d-m-Y', $m[2])?->format('Y-m-d');
        }

        return [$weekLabel, $tanggalMulai, $tanggalSelesai];
    }
}
