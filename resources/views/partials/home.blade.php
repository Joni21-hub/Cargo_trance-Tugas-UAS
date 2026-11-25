<div id="page-home">
  <!-- NAVIGASI PUBLIK TERANG & BERSIH -->
  <header class="pub-nav">
    <div class="pub-nav-inner">
      <div class="pub-nav-brand">
        @include('partials.brand-logo')
        <span class="brand-divider"></span>
        <span class="brand-sub">Pelacakan Kargo &amp; Rekening Jaminan Ekspor</span>
      </div>

      <nav class="pub-nav-links">
        <a class="pub-nav-link" href="#beranda-lacak">Lacak Resi</a>
        <a class="pub-nav-link" href="#beranda-alur">Cara Kerja</a>
        <a class="pub-nav-link" href="#beranda-jaringan">Jaringan 5 Pulau</a>
      </nav>

      <div class="pub-nav-actions">
        <button type="button" class="btn btn-outline btn-sm" onclick="MeridianApp.openHelpModal()">
          Bantuan Admin
        </button>
        <button type="button" id="nav-btn-signin" class="btn btn-primary btn-sm" onclick="MeridianApp.navigate('login', '/login')">
          Masuk
        </button>
        <button type="button" id="nav-btn-console" class="btn btn-primary btn-sm hidden" onclick="MeridianApp.openUserDashboard()">
          Buka Dashboard
        </button>
      </div>
    </div>
  </header>

  <!-- HERO + LACAK RESI INSTAN + PETA BUMI 3D -->
  <section class="hero-section" id="beranda-lacak">
    <div class="hero-copy">
      <div class="hero-eyebrow">
        <span class="pulse-dot"></span>
        <span>BUKAN MARKETPLACE &middot; PELACAKAN KARGO &amp; REKENING JAMINAN</span>
      </div>

      <h1 class="hero-title">Kirim kopi Nusantara ke luar negeri dengan aman, terpantau, dan anti-penipuan.</h1>
      <p class="hero-desc">
        Pembeli dan pengirim bebas bernegosiasi langsung. Petugas kami memeriksa fisik barang memakai armada truk cabang di 5 pulau Indonesia, mengirim via kapal atau pesawat mitra, serta menahan dana di Rekening Jaminan hingga barang diterima.
      </p>

      <!-- KOLOM LACAK PUBLIK -->
      <div class="surface-card hero-track-card">
        <label class="field-label-strong" for="home-track-input">Lacak Nomor Resi atau Nomor Kontainer</label>
        <form class="hero-track-form" onsubmit="MeridianApp.handlePublicTrackSubmit(event)">
          <input
            type="text"
            id="home-track-input"
            class="input mono"
            placeholder="Contoh: MRD-20261123-XXXX atau CSQU3054383"
            autocomplete="off"
          >
          <button type="submit" class="btn btn-primary">Lacak Sekarang</button>
        </form>
        <div class="hero-track-foot">
          <span>Bisa dicek publik tanpa login &middot; Nyaman dibuka dari HP maupun laptop</span>
          <a class="text-link" onclick="MeridianApp.navigate('login', '/login')">Masuk ke Portal Kerja &rarr;</a>
        </div>
      </div>

      <!-- RINGKASAN JARINGAN -->
      <div class="hero-metrics-strip">
        <div class="metric-pill-box">
          <div class="metric-pill-val" id="home-stat-ports">17</div>
          <div class="metric-pill-lbl">Pelabuhan &amp; Bandara Mitra</div>
        </div>
        <div class="metric-pill-box">
          <div class="metric-pill-val" id="home-stat-carriers">11</div>
          <div class="metric-pill-lbl">Mitra Kapal, Pesawat &amp; Truk</div>
        </div>
        <div class="metric-pill-box">
          <div class="metric-pill-val" id="home-stat-coffee">9</div>
          <div class="metric-pill-lbl">Kopi Daerah 5 Pulau</div>
        </div>
        <div class="metric-pill-box">
          <div class="metric-pill-val" id="home-stat-shipments">0</div>
          <div class="metric-pill-lbl">Pengiriman Aktif</div>
        </div>
      </div>
    </div>

    <!-- KARTU PETA 3D INTERAKTIF RINGAN DI BERANDA -->
    <div class="surface-card hero-globe-stage">
      <div id="hero-globe-map" class="hero-globe-canvas"></div>
      <div class="hero-globe-caption">
        <span>PETA JARINGAN 5 PULAU INDONESIA &amp; DUNIA</span>
        <span>BISA DIGESER &amp; DIPERBESAR</span>
      </div>
    </div>
  </section>

  <!-- 4 PERAN ANTI-PENIPUAN -->
  <section class="home-band" id="beranda-alur">
    <div class="band-header">
      <div class="band-kicker">ALUR KERJA NYATA &amp; ANTI-PENIPUAN</div>
      <h2 class="band-title">Empat ruang kerja layar penuh yang bersih, jelas, dan saling terhubung.</h2>
      <p class="band-desc">
        Setiap pihak punya tugas yang saling menjaga: Pengirim memanggil petugas cabang, Petugas mengecek fisik barang sebelum menginput manifest resmi, Pembeli menitipkan dana di Rekening Jaminan, dan Admin memverifikasi identitas semua pihak.
      </p>
    </div>

    <div class="roles-showcase">
      <div class="surface-card role-showcase-card">
        <span class="badge badge-sky">01 &middot; OPERATOR CABANG</span>
        <h3>Cek Fisik Barang &amp; Pelacakan</h3>
        <p>Datang ke lokasi pengirim memakai armada truk cabang kami, memeriksa isi kopi, berat sah &amp; segel, menginput manifest resmi, lalu memperbarui jalur truk, kapal, atau pesawat.</p>
      </div>

      <div class="surface-card role-showcase-card">
        <span class="badge badge-warn">02 &middot; PENGIRIM (SHIPPER)</span>
        <h3>Panggil Petugas &amp; Konfirmasi</h3>
        <p>Memajang profil komoditas kopi Nusantara (tanpa patokan harga), bernegosiasi lewat Chat, menekan tombol <strong>Buat Pengiriman</strong> untuk memanggil Operator, dan menyetujui data resmi.</p>
      </div>

      <div class="surface-card role-showcase-card">
        <span class="badge badge-ok">03 &middot; PEMBELI (BUYER)</span>
        <h3>Direktori Kopi &amp; Rekening Jaminan</h3>
        <p>Menemukan eksportir kopi 5 pulau terverifikasi tanpa mencari lewat media sosial, memilih jalur Pesawat (&le; 7 hari) atau Kapal, menyetor dana jaminan aman, dan mencetak surat pengambilan barang.</p>
      </div>

      <div class="surface-card role-showcase-card">
        <span class="badge badge-indigo">04 &middot; ADMINISTRATOR</span>
        <h3>Verifikasi Identitas &amp; Bantuan</h3>
        <p>Dashboard admin layar penuh untuk memverifikasi identitas akun, menugaskan petugas cabang 5 pulau, mengelola mitra pelabuhan/bandara/maskapai, dan menjawab Kotak Bantuan Masuk.</p>
      </div>
    </div>
  </section>

  <!-- JARINGAN 5 PULAU INDONESIA + TUJUAN DUNIA -->
  <section class="home-band" id="beranda-jaringan" style="padding-top:0;">
    <div class="band-header">
      <div class="band-kicker">JARINGAN 5 PULAU INTI INDONESIA &amp; TUJUAN EKSPOR</div>
      <h2 class="band-title">1 Pelabuhan Kapal &amp; 1 Bandara Kargo Mitra di Setiap Pulau</h2>
      <p class="band-desc">Sumatra, Jawa, Kalimantan, Sulawesi, dan Papua terhubung langsung dengan tujuan Amerika Serikat, Eropa, dan Asia.</p>
    </div>

    <div class="surface-card table-card">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Kode</th>
              <th>Nama Pelabuhan / Bandara</th>
              <th>Pulau / Wilayah</th>
              <th>Jalur</th>
              <th>Negara</th>
            </tr>
          </thead>
          <tbody id="home-ports-tbody"></tbody>
        </table>
      </div>
    </div>
  </section>

  <footer class="pub-footer">
    <div class="pub-footer-inner">
      <div class="pub-nav-brand">
        @include('partials.brand-logo')
        <span class="muted-sm">&copy; {{ date('Y') }} Meridian Nusantara — Pelacakan Kargo &amp; Rekening Jaminan</span>
      </div>
      <div class="pub-footer-right">
        <button type="button" class="btn btn-outline btn-sm" onclick="MeridianApp.openHelpModal()">Butuh Bantuan? Hubungi Admin</button>
      </div>
    </div>
  </footer>
</div>
