<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Detail Uniformity</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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

        .toolbar-card, .group-card {
            background: #fff;
            border-radius: 10px;
            border: 1px solid var(--line);
        }
        .toolbar-card { padding: 16px 20px; }

        .empty-state { text-align: center; padding: 60px 20px; color: var(--muted); }
        .empty-state i { font-size: 3rem; margin-bottom: 14px; display: block; color: #c3cdd6; }

        .group-card { margin-bottom: 20px; overflow: hidden; }
        .group-header {
            background: #0a9396;
            color: #fff;
            padding: 10px 18px;
            font-weight: 700;
            font-size: 0.9rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
        }
        .group-header .group-sub { font-weight: 400; font-size: 0.75rem; opacity: 0.9; }

        table.detail-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
        table.detail-table thead th {
            background: #f8fafc;
            color: var(--muted);
            font-size: 0.68rem;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            text-align: center;
            padding: 10px 8px;
            border-bottom: 1px solid var(--line);
            white-space: nowrap;
        }
        table.detail-table thead th:first-child { text-align: left; }
        table.detail-table tbody td {
            padding: 8px;
            border-bottom: 1px solid var(--line);
            text-align: center;
            vertical-align: middle;
        }
        table.detail-table tbody td:first-child { text-align: left; font-weight: 600; }
        table.detail-table tbody tr:last-child td { border-bottom: none; }
        table.detail-table tbody tr:hover { background: #f8fafc; }

        .size-badge {
            display: inline-block;
            font-weight: 700;
            font-size: 0.72rem;
            padding: 3px 10px;
            border-radius: 20px;
            background: #e0f2f1;
            color: #0a9396;
        }

        .metric-under { color: #b91c1c; font-weight: 600; }
        .metric-masuk { color: var(--ok); font-weight: 600; }
        .metric-over { color: #b45309; font-weight: 600; }
        .metric-sub { display: block; font-size: 0.7rem; color: var(--muted); font-weight: 400; }

        .filter-checkbox-group label {
            font-size: 0.82rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-right: 14px;
            cursor: pointer;
            user-select: none;
        }
    </style>
</head>
<body>

    <div class="page-header">
        <div class="container">
            <a href="{{ route('slaughter.uniformity.index') }}" class="back-link"><i class="fa-solid fa-arrow-left"></i> Kembali ke Rekap Uniformity</a>
            <h3 class="fw-bold mt-2 mb-0"><i class="fa-solid fa-list-check"></i> Detail Uniformity </h3>
            <p class="mb-0 opacity-75" id="pageSubtitle">Rincian data penerimaan per rit</p>
        </div>
    </div>

    <div class="container my-4">

        <div class="toolbar-card mb-4">
            <div class="row g-3 align-items-end">
                <div class="col-auto">
                    <label class="form-label small fw-bold text-muted mb-1">Plant</label>
                    <div class="fw-bold" id="plantLabel">-</div>
                </div>
                <div class="col-auto">
                    <label class="form-label small fw-bold text-muted mb-1">Dari Tanggal</label>
                    <input type="date" id="filterStart" class="form-control form-control-sm" onchange="reloadDetail()">
                </div>
                <div class="col-auto">
                    <label class="form-label small fw-bold text-muted mb-1">Sampai Tanggal</label>
                    <input type="date" id="filterEnd" class="form-control form-control-sm" onchange="reloadDetail()">
                </div>
                <div class="col-12">
                    <label class="form-label small fw-bold text-muted mb-1 d-block">Filter Kategori Size</label>
                    <div class="filter-checkbox-group">
                        <label><input type="checkbox" class="chk-kategori" value="AK" checked onchange="reloadDetail()"> AK</label>
                        <label><input type="checkbox" class="chk-kategori" value="AM" checked onchange="reloadDetail()"> AM</label>
                        <label><input type="checkbox" class="chk-kategori" value="AB" checked onchange="reloadDetail()"> AB</label>
                        <label><input type="checkbox" class="chk-kategori" value="AJ" checked onchange="reloadDetail()"> AJ</label>
                    </div>
                </div>
            </div>
        </div>

        <div id="detailContent"></div>

        <div class="empty-state" id="emptyState" style="display:none;">
            <i class="fa-solid fa-folder-open"></i>
            <p class="mb-1 fw-semibold">Tidak ada data untuk filter ini.</p>
            <p class="mb-0 small">Coba ganti rentang tanggal atau kategori size yang dipilih.</p>
        </div>

    </div>

    <script>
        const ROUTES = {
            detail: `{{ route('slaughter.uniformity.detail') }}`,
        };

        const SIZE_ORDER = ['AK', 'AM', 'AB', 'AJ'];

        // Ambil plant & rentang tanggal default dari query string URL (dikirim dari halaman utama)
        const urlParams = new URLSearchParams(window.location.search);
        const plantName = urlParams.get('plant') || '';

        function todayStr() {
            const d = new Date();
            return d.toISOString().slice(0, 10);
        }

        function init() {
            if (!plantName) {
                document.getElementById('detailContent').innerHTML = '';
                document.getElementById('emptyState').style.display = 'block';
                document.querySelector('#emptyState p.fw-semibold').innerText = 'Plant tidak ditemukan di URL.';
                document.querySelector('#emptyState p.small').innerText = 'Buka halaman ini dari tombol "Detail" pada halaman Rekap Uniformity.';
                return;
            }

            document.getElementById('plantLabel').innerText = plantName;
            document.getElementById('pageSubtitle').innerText = `Rincian data penerimaan per rit \u2014 ${plantName}`;

            const start = urlParams.get('start') || todayStr();
            const end = urlParams.get('end') || todayStr();
            document.getElementById('filterStart').value = start;
            document.getElementById('filterEnd').value = end;

            reloadDetail();
        }

        function getCheckedKategori() {
            return Array.from(document.querySelectorAll('.chk-kategori:checked')).map(el => el.value);
        }

        async function reloadDetail() {
            const start = document.getElementById('filterStart').value;
            const end = document.getElementById('filterEnd').value;

            if (!start || !end) return;

            if (start > end) {
                document.getElementById('detailContent').innerHTML = '';
                document.getElementById('emptyState').style.display = 'block';
                document.querySelector('#emptyState p.fw-semibold').innerText = 'Rentang tanggal tidak valid.';
                document.querySelector('#emptyState p.small').innerText = '"Dari Tanggal" harus sebelum atau sama dengan "Sampai Tanggal".';
                return;
            }

            const kategori = getCheckedKategori();
            if (kategori.length === 0) {
                document.getElementById('detailContent').innerHTML = '';
                document.getElementById('emptyState').style.display = 'block';
                document.querySelector('#emptyState p.fw-semibold').innerText = 'Tidak ada kategori size dipilih.';
                document.querySelector('#emptyState p.small').innerText = 'Centang minimal satu kategori (AK/AM/AB/AJ) untuk menampilkan data.';
                return;
            }

            const params = new URLSearchParams();
            params.set('plant', plantName);
            params.set('start', start);
            params.set('end', end);
            kategori.forEach(k => params.append('kategori_size[]', k));

            try {
                const res = await fetch(`${ROUTES.detail}?${params.toString()}`);
                const rows = await res.json();
                renderDetail(rows);
            } catch (err) {
                console.error(err);
                document.getElementById('detailContent').innerHTML = '';
                document.getElementById('emptyState').style.display = 'block';
                document.querySelector('#emptyState p.fw-semibold').innerText = 'Gagal memuat data.';
                document.querySelector('#emptyState p.small').innerText = 'Silakan coba lagi beberapa saat.';
            }
        }

        // Kelompokkan baris per tanggal, urut tanggal naik, di dalamnya urut sesuai no_urut (bawaan query backend)
        function groupByTanggal(rows) {
            const map = {};
            rows.forEach(r => {
                const tgl = r.tanggal;
                if (!map[tgl]) map[tgl] = [];
                map[tgl].push(r);
            });
            return Object.keys(map).sort().map(tgl => ({ tanggal: tgl, rows: map[tgl] }));
        }

        function formatTanggalIndo(ymd) {
            const d = new Date(ymd);
            return d.toLocaleDateString('id-ID', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
        }

        function pctOf(part, total) {
            if (!total || total <= 0) return 0;
            return (part / total) * 100;
        }

        function metricCell(ekor, total, cls) {
            const pct = pctOf(ekor, total).toFixed(1);
            return `<td><span class="${cls}">${pct}%</span><span class="metric-sub">${ekor} ekor</span></td>`;
        }

        function renderDetail(rows) {
            const content = document.getElementById('detailContent');
            const empty = document.getElementById('emptyState');

            if (!rows || rows.length === 0) {
                content.innerHTML = '';
                document.querySelector('#emptyState p.fw-semibold').innerText = 'Tidak ada data untuk filter ini.';
                document.querySelector('#emptyState p.small').innerText = 'Coba ganti rentang tanggal atau kategori size yang dipilih.';
                empty.style.display = 'block';
                return;
            }
            empty.style.display = 'none';

            const groups = groupByTanggal(rows);

            content.innerHTML = groups.map(group => {
                const totalRit = group.rows.length;
                const totalEkorGroup = group.rows.reduce((sum, r) => sum + Number(r.total_ekor || 0), 0);

                const bodyRows = group.rows.map(r => {
                    const total = Number(r.total_ekor || 0);
                                        return `
                        <tr>
                            <td>${r.nama_farm ?? '-'}</td>
                            <td><span class="size-badge">${r.kategori_size ?? '-'}</span></td>
                            <td>${r.berat_min !== null ? Number(r.berat_min).toFixed(2) : '-'}</td>
                            <td>${r.berat_max !== null ? Number(r.berat_max).toFixed(2) : '-'}</td>
                            <td>${r.jumlah_sample ?? '-'}</td>
                            <td>${r.ekspedisi ?? '-'}</td>
                            <td>${r.rata_sppa !== null ? Number(r.rata_sppa).toFixed(2) : '-'}</td>
                            <td>${r.rata_rpa !== null ? Number(r.rata_rpa).toFixed(2) : '-'}</td>
                            ${metricCell(r.ekor_under, total, 'metric-under')}
                            ${metricCell(r.ekor_standart, total, 'metric-masuk')}
                            ${metricCell(r.ekor_over, total, 'metric-over')}
                        </tr>
                    `;
                }).join('');

                return `
                    <div class="group-card">
                        <div class="group-header">
                            <span><i class="fa-regular fa-calendar"></i> ${formatTanggalIndo(group.tanggal)}</span>
                            <span class="group-sub">${totalRit} rit &middot; ${totalEkorGroup} ekor</span>
                        </div>
                        <div style="overflow-x:auto;">
                            <table class="detail-table">
                                <thead>
                                    <tr>
                                        <th>Nama Farm</th>
                                        <th>Kategori Size</th>
                                        <th>Size Min</th>
                                        <th>Size Max</th>
                                        <th>Jumlah Sample</th>
                                        <th>Ekspedisi</th>
                                        <th>Rataan SPPA</th>
                                        <th>Rataan RPHU</th>
                                        <th>Under (% / Ekor)</th>
                                        <th>Masuk (% / Ekor)</th>
                                        <th>Over (% / Ekor)</th>
                                    </tr>
                                </thead>
                                <tbody>${bodyRows}</tbody>
                            </table>
                        </div>
                    </div>
                `;
            }).join('');
        }

        init();
    </script>

</body>
</html>
