<!DOCTYPE html>
<html lang="id" class="notranslate" translate="no">
<head>
  <meta charset="UTF-8">
  <meta name="google" content="notranslate">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>MERIDIAN — Pelacakan Kargo Ekspor &amp; Rekening Jaminan Nusantara</title>
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 28 28' fill='none'%3E%3Ccircle cx='14' cy='14' r='11.25' stroke='%230f172a' stroke-width='2'/%3E%3Cellipse cx='14' cy='14' rx='5.2' ry='11.25' stroke='%230f172a' stroke-width='1.5' opacity='0.6'/%3E%3Cpath d='M4.2 17.5C8.4 13.8 14.8 11.4 23.8 10.5' stroke='%230284c7' stroke-width='2.2' stroke-linecap='round'/%3E%3Ccircle cx='20.4' cy='11.1' r='2.3' fill='%230284c7'/%3E%3C/svg%3E">

  <!-- Tipografi: Plus Jakarta Sans + JetBrains Mono -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- MapLibre GL JS v5 (Peta Rute 3D & Animasi Multimoda) -->
  <link rel="stylesheet" href="https://unpkg.com/maplibre-gl@5.2.0/dist/maplibre-gl.css" crossorigin="">

  <!-- Meridian Clean Daylight Enterprise Design System -->
  <link rel="stylesheet" href="/assets/css/meridian.css?v=20261123c">
</head>
<body>
  <!-- 1. BERANDA PUBLIK & PELACAKAN RESI INSTAN -->
  @include('partials.home')

  <!-- 2. HALAMAN MASUK & PENDAFTARAN AKUN -->
  @include('partials.auth')

  <!-- 3. RUANG KERJA LAYAR PENUH (ADMIN / OPERATOR / PENGIRIM / PEMBELI / LACAK PUBLIK) -->
  @include('partials.workspace')

  <!-- MODAL PETA RUTE 3D MULTIMODA (TRUK DARAT -> KAPAL LAUT / PESAWAT UDARA) -->
  <div id="map-modal-overlay" class="modal-overlay hidden" role="dialog" aria-modal="true">
    <div class="map-modal-shell">
      <div class="map-modal-head">
        <div>
          <div class="modal-title" id="map-modal-title">Peta Rute &amp; Pergerakan Kargo 3D</div>
          <div class="modal-sub" id="map-modal-sub">Truk Armada Cabang (Darat) &middot; Kapal Mitra (Laut) &middot; Pesawat Mitra (Udara)</div>
        </div>
        <div class="map-modal-controls">
          <div class="btn-group">
            <button type="button" id="btn-layer-osm" class="btn btn-sm btn-primary" onclick="MeridianGlobe.setLayerMode('osm')">Peta Terang</button>
            <button type="button" id="btn-layer-sat" class="btn btn-sm btn-outline" onclick="MeridianGlobe.setLayerMode('satellite')">Satelit Bumi</button>
          </div>
          <button type="button" class="btn btn-sm btn-outline" onclick="MeridianGlobe.resetView()">Reset Bumi</button>
          <button type="button" class="btn btn-sm btn-danger-soft" onclick="MeridianApp.closeMapModal()">Tutup Peta</button>
        </div>
      </div>

      <div class="map-modal-body">
        <div id="workspace-globe-map" class="map-canvas-full"></div>
        <div id="map-modal-hud" class="map-modal-hud"></div>
      </div>
    </div>
  </div>

  <!-- MODAL DIALOG UMUM (BANTUAN ADMIN / PROFIL IDENTITAS / SURAT RILIS DIGITAL / FORM CEPAT) -->
  <div id="app-modal-overlay" class="modal-overlay hidden" role="dialog" aria-modal="true" onclick="if(event.target===this) MeridianApp.closeModal()">
    <div class="dialog-card" id="app-modal-card">
      <div class="dialog-head">
        <div>
          <div class="modal-title" id="app-modal-title">Detail</div>
          <div class="modal-sub" id="app-modal-sub"></div>
        </div>
        <button type="button" class="btn btn-sm btn-outline" onclick="MeridianApp.closeModal()">Tutup</button>
      </div>
      <div class="dialog-body" id="app-modal-body"></div>
    </div>
  </div>

  <!-- NOTIFIKASI TOAST -->
  <div class="toast-container" id="toast-container"></div>

  <script>
    window.__MERIDIAN_INIT__ = {
      page: @json($initialPage ?? 'home'),
      trackingNumber: @json($trackingNumber ?? null)
    };
  </script>

  <!-- MapLibre GL JS v5 -->
  <script src="https://unpkg.com/maplibre-gl@5.2.0/dist/maplibre-gl.js" crossorigin=""></script>

  <!-- Meridian Multimodal Map Engine & Full-Screen App -->
  <script src="/assets/js/globe-engine.js?v=20261125a"></script>
  <script src="/assets/js/meridian-app.js?v=20261125a"></script>
</body>
</html>
