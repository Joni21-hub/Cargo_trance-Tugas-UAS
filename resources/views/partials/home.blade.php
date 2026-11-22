<div id="page-home">
  <!-- NAVIGASI PUBLIK -->
  <header class="pub-nav">
    <div class="pub-nav-inner">
      <div style="display:flex;align-items:center;gap:14px;">
        @include('partials.brand-logo')
        <span class="brand-divider"></span>
        <span class="brand-sub">Visibilitas Pengiriman Maritim Global</span>
      </div>

      <nav class="pub-nav-links">
        <a class="pub-nav-link" href="#beranda-lacak">Lacak Kargo</a>
        <a class="pub-nav-link" href="#beranda-peran">Portal Peran</a>
        <a class="pub-nav-link" href="#beranda-jaringan">Jaringan Pelabuhan</a>
      </nav>

      <div style="display:flex;align-items:center;gap:10px;">
        <button type="button" id="nav-btn-signin" class="btn btn-primary btn-sm" onclick="MeridianApp.navigate('login', '/login')">
          Masuk
        </button>
        <button type="button" id="nav-btn-console" class="btn btn-primary btn-sm hidden" onclick="MeridianApp.openUserDashboard()">
          Buka Dashboard
        </button>
      </div>
    </div>
  </header>

  <!-- HERO + LACAK PUBLIK + BOLA BUMI 3D TERPADU -->
  <section class="hero-section" id="beranda-lacak">
    <div>
      <div class="hero-eyebrow">
        <span class="hero-eyebrow-dot"></span>
        <span>SISTEM PEMANTAUAN MARITIM &amp; KONTAINER</span>
      </div>

      <h1 class="hero-title">Satu bola bumi untuk memantau setiap rute pelayaran.</h1>
      <p class="hero-desc">
        Jelajahi bumi secara bebas, lacak pergerakan kontainer ISO 6346 dari pelabuhan asal hingga tujuan, pantau perhitungan keterlambatan otomatis, dan lihat cuaca laut secara langsung.
      </p>

      <!-- KOLOM LACAK PUBLIK INSTAN -->
      <div class="hero-track-card glass-surface">
        <label class="hero-track-label" for="home-track-input">Lacak Nomor Resi atau Kontainer (ISO 6346)</label>
        <form class="hero-track-form" onsubmit="MeridianApp.handlePublicTrackSubmit(event)">
          <div class="hero-track-input-wrap">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
              <circle cx="9" cy="9" r="6"/>
              <path d="M13.6 13.6L17.5 17.5" stroke-linecap="round"/>
            </svg>
            <input
              type="text"
              id="home-track-input"
              class="hero-track-input"
              placeholder="Contoh: MRD-20261121-XXXX atau CSQU3054383"
              autocomplete="off"
            >
          </div>
          <button type="submit" class="btn btn-accent">Lacak</button>
        </form>
        <div style="margin-top:10px;display:flex;justify-content:space-between;font-size:11.5px;color:var(--ink-muted);">
          <span>Akses publik · Tanpa perlu login</span>
          <a style="color:var(--accent-sky);cursor:pointer;text-decoration:none;font-weight:600;" onclick="MeridianApp.navigate('login', '/login')">Masuk ke Portal Kerja &rarr;</a>
        </div>
      </div>

      <!-- STATISTIK JARINGAN REAL-TIME -->
      <div class="hero-metrics-strip">
        <div>
          <div class="hero-metric-val" id="home-stat-ports">08</div>
          <div class="hero-metric-lbl">Pelabuhan UN/LOCODE</div>
        </div>
        <div>
          <div class="hero-metric-val" id="home-stat-carriers">06</div>
          <div class="hero-metric-lbl">Maskapai Pelayaran</div>
        </div>
        <div>
          <div class="hero-metric-val" id="home-stat-shipments">0</div>
          <div class="hero-metric-lbl">Pengiriman Aktif</div>
        </div>
      </div>
    </div>

    <!-- BOLA BUMI 3D INTERAKTIF DI HERO -->
    <div class="hero-globe-stage glass-surface">
      <div id="hero-globe-map"></div>
      <div class="hero-globe-caption">
        <span>BOLA BUMI SATELIT INTERAKTIF</span>
        <span>GESER &amp; PERBESAR BEBAS</span>
      </div>
    </div>
  </section>

  <!-- 4 DASHBOARD PERAN YANG TERPISAH NYATA -->
  <section class="home-band" id="beranda-peran">
    <div class="band-header">
      <div class="band-kicker">Arsitektur Peran Terpisah</div>
      <h2 class="band-title">Empat ruang kerja berbeda sesuai tanggung jawab nyata.</h2>
      <p class="band-desc">
        Setiap pengguna masuk ke dashboard khusus sesuai otorisasi perannya di server — dengan Bola Bumi 3D di tengah dan bingkai kaca operasional di sisi layar.
      </p>
    </div>

    <div class="roles-showcase">
      <div class="role-showcase-card glass-surface">
        <div class="role-showcase-idx">01 / OPERATOR LOGISTIK</div>
        <h3>Pusat Operasi &amp; Input Kargo</h3>
        <p>Mendaftarkan barang, verifikasi nomor kontainer ISO 6346, mencatat checkpoint perjalanan kapal, serta menangani laporan gangguan &amp; keterlambatan.</p>
      </div>

      <div class="role-showcase-card glass-surface">
        <div class="role-showcase-idx">02 / SHIPPER (PENGIRIM)</div>
        <h3>Persetujuan &amp; Pelacakan Ekspor</h3>
        <p>Meninjau rincian barang yang diinput operator, menyetujui keberangkatan kargo, dan memantau rute kiriman miliknya langsung pada bola bumi 3D.</p>
      </div>

      <div class="role-showcase-card glass-surface">
        <div class="role-showcase-idx">03 / BUYER (PENERIMA)</div>
        <h3>Kedatangan &amp; Serah Terima</h3>
        <p>Memantau pesanan yang sedang berlayar menuju pelabuhan tujuan, mengecek estimasi tiba serta cuaca dermaga, dan mengonfirmasi penerimaan barang.</p>
      </div>

      <div class="role-showcase-card glass-surface">
        <div class="role-showcase-idx">04 / ADMINISTRATOR</div>
        <h3>Kendali Sistem &amp; Master Data</h3>
        <p>Mengelola pengguna dan hak akses RBAC, menambah maskapai pelayaran, mendaftarkan koordinat pelabuhan baru, serta mengawasi jejak audit sistem.</p>
      </div>
    </div>
  </section>

  <!-- DIREKTORI PELABUHAN UN/LOCODE -->
  <section class="home-band" id="beranda-jaringan" style="padding-top:0;">
    <div class="band-header">
      <div class="band-kicker">Registri UN/LOCODE</div>
      <h2 class="band-title">Terminal Maritim Terhubung</h2>
      <p class="band-desc">Koordinat pelabuhan aktif yang tersinkronisasi langsung dengan database dan ditampilkan pada bola bumi Meridian.</p>
    </div>

    <div class="glass-surface" style="border-radius:var(--radius-md);overflow:hidden;">
      <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;text-align:left;">
          <thead>
            <tr style="border-bottom:1px solid rgba(148,163,184,0.16);background:rgba(2,6,17,0.45);">
              <th style="padding:12px 18px;font-size:11px;color:var(--ink-muted);text-transform:uppercase;letter-spacing:0.06em;">Kode</th>
              <th style="padding:12px 18px;font-size:11px;color:var(--ink-muted);text-transform:uppercase;letter-spacing:0.06em;">Nama Terminal / Pelabuhan</th>
              <th style="padding:12px 18px;font-size:11px;color:var(--ink-muted);text-transform:uppercase;letter-spacing:0.06em;">Negara</th>
              <th style="padding:12px 18px;font-size:11px;color:var(--ink-muted);text-transform:uppercase;letter-spacing:0.06em;">Tipe</th>
              <th style="padding:12px 18px;font-size:11px;color:var(--ink-muted);text-transform:uppercase;letter-spacing:0.06em;">Koordinat</th>
            </tr>
          </thead>
          <tbody id="home-ports-tbody"></tbody>
        </table>
      </div>
    </div>
  </section>

  <footer class="pub-footer">
    <div class="pub-footer-inner">
      <div style="display:flex;align-items:center;gap:12px;">
        @include('partials.brand-logo')
        <span>&copy; {{ date('Y') }} Meridian Global Freight Visibility</span>
      </div>
      <div class="mono" style="font-size:11px;color:var(--ink-muted);">
        REST API v1 · ISO 6346 · MapLibre Globe 3D · OpenStreetMap · Open-Meteo
      </div>
    </div>
  </footer>
</div>
