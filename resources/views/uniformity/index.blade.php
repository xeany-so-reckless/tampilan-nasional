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

        .empty-state { text-align: center; padding: 60px 20px; color: var(--muted); }
        .empty-state i { font-size: 3rem; margin-bottom: 14px; display: block; color: #c3cdd6; }

        .info-week { font-size: 0.85rem; color: #556; }

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
                    </div>
                </div>

                <button class="btn btn-upload btn-sm text-white" onclick="document.getElementById('excelUploadInput').click()">
                    <i class="fa-solid fa-file-arrow-up"></i> Upload Excel
                </button>
                <input type="file" id="excelUploadInput" accept=".xlsx,.xls" style="display:none;" onchange="handleExcelUpload(this)">
            </div>
            <div class="info-week mt-2" id="infoWeek"></div>
        </div>

        <div class="chart-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-bold mb-0" id="chartTitle">Nasional - Semua Plant</h6>
    <span class="badge bg-info text-dark" id="chartScopeBadge">Nasional</span>
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
            <h6 class="fw-bold mb-3">Detail per Plant (klik baris untuk lihat rincian)</h6>
            <table class="uniformity-table">
                <thead>
                    <tr>
                        <th>Plant</th>
                        <th>AK</th>
                        <th>AM</th>
                        <th>AB</th>
                        <th>AJ</th>
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
            data: `{{ route('slaughter.uniformity.data') }}`,
            filterOptions: `{{ route('slaughter.uniformity.filter-options') }}`,
        };

        let currentRegion = '';
        let currentPlant = '';
        let currentWeek = '';
        let plantsByRegion = {};
        let chartInstance = null;
        let expandedPlants = new Set();

        const SIZE_ORDER = ['AK', 'AM', 'AB', 'AJ'];
        const TARGET_DEFAULT = 0.8;
        const JAWA_REGIONS = ['Banten', 'Jabar', 'Jateng', 'Jatim'];

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
                if (!res.ok) throw new Error(result.message || 'Gagal memproses file.');

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
                await refreshView();
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
        tabWrap.appendChild(jawaBtn); // fallback kalau Jatim belum ada datanya
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

// TAMBAHKAN FUNGSI INI
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

// TAMBAHKAN FUNGSI INI
function setWeek(week) {
    currentWeek = week;
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
            const map = {};
            rows.forEach(r => {
                if (!map[r.plant]) map[r.plant] = {};
                map[r.plant][r.size] = {
                    persen_standart: Number(r.persen_standart || 0),
                    persen_under: Number(r.persen_under || 0),
                    persen_over: Number(r.persen_over || 0),
                    target: Number(r.target || TARGET_DEFAULT),
                };
            });
            return map;
        }

        async function refreshView() {
            updateChartTitle();
            const qs = buildQuery();

            try {
                const res = await fetch(`${ROUTES.data}?${qs}`);
                const rows = await res.json();

let scopedRows = rows;
if (currentRegion === '__JAWA__') {
    scopedRows = rows.filter(r => JAWA_REGIONS.includes(r.region));
}

if (!scopedRows || scopedRows.length === 0) {
    showEmptyState(true);
    return;
}
showEmptyState(false);

document.getElementById('infoWeek').innerText = scopedRows[0]?.week_label ? `Data minggu: ${scopedRows[0].week_label}` : '';

const rowsForChart = currentPlant ? scopedRows.filter(r => r.plant === currentPlant) : scopedRows;

const agg = aggregateBySize(rowsForChart);
updateStatStrip(agg);
renderChart(agg);
renderPlantTable(groupByPlant(scopedRows));
            } catch (err) {
                console.error(err);
                showEmptyState(true);
            }
        }

        function showEmptyState(isEmpty) {
            document.getElementById('emptyState').style.display = isEmpty ? 'block' : 'none';
            document.getElementById('chartWrapper').style.display = isEmpty ? 'none' : 'block';
            document.getElementById('tableCard').style.display = isEmpty ? 'none' : 'block';
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

        function renderPlantTable(plantMap) {
            const tbody = document.getElementById('plantTableBody');
            const plantNames = Object.keys(plantMap).sort();

            tbody.innerHTML = plantNames.map(plant => {
                const sizes = plantMap[plant];
                const isExpanded = expandedPlants.has(plant);

                const mainRow = `
                    <tr class="plant-row" onclick="togglePlantRow('${plant.replace(/'/g, "\\'")}')">
                        <td>
                            <i class="fa-solid fa-chevron-down expand-icon ${isExpanded ? 'open' : ''}"></i>
                            ${plant}
                        </td>
                        ${SIZE_ORDER.map(sz => `<td>${sizes[sz] ? pctChip(sizes[sz].persen_standart) : '-'}</td>`).join('')}
                    </tr>
                `;

                const detailRow = isExpanded ? `
                    <tr class="detail-row">
                        <td colspan="5">
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
                    <div class="row-metric"><span>Target</span><b style="color:#16a34a;">${(data.target * 100).toFixed(0)}%</b></div>
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

        (async function init() {
            await loadFilterOptions();
            await refreshView();
        })();
    </script>

</body>
</html>