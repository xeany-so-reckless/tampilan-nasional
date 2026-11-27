<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard Rekap Yield Produksi Nasional</title>
    @include('partials.favicon')
    <!-- Google Fonts: Plus Jakarta Sans & Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <!-- SweetAlert2 (dialog kode otorisasi, pratinjau, hasil upload) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Plugin Chart.js Annotation & Datalabels -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/chartjs-plugin-annotation/2.2.1/chartjs-plugin-annotation.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/chartjs-plugin-datalabels/2.2.0/chartjs-plugin-datalabels.min.js"></script>
    <style>
        /* Status kartu ringkasan di halaman detail: gradasi tipis dari kanan bawah + garis tepi berwarna */
        .kartu-hijau { background-image: linear-gradient(135deg, #ffffff 55%, rgba(16,185,129,.14) 100%); border-color: rgba(16,185,129,.35) !important; }
        .kartu-amber { background-image: linear-gradient(135deg, #ffffff 55%, rgba(245,158,11,.16) 100%); border-color: rgba(245,158,11,.40) !important; }
        .kartu-merah { background-image: linear-gradient(135deg, #ffffff 55%, rgba(239,68,68,.14) 100%); border-color: rgba(239,68,68,.35) !important; }

        /* Tabel Rekap Yield per Plant: header (2 baris) menempel di atas layar saat halaman di-scroll.
           Sticky tidak jalan kalau pembungkusnya overflow-x:auto, jadi scroll horizontal hanya
           diaktifkan di layar sempit. */
        .titiknol-head th { background-color: #f8fafc; } /* slate-50, agar baris di bawah tidak tembus */
        @media (max-width: 1279px) {
            .titiknol-scroll { overflow-x: auto; }
            .titiknol-table { min-width: 1100px; }
        }
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-800 antialiased">

    <!-- ================================= VIEW 1: DASHBOARD UTAMA NASIONAL ================================= -->
    <div id="nationalDashboardView">
        <!-- Header / Navbar -->
        <header class="bg-blue-900 text-white shadow-lg">
            <div class="container mx-auto px-6 py-4 flex flex-wrap gap-3 justify-between items-center">
                <div class="flex items-center space-x-4">
                    <a href="{{ url('/') }}" class="bg-blue-800 hover:bg-blue-700 text-white text-xs font-semibold px-4 py-2 rounded-xl transition flex items-center space-x-1.5">
                        <span>&larr; Kembali</span>
                    </a>
                    <h1 class="text-xl font-bold tracking-wide">Dashboard Yield Produksi Nasional</h1>
                </div>
                <div class="flex items-center space-x-2">
                    <label for="monthSelect" class="text-xs font-medium text-blue-100">Periode:</label>
                    <input type="month" id="monthSelect" onchange="setMonth(this.value)"
                        class="text-xs font-medium text-white bg-blue-800/80 border border-blue-700 rounded-full px-3.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-400"
                        style="color-scheme: dark;">
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="container mx-auto px-6 py-8 space-y-8">

            <!-- Control Panel: Upload Data Harian -->
            <section class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                <h2 class="text-base font-bold text-slate-900 mb-1">Upload Data Yield Produksi</h2>
                <p class="text-xs text-slate-500 mb-5">Pilih plant, lalu klik Upload Excel dan masukkan kode otorisasi plant. 1 file = 1 plant, boleh berisi satu hari atau beberapa hari (tiap hari satu blok kolom); tanggal dibaca otomatis dari file. Upload ulang untuk tanggal yang sama akan menimpa data lama.</p>

                <div class="flex flex-wrap items-end gap-4">
                    <div class="w-full md:w-72">
                        <label for="selectPlant" class="block text-xs font-semibold text-slate-600 mb-1.5">Pilih Plant</label>
                        <select id="selectPlant" class="w-full text-xs text-slate-700 bg-slate-50/50 border border-slate-200 rounded-xl px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Pilih Plant --</option>
                        </select>
                    </div>
                    <button onclick="mulaiUploadExcel()" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-xl transition duration-200 text-xs shadow-sm shadow-blue-500/20">Upload Excel</button>
                    <input type="file" id="excelUploadInput" accept=".xlsx,.xls" style="display:none;" onchange="handleExcelUpload(this)">
                </div>

                <p id="uploadStatus" class="text-xs font-medium text-emerald-600 mt-4 hidden"></p>
            </section>

            <!-- Grafik Rekapitulasi Nasional -->
            <section class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                <div class="flex flex-wrap justify-between items-center mb-6 gap-2">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Grafik Rata-Rata Final Yield per Plant</h2>
                        <p id="chartPeriodLabel" class="text-xs text-slate-400 mt-0.5"></p>
                    </div>
                    <div class="flex items-center space-x-4 text-xs font-medium">
                        <div class="flex items-center space-x-1.5">
                            <span class="inline-block w-3 h-3 bg-emerald-500 rounded-sm"></span>
                            <span class="text-slate-600">&ge; 93% (Target Tercapai)</span>
                        </div>
                        <div class="flex items-center space-x-1.5">
                            <span class="inline-block w-3 h-3 bg-amber-400 rounded-sm"></span>
                            <span class="text-slate-600">&lt; 93% (Di Bawah Target)</span>
                        </div>
                        <div class="flex items-center space-x-1.5">
                            <span class="inline-block w-5 h-0.5 bg-rose-500"></span>
                            <span class="text-slate-600 font-semibold">Standar (93%)</span>
                        </div>
                    </div>
                </div>
                <div id="chartWrap" class="relative h-96 w-full">
                    <canvas id="yieldChart"></canvas>
                </div>
                <div id="chartEmpty" class="hidden py-20 text-center text-slate-400 text-xs">
                    Belum ada data Yield di periode ini. Silakan upload file Excel untuk menampilkan grafik.
                </div>
                <p class="text-xs text-slate-400 mt-3 text-center">*Klik pada batang grafik atau tombol detail pada tabel di bawah untuk melihat rincian harian plant. Nilai adalah rata-rata berbobot terhadap Berat Bersih.</p>
            </section>

            <!-- Tabel Rekap Yield per Plant (Yield thd Titik Nol, Achievement, Final Yield, Aksi) -->
            <section class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                <h2 class="text-base font-bold text-slate-900">Rekap Yield per Plant</h2>
                <p class="text-xs text-slate-400 mt-0.5 mb-4">Semua plant, rata-rata berbobot untuk periode terpilih. Klik baris atau tombol Detail untuk melihat rincian harian. Tanda "-" berarti tidak ada produksi di jalur tersebut.</p>

                <div class="titiknol-scroll">
                    <table class="titiknol-table w-full text-left border-collapse text-xs">
                        <thead class="titiknol-head sticky top-0 z-10 bg-slate-50 text-slate-500 uppercase tracking-wider shadow-sm">
                            <tr class="border-b border-slate-100">
                                <th rowspan="2" class="py-3 px-3 font-semibold align-middle">Plant</th>
                                <th colspan="2" class="py-3 px-3 font-semibold text-center border-l border-slate-100">H1 (Griller)</th>
                                <th colspan="2" class="py-3 px-3 font-semibold text-center border-l border-slate-100">H2 (Parting)</th>
                                <th colspan="2" class="py-3 px-3 font-semibold text-center border-l border-slate-100">H3 (Cut-Up)</th>
                                <th colspan="2" class="py-3 px-3 font-semibold text-center border-l border-slate-100">H4 (Boneless)</th>
                                <th rowspan="2" class="py-3 px-3 font-semibold text-center align-middle border-l border-slate-100">Yield FG</th>
                                <th rowspan="2" class="py-3 px-3 font-semibold text-center align-middle border-l border-slate-100">Final Yield</th>
                                <th rowspan="2" class="py-3 px-3 font-semibold text-center align-middle border-l border-slate-100">Aksi</th>
                            </tr>
                            <tr class="border-b border-slate-100 text-[10px]">
                                <th class="py-2 px-3 font-semibold text-center border-l border-slate-100">Yield H1 thd Titik 0</th>
                                <th class="py-2 px-3 font-semibold text-center">Ach. Yield</th>
                                <th class="py-2 px-3 font-semibold text-center border-l border-slate-100">Yield H2 thd Titik 0</th>
                                <th class="py-2 px-3 font-semibold text-center">Ach. Yield</th>
                                <th class="py-2 px-3 font-semibold text-center border-l border-slate-100">Yield H3 thd Titik 0</th>
                                <th class="py-2 px-3 font-semibold text-center">Ach. Yield</th>
                                <th class="py-2 px-3 font-semibold text-center border-l border-slate-100">Yield H4 thd Titik 0</th>
                                <th class="py-2 px-3 font-semibold text-center">Ach. Yield</th>
                            </tr>
                        </thead>
                        <tbody id="titikNolTableBody" class="divide-y divide-slate-100 text-slate-700 font-medium">
                            <tr><td colspan="12" class="py-6 text-center text-slate-400">Memuat data...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>


    <!-- ================================= VIEW 2: DETAIL DASHBOARD PLANT ================================= -->
    <div id="plantDetailView" class="hidden min-h-screen bg-slate-50 pb-12">
        <!-- Top Navbar Detail -->
        <header class="bg-blue-900 text-white shadow-lg">
            <div class="container mx-auto px-6 py-4 flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <button onclick="backToMainDashboard()" class="bg-blue-800 hover:bg-blue-700 text-white text-xs font-semibold px-4 py-2 rounded-xl transition flex items-center space-x-1.5">
                        <span>&larr; Kembali</span>
                    </button>
                    <h1 id="detailHeaderTitle" class="text-xl font-bold tracking-wide">Detail Yield Plant</h1>
                </div>
                <span id="detailPeriodLabel" class="text-xs font-medium bg-blue-800/80 px-3.5 py-1.5 rounded-full border border-blue-700">Periode: -</span>
            </div>
        </header>

        <main class="container mx-auto px-6 py-8 space-y-8">

            <!-- 4 Grid Kartu Rekapitulasi 1 Bulan di Bagian Atas -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <div id="boxFinalYield" class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-semibold text-slate-400 block mb-1">1. Final Yield % (1 Bulan)</span>
                        <h3 id="cardFinalYield" class="text-2xl font-bold text-slate-900">0,00%</h3>
                        <span id="cardFinalYieldSub" class="text-[10px] text-slate-500 font-semibold mt-1 inline-block">Target &ge; 93%</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 flex items-center justify-center text-blue-600">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 17l6-6 4 4 8-8"/>
                            <path d="M15 7h6v6"/>
                        </svg>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-semibold text-slate-400 block mb-1">2. Griller Titik Nol %</span>
                        <h3 id="cardGrillerNol" class="text-2xl font-bold text-slate-900">0,00%</h3>
                        <span class="text-[10px] text-slate-500 font-medium mt-1 inline-block">Rata-rata bulanan (berbobot)</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-600">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9"/>
                            <circle cx="12" cy="12" r="5"/>
                            <circle cx="12" cy="12" r="1" fill="currentColor"/>
                        </svg>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-semibold text-slate-400 block mb-1">3. FG + BP Others %</span>
                        <h3 id="cardFgBp" class="text-2xl font-bold text-slate-900">0,00%</h3>
                        <span class="text-[10px] text-slate-500 font-medium mt-1 inline-block">Akumulasi produk jadi</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 flex items-center justify-center text-emerald-600">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 3 3.5 7.5v9L12 21l8.5-4.5v-9L12 3Z"/>
                            <path d="M3.5 7.5 12 12l8.5-4.5M12 12v9"/>
                        </svg>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-semibold text-slate-400 block mb-1">4. ABW (Avg Body Weight)</span>
                        <h3 id="cardAbw" class="text-2xl font-bold text-slate-900">0,00</h3>
                        <span class="text-[10px] text-slate-500 font-medium mt-1 inline-block">Berat rata-rata ayam hidup (kg/ekor)</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-amber-50 flex items-center justify-center text-amber-600">
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 20h12l-1.5-9h-9L6 20Z"/>
                        <circle cx="12" cy="7" r="2.5"/>
                    </svg>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-semibold text-slate-400 block mb-1">5. Berat Bersih (1 Bulan)</span>
                        <h3 id="cardBeratBersih" class="text-2xl font-bold text-slate-900">0 kg</h3>
                        <span class="text-[10px] text-slate-500 font-medium mt-1 inline-block">Total berat bersih sebulan</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-sky-50 flex items-center justify-center text-sky-600">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 3 3 8l9 5 9-5-9-5Z"/>
                            <path d="M3 13l9 5 9-5"/>
                        </svg>
                    </div>
                </div>
                <div id="boxSusut" class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-semibold text-slate-400 block mb-1">6. Susut % (1 Bulan)</span>
                        <h3 id="cardSusut" class="text-2xl font-bold text-slate-900">0,00%</h3>
                        <span id="cardSusutSub" class="text-[10px] text-slate-500 font-medium mt-1 block">0 kg dari 0 kg berat LB</span>
                        <span id="cardSusutBatas" class="text-[10px] text-slate-500 font-semibold block">Batas maksimum 2,5%</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-rose-50 flex items-center justify-center text-rose-600">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 7l6 6 4-4 8 8"/>
                            <path d="M15 17h6v-6"/>
                        </svg>
                    </div>
                </div>

            </div>

            <!-- Grid Grafik & Grafik Lingkaran (Donat) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-base font-bold text-slate-900">Pergerakan Final Yield Per Hari (1 Bulan)</h3>
                        <span class="text-xs font-medium text-slate-400">Data Harian</span>
                    </div>
                    <div class="relative h-80 w-full">
                        <canvas id="dailyYieldTrendChart"></canvas>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex flex-col justify-between">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 mb-1">Komposisi Griller Titik Nol</h3>
                        <p class="text-xs text-slate-400 mb-4">Porsi RM titik nol per jalur (total sebulan)</p>
                        <div class="relative h-56 w-full flex items-center justify-center">
                            <canvas id="grillerDonutChart"></canvas>
                        </div>
                    </div>
                    <div id="donutLegend" class="space-y-2 mt-4 text-xs font-medium text-slate-600">
                        <!-- Legend dinamis -->
                    </div>
                </div>
            </div>

            <!-- Grid Data Harian 1 Bulan -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-4">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Data Harian Produksi (1 Bulan Penuh)</h3>
                        <p class="text-xs text-slate-400">Rincian parameter operasional harian plant terpilih</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 text-slate-400 uppercase tracking-wider border-b border-slate-100">
                                <th class="py-3 px-3 font-semibold">Tanggal</th>
                                <th class="py-3 px-3 font-semibold text-right">Susut (kg)</th>
                                <th class="py-3 px-3 font-semibold text-right">Susut (%)</th>
                                <th class="py-3 px-3 font-semibold text-right">ABW</th>
                                <th class="py-3 px-3 font-semibold text-right">Berat Bersih</th>
                                <th class="py-3 px-3 font-semibold text-center">Titik Nol %</th>
                                <th class="py-3 px-3 font-semibold text-center">H1 %</th>
                                <th class="py-3 px-3 font-semibold text-center">H2 %</th>
                                <th class="py-3 px-3 font-semibold text-center">H3 %</th>
                                <th class="py-3 px-3 font-semibold text-center">H4 %</th>
                                <th class="py-3 px-3 font-semibold text-center">FG + BP Others %</th>
                                <th class="py-3 px-3 font-semibold text-center">By Prod Evis %</th>
                                <th class="py-3 px-3 font-semibold text-center">Final Yield %</th>
                            </tr>
                        </thead>
                        <tbody id="dailyDataTableBody" class="divide-y divide-slate-100 text-slate-700 font-medium">
                            <tr>
                                <td colspan="13" class="py-6 text-center text-slate-400">Belum ada data harian yang tersedia.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const ROUTES = {
            upload: `{{ route('slaughter.yield.upload') }}`,
            cekKode: `{{ route('slaughter.yield.cek-kode') }}`,
            data: `{{ route('slaughter.yield.data') }}`,
            filterOptions: `{{ route('slaughter.yield.filter-options') }}`,
        };

        const TARGET = 93; // standar Final Yield (%)
        const BATAS_SUSUT = 2.5; // batas maksimum Susut (%), di atas ini kartu merah
        const BULAN_NAMA = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        let currentBulan = new Date().getMonth() + 1;
        let currentTahun = new Date().getFullYear();

        let allPlantsByRegion = {};   // { region: [plant, ...] } dari plantMap() server (22 plant)
        let plantSummaries = [];      // ringkasan per plant (urut sesuai plantMap) + baris harian
        let chartPlants = [];         // plant yang punya data (urut sama dengan batang grafik)
        let loadToken = 0;            // mencegah hasil lama menimpa hasil baru saat ganti bulan cepat
        let pendingKode = '';

        let dailyTrendChartInstance = null;
        let grillerDonutChartInstance = null;

        // ===================== Helper format =====================
        const nf = (v, d) => Number(v).toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d });
        const pct = v => nf(Number(v) * 100, 2) + '%';
        const dashIfZero = (v, fmt) => (v === null || v === undefined || Number(v) === 0) ? '-' : fmt(v);

        function escapeHtml(v) {
            return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        }

        // ===================== Chart utama (nasional) =====================
        const ctx = document.getElementById('yieldChart').getContext('2d');
        const yieldChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [],
                datasets: [{
                    label: 'Final Yield Bulanan (%)',
                    data: [],
                    backgroundColor: 'rgba(37, 99, 235, 0.8)',
                    borderColor: 'rgba(37, 99, 235, 1)',
                    borderWidth: 1.5,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 10 } } },
                    y: { min: 0, max: 105, grid: { color: 'rgba(241, 245, 249, 1)' }, ticks: { font: { size: 10 } } }
                },
                onClick: (e, elements) => {
                    if (elements.length > 0 && chartPlants[elements[0].index]) {
                        openPlantDetail(chartPlants[elements[0].index].name);
                    }
                },
                plugins: {
                    legend: { display: false },
                    datalabels: {
                        anchor: 'end', align: 'end', color: '#334155',
                        font: { size: 10, weight: 'bold' },
                        formatter: value => nf(value, 2) + '%'
                    },
                    annotation: {
                        annotations: {
                            standardLine: {
                                type: 'line', yMin: TARGET, yMax: TARGET,
                                borderColor: 'rgba(244, 63, 94, 0.9)', borderWidth: 2, borderDash: [6, 6],
                                label: { content: 'Standar: 93%', enabled: true, position: 'end', backgroundColor: 'rgba(244, 63, 94, 0.9)', font: { size: 10, weight: 'bold' } }
                            }
                        }
                    }
                }
            },
            plugins: [ChartDataLabels]
        });

        // ===================== Agregasi (berbobot) =====================
        // Semua persen bulanan = total Qty / total Berat Bersih (bukan rata-rata biasa dari persen harian).
        // ABW = total kg LB / total ekor LB.
        function aggregate(rows) {
            const s = { bb: 0, final: 0, h0: 0, h1: 0, h2: 0, h3: 0, h4: 0, fgbp: 0, bp: 0, lbKg: 0, lbEkor: 0, susut: 0, griller: 0, parting: 0, cutup: 0, boneless: 0 };
            rows.forEach(r => {
                s.bb       += Number(r.berat_bersih || 0);
                s.final    += Number(r.final_yield_kg || 0);
                s.h0       += Number(r.rm_h0_kg || 0);
                s.h1       += Number(r.h1_kg || 0);
                s.h2       += Number(r.h2_kg || 0);
                s.h3       += Number(r.h3_kg || 0);
                s.h4       += Number(r.h4_kg || 0);
                s.fgbp     += Number(r.fg_bp_kg || 0);
                s.bp       += Number(r.bp_evisc_kg || 0);
                s.lbKg     += Number(r.sppa_lb_kg || 0);
                s.lbEkor   += Number(r.sppa_lb_ekor || 0);
                s.susut    += Number(r.susut_kg || 0);
                s.griller  += Number(r.rm_griller_kg || 0);
                s.parting  += Number(r.rm_parting_kg || 0);
                s.cutup    += Number(r.rm_cutup_kg || 0);
                s.boneless += Number(r.rm_boneless_kg || 0);
            });
            return {
                finalYield: s.bb > 0 ? s.final / s.bb : 0,
                titikNol:   s.bb > 0 ? s.h0 / s.bb : 0,
                // H1-H4 = yield per jalur: total hasil jalur / total RM jalur itu sendiri (bukan Berat Bersih)
                h1:         s.griller  > 0 ? s.h1 / s.griller  : 0,
                h2:         s.parting  > 0 ? s.h2 / s.parting  : 0,
                h3:         s.cutup    > 0 ? s.h3 / s.cutup    : 0,
                h4:         s.boneless > 0 ? s.h4 / s.boneless : 0,
                fgBp:       s.bb > 0 ? s.fgbp / s.bb : 0,
                byProduct:  s.bb > 0 ? s.bp / s.bb : 0,
                abw:        s.lbEkor > 0 ? s.lbKg / s.lbEkor : 0,
                beratBersih: s.bb,
                lbKg: s.lbKg,
                susutKg:    s.susut,
                susutPersen: s.lbKg > 0 ? s.susut / s.lbKg : 0,
                komposisi:  [s.griller, s.parting, s.cutup, s.boneless],
            };
        }

        function buildSummaries(rows) {
            const byPlant = {};
            rows.forEach(r => {
                r.tanggal = String(r.tanggal).slice(0, 10);
                if (!byPlant[r.plant]) byPlant[r.plant] = [];
                byPlant[r.plant].push(r);
            });

            plantSummaries = [];
            Object.keys(allPlantsByRegion).forEach(region => {
                allPlantsByRegion[region].forEach(name => {
                    const days = (byPlant[name] || []).slice().sort((a, b) => a.tanggal.localeCompare(b.tanggal));
                    plantSummaries.push({ name, region, days, hasData: days.length > 0, ...aggregate(days) });
                });
            });
        }

        // ===================== Muat data =====================
        async function loadFilterOptions() {
            const res = await fetch(ROUTES.filterOptions, { headers: { 'Accept': 'application/json' } });
            const opts = await res.json();
            allPlantsByRegion = opts.all_plants_by_region || {};

            const select = document.getElementById('selectPlant');
            select.innerHTML = '<option value="">-- Pilih Plant --</option>';
            Object.keys(allPlantsByRegion).forEach(region => {
                const group = document.createElement('optgroup');
                group.label = region;
                allPlantsByRegion[region].forEach(plant => {
                    const opt = document.createElement('option');
                    opt.value = plant;
                    opt.textContent = plant;
                    group.appendChild(opt);
                });
                select.appendChild(group);
            });
        }

        function updatePeriodLabels() {
            const label = BULAN_NAMA[currentBulan] + ' ' + currentTahun;
            document.getElementById('chartPeriodLabel').innerText = 'Periode: ' + label;
            document.getElementById('detailPeriodLabel').innerText = 'Periode: ' + label;
            document.getElementById('monthSelect').value = `${currentTahun}-${String(currentBulan).padStart(2, '0')}`;
        }

        async function refreshView() {
            const token = ++loadToken;
            updatePeriodLabels();

            try {
                const res = await fetch(`${ROUTES.data}?bulan=${currentBulan}&tahun=${currentTahun}`, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('Server mengembalikan status ' + res.status);
                const rows = await res.json();
                if (token !== loadToken) return; // sudah ada permintaan yang lebih baru

                buildSummaries(rows);
                renderTabelRekap();
                updateNationalChart();
            } catch (err) {
                console.error(err);
                if (token !== loadToken) return;
                Swal.fire({ title: 'Gagal memuat data', text: err.message, icon: 'error' });
            }
        }

        function setMonth(value) {
            if (!value) return;
            const [tahun, bulan] = value.split('-');
            currentTahun = parseInt(tahun, 10);
            currentBulan = parseInt(bulan, 10);
            refreshView();
        }

        // ===================== Render dashboard nasional =====================
        // Tabel Rekap Yield per Plant.
        // Ach. Yield        = yield jalur (aggregate().h1..h4, terhadap RM jalurnya sendiri)
        // Yield thd Titik 0 = Ach. Yield x Yield Titik Nol (sama dengan rumus D25/D34/D45/D55 di Excel)
        // Yield FG          = FG + BP Others (D57)
        // Final Yield       = Total FG + BP (D59), badge hijau jika >= TARGET, amber jika di bawahnya
        // Aksi              = tombol Detail (hanya untuk plant yang punya data); seluruh baris juga bisa diklik
        function renderTabelRekap() {
            const body = document.getElementById('titikNolTableBody');

            if (plantSummaries.length === 0) {
                body.innerHTML = `<tr><td colspan="12" class="py-6 text-center text-slate-400">Daftar plant belum termuat.</td></tr>`;
                return;
            }

            const sel = (v, border) =>
                `<td class="py-3 px-3 text-center ${border ? 'border-l border-slate-100' : ''}">${dashIfZero(v, pct)}</td>`;

            body.innerHTML = plantSummaries.map(p => {
                const jalur = [p.h1, p.h2, p.h3, p.h4];
                const kolomJalur = jalur.map(ach => sel(ach * p.titikNol, true) + sel(ach, false)).join('');

                const fy = p.finalYield * 100;
                const badgeColor = !p.hasData
                    ? 'bg-slate-100 text-slate-500 border border-slate-200'
                    : (fy >= TARGET ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/60' : 'bg-amber-50 text-amber-700 border border-amber-200/60');
                const badgeText = p.hasData ? pct(p.finalYield) : 'Belum ada data';

                const rowClass = p.hasData
                    ? 'group cursor-pointer hover:bg-blue-50/40 transition'
                    : 'opacity-60';

                return `
                    <tr class="${rowClass}" ${p.hasData ? `data-plant="${escapeHtml(p.name)}"` : ''}>
                        <td class="py-3 px-3 whitespace-nowrap">
                            <div class="font-bold text-slate-800">${escapeHtml(p.name)}</div>
                            <div class="text-[10px] font-medium text-slate-400">${escapeHtml(p.region)}${p.hasData ? ' &middot; ' + p.days.length + ' hari data' : ''}</div>
                        </td>
                        ${kolomJalur}
                        ${sel(p.fgBp, true)}
                        <td class="py-3 px-3 text-center border-l border-slate-100">
                            <span class="px-2.5 py-1 rounded-xl text-xs font-bold whitespace-nowrap ${badgeColor}">${badgeText}</span>
                        </td>
                        <td class="py-3 px-3 text-center border-l border-slate-100">
                            ${p.hasData ? `<button type="button" class="bg-slate-100 group-hover:bg-blue-600 group-hover:text-white text-slate-600 text-xs font-semibold px-3 py-1.5 rounded-xl transition">Detail</button>` : ''}
                        </td>
                    </tr>`;
            }).join('');

            // Klik baris atau tombol Detail -> buka detail plant (klik tombol naik ke baris, jadi cukup satu handler)
            body.querySelectorAll('tr[data-plant]').forEach(tr => {
                tr.onclick = () => openPlantDetail(tr.dataset.plant);
            });
        }

        // Grafik nasional: hanya plant yang punya data di periode terpilih
        function updateNationalChart() {
            chartPlants = plantSummaries.filter(p => p.hasData);
            const empty = chartPlants.length === 0;
            document.getElementById('chartWrap').classList.toggle('hidden', empty);
            document.getElementById('chartEmpty').classList.toggle('hidden', !empty);

            const values = chartPlants.map(p => Number((p.finalYield * 100).toFixed(2)));
            yieldChart.data.labels = chartPlants.map(p => p.name);
            yieldChart.data.datasets[0].data = values;
            yieldChart.data.datasets[0].backgroundColor = values.map(v => v >= TARGET ? 'rgba(16, 185, 129, 0.85)' : 'rgba(251, 191, 36, 0.85)');
            yieldChart.data.datasets[0].borderColor = values.map(v => v >= TARGET ? 'rgba(5, 150, 105, 1)' : 'rgba(245, 158, 11, 1)');
            yieldChart.update();
        }

        // status: 'hijau' | 'amber' | 'merah' | null (polos)
        function setStatusKartu(id, status) {
            const el = document.getElementById(id);
            el.classList.remove('kartu-hijau', 'kartu-amber', 'kartu-merah');
            if (status) el.classList.add('kartu-' + status);
        }

        // ===================== Detail plant =====================
        function openPlantDetail(plantName) {
            const plant = plantSummaries.find(p => p.name === plantName);
            if (!plant || !plant.hasData) return;

            document.getElementById('nationalDashboardView').classList.add('hidden');
            document.getElementById('plantDetailView').classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });

            document.getElementById('detailHeaderTitle').innerText = `Detail Yield - ${plant.name}`;

            // 6 kartu atas
            document.getElementById('cardFinalYield').innerText = pct(plant.finalYield);
            document.getElementById('cardGrillerNol').innerText = pct(plant.titikNol);
            document.getElementById('cardFgBp').innerText = pct(plant.fgBp);
            document.getElementById('cardAbw').innerText = nf(plant.abw, 2);
            document.getElementById('cardBeratBersih').innerText = nf(plant.beratBersih, 0) + ' kg';

            // Final Yield: hijau jika mencapai target, amber jika di bawah (dibanding setelah dibulatkan 2 desimal)
            const fyTercapai = Number((plant.finalYield * 100).toFixed(2)) >= TARGET;
            setStatusKartu('boxFinalYield', fyTercapai ? 'hijau' : 'amber');
            const fySub = document.getElementById('cardFinalYieldSub');
            fySub.innerText = (fyTercapai ? 'Target tercapai' : 'Di bawah target') + ` (\u2265 ${TARGET}%)`;
            fySub.className = 'text-[10px] font-semibold mt-1 inline-block ' + (fyTercapai ? 'text-emerald-600' : 'text-amber-600');

            // Susut: persen sebagai nilai utama, kartu merah jika di atas batas
            const susutTinggi = Number((plant.susutPersen * 100).toFixed(2)) > BATAS_SUSUT;
            document.getElementById('cardSusut').innerText = pct(plant.susutPersen);
            document.getElementById('cardSusutSub').innerText = `${nf(plant.susutKg, 0)} kg dari ${nf(plant.lbKg, 0)} kg berat LB`;
            setStatusKartu('boxSusut', susutTinggi ? 'merah' : null);
            const susutBatas = document.getElementById('cardSusutBatas');
            susutBatas.innerText = (susutTinggi ? 'Di atas batas ' : 'Dalam batas ') + nf(BATAS_SUSUT, 1) + '%';
            susutBatas.className = 'text-[10px] font-semibold block ' + (susutTinggi ? 'text-rose-600' : 'text-slate-500');

            const records = plant.days;

            // Grafik garis harian
            const trendCtx = document.getElementById('dailyYieldTrendChart').getContext('2d');
            if (dailyTrendChartInstance) dailyTrendChartInstance.destroy();

            dailyTrendChartInstance = new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: records.map(r => `Tgl ${parseInt(r.tanggal.slice(8, 10), 10)}`),
                    datasets: [{
                        label: 'Final Yield Harian (%)',
                        data: records.map(r => Number((Number(r.final_yield_persen) * 100).toFixed(2))),
                        borderColor: 'rgba(37, 99, 235, 1)',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointBackgroundColor: 'rgba(37, 99, 235, 1)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 10 }, maxTicksLimit: 10 } },
                        y: { min: 0, max: 105, grid: { color: 'rgba(241, 245, 249, 1)' }, ticks: { font: { size: 10 } } }
                    },
                    plugins: {
                        legend: { display: false },
                        datalabels: { display: false },
                        tooltip: { callbacks: { label: c => ` ${c.dataset.label}: ${nf(c.parsed.y, 2)}%` } }
                    }
                }
            });

            // Donat komposisi RM titik nol: Griller / Parting / Cut Up / Boneless (total sebulan)
            const jalur = [
                { nama: 'Bahan Baku Griller',  warna: '#2563eb' },
                { nama: 'Bahan Baku Parting',  warna: '#0ea5e9' },
                { nama: 'Bahan Baku Cut Up',   warna: '#8b5cf6' },
                { nama: 'Bahan Baku Boneless', warna: '#f59e0b' },
            ];
            const totalKomposisi = plant.komposisi.reduce((a, b) => a + b, 0);
            const komposisiPersen = plant.komposisi.map(v => totalKomposisi > 0 ? (v / totalKomposisi) * 100 : 0);

            const donutCtx = document.getElementById('grillerDonutChart').getContext('2d');
            if (grillerDonutChartInstance) grillerDonutChartInstance.destroy();

            grillerDonutChartInstance = new Chart(donutCtx, {
                type: 'doughnut',
                data: {
                    labels: jalur.map(j => j.nama),
                    datasets: [{
                        data: totalKomposisi > 0 ? komposisiPersen.map(v => Number(v.toFixed(2))) : [1],
                        backgroundColor: totalKomposisi > 0 ? jalur.map(j => j.warna) : ['#e2e8f0'],
                        borderWidth: 0,
                        cutout: '75%'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        datalabels: { display: false },
                        tooltip: { enabled: totalKomposisi > 0, callbacks: { label: c => ` ${c.label}: ${nf(c.parsed, 2)}%` } }
                    }
                }
            });

            document.getElementById('donutLegend').innerHTML = jalur.map((j, i) => `
                <div class="flex justify-between items-center">
                    <span class="flex items-center space-x-2"><span class="w-3 h-3 rounded-full inline-block" style="background:${j.warna}"></span><span>${j.nama}</span></span>
                    <span class="font-bold text-slate-800">${nf(komposisiPersen[i], 2)}%</span>
                </div>
            `).join('');

            // Tabel data harian
            const tbody = document.getElementById('dailyDataTableBody');
            tbody.innerHTML = '';
            if (records.length === 0) {
                tbody.innerHTML = `<tr><td colspan="13" class="py-6 text-center text-slate-400">Belum ada data harian yang tersedia.</td></tr>`;
                return;
            }

            records.forEach(r => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-50/80 transition';
                tr.innerHTML = `
                    <td class="py-3 px-3 font-bold text-slate-800">Tgl ${parseInt(r.tanggal.slice(8, 10), 10)}</td>
                    <td class="py-3 px-3 text-right">${dashIfZero(r.susut_kg, v => nf(v, 0))}</td>
                    <td class="py-3 px-3 text-right">${dashIfZero(r.susut_persen, pct)}</td>
                    <td class="py-3 px-3 text-right">${dashIfZero(r.abw, v => nf(v, 2))}</td>
                    <td class="py-3 px-3 text-right">${dashIfZero(r.berat_bersih, v => nf(v, 0))}</td>
                    <td class="py-3 px-3 text-center">${dashIfZero(r.rm_h0_persen, pct)}</td>
                    <td class="py-3 px-3 text-center">${dashIfZero(r.h1_persen, pct)}</td>
                    <td class="py-3 px-3 text-center">${dashIfZero(r.h2_persen, pct)}</td>
                    <td class="py-3 px-3 text-center">${dashIfZero(r.h3_persen, pct)}</td>
                    <td class="py-3 px-3 text-center">${dashIfZero(r.h4_persen, pct)}</td>
                    <td class="py-3 px-3 text-center">${dashIfZero(r.fg_bp_persen, pct)}</td>
                    <td class="py-3 px-3 text-center">${dashIfZero(r.bp_evisc_persen, pct)}</td>
                    <td class="py-3 px-3 text-center font-bold text-blue-600">${dashIfZero(r.final_yield_persen, pct)}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        function backToMainDashboard() {
            document.getElementById('plantDetailView').classList.add('hidden');
            document.getElementById('nationalDashboardView').classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // ===================== Upload Excel =====================
        function getSelectedPlant() {
            return document.getElementById('selectPlant').value;
        }

        // Langkah 1: pilih plant -> kode otorisasi (dicek server) -> baru file picker dibuka
        async function mulaiUploadExcel() {
            const plant = getSelectedPlant();

            if (!plant) {
                Swal.fire({ title: 'Belum lengkap', text: 'Pilih Plant terlebih dahulu.', icon: 'warning' });
                return;
            }

            const { value: kode, isConfirmed } = await Swal.fire({
                title: `Masukkan kode otorisasi Plant ${escapeHtml(plant)}`,
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
                            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({ plant, kode_otorisasi: value.trim() }),
                        });
                        let result = {};
                        try { result = await res.json(); } catch (e) { /* respons bukan JSON */ }
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

            pendingKode = kode;
            document.getElementById('excelUploadInput').click();
        }

        // Kirim file ke server. simpan=false -> hanya baca & validasi (pratinjau); simpan=true -> simpan.
        // Kode otorisasi dicek server di kedua tahap.
        async function kirimFileYield(file, plant, kodeOtorisasi, simpan) {
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

            let result = {};
            try { result = await res.json(); } catch (e) { /* respons bukan JSON */ }
            if (!res.ok) throw new Error(result.message || 'Gagal memproses file (status ' + res.status + ').');
            return result;
        }

        // Pratinjau upload (1 file bisa berisi banyak hari). r = respons server "ringkasan":
        // { jumlah_hari, rentang_label, jumlah_ditimpa, hari: [...], dilewati: [...] }
        function ringkasanHtml(plant, r) {
            const hari = r.hari || [];
            const dilewati = r.dilewati || [];
            const tglPendek = t => `${t.slice(8, 10)}/${t.slice(5, 7)}/${t.slice(0, 4)}`;
            const warnaYield = v => (Number(v) * 100) >= TARGET ? '#047857' : '#b45309';

            // Rata-rata berbobot Berat Bersih untuk seluruh hari di file
            const totalBB = hari.reduce((a, h) => a + Number(h.berat_bersih || 0), 0);
            const rataFinal = totalBB > 0
                ? hari.reduce((a, h) => a + Number(h.final_yield_persen || 0) * Number(h.berat_bersih || 0), 0) / totalBB
                : 0;

            const tdKiri = 'padding:6px 8px; text-align:left; white-space:nowrap;';
            const tdKanan = 'padding:6px 8px; text-align:right; white-space:nowrap;';
            const thStyle = 'padding:6px 8px; position:sticky; top:0; background:#f8fafc; color:#64748b; font-weight:600; font-size:11px;';

            const baris = hari.map(h => `
                <tr style="border-top:1px solid #f1f5f9;">
                    <td style="${tdKiri}">${escapeHtml(tglPendek(h.tanggal))}${h.akan_ditimpa ? ' <span style="background:#fef3c7; color:#92400e; font-size:10px; font-weight:700; padding:1px 6px; border-radius:6px; margin-left:4px;">timpa</span>' : ''}</td>
                    <td style="${tdKanan}">${nf(h.berat_bersih, 0)}</td>
                    <td style="${tdKanan}">${pct(h.titik_nol_persen)}</td>
                    <td style="${tdKanan}">${pct(h.fg_bp_persen)}</td>
                    <td style="${tdKanan} font-weight:700; color:${warnaYield(h.final_yield_persen)};">${pct(h.final_yield_persen)}</td>
                </tr>`).join('');

            const catatanDilewati = dilewati.length > 0
                ? `<div style="font-size:12px; color:#92400e; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:8px 10px; margin-top:10px;">
                       <b>${dilewati.length} kolom dilewati</b> (kosong, tidak akan disimpan):<br>${dilewati.map(escapeHtml).join('<br>')}
                   </div>`
                : '';

            return `
                <div style="text-align:left; font-size:14px;">
                    <div style="margin-bottom:12px;">
                        Plant <b>${escapeHtml(plant)}</b> &middot; <b>${escapeHtml(r.jumlah_hari)} hari</b> &middot; ${escapeHtml(r.rentang_label)}
                        ${r.jumlah_ditimpa > 0 ? `<span style="background:#fef3c7; color:#92400e; font-size:11px; font-weight:700; padding:2px 8px; border-radius:6px; margin-left:6px;">${r.jumlah_ditimpa} hari menimpa data lama</span>` : ''}
                    </div>
                    <div style="max-height:280px; overflow:auto; border:1px solid #e2e8f0; border-radius:8px;">
                        <table style="width:100%; font-size:12px; border-collapse:collapse;">
                            <thead>
                                <tr>
                                    <th style="${thStyle} text-align:left;">Tanggal</th>
                                    <th style="${thStyle} text-align:right;">Berat Bersih (kg)</th>
                                    <th style="${thStyle} text-align:right;">Titik Nol</th>
                                    <th style="${thStyle} text-align:right;">FG + BP</th>
                                    <th style="${thStyle} text-align:right;">Final Yield</th>
                                </tr>
                            </thead>
                            <tbody>${baris}</tbody>
                        </table>
                    </div>
                    <table style="width:100%; font-size:13px; border-collapse:collapse; margin-top:10px;">
                        <tr>
                            <td style="padding:4px 0; color:#64748b;">Total Berat Bersih</td>
                            <td style="padding:4px 0; text-align:right;">${nf(totalBB, 0)} kg</td>
                        </tr>
                        <tr>
                            <td style="padding:4px 0; font-weight:700;">Rata-rata Final Yield (berbobot)</td>
                            <td style="padding:4px 0; text-align:right; font-weight:700; color:${warnaYield(rataFinal)};">${pct(rataFinal)}</td>
                        </tr>
                    </table>
                    ${catatanDilewati}
                </div>`;
        }

        async function handleExcelUpload(input) {
            const file = input.files[0];
            if (!file) return;

            const plant = getSelectedPlant();
            const kode = pendingKode;

            if (!plant || !kode) {
                Swal.fire({ title: 'Belum lengkap', text: 'Data upload belum lengkap. Silakan klik Upload Excel lagi.', icon: 'warning' });
                input.value = '';
                return;
            }

            try {
                // Tahap 1: baca & validasi file, tampilkan pratinjau (belum ada yang disimpan)
                Swal.fire({
                    title: 'Membaca file...',
                    html: 'Mohon tunggu, sedang membaca dan memeriksa isi file.',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading(),
                });

                const pratinjau = await kirimFileYield(file, plant, kode, false);
                const rp = pratinjau.ringkasan;

                const konfirmasi = await Swal.fire({
                    title: `Simpan ${rp.jumlah_hari} hari data Plant ${escapeHtml(plant)}?`,
                    html: ringkasanHtml(plant, rp) +
                        (rp.jumlah_ditimpa > 0 ? `<div style="font-size:12px; color:#64748b; margin-top:12px;">${rp.jumlah_ditimpa} tanggal sudah punya data dan akan diganti dengan isi file ini.</div>` : ''),
                    icon: 'question',
                    width: '42rem',
                    showCancelButton: true,
                    confirmButtonText: 'Simpan',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#2563eb',
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

                const result = await kirimFileYield(file, plant, kode, true);
                const rs = result.ringkasan;

                await Swal.fire({
                    title: 'Selesai!',
                    html: `<div>Data Plant <b>${escapeHtml(result.plant)}</b> sebanyak <b>${escapeHtml(rs.jumlah_hari)} hari</b> (${escapeHtml(rs.rentang_label)}) berhasil disimpan.</div>`,
                    icon: 'success',
                });

                const status = document.getElementById('uploadStatus');
                status.classList.remove('hidden');
                status.innerText = `✓ Data ${result.plant}: ${rs.jumlah_hari} hari (${rs.rentang_label}) berhasil disimpan.`;

                // Pindah ke bulan data yang baru diupload (bulan data terakhir di file)
                currentBulan = result.bulan;
                currentTahun = result.tahun;

                await refreshView();
            } catch (err) {
                Swal.fire({ title: 'Gagal', text: err.message, icon: 'error' });
            } finally {
                input.value = '';
                pendingKode = ''; // kode sekali pakai per percobaan upload
            }
        }

        // ===================== Init =====================
        (async function init() {
            updatePeriodLabels();
            try {
                await loadFilterOptions();
            } catch (err) {
                console.error(err);
                Swal.fire({ title: 'Gagal memuat daftar plant', text: err.message, icon: 'error' });
            }
            await refreshView();
        })();
    </script>
</body>
</html>