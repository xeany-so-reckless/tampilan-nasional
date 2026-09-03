<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Data Produksi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    
    <style>
        /* Styling Running Text & Timestamp Top Ticker */
        .top-ticker {
            background: #212529;
            color: #ffffff;
            font-size: 0.85rem;
            padding: 6px 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            overflow: hidden;
            white-space: nowrap;
        }
        .datetime-container {
            flex-shrink: 0;
            background: #343a40;
            padding: 2px 10px;
            border-radius: 4px;
            margin-right: 15px;
            font-weight: 500;
        }
        .marquee-container {
            overflow: hidden;
            width: 100%;
            position: relative;
        }
        .marquee {
            display: inline-block;
            animation: marquee 25s linear infinite;
        }
        @keyframes marquee {
            0%   { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }

        .navbar-custom {
            background: #008891;
        }
        .hero-section {
            background: linear-gradient(135deg, #005f73, #0a9396);
            color: white;
            padding: 50px 0;
        }
        .menu-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
            background: #fff;
        }
        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-color: #0a9396;
        }
        .content-section {
            display: none;
        }
        .content-section.active {
            display: block;
        }
    </style>
</head>
<body>

    <div class="top-ticker">
        <div class="datetime-container" id="current-datetime">
            <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">schedule</span> Memuat waktu...
        </div>
        <div class="marquee-container">
            <div class="marquee">
                Selamat Datang di Sistem Integerasi Laporan PT. CHAROEN POKHPAND INDONESIA - FOOD &nbsp; | &nbsp; 1 Halaman Untuk Report Nasional &nbsp; | &nbsp; Tampilan Beta Test
            </div>
        </div>
    </div>

    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom py-3">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="{{ asset('images/logo.jpg') }}" alt="Logo" style="height: 50px;">
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-center gap-3">
                    <li class="nav-item"><a class="nav-link active fw-semibold" href="#" onclick="switchTab('slaughter', event)">Slaughter House</a></li>
                    <li class="nav-item"><a class="nav-link fw-semibold" href="#" onclick="switchTab('further', event)">Further Processing</a></li>
                    <li class="nav-item"><a class="nav-link fw-semibold" href="#" onclick="switchTab('breadcrumb', event)">Bread Crumb</a></li>
                    <li class="nav-item"><a class="nav-link fw-semibold" href="#" onclick="switchTab('retort', event)">Retort Plant</a></li>
                    <li class="nav-item"><a class="nav-link fw-semibold" href="#" onclick="switchTab('warehouse', event)">Warehouse</a></li>
                    <li class="nav-item">
                        <a href="#" class="btn btn-light text-dark fw-bold rounded-pill px-4">Bantuan Sistem</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <h1 class="fw-bold display-6 mb-3">Rekapitulasi Laporan Nasional Food - Division</h1>
                    <p class="lead mb-4">Sistem monitoring laporan produksi dari Live Birds hingga Finished Goods.</p>
                    <div class="input-group bg-white p-2 rounded-pill shadow-sm" style="max-width: 500px;">
                    </div>
                </div>
                <div class="col-lg-5 text-center mt-4 mt-lg-0">
                    <div class="bg-white p-4 rounded shadow text-dark text-start">
                        <h5 class="fw-bold mb-2"><i class="fa-solid fa-chart-line text-success"></i> Average Data Nasional</h5>
                        <hr>
                        <p class="mb-1 text-muted small">Status Achievement Nasional:</p>
                        <span class="badge bg-success">Good</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <main class="container my-5">
        
        <div id="content-slaughter" class="content-section active">
            <h4 class="fw-bold mb-4 text-secondary"><i class="fa-solid fa-industry"></i> Modul Slaughter House</h4>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="menu-card p-4 h-100 d-flex align-items-center justify-content-between" 
                        onclick="window.location.href='{{ route('slaughter.uniformity.index') }}'">
                        <div>
                            <h6 class="fw-bold mb-1">Rekap Uniformity Mingguan</h6>
                            <small class="text-muted">Kelola data keseragaman bobot</small>
                        </div>
                        <i class="fa-solid fa-chart-pie fs-3 text-primary"></i>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="menu-card p-4 h-100 d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="fw-bold mb-1">Rekap Yield Mingguan</h6>
                            <small class="text-muted">Analisis persentase karkas</small>
                        </div>
                        <i class="fa-solid fa-scale-balanced fs-3 text-success"></i>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="menu-card p-4 h-100 d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="fw-bold mb-1">Laporan Penerimaan Live Birds</h6>
                            <small class="text-muted">Data masuk ayam hidup harian</small>
                        </div>
                        <i class="fa-solid fa-truck-fast fs-3 text-warning"></i>
                    </div>
                </div>
            </div>
        </div>

        <div id="content-further" class="content-section">
            <h4 class="fw-bold mb-4 text-secondary"><i class="fa-solid fa-gear"></i> Modul Further Processing</h4>
            <div class="p-5 border border-dashed rounded text-center bg-light text-muted">
                <i class="fa-solid fa-folder-open fs-1 mb-2"></i>
                <p class="mb-0">Area Modul Further Processing Kosong. (Siap diisi script Blade / komponen anak nantinya)</p>
            </div>
        </div>

        <div id="content-breadcrumb" class="content-section">
            <h4 class="fw-bold mb-4 text-secondary"><i class="fa-solid fa-wheat-awn"></i> Modul Bread Crumb</h4>
            <div class="p-5 border border-dashed rounded text-center bg-light text-muted">
                <i class="fa-solid fa-folder-open fs-1 mb-2"></i>
                <p class="mb-0">Area Modul Bread Crumb Kosong. (Siap diisi script Blade / komponen anak nantinya)</p>
            </div>
        </div>

        <div id="content-retort" class="content-section">
            <h4 class="fw-bold mb-4 text-secondary"><i class="fa-solid fa-fire-burner"></i> Modul Retort Plant</h4>
            <div class="p-5 border border-dashed rounded text-center bg-light text-muted">
                <i class="fa-solid fa-folder-open fs-1 mb-2"></i>
                <p class="mb-0">Area Modul Retort Plant Kosong. (Siap diisi script Blade / komponen anak nantinya)</p>
            </div>
        </div>

        <div id="content-warehouse" class="content-section">
            <h4 class="fw-bold mb-4 text-secondary"><i class="fa-solid fa-warehouse"></i> Modul Warehouse</h4>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="menu-card p-4 h-100 d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="fw-bold mb-1">Laporan Stock Warehouse</h6>
                            <small class="text-muted">Cek ketersediaan barang jadi</small>
                        </div>
                        <i class="fa-solid fa-boxes-stacked fs-3 text-primary"></i>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="menu-card p-4 h-100 d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="fw-bold mb-1">Pengiriman / Outbound</h6>
                            <small class="text-muted">Jadwal & status distribusi</small>
                        </div>
                        <i class="fa-solid fa-truck-ramp-box fs-3 text-info"></i>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="menu-card p-4 h-100 d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="fw-bold mb-1">Monitoring Suhu CS</h6>
                            <small class="text-muted">Cold Storage real-time tracking</small>
                        </div>
                        <i class="fa-solid fa-temperature-snowflake fs-3 text-danger"></i>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Fungsi untuk mengganti tab konten
        function switchTab(tabName, event) {
            // Hilangkan kelas active dari semua section konten
            const sections = document.querySelectorAll('.content-section');
            sections.forEach(sec => sec.classList.remove('active'));

            // Hilangkan kelas active dari semua navbar link
            const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
            navLinks.forEach(link => link.classList.remove('active'));

            // Tampilkan section yang diklik
            const targetSection = document.getElementById('content-' + tabName);
            if (targetSection) {
                targetSection.classList.add('active');
            }

            // Tandai link menu navbar yang aktif
            if (event && event.currentTarget) {
                event.currentTarget.classList.add('active');
            }
        }

        // Fungsi Jam & Tanggal Real-Time
        function updateDateTime() {
            const now = new Date();
            const options = { day: 'numeric', month: 'long', year: 'numeric' };
            const dateStr = now.toLocaleDateString('id-ID', options);
            const timeStr = now.toLocaleTimeString('id-ID', { hour12: false });
            
            const datetimeElement = document.getElementById('current-datetime');
            if (datetimeElement) {
                datetimeElement.innerHTML = `
                    
                    ${dateStr} &nbsp;|&nbsp; ${timeStr} WIB
                `;
            }
        }
        
        // Jalankan interval real-time setiap 1 detik
        setInterval(updateDateTime, 1000);
        updateDateTime();
    </script>
</body>
</html>