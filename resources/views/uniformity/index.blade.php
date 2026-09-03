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

    <style>
        body { background: #f4f6f8; }
        .page-header {
            background: linear-gradient(135deg, #005f73, #0a9396);
            color: #fff;
            padding: 30px 0;
        }
        .back-link { color: #d7f5f7; text-decoration: none; font-size: 0.85rem; }
        .back-link:hover { color: #fff; }

        .toolbar-card {
            background: #fff;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            padding: 16px 20px;
        }

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

        .btn-upload {
            background: #16a34a;
            border-color: #16a34a;
        }
        .btn-upload:hover { background: #15803d; border-color: #15803d; }

        .toggle-group {
            display: inline-flex;
            background: #eef2f5;
            border-radius: 8px;
            padding: 3px;
        }
        .toggle-btn {
            border: none;
            background: transparent;
            padding: 6px 16px;
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 700;
            color: #556;
        }
        .toggle-btn.active { background: #0a9396; color: #fff; }

        .chart-card {
            background: #fff;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            padding: 24px;
            min-height: 420px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #8a96a3;
        }
        .empty-state i { font-size: 3rem; margin-bottom: 14px; display: block; color: #c3cdd6; }

        .info-week {
            font-size: 0.85rem;
            color: #556;
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

        <div class="toolbar-card mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div id="regionTabs">
                        <button class="tab-region active" data-region="" onclick="setRegion('', this)">Nasional</button>
                        <!-- tab region lain akan di-generate otomatis dari data -->
                    </div>
                    <div class="mt-2">
                        <select id="plantSelect" class="form-select form-select-sm" style="max-width:280px; display:inline-block;" onchange="setPlant(this.value)">
                            <option value="">-- Semua Plant --</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <div class="toggle-group">
                        <button class="toggle-btn active" id="btnModeEkor" onclick="setMode('ekor')">EKOR</button>
                        <button class="toggle-btn" id="btnModePersen" onclick="setMode('persen')">PERSEN (%)</button>
                    </div>
                    <button class="btn btn-upload btn-sm text-white" onclick="document.getElementById('excelUploadInput').click()">
                        <i class="fa-solid fa-file-arrow-up"></i> Upload Excel
                    </button>
                    <input type="file" id="excelUploadInput" accept=".xlsx,.xls" style="display:none;" onchange="handleExcelUpload(this)">
                </div>
            </div>
            <div class="info-week mt-2" id="infoWeek"></div>
        </div>

        <div class="chart-card">
            <h6 class="fw-bold mb-3" id="chartTitle">Nasional - Semua Plant</h6>
            <div id="chartWrapper">
                <canvas id="uniformityChart" height="90"></canvas>
            </div>
            <div class="empty-state" id="emptyState" style="display:none;">
                <i class="fa-solid fa-folder-open"></i>
                <p class="mb-1 fw-semibold">Belum ada data Uniformity minggu ini.</p>
                <p class="mb-0 small">Silakan upload file Excel rekap Uniformity untuk menampilkan grafik.</p>
            </div>
        </div>

    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        const ROUTES = {
            upload: `{{ route('slaughter.uniformity.upload') }}`,
            data: `{{ route('slaughter.uniformity.data') }}`,
            filterOptions: `{{ route('slaughter.uniformity.filter-options') }}`,
        };

        let currentRegion = '';   // '' = nasional (semua region)
        let currentPlant = '';    // '' = semua plant (agregat)
        let currentMode = 'ekor'; // 'ekor' | 'persen'
        let plantsByRegion = {};  // hasil dari filter-options
        let chartInstance = null;
        let latestWeekLabel = null;

        const SIZE_ORDER = ['AK', 'AM', 'AB', 'AJ'];

        async function handleExcelUpload(input) {
            const file = input.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('file', file);

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

                if (!res.ok) {
                    throw new Error(result.message || 'Gagal memproses file.');
                }

                let dilewatiHtml = '';
                if (result.dilewati && result.dilewati.length > 0) {
                    dilewatiHtml = `<div style="text-align:left; max-height:200px; overflow-y:auto; margin-top:12px; font-size:12px;">
                        <b>Dilewati (${result.dilewati.length}):</b>
                        <ul>${result.dilewati.map(d => `<li>${d.plant}: ${d.alasan}</li>`).join('')}</ul>
                    </div>`;
                }

                await Swal.fire({
                    title: 'Selesai!',
                    html: `<div>${result.berhasil} baris data (${result.week ?? '-'}) berhasil disimpan.</div>${dilewatiHtml}`,
                    icon: (result.dilewati && result.dilewati.length > 0) ? 'warning' : 'success',
                });

                await loadFilterOptions();
                await refreshChart();
            } catch (err) {
                Swal.fire({ title: 'Gagal', text: err.message, icon: 'error' });
            } finally {
                input.value = '';
            }
        }

        async function loadFilterOptions() {
            const res = await fetch(ROUTES.filterOptions);
            const opts = await res.json();
            plantsByRegion = opts.plants_by_region || {};

            // Generate tombol tab region (selain Nasional yang sudah statis)
            const tabWrap = document.getElementById('regionTabs');
            tabWrap.querySelectorAll('.tab-region[data-region]:not([data-region=""])').forEach(el => el.remove());

            (opts.regions || []).forEach(region => {
                const btn = document.createElement('button');
                btn.className = 'tab-region';
                btn.dataset.region = region;
                btn.innerText = region;
                btn.onclick = () => setRegion(region, btn);
                tabWrap.appendChild(btn);
            });

            populatePlantDropdown();
        }

        function populatePlantDropdown() {
            const select = document.getElementById('plantSelect');
            select.innerHTML = `<option value="">-- Semua Plant ${currentRegion ? 'di ' + currentRegion : '(Nasional)'} --</option>`;

            let plantList = [];
            if (currentRegion === '') {
                // Nasional -> gabungkan semua plant dari semua region
                Object.values(plantsByRegion).forEach(list => plantList.push(...list));
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

        function setRegion(region, btnEl) {
            currentRegion = region;
            currentPlant = '';
            document.querySelectorAll('.tab-region').forEach(b => b.classList.remove('active'));
            btnEl.classList.add('active');
            populatePlantDropdown();
            refreshChart();
        }

        function setPlant(plant) {
            currentPlant = plant;
            refreshChart();
        }

        function setMode(mode) {
            currentMode = mode;
            document.getElementById('btnModeEkor').classList.toggle('active', mode === 'ekor');
            document.getElementById('btnModePersen').classList.toggle('active', mode === 'persen');
            refreshChart();
        }

        function buildQuery() {
            const params = new URLSearchParams();
            if (currentPlant) {
                params.set('plant', currentPlant);
            } else if (currentRegion) {
                params.set('region', currentRegion);
            }
            return params.toString();
        }

        // Gabungkan baris-baris (bisa 1 plant atau banyak plant) jadi total per size AK/AM/AB/AJ
        function aggregateBySize(rows) {
            const bucket = {};
            SIZE_ORDER.forEach(sz => bucket[sz] = { total_lb: 0, lb_standart: 0 });

            rows.forEach(r => {
                if (bucket[r.size]) {
                    bucket[r.size].total_lb += Number(r.total_lb || 0);
                    bucket[r.size].lb_standart += Number(r.lb_standart || 0);
                }
            });

            return SIZE_ORDER.map(sz => ({
                size: sz,
                total_lb: bucket[sz].total_lb,
                persen_standart: bucket[sz].total_lb > 0 ? (bucket[sz].lb_standart / bucket[sz].total_lb) : 0,
            }));
        }

        function updateChartTitle() {
            let title = 'Nasional - Semua Plant';
            if (currentPlant) {
                title = currentPlant;
            } else if (currentRegion) {
                title = 'Region ' + currentRegion + ' - Semua Plant';
            }
            document.getElementById('chartTitle').innerText = title;
        }

        async function refreshChart() {
            updateChartTitle();
            const qs = buildQuery();

            try {
                const res = await fetch(`${ROUTES.data}?${qs}`);
                const rows = await res.json();

                if (!rows || rows.length === 0) {
                    showEmptyState(true);
                    return;
                }
                showEmptyState(false);

                latestWeekLabel = rows[0]?.week_label ?? null;
                document.getElementById('infoWeek').innerText = latestWeekLabel ? `Data minggu: ${latestWeekLabel}` : '';

                const agg = aggregateBySize(rows);
                renderChart(agg);
            } catch (err) {
                console.error(err);
                showEmptyState(true);
            }
        }

        function showEmptyState(isEmpty) {
            document.getElementById('emptyState').style.display = isEmpty ? 'block' : 'none';
            document.getElementById('chartWrapper').style.display = isEmpty ? 'none' : 'block';
        }

        function renderChart(agg) {
            const ctx = document.getElementById('uniformityChart').getContext('2d');
            const labels = agg.map(a => a.size);

            let datasets = [];

            if (currentMode === 'ekor') {
                datasets = [{
                    label: 'Jumlah Ekor',
                    data: agg.map(a => Math.round(a.total_lb)),
                    backgroundColor: '#0a9396',
                    borderRadius: 6,
                }];
            } else {
                datasets = [
                    {
                        type: 'bar',
                        label: '% Standart',
                        data: agg.map(a => Number((a.persen_standart * 100).toFixed(1))),
                        backgroundColor: agg.map(a => a.persen_standart >= 0.8 ? '#16a34a' : '#f59e0b'),
                        borderRadius: 6,
                        order: 2,
                    },
                    {
                        type: 'line',
                        label: 'Target (80%)',
                        data: agg.map(() => 80),
                        borderColor: '#dc2626',
                        borderDash: [6, 4],
                        pointRadius: 0,
                        borderWidth: 2,
                        order: 1,
                    },
                ];
            }

            if (chartInstance) {
                chartInstance.destroy();
            }

            chartInstance = new Chart(ctx, {
                type: 'bar',
                data: { labels, datasets },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: currentMode === 'persen' },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: currentMode === 'persen' ? 100 : undefined,
                            ticks: {
                                callback: (v) => currentMode === 'persen' ? v + '%' : v.toLocaleString('id-ID'),
                            },
                        },
                    },
                },
            });
        }

        // Init
        (async function init() {
            await loadFilterOptions();
            await refreshChart();
        })();
    </script>

</body>
</html>
