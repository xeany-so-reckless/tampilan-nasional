<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Rekap ATU Bulanan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>

    <style>
        :root {
            --accent: #4f46e5;
            --accent-2: #4f46e5;
            --accent-soft: rgba(79,70,229,.08);
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --bg: #f8fafc;
            --card: #ffffff;
            --farm: #ef4444;
            --proses: #f59e0b;
            --atu: #4f46e5;
            --radius: 12px;
            --radius-sm: 8px;
        }
        * { font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg); color: var(--ink); }

        .page-header {
            background: #fff;
            color: var(--ink);
            padding: 24px 0 22px;
            border-bottom: 1px solid var(--line);
        }
        .page-header h3 { font-size: 1.4rem; font-weight: 700; letter-spacing: -0.2px; }
        .page-header h3 i { color: var(--accent); margin-right: 6px; }
        .page-header p { color: var(--muted); font-size: 0.875rem; opacity: 1 !important; }
        .back-link { color: var(--muted); text-decoration: none; font-size: 0.85rem; font-weight: 500; }
        .back-link:hover { color: var(--accent); }

        .toolbar-card, .chart-card, .table-card {
            background: var(--card);
            border-radius: var(--radius);
            border: 1px solid var(--line);
            box-shadow: none;
        }
        .toolbar-card { padding: 18px 22px; }
        .chart-card { padding: 24px; }
        .chart-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .chart-panel { min-width: 0; }
        .chart-panel-title { font-size: 0.75rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; margin-bottom: 10px; }
        .chart-box { position: relative; height: 320px; }
.chart-box.fade-in { animation: chartFadeUp 1.1s ease-out both; }
@keyframes chartFadeUp {
    from { opacity: 0; transform: translateY(18px); }
    to   { opacity: 1; transform: translateY(0); }
}
@media (prefers-reduced-motion: reduce) { .chart-box.fade-in { animation: none; } }
        @media (max-width: 768px) { .chart-grid { grid-template-columns: 1fr; } }

        /* Pilihan size untuk chart overview di atas */
        .size-pill {
            border: 1px solid var(--line);
            color: var(--muted);
            background: #fff;
            border-radius: 6px;
            padding: 4px 12px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 4px;
            transition: all .15s ease;
        }
        .size-pill:hover { border-color: var(--accent); color: var(--accent); }
        .size-pill.active { background: var(--accent); border-color: var(--accent); color: #fff; }

        /* Efek cahaya & partikel chart atas (canvas terpisah di belakang chart) */
        .fx-canvas { position: absolute; left: 0; top: 0; z-index: 0; pointer-events: none; }
        #chartOverview { position: relative; z-index: 1; }

        /* Chart per size di dalam detail plant */
        .detail-charts { padding: 18px 16px 0; }

        .stat-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }
        .stat-box {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 18px 20px;
            box-shadow: none;
        }
        .stat-label {
            font-size: 0.75rem;
            color: var(--muted);
            letter-spacing: 0.3px;
            text-transform: uppercase;
            margin-bottom: 6px;
            font-weight: 600;
        }
        .stat-value { font-weight: 700; font-size: 1.85rem; letter-spacing: -0.5px; color: var(--ink); }
        .stat-sub { font-size: 0.75rem; color: var(--muted); margin-top: 2px; }
        .stat-box.farm .stat-value { color: var(--farm); }
        .stat-box.proses .stat-value { color: var(--proses); }
        .stat-box.atu .stat-value { color: var(--atu); }

        /* Tren dibanding bulan sebelumnya: turun = hijau (bagus), naik = merah (jelek) */
        .stat-trend { margin-top: 6px; min-height: 24px; display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
        .trend-badge {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 0.75rem; font-weight: 700;
            padding: 2px 8px; border-radius: 6px;
        }
        .trend-badge.up   { color: #dc2626; background: rgba(239,68,68,.10); }
        .trend-badge.down { color: #059669; background: rgba(16,185,129,.12); }
        .trend-badge.flat, .trend-badge.none { color: var(--muted); background: #f1f5f9; font-weight: 600; }
        .trend-vs { font-size: 0.72rem; color: var(--muted); }
        .stat-box { transition: background .2s ease, border-color .2s ease; }
        .stat-box.trend-up   { background: linear-gradient(135deg, #fff 55%, rgba(239,68,68,.09) 100%); border-color: rgba(239,68,68,.25); }
        .stat-box.trend-down { background: linear-gradient(135deg, #fff 55%, rgba(16,185,129,.10) 100%); border-color: rgba(16,185,129,.28); }

        .tab-region {
            border: 1px solid var(--line);
            color: var(--muted);
            background: #fff;
            border-radius: var(--radius-sm);
            padding: 6px 14px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-right: 6px;
            margin-bottom: 6px;
            transition: all .15s ease;
        }
        .tab-region:hover { border-color: var(--accent); color: var(--accent); }
        .tab-region.active { background: var(--accent); border-color: var(--accent); color: #fff; }

        .btn-upload {
            background: var(--accent);
            border: 1px solid var(--accent);
            border-radius: var(--radius-sm);
            font-weight: 600;
        }
        .btn-upload:hover { background: #4338ca; border-color: #4338ca; color: #fff; }

        .form-select, .form-control { border-radius: var(--radius-sm); border-color: var(--line); color: var(--ink); }
        .form-select:focus, .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }

        .empty-state { text-align: center; padding: 60px 20px; color: var(--muted); }
        .empty-state i { font-size: 3rem; margin-bottom: 14px; display: block; color: #cbd5e1; }

        .info-period { font-size: 0.85rem; color: var(--muted); }

        table.atu-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        table.atu-table thead th {
            background: var(--bg);
            color: var(--muted);
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            text-align: center;
            padding: 10px;
            border-bottom: 1px solid var(--line);
        }
        table.atu-table thead th:first-child { text-align: left; }
        table.atu-table tbody td { padding: 10px; border-bottom: 1px solid var(--line); text-align: center; vertical-align: middle; }
        table.atu-table tbody td:first-child { text-align: left; font-weight: 600; }
        .plant-row { cursor: pointer; }
        .plant-row:hover { background: var(--bg); }

        /* Sel Plant: nama di kiri, badge status di ujung kanan (lebar tetap -> semua badge sejajar lurus) */
        .plant-cell { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .plant-name { display: flex; align-items: center; gap: 6px; min-width: 0; }
        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 112px;
            width: 112px;
            padding: 3px 0;
            border-radius: 999px;
            font-size: 0.65rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .status-badge.on {
            background: #39ff14;
            color: #052e05;
            box-shadow: 0 0 6px rgba(57,255,20,.75), 0 0 14px rgba(57,255,20,.35);
        }
        .status-badge.off {
            background: #e2e8f0;
            color: #64748b;
            box-shadow: none;
        }

        .pct-chip {
            display: inline-block;
            font-weight: 600;
            font-size: 0.8rem;
            padding: 2px 10px;
            border-radius: 6px;
            background: #f1f5f9;
            color: var(--ink);
        }

        .expand-icon { transition: transform .15s ease; color: var(--muted); }
        .expand-icon.open { transform: rotate(180deg); }

        .detail-row td { padding: 0 !important; background: var(--bg); }
        .week-cards { display: flex; gap: 12px; padding: 16px; flex-wrap: wrap; }
        .week-card {
            flex: 1;
            min-width: 260px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 14px 16px;
        }
        .week-card .week-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 2px;
        }
        .week-card .week-range { font-size: 0.72rem; color: var(--muted); margin-bottom: 10px; }
        .mini-table { width: 100%; font-size: 0.75rem; border-collapse: collapse; }
        .mini-table th {
            font-size: 0.68rem; font-weight: 600; text-transform: uppercase; color: var(--muted);
            text-align: center; padding: 4px; border-bottom: 1px solid var(--line);
        }
        .mini-table th:first-child { text-align: left; }
        .mini-table td { padding: 4px; text-align: center; border-bottom: 1px dashed var(--line); }
        .mini-table td:first-child { text-align: left; color: var(--muted); }
        .mini-table tr.persen-row td { font-weight: 700; color: var(--ink); }
        .no-week-data { color: var(--muted); font-size: 0.8rem; text-align: center; padding: 20px; width: 100%; }
    </style>
</head>
<body>

    <div class="page-header">
        <div class="container">
            <a href="{{ url('/') }}" class="back-link"><i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard</a>
            <h3 class="fw-bold mt-2 mb-0"><i class="fa-solid fa-chart-line"></i> Rekap ATU Bulanan</h3>
            <p class="mb-0 opacity-75">Modul Slaughter House</p>
        </div>
    </div>

    <div class="container my-4">

        <div class="stat-strip" id="statStrip">
            <div class="stat-box farm">
                <div class="stat-label">% Defect Farm</div>
                <div class="stat-value" id="statFarm">-</div>
                <div class="stat-trend" id="trendFarm"></div>
                <div class="stat-sub" id="statSubFarm"></div>
            </div>
            <div class="stat-box proses">
                <div class="stat-label">% Defect Proses</div>
                <div class="stat-value" id="statProses">-</div>
                <div class="stat-trend" id="trendProses"></div>
                <div class="stat-sub" id="statSubProses"></div>
            </div>
            <div class="stat-box atu">
                <div class="stat-label">% ATU</div>
                <div class="stat-value" id="statAtu">-</div>
                <div class="stat-trend" id="trendAtu"></div>
                <div class="stat-sub" id="statSubAtu"></div>
            </div>
        </div>

        <div class="toolbar-card mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div id="regionTabs">
                        <button class="tab-region active" data-region="" onclick="setRegion('', this)">Nasional</button>
                    </div>
                    <div class="mt-2">
                        <select id="plantSelect" class="form-select form-select-sm" style="max-width:280px; display:inline-block;" onchange="setPlant(this.value)">
                            <option value="">-- Semua Plant --</option>
                        </select>
                        <input type="month" id="monthSelect" class="form-control form-control-sm" style="max-width:170px; display:inline-block; margin-left:8px;" onchange="setMonth(this.value)">
                    </div>
                </div>

                <div class="d-flex align-items-end gap-2 flex-wrap">
                    <div>
                        <select id="uploadPlantSelect" class="form-select form-select-sm" style="min-width:180px;">
                            <option value="">-- Pilih Plant --</option>
                        </select>
                    </div>
                    <button class="btn btn-upload btn-sm text-white" onclick="mulaiUploadExcel()">
                        <i class="fa-solid fa-file-arrow-up"></i> Upload Excel
                    </button>
                    <input type="file" id="excelUploadInput" accept=".xlsx,.xls" style="display:none;" onchange="handleExcelUpload(this)">
                </div>
            </div>
            <div class="info-period mt-2" id="infoPeriod"></div>
        </div>

        <div class="chart-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0" id="chartTitle">Nasional - Semua Plant</h6>
                <span class="badge" style="background: var(--accent-soft); color: var(--accent);" id="chartScopeBadge">Nasional</span>
            </div>
            <div id="chartWrapper">
                <div class="chart-panel">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <div class="chart-panel-title mb-0">% Defect Farm &amp; % Defect Proses per Minggu</div>
                        <div id="sizeTabs">
                            <button class="size-pill active" data-size="" onclick="setSize('', this)">Semua (rata-rata)</button>
                            <button class="size-pill" data-size="AK" onclick="setSize('AK', this)">AK</button>
                            <button class="size-pill" data-size="AM" onclick="setSize('AM', this)">AM</button>
                            <button class="size-pill" data-size="AB" onclick="setSize('AB', this)">AB</button>
                            <button class="size-pill" data-size="AJ" onclick="setSize('AJ', this)">AJ</button>
                        </div>
                    </div>
                    <div class="chart-box"><canvas id="fxCanvas" class="fx-canvas"></canvas><canvas id="chartOverview"></canvas></div>
                </div>
            </div>
            <div class="empty-state" id="emptyState" style="display:none;">
                <i class="fa-solid fa-folder-open"></i>
                <p class="mb-1 fw-semibold">Belum ada data ATU bulan ini.</p>
                <p class="mb-0 small">Silakan upload file Excel rekap ATU untuk menampilkan grafik.</p>
            </div>
        </div>

        <div class="table-card p-3" id="tableCard" style="display:none;">
            <h6 class="fw-bold mb-1">Detail per Plant (klik baris untuk lihat rincian per minggu)</h6>
            <div class="small text-muted mb-3">Kolom AK/AM/AB/AJ = % Defect Farm per size. % ATU = total satu plant (semua size). Badge UPDATE = plant punya data di bulan terpilih.</div>
            <table class="atu-table">
                <thead>
                    <tr>
                        <th>Plant</th>
                        <th>AK</th>
                        <th>AM</th>
                        <th>AB</th>
                        <th>AJ</th>
                        <th>% ATU</th>
                    </tr>
                </thead>
                <tbody id="plantTableBody"></tbody>
            </table>
        </div>

    </div>

    <script>
        Chart.register(ChartDataLabels);
        Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
        Chart.defaults.color = '#8b8aa3';

        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const ROUTES = {
            upload: `{{ route('slaughter.atu.upload') }}`,
            cekKode: `{{ route('slaughter.atu.cek-kode') }}`,
            data: `{{ route('slaughter.atu.data') }}`,
            filterOptions: `{{ route('slaughter.atu.filter-options') }}`,
        };

        let currentRegion = '';
        let currentPlant = '';
        let currentSize = ''; // '' = semua size (rata-rata)
        const now = new Date();
        let currentBulan = now.getMonth() + 1;
        let currentTahun = now.getFullYear();

        let plantsByRegion = {};
        let allPlantsByRegion = {};
        let chartOverviewInstance = null;   // chart atas (Farm & Proses)
        let detailCharts = {};              // chart per size di detail plant: { [plant]: { farm, proses } }
        let currentChartRows = [];          // baris untuk chart atas (mengikuti filter plant)
        let currentScopedRows = [];         // baris untuk tabel & detail plant
        let expandedPlants = new Set();

        let pendingKodeOtorisasi = '';
        let trendToken = 0; // mencegah hasil lama menimpa hasil baru saat filter cepat berganti

        const SIZE_ORDER = ['AK', 'AM', 'AB', 'AJ'];
        // Satu warna selalu berarti satu size, di kedua grafik
        const SIZE_COLORS = { AK: '#6366f1', AM: '#10b981', AB: '#0ea5e9', AJ: '#ec4899' };
        // Warna chart atas: Farm merah, Proses oranye (sama dengan kartu statistik)
        const OVERVIEW_COLORS = { farm: '#ef4444', proses: '#f59e0b' };
        const JAWA_REGIONS = ['Banten', 'Jabar', 'Jateng', 'Jatim'];
        const BULAN_NAMA = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        function formatDateShort(ymd) {
            if (!ymd) return '';
            const d = new Date(String(ymd).slice(0, 10) + 'T00:00:00');
            return d.getDate() + ' ' + BULAN_NAMA[d.getMonth() + 1].slice(0, 3);
        }

        function formatDateFull(ymd) {
            if (!ymd) return '';
            const d = new Date(String(ymd).slice(0, 10) + 'T00:00:00');
            return d.getDate() + ' ' + BULAN_NAMA[d.getMonth() + 1].slice(0, 3) + ' ' + d.getFullYear();
        }

        function escapeHtml(v) {
            return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        }

        function getSelectedUploadPlantLabel() {
            const select = document.getElementById('uploadPlantSelect');
            const opt = select.options[select.selectedIndex];
            return opt ? opt.innerText : '';
        }

        async function mulaiUploadExcel() {
            const plant = document.getElementById('uploadPlantSelect').value;

            if (!plant) {
                Swal.fire({ title: 'Belum lengkap', text: 'Pilih Plant terlebih dahulu.', icon: 'warning' });
                return;
            }

            const plantLabel = getSelectedUploadPlantLabel();

            const { value: kode, isConfirmed } = await Swal.fire({
                title: `Masukkan kode otorisasi Plant ${plantLabel}`,
                input: 'password',
                inputPlaceholder: 'Kode otorisasi',
                inputAttributes: { autocapitalize: 'off', autocomplete: 'new-password' },
                showCancelButton: true,

                inputValidator: (value) => {
                    if (!value || !value.trim()) return 'Kode otorisasi wajib diisi.';
                },
                preConfirm: async (value) => {
                    try {
                        const res = await fetch(ROUTES.cekKode, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({ plant, kode_otorisasi: value.trim() }),
                        });
                        const result = await res.json();
                        if (!res.ok) {
                            Swal.showValidationMessage(result.message || 'Kode otorisasi tidak sesuai.');
                            return false;
                        }
                        return value.trim();
                    } catch (err) {
                        Swal.showValidationMessage('Gagal menghubungi server, coba lagi.');
                        return false;
                    }
                },
                allowOutsideClick: () => !Swal.isLoading(),
            });

            if (!isConfirmed) return;

            pendingKodeOtorisasi = kode;
            document.getElementById('excelUploadInput').click();
        }

        // Kirim file ke server. simpan=false -> hanya pratinjau (tidak ada yang disimpan);
        // simpan=true -> simpan semua minggu di file. Kode otorisasi dicek server di kedua tahap.
        async function kirimFileAtu(file, plant, kodeOtorisasi, simpan) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('plant', plant);
            formData.append('kode_otorisasi', kodeOtorisasi);
            formData.append('simpan', simpan ? '1' : '0');

            const res = await fetch(ROUTES.upload, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: formData,
            });
            const result = await res.json();
            if (!res.ok) throw new Error(result.message || 'Gagal memproses file.');
            return result;
        }

        function daftarMingguHtml(minggu) {
            return `<ul style="text-align:left; margin:12px 0 0; padding-left:20px; font-size:14px;">` +
                minggu.map(m => `
                    <li style="margin-bottom:6px;">
                        <b>Minggu ${m.urutan}</b> (${escapeHtml(m.week_label)}):
                        ${formatDateFull(m.tanggal_mulai)} &ndash; ${formatDateFull(m.tanggal_selesai)}
                        &rarr; <b>${escapeHtml(m.bulan_nama)}</b>
                        ${m.akan_ditimpa ? '<span class="badge bg-warning text-dark ms-1">menimpa data lama</span>' : ''}
                    </li>`).join('') +
                `</ul>`;
        }

        async function handleExcelUpload(input) {
            const file = input.files[0];
            if (!file) return;

            const plant = document.getElementById('uploadPlantSelect').value;
            const plantLabel = getSelectedUploadPlantLabel();
            const kodeOtorisasi = pendingKodeOtorisasi;

            if (!plant || !kodeOtorisasi) {
                Swal.fire({ title: 'Belum lengkap', text: 'Data upload belum lengkap. Silakan klik Upload Excel lagi.', icon: 'warning' });
                input.value = '';
                return;
            }

            try {
                // Tahap 1: baca file & tampilkan pratinjau (belum ada yang disimpan)
                Swal.fire({
                    title: 'Membaca file...',
                    html: 'Mohon tunggu, sedang mendeteksi minggu di dalam file.',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading(),
                });

                const pratinjau = await kirimFileAtu(file, plant, kodeOtorisasi, false);
                const adaTimpa = pratinjau.minggu.some(m => m.akan_ditimpa);

                const konfirmasi = await Swal.fire({
                    title: `Simpan data Plant ${escapeHtml(plantLabel)}?`,
                    html: `<div>Terbaca <b>${pratinjau.minggu.length} minggu</b> di file ini:</div>` +
                        daftarMingguHtml(pratinjau.minggu) +
                        (adaTimpa ? `<div class="small text-muted mt-3">Minggu bertanda kuning sudah punya data dan akan diganti dengan isi file ini.</div>` : ''),
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Simpan',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#4f46e5',
                    allowOutsideClick: false,
                });

                if (!konfirmasi.isConfirmed) return; // user batal, tidak ada yang disimpan

                // Tahap 2: simpan
                Swal.fire({
                    title: 'Menyimpan...',
                    html: 'Mohon tunggu, sedang menyimpan data.',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading(),
                });

                const result = await kirimFileAtu(file, plant, kodeOtorisasi, true);

                await Swal.fire({
                    title: 'Selesai!',
                    html: `<div>Data Plant <b>${escapeHtml(result.plant)}</b> berhasil disimpan (${result.minggu.length} minggu).</div>` +
                        daftarMingguHtml(result.minggu),
                    icon: 'success',
                });

                currentBulan = result.bulan;
                currentTahun = result.tahun;
                document.getElementById('monthSelect').value = `${result.tahun}-${String(result.bulan).padStart(2, '0')}`;

                await loadFilterOptions();
                await refreshView();
            } catch (err) {
                Swal.fire({ title: 'Gagal', text: err.message, icon: 'error' });
            } finally {
                input.value = '';
                pendingKodeOtorisasi = ''; // kode sekali pakai per percobaan upload
            }
        }

        async function loadFilterOptions() {
            const res = await fetch(ROUTES.filterOptions);
            const opts = await res.json();
            plantsByRegion = opts.plants_by_region || {};
            allPlantsByRegion = opts.all_plants_by_region || {};
            populateUploadPlantDropdown(allPlantsByRegion);

            const tabWrap = document.getElementById('regionTabs');
            tabWrap.querySelectorAll('.tab-region[data-region]:not([data-region=""])').forEach(el => el.remove());

            Object.keys(allPlantsByRegion).forEach(region => {
                const btn = document.createElement('button');
                btn.className = 'tab-region';
                btn.dataset.region = region;
                btn.innerText = region;
                btn.onclick = () => setRegion(region, btn);
                tabWrap.appendChild(btn);
            });

            const jawaBtn = document.createElement('button');
            jawaBtn.className = 'tab-region';
            jawaBtn.dataset.region = '__JAWA__';
            jawaBtn.innerText = 'Jawa';
            jawaBtn.onclick = () => setRegion('__JAWA__', jawaBtn);
            const jatimBtn = tabWrap.querySelector('[data-region="Jatim"]');
            if (jatimBtn) jatimBtn.insertAdjacentElement('afterend', jawaBtn);
            else tabWrap.appendChild(jawaBtn);

            populatePlantDropdown();

            const monthInput = document.getElementById('monthSelect');
            if (!monthInput.value) {
                monthInput.value = `${currentTahun}-${String(currentBulan).padStart(2, '0')}`;
            }
        }

        function populatePlantDropdown() {
            const select = document.getElementById('plantSelect');
            select.innerHTML = `<option value="">-- Semua Plant ${currentRegion ? 'di ' + (currentRegion === '__JAWA__' ? 'Jawa' : currentRegion) : '(Nasional)'} --</option>`;
            let plantList = [];
            if (currentRegion === '') {
                Object.values(plantsByRegion).forEach(list => plantList.push(...list));
            } else if (currentRegion === '__JAWA__') {
                JAWA_REGIONS.forEach(r => plantList.push(...(plantsByRegion[r] || [])));
            } else {
                plantList = plantsByRegion[currentRegion] || [];
            }
            plantList.sort();
            plantList.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p;
                opt.innerText = p;
                select.appendChild(opt);
            });
        }

        function populateUploadPlantDropdown(allPlantsByRegionMap) {
            const select = document.getElementById('uploadPlantSelect');
            select.innerHTML = `<option value="">-- Pilih Plant --</option>`;
            Object.keys(allPlantsByRegionMap).sort().forEach(region => {
                const optgroup = document.createElement('optgroup');
                optgroup.label = region;
                allPlantsByRegionMap[region].slice().sort().forEach(plant => {
                    const opt = document.createElement('option');
                    opt.value = plant;
                    opt.innerText = plant;
                    optgroup.appendChild(opt);
                });
                select.appendChild(optgroup);
            });
        }

        function setMonth(value) {
            if (!value) return;
            const [tahun, bulan] = value.split('-');
            currentTahun = parseInt(tahun, 10);
            currentBulan = parseInt(bulan, 10);
            refreshView();
        }

        function setRegion(region, btnEl) {
            currentRegion = region;
            currentPlant = '';
            document.querySelectorAll('.tab-region').forEach(b => b.classList.remove('active'));
            btnEl.classList.add('active');
            populatePlantDropdown();
            refreshView();
        }

        function setPlant(plant) {
            currentPlant = plant;
            refreshView();
        }

        // Pilih size untuk chart atas (hanya satu size, '' = rata-rata semua size)
        function setSize(size, btnEl) {
            currentSize = size;
            document.querySelectorAll('.size-pill').forEach(b => b.classList.remove('active'));
            btnEl.classList.add('active');
            if (currentChartRows.length) {
                renderChart(aggregateOverviewByWeek(currentChartRows, currentSize));
            }
        }

        // Query data untuk tabel: TIDAK menyertakan filter plant, supaya semua plant di scope
        // tetap punya data (dibutuhkan untuk badge UPDATE / TIDAK ADA DATA). Filter plant
        // untuk chart & kartu statistik dilakukan di sisi client (lihat refreshView).
        function buildQuery() {
            const params = new URLSearchParams();
            if (currentRegion && currentRegion !== '__JAWA__') params.set('region', currentRegion);
            params.set('bulan', currentBulan);
            params.set('tahun', currentTahun);
            return params.toString();
        }

        function updateChartTitle() {
            let title = 'Nasional - Semua Plant';
            if (currentPlant) title = currentPlant;
            else if (currentRegion === '__JAWA__') title = 'Jawa - Semua Plant';
            else if (currentRegion) title = 'Region ' + currentRegion + ' - Semua Plant';
            document.getElementById('chartTitle').innerText = title;

            const scopeText = currentPlant || (currentRegion === '__JAWA__' ? 'Jawa' : currentRegion) || 'Nasional';
            document.getElementById('chartScopeBadge').innerText = scopeText;
        }

        // Ringkas seluruh baris (semua plant/size/minggu dalam scope) jadi 1 angka
        // %Defect Farm, %Defect Proses, %ATU (weighted terhadap jumlah_lb).
        function aggregateOverall(rows) {
            let lb = 0, farmEkor = 0, prosesEkor = 0, atuEkor = 0;
            rows.forEach(r => {
                lb += Number(r.jumlah_lb || 0);
                farmEkor += Number(r.defect_farm_ekor || 0);
                prosesEkor += Number(r.defect_proses_ekor || 0);
                atuEkor += Number(r.atu_ekor || 0);
            });
            return {
                persen_farm: lb > 0 ? farmEkor / lb : 0,
                persen_proses: lb > 0 ? prosesEkor / lb : 0,
                persen_atu: lb > 0 ? atuEkor / lb : 0,
            };
        }

        // Untuk chart atas: per minggu, 2 nilai saja (%Defect Farm & %Defect Proses).
        // size '' -> rata-rata semua size (weighted: total ekor / total LB); size tertentu -> hanya size itu.
        // Minggu yang LB-nya 0 diberi null (garis putus), bukan 0%.
        function aggregateOverviewByWeek(rows, size) {
            const bucket = {};
            rows.forEach(r => {
                const key = r.tanggal_mulai;
                if (!bucket[key]) bucket[key] = { tanggal_mulai: key, tanggal_selesai: r.tanggal_selesai, lb: 0, farmEkor: 0, prosesEkor: 0 };
                if (size && r.size !== size) return;
                const b = bucket[key];
                b.lb += Number(r.jumlah_lb || 0);
                b.farmEkor += Number(r.defect_farm_ekor || 0);
                b.prosesEkor += Number(r.defect_proses_ekor || 0);
            });

            const weeks = Object.values(bucket).sort((a, b) => a.tanggal_mulai.localeCompare(b.tanggal_mulai));
            const labels = weeks.map((w, idx) => 'Minggu ' + (idx + 1));
            const rentang = weeks.map(w => formatDateShort(w.tanggal_mulai) + ' \u2013 ' + formatDateShort(w.tanggal_selesai));
            const farm = weeks.map(w => w.lb > 0 ? Number(((w.farmEkor / w.lb) * 100).toFixed(2)) : null);
            const proses = weeks.map(w => w.lb > 0 ? Number(((w.prosesEkor / w.lb) * 100).toFixed(2)) : null);

            return { labels, rentang, farm, proses };
        }

        // Kelompokkan baris jadi per minggu (tanggal_mulai) -> per size, untuk sumbu-X & garis line chart.
        // Persen dihitung dari total ekor / total LB (weighted). Size yang LB-nya 0 di minggu tertentu
        // diberi nilai null (garis putus), bukan 0%, supaya tidak terbaca seolah-olah tidak ada defect.
        function aggregateByWeekSize(rows) {
            const bucket = {};
            rows.forEach(r => {
                const key = r.tanggal_mulai;
                if (!bucket[key]) bucket[key] = { tanggal_mulai: key, tanggal_selesai: r.tanggal_selesai, sizes: {} };
                if (!bucket[key].sizes[r.size]) bucket[key].sizes[r.size] = { lb: 0, farmEkor: 0, prosesEkor: 0 };
                const x = bucket[key].sizes[r.size];
                x.lb += Number(r.jumlah_lb || 0);
                x.farmEkor += Number(r.defect_farm_ekor || 0);
                x.prosesEkor += Number(r.defect_proses_ekor || 0);
            });

            const weeks = Object.values(bucket).sort((a, b) => a.tanggal_mulai.localeCompare(b.tanggal_mulai));
            const labels = weeks.map((w, idx) => 'Minggu ' + (idx + 1));
            const rentang = weeks.map(w => formatDateShort(w.tanggal_mulai) + ' \u2013 ' + formatDateShort(w.tanggal_selesai));

            const series = {};
            SIZE_ORDER.forEach(sz => {
                const farm = weeks.map(w => {
                    const x = w.sizes[sz];
                    return x && x.lb > 0 ? Number(((x.farmEkor / x.lb) * 100).toFixed(2)) : null;
                });
                const proses = weeks.map(w => {
                    const x = w.sizes[sz];
                    return x && x.lb > 0 ? Number(((x.prosesEkor / x.lb) * 100).toFixed(2)) : null;
                });
                // Size tanpa data sama sekali di bulan ini tidak digambar
                if (farm.some(v => v !== null)) series[sz] = { farm, proses };
            });

            return { labels, rentang, series };
        }

        // Kelompokkan baris jadi per plant: %Defect Farm per size + %ATU total plant (agregat sebulan).
        // ATU di Excel berlaku untuk seluruh plant (bukan per size), jadi dihitung dari total ATU / total LB semua size.
        function groupByPlantSize(rows) {
            const bucket = {};
            rows.forEach(r => {
                if (!bucket[r.plant]) bucket[r.plant] = { sizes: {}, lb: 0, atuEkor: 0 };
                const b = bucket[r.plant];
                if (!b.sizes[r.size]) b.sizes[r.size] = { lb: 0, farmEkor: 0 };
                b.sizes[r.size].lb += Number(r.jumlah_lb || 0);
                b.sizes[r.size].farmEkor += Number(r.defect_farm_ekor || 0);
                b.lb += Number(r.jumlah_lb || 0);
                b.atuEkor += Number(r.atu_ekor || 0);
            });
            const map = {};
            Object.keys(bucket).forEach(plant => {
                const b = bucket[plant];
                map[plant] = { sizes: {}, persen_atu: b.lb > 0 ? b.atuEkor / b.lb : 0 };
                Object.keys(b.sizes).forEach(size => {
                    const x = b.sizes[size];
                    map[plant].sizes[size] = { persen_farm: x.lb > 0 ? x.farmEkor / x.lb : 0 };
                });
            });
            return map;
        }

        // Kelompokkan baris jadi per plant -> per minggu -> per size (data mentah lengkap, utk expand card)
        function groupByPlantWeekSize(rows) {
            const map = {};
            rows.forEach(r => {
                if (!map[r.plant]) map[r.plant] = {};
                const wkey = r.tanggal_mulai;
                if (!map[r.plant][wkey]) {
                    map[r.plant][wkey] = {
                        tanggal_mulai: r.tanggal_mulai,
                        tanggal_selesai: r.tanggal_selesai,
                        week_label: r.week_label,
                        sizes: {},
                    };
                }
                map[r.plant][wkey].sizes[r.size] = r;
            });
            const result = {};
            Object.keys(map).forEach(plant => {
                const weeks = Object.values(map[plant]).sort((a, b) => a.tanggal_mulai.localeCompare(b.tanggal_mulai));
                result[plant] = weeks.map((w, idx) => ({ ...w, urutan: idx + 1 }));
            });
            return result;
        }

        function getPlantsForCurrentScope() {
            let list = [];
            if (currentRegion === '') {
                Object.values(allPlantsByRegion).forEach(arr => list.push(...arr));
            } else if (currentRegion === '__JAWA__') {
                JAWA_REGIONS.forEach(r => list.push(...(allPlantsByRegion[r] || [])));
            } else {
                list = allPlantsByRegion[currentRegion] || [];
            }
            return Array.from(new Set(list)).sort();
        }

        let currentWeekDetailCache = {};

        async function refreshView() {
            updateChartTitle();
            const qs = buildQuery();

            try {
                const res = await fetch(`${ROUTES.data}?${qs}`);
                const rows = await res.json();

                // Pastikan tanggal selalu format Y-m-d (backend bisa mengirim format ISO datetime)
                rows.forEach(r => {
                    r.tanggal_mulai = String(r.tanggal_mulai).slice(0, 10);
                    r.tanggal_selesai = String(r.tanggal_selesai).slice(0, 10);
                });

                let scopedRows = rows;
                if (currentRegion === '__JAWA__') {
                    scopedRows = rows.filter(r => JAWA_REGIONS.includes(r.region));
                }

                // Chart & kartu statistik mengikuti filter plant; tabel selalu memakai semua plant di scope
                const rowsForChart = currentPlant ? scopedRows.filter(r => r.plant === currentPlant) : scopedRows;
                const isEmpty = rowsForChart.length === 0;
                showChartEmptyState(isEmpty);

                document.getElementById('infoPeriod').innerText = `Periode: ${BULAN_NAMA[currentBulan]} ${currentTahun}`;

                if (!isEmpty) {
                    currentChartRows = rowsForChart;
                    const overall = aggregateOverall(rowsForChart);
                    updateStatStrip(overall);
                    updateTrend(overall); // tidak di-await supaya chart & tabel tidak menunggu
                    renderChart(aggregateOverviewByWeek(rowsForChart, currentSize));
                } else {
                    currentChartRows = [];
                }

                currentScopedRows = scopedRows || [];
                const plantSizeMap = groupByPlantSize(scopedRows || []);
                currentWeekDetailCache = groupByPlantWeekSize(scopedRows || []);
                renderPlantTable(plantSizeMap);
                document.getElementById('tableCard').style.display = 'block';
            } catch (err) {
                console.error(err);
                showChartEmptyState(true);
                document.getElementById('tableCard').style.display = 'none';
            }
        }

        function showChartEmptyState(isEmpty) {
            document.getElementById('emptyState').style.display = isEmpty ? 'block' : 'none';
            document.getElementById('chartWrapper').style.display = isEmpty ? 'none' : 'block';
            document.getElementById('statStrip').style.display = isEmpty ? 'none' : 'grid';
        }

        function updateStatStrip(overall) {
            const scopeLabel = currentPlant ? currentPlant : (currentRegion ? currentRegion : 'Nasional');
            document.getElementById('statFarm').innerText = (overall.persen_farm * 100).toFixed(1) + '%';
            document.getElementById('statProses').innerText = (overall.persen_proses * 100).toFixed(1) + '%';
            document.getElementById('statAtu').innerText = (overall.persen_atu * 100).toFixed(1) + '%';
            const sub = scopeLabel + ' \u00b7 ' + BULAN_NAMA[currentBulan] + ' ' + currentTahun;
            document.getElementById('statSubFarm').innerText = sub;
            document.getElementById('statSubProses').innerText = sub;
            document.getElementById('statSubAtu').innerText = sub;
        }

        function getPrevPeriod() {
            let bulan = currentBulan - 1, tahun = currentTahun;
            if (bulan < 1) { bulan = 12; tahun -= 1; }
            return { bulan, tahun };
        }

        // Ambil angka ringkasan bulan sebelumnya dengan filter (region/Jawa/plant) yang sama.
        // Mengembalikan null kalau bulan sebelumnya belum ada data.
        async function fetchPrevOverall() {
            const prev = getPrevPeriod();
            const params = new URLSearchParams();
            if (currentRegion && currentRegion !== '__JAWA__') params.set('region', currentRegion);
            if (currentPlant) params.set('plant', currentPlant);
            params.set('bulan', prev.bulan);
            params.set('tahun', prev.tahun);

            const res = await fetch(`${ROUTES.data}?${params.toString()}`);
            let rows = await res.json();
            if (!Array.isArray(rows)) return null;
            if (currentRegion === '__JAWA__') rows = rows.filter(r => JAWA_REGIONS.includes(r.region));
            if (currentPlant) rows = rows.filter(r => r.plant === currentPlant);
            if (rows.length === 0) return null;
            return aggregateOverall(rows);
        }

        async function updateTrend(overall) {
            const token = ++trendToken;
            ['Farm', 'Proses', 'Atu'].forEach(k => renderTrend(k, null, null, true)); // kosongkan dulu

            let prevOverall = null;
            try { prevOverall = await fetchPrevOverall(); } catch (e) { prevOverall = null; }
            if (token !== trendToken) return; // sudah ada permintaan yang lebih baru

            renderTrend('Farm', overall.persen_farm, prevOverall ? prevOverall.persen_farm : null);
            renderTrend('Proses', overall.persen_proses, prevOverall ? prevOverall.persen_proses : null);
            renderTrend('Atu', overall.persen_atu, prevOverall ? prevOverall.persen_atu : null);
        }

        // Defect/ATU yang lebih rendah = lebih baik, jadi turun = hijau, naik = merah.
        function renderTrend(key, cur, prevVal, reset) {
            const box = document.getElementById('stat' + key).closest('.stat-box');
            const el = document.getElementById('trend' + key);
            box.classList.remove('trend-up', 'trend-down');
            if (reset) { el.innerHTML = ''; return; }

            const prevName = BULAN_NAMA[getPrevPeriod().bulan];
            if (prevVal === null) {
                el.innerHTML = `<span class="trend-badge none">Belum ada data ${prevName}</span>`;
                return;
            }

            const diff = (cur - prevVal) * 100; // selisih dalam poin persentase
            const tip = `Selisih dalam poin persentase dibanding ${prevName} (${(prevVal * 100).toFixed(1)}%)`;
            if (Math.abs(diff) < 0.05) {
                el.innerHTML = `<span class="trend-badge flat" title="${tip}"><i class="fa-solid fa-minus"></i> Tetap</span><span class="trend-vs">vs ${prevName}</span>`;
                return;
            }

            const naik = diff > 0;
            box.classList.add(naik ? 'trend-up' : 'trend-down');
            el.innerHTML = `<span class="trend-badge ${naik ? 'up' : 'down'}" title="${tip}">` +
                `<i class="fa-solid ${naik ? 'fa-arrow-up' : 'fa-arrow-down'}"></i> ${Math.abs(diff).toFixed(1)} pt</span>` +
                `<span class="trend-vs">vs ${prevName}</span>`;
        }

        // Garis bantu vertikal tipis di minggu yang sedang disorot kursor
        const hoverLinePlugin = {
            id: 'hoverLine',
            beforeDatasetsDraw(chart) {
                const active = chart.tooltip && chart.tooltip._active;
                if (!active || !active.length) return;
                const x = active[0].element.x;
                const { top, bottom } = chart.chartArea;
                const c = chart.ctx;
                c.save();
                c.beginPath();
                c.setLineDash([4, 4]);
                c.strokeStyle = '#c9c8e0';
                c.lineWidth = 1;
                c.moveTo(x, top);
                c.lineTo(x, bottom);
                c.stroke();
                c.restore();
            },
        };

        function hexToRgba(hex, alpha) {
            const h = hex.replace('#', '');
            const r = parseInt(h.substring(0, 2), 16);
            const g = parseInt(h.substring(2, 4), 16);
            const b = parseInt(h.substring(4, 6), 16);
            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        }

        // Gradient area di bawah garis: pekat di dekat garis, memudar ke bawah
        function areaGradient(chart, hex) {
            const { ctx, chartArea } = chart;
            if (!chartArea) return hexToRgba(hex, 0.15);
            const g = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
            g.addColorStop(0, hexToRgba(hex, 0.35));
            g.addColorStop(1, hexToRgba(hex, 0.02));
            return g;
        }

        // Chart atas: 2 garis (% Defect Farm & % Defect Proses) dengan area berwarna di bawahnya
        function buildOverviewChart(weekData, oldInstance) {
            if (oldInstance) oldInstance.destroy();
            const ctx = document.getElementById('chartOverview').getContext('2d');
            const { labels, rentang, farm, proses } = weekData;

            const defs = [
                { label: '% Defect Farm', data: farm, color: OVERVIEW_COLORS.farm, align: 'top' },
                { label: '% Defect Proses', data: proses, color: OVERVIEW_COLORS.proses, align: 'bottom' },
            ];

            const datasets = defs.map(d => ({
                label: d.label,
                data: d.data,
                borderColor: d.color,
                backgroundColor: (c) => areaGradient(c.chart, d.color),
                fill: true,
                borderWidth: 3,
                tension: 0.35,
                spanGaps: false,
                pointRadius: 5,
                pointHoverRadius: 8,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: d.color,
                pointBorderWidth: 3,
                datalabels: {
                    align: d.align,
                    anchor: 'center',
                    offset: 8,
                    clip: false,
                    color: d.color,
                    backgroundColor: '#ffffff',
                    borderColor: d.color,
                    borderWidth: 1,
                    borderRadius: 6,
                    padding: { top: 2, bottom: 2, left: 5, right: 5 },
                    font: { weight: 'bold', size: 10 },
                    formatter: (v) => (v === null ? '' : v.toFixed(1) + '%'),
                    display: (c) => c.dataset.data[c.dataIndex] !== null,
                },
            }));

            return new Chart(ctx, {
                type: 'line',
                data: { labels, datasets },
                plugins: [hoverLinePlugin],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    layout: { padding: { top: 26, bottom: 4, left: 4, right: 14 } },
                    animation: { duration: 0 }, // gerakan masuk ditangani CSS (fade-in naik pelan)
                    plugins: {
                        legend: {
                            position: 'top',
                            align: 'end',
                            labels: {
                                usePointStyle: true, pointStyle: 'circle', boxWidth: 8, boxHeight: 8, padding: 16, font: { weight: '600' },
                                generateLabels: (chart) => Chart.defaults.plugins.legend.labels.generateLabels(chart).map(l => ({
                                    ...l,
                                    fillStyle: defs[l.datasetIndex].color,
                                    strokeStyle: defs[l.datasetIndex].color,
                                })),
                            },
                        },
                        tooltip: {
                            backgroundColor: '#1e1b3a',
                            padding: 12,
                            cornerRadius: 10,
                            usePointStyle: true,
                            boxPadding: 4,
                            titleFont: { weight: '700', size: 12 },
                            bodyFont: { size: 12 },
                            filter: (item) => item.parsed.y !== null,
                            callbacks: {
                                title: (items) => items.length ? `${labels[items[0].dataIndex]} \u00b7 ${rentang[items[0].dataIndex]}` : '',
                                label: (c) => ` ${c.dataset.label}: ${c.parsed.y}%`,
                            },
                        },
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            border: { display: false },
                            ticks: { font: { weight: '600' } },
                        },
                        y: {
                            beginAtZero: true,
                            border: { display: false },
                            grid: { color: '#eeeef7', borderDash: [4, 4] },
                            ticks: { padding: 8, callback: (v) => v + '%' },
                        },
                    },
                },
            });
        }

        // ===== Efek chart atas: cahaya (komet) meluncur di sepanjang garis =====
        // Digambar di canvas terpisah (di belakang chart) supaya chart tidak perlu digambar ulang tiap frame.
        const COMET_PERIOD = 5;        // detik per lintasan Minggu 1 -> terakhir
        const COMET_TAIL = 110;        // panjang ekor (px)
        const COMET_TAIL_STEPS = 16;
        let fxRaf = null;

        // Ambil titik-titik sepanjang kurva garis (termasuk lengkungan tension) untuk tahu posisi garis di tiap x
        function sampleCurve(points) {
            const out = [];
            let seg = 0, prev = null;
            for (let i = 0; i < points.length; i++) {
                const p = points[i];
                const valid = p && !p.skip && isFinite(p.x) && isFinite(p.y);
                if (!valid) { if (prev !== null) { seg++; prev = null; } continue; }
                if (prev !== null) {
                    const c1x = prev.cp2x ?? prev.x, c1y = prev.cp2y ?? prev.y;
                    const c2x = p.cp1x ?? p.x,      c2y = p.cp1y ?? p.y;
                    for (let k = 1; k <= 16; k++) {
                        const t = k / 16, mt = 1 - t;
                        out.push({
                            x: mt*mt*mt*prev.x + 3*mt*mt*t*c1x + 3*mt*t*t*c2x + t*t*t*p.x,
                            y: mt*mt*mt*prev.y + 3*mt*mt*t*c1y + 3*mt*t*t*c2y + t*t*t*p.y,
                            seg,
                        });
                    }
                } else {
                    out.push({ x: p.x, y: p.y, seg });
                }
                prev = p;
            }
            return out;
        }

        // Panjang kumulatif sepanjang kurva (celah data tidak dihitung, jadi cahaya "melompati" celah)
        function cumulativeLength(samples) {
            const cum = [0];
            for (let i = 1; i < samples.length; i++) {
                const a = samples[i - 1], b = samples[i];
                cum.push(cum[i - 1] + (a.seg === b.seg ? Math.hypot(b.x - a.x, b.y - a.y) : 0));
            }
            return cum;
        }

        function positionAtDistance(samples, cum, d) {
            for (let i = 1; i < samples.length; i++) {
                if (cum[i] >= d) {
                    const a = samples[i - 1], b = samples[i];
                    const span = cum[i] - cum[i - 1];
                    if (span === 0) return { x: b.x, y: b.y, seg: b.seg };
                    const f = (d - cum[i - 1]) / span;
                    return { x: a.x + (b.x - a.x) * f, y: a.y + (b.y - a.y) * f, seg: b.seg };
                }
            }
            const last = samples[samples.length - 1];
            return { x: last.x, y: last.y, seg: last.seg };
        }

        function drawComet(ctx, samples, cum, color, progress) {
            const total = cum[cum.length - 1];
            if (total <= 0) return null;
            const D = progress * total;
            const head = positionAtDistance(samples, cum, D);

            // memudar halus di awal & akhir lintasan
            const edge = Math.min(1, progress / 0.06, (1 - progress) / 0.06);

            // ekor: ruas-ruas pendek ke belakang, makin tipis dan transparan
            ctx.lineCap = 'round';
            let prevPos = head;
            for (let k = 1; k <= COMET_TAIL_STEPS; k++) {
                const d = D - k * (COMET_TAIL / COMET_TAIL_STEPS);
                if (d < 0) break;
                const pos = positionAtDistance(samples, cum, d);
                if (pos.seg !== head.seg) break;
                const f = 1 - k / COMET_TAIL_STEPS;
                ctx.strokeStyle = hexToRgba(color, 0.5 * f * f * edge);
                ctx.lineWidth = 1 + 6 * f;
                ctx.beginPath();
                ctx.moveTo(prevPos.x, prevPos.y);
                ctx.lineTo(pos.x, pos.y);
                ctx.stroke();
                prevPos = pos;
            }

            // kepala: cahaya lembut berbentuk lingkaran
            const r = 15;
            const g = ctx.createRadialGradient(head.x, head.y, 0, head.x, head.y, r);
            g.addColorStop(0, hexToRgba(color, 0.75 * edge));
            g.addColorStop(0.4, hexToRgba(color, 0.3 * edge));
            g.addColorStop(1, hexToRgba(color, 0));
            ctx.fillStyle = g;
            ctx.beginPath();
            ctx.arc(head.x, head.y, r, 0, Math.PI * 2);
            ctx.fill();
            return head;
        }

        function fxFrame(ts) {
            const chart = chartOverviewInstance;
            if (!chart || !chart.canvas) { fxRaf = null; return; }
            fxRaf = requestAnimationFrame(fxFrame);

            const overlay = document.getElementById('fxCanvas');
            if (!overlay || !chart.canvas.offsetParent) return; // chart sedang disembunyikan

            const dpr = window.devicePixelRatio || 1;
            const w = chart.canvas.clientWidth, h = chart.canvas.clientHeight;
            if (overlay.width !== Math.round(w * dpr) || overlay.height !== Math.round(h * dpr)) {
                overlay.width = Math.round(w * dpr);
                overlay.height = Math.round(h * dpr);
                overlay.style.width = w + 'px';
                overlay.style.height = h + 'px';
            }
            const ctx = overlay.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            ctx.clearRect(0, 0, w, h);

            const area = chart.chartArea;
            if (!area) return;
            const t = ts / 1000;

            chart.data.datasets.forEach((ds, di) => {
                if (!chart.isDatasetVisible(di)) return;
                const samples = sampleCurve(chart.getDatasetMeta(di).data);
                if (samples.length < 2) return;
                const cum = cumulativeLength(samples);

                // garis kedua digeser fase supaya cahayanya tidak berjalan serempak
                const progress = ((t / COMET_PERIOD) + di * 0.45) % 1;
                drawComet(ctx, samples, cum, ds.borderColor, progress);
            });
        }

        function startFx() {
            // Hormati pengaturan "kurangi gerakan" di perangkat pengguna: chart tampil statis
            if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            if (fxRaf === null) fxRaf = requestAnimationFrame(fxFrame);
        }

        function buildSizeLineChart(canvasId, weekData, metricKey, oldInstance) {
            if (oldInstance) oldInstance.destroy();
            const ctx = document.getElementById(canvasId).getContext('2d');
            const { labels, rentang, series } = weekData;

            const datasets = Object.keys(series).map((sz, idx) => ({
                label: sz,
                data: series[sz][metricKey],
                borderColor: SIZE_COLORS[sz],
                backgroundColor: SIZE_COLORS[sz],
                borderWidth: 3,
                tension: 0.35,
                spanGaps: false,
                pointRadius: 5,
                pointHoverRadius: 8,
                pointBackgroundColor: '#ffffff',   // titik berbentuk cincin: isi putih, tepi berwarna
                pointBorderColor: SIZE_COLORS[sz],
                pointBorderWidth: 3,
                datalabels: {
                    // label kecil berlatar putih, bergantian di atas/bawah titik supaya tidak saling tumpuk
                    align: idx % 2 === 0 ? 'top' : 'bottom',
                    anchor: 'center',
                    offset: 8,
                    clip: false,
                    color: SIZE_COLORS[sz],
                    backgroundColor: '#ffffff',
                    borderColor: SIZE_COLORS[sz],
                    borderWidth: 1,
                    borderRadius: 6,
                    padding: { top: 2, bottom: 2, left: 5, right: 5 },
                    font: { weight: 'bold', size: 10 },
                    formatter: (v) => (v === null ? '' : v.toFixed(1) + '%'),
                    display: (c) => c.dataset.data[c.dataIndex] !== null,
                },
            }));

            return new Chart(ctx, {
                type: 'line',
                data: { labels, datasets },
                plugins: [hoverLinePlugin],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    layout: { padding: { top: 26, bottom: 4, left: 4, right: 14 } },
                    animation: { duration: 0 }, // gerakan masuk ditangani CSS (fade-in naik pelan)
                    plugins: {
                        legend: {
                            position: 'top',
                            align: 'end',
                            labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, boxHeight: 8, padding: 16, font: { weight: '600' } },
                        },
                        tooltip: {
                            backgroundColor: '#1e1b3a',
                            padding: 12,
                            cornerRadius: 10,
                            usePointStyle: true,
                            boxPadding: 4,
                            titleFont: { weight: '700', size: 12 },
                            bodyFont: { size: 12 },
                            filter: (item) => item.parsed.y !== null,
                            callbacks: {
                                title: (items) => items.length ? `${labels[items[0].dataIndex]} \u00b7 ${rentang[items[0].dataIndex]}` : '',
                                label: (c) => ` ${c.dataset.label}: ${c.parsed.y}%`,
                            },
                        },
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            border: { display: false },
                            ticks: { font: { weight: '600' } },
                        },
                        y: {
                            beginAtZero: true,
                            border: { display: false },
                            grid: { color: '#eeeef7', borderDash: [4, 4] },
                            ticks: { padding: 8, callback: (v) => v + '%' },
                        },
                    },
                },
            });
        }

        function renderChart(weekData) {
            // Putar ulang animasi fade-in (naik pelan) setiap grafik digambar ulang
            document.querySelectorAll('#chartWrapper .chart-box').forEach(el => {
                el.classList.remove('fade-in');
                void el.offsetWidth; // paksa reflow supaya animasi mulai dari awal
                el.classList.add('fade-in');
            });
            chartOverviewInstance = buildOverviewChart(weekData, chartOverviewInstance);
            startFx();
        }

        // Hancurkan chart per size di detail plant (dipanggil sebelum tabel dirender ulang)
        function destroyDetailCharts() {
            Object.values(detailCharts).forEach(c => {
                if (c.farm) c.farm.destroy();
                if (c.proses) c.proses.destroy();
            });
            detailCharts = {};
        }

        function pctChip(value) {
            const pct = (value * 100).toFixed(1);
            return `<span class="pct-chip">${pct}%</span>`;
        }

        // Badge status per plant: UPDATE (hijau neon) kalau punya data di bulan terpilih,
        // TIDAK ADA DATA (abu-abu) kalau tidak ada sama sekali.
        function statusBadge(hasData) {
            return hasData
                ? `<span class="status-badge on">Update</span>`
                : `<span class="status-badge off">Tidak ada data</span>`;
        }

        function renderPlantTable(plantMap) {
            const tbody = document.getElementById('plantTableBody');
            const allPlants = getPlantsForCurrentScope();

            // Canvas lama akan hilang saat tabel dirender ulang, jadi chart lamanya dibersihkan dulu
            destroyDetailCharts();

            if (allPlants.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-3">Tidak ada plant terdaftar untuk region ini.</td></tr>`;
                return;
            }

            tbody.innerHTML = allPlants.map((plant, plantIdx) => {
                const plantData = plantMap[plant] || { sizes: {}, persen_atu: null };
                const sizes = plantData.sizes;
                const isExpanded = expandedPlants.has(plant);
                const hasData = Object.keys(sizes).length > 0;

                const mainRow = `
                    <tr class="plant-row" onclick="togglePlantRow('${plant.replace(/'/g, "\\'")}')">
                        <td>
                            <div class="plant-cell">
                                <span class="plant-name">
                                    <i class="fa-solid fa-chevron-down expand-icon ${isExpanded ? 'open' : ''}"></i>
                                    ${plant}
                                </span>
                                ${statusBadge(hasData)}
                            </div>
                        </td>
                        ${SIZE_ORDER.map(sz => `<td>${sizes[sz] ? pctChip(sizes[sz].persen_farm) : '-'}</td>`).join('')}
                        <td>${plantData.persen_atu !== null && hasData ? pctChip(plantData.persen_atu) : '-'}</td>
                    </tr>
                `;

                const detailRow = isExpanded ? `
                    <tr class="detail-row">
                        <td colspan="6">
                            ${renderDetailChartsForPlant(plant, plantIdx)}
                            <div class="week-cards">
                                ${renderWeekCardsForPlant(plant)}
                            </div>
                        </td>
                    </tr>
                ` : '';

                return mainRow + detailRow;
            }).join('');

            // Gambar chart per size untuk setiap plant yang sedang terbuka (setelah canvas ada di DOM)
            allPlants.forEach((plant, plantIdx) => {
                if (!expandedPlants.has(plant)) return;
                const farmCanvas = document.getElementById('detailFarm_' + plantIdx);
                const prosesCanvas = document.getElementById('detailProses_' + plantIdx);
                if (!farmCanvas || !prosesCanvas) return;

                const weekData = aggregateByWeekSize(currentScopedRows.filter(r => r.plant === plant));
                detailCharts[plant] = {
                    farm: buildSizeLineChart('detailFarm_' + plantIdx, weekData, 'farm', null),
                    proses: buildSizeLineChart('detailProses_' + plantIdx, weekData, 'proses', null),
                };
            });
        }

        // Kerangka 2 chart per size (Farm & Proses) di atas kartu mingguan; digambar di renderPlantTable
        function renderDetailChartsForPlant(plant, plantIdx) {
            const weeks = currentWeekDetailCache[plant] || [];
            if (weeks.length === 0) return '';
            return `
                <div class="detail-charts">
                    <div class="chart-grid">
                        <div class="chart-panel">
                            <div class="chart-panel-title">% Defect Farm per Size</div>
                            <div class="chart-box fade-in"><canvas id="detailFarm_${plantIdx}"></canvas></div>
                        </div>
                        <div class="chart-panel">
                            <div class="chart-panel-title">% Defect Proses per Size</div>
                            <div class="chart-box fade-in"><canvas id="detailProses_${plantIdx}"></canvas></div>
                        </div>
                    </div>
                </div>
            `;
        }

        function renderWeekCardsForPlant(plant) {
            const weeks = currentWeekDetailCache[plant] || [];
            if (weeks.length === 0) {
                return `<div class="no-week-data">Belum ada data ATU untuk plant ini di periode terpilih.</div>`;
            }
            return weeks.map(w => {
                // ATU & Total RM Defect+ATU berlaku untuk seluruh plant (sel gabungan di Excel)
                const sizeRows = Object.values(w.sizes);
                const totalLb = sizeRows.reduce((a, r) => a + Number(r.jumlah_lb || 0), 0);
                const atuEkor = sizeRows.reduce((a, r) => a + Number(r.atu_ekor || 0), 0);
                const totalEkor = sizeRows.reduce((a, r) => a + Number(r.total_defect_atu_ekor || 0), 0);
                const atuPersen = totalLb > 0 ? atuEkor / totalLb : 0;
                const totalPersen = totalLb > 0 ? totalEkor / totalLb : 0;
                return `
                <div class="week-card">
                    <div class="week-title">Minggu ${w.urutan} <span class="text-muted fw-normal">(${w.week_label || ''})</span></div>
                    <div class="week-range">${formatDateShort(w.tanggal_mulai)} - ${formatDateShort(w.tanggal_selesai)}</div>
                    <table class="mini-table">
                        <thead>
                            <tr><th>Metrik</th>${SIZE_ORDER.map(sz => `<th>${sz}</th>`).join('')}</tr>
                        </thead>
                        <tbody>
                            <tr><td>Defect Farm (ekor)</td>${SIZE_ORDER.map(sz => `<td>${fmtNum(w.sizes[sz]?.defect_farm_ekor)}</td>`).join('')}</tr>
                            <tr><td>Defect Proses (ekor)</td>${SIZE_ORDER.map(sz => `<td>${fmtNum(w.sizes[sz]?.defect_proses_ekor)}</td>`).join('')}</tr>
                            <tr class="persen-row"><td>% ATU</td><td colspan="4">${fmtPct(atuPersen)}</td></tr>
                            <tr class="persen-row"><td>% Total Defect+ATU</td><td colspan="4">${fmtPct(totalPersen)}</td></tr>
                        </tbody>
                    </table>
                </div>
            `;
            }).join('');
        }

        function fmtNum(v) {
            if (v === undefined || v === null) return '-';
            return Number(v).toLocaleString('id-ID', { maximumFractionDigits: 0 });
        }
        function fmtPct(v) {
            if (v === undefined || v === null) return '-';
            return (Number(v) * 100).toFixed(1) + '%';
        }

        function togglePlantRow(plant) {
            if (expandedPlants.has(plant)) {
                expandedPlants.delete(plant);
            } else {
                expandedPlants.add(plant);
            }
            currentPlant = plant;
            document.getElementById('plantSelect').value = plant;
            refreshView();
        }

        (async function init() {
            await loadFilterOptions();
            await refreshView();
        })();
    </script>

</body>
</html>