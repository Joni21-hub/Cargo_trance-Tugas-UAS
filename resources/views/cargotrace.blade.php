<!DOCTYPE html>
<html lang="id" class="notranslate" translate="no">
<head>
  <meta charset="UTF-8">
  <meta name="google" content="notranslate">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>MERIDIAN — Visibilitas Pengiriman Global</title>
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 28 28' fill='none'%3E%3Ccircle cx='14' cy='14' r='11.25' stroke='%230b1f3a' stroke-width='2'/%3E%3Cellipse cx='14' cy='14' rx='5.2' ry='11.25' stroke='%230b1f3a' stroke-width='1.5' opacity='0.7'/%3E%3Cpath d='M4.2 17.5C8.4 13.8 14.8 11.4 23.8 10.5' stroke='%230264d4' stroke-width='2.2' stroke-linecap='round'/%3E%3Ccircle cx='20.4' cy='11.1' r='2.3' fill='%2338bdf8'/%3E%3C/svg%3E">

  <!-- Tipografi: Plus Jakarta Sans + JetBrains Mono -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- MapLibre GL JS v5 (Native Unified 3D Globe -> Seamless Detail Zoom) -->
  <link rel="stylesheet" href="https://unpkg.com/maplibre-gl@5.2.0/dist/maplibre-gl.css" crossorigin="">

  <!-- Meridian Cosmic Glassmorphism Design System -->
  <link rel="stylesheet" href="/assets/css/meridian.css?v=20261122b">
</head>
<body>
  <!-- LATAR ALAM SEMESTA BERBINTANG -->
  <canvas id="cosmic-starfield" aria-hidden="true"></canvas>

  <!-- 1. BERANDA PUBLIK & PELACAKAN INSTAN -->
  @include('partials.home')

  <!-- 2. HALAMAN MASUK & REGISTRASI -->
  @include('partials.auth')

  <!-- 3. RUANG KERJA COSMIC GLOBE 3D (4 DASHBOARD BERBEDA: ADMIN / OPERATOR / SHIPPER / BUYER / PUBLIC TRACK) -->
  @include('partials.workspace')

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

  <!-- Meridian Engine & Application Modules -->
  <script src="/assets/js/globe-engine.js?v=20261122b"></script>
  <script src="/assets/js/meridian-app.js?v=20261122b"></script>
</body>
</html>
