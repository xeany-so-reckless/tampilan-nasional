<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Rekap Uniformity Mingguan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>

    <style>
        :root {
            --ok: #16a34a;
            --warn: #f59e0b;
            --danger: #f87171;
            --line: #e5e7eb;
            --muted: #7d8ea1;
        }
        body { background: #f4f6f8; }
        .page-header {
            background: linear-gradient(135deg, #005f73, #0a9396);
            color: #fff;
            padding: 30px 0;
        }
        .back-link { color: #d7f5f7; text-decoration: none; font-size: 0.85rem; }
        .back-link:hover { color: #fff; }

        .toolbar-card, .chart-card, .table-card {
            background: #fff;
            border-radius: 10px;
            border: 1px solid var(--line);
        }
        .toolbar-card { padding: 16px 20px; }
        .chart-card { padding: 24px; }

        /* Stat strip ala Warehouse */
        .stat-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }
        .stat-box {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 16px 18px;
        }
        .stat-label {
            font-size: 0.68rem;
            color: var(--muted);
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 6px;
            font-weight: 700;
        }
        .stat-value {
            font-weight: 800;
            font-size: 1.9rem;
            color: #1e293b;
        }
        .stat-box.ok .stat-value { color: var(--ok); }
        .stat-box.warn .stat-value { color: var(--warn); }
        .stat-box.danger .stat-value { color: #b91c1c; }
        .stat-sub { font-size: 0.72rem; color: var(--muted); margin-top: 2px; }

        .tab-region {
            border: 1px solid #0a9396;
            color: #0a9396;
            background: #fff;
            border-radius: 20px;
            padding: 6px 16px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-right: 6px;
            margin-bottom: 6px;
        }
        .tab-region.active { background: #0a9396; color: #fff; }

        .btn-upload { background: #16a34a; border-color: #16a34a; }
        .btn-upload:hover { background: #15803d; border-color: #15803d; }

        .btn-compare {
            background: #fff;
            border: 1px solid #0a9396;
            color: #0a9396;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            padding: 6px 14px;
            white-space: nowrap;
        }
        .btn-compare:hover { background: #0a9396; color: #fff; }

        .empty-state { text-align: center; padding: 60px 20px; color: var(--muted); }
        .empty-state i { font-size: 3rem; margin-bottom: 14px; display: block; color: #c3cdd6; }

        .info-week { font-size: 0.85rem; color: #556; }

        /* Banner mode Perbandingan Bulan / Tren Setahun */
        .comparison-banner {
            background: linear-gradient(135deg, #0a9396, #005f73);
            color: #fff;
            border-radius: 10px;
            padding: 12px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            font-size: 0.85rem;
            font-weight: 600;
            flex-wrap: wrap;
        }
        .comparison-banner .btn-exit-compare {
            background: rgba(255,255,255,.15);
            border: 1px solid rgba(255,255,255,.5);
            color: #fff;
            border-radius: 6px;
            font-size: 0.78rem;
            padding: 5px 12px;
            white-space: nowrap;
        }
        .comparison-banner .btn-exit-compare:hover { background: rgba(255,255,255,.3); }

        /* Sembunyikan kolom Status & Aksi di tabel saat mode Perbandingan Bulan / Tren Setahun aktif,
           karena kolom itu maknanya spesifik minggu berjalan / detail per-minggu,
           tidak relevan saat menampilkan agregat bulanan. */
        .table-card.compare-active .col-status,
        .table-card.compare-active .col-aksi { display: none; }

        /* Filter normal (region/plant/week) di-nonaktifkan visual saat mode Perbandingan aktif */
        .filters-disabled { pointer-events: none; opacity: 0.5; }

        /* Tombol pilih size (Semua / AK / AM / AB / AJ) di atas chart mode Tren Setahun */
        .size-toggle {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 14px;
        }
        .size-toggle button {
            border: 1px solid #0a9396;
            color: #0a9396;
            background: #fff;
            border-radius: 16px;
            padding: 4px 14px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .size-toggle button:hover { background: #e6f6f6; }
        .size-toggle button.active { background: #0a9396; color: #fff; }

        /* Table */
        table.uniformity-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        table.uniformity-table thead th {
            background: #f8fafc;
            color: var(--muted);
            font-size: 0.7rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            text-align: center;
            padding: 10px;
            border-bottom: 1px solid var(--line);
        }
        table.uniformity-table thead th:first-child { text-align: left; }
        table.uniformity-table tbody td { padding: 10px; border-bottom: 1px solid var(--line); text-align: center; vertical-align: middle; }
        table.uniformity-table tbody td:first-child { text-align: left; font-weight: 600; }
        .plant-row { cursor: pointer; }
        .plant-row:hover { background: #f8fafc; }
        /* Baris bulan di Tren Setahun: tidak bisa diklik, dibuat sedikit berwarna supaya tiap bulan mudah dibedakan saat scroll */
        .month-row td { background: #eef6f6; }

        .pct-chip {
            display: inline-block;
            font-weight: 700;
            font-size: 0.8rem;
            padding: 3px 10px;
            border-radius: 20px;
        }
        .pct-chip.ok { background: rgba(22,163,74,.12); color: var(--ok); }
        .pct-chip.bad { background: rgba(248,113,113,.15); color: #b91c1c; }

        .expand-icon { transition: transform .15s ease; color: var(--muted); }
        .expand-icon.open { transform: rotate(180deg); }

        .detail-row td { padding: 0 !important; background: #f8fafc; }
        .size-breakdown { display: flex; gap: 10px; padding: 14px 16px; flex-wrap: wrap; }
        .size-card {
            flex: 1;
            min-width: 170px;
            background: #fff;
            border: 1px solid var(--line);
            border-left: 4px solid var(--sc);
            border-radius: 8px;
            padding: 12px 14px;
        }
        .size-card .label {
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--sc);
            margin-bottom: 6px;
        }
        .size-card .row-metric { display: flex; justify-content: space-between; font-size: 0.78rem; margin-bottom: 3px; }
        .size-card .row-metric b { font-weight: 700; }
        .size-card .gap-line { margin-top: 6px; padding-top: 6px; border-top: 1px dashed var(--line); font-size: 0.75rem; font-weight: 700; }
        .gap-line.ok { color: var(--ok); }
        .gap-line.bad { color: #b91c1c; }

        /* Ikon tren naik/turun/tetap di size-card mode Perbandingan Bulan */
        .trend-arrow {
            font-size: 0.7rem;
            margin-left: 5px;
        }
        .trend-arrow.good { color: #16a34a; }
        .trend-arrow.bad { color: #b91c1c; }
        .trend-arrow.flat { color: var(--muted); }

        /* Grup 1 size di dalam mode Perbandingan Bulan (judul size + 2 size-card berdampingan) */
        .compare-size-group { margin-bottom: 4px; }
        .compare-size-group .compare-size-title {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .btn-detail-plant { white-space: nowrap; }

        .col-status { width: 100px; white-space: nowrap; text-align: center; }

        /* Badge status upload minggu berjalan, ditampilkan di sebelah nama plant */
        .plant-badge {
            display: inline-block;
            font-size: 0.62rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 8px;
            vertical-align: middle;
        }
        .plant-badge.badge-off { background: #e5e7eb; color: #6b7280; }

        /* Badge UPDATE: glow neon hijau berdenyut pelan */
        .plant-badge.badge-update {
            background: rgba(22,163,74,.15);
            color: var(--ok);
            animation: neonPulse 1.8s ease-in-out infinite;
        }
        @keyframes neonPulse {
            0%, 100% {
                box-shadow: 0 0 2px rgba(22,163,74,.35), 0 0 4px rgba(22,163,74,.2);
                text-shadow: none;
            }
            50% {
                box-shadow: 0 0 6px #16a34a, 0 0 12px rgba(22,163,74,.7), 0 0 20px rgba(22,163,74,.4);
                text-shadow: 0 0 6px rgba(22,163,74,.8);
            }
        }
        /* Matikan animasi untuk pengguna yang memilih mengurangi gerakan */
        @media (prefers-reduced-motion: reduce) {
            .plant-badge.badge-update {
                animation: none;
                box-shadow: 0 0 6px rgba(22,163,74,.6);
            }
        }
    </style>
</head>
<body>

    <div class="page-header">
        <div class="container">
            <a href="{{ url('/') }}" class="back-link"><i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard</a>
            <h3 class="fw-bold mt-2 mb-0"><i class="fa-solid fa-chart-pie"></i> Rekap Uniformity Mingguan</h3>
            <p class="mb-0 opacity-75">Modul Slaughter House</p>
        </div>
    </div>

    <div class="container my-4">

        <div id="comparisonBanner" class="comparison-banner" style="display:none;">
            <span id="comparisonBannerText"><i class="fa-solid fa-code-compare"></i></span>
            <button class="btn-exit-compare" onclick="keluarModePerbandingan()">
                <i class="fa-solid fa-xmark"></i> Kembali ke Tampilan Mingguan
            </button>
        </div>

        <div class="stat-strip" id="statStrip">
            <div class="stat-box" id="statBoxAK">
                <div class="stat-label">% LB Standart AK</div>
                <div class="stat-value" id="statAK">-</div>
                <div class="stat-sub" id="statSubAK"></div>
            </div>
            <div class="stat-box" id="statBoxAM">
                <div class="stat-label">% LB Standart AM</div>
                <div class="stat-value" id="statAM">-</div>
                <div class="stat-sub" id="statSubAM"></div>
            </div>
            <div class="stat-box" id="statBoxAB">
                <div class="stat-label">% LB Standart AB</div>
                <div class="stat-value" id="statAB">-</div>
                <div class="stat-sub" id="statSubAB"></div>
            </div>
            <div class="stat-box" id="statBoxAJ">
                <div class="stat-label">% LB Standart AJ</div>
                <div class="stat-value" id="statAJ">-</div>
                <div class="stat-sub" id="statSubAJ"></div>
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
                        <select id="weekSelect" class="form-select form-select-sm" style="max-width:200px; display:inline-block; margin-left:8px;" onchange="setWeek(this.value)">
                            <option value="">-- Minggu Terbaru --</option>
                        </select>
                        <button type="button" class="btn-compare ms-2" onclick="bukaModalPerbandingan()">
                            <i class="fa-solid fa-code-compare"></i> Perbandingan
                        </button>
                        <button type="button" class="btn-compare ms-2" onclick="bukaModalTrenSetahun()">
                            <i class="fa-solid fa-chart-line"></i> Tren Setahun
                        </button>
                    </div>
                </div>

                <div class="d-flex align-items-end gap-2 flex-wrap">
                    <div>
                        <select id="uploadPlantSelect" class="form-select form-select-sm" style="min-width:180px;">
                            <option value="">-- Pilih Plant --</option>
                        </select>
                    </div>
                    <div>
                        <input type="date" id="uploadTanggalInput" class="form-control form-control-sm">
                    </div>
                    <button class="btn btn-upload btn-sm text-white" onclick="mulaiUploadExcel()">
                        <i class="fa-solid fa-file-arrow-up"></i> Upload Excel
                    </button>
                    <input type="file" id="excelUploadInput" accept=".xlsx,.xls" style="display:none;" onchange="handleExcelUpload(this)">
                </div>
            </div>
            <div class="info-week mt-2" id="infoWeek"></div>
        </div>

        <div class="chart-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0" id="chartTitle">Nasional - Semua Plant</h6>
                <span class="badge bg-info text-dark" id="chartScopeBadge">Nasional</span>
            </div>

            <!-- Tombol pilih size, hanya tampil di mode Tren Setahun -->
            <div class="size-toggle" id="yearSizeToggle" style="display:none;">
                <button type="button" data-size="ALL" class="active" onclick="setYearSize('ALL')">Semua</button>
                <button type="button" data-size="AK" onclick="setYearSize('AK')">AK</button>
                <button type="button" data-size="AM" onclick="setYearSize('AM')">AM</button>
                <button type="button" data-size="AB" onclick="setYearSize('AB')">AB</button>
                <button type="button" data-size="AJ" onclick="setYearSize('AJ')">AJ</button>
            </div>

            <div id="chartWrapper">
                <canvas id="uniformityChart" height="100"></canvas>
            </div>
            <div class="empty-state" id="emptyState" style="display:none;">
                <i class="fa-solid fa-folder-open"></i>
                <p class="mb-1 fw-semibold">Belum ada data Uniformity minggu ini.</p>
                <p class="mb-0 small">Silakan upload file Excel rekap Uniformity untuk menampilkan grafik.</p>
            </div>
        </div>

        <div class="table-card p-3" id="tableCard" style="display:none;">
            <h6 class="fw-bold mb-3" id="tableCardTitle">Detail per Plant (klik baris untuk lihat rincian)</h6>
            <table class="uniformity-table">
                <thead>
                    <tr>
                        <th id="thFirstCol">Plant</th>
                        <th class="col-status">Status</th>
                        <th>AK</th>
                        <th>AM</th>
                        <th>AB</th>
                        <th>AJ</th>
                        <th class="col-aksi">Aksi</th>
                    </tr>
                </thead>
                <tbody id="plantTableBody"></tbody>
            </table>
        </div>

    </div>

    <script>
        Chart.register(ChartDataLabels);

        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const ROUTES = {
            upload: `{{ route('slaughter.uniformity.upload') }}`,
            cekKode: `{{ route('slaughter.uniformity.cek-kode') }}`,
            data: `{{ route('slaughter.uniformity.data') }}`,
            compareBulan: `{{ route('slaughter.uniformity.compare-bulan') }}`,
            compareMinggu: `{{ route('slaughter.uniformity.compare-minggu') }}`,
            compareTahun: `{{ route('slaughter.uniformity.compare-tahun') }}`,
            filterOptions: `{{ route('slaughter.uniformity.filter-options') }}`,
            detailPage: `{{ route('slaughter.uniformity.detail-page') }}`,
            uploadStatus: `{{ route('slaughter.uniformity.upload-status') }}`,
        };

        let currentRegion = '';
        let currentPlant = '';
        let currentWeek = '';
        let plantsByRegion = {};    // plant yang SUDAH ada datanya (dari uniformity_reports) - dipakai utk dropdown filter
        let allPlantsByRegion = {}; // SEMUA plant terdaftar (dari plantMap backend) - dipakai utk tabel & dropdown upload
        let chartInstance = null;
        let expandedPlants = new Set();

        // Rentang tanggal minggu yang lagi ditampilkan di chart/tabel (dipakai untuk link tombol Detail)
        let currentViewWeekStart = '';
        let currentViewWeekEnd = '';
        let currentDisplayedWeekLabel = ''; // week_label asli dari data yang sedang tampil di layar

        // Status upload per plant untuk minggu KALENDER berjalan (Senin s/d hari ini),
        // dipakai untuk badge "UPDATE" / "OFF POTONG" di tabel. Diisi oleh fetchUploadBadges().
        // Bentuk: { plantName: { uploadedThisWeek: bool, uploadedToday: bool } }
        let uploadBadgeMap = {};

        // Kode otorisasi yang sudah dikonfirmasi user untuk sesi upload yang sedang berjalan.
        // Diisi setelah user mengisi modal kode, dipakai saat submit file, lalu dikosongkan lagi.
        let pendingKodeOtorisasi = '';

        // ==== State fitur "Perbandingan Bulan" & "Tren Setahun" ====
        // Daftar bulan (value: "Y-m", label: "Agustus 2026") yang punya data, dari filterOptions().
        let comparisonMonthsList = [];
        // Daftar tahun yang punya data (terbaru duluan), dari filterOptions(). Contoh: [2026]
        let comparisonYearsList = [];
        // Daftar minggu ISO yang punya data, dari filterOptions(). Contoh: [{value:"2026-W36", label:"Week 36 (31 Agu – 6 Sep 2026)"}]
        let comparisonWeeksList = [];
        // true kalau tampilan sedang dalam mode perbandingan (bulan ATAU tahun), bukan tampilan mingguan biasa.
        let comparisonMode = false;
        // Jenis mode perbandingan yang aktif: 'bulan' atau 'tahun'.
        let comparisonType = 'bulan';
        // Hasil terakhir dari endpoint compare-bulan, dipakai render ulang saat expand/collapse baris.
        let comparisonResult = null;
        // Baris tabel Perbandingan Bulan cuma 1 (plant terpilih), jadi expand state-nya boolean saja.
        let comparisonExpanded = false;

        // State khusus Tren Setahun
        let yearResult = null;               // hasil terakhir endpoint compare-tahun
        let yearSize = 'ALL';                // 'ALL' = line chart 4 size, atau 'AK'/'AM'/'AB'/'AJ' = batang per size

        const SIZE_ORDER = ['AK', 'AM', 'AB', 'AJ'];
        const TARGET_DEFAULT = 0.8;
        const JAWA_REGIONS = ['Banten', 'Jabar', 'Jateng', 'Jatim'];
        const MONTH_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        // Warna garis per size di line chart Tren Setahun (sengaja beda dari hijau/merah/oranye batang)
        const SIZE_LINE_COLORS = { AK: '#2563eb', AM: '#9333ea', AB: '#0d9488', AJ: '#db2777' };

        // Durasi satu siklus denyut glow badge UPDATE (detik) - harus sama dengan di CSS (neonPulse)
        const BADGE_PULSE_SECONDS = 1.8;

        function formatDateYMD(d) {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        }

        // Hitung rentang Senin-Minggu (ISO week) dari 1 tanggal acuan
        function mondaySundayRange(anchor) {
            const d = new Date(anchor);
            const day = d.getDay(); // 0=Minggu, 1=Senin, ... 6=Sabtu
            const isoDay = day === 0 ? 7 : day; // 1..7, Senin=1
            const monday = new Date(d);
            monday.setDate(d.getDate() - (isoDay - 1));
            const sunday = new Date(monday);
            sunday.setDate(monday.getDate() + 6);
            return { start: formatDateYMD(monday), end: formatDateYMD(sunday) };
        }

        // Nomor ISO week (Senin sbg awal minggu), dibikin supaya konsisten dengan
        // Carbon::isoWeek() di backend (dipakai buat weekLabelFromDate()).
        function getISOWeekNumber(date) {
            const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
            const dayNum = d.getUTCDay() || 7; // Minggu jadi 7, bukan 0
            d.setUTCDate(d.getUTCDate() + 4 - dayNum);
            const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
            return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
        }

        function currentCalendarWeekLabel() {
            return 'Week ' + getISOWeekNumber(new Date());
        }

        // Badge cuma boleh tampil kalau tabel sedang benar-benar menampilkan
        // minggu kalender berjalan (bukan minggu lama yang dipilih lewat dropdown).
        function isViewingCurrentCalendarWeek() {
            return currentDisplayedWeekLabel === currentCalendarWeekLabel();
        }

        // Ambil teks nama plant yang lagi dipilih di dropdown upload, untuk ditampilkan di modal kode.
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
                input: 'text',
                inputPlaceholder: 'Kode otorisasi',
                inputAttributes: { autocapitalize: 'off', autocomplete: 'off' },
                showCancelButton: true,
                confirmButtonText: 'Lanjut Upload',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#16a34a',
                showLoaderOnConfirm: true,
                inputValidator: (value) => {
                    if (!value || !value.trim()) {
                        return 'Kode otorisasi wajib diisi.';
                    }
                },
                preConfirm: async (value) => {
                    // Cek kode ke server DULU, sebelum modal ditutup dan file picker dibuka
                    try {
                        const res = await fetch(ROUTES.cekKode, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
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

            if (!isConfirmed) {
                return; // user batal, atau kode salah (preConfirm return false)
            }

            pendingKodeOtorisasi = kode;
            document.getElementById('excelUploadInput').click();
        }

        async function handleExcelUpload(input) {
            const file = input.files[0];
            if (!file) return;

            const plant = document.getElementById('uploadPlantSelect').value;
            const tanggal = document.getElementById('uploadTanggalInput').value;
            const kodeOtorisasi = pendingKodeOtorisasi;

            if (!plant) {
                Swal.fire({ title: 'Belum lengkap', text: 'Pilih Plant terlebih dahulu.', icon: 'warning' });
                input.value = '';
                return;
            }

            if (!kodeOtorisasi) {
                Swal.fire({ title: 'Belum lengkap', text: 'Kode otorisasi belum diisi. Silakan klik Upload Excel lagi.', icon: 'warning' });
                input.value = '';
                return;
            }

            const formData = new FormData();
            formData.append('file', file);
            formData.append('plant', plant);
            formData.append('tanggal', tanggal);
            formData.append('kode_otorisasi', kodeOtorisasi);

            Swal.fire({
                title: 'Memproses Excel...',
                html: 'Mohon tunggu, sedang membaca & menyusun data.',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading(),
            });

            try {
                const res = await fetch(ROUTES.upload, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    body: formData,
                });
                const result = await res.json();
                if (!res.ok) throw new Error(result.message || 'Gagal memproses file.');

                let dilewatiHtml = '';
                if (result.dilewati && result.dilewati.length > 0) {
                    dilewatiHtml = `<div style="text-align:left; max-height:200px; overflow-y:auto; margin-top:12px; font-size:12px;">
                        <b>Dilewati (${result.dilewati.length}):</b>
                        <ul>${result.dilewati.map(d => `<li>Baris ${d.baris}: ${d.alasan}</li>`).join('')}</ul>
                    </div>`;
                }

                const tanggalText = (result.tanggal_diproses && result.tanggal_diproses.length > 0)
                    ? result.tanggal_diproses.join(', ')
                    : '-';

                await Swal.fire({
                    title: 'Selesai!',
                    html: `<div>${result.berhasil} baris data (tanggal: ${tanggalText}) berhasil disimpan.</div>${dilewatiHtml}`,
                    icon: (result.dilewati && result.dilewati.length > 0) ? 'warning' : 'success',
                });

                await loadFilterOptions();

                // Kalau lagi mode Perbandingan Bulan / Tren Setahun, jangan timpa tampilan comparison
                // dengan refreshView() mingguan - cukup refresh badge datanya saja.
                if (!comparisonMode) {
                    await refreshView();
                }
                await fetchUploadBadges();
            } catch (err) {
                Swal.fire({ title: 'Gagal', text: err.message, icon: 'error' });
            } finally {
                input.value = '';
                pendingKodeOtorisasi = ''; // kode sekali pakai per percobaan upload, jangan disimpan lebih lama
            }
        }

        async function loadFilterOptions() {
            const res = await fetch(ROUTES.filterOptions);
            const opts = await res.json();
            plantsByRegion = opts.plants_by_region || {};
            allPlantsByRegion = opts.all_plants_by_region || {};
            comparisonMonthsList = opts.months || [];
            comparisonYearsList = opts.years || [];
            comparisonWeeksList = opts.iso_weeks || [];
            populateUploadPlantDropdown(allPlantsByRegion);

            const tabWrap = document.getElementById('regionTabs');
            tabWrap.querySelectorAll('.tab-region[data-region]:not([data-region=""])').forEach(el => el.remove());

            // Region untuk tab diambil dari SEMUA region terdaftar (bukan cuma yang sudah ada datanya),
            // supaya tab region tetap muncul lengkap meski belum ada plant yang upload.
            Object.keys(allPlantsByRegion).forEach(region => {
                const btn = document.createElement('button');
                btn.className = 'tab-region';
                btn.dataset.region = region;
                btn.innerText = region;
                btn.onclick = () => setRegion(region, btn);
                tabWrap.appendChild(btn);
            });

            // Tombol "Jawa" (gabungan Banten, Jabar, Jateng, Jatim) - taruh persis di sebelah kanan Jatim
            const jawaBtn = document.createElement('button');
            jawaBtn.className = 'tab-region';
            jawaBtn.dataset.region = '__JAWA__';
            jawaBtn.innerText = 'Jawa';
            jawaBtn.onclick = () => setRegion('__JAWA__', jawaBtn);

            const jatimBtn = tabWrap.querySelector('[data-region="Jatim"]');
            if (jatimBtn) {
                jatimBtn.insertAdjacentElement('afterend', jawaBtn);
            } else {
                tabWrap.appendChild(jawaBtn); // fallback kalau Jatim belum ada di daftar
            }

            populatePlantDropdown();
            populateWeekDropdown(opts.weeks || []);
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

        function populateWeekDropdown(weeks) {
            const select = document.getElementById('weekSelect');
            select.innerHTML = `<option value="">-- Minggu Terbaru --</option>`;
            weeks.forEach(w => {
                const opt = document.createElement('option');
                opt.value = w.week_label;
                opt.innerText = w.week_label;
                select.appendChild(opt);
            });
        }

        function setWeek(week) {
            currentWeek = week;
            refreshView();
            renderPlantTable(currentFullPlantMapCache || {});
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

        function buildQuery() {
            const params = new URLSearchParams();
            if (currentRegion && currentRegion !== '__JAWA__') params.set('region', currentRegion);
            if (currentWeek) params.set('week', currentWeek);
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

        // Gabungkan banyak baris jadi total per size (dipakai utk chart ringkasan)
        function aggregateBySize(rows) {
            const bucket = {};
            SIZE_ORDER.forEach(sz => bucket[sz] = { total_lb: 0, lb_standart: 0, lb_under: 0, lb_over: 0, target: TARGET_DEFAULT });

            rows.forEach(r => {
                if (bucket[r.size]) {
                    bucket[r.size].total_lb += Number(r.total_lb || 0);
                    bucket[r.size].lb_standart += Number(r.lb_standart || 0);
                    bucket[r.size].lb_under += Number(r.lb_under || 0);
                    bucket[r.size].lb_over += Number(r.lb_over || 0);
                    bucket[r.size].target = Number(r.target || TARGET_DEFAULT);
                }
            });

            return SIZE_ORDER.map(sz => {
                const b = bucket[sz];
                const total = b.total_lb;
                return {
                    size: sz,
                    persen_standart: total > 0 ? b.lb_standart / total : 0,
                    persen_under: total > 0 ? b.lb_under / total : 0,
                    persen_over: total > 0 ? b.lb_over / total : 0,
                    target: b.target,
                };
            });
        }

        // Kelompokkan baris jadi per plant -> per size (dipakai utk tabel detail)
        function groupByPlant(rows) {
            // Tahap 1: akumulasi angka mentah per plant+size (bisa banyak baris kalau lintas hari)
            const bucket = {};
            rows.forEach(r => {
                if (!bucket[r.plant]) bucket[r.plant] = {};
                if (!bucket[r.plant][r.size]) {
                    bucket[r.plant][r.size] = { total_lb: 0, lb_standart: 0, lb_under: 0, lb_over: 0, target: TARGET_DEFAULT };
                }
                bucket[r.plant][r.size].total_lb += Number(r.total_lb || 0);
                bucket[r.plant][r.size].lb_standart += Number(r.lb_standart || 0);
                bucket[r.plant][r.size].lb_under += Number(r.lb_under || 0);
                bucket[r.plant][r.size].lb_over += Number(r.lb_over || 0);
                bucket[r.plant][r.size].target = Number(r.target || TARGET_DEFAULT);
            });

            // Tahap 2: baru hitung persentase dari hasil akumulasi
            const map = {};
            Object.keys(bucket).forEach(plant => {
                map[plant] = {};
                Object.keys(bucket[plant]).forEach(size => {
                    const b = bucket[plant][size];
                    const total = b.total_lb;
                    map[plant][size] = {
                        persen_standart: total > 0 ? b.lb_standart / total : 0,
                        persen_under: total > 0 ? b.lb_under / total : 0,
                        persen_over: total > 0 ? b.lb_over / total : 0,
                        target: b.target,
                    };
                });
            });
            return map;
        }

        // Daftar SEMUA nama plant untuk scope region yang lagi aktif (Nasional/Jawa/per region),
        // diambil dari allPlantsByRegion (bukan cuma plant yang sudah ada datanya).
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

        // Gabungkan daftar SEMUA plant (walau belum ada data) dengan data yang sudah ada.
        // Plant tanpa data akan punya objek kosong {} -> tiap kolom size otomatis tampil "-".
        function buildFullPlantMap(scopedRows) {
            const dataMap = groupByPlant(scopedRows);
            const allPlants = getPlantsForCurrentScope();

            const fullMap = {};
            allPlants.forEach(p => {
                fullMap[p] = dataMap[p] || {};
            });
            // Jaga-jaga kalau ada plant di data tapi belum terdaftar di allPlantsByRegion (mismatch nama, dll)
            Object.keys(dataMap).forEach(p => {
                if (!fullMap[p]) fullMap[p] = dataMap[p];
            });
            return fullMap;
        }

        // Cache map plant terakhir yang dipakai renderPlantTable(), supaya setWeek() bisa
        // re-render badge tanpa harus nunggu ulang fetch data chart/tabel.
        let currentFullPlantMapCache = {};

        async function refreshView() {
            // Jangan timpa tampilan Perbandingan Bulan / Tren Setahun dengan tampilan mingguan biasa.
            // refreshView() masih bisa "tertrigger" secara tidak langsung (misal setelah upload),
            // jadi guard ini penting supaya mode comparison tidak ketimpa tiba-tiba.
            if (comparisonMode) return;

            updateChartTitle();
            const qs = buildQuery();

            try {
                const res = await fetch(`${ROUTES.data}?${qs}`);
                const rows = await res.json();

                let scopedRows = rows;
                if (currentRegion === '__JAWA__') {
                    scopedRows = rows.filter(r => JAWA_REGIONS.includes(r.region));
                }

                const isChartEmpty = !scopedRows || scopedRows.length === 0;
                showChartEmptyState(isChartEmpty);

                document.getElementById('infoWeek').innerText = (!isChartEmpty && scopedRows[0]?.week_label)
                    ? `Data minggu: ${scopedRows[0].week_label}`
                    : '';
                currentDisplayedWeekLabel = (!isChartEmpty && scopedRows[0]?.week_label) ? scopedRows[0].week_label : '';

                // Rentang tanggal minggu yang sedang ditampilkan (default ke minggu berjalan kalau belum ada data sama sekali)
                const anchorTanggal = (scopedRows || []).reduce((latest, r) => (!latest || r.tanggal > latest) ? r.tanggal : latest, null);
                const viewRange = mondaySundayRange(anchorTanggal || new Date());
                currentViewWeekStart = viewRange.start;
                currentViewWeekEnd = viewRange.end;

                if (!isChartEmpty) {
                    const rowsForChart = currentPlant ? scopedRows.filter(r => r.plant === currentPlant) : scopedRows;
                    const agg = aggregateBySize(rowsForChart);
                    updateStatStrip(agg);
                    renderChart(agg);
                }

                // Tabel plant SELALU tampil lengkap (semua plant), baik sudah ada data maupun belum
                currentFullPlantMapCache = buildFullPlantMap(scopedRows || []);
                renderPlantTable(currentFullPlantMapCache);
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

        function statusClass(pct) {
            if (pct >= 0.8) return 'ok';
            if (pct >= 0.6) return 'warn';
            return 'danger';
        }

        function updateStatStrip(agg) {
            const scopeLabel = currentPlant ? currentPlant : (currentRegion ? currentRegion : 'Nasional');
            agg.forEach(a => {
                const pct = (a.persen_standart * 100).toFixed(1);
                const box = document.getElementById('statBox' + a.size);
                const val = document.getElementById('stat' + a.size);
                const sub = document.getElementById('statSub' + a.size);
                if (!box || !val) return;

                box.classList.remove('ok', 'warn', 'danger');
                box.classList.add(statusClass(a.persen_standart));
                val.innerText = pct + '%';
                sub.innerText = scopeLabel + ' \u00b7 Target ' + (a.target * 100).toFixed(0) + '%';
            });
        }

        function renderChart(agg) {
            const ctx = document.getElementById('uniformityChart').getContext('2d');
            const labels = agg.map(a => a.size);
            const targetPct = (agg[0]?.target ?? TARGET_DEFAULT) * 100;

            const datasets = [
                {
                    type: 'bar',
                    label: '% Standart',
                    data: agg.map(a => Number((a.persen_standart * 100).toFixed(1))),
                    backgroundColor: '#16a34a',
                    borderRadius: 6,
                    datalabels: { anchor: 'end', align: 'top', color: '#16a34a', font: { weight: 'bold', size: 11 } },
                },
                {
                    type: 'bar',
                    label: '% Under',
                    data: agg.map(a => Number((a.persen_under * 100).toFixed(1))),
                    backgroundColor: '#f87171',
                    borderRadius: 6,
                    datalabels: { anchor: 'end', align: 'top', color: '#b91c1c', font: { weight: 'bold', size: 11 } },
                },
                {
                    type: 'bar',
                    label: '% Over',
                    data: agg.map(a => Number((a.persen_over * 100).toFixed(1))),
                    backgroundColor: '#f59e0b',
                    borderRadius: 6,
                    datalabels: { anchor: 'end', align: 'top', color: '#b45309', font: { weight: 'bold', size: 11 } },
                },
            ];

            // Plugin custom: gambar garis target lurus penuh dari ujung kiri sampai ujung kanan chart
            const targetLinePlugin = {
                id: 'targetLinePlugin',
                afterDatasetsDraw(chart) {
                    const { ctx, chartArea, scales } = chart;
                    const y = scales.y.getPixelForValue(targetPct);

                    ctx.save();
                    ctx.beginPath();
                    ctx.setLineDash([6, 4]);
                    ctx.lineWidth = 2;
                    ctx.strokeStyle = '#16a34a';
                    ctx.moveTo(chartArea.left, y);
                    ctx.lineTo(chartArea.right, y);
                    ctx.stroke();

                    ctx.setLineDash([]);
                    ctx.fillStyle = '#16a34a';
                    ctx.font = 'bold 11px sans-serif';
                    ctx.textAlign = 'right';
                    ctx.fillText(`Target LB Standard (${targetPct}%)`, chartArea.right, y - 6);
                    ctx.restore();
                },
            };

            if (chartInstance) chartInstance.destroy();

            chartInstance = new Chart(ctx, {
                type: 'bar',
                data: { labels, datasets },
                plugins: [targetLinePlugin],
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom' },
                        datalabels: {
                            formatter: (v) => v + '%',
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: { callback: (v) => v + '%' },
                        },
                    },
                },
            });
        }

        function pctChip(value) {
            const pct = (value * 100).toFixed(1);
            const cls = value >= TARGET_DEFAULT ? 'ok' : 'bad';
            return `<span class="pct-chip ${cls}">${pct}%</span>`;
        }

        // Bikin HTML badge kecil di sebelah nama plant, berdasarkan uploadBadgeMap.
        // Return string kosong kalau tidak perlu badge (sudah upload minggu ini tapi bukan hari ini,
        // atau sedang tidak melihat minggu kalender berjalan).
        function plantBadgeHtml(plant) {
            if (!isViewingCurrentCalendarWeek()) return '';

            const status = uploadBadgeMap[plant];
            if (!status) return ''; // data badge belum ada/gagal load -> jangan tampilkan apa-apa

            if (!status.uploadedThisWeek) {
                return `<span class="plant-badge badge-off">OFF POTONG</span>`;
            }
            if (status.uploadedToday) {
                // Delay negatif berdasarkan waktu halaman, supaya denyut glow tetap nyambung
                // (tidak mulai dari awal) setiap kali tabel dirender ulang.
                const delay = -((performance.now() / 1000) % BADGE_PULSE_SECONDS).toFixed(2);
                return `<span class="plant-badge badge-update" style="animation-delay:${delay}s;">UPDATE</span>`;
            }
            return '';
        }

        function renderPlantTable(plantMap) {
            const tbody = document.getElementById('plantTableBody');
            const plantNames = Object.keys(plantMap).sort();

            if (plantNames.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-3">Tidak ada plant terdaftar untuk region ini.</td></tr>`;
                return;
            }

            tbody.innerHTML = plantNames.map(plant => {
                const sizes = plantMap[plant];
                const isExpanded = expandedPlants.has(plant);
                const detailUrl = `${ROUTES.detailPage}?plant=${encodeURIComponent(plant)}&start=${currentViewWeekStart}&end=${currentViewWeekEnd}`;

                const mainRow = `
                    <tr class="plant-row" onclick="togglePlantRow('${plant.replace(/'/g, "\\'")}')">
                        <td>
                            <i class="fa-solid fa-chevron-down expand-icon ${isExpanded ? 'open' : ''}"></i>
                            ${plant}
                        </td>
                        <td class="col-status">${plantBadgeHtml(plant)}</td>
                        ${SIZE_ORDER.map(sz => `<td>${sizes[sz] ? pctChip(sizes[sz].persen_standart) : '-'}</td>`).join('')}
                        <td class="col-aksi" onclick="event.stopPropagation();">
                            <a href="${detailUrl}" class="btn btn-sm btn-outline-primary btn-detail-plant">
                                <i class="fa-solid fa-magnifying-glass"></i> Detail
                            </a>
                        </td>
                    </tr>
                `;

                const detailRow = isExpanded ? `
                    <tr class="detail-row">
                        <td colspan="7">
                            <div class="size-breakdown">
                                ${SIZE_ORDER.map(sz => renderSizeCard(sz, sizes[sz])).join('')}
                            </div>
                        </td>
                    </tr>
                ` : '';

                return mainRow + detailRow;
            }).join('');
        }

        function renderSizeCard(size, data) {
            if (!data) {
                return `<div class="size-card" style="--sc:#94a3b8;"><div class="label">${size}</div><div class="text-muted small">Tidak ada data</div></div>`;
            }
            const gap = data.persen_standart - data.target;
            const gapClass = gap >= 0 ? 'ok' : 'bad';
            const gapText = gap >= 0
                ? `✓ Capai target (+${(gap * 100).toFixed(1)}%)`
                : `⚠ Kurang ${Math.abs(gap * 100).toFixed(1)}% dari target`;
            const color = gap >= 0 ? '#16a34a' : '#f87171';

            return `
                <div class="size-card" style="--sc:${color};">
                    <div class="label">${size}</div>
                    <div class="row-metric"><span>% Standart</span><b>${(data.persen_standart * 100).toFixed(1)}%</b></div>
                    <div class="row-metric"><span>% Under</span><b>${(data.persen_under * 100).toFixed(1)}%</b></div>
                    <div class="row-metric"><span>% Over</span><b>${(data.persen_over * 100).toFixed(1)}%</b></div>
                    <div class="row-metric"><span style="color:#16a34a;">Target</span><b style="color:#16a34a;">${(data.target * 100).toFixed(0)}%</b></div>
                    <div class="gap-line ${gapClass}">${gapText}</div>
                </div>
            `;
        }

        function togglePlantRow(plant) {
            if (expandedPlants.has(plant)) {
                expandedPlants.delete(plant);
            } else {
                expandedPlants.add(plant);
            }

            // baris baru: set plant ini jadi scope aktif buat chart & badge
            currentPlant = plant;
            document.getElementById('plantSelect').value = plant; // biar dropdown ikut nyocok
            refreshView();
        }

        // Ambil status upload minggu kalender berjalan (Senin s/d hari ini) untuk semua plant,
        // lalu simpan ringkasannya ke uploadBadgeMap supaya renderPlantTable() bisa pasang badge.
        // Dipanggil terpisah dari refreshView() karena basisnya minggu kalender asli,
        // bukan minggu yang sedang difilter user (currentWeek/currentViewWeekStart-End).
        async function fetchUploadBadges() {
            const today = new Date();
            const range = mondaySundayRange(today);
            const todayStr = formatDateYMD(today);

            try {
                const res = await fetch(`${ROUTES.uploadStatus}?start=${range.start}&end=${range.end}`);
                const data = await res.json(); // { region: [ { plant, tanggal: [{tanggal, sudah_upload}] } ] }

                const map = {};
                Object.values(data).forEach(plantList => {
                    plantList.forEach(p => {
                        const uploadedThisWeek = p.tanggal.some(t => t.sudah_upload);
                        const uploadedToday = p.tanggal.some(t => t.tanggal === todayStr && t.sudah_upload);
                        map[p.plant] = { uploadedThisWeek, uploadedToday };
                    });
                });

                uploadBadgeMap = map;
            } catch (err) {
                console.error('Gagal ambil status upload untuk badge:', err);
                uploadBadgeMap = {};
            }

            // Re-render tabel yang sudah ada di layar supaya badge langsung update.
            // Kalau lagi mode Perbandingan Bulan / Tren Setahun, tabelnya beda struktur (tanpa badge),
            // jadi jangan ditimpa oleh renderPlantTable().
            if (!comparisonMode) {
                renderPlantTable(currentFullPlantMapCache);
            }
        }

        // ==========================================================
        // FITUR: PERBANDINGAN BULAN
        // ==========================================================

                // Buka modal Perbandingan: pilih Bulan/Minggu, lalu pilih 2 periode untuk plant yang aktif.
        async function bukaModalPerbandingan() {
            if (!currentPlant) {
                Swal.fire({
                    title: 'Pilih plant dulu',
                    text: 'Pilih salah satu plant di dropdown sebelum membuka Perbandingan.',
                    icon: 'warning',
                });
                return;
            }

            const { value: hasil } = await Swal.fire({
                title: `Perbandingan - ${currentPlant}`,
                html: `
                    <div style="text-align:left; margin-bottom:10px;">
                        <label style="font-size:0.8rem; font-weight:600;">Bandingkan per</label>
                        <select id="swalTipe" class="form-select form-select-sm">
                            <option value="bulan">Bulan</option>
                            <option value="minggu">Minggu</option>
                        </select>
                    </div>
                    <div style="text-align:left; margin-bottom:10px;">
                        <label id="swalLabel1" style="font-size:0.8rem; font-weight:600;"></label>
                        <select id="swalPeriode1" class="form-select form-select-sm"></select>
                    </div>
                    <div style="text-align:left;">
                        <label id="swalLabel2" style="font-size:0.8rem; font-weight:600;"></label>
                        <select id="swalPeriode2" class="form-select form-select-sm"></select>
                    </div>
                `,
                didOpen: () => {
                    const isiDropdown = () => {
                        const tipe = document.getElementById('swalTipe').value;
                        const list = tipe === 'minggu' ? comparisonWeeksList : comparisonMonthsList;
                        const nama = tipe === 'minggu' ? 'Minggu' : 'Bulan';
                        const optionsHtml = list.map(x => `<option value="${x.value}">${x.label}</option>`).join('');

                        document.getElementById('swalLabel1').innerText = nama + ' 1';
                        document.getElementById('swalLabel2').innerText = nama + ' 2';

                        const sel1 = document.getElementById('swalPeriode1');
                        const sel2 = document.getElementById('swalPeriode2');
                        sel1.innerHTML = optionsHtml;
                        sel2.innerHTML = optionsHtml;

                        // Default: periode 2 = terbaru (list sudah urut terbaru duluan), periode 1 = sebelumnya
                        if (list[0]) sel2.value = list[0].value;
                        if (list[1]) sel1.value = list[1].value;
                    };
                    isiDropdown();
                    document.getElementById('swalTipe').addEventListener('change', isiDropdown);
                },
                showCancelButton: true,
                confirmButtonText: 'Bandingkan',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#0a9396',
                preConfirm: () => {
                    const tipe = document.getElementById('swalTipe').value;
                    const list = tipe === 'minggu' ? comparisonWeeksList : comparisonMonthsList;
                    if (list.length < 2) {
                        Swal.showValidationMessage(`Belum ada minimal 2 ${tipe} dengan data untuk dibandingkan.`);
                        return false;
                    }
                    const p1 = document.getElementById('swalPeriode1').value;
                    const p2 = document.getElementById('swalPeriode2').value;
                    if (p1 === p2) {
                        Swal.showValidationMessage('Pilihan 1 dan 2 tidak boleh sama.');
                        return false;
                    }
                    // Format "Y-m" dan "YYYY-Www" sama-sama bisa diurutkan sebagai string,
                    // jadi yang kiri selalu lebih lama dan yang kanan selalu lebih baru.
                    return { tipe, periods: [p1, p2].sort() };
                },
            });

            if (!hasil) return; // user batal / validasi gagal

            await jalankanPerbandingan(currentPlant, hasil.tipe, hasil.periods);
        }

                // Panggil endpoint compare-bulan atau compare-minggu, simpan hasilnya, lalu render tampilan comparison.
        async function jalankanPerbandingan(plant, tipe, periods) {
            Swal.fire({
                title: 'Memuat data perbandingan...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading(),
            });

            try {
                const params = new URLSearchParams();
                params.set('plant', plant);
                periods.forEach(p => params.append(tipe === 'minggu' ? 'minggu[]' : 'bulan[]', p));

                const url = tipe === 'minggu' ? ROUTES.compareMinggu : ROUTES.compareBulan;
                const res = await fetch(`${url}?${params.toString()}`);
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Gagal mengambil data perbandingan.');

                // Endpoint minggu mengembalikan key "minggu", sedangkan fungsi render di bawah membaca "bulan".
                // Disamakan di sini supaya renderComparisonChart/Table/SizeGroup tidak perlu diubah.
                if (tipe === 'minggu') data.bulan = data.minggu;

                comparisonResult = data;
                comparisonMode = true;
                comparisonType = tipe; // 'bulan' atau 'minggu'
                comparisonExpanded = false;

                Swal.close();
                renderComparisonView();
            } catch (err) {
                Swal.fire({ title: 'Gagal', text: err.message, icon: 'error' });
            }
        }

        // Keluar dari mode Perbandingan Bulan / Tren Setahun, balik ke tampilan mingguan biasa.
        function keluarModePerbandingan() {
            comparisonMode = false;
            comparisonType = 'bulan';
            comparisonResult = null;
            comparisonExpanded = false;

            yearResult = null;
            yearSize = 'ALL';
            document.getElementById('yearSizeToggle').style.display = 'none';
            syncYearSizeToggle();

            document.getElementById('comparisonBanner').style.display = 'none';
            document.getElementById('tableCard').classList.remove('compare-active');
            document.getElementById('tableCardTitle').innerText = 'Detail per Plant (klik baris untuk lihat rincian)';
            document.getElementById('thFirstCol').innerText = 'Plant';

            document.getElementById('regionTabs').classList.remove('filters-disabled');
            document.getElementById('plantSelect').disabled = false;
            document.getElementById('weekSelect').disabled = false;

            refreshView();
        }

        // Render keseluruhan tampilan mode Perbandingan Bulan: banner, chart grouped-bar, tabel 1-baris.
        function renderComparisonView() {
            if (!comparisonResult) return;
            const [m1, m2] = comparisonResult.bulan;
            const namaTipe = comparisonType === 'minggu' ? 'Minggu' : 'Bulan';

            document.getElementById('comparisonBanner').style.display = 'flex';
            document.getElementById('comparisonBannerText').innerHTML =
                                `<i class="fa-solid fa-code-compare"></i> Perbandingan ${namaTipe} &mdash; ${comparisonResult.plant}: ${labelPendek(m1)} vs ${labelPendek(m2)}`;

            // Filter normal (region/plant/week) dinonaktifkan visual selama mode ini aktif,
            // supaya tidak membingungkan (klik region/plant/week tidak akan mengganti tampilan comparison).
            document.getElementById('regionTabs').classList.add('filters-disabled');
            document.getElementById('plantSelect').disabled = true;
            document.getElementById('weekSelect').disabled = true;

            // Pastikan sisa tampilan Tren Setahun (kalau sebelumnya aktif) tidak ikut terbawa
            document.getElementById('yearSizeToggle').style.display = 'none';
            document.getElementById('thFirstCol').innerText = 'Plant';

            document.getElementById('chartTitle').innerText = `${comparisonResult.plant} - Perbandingan ${namaTipe}`;
            document.getElementById('chartScopeBadge').innerText = `${labelPendek(m1)} vs ${labelPendek(m2)}`;

            document.getElementById('infoWeek').innerText = '';
            document.getElementById('statStrip').style.display = 'none'; // stat strip mingguan tidak relevan di sini

            showChartEmptyState(false);
            document.getElementById('statStrip').style.display = 'none';
            renderComparisonChart(m1, m2);

            document.getElementById('tableCardTitle').innerText = `Detail ${comparisonResult.plant} (klik untuk lihat rincian per ${namaTipe.toLowerCase()})`;
            document.getElementById('tableCard').style.display = 'block';
            document.getElementById('tableCard').classList.add('compare-active');
            renderComparisonTable(m1, m2);
        }

        // Grouped bar chart: 4 size di X-axis, 6 dataset (Standart/Under/Over x 2 bulan).
        // Size yang total_lb-nya 0 (tidak ada data bulan itu) dikasih nilai null supaya
        // Chart.js tidak menggambar bar-nya sama sekali (bukan digambar sebagai 0%).
        function renderComparisonChart(m1, m2) {
            const ctx = document.getElementById('uniformityChart').getContext('2d');
            const labels = SIZE_ORDER;

            function seriesFor(bulanObj, metricKey) {
                return SIZE_ORDER.map(sz => {
                    const s = bulanObj.sizes[sz];
                    if (!s || s.total_lb === 0) return null;
                    return Number((s[metricKey] * 100).toFixed(1));
                });
            }

            const rawDatasets = [
                { label: `${labelPendek(m1)} - Standart`, data: seriesFor(m1, 'persen_standart'), backgroundColor: '#16a34a' },
                { label: `${labelPendek(m1)} - Under`,    data: seriesFor(m1, 'persen_under'),    backgroundColor: '#f87171' },
                { label: `${labelPendek(m1)} - Over`,     data: seriesFor(m1, 'persen_over'),     backgroundColor: '#f59e0b' },
                { label: `${labelPendek(m2)} - Standart`, data: seriesFor(m2, 'persen_standart'), backgroundColor: '#0f6b2e' },
                { label: `${labelPendek(m2)} - Under`,    data: seriesFor(m2, 'persen_under'),    backgroundColor: '#b91c1c' },
                { label: `${labelPendek(m2)} - Over`,     data: seriesFor(m2, 'persen_over'),     backgroundColor: '#b45309' },
            ];

            const datasets = rawDatasets.map(d => ({
                type: 'bar',
                label: d.label,
                data: d.data,
                backgroundColor: d.backgroundColor,
                borderRadius: 4,
                datalabels: {
                    anchor: 'end',
                    align: 'top',
                    color: d.backgroundColor,
                    font: { weight: 'bold', size: 9 },
                    formatter: (v) => (v === null ? '' : v + '%'),
                },
            }));

            // Target dianggap sama untuk kedua bulan (default 0.8) - ambil dari size AK di bulan
            // manapun yang punya data, fallback ke TARGET_DEFAULT kalau dua-duanya kosong.
            const targetSource = (m2.sizes.AK && m2.sizes.AK.total_lb > 0) ? m2.sizes.AK
                : (m1.sizes.AK && m1.sizes.AK.total_lb > 0) ? m1.sizes.AK
                : null;
            const targetPct = (targetSource ? targetSource.target : TARGET_DEFAULT) * 100;

            const targetLinePlugin = {
                id: 'targetLinePluginCompare',
                afterDatasetsDraw(chart) {
                    const { ctx, chartArea, scales } = chart;
                    const y = scales.y.getPixelForValue(targetPct);

                    ctx.save();
                    ctx.beginPath();
                    ctx.setLineDash([6, 4]);
                    ctx.lineWidth = 2;
                    ctx.strokeStyle = '#16a34a';
                    ctx.moveTo(chartArea.left, y);
                    ctx.lineTo(chartArea.right, y);
                    ctx.stroke();

                    ctx.setLineDash([]);
                    ctx.fillStyle = '#16a34a';
                    ctx.font = 'bold 11px sans-serif';
                    ctx.textAlign = 'right';
                    ctx.fillText(`Target LB Standard (${targetPct}%)`, chartArea.right, y - 6);
                    ctx.restore();
                },
            };

            if (chartInstance) chartInstance.destroy();

            chartInstance = new Chart(ctx, {
                type: 'bar',
                data: { labels, datasets },
                plugins: [targetLinePlugin],
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } },
                        datalabels: {},
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: { callback: (v) => v + '%' },
                        },
                    },
                },
            });
        }

        // Tabel mode Perbandingan Bulan: cuma 1 baris (plant terpilih). Chip utama pakai
        // nilai Bulan 2 (slot kedua yang dipilih user, default-nya bulan terbaru). Expand
        // menampilkan 2 size-card berdampingan (Bulan 1 vs Bulan 2) untuk tiap size.
        function renderComparisonTable(m1, m2) {
            const tbody = document.getElementById('plantTableBody');
            const plant = comparisonResult.plant;

            function chipFor(sz) {
                const s = m2.sizes[sz];
                if (!s || s.total_lb === 0) return '-';
                return pctChip(s.persen_standart);
            }

            const mainRow = `
                <tr class="plant-row" onclick="toggleComparisonRow()">
                    <td>
                        <i class="fa-solid fa-chevron-down expand-icon ${comparisonExpanded ? 'open' : ''}"></i>
                        ${plant}
                    </td>
                    <td class="col-status"></td>
                    ${SIZE_ORDER.map(sz => `<td>${chipFor(sz)}</td>`).join('')}
                    <td class="col-aksi"></td>
                </tr>
            `;

            const detailRow = comparisonExpanded ? `
                <tr class="detail-row">
                    <td colspan="7">
                        <div style="padding:14px 16px; display:flex; flex-direction:column; gap:14px;">
                            ${SIZE_ORDER.map(sz => renderComparisonSizeGroup(sz, m1, m2)).join('')}
                        </div>
                    </td>
                </tr>
            ` : '';

            tbody.innerHTML = mainRow + detailRow;
        }

                // Label pendek untuk legend & badge: "Week 36 (31 Agu – 6 Sep 2026)" -> "Week 36".
        // Label bulan ("Agustus 2026") tidak punya tanda kurung, jadi tidak berubah.
        function labelPendek(p) {
            return p.label.split(' (')[0];
        }

        // Label lengkap untuk kartu. Di mode Minggu ditambah jumlah hari yang ada datanya
        // (jumlah_hari hanya ada di response compare-minggu), supaya minggu yang datanya sedikit kelihatan.
        function labelPeriode(p) {
            return p.jumlah_hari !== undefined ? `${p.label} · ${p.jumlah_hari} hari` : p.label;
        }

        function renderComparisonSizeGroup(size, m1, m2) {
            return `
                <div class="compare-size-group">
                    <div class="compare-size-title">${size}</div>
                    <div class="size-breakdown" style="padding:0;">
                        ${renderSizeCardLabeled(size, m1.sizes[size], labelPeriode(m1))}
                        ${renderSizeCardLabeled(size, m2.sizes[size], labelPeriode(m2), m1.sizes[size], labelPendek(m1))}
                    </div>
                </div>
            `;
        }

        // Bandingkan valueNow (bulan pembanding) vs valueBefore (bulan baseline), return ikon panah tren.
        // higherIsBetter: true kalau naik = bagus (Standart), false kalau naik = jelek (Under/Over).
        // pembandingLabel (opsional): nama bulan pembanding untuk tooltip, misal "Agustus".
        function trendArrowHtml(valueNow, valueBefore, higherIsBetter, pembandingLabel) {
            if (valueBefore === null || valueBefore === undefined) return '';
            const diff = valueNow - valueBefore;
            const EPSILON = 0.0005; // selisih < 0.05% dianggap tetap/flat

            let icon = 'fa-minus';
            let cls = 'flat';
            if (diff > EPSILON) {
                icon = 'fa-arrow-up';
                cls = higherIsBetter ? 'good' : 'bad';
            } else if (diff < -EPSILON) {
                icon = 'fa-arrow-down';
                cls = higherIsBetter ? 'bad' : 'good';
            }

            const deltaText = (diff * 100).toFixed(1);
            const namaPembanding = pembandingLabel || 'bulan pembanding';
            const tooltip = cls === 'flat' ? `Tidak berubah vs ${namaPembanding}` : `${diff > 0 ? '+' : ''}${deltaText}% vs ${namaPembanding}`;

            return `<i class="fa-solid ${icon} trend-arrow ${cls}" title="${tooltip}"></i>`;
        }

            function renderSizeCardLabeled(size, data, monthLabel, compareData, compareLabel) {
            if (!data || data.total_lb === 0) {
                return `
                    <div class="size-card" style="--sc:#94a3b8;">
                        <div class="label">${monthLabel}</div>
                        <div class="text-muted small">Tidak ada data</div>
                    </div>
                `;
            }
            const gap = data.persen_standart - data.target;
            const gapClass = gap >= 0 ? 'ok' : 'bad';
            const gapText = gap >= 0
                ? `✓ Capai target (+${(gap * 100).toFixed(1)}%)`
                : `⚠ Kurang ${Math.abs(gap * 100).toFixed(1)}% dari target`;
            const color = gap >= 0 ? '#16a34a' : '#f87171';

            const arrowStandart = compareData ? trendArrowHtml(data.persen_standart, compareData.persen_standart, true, compareLabel) : '';
            const arrowUnder = compareData ? trendArrowHtml(data.persen_under, compareData.persen_under, false, compareLabel) : '';
            const arrowOver = compareData ? trendArrowHtml(data.persen_over, compareData.persen_over, false, compareLabel) : '';

            return `
                <div class="size-card" style="--sc:${color};">
                    <div class="label">${monthLabel}</div>
                    <div class="row-metric"><span>% Standart</span><b>${(data.persen_standart * 100).toFixed(1)}%${arrowStandart}</b></div>
                    <div class="row-metric"><span>% Under</span><b>${(data.persen_under * 100).toFixed(1)}%${arrowUnder}</b></div>
                    <div class="row-metric"><span>% Over</span><b>${(data.persen_over * 100).toFixed(1)}%${arrowOver}</b></div>
                    <div class="row-metric"><span style="color:#16a34a;">Target</span><b style="color:#16a34a;">${(data.target * 100).toFixed(0)}%</b></div>
                    <div class="gap-line ${gapClass}">${gapText}</div>
                </div>
            `;
        }

        function toggleComparisonRow() {
            comparisonExpanded = !comparisonExpanded;
            const [m1, m2] = comparisonResult.bulan;
            renderComparisonTable(m1, m2);
        }

        // ==========================================================
        // FITUR: TREN SETAHUN
        // ==========================================================

        // Buka modal pilih tahun untuk plant yang sedang aktif di dropdown (currentPlant).
        async function bukaModalTrenSetahun() {
            if (!currentPlant) {
                Swal.fire({
                    title: 'Pilih plant dulu',
                    text: 'Pilih salah satu plant di dropdown sebelum membuka Tren Setahun.',
                    icon: 'warning',
                });
                return;
            }

            if (comparisonYearsList.length < 1) {
                Swal.fire({
                    title: 'Data belum ada',
                    text: 'Belum ada data tahun manapun untuk ditampilkan.',
                    icon: 'warning',
                });
                return;
            }

            const yearOptionsHtml = comparisonYearsList
                .map(y => `<option value="${y}">${y}</option>`)
                .join('');

            const { value: tahun } = await Swal.fire({
                title: `Tren Setahun - ${currentPlant}`,
                html: `
                    <div style="text-align:left;">
                        <label style="font-size:0.8rem; font-weight:600;">Tahun</label>
                        <select id="swalTahun" class="form-select form-select-sm">${yearOptionsHtml}</select>
                    </div>
                `,
                didOpen: () => {
                    // comparisonYearsList sudah urut terbaru duluan, jadi default = tahun terbaru
                    document.getElementById('swalTahun').value = comparisonYearsList[0];
                },
                showCancelButton: true,
                confirmButtonText: 'Tampilkan',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#0a9396',
                preConfirm: () => document.getElementById('swalTahun').value,
            });

            if (!tahun) return; // user batal

            await jalankanTrenSetahun(currentPlant, tahun);
        }

        // Panggil endpoint compare-tahun, simpan hasilnya, lalu render tampilan Tren Setahun.
        async function jalankanTrenSetahun(plant, tahun) {
            Swal.fire({
                title: 'Memuat data tren setahun...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading(),
            });

            try {
                const params = new URLSearchParams({ plant, tahun });
                const res = await fetch(`${ROUTES.compareTahun}?${params.toString()}`);
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Gagal mengambil data tren setahun.');

                yearResult = data;
                yearSize = 'ALL';
                comparisonMode = true;
                comparisonType = 'tahun';

                Swal.close();
                renderYearView();
            } catch (err) {
                Swal.fire({ title: 'Gagal', text: err.message, icon: 'error' });
            }
        }

        // Render keseluruhan tampilan Tren Setahun: banner, tombol size, chart, tabel 12 bulan.
        function renderYearView() {
            if (!yearResult) return;

            document.getElementById('comparisonBanner').style.display = 'flex';
            document.getElementById('comparisonBannerText').innerHTML =
                `<i class="fa-solid fa-chart-line"></i> Tren Setahun &mdash; ${yearResult.plant}: ${yearResult.tahun}`;

            document.getElementById('regionTabs').classList.add('filters-disabled');
            document.getElementById('plantSelect').disabled = true;
            document.getElementById('weekSelect').disabled = true;

            document.getElementById('infoWeek').innerText = '';
            showChartEmptyState(false);
            document.getElementById('statStrip').style.display = 'none';

            document.getElementById('yearSizeToggle').style.display = 'flex';
            syncYearSizeToggle();
            renderYearChart();

            document.getElementById('thFirstCol').innerText = 'Bulan';
            document.getElementById('tableCardTitle').innerText =
                `Detail ${yearResult.plant} ${yearResult.tahun}`;
            document.getElementById('tableCard').style.display = 'block';
            document.getElementById('tableCard').classList.add('compare-active');
            renderYearTable();
        }

        // Ganti size yang ditampilkan di chart (tanpa request ulang, data 12 bulan x 4 size sudah dimuat).
        function setYearSize(size) {
            yearSize = size;
            syncYearSizeToggle();
            renderYearChart();
        }

        function syncYearSizeToggle() {
            document.querySelectorAll('#yearSizeToggle button').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.size === yearSize);
            });
        }

        // Nilai persen (0-100, 1 desimal) untuk 1 bulan + 1 size + 1 metrik.
        // Return null kalau bulan itu tidak ada data, supaya jadi celah di chart (bukan 0%).
        function yearValue(bulanObj, size, metricKey) {
            const s = bulanObj.sizes[size];
            if (!s || s.total_lb === 0) return null;
            return Number((s[metricKey] * 100).toFixed(1));
        }

        // Ambil target dari data (semua size sama, default 0.8), fallback ke TARGET_DEFAULT kalau setahun kosong.
        function yearTargetPct() {
            for (const b of yearResult.bulan) {
                for (const sz of SIZE_ORDER) {
                    const s = b.sizes[sz];
                    if (s && s.total_lb > 0) return Number(s.target) * 100;
                }
            }
            return TARGET_DEFAULT * 100;
        }

        function makeTargetLinePlugin(id, targetPct) {
            return {
                id,
                afterDatasetsDraw(chart) {
                    const { ctx, chartArea, scales } = chart;
                    const y = scales.y.getPixelForValue(targetPct);

                    ctx.save();
                    ctx.beginPath();
                    ctx.setLineDash([6, 4]);
                    ctx.lineWidth = 2;
                    ctx.strokeStyle = '#16a34a';
                    ctx.moveTo(chartArea.left, y);
                    ctx.lineTo(chartArea.right, y);
                    ctx.stroke();

                    ctx.setLineDash([]);
                    ctx.fillStyle = '#16a34a';
                    ctx.font = 'bold 11px sans-serif';
                    ctx.textAlign = 'right';
                    ctx.fillText(`Target LB Standard (${targetPct}%)`, chartArea.right, y - 6);
                    ctx.restore();
                },
            };
        }

        // Chart Tren Setahun:
        // - yearSize 'ALL': line chart 4 garis (AK, AM, AB, AJ), metrik % Standart saja.
        // - yearSize satu size: batang, 3 batang per bulan (Standart hijau, Under merah, Over oranye).
        function renderYearChart() {
            if (!yearResult) return;

            const ctx = document.getElementById('uniformityChart').getContext('2d');
            const bulanArr = yearResult.bulan;
            const labels = MONTH_SHORT;
            const targetPct = yearTargetPct();
            const isAll = yearSize === 'ALL';

            document.getElementById('chartTitle').innerText =
                `${yearResult.plant} - Tren Setahun ${yearResult.tahun}` + (isAll ? '' : ` \u00b7 ${yearSize}`);
            document.getElementById('chartScopeBadge').innerText = isAll ? `Tahun ${yearResult.tahun}` : `${yearSize} \u00b7 ${yearResult.tahun}`;

            let datasets;
            let chartType;

            if (isAll) {
    chartType = 'bar';
    datasets = SIZE_ORDER.map(sz => ({
        type: 'bar',
        label: sz,
        data: bulanArr.map(b => yearValue(b, sz, 'persen_standart')),
        backgroundColor: SIZE_LINE_COLORS[sz],
        borderRadius: 3,
        _size: sz,
        datalabels: {
            anchor: 'end',
            align: 'top',
            color: SIZE_LINE_COLORS[sz],
            font: { weight: 'bold', size: 8 },
            formatter: (v) => (v === null || v === undefined ? '' : v + '%'),
        },
    }));
} else {
                chartType = 'bar';
                const metrics = [
                    { key: 'persen_standart', label: '% Standart', bg: '#16a34a', text: '#16a34a' },
                    { key: 'persen_under',    label: '% Under',    bg: '#f87171', text: '#b91c1c' },
                    { key: 'persen_over',     label: '% Over',     bg: '#f59e0b', text: '#b45309' },
                ];
                datasets = metrics.map(m => ({
                    type: 'bar',
                    label: m.label,
                    data: bulanArr.map(b => yearValue(b, yearSize, m.key)),
                    backgroundColor: m.bg,
                    borderRadius: 4,
                    _size: yearSize,
                    datalabels: {
                        anchor: 'end',
                        align: 'top',
                        color: m.text,
                        font: { weight: 'bold', size: 8 },
                        formatter: (v) => (v === null || v === undefined ? '' : v + '%'),
                    },
                }));
            }

            const tooltipCallbacks = {
                title: (items) => {
                    const b = bulanArr[items[0].dataIndex];
                    return `${b.label} ${yearResult.tahun}`;
                },
                label: (c) => {
                    const v = c.parsed.y;
                    if (v === null || v === undefined) return `${c.dataset.label}: tidak ada data`;
                    if (isAll) {
                        const total = bulanArr[c.dataIndex].sizes[c.dataset._size].total_lb;
                        return `${c.dataset.label}: ${v}% (total ${Number(total).toLocaleString('id-ID')} LB)`;
                    }
                    return `${c.dataset.label}: ${v}%`;
                },
                footer: (items) => {
                    if (isAll || !items.length) return '';
                    const total = bulanArr[items[0].dataIndex].sizes[yearSize].total_lb;
                    return total > 0 ? `Total ${Number(total).toLocaleString('id-ID')} LB` : '';
                },
            };

            if (chartInstance) chartInstance.destroy();

            chartInstance = new Chart(ctx, {
                type: chartType,
                data: { labels, datasets },
                plugins: [makeTargetLinePlugin('targetLinePluginYear', targetPct)],
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: { callbacks: tooltipCallbacks },
                        // Mode garis: label angka permanen ditiadakan (4 garis akan saling tumpuk), angka ada di tooltip & tabel.
                        datalabels: {},
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: { display: isAll, text: '% Standart' },
                            ticks: { callback: (v) => v + '%' },
                        },
                    },
                },
            });
        }

        // Apakah bulan ini punya data di minimal 1 size (konsisten dengan chart: total_lb 0 = tidak ada data).
        function monthHasData(bulanObj) {
            return SIZE_ORDER.some(sz => bulanObj.sizes[sz] && bulanObj.sizes[sz].total_lb > 0);
        }

        // Tabel Tren Setahun: semua bulan langsung tampil berurutan (tanpa expand/collapse).
        // - Bulan yang punya data: 1 baris bulan (chip % Standart) + 4 kartu rincian per size di bawahnya.
        // - Bulan kosong: 1 baris saja bertuliskan "Tidak ada data".
        // Panah tren tiap bulan dibandingkan dengan bulan TERAKHIR yang punya data (bukan sekadar bulan kalender sebelumnya).
        function renderYearTable() {
            const tbody = document.getElementById('plantTableBody');
            const bulanArr = yearResult.bulan;
            let bulanPembanding = null; // bulan terakhir yang punya data, dilewati kalau bulan kosong

            tbody.innerHTML = bulanArr.map(b => {
                if (!monthHasData(b)) {
                    return `
                        <tr class="month-row">
                            <td>${b.label}</td>
                            <td colspan="4" class="text-muted small">Tidak ada data</td>
                        </tr>
                    `;
                }

                const chips = SIZE_ORDER.map(sz => {
                    const s = b.sizes[sz];
                    return `<td>${(s && s.total_lb > 0) ? pctChip(s.persen_standart) : '-'}</td>`;
                }).join('');

                const cards = SIZE_ORDER.map(sz => renderYearSizeCard(
                    sz,
                    b.sizes[sz],
                    bulanPembanding ? bulanPembanding.sizes[sz] : null,
                    bulanPembanding ? bulanPembanding.label : ''
                )).join('');

                bulanPembanding = b; // bulan ini jadi pembanding untuk bulan berikutnya yang punya data

                return `
                    <tr class="month-row">
                        <td>${b.label}</td>
                        <td class="col-status"></td>
                        ${chips}
                        <td class="col-aksi"></td>
                    </tr>
                    <tr class="detail-row">
                        <td colspan="7">
                            <div class="size-breakdown">${cards}</div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // Size-card rincian 1 bulan di Tren Setahun (sama seperti renderSizeCard, plus baris Total LB dan panah tren).
        // compareData = data size yang sama di bulan pembanding (null kalau tidak ada), compareLabel = nama bulan pembanding.
        function renderYearSizeCard(size, data, compareData, compareLabel) {
            if (!data || data.total_lb === 0) {
                return `<div class="size-card" style="--sc:#94a3b8;"><div class="label">${size}</div><div class="text-muted small">Tidak ada data</div></div>`;
            }
            const gap = data.persen_standart - data.target;
            const gapClass = gap >= 0 ? 'ok' : 'bad';
            const gapText = gap >= 0
                ? `✓ Capai target (+${(gap * 100).toFixed(1)}%)`
                : `⚠ Kurang ${Math.abs(gap * 100).toFixed(1)}% dari target`;
            const color = gap >= 0 ? '#16a34a' : '#f87171';

            // Panah hanya muncul kalau bulan pembanding punya data untuk size ini
            const punyaPembanding = compareData && compareData.total_lb > 0;
            const arrowStandart = punyaPembanding ? trendArrowHtml(data.persen_standart, compareData.persen_standart, true, compareLabel) : '';
            const arrowUnder = punyaPembanding ? trendArrowHtml(data.persen_under, compareData.persen_under, false, compareLabel) : '';
            const arrowOver = punyaPembanding ? trendArrowHtml(data.persen_over, compareData.persen_over, false, compareLabel) : '';

            return `
                <div class="size-card" style="--sc:${color};">
                    <div class="label">${size}</div>
                    <div class="row-metric"><span>% Standart</span><b>${(data.persen_standart * 100).toFixed(1)}%${arrowStandart}</b></div>
                    <div class="row-metric"><span>% Under</span><b>${(data.persen_under * 100).toFixed(1)}%${arrowUnder}</b></div>
                    <div class="row-metric"><span>% Over</span><b>${(data.persen_over * 100).toFixed(1)}%${arrowOver}</b></div>
                    <div class="row-metric"><span style="color:#16a34a;">Target</span><b style="color:#16a34a;">${(data.target * 100).toFixed(0)}%</b></div>
                    <div class="row-metric"><span>Total LB</span><b>${Number(data.total_lb).toLocaleString('id-ID')}</b></div>
                    <div class="gap-line ${gapClass}">${gapText}</div>
                </div>
            `;
        }

        (async function init() {
            await loadFilterOptions();
            await refreshView();
            await fetchUploadBadges();
        })();
    </script>

</body>
</html>