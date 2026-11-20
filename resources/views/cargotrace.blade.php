<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CargoTrace - Platform Escrow Ekspor-Impor & Freight Forwarding Maritim</title>
  
  <!-- Leaflet CSS for Maritime Geospatial Map -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
  <!-- Google Fonts: Plus Jakarta Sans & JetBrains Mono -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    /* ==========================================================================
       DESIGN SYSTEM - NO ICONS, NO LOGOS, PURE TYPOGRAPHY & CLEAN DATA-DENSE UI
       ========================================================================== */
    :root {
      --bg-body: #f1f5f9;
      --bg-surface: #ffffff;
      --bg-subtle: #f8fafc;
      --border-color: #cbd5e1;
      --border-strong: #94a3b8;
      
      --text-main: #0f172a;
      --text-muted: #475569;
      --text-subtle: #64748b;
      
      --brand-navy: #0f2d59;
      --brand-blue: #1d4ed8;
      --brand-blue-hover: #1e40af;
      --brand-light: #eff6ff;
      
      --accent-cyan: #0284c7;
      --accent-slate: #334155;
      
      --badge-green-bg: #ecfdf5;
      --badge-green-text: #065f46;
      --badge-green-border: #a7f3d0;
      
      --badge-amber-bg: #fffbeb;
      --badge-amber-text: #92400e;
      --badge-amber-border: #fde68a;
      
      --badge-red-bg: #fef2f2;
      --badge-red-text: #991b1b;
      --badge-red-border: #fecaca;
      
      --badge-blue-bg: #eff6ff;
      --badge-blue-text: #1e40af;
      --badge-blue-border: #bfdbfe;

      --badge-purple-bg: #faf5ff;
      --badge-purple-text: #6b21a8;
      --badge-purple-border: #e9d5ff;

      --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
      --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
      --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -4px rgba(0, 0, 0, 0.05);

      --font-sans: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
      --font-mono: 'JetBrains Mono', monospace;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: var(--font-sans);
      background-color: var(--bg-body);
      color: var(--text-main);
      line-height: 1.5;
      font-size: 14px;
      -webkit-font-smoothing: antialiased;
    }

    /* TYPOGRAPHY */
    h1, h2, h3, h4, h5, h6 {
      color: var(--brand-navy);
      font-weight: 700;
      line-height: 1.25;
    }

    code, .font-mono {
      font-family: var(--font-mono);
    }

    /* BADGES (STRICTLY TEXT ONLY) */
    .badge {
      display: inline-flex;
      align-items: center;
      padding: 3px 8px;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      border-radius: 4px;
      border: 1px solid transparent;
      white-space: nowrap;
      font-family: var(--font-mono);
    }
    .badge-blue { background: var(--badge-blue-bg); color: var(--badge-blue-text); border-color: var(--badge-blue-border); }
    .badge-green { background: var(--badge-green-bg); color: var(--badge-green-text); border-color: var(--badge-green-border); }
    .badge-amber { background: var(--badge-amber-amber, #fffbeb); color: var(--badge-amber-text); border-color: var(--badge-amber-border); }
    .badge-red { background: var(--badge-red-bg); color: var(--badge-red-text); border-color: var(--badge-red-border); }
    .badge-purple { background: var(--badge-purple-bg); color: var(--badge-purple-text); border-color: var(--badge-purple-border); }
    .badge-navy { background: #0f2d59; color: #ffffff; border-color: #0f2d59; }
    .badge-dark { background: #334155; color: #ffffff; border-color: #334155; }
    .badge-gray { background: #e2e8f0; color: #334155; border-color: #cbd5e1; }

    /* BUTTONS (STRICTLY TEXT ONLY) */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 8px 14px;
      font-size: 13px;
      font-weight: 600;
      border-radius: 6px;
      border: 1px solid transparent;
      cursor: pointer;
      transition: all 0.15s ease;
      text-decoration: none;
      font-family: var(--font-sans);
    }
    .btn-sm { padding: 4px 10px; font-size: 12px; }
    .btn-lg { padding: 12px 20px; font-size: 15px; }
    
    .btn-primary { background: var(--brand-blue); color: #ffffff; border-color: var(--brand-blue); }
    .btn-primary:hover { background: var(--brand-blue-hover); border-color: var(--brand-blue-hover); }
    
    .btn-navy { background: var(--brand-navy); color: #ffffff; border-color: var(--brand-navy); }
    .btn-navy:hover { background: #173b70; border-color: #173b70; }
    
    .btn-success { background: #059669; color: #ffffff; border-color: #059669; }
    .btn-success:hover { background: #047857; border-color: #047857; }
    
    .btn-warning { background: #d97706; color: #ffffff; border-color: #d97706; }
    .btn-warning:hover { background: #b45309; border-color: #b45309; }

    .btn-danger { background: #dc2626; color: #ffffff; border-color: #dc2626; }
    .btn-danger:hover { background: #b91c1c; border-color: #b91c1c; }

    .btn-outline { background: #ffffff; color: var(--text-main); border-color: var(--border-color); }
    .btn-outline:hover { background: var(--bg-subtle); border-color: var(--border-strong); }

    .btn-outline-primary { background: #ffffff; color: var(--brand-blue); border-color: var(--brand-blue); }
    .btn-outline-primary:hover { background: var(--brand-light); }

    /* CARD CONTAINER */
    .card {
      background: var(--bg-surface);
      border: 1px solid var(--border-color);
      border-radius: 8px;
      box-shadow: var(--shadow-sm);
      overflow: hidden;
    }
    .card-header {
      padding: 14px 18px;
      border-bottom: 1px solid var(--border-color);
      background: var(--bg-subtle);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
    }
    .card-body {
      padding: 18px;
    }

    /* FORM STYLES */
    .form-group {
      margin-bottom: 14px;
    }
    .form-label {
      display: block;
      font-size: 12px;
      font-weight: 700;
      color: var(--text-main);
      margin-bottom: 4px;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }
    .form-control, .form-select {
      width: 100%;
      padding: 8px 12px;
      font-size: 13px;
      border: 1px solid var(--border-color);
      border-radius: 6px;
      background: #ffffff;
      color: var(--text-main);
      font-family: inherit;
    }
    .form-control:focus, .form-select:focus {
      outline: none;
      border-color: var(--brand-blue);
      box-shadow: 0 0 0 2px rgba(29, 78, 216, 0.15);
    }

    /* TOP HEADER */
    .top-header {
      background: var(--brand-navy);
      color: #ffffff;
      border-bottom: 3px solid var(--brand-blue);
      padding: 10px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 12px;
    }
    .brand-box {
      display: flex;
      align-items: baseline;
      gap: 10px;
    }
    .brand-title {
      font-size: 20px;
      font-weight: 800;
      color: #ffffff;
      letter-spacing: 0.05em;
    }
    .brand-subtitle {
      font-size: 11px;
      color: #94a3b8;
      font-weight: 500;
      letter-spacing: 0.02em;
    }
    .header-user-info {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }

    /* ROLE SWITCHER BAR */
    .role-switcher-bar {
      background: #ffffff;
      border-bottom: 1px solid var(--border-color);
      padding: 8px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 10px;
      font-size: 12px;
    }
    .role-pills {
      display: flex;
      align-items: center;
      gap: 6px;
      flex-wrap: wrap;
    }
    .role-pill-btn {
      padding: 5px 12px;
      font-size: 12px;
      font-weight: 700;
      border-radius: 4px;
      cursor: pointer;
      border: 1px solid var(--border-color);
      background: #ffffff;
      color: var(--text-muted);
      transition: all 0.15s ease;
      font-family: var(--font-mono);
    }
    .role-pill-btn.active {
      background: var(--brand-navy);
      color: #ffffff;
      border-color: var(--brand-navy);
    }
    .role-pill-btn:hover:not(.active) {
      background: var(--bg-body);
      border-color: var(--border-strong);
    }

    /* MAIN CONTAINER */
    .main-container {
      max-width: 1400px;
      margin: 20px auto;
      padding: 0 20px;
    }

    /* GRID SYSTEM */
    .grid-2 {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 20px;
    }
    .grid-3 {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 16px;
    }
    .grid-4 {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 16px;
    }
    .grid-layout-sidebar {
      display: grid;
      grid-template-columns: 360px 1fr;
      gap: 20px;
    }
    .grid-layout-main {
      display: grid;
      grid-template-columns: 1fr 400px;
      gap: 20px;
    }

    @media (max-width: 1024px) {
      .grid-4 { grid-template-columns: repeat(2, 1fr); }
      .grid-3 { grid-template-columns: 1fr; }
      .grid-2 { grid-template-columns: 1fr; }
      .grid-layout-sidebar { grid-template-columns: 1fr; }
      .grid-layout-main { grid-template-columns: 1fr; }
    }

    /* STAT CARD */
    .stat-card {
      background: #ffffff;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      padding: 16px;
      border-left: 4px solid var(--brand-navy);
    }
    .stat-label {
      font-size: 11px;
      font-weight: 700;
      color: var(--text-subtle);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 4px;
    }
    .stat-value {
      font-size: 24px;
      font-weight: 800;
      color: var(--brand-navy);
      font-family: var(--font-mono);
      line-height: 1.1;
    }
    .stat-desc {
      font-size: 12px;
      color: var(--text-muted);
      margin-top: 6px;
    }

    /* TABLES */
    .table-responsive {
      overflow-x: auto;
    }
    .data-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
      text-align: left;
    }
    .data-table th {
      background: var(--bg-subtle);
      padding: 10px 14px;
      font-size: 11px;
      font-weight: 700;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.04em;
      border-bottom: 1px solid var(--border-color);
    }
    .data-table td {
      padding: 12px 14px;
      border-bottom: 1px solid var(--border-color);
      vertical-align: middle;
    }
    .data-table tr:hover {
      background-color: var(--bg-subtle);
    }

    /* TABS */
    .nav-tabs {
      display: flex;
      border-bottom: 2px solid var(--border-color);
      margin-bottom: 18px;
      gap: 4px;
    }
    .nav-tab-btn {
      padding: 9px 18px;
      font-size: 13px;
      font-weight: 700;
      color: var(--text-muted);
      background: transparent;
      border: none;
      border-bottom: 2px solid transparent;
      margin-bottom: -2px;
      cursor: pointer;
      font-family: var(--font-sans);
    }
    .nav-tab-btn.active {
      color: var(--brand-blue);
      border-bottom-color: var(--brand-blue);
      background: #ffffff;
    }

    /* MAP CONTAINER */
    #leaflet-map {
      height: 440px;
      width: 100%;
      border-radius: 6px;
      border: 1px solid var(--border-color);
      background: #e2e8f0;
      z-index: 10;
    }

    /* CHAT BOX */
    .chat-box {
      display: flex;
      flex-direction: column;
      height: 480px;
      border: 1px solid var(--border-color);
      border-radius: 6px;
      background: #ffffff;
      overflow: hidden;
    }
    .chat-header {
      background: var(--bg-subtle);
      padding: 10px 14px;
      border-bottom: 1px solid var(--border-color);
      font-weight: 700;
      font-size: 12px;
      color: var(--brand-navy);
      display: flex;
      justify-content: space-between;
    }
    .chat-messages {
      flex: 1;
      padding: 14px;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 10px;
      background: #f8fafc;
    }
    .chat-msg {
      max-width: 85%;
      padding: 8px 12px;
      border-radius: 6px;
      font-size: 13px;
      line-height: 1.4;
      border: 1px solid var(--border-color);
    }
    .chat-msg-sender {
      font-size: 11px;
      font-weight: 700;
      margin-bottom: 3px;
      font-family: var(--font-mono);
    }
    .chat-msg-time {
      font-size: 10px;
      color: var(--text-subtle);
      margin-top: 4px;
      text-align: right;
    }
    .chat-msg-shipper {
      align-self: flex-start;
      background: #ffffff;
      border-color: #cbd5e1;
    }
    .chat-msg-buyer {
      align-self: flex-end;
      background: #eff6ff;
      border-color: #bfdbfe;
    }
    .chat-msg-operator {
      align-self: center;
      background: #fffbeb;
      border-color: #fde68a;
      max-width: 95%;
    }
    .chat-footer {
      padding: 10px 14px;
      border-top: 1px solid var(--border-color);
      background: #ffffff;
      display: flex;
      gap: 8px;
    }

    /* STEPPER TIMELINE */
    .timeline-steps {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 14px 0;
      position: relative;
    }
    .timeline-steps::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 30px;
      right: 30px;
      height: 2px;
      background: var(--border-color);
      z-index: 1;
      transform: translateY(-50%);
    }
    .timeline-step {
      position: relative;
      z-index: 2;
      background: #ffffff;
      padding: 4px 10px;
      border: 2px solid var(--border-color);
      border-radius: 4px;
      font-size: 11px;
      font-weight: 700;
      color: var(--text-muted);
      font-family: var(--font-mono);
    }
    .timeline-step.completed {
      border-color: #059669;
      background: #ecfdf5;
      color: #065f46;
    }
    .timeline-step.active {
      border-color: var(--brand-blue);
      background: var(--brand-blue);
      color: #ffffff;
    }

    /* MODAL DIALOG */
    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.6);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      padding: 20px;
    }
    .modal-overlay.active {
      display: flex;
    }
    .modal-card {
      background: #ffffff;
      border-radius: 8px;
      max-width: 650px;
      width: 100%;
      max-height: 90vh;
      overflow-y: auto;
      border: 1px solid var(--border-color);
      box-shadow: var(--shadow-lg);
    }
    .modal-header {
      padding: 16px 20px;
      border-bottom: 1px solid var(--border-color);
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: var(--bg-subtle);
    }
    .modal-body {
      padding: 20px;
    }
    .modal-footer {
      padding: 14px 20px;
      border-top: 1px solid var(--border-color);
      background: var(--bg-subtle);
      display: flex;
      justify-content: flex-end;
      gap: 10px;
    }

    /* NOTIFICATION TOAST */
    #toast-notification {
      position: fixed;
      bottom: 24px;
      right: 24px;
      padding: 12px 18px;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 600;
      box-shadow: var(--shadow-lg);
      z-index: 2000;
      display: none;
      font-family: var(--font-sans);
      border: 1px solid transparent;
      max-width: 400px;
    }

    /* AUTHENTICATION PORTALS SCREEN */
    #auth-screen {
      max-width: 1000px;
      margin: 40px auto;
      padding: 0 20px;
    }
    .auth-card {
      background: #ffffff;
      border: 1px solid var(--border-color);
      border-radius: 10px;
      box-shadow: var(--shadow-md);
      overflow: hidden;
    }
    .auth-banner {
      background: var(--brand-navy);
      color: #ffffff;
      padding: 24px 30px;
      border-bottom: 3px solid var(--brand-blue);
    }
    .auth-tabs {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      border-bottom: 1px solid var(--border-color);
      background: var(--bg-subtle);
    }
    @media (max-width: 900px) {
      .auth-tabs {
        grid-template-columns: repeat(2, 1fr);
      }
    }
    .auth-tab-btn {
      padding: 14px 10px;
      font-size: 13px;
      font-weight: 700;
      border: none;
      background: transparent;
      cursor: pointer;
      color: var(--text-muted);
      border-bottom: 3px solid transparent;
      text-align: center;
      font-family: var(--font-mono);
      text-transform: uppercase;
    }
    .auth-tab-btn.active {
      background: #ffffff;
      color: var(--brand-blue);
      border-bottom-color: var(--brand-blue);
    }
    .auth-body {
      padding: 30px;
    }
    .demo-accounts-panel {
      margin-top: 24px;
      padding: 16px;
      background: var(--bg-subtle);
      border: 1px solid var(--border-color);
      border-radius: 6px;
    }
    .demo-account-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 8px 12px;
      background: #ffffff;
      border: 1px solid var(--border-color);
      border-radius: 4px;
      margin-top: 8px;
    }
  </style>
</head>
<body>

  <!-- ========================================================================
       TOP HEADER (NAVIGATION & IDENTITY)
       ======================================================================== -->
  <header class="top-header">
    <div class="brand-box">
      <span class="brand-title">CARGOTRACE</span>
      <span class="brand-subtitle">Platform Escrow Ekspor-Impor & Freight Forwarding Maritim</span>
    </div>

    <div class="header-user-info" id="header-user-info" style="display: none;">
      <span class="badge badge-navy" id="header-active-role-badge">PERAN: SHIPPER</span>
      <span style="font-size: 12px; color: #cbd5e1;" id="header-user-email">shipper@cargotrace.com</span>
      <button class="btn btn-sm btn-outline" style="color: #ffffff; border-color: #475569;" onclick="handleLogout()">LOGOUT / KELUAR</button>
    </div>
  </header>

  <!-- ========================================================================
       ROLE SWITCHER BAR (FOR EVALUATION & INSTANT MULTI-ROLE TESTING)
       ======================================================================== -->
  <div class="role-switcher-bar" id="role-switcher-bar" style="display: none;">
    <div style="display: flex; align-items: center; gap: 8px;">
      <span style="font-weight: 700; color: var(--text-muted); font-size: 11px; text-transform: uppercase;">Sudut Pandang Peran:</span>
      <div class="role-pills">
        <button class="role-pill-btn" id="pill-operator" onclick="switchRole('operator')">[ 1. PORTAL OPERATOR (INPUT BARANG) ]</button>
        <button class="role-pill-btn" id="pill-shipper" onclick="switchRole('shipper')">[ 2. PORTAL SHIPPER (KONFIRMASI BARANG) ]</button>
        <button class="role-pill-btn" id="pill-buyer" onclick="switchRole('buyer')">[ 3. PORTAL BUYER (SETUJUI PENERIMAAN) ]</button>
      </div>
    </div>

    <div style="display: flex; align-items: center; gap: 12px;">
      <span class="badge badge-green" id="system-status-badge">SISTEM AKTIF: ESCROW & TRACKING ON</span>
      <span style="color: var(--text-subtle); font-size: 11px; font-family: var(--font-mono);" id="live-clock">2026-11-12 08:00 WIB</span>
    </div>
  </div>

  <!-- ========================================================================
       SCREEN 1: AUTHENTICATION / 3 SEPARATE LOGIN PORTALS
       ======================================================================== -->
  <div id="auth-screen">
    <div class="auth-card">
      <div class="auth-banner">
        <h2 style="color: #ffffff; font-size: 22px; margin-bottom: 6px;">Portal Otentikasi Terpadu CargoTrace</h2>
        <p style="font-size: 13px; color: #94a3b8; line-height: 1.4;">
          Platform perdagangan dan logistik maritim dengan jaminan Rekening Bersama (Escrow) pihak ketiga terpercaya dan pelacakan kargo laut terverifikasi.
        </p>
      </div>

      <!-- 3 PORTAL TABS -->
      <div class="auth-tabs">
        <button class="auth-tab-btn active" id="auth-tab-operator" onclick="selectAuthTab('operator')">
          [ 1. OPERATOR (INPUT DETAIL BARANG) ]
        </button>
        <button class="auth-tab-btn" id="auth-tab-shipper" onclick="selectAuthTab('shipper')">
          [ 2. SHIPPER (KONFIRMASI BARANG) ]
        </button>
        <button class="auth-tab-btn" id="auth-tab-buyer" onclick="selectAuthTab('buyer')">
          [ 3. BUYER (SETUJUI PENERIMAAN) ]
        </button>
      </div>

      <div class="auth-body">
        <!-- ROLE CONTEXT NOTICE -->
        <div style="padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; border: 1px solid var(--border-color);" id="auth-context-box">
          <span class="badge badge-navy" id="auth-context-badge">PORTAL AKSES: SHIPPER</span>
          <p style="font-size: 13px; color: var(--text-muted); margin-top: 6px;" id="auth-context-desc">
            Pintu masuk eksportir, produsen komoditas, dan UKM pengirim barang. Ajukan pengiriman kargo baru, pantau verifikasi operator, dan dapatkan kepastian pencairan dana escrow tanpa takut ditipu pembeli luar negeri.
          </p>
        </div>

        <form id="login-form" onsubmit="handleLoginSubmit(event)">
          <div class="form-group">
            <label class="form-label">Alamat Email Pengguna</label>
            <input type="email" id="login-email" class="form-control" required placeholder="nama@perusahaan.com">
          </div>

          <div class="form-group">
            <label class="form-label">Kata Sandi Akun</label>
            <input type="password" id="login-password" class="form-control" required value="password123">
          </div>

          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;">
            <label style="font-size: 12px; color: var(--text-muted); display: flex; align-items: center; gap: 6px; cursor: pointer;">
              <input type="checkbox" checked> Ingat sesi login saya
            </label>
            <span style="font-size: 12px; color: var(--brand-blue); font-weight: 600;">Sandi Default: password123</span>
          </div>

          <button type="submit" class="btn btn-navy btn-lg" style="width: 100%;" id="btn-login-submit">
            MASUK KE DASHBOARD SHIPPER
          </button>
        </form>

        <!-- QUICK ACCESS DEMO ACCOUNTS FOR EVALUATION / TESTING -->
        <div class="demo-accounts-panel">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <span style="font-size: 11px; font-weight: 700; color: var(--text-subtle); text-transform: uppercase; font-family: var(--font-mono);">
              AKSES CEPAT AKUN DEMO (1-KLIK LANGSUNG MASUK)
            </span>
            <span class="badge badge-gray">SIAP UJI</span>
          </div>

          <div class="demo-account-item">
            <div>
              <div style="font-weight: 700; font-size: 13px;">PT Gayo Agro Lestari & Nusantara Export</div>
              <div style="font-size: 11px; color: var(--text-muted); font-family: var(--font-mono);">shipper@cargotrace.com • Peran: Shipper</div>
            </div>
            <button class="btn btn-sm btn-outline-primary" onclick="quickLogin('shipper', 'shipper@cargotrace.com')">
              MASUK SHIPPER
            </button>
          </div>

          <div class="demo-account-item">
            <div>
              <div style="font-weight: 700; font-size: 13px;">Global Maritime Logistics & Escrow Officer</div>
              <div style="font-size: 11px; color: var(--text-muted); font-family: var(--font-mono);">operator@cargotrace.com • Peran: Operator / Admin</div>
            </div>
            <button class="btn btn-sm btn-navy" onclick="quickLogin('operator', 'operator@cargotrace.com')">
              MASUK OPERATOR
            </button>
          </div>

          <div class="demo-account-item">
            <div>
              <div style="font-weight: 700; font-size: 13px;">EuroGlobal Coffee & Commodity Traders B.V. (Rotterdam)</div>
              <div style="font-size: 11px; color: var(--text-muted); font-family: var(--font-mono);">buyer@cargotrace.com • Peran: Buyer (Penerima)</div>
            </div>
            <button class="btn btn-sm btn-outline-primary" onclick="quickLogin('buyer', 'buyer@cargotrace.com')">
              MASUK BUYER
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ========================================================================
       SCREEN 2: MAIN DASHBOARDS (AFTER LOGIN)
       ======================================================================== -->
  <main class="main-container" id="dashboard-screen" style="display: none;">

    <!-- ======================================================================
         VIEW A: SHIPPER DASHBOARD (PENGIRIM / EKSPORTIR)
         ====================================================================== -->
    <section id="view-shipper" style="display: none;">
      <!-- TOP SUMMARY CARDS -->
      <div class="grid-4" style="margin-bottom: 20px;">
        <div class="stat-card" style="border-left-color: var(--brand-blue);">
          <div class="stat-label">TOTAL PENGAJUAN SAYA</div>
          <div class="stat-value" id="shipper-stat-total">3 KARGO</div>
          <div class="stat-desc">2 Dalam Pelayaran, 1 Draft</div>
        </div>

        <div class="stat-card" style="border-left-color: #059669;">
          <div class="stat-label">TOTAL NILAI KONTRAK</div>
          <div class="stat-value" id="shipper-stat-value">$603,800 USD</div>
          <div class="stat-desc">Seluruh Kontrak Dagang Ekspor</div>
        </div>

        <div class="stat-card" style="border-left-color: #d97706;">
          <div class="stat-label">DANA TERTAMPUNG DI ESCROW</div>
          <div class="stat-value" id="shipper-stat-escrow">$458,800 USD</div>
          <div class="stat-desc">100% Aman di Operator</div>
        </div>

        <div class="stat-card" style="border-left-color: #6366f1;">
          <div class="stat-label">DANA SUDAH DICAIRKAN</div>
          <div class="stat-value" id="shipper-stat-payout">$145,000 USD</div>
          <div class="stat-desc">Diterima di Rekening Mandiri</div>
        </div>
      </div>

      <!-- MAIN CONTENT TABS FOR SHIPPER -->
      <div class="card" style="margin-bottom: 24px;">
        <div class="card-header">
          <div style="display: flex; align-items: center; gap: 10px;">
            <span class="badge badge-navy">PORTAL SHIPPER</span>
            <span style="font-weight: 700; font-size: 15px;">Pusat Pemeriksaan & Persetujuan Konfirmasi Barang dari Operator</span>
          </div>
          <span class="badge badge-green">FOKUS: KONFIRMASI DATA BARANG</span>
        </div>

        <div class="card-body">
          <div class="nav-tabs">
            <button class="nav-tab-btn active" id="tab-btn-shipper-pending" onclick="switchShipperTab('pending')">1. BARANG MENUNGGU KONFIRMASI SAYA</button>
            <button class="nav-tab-btn" id="tab-btn-shipper-list" onclick="switchShipperTab('list')">2. RIWAYAT KARGO BERJALAN & SELESAI</button>
            <button class="nav-tab-btn" id="tab-btn-shipper-tracking" onclick="switchShipperTab('tracking')">3. PETA PELACAKAN KAPAL MARITIM</button>
          </div>

          <!-- SUB-TAB 1: BARANG MENUNGGU KONFIRMASI SHIPPER -->
          <div id="shipper-tab-pending">
            <div style="padding: 14px 18px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; margin-bottom: 16px;">
              <span class="badge badge-amber">TUGAS UTAMA SHIPPER</span>
              <p style="font-size: 13px; color: #92400e; margin-top: 6px;">
                Di bawah ini adalah daftar barang kargo yang telah diinput dan didata oleh <strong>Operator</strong>. Silakan periksa detail komoditas, jumlah, berat, dan nomor kontainer. Klik tombol <strong>SETUJUI & KONFIRMASI BARANG</strong> untuk mengesahkan bahwa barang siap diberangkatkan ke Buyer.
              </p>
            </div>

            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>NOMOR RESI & KONTAINER</th>
                    <th>DETAIL BARANG (DARI OPERATOR)</th>
                    <th>BOBOT & VOLUME</th>
                    <th>PELABUHAN TUJUAN & BUYER</th>
                    <th>STATUS ALUR</th>
                    <th>AKSI SHIPPER</th>
                  </tr>
                </thead>
                <tbody id="shipper-pending-tbody">
                  <!-- Injected via JavaScript -->
                </tbody>
              </table>
            </div>
          </div>

          <!-- SUB-TAB 2: DAFTAR KARGO AKTIF & SELESAI -->
          <div id="shipper-tab-list" style="display: none;">
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>NOMOR RESI / KONTRAK</th>
                    <th>KOMODITAS / DESKRIPSI</th>
                    <th>RUTE PELAYARAN</th>
                    <th>STATUS KARGO</th>
                    <th>STATUS PENERIMAAN BUYER</th>
                    <th>AKSI</th>
                  </tr>
                </thead>
                <tbody id="shipper-shipments-tbody">
                  <!-- Injected via JavaScript -->
                </tbody>
              </table>
            </div>
          </div>

          <!-- SUB-TAB 2: PETA LIVE TRACKING SHIPPER -->
          <div id="shipper-tab-tracking" style="display: none;">
            <div style="margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
              <div>
                <span style="font-weight: 700; font-size: 14px;">Pelacakan Satelit Rute Kapal Kargo:</span>
                <span class="badge badge-blue" id="shipper-tracking-selected-id">CTG-20261101-0001</span>
              </div>
              <div style="font-size: 12px; color: var(--text-muted);">
                Data satelit posisi kapal & kondisi cuaca maritim real-time
              </div>
            </div>
            
            <div id="leaflet-map-shipper" style="height: 420px; border-radius: 6px; border: 1px solid var(--border-color); background: #cbd5e1; margin-bottom: 16px;"></div>

            <!-- MILESTONE STEPPER -->
            <div class="card" style="padding: 16px; background: var(--bg-subtle);">
              <div style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 10px;">
                TAHAPAN PERJALANAN KARGO (MILESTONE LIFECYCLE)
              </div>
              <div class="timeline-steps" id="shipper-stepper">
                <!-- Injected via JS -->
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>


    <!-- ======================================================================
         VIEW B: OPERATOR / ADMIN DASHBOARD (PIHAK TENGAH / KEPUTUSAN & ESCROW)
         ====================================================================== -->
    <section id="view-operator" style="display: none;">
      <!-- TOP METRIC CARDS FOR OPERATOR -->
      <div class="grid-4" style="margin-bottom: 20px;">
        <div class="stat-card" style="border-left-color: var(--brand-navy);">
          <div class="stat-label">TOTAL KARGO DIAWASI</div>
          <div class="stat-value" id="op-stat-total">3 PENGIRIMAN</div>
          <div class="stat-desc">Lintas Rute Asia - Eropa</div>
        </div>

        <div class="stat-card" style="border-left-color: #059669;">
          <div class="stat-label">TOTAL DANA ESCROW DIKELOLA</div>
          <div class="stat-value" id="op-stat-escrow">$603,800 USD</div>
          <div class="stat-desc">Tertampung di Rekening Bersama</div>
        </div>

        <div class="stat-card" style="border-left-color: #d97706;">
          <div class="stat-label">PENGAJUAN MENUNGGU VERIFIKASI</div>
          <div class="stat-value" id="op-stat-pending">1 PENGAJUAN</div>
          <div class="stat-desc">Perlu Cek Kapasitas Muat Kapal</div>
        </div>

        <div class="stat-card" style="border-left-color: #dc2626;">
          <div class="stat-label">STATUS KENDALA (EXCEPTION)</div>
          <div class="stat-value" id="op-stat-exception">1 HOLD</div>
          <div class="stat-desc">Customs Inspection Singapore</div>
        </div>
      </div>

      <!-- OPERATOR WORKBENCH -->
      <div class="card" style="margin-bottom: 24px;">
        <div class="card-header">
          <div style="display: flex; align-items: center; gap: 10px;">
            <span class="badge badge-navy">PORTAL OPERATOR & ADMIN</span>
            <span style="font-weight: 700; font-size: 15px;">Pusat Keputusan Muatan Kapal, Verifikasi Resmi & Pengendalian Escrow</span>
          </div>
          <span class="badge badge-green">STATUS KEPUTUSAN: SIAP EKSEKUSI</span>
        </div>

        <div class="card-body">
          <div class="nav-tabs">
            <button class="nav-tab-btn active" id="tab-btn-op-input" onclick="switchOpTab('input')">1. FORM INPUT DETAIL BARANG (OPERATOR)</button>
            <button class="nav-tab-btn" id="tab-btn-op-list" onclick="switchOpTab('list')">2. DAFTAR SEMUA KARGO & MONITORING</button>
            <button class="nav-tab-btn" id="tab-btn-op-tracking" onclick="switchOpTab('tracking')">3. KONTROL CHECKPOINT & SATELIT</button>
            <button class="nav-tab-btn" id="tab-btn-op-audit" onclick="switchOpTab('audit')">4. AUDIT TRAIL LOG</button>
          </div>

          <!-- OPERATOR SUB-TAB 1: FORM INPUT DETAIL BARANG BARU -->
          <div id="op-tab-input">
            <div style="padding: 14px 18px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; margin-bottom: 16px;">
              <span class="badge badge-blue">TUGAS UTAMA OPERATOR</span>
              <p style="font-size: 13px; color: #1e40af; margin-top: 6px;">
                Operator bertugas memasukkan dan mencatat seluruh detail barang kargo (nama komoditas, kategori, bobot, volume, nomor kontainer ISO 6346, serta penentuan rute pelayaran). Setelah disimpan, data barang akan dikirim ke <strong>Dashboard Shipper</strong> untuk dikonfirmasi.
              </p>
            </div>

            <form id="form-operator-input-cargo" onsubmit="handleOperatorInputSubmit(event)">
              <div class="card" style="padding: 20px; background: #ffffff; border: 1px solid var(--border-color); border-radius: 6px; margin-bottom: 20px;">
                <h4 style="font-size: 14px; text-transform: uppercase; margin-bottom: 14px; color: var(--brand-navy); border-bottom: 2px solid var(--brand-light); padding-bottom: 8px;">
                  A. Identitas & Spesifikasi Barang Kargo
                </h4>

                <div class="form-group">
                  <label class="form-label">Nama Komoditas / Barang <span style="color: red;">*</span></label>
                  <input type="text" id="op-cargo-name" class="form-control" required placeholder="Contoh: Kopi Arabika Gayo Specialty Grade 1 / Rempah Kayu Manis / Tekstil Garmen" value="Kopi Arabika Gayo Specialty Grade 1">
                </div>

                <div class="grid-3">
                  <div class="form-group">
                    <label class="form-label">Kategori Komoditas</label>
                    <select id="op-cargo-category" class="form-select">
                      <option value="Kopi & Hasil Bumi">Kopi & Hasil Bumi</option>
                      <option value="Rempah-Rempah">Rempah-Rempah & Herbal</option>
                      <option value="Kakao & Cokelat">Kakao & Olahan Cokelat</option>
                      <option value="Teh Specialty">Teh Specialty</option>
                      <option value="Furnitur & Kerajinan">Furnitur & Kerajinan Kayu</option>
                      <option value="Tekstil & Garmen">Tekstil & Produk Garmen</option>
                      <option value="Komoditas Lainnya">Komoditas Lainnya</option>
                    </select>
                  </div>

                  <div class="form-group">
                    <label class="form-label">Berat Bersih (kg) <span style="color: red;">*</span></label>
                    <input type="number" step="0.01" id="op-cargo-weight" class="form-control" required placeholder="Contoh: 43200" value="43200">
                  </div>

                  <div class="form-group">
                    <label class="form-label">Estimasi Volume (CBM)</label>
                    <input type="number" step="0.01" id="op-cargo-volume" class="form-control" placeholder="Contoh: 120" value="120">
                  </div>
                </div>

                <div class="grid-3">
                  <div class="form-group">
                    <label class="form-label">Jumlah Koli / Pack</label>
                    <input type="text" id="op-cargo-qty" class="form-control" placeholder="Contoh: 720 Karung Goni GrainPro" value="720 Karung Goni">
                  </div>

                  <div class="form-group">
                    <label class="form-label">Kondisi Fisik Barang</label>
                    <select id="op-cargo-condition" class="form-select">
                      <option value="BAIK & SEGEL UTUH">BAIK & SEGEL UTUH (Lolos Syarat Ekspor)</option>
                      <option value="KEMASAN STANDAR EKSPOR">KEMASAN STANDAR EKSPOR</option>
                      <option value="SUHU TERKONTROL REEFER">SUHU TERKONTROL REEFER (18°C - 20°C)</option>
                    </select>
                  </div>

                  <div class="form-group">
                    <label class="form-label">Nomor Kontainer (ISO 6346) <span style="color: red;">*</span></label>
                    <input type="text" id="op-cargo-container" class="form-control" required placeholder="Contoh: CSQU3054383" value="CSQU3054383">
                    <small style="color: var(--text-muted); font-size: 11px;">Format: 4 Huruf + 7 Angka (Checksum ISO 6346)</small>
                  </div>
                </div>

                <h4 style="font-size: 14px; text-transform: uppercase; margin-top: 20px; margin-bottom: 14px; color: var(--brand-navy); border-bottom: 2px solid var(--brand-light); padding-bottom: 8px;">
                  B. Penentuan Pihak Transaksi & Rute Pelayaran
                </h4>

                <div class="grid-2">
                  <div class="form-group">
                    <label class="form-label">Pihak Pengirim (Shipper / Eksportir) <span style="color: red;">*</span></label>
                    <select id="op-cargo-shipper" class="form-select">
                      <option value="PT Samudera Kopi Nusantara">PT Samudera Kopi Nusantara (customer@cargotrace.com)</option>
                      <option value="Koperasi Baburrayyan Takengon">Koperasi Baburrayyan Takengon (koperasi@cargotrace.com)</option>
                    </select>
                  </div>

                  <div class="form-group">
                    <label class="form-label">Pihak Penerima (Buyer / Consignee) <span style="color: red;">*</span></label>
                    <select id="op-cargo-buyer" class="form-select">
                      <option value="EuroGlobal Coffee & Commodity Traders B.V.">EuroGlobal Coffee & Commodity Traders B.V. (Rotterdam)</option>
                      <option value="Hamburg Specialty Trade GmbH">Hamburg Specialty Trade GmbH (Hamburg)</option>
                    </select>
                  </div>
                </div>

                <div class="grid-3">
                  <div class="form-group">
                    <label class="form-label">Maskapai Pelayaran (Carrier)</label>
                    <select id="op-cargo-carrier" class="form-select">
                      <option value="Maersk Line (Reefer Cold Chain)">Maersk Line (MAEU)</option>
                      <option value="MSC Mediterranean Shipping">MSC Mediterranean Shipping (MSCU)</option>
                      <option value="Evergreen Marine Corp">Evergreen Marine Corp (EGLV)</option>
                      <option value="Ocean Network Express (ONE)">Ocean Network Express (ONEU)</option>
                      <option value="CMA CGM Group">CMA CGM Group (CMDU)</option>
                    </select>
                  </div>

                  <div class="form-group">
                    <label class="form-label">Pelabuhan Muat (Origin)</label>
                    <select id="op-cargo-origin" class="form-select">
                      <option value="IDTPR">Port of Tanjung Priok (Jakarta - IDTPR)</option>
                      <option value="IDBLW">Port of Belawan (Medan - IDBLW)</option>
                      <option value="SGSIN">Port of Singapore (SGSIN)</option>
                    </select>
                  </div>

                  <div class="form-group">
                    <label class="form-label">Pelabuhan Bongkar (Destination)</label>
                    <select id="op-cargo-dest" class="form-select">
                      <option value="NLRTM">Port of Rotterdam (Belanda - NLRTM)</option>
                      <option value="DEHAM">Port of Hamburg (Jerman - DEHAM)</option>
                      <option value="JPTYO">Port of Tokyo (Jepang - JPTYO)</option>
                      <option value="CNSHA">Port of Shanghai (China - CNSHA)</option>
                    </select>
                  </div>
                </div>

                <div class="grid-2">
                  <div class="form-group">
                    <label class="form-label">Tanggal Rencana Keberangkatan (ETD)</label>
                    <input type="date" id="op-cargo-departure" class="form-control" required value="2026-11-25">
                  </div>

                  <div class="form-group">
                    <label class="form-label">Tanggal Estimasi Tiba (ETA)</label>
                    <input type="date" id="op-cargo-arrival" class="form-control" required value="2026-12-15">
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">Catatan Pemeriksaan & Dokumen Pendukung</label>
                  <textarea id="op-cargo-notes" class="form-control" rows="2" placeholder="Catatan inspeksi fisik kargo, nomor sertifikat phytosanitary, atau instruksi handling dermaga..."></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px;">
                  <button type="submit" class="btn btn-navy btn-lg" style="padding: 12px 28px; font-weight: 700;">
                    + SIMPAN DETAIL BARANG & KIRIM KE SHIPPER UNTUK KONFIRMASI
                  </button>
                </div>
              </div>
            </form>
          </div>

          <!-- OPERATOR SUB-TAB 2: DAFTAR SEMUA KARGO & MONITORING -->
          <div id="op-tab-list" style="display: none;">
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>NOMOR RESI & KONTAINER</th>
                    <th>DETAIL BARANG & BOBOT</th>
                    <th>PENGIRIM (SHIPPER)</th>
                    <th>PENERIMA (BUYER)</th>
                    <th>RUTE PELAYARAN</th>
                    <th>STATUS ALUR TAHAPAN</th>
                    <th>AKSI</th>
                  </tr>
                </thead>
                <tbody id="op-verify-tbody">
                  <!-- Injected via JS -->
                </tbody>
              </table>
            </div>
          </div>

          <!-- OPERATOR SUB-TAB 3: KONTROL TRACKING & CHECKPOINTS -->
          <div id="op-tab-tracking" style="display: none;">
            <div class="grid-2" style="margin-bottom: 16px;">
              <div>
                <div class="card" style="padding: 16px;">
                  <div style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 10px;">
                    INPUT CHECKPOINT MARITIM BARU
                  </div>
                  <form id="form-add-checkpoint" onsubmit="handleAddCheckpoint(event)">
                    <div class="form-group">
                      <label class="form-label">Pilih Kargo</label>
                      <select id="op-checkpoint-shipment" class="form-select">
                        <option value="CTG-20261101-0001">CTG-20261101-0001 (Jakarta -> Rotterdam)</option>
                        <option value="CTG-20261102-0002">CTG-20261102-0002 (Singapore -> Hamburg)</option>
                      </select>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Pelabuhan / Checkpoint</label>
                      <select id="op-checkpoint-location" class="form-select">
                        <option value="IDTPR">Port of Tanjung Priok (Jakarta)</option>
                        <option value="SGSIN">Port of Singapore</option>
                        <option value="NLRTM">Port of Rotterdam</option>
                        <option value="DEHAM">Port of Hamburg</option>
                        <option value="CNSHA">Port of Shanghai</option>
                      </select>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Tipe Peristiwa (Event)</label>
                      <select id="op-checkpoint-type" class="form-select">
                        <option value="DEPARTURE">DEPARTURE (Kapal Berangkat)</option>
                        <option value="IN_TRANSIT_CHECKPOINT">IN_TRANSIT_CHECKPOINT (Transit Selat Malaka / Terusan Suez)</option>
                        <option value="CUSTOMS_INSPECTION">CUSTOMS_INSPECTION (Pemeriksaan Bea Cukai)</option>
                        <option value="ARRIVAL">ARRIVAL (Tiba di Pelabuhan Tujuan)</option>
                        <option value="DELIVERED">DELIVERED (Selesai Serah Terima)</option>
                      </select>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Keterangan Log Petugas</label>
                      <input type="text" id="op-checkpoint-remarks" class="form-control" required placeholder="Contoh: Genset reefer dicek, suhu 19C stabil...">
                    </div>
                    <button type="submit" class="btn btn-navy" style="width: 100%;">
                      CATAT CHECKPOINT KE SISTEM
                    </button>
                  </form>
                </div>
              </div>

              <div>
                <div class="card" style="padding: 16px;">
                  <div style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 10px;">
                    MANAJEMEN KENDALA & KETERLAMBATAN (EXCEPTION)
                  </div>
                  <form id="form-add-exception" onsubmit="handleAddException(event)">
                    <div class="form-group">
                      <label class="form-label">Pilih Kargo Terkena Kendala</label>
                      <select id="op-exception-shipment" class="form-select">
                        <option value="CTG-20261102-0002">CTG-20261102-0002 (Kopi Wine Process - Singapore)</option>
                        <option value="CTG-20261101-0001">CTG-20261101-0001 (Kopi Gayo Grade 1)</option>
                      </select>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Jenis Kendala</label>
                      <select id="op-exception-type" class="form-select">
                        <option value="CUSTOMS_HOLD">CUSTOMS_HOLD (Penahanan Bea Cukai / Karantina)</option>
                        <option value="WEATHER_DELAY">WEATHER_DELAY (Cuaca Buruk / Badai Laut)</option>
                        <option value="PORT_CONGESTION">PORT_CONGESTION (Antrean Dermaga Pelabuhan)</option>
                        <option value="VESSEL_BREAKDOWN">VESSEL_BREAKDOWN (Gangguan Mesin Kapal)</option>
                      </select>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Tingkat Keparahan & Estimasi Tambahan Jam</label>
                      <div style="display: flex; gap: 10px;">
                        <select id="op-exception-severity" class="form-select" style="flex: 1;">
                          <option value="MEDIUM">MEDIUM</option>
                          <option value="HIGH">HIGH</option>
                          <option value="CRITICAL">CRITICAL</option>
                        </select>
                        <input type="number" id="op-exception-hours" class="form-control" style="width: 120px;" value="24" placeholder="Jam (+24)">
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Catatan Penyebab</label>
                      <input type="text" id="op-exception-desc" class="form-control" required placeholder="Contoh: Audit karantina phytosanitary di terminal transit">
                    </div>
                    <div style="display: flex; gap: 8px;">
                      <button type="submit" class="btn btn-danger" style="flex: 1;">
                        TERBITKAN FLAG DELAY (+JAM)
                      </button>
                      <button type="button" class="btn btn-success" onclick="resolveCurrentException()">
                        RESOLVE / SELESAIKAN KENDALA
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <!-- MAP PREVIEW FOR OPERATOR -->
            <div id="leaflet-map-operator" style="height: 380px; border-radius: 6px; border: 1px solid var(--border-color); background: #cbd5e1;"></div>
          </div>

          <!-- OPERATOR SUB-TAB 4: AUDIT TRAIL LOG -->
          <div id="op-tab-audit" style="display: none;">
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>WAKTU AUDIT</th>
                    <th>AKTOR / PETUGAS</th>
                    <th>PERISTIWA / AKSI</th>
                    <th>MODUL SISTEM</th>
                    <th>DETAIL LOG PERUBAHAN</th>
                    <th>STATUS INTEGRITAS</th>
                  </tr>
                </thead>
                <tbody id="op-audit-tbody">
                  <!-- Injected via JS -->
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </section>


    <!-- ======================================================================
         VIEW C: BUYER DASHBOARD (PEMBELI / IMPORTIR INTERNASIONAL)
         ====================================================================== -->
    <section id="view-buyer" style="display: none;">
      <!-- TOP METRIC CARDS FOR BUYER -->
      <div class="grid-4" style="margin-bottom: 20px;">
        <div class="stat-card" style="border-left-color: var(--brand-blue);">
          <div class="stat-label">PESANAN AKTIF SAYA</div>
          <div class="stat-value" id="buyer-stat-orders">2 KARGO</div>
          <div class="stat-desc">Importasi Laut Lintas Benua</div>
        </div>

        <div class="stat-card" style="border-left-color: #059669;">
          <div class="stat-label">DANA SAYA TERLINDUNGI ESCROW</div>
          <div class="stat-value" id="buyer-stat-escrow">$388,800 USD</div>
          <div class="stat-desc">Aman di Rekening Operator</div>
        </div>

        <div class="stat-card" style="border-left-color: #6366f1;">
          <div class="stat-label">STATUS TIBA DI TUJUAN</div>
          <div class="stat-value" id="buyer-stat-arrived">1 KARGO</div>
          <div class="stat-desc">Siap Konfirmasi Penerimaan</div>
        </div>

        <div class="stat-card" style="border-left-color: #d97706;">
          <div class="stat-label">PELACAKAN KAPAL</div>
          <div class="stat-value" id="buyer-stat-eta">ETA 12 HARI</div>
          <div class="stat-desc">Rute Selat Malaka - Suez</div>
        </div>
      </div>

      <div class="card" style="margin-bottom: 24px;">
        <div class="card-header">
          <div style="display: flex; align-items: center; gap: 10px;">
            <span class="badge badge-navy">PORTAL BUYER</span>
            <span style="font-weight: 700; font-size: 15px;">Pusat Persetujuan Penerimaan Pesanan Kargo & Pelacakan Satelit</span>
          </div>
          <span class="badge badge-blue">BUYER: Rotterdam Specialty Coffee Traders B.V.</span>
        </div>

        <div class="card-body">
          <div class="nav-tabs">
            <button class="nav-tab-btn active" id="tab-btn-buyer-pending" onclick="switchBuyerTab('pending')">1. PESANAN MENUNGGU PERSETUJUAN PENERIMAAN</button>
            <button class="nav-tab-btn" id="tab-btn-buyer-orders" onclick="switchBuyerTab('orders')">2. ARSIP PESANAN SELESAI (DITERIMA)</button>
            <button class="nav-tab-btn" id="tab-btn-buyer-map" onclick="switchBuyerTab('map')">3. PETA LIVE TRACKING MARITIM</button>
          </div>

          <!-- BUYER SUB-TAB 1: PESANAN MENUNGGU PERSETUJUAN PENERIMAAN -->
          <div id="buyer-tab-pending">
            <div style="padding: 14px 18px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; margin-bottom: 16px;">
              <span class="badge badge-blue">TUGAS UTAMA BUYER</span>
              <p style="font-size: 13px; color: #1e40af; margin-top: 6px;">
                Berikut adalah daftar pesanan kargo yang dikirimkan kepada Anda. Setelah kontainer tiba di pelabuhan atau gudang Anda, silakan periksa kelengkapan fisik dan nomor segel, kemudian tekan tombol <strong>SETUJUI PENERIMAAN PESANAN</strong> untuk menyelesaikan transaksi pengiriman.
              </p>
            </div>

            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>NOMOR RESI & KONTAINER</th>
                    <th>KOMODITAS & SPESIFIKASI</th>
                    <th>EKSPORTIR (SHIPPER)</th>
                    <th>STATUS PELAYARAN</th>
                    <th>STATUS PENERIMAAN</th>
                    <th>AKSI BUYER</th>
                  </tr>
                </thead>
                <tbody id="buyer-pending-tbody">
                  <!-- Injected via JS -->
                </tbody>
              </table>
            </div>
          </div>

          <!-- BUYER SUB-TAB 2: PESANAN SELESAI DITERIMA -->
          <div id="buyer-tab-orders" style="display: none;">
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>NOMOR RESI</th>
                    <th>KOMODITAS</th>
                    <th>EKSPORTIR (SHIPPER)</th>
                    <th>STATUS PELAYARAN</th>
                    <th>STATUS PENERIMAAN BUYER</th>
                    <th>TINDAKAN</th>
                  </tr>
                </thead>
                <tbody id="buyer-orders-tbody">
                  <!-- Injected via JS -->
                </tbody>
              </table>
            </div>
          </div>

          <!-- BUYER SUB-TAB 3: PETA LIVE TRACKING BUYER -->
          <div id="buyer-tab-map" style="display: none;">
            <div style="margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between;">
              <span style="font-weight: 700; font-size: 14px;">Posisi Kapal Pengangkut Kargo Menuju Pelabuhan Rotterdam (NLRTM):</span>
              <span class="badge badge-green">STATUS SINYAL SATELIT: TERHUBUNG</span>
            </div>
            <div id="leaflet-map-buyer" style="height: 420px; border-radius: 6px; border: 1px solid var(--border-color); background: #cbd5e1; margin-bottom: 16px;"></div>
          </div>
        </div>
      </div>
    </section>

  </main>

  <!-- TOAST NOTIFICATION -->
  <div id="toast-notification"></div>

  <!-- Leaflet JS -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

  <!-- ========================================================================
       APPLICATION LOGIC, DATABASE STATE MACHINE & ESCROW ENGINE
       ======================================================================== -->
  <script>
    /* MASTER PORT LOCATIONS WITH REAL COORDINATES */
    const MASTER_LOCATIONS = {
      'IDTPR': { name: 'Port of Tanjung Priok', code: 'IDTPR', country: 'Indonesia', lat: -6.1018, lng: 106.8836 },
      'IDBLW': { name: 'Port of Belawan', code: 'IDBLW', country: 'Indonesia', lat: 3.7836, lng: 98.6881 },
      'SGSIN': { name: 'Port of Singapore', code: 'SGSIN', country: 'Singapore', lat: 1.29027, lng: 103.85196 },
      'NLRTM': { name: 'Port of Rotterdam', code: 'NLRTM', country: 'Netherlands', lat: 51.9244, lng: 4.4777 },
      'DEHAM': { name: 'Port of Hamburg', code: 'DEHAM', country: 'Germany', lat: 53.5511, lng: 9.9937 },
      'JPTYO': { name: 'Port of Tokyo', code: 'JPTYO', country: 'Japan', lat: 35.6180, lng: 139.7820 },
      'CNSHA': { name: 'Port of Shanghai', code: 'CNSHA', country: 'China', lat: 31.2304, lng: 121.4737 }
    };

    /* APPLICATION STATE (3-DASHBOARD ECOSYSTEM: OPERATOR, SHIPPER, BUYER) */
    let appState = {
      isAuthenticated: false,
      currentUser: null, // { email, role, name }
      activeRole: 'operator', // 'operator', 'shipper', 'buyer'
      selectedAuthTab: 'operator',
      selectedShipmentId: 'CTG-20261101-0001',
      
      /* GLOBAL SHIPMENTS REPOSITORY (ESCROW & FREIGHT DOMAIN) */
      shipments: [
        {
          id: 'CTG-20261101-0001',
          tracking_number: 'CTG-20261101-0001',
          shipper_name: 'PT Gayo Agro Lestari & Nusantara Export',
          buyer_name: 'Rotterdam Specialty Coffee Traders B.V.',
          carrier_name: 'Maersk Line (MV Maersk Mc-Kinney)',
          carrier_code: 'MAEU',
          commodity: 'Kopi Arabika Gayo Grade 1 (Fairtrade Organic)',
          cargo_type: '4x 40ft Reefer (Suhu 19.2 C)',
          weight_kg: 43200,
          value_usd: 388800,
          origin: MASTER_LOCATIONS['IDTPR'],
          destination: MASTER_LOCATIONS['NLRTM'],
          current_location: MASTER_LOCATIONS['SGSIN'],
          status: 'IN_TRANSIT', // 'DRAFT', 'VERIFIED', 'IN_TRANSIT', 'ARRIVED', 'COMPLETED'
          vessel_capacity_status: 'CONFIRMED_ENOUGH_CAPACITY',
          vessel_capacity_notes: 'Kapal MV Maersk terisi 82%, sisa 18% ruang muat reefer mencukupi.',
          escrow_status: 'HELD_IN_ESCROW',
          escrow_payout_status: 'DP 30% Dicairkan, 70% Menunggu Konfirmasi Buyer',
          bank_account: 'Bank Mandiri #108-00-1928-881 (PT Gayo Agro)',
          is_delayed: false,
          delay_hours: 0,
          expected_arrival: '2026-11-24',
          buyer_confirmed_arrival: false,
          
          /* FIELD OFFICER / TALLY VERIFICATION DATA */
          tally_status: 'CONFIRMED_BY_SHIPPER', // 'PENDING_INSPECTION', 'INSPECTED', 'DISCREPANCY', 'CONFIRMED_BY_SHIPPER'
          tally_data: {
            inspector: 'Ahmad Fauzi (Senior Tally Clerk IDTPR #88129)',
            inspection_loc: 'Terminal JICT 1 - Tanjung Priok',
            declared_qty: '4x 40ft Reefer (800 Karung GrainPro)',
            declared_weight_kg: 43200,
            actual_qty: '800 Karung GrainPro (Utuh & Rapi)',
            actual_weight_kg: 43180,
            seal_number: 'SEAL-MAEU-99214-ID',
            quality_grade: 'Grade 1 Specialty Fairtrade Organic (Kadar Air 11.8%)',
            packaging: 'Kemasan GrainPro Kedap Udara, Palet Fumigasi Standar ISPM 15',
            inspection_result: 'PASSED',
            inspection_time: '2026-11-06 09:30 WIB',
            notes: 'Pemeriksaan fisik di Terminal JICT 1 selesai. Bobot VGM tercatat 43,180 kg (selisih alami desikasi 0.04%). Segel resmi terpasang kuat, suhu reefer 19.2 C stabil.'
          },

          tracking_events: [
            { time: '2026-11-06 09:30', location: 'Tanjung Priok', event: 'PHYSICAL_INSPECTION', remarks: 'Pemeriksaan fisik selesai oleh Petugas Lapangan. Lembar Tally terbit.' },
            { time: '2026-11-06 14:00', location: 'Tanjung Priok', event: 'DEPARTURE', remarks: 'Pemuatan 4 unit kontainer selesai, kapal bertolak dari Terminal JICT.' },
            { time: '2026-11-10 09:30', location: 'Port of Singapore', event: 'IN_TRANSIT_CHECKPOINT', remarks: 'Transit Selat Malaka - Pengisian genset reefer, genset 440V stabil.' }
          ]
        },
        {
          id: 'CTG-20261102-0002',
          tracking_number: 'CTG-20261102-0002',
          shipper_name: 'CV Rempah Alam Banda & Nusantara',
          buyer_name: 'Hamburg Specialty Trade GmbH',
          carrier_name: 'MSC Mediterranean Shipping (MSC Zoe)',
          carrier_code: 'MSCU',
          commodity: 'Biji Pala & Kayu Manis Organik Grade A',
          cargo_type: '2x 20ft Standard Dry',
          weight_kg: 21500,
          value_usd: 215000,
          origin: MASTER_LOCATIONS['SGSIN'],
          destination: MASTER_LOCATIONS['DEHAM'],
          current_location: MASTER_LOCATIONS['SGSIN'],
          status: 'IN_TRANSIT',
          vessel_capacity_status: 'CONFIRMED_ENOUGH_CAPACITY',
          vessel_capacity_notes: 'Daya muat kapal mencukupi, sertifikat fumigasi lengkap.',
          escrow_status: 'HELD_IN_ESCROW',
          escrow_payout_status: 'Dana Tertahan Menunggu Clearance Bea Cukai',
          bank_account: 'Bank BCA #882-019-3321 (CV Rempah Alam)',
          is_delayed: true,
          delay_hours: 24,
          expected_arrival: '2026-11-21',
          buyer_confirmed_arrival: false,
          
          tally_status: 'CONFIRMED_BY_SHIPPER',
          tally_data: {
            inspector: 'Budi Santoso (Tally Officer Pelabuhan Belawan #44102)',
            inspection_loc: 'Depo Lini 1 - Belawan International Container Terminal',
            declared_qty: '2x 20ft Standard Dry (450 Karung)',
            declared_weight_kg: 21500,
            actual_qty: '450 Karung Double Goni (Lengkap)',
            actual_weight_kg: 21480,
            seal_number: 'SEAL-MSCU-77182-ID',
            quality_grade: 'Grade A Organik (Kadar Air 10.5%, Bebas Kutu)',
            packaging: 'Double Karung Goni, Sanitasi Bersih',
            inspection_result: 'PASSED',
            inspection_time: '2026-11-07 14:15 WIB',
            notes: 'Sertifikat fumigasi disertakan, segel baut baja terpasang kuat. Mengalami hold karantina lanjutan di pelabuhan transit Singapura.'
          },

          tracking_events: [
            { time: '2026-11-07 14:15', location: 'Port of Belawan', event: 'PHYSICAL_INSPECTION', remarks: 'Pemeriksaan fisik kargo rempah selesai. Tally Sheet resmi terbit.' },
            { time: '2026-11-08 10:00', location: 'Port of Singapore', event: 'CUSTOMS_INSPECTION', remarks: 'Audit karantina phytosanitary & pemeriksaan dokumen kepabeanan (+24 Jam).' }
          ]
        },
        {
          id: 'CTG-20261103-0003',
          tracking_number: 'CTG-20261103-0003',
          shipper_name: 'PT Jepara Living Furniture Export',
          buyer_name: 'Tokyo Home Living Co. Ltd.',
          carrier_name: 'Evergreen Marine (Ever Given)',
          carrier_code: 'EGLV',
          commodity: 'Furnitur Kayu Jati & Rotan Alami',
          cargo_type: '3x 40ft High Cube',
          weight_kg: 32000,
          value_usd: 145000,
          origin: MASTER_LOCATIONS['IDTPR'],
          destination: MASTER_LOCATIONS['JPTYO'],
          current_location: MASTER_LOCATIONS['JPTYO'],
          status: 'ARRIVED',
          vessel_capacity_status: 'CONFIRMED_ENOUGH_CAPACITY',
          vessel_capacity_notes: 'Kapal tiba tepat waktu, pembongkaran dermaga selesai.',
          escrow_status: 'RELEASE_READY',
          escrow_payout_status: 'Kargo Tiba, Menunggu Buyer Klik Rilis Dana',
          bank_account: 'Bank BNI #039-441-9921 (PT Jepara Living)',
          is_delayed: false,
          delay_hours: 0,
          expected_arrival: '2026-11-12',
          buyer_confirmed_arrival: false,
          
          tally_status: 'CONFIRMED_BY_SHIPPER',
          tally_data: {
            inspector: 'Hendro Wijaya (Tally Clerk Tanjung Emas #99182)',
            inspection_loc: 'Terminal Peti Kemas Semarang',
            declared_qty: '3x 40ft High Cube (120 Set Furnitur)',
            declared_weight_kg: 32000,
            actual_qty: '120 Set Furnitur (360 Box Wooden Crate)',
            actual_weight_kg: 31950,
            seal_number: 'SEAL-EGLV-44129-ID',
            quality_grade: 'Grade A Export Kiln-Dried Teakwood & Natural Rattan',
            packaging: 'Foam Protection & Peti Kayu Standar Ekspor',
            inspection_result: 'PASSED',
            inspection_time: '2026-11-01 11:00 WIB',
            notes: 'Semua sudut furnitur terlindungi foam ekspor, fisik utuh tanpa goresan atau cacat produksi.'
          },

          tracking_events: [
            { time: '2026-11-01 11:00', location: 'Tanjung Priok', event: 'PHYSICAL_INSPECTION', remarks: 'Verifikasi fisik peti kemas selesai. Lembar Tally resmi diterbitkan.' },
            { time: '2026-11-02 08:00', location: 'Tanjung Priok', event: 'DEPARTURE', remarks: 'Kapal berlayar menuju Tokyo Bay.' },
            { time: '2026-11-12 06:00', location: 'Port of Tokyo', event: 'ARRIVAL', remarks: 'Kapal sandar di Dermaga Oi Tokyo, kontainer diturunkan.' }
          ]
        },
        {
          id: 'CTG-20261104-0004',
          tracking_number: 'CTG-20261104-0004',
          shipper_name: 'PT Perkebunan Teh Nusantara & Rempah',
          buyer_name: 'Hamburg Tea & Botanical Import GmbH',
          carrier_name: 'Hapag-Lloyd / ONE (Menunggu Konfirmasi)',
          carrier_code: 'HLAG',
          commodity: 'Teh Hitam Specialty Walini & Java Spices',
          cargo_type: '1x 20ft Standard Dry',
          weight_kg: 24500,
          value_usd: 98000,
          origin: MASTER_LOCATIONS['IDTPR'],
          destination: MASTER_LOCATIONS['DEHAM'],
          current_location: MASTER_LOCATIONS['IDTPR'],
          status: 'DRAFT', // WAITING FOR FIELD TALLY INSPECTION
          vessel_capacity_status: 'ANALYSIS_PENDING',
          vessel_capacity_notes: 'Menunggu hasil pemeriksaan fisik lapangan sebelum analisa daya muat kapal.',
          escrow_status: 'PENDING_DEPOSIT',
          escrow_payout_status: 'Menunggu Verifikasi Fisik & Setoran Escrow Buyer',
          bank_account: 'Bank Mandiri #131-00-9921-772 (PT Perkebunan Teh)',
          is_delayed: false,
          delay_hours: 0,
          expected_arrival: '2026-12-10',
          buyer_confirmed_arrival: false,
          
          /* PENDING INSPECTION: READY TO TEST IN SURVEYOR PORTAL */
          tally_status: 'PENDING_INSPECTION',
          tally_data: null,

          tracking_events: [
            { time: '2026-11-16 08:00', location: 'Tanjung Priok', event: 'CREATED', remarks: 'Pengajuan kargo baru didaftarkan oleh Shipper. Menunggu kedatangan Petugas Lapangan di Dermaga.' }
          ]
        }
      ],

      /* CHAT REPOSITORY (INTER-PORTAL COMMUNICATION) */
      chats: [
        { sender: 'buyer', author: 'EuroGlobal Traders (Buyer)', text: 'Halo PT Gayo Agro, apakah dokumen sertifikat organik Fairtrade sudah dilampirkan ke Operator?', time: '07:45 WIB' },
        { sender: 'shipper', author: 'PT Gayo Agro (Shipper)', text: 'Selamat pagi! Sudah kami serahkan lengkap ke Operator kemarin sore. Nomor B/L MAEU-981292.', time: '07:48 WIB' },
        { sender: 'surveyor', author: 'Ahmad Fauzi (Petugas Tally)', text: '[LAPORAN LAPANGAN]: Pemeriksaan fisik 800 karung goni di JICT Priok telah selesai. Bobot VGM 43,180 kg dan segel SEAL-MAEU-99214-ID telah terpasang resmi.', time: '07:49 WIB' },
        { sender: 'operator', author: 'Operator Escrow', text: '[SISTEM OPERATOR]: Lembar Tally petugas lapangan diverifikasi. Dana Escrow $388,800 USD telah dikunci di rekening bersama.', time: '07:50 WIB' },
        { sender: 'buyer', author: 'EuroGlobal Traders (Buyer)', text: 'Bagus sekali! Dengan adanya verifikasi fisik independen dari petugas lapangan, kami merasa sangat aman.', time: '07:55 WIB' }
      ],

      /* AUDIT LOG REPOSITORY */
      auditLogs: [
        { time: '2026-11-16 08:00:10', actor: 'shipper@cargotrace.com', action: 'SHIPMENT_SUBMITTED', module: 'SHIPMENT_MGMT', detail: 'Shipper mendaftarkan pengajuan CTG-20261104-0004. Antrean pemeriksaan fisik lapangan dibuka.', status: 'VALID' },
        { time: '2026-11-12 07:50:11', actor: 'operator@cargotrace.com', action: 'ESCROW_FUNDS_LOCKED', module: 'ESCROW_ENGINE', detail: 'Dana sebesar $388,800 USD untuk CTG-20261101-0001 dikunci di rekening penampung bersama.', status: 'VALID' },
        { time: '2026-11-06 09:35:10', actor: 'surveyor@cargotrace.com', action: 'TALLY_SHEET_ISSUED', module: 'FIELD_INSPECTION', detail: 'Petugas Lapangan menerbitkan Berita Acara Fisik untuk CTG-20261101-0001 (VGM 43,180 kg, Segel SEAL-MAEU-99214-ID).', status: 'VALID' }
      ]
    };

    /* LEAFLET MAP INSTANCES */
    let mapInstances = {
      shipper: null,
      operator: null,
      buyer: null
    };

    /* ========================================================================
       AUTHENTICATION & 4-PORTAL SWITCHING
       ======================================================================== */
    function selectAuthTab(role) {
      appState.selectedAuthTab = role;
      document.querySelectorAll('.auth-tab-btn').forEach(btn => btn.classList.remove('active'));
      const activeTabBtn = document.getElementById('auth-tab-' + role);
      if (activeTabBtn) activeTabBtn.classList.add('active');

      const contextBadge = document.getElementById('auth-context-badge');
      const contextDesc = document.getElementById('auth-context-desc');
      const btnSubmit = document.getElementById('btn-login-submit');
      const emailInput = document.getElementById('login-email');

      if (role === 'shipper') {
        contextBadge.innerText = 'PORTAL AKSES: SHIPPER (PENGIRIM)';
        contextBadge.className = 'badge badge-navy';
        contextDesc.innerText = 'Pintu masuk eksportir, produsen komoditas, dan UKM pengirim barang. Ajukan kargo baru, pantau verifikasi fisik dari petugas lapangan, dan dapatkan kepastian pencairan dana escrow tanpa takut ditipu pembeli luar negeri.';
        btnSubmit.innerText = 'MASUK KE DASHBOARD SHIPPER';
        btnSubmit.className = 'btn btn-navy btn-lg';
        emailInput.value = 'shipper@cargotrace.com';
      } else if (role === 'operator') {
        contextBadge.innerText = 'PORTAL AKSES: OPERATOR / ADMIN (PENENGAH)';
        contextBadge.className = 'badge badge-green';
        contextDesc.innerText = 'Pintu masuk pengelola logistik maritim, pemeriksa kelayakan muatan kapal, dan pemegang rekening bersama (Escrow). Memvalidasi dokumen resmi, memantau hasil tally fisik lapangan, dan mengotorisasi pencairan dana.';
        btnSubmit.innerText = 'MASUK KE DASHBOARD OPERATOR / ADMIN';
        btnSubmit.className = 'btn btn-primary btn-lg';
        emailInput.value = 'operator@cargotrace.com';
      } else if (role === 'buyer') {
        contextBadge.innerText = 'PORTAL AKSES: BUYER (PEMBELI / IMPORTIR)';
        contextBadge.className = 'badge badge-blue';
        contextDesc.innerText = 'Pintu masuk pembeli dan importir. Periksa kargo pesanan Anda, lacak pergerakan kapal secara langsung, dan lakukan persetujuan penerimaan barang saat kargo tiba di pelabuhan tujuan.';
        btnSubmit.innerText = 'MASUK KE DASHBOARD BUYER';
        btnSubmit.className = 'btn btn-navy btn-lg';
        emailInput.value = 'buyer@cargotrace.com';
      }
    }

    function quickLogin(role, email) {
      selectAuthTab(role);
      document.getElementById('login-email').value = email;
      loginAs(role, email);
    }

    function handleLoginSubmit(e) {
      e.preventDefault();
      const email = document.getElementById('login-email').value;
      loginAs(appState.selectedAuthTab, email);
    }

    function loginAs(role, email) {
      appState.isAuthenticated = true;
      appState.activeRole = role;
      
      let userName = 'PT Gayo Agro Lestari';
      if (role === 'operator') userName = 'Logistics & Operations Officer';
      else if (role === 'buyer') userName = 'Rotterdam Coffee Traders B.V.';

      appState.currentUser = {
        email: email,
        role: role,
        name: userName
      };

      // Hide Auth Screen, Show Dashboard
      document.getElementById('auth-screen').style.display = 'none';
      document.getElementById('dashboard-screen').style.display = 'block';
      document.getElementById('header-user-info').style.display = 'flex';
      document.getElementById('role-switcher-bar').style.display = 'flex';

      // Update Header Badges
      const roleBadge = document.getElementById('header-active-role-badge');
      if (roleBadge) roleBadge.innerText = 'PERAN: ' + role.toUpperCase();
      document.getElementById('header-user-email').innerText = email;

      showToast('Berhasil masuk sebagai ' + role.toUpperCase() + ' (' + email + ')', 'success');
      switchRole(role);
    }

    function handleLogout() {
      appState.isAuthenticated = false;
      appState.currentUser = null;

      document.getElementById('auth-screen').style.display = 'block';
      document.getElementById('dashboard-screen').style.display = 'none';
      document.getElementById('header-user-info').style.display = 'none';
      document.getElementById('role-switcher-bar').style.display = 'none';

      showToast('Sesi login telah diakhiri. Kembali ke portal otentikasi.', 'info');
    }

    /* SWITCH ACTIVE VIEW ROLE */
    function switchRole(role) {
      appState.activeRole = role;

      // Update pills
      document.querySelectorAll('.role-pill-btn').forEach(btn => btn.classList.remove('active'));
      const activePill = document.getElementById('pill-' + role);
      if (activePill) activePill.classList.add('active');

      const roleBadge = document.getElementById('header-active-role-badge');
      if (roleBadge) roleBadge.innerText = 'PERAN: ' + role.toUpperCase();

      // Toggle views
      document.getElementById('view-shipper').style.display = (role === 'shipper') ? 'block' : 'none';
      document.getElementById('view-operator').style.display = (role === 'operator') ? 'block' : 'none';
      document.getElementById('view-buyer').style.display = (role === 'buyer') ? 'block' : 'none';

      // Render content
      renderAllData();

      // Trigger map resize if tab visible
      setTimeout(() => {
        if (role === 'shipper' && mapInstances.shipper) mapInstances.shipper.invalidateSize();
        if (role === 'operator' && mapInstances.operator) mapInstances.operator.invalidateSize();
        if (role === 'buyer' && mapInstances.buyer) mapInstances.buyer.invalidateSize();
      }, 250);
    }

    /* ========================================================================
       RENDER FUNCTIONS FOR 3 ROLES (OPERATOR, SHIPPER, BUYER)
       ======================================================================== */
    function renderAllData() {
      renderOperatorData();
      renderShipperData();
      renderBuyerData();
      renderAuditLogs();
    }

    /* 1. SHIPPER RENDER */
    function renderShipperData() {
      // A. Tab 1: Kargo Menunggu Konfirmasi Shipper (status === 'CREATED' || status === 'DRAFT')
      const pendingTbody = document.getElementById('shipper-pending-tbody');
      if (pendingTbody) {
        pendingTbody.innerHTML = '';
        const pendingShipments = appState.shipments.filter(s => s.status === 'CREATED' || s.status === 'DRAFT' || s.status === 'PENDING_CONFIRMATION');
        
        if (pendingShipments.length === 0) {
          pendingTbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">Tidak ada kargo yang menunggu konfirmasi saat ini. Semua data telah disetujui.</td></tr>';
        } else {
          pendingShipments.forEach(s => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
              <td>
                <strong style="color: var(--brand-navy); font-size: 13px;">${s.tracking_number}</strong>
                <div style="font-size: 11px; font-family: var(--font-mono); color: var(--brand-blue);">${s.container_number || 'CSQU-305438-3'}</div>
              </td>
              <td>
                <strong>${s.commodity}</strong>
                <div style="font-size: 11px; color: var(--text-muted);">Kategori: ${s.category || 'Hasil Bumi'} • Kondisi: ${s.condition || 'BAIK & SEGEL UTUH'}</div>
              </td>
              <td>
                <strong>${s.weight_kg ? s.weight_kg.toLocaleString() : '43,200'} kg</strong>
                <div style="font-size: 11px; color: var(--text-muted);">${s.volume_cbm || '120'} CBM</div>
              </td>
              <td>
                <div>${s.destination ? s.destination.name : 'Port of Rotterdam'}</div>
                <div style="font-size: 11px; color: var(--text-muted);">Penerima: ${s.buyer_name || 'EuroGlobal Coffee B.V.'}</div>
              </td>
              <td>
                <span class="badge badge-amber">MENUNGGU KONFIRMASI ANDA</span>
                <div style="font-size: 10px; color: var(--text-muted); margin-top: 2px;">Diinput oleh Operator</div>
              </td>
              <td>
                <button class="btn btn-sm" style="background: #059669; color: #ffffff; font-weight: 700; padding: 6px 14px;" onclick="confirmByShipper('${s.id}')">
                  ✓ SETUJUI & KONFIRMASI
                </button>
              </td>
            `;
            pendingTbody.appendChild(tr);
          });
        }
      }

      // B. Tab 2: Kargo Aktif & Selesai (status !== 'CREATED' && status !== 'DRAFT')
      const tbody = document.getElementById('shipper-shipments-tbody');
      if (tbody) {
        tbody.innerHTML = '';
        const activeShipments = appState.shipments.filter(s => s.status !== 'CREATED' && s.status !== 'DRAFT' && s.status !== 'PENDING_CONFIRMATION');

        activeShipments.forEach(s => {
          const tr = document.createElement('tr');
          let statusBadge = '<span class="badge badge-blue">DALAM PELAYARAN</span>';
          if (s.status === 'DELIVERED') statusBadge = '<span class="badge badge-green">SELESAI (DITERIMA BUYER)</span>';
          else if (s.status === 'CONFIRMED') statusBadge = '<span class="badge badge-blue">DIKONFIRMASI (IN TRANSIT)</span>';

          tr.innerHTML = `
            <td>
              <strong>${s.tracking_number}</strong>
              <div style="font-size: 11px; color: var(--text-muted);">${s.carrier_name}</div>
            </td>
            <td>
              <strong>${s.commodity}</strong>
              <div style="font-size: 11px; color: var(--text-muted);">${s.weight_kg ? s.weight_kg.toLocaleString() : '43,200'} kg</div>
            </td>
            <td>
              <div>${s.origin ? s.origin.name : 'Tanjung Priok'} -> ${s.destination ? s.destination.name : 'Rotterdam'}</div>
            </td>
            <td>${statusBadge}</td>
            <td>${s.status === 'DELIVERED' ? '<span class="badge badge-green">DITERIMA OLEH BUYER</span>' : '<span class="badge badge-amber">MENUNGGU KEDATANGAN</span>'}</td>
            <td>
              <button class="btn btn-sm btn-outline-primary" onclick="viewShipmentOnMap('${s.id}')">LIHAT PETA</button>
            </td>
          `;
          tbody.appendChild(tr);
        });
      }

      // Metric update for Shipper
      const pendingCount = appState.shipments.filter(s => s.status === 'CREATED' || s.status === 'DRAFT' || s.status === 'PENDING_CONFIRMATION').length;
      const elPending = document.getElementById('shipper-stat-total');
      if (elPending) elPending.innerText = pendingCount + ' MENUNGGU';

      renderTimelineStepper(appState.selectedShipmentId, 'shipper-stepper');
    }

    /* 2. OPERATOR RENDER */
    function renderOperatorData() {
      const tbody = document.getElementById('op-verify-tbody');
      if (!tbody) return;
      tbody.innerHTML = '';

      appState.shipments.forEach(s => {
        const tr = document.createElement('tr');
        
        let statusBadge = '';
        if (s.status === 'CREATED' || s.status === 'DRAFT' || s.status === 'PENDING_CONFIRMATION') {
          statusBadge = '<span class="badge badge-amber">1. MENUNGGU KONFIRMASI SHIPPER</span>';
        } else if (s.status === 'CONFIRMED' || s.status === 'IN_TRANSIT') {
          statusBadge = '<span class="badge badge-blue">2. DIKONFIRMASI SHIPPER (BERLAYAR)</span>';
        } else if (s.status === 'DELIVERED' || s.status === 'COMPLETED') {
          statusBadge = '<span class="badge badge-green">3. SELESAI (DITERIMA BUYER)</span>';
        } else {
          statusBadge = `<span class="badge badge-gray">${s.status}</span>`;
        }

        tr.innerHTML = `
          <td>
            <strong style="color: var(--brand-navy); font-size: 13px;">${s.tracking_number}</strong>
            <div style="font-size: 11px; font-family: var(--font-mono); color: var(--brand-blue);">${s.container_number || 'CSQU-305438-3'}</div>
          </td>
          <td>
            <strong>${s.commodity}</strong>
            <div style="font-size: 11px; color: var(--text-muted);">${s.cargo_type || (s.weight_kg ? s.weight_kg.toLocaleString() + ' kg' : '')}</div>
          </td>
          <td>${s.shipper_name || 'PT Samudera Kopi Nusantara'}</td>
          <td>${s.buyer_name || 'EuroGlobal Coffee B.V.'}</td>
          <td>
            <div>${s.origin ? s.origin.code : 'IDTPR'} &rarr; ${s.destination ? s.destination.code : 'NLRTM'}</div>
            <div style="font-size: 10px; color: var(--text-muted);">${s.carrier_name || 'Maersk Line'}</div>
          </td>
          <td>${statusBadge}</td>
          <td>
            <button class="btn btn-sm btn-outline-primary" onclick="viewShipmentOnMap('${s.id}')">
              LACAK PETA
            </button>
          </td>
        `;
        tbody.appendChild(tr);
      });

      // Update checkpoint and exception dropdowns
      const cpSelect = document.getElementById('op-checkpoint-shipment');
      if (cpSelect) {
        const curVal = cpSelect.value;
        cpSelect.innerHTML = '';
        appState.shipments.forEach(s => {
          const opt = document.createElement('option');
          opt.value = s.id;
          opt.innerText = `${s.tracking_number} — ${s.commodity}`;
          cpSelect.appendChild(opt);
        });
        if (curVal) cpSelect.value = curVal;
      }

      const exSelect = document.getElementById('op-exception-shipment');
      if (exSelect) {
        const curVal2 = exSelect.value;
        exSelect.innerHTML = '';
        appState.shipments.forEach(s => {
          const opt = document.createElement('option');
          opt.value = s.id;
          opt.innerText = `${s.tracking_number} — ${s.commodity}`;
          exSelect.appendChild(opt);
        });
        if (curVal2) exSelect.value = curVal2;
      }

      // Update Operator Metrics
      const totalCount = appState.shipments.length;
      const pendingCount = appState.shipments.filter(s => s.status === 'CREATED' || s.status === 'DRAFT' || s.status === 'PENDING_CONFIRMATION').length;
      const confirmedCount = appState.shipments.filter(s => s.status === 'CONFIRMED' || s.status === 'IN_TRANSIT').length;
      const deliveredCount = appState.shipments.filter(s => s.status === 'DELIVERED' || s.status === 'COMPLETED').length;

      const elTotal = document.getElementById('op-stat-total');
      if (elTotal) elTotal.innerText = totalCount + ' PENGIRIMAN';
      const elPending = document.getElementById('op-stat-pending');
      if (elPending) elPending.innerText = pendingCount + ' PERLU KONFIRMASI';
      const elEscrow = document.getElementById('op-stat-escrow');
      if (elEscrow) elEscrow.innerText = confirmedCount + ' BERLAYAR';
      const elException = document.getElementById('op-stat-exception');
      if (elException) elException.innerText = deliveredCount + ' SELESAI';
    }

    /* 3. BUYER RENDER */
    function renderBuyerData() {
      // Tab A: Barang Menunggu Persetujuan Penerimaan (IN_TRANSIT / CONFIRMED / ARRIVED)
      const pendingTbody = document.getElementById('buyer-pending-tbody');
      if (pendingTbody) {
        pendingTbody.innerHTML = '';
        const pendingShipments = appState.shipments.filter(s => s.status === 'CONFIRMED' || s.status === 'IN_TRANSIT' || s.status === 'ARRIVED');
        
        if (pendingShipments.length === 0) {
          pendingTbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">Tidak ada kargo yang sedang berlayar/menunggu konfirmasi penerimaan saat ini.</td></tr>';
        } else {
          pendingShipments.forEach(s => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
              <td>
                <strong style="color: var(--brand-navy); font-size: 13px;">${s.tracking_number}</strong>
                <div style="font-size: 11px; font-family: var(--font-mono); color: var(--brand-blue);">${s.container_number || 'CSQU-305438-3'}</div>
              </td>
              <td>
                <strong>${s.commodity}</strong>
                <div style="font-size: 11px; color: var(--text-muted);">${s.cargo_type || (s.weight_kg ? s.weight_kg.toLocaleString() + ' kg' : '')}</div>
              </td>
              <td>${s.shipper_name || 'PT Samudera Kopi Nusantara'}</td>
              <td>
                <div>${s.destination ? s.destination.name : 'Port of Rotterdam'}</div>
                <div style="font-size: 11px; color: var(--text-muted);">Asal: ${s.origin ? s.origin.name : 'Tanjung Priok'}</div>
              </td>
              <td>
                <span class="badge badge-blue">DALAM PERJALANAN / TIBA</span>
                <div style="font-size: 10px; color: var(--text-muted); margin-top: 2px;">Telah dikonfirmasi Shipper</div>
              </td>
              <td>
                <button class="btn btn-sm" style="background: #059669; color: #ffffff; font-weight: 700; padding: 6px 14px;" onclick="confirmReceiptByBuyer('${s.id}')">
                  ✓ SETUJUI PENERIMAAN
                </button>
              </td>
            `;
            pendingTbody.appendChild(tr);
          });
        }
      }

      // Tab B: Riwayat Pesanan Selesai (status === 'DELIVERED' || status === 'COMPLETED')
      const ordersTbody = document.getElementById('buyer-orders-tbody');
      if (ordersTbody) {
        ordersTbody.innerHTML = '';
        const completedShipments = appState.shipments.filter(s => s.status === 'DELIVERED' || s.status === 'COMPLETED');

        if (completedShipments.length === 0) {
          ordersTbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">Belum ada pesanan yang selesai diterima.</td></tr>';
        } else {
          completedShipments.forEach(s => {
            const row = document.createElement('tr');
            row.innerHTML = `
              <td><strong>${s.tracking_number}</strong></td>
              <td>${s.commodity}</td>
              <td>${s.shipper_name || 'PT Samudera Kopi Nusantara'}</td>
              <td>${s.carrier_name || 'Maersk Line'}</td>
              <td><span class="badge badge-green">PESANAN DITERIMA & SELESAI</span></td>
              <td>
                <button class="btn btn-sm btn-outline-primary" onclick="viewShipmentOnMap('${s.id}')">LIHAT RUTE</button>
              </td>
            `;
            ordersTbody.appendChild(row);
          });
        }
      }

      // Update Buyer stats
      const buyerTotal = appState.shipments.length;
      const buyerArrived = appState.shipments.filter(s => s.status === 'DELIVERED' || s.status === 'COMPLETED').length;
      const elOrders = document.getElementById('buyer-stat-orders');
      if (elOrders) elOrders.innerText = buyerTotal + ' TOTAL KARGO';
      const elArrived = document.getElementById('buyer-stat-arrived');
      if (elArrived) elArrived.innerText = buyerArrived + ' DITERIMA';
    }

    /* AUDIT LOG RENDER */
    function renderAuditLogs() {
      const tbody = document.getElementById('op-audit-tbody');
      if (!tbody) return;
      tbody.innerHTML = '';

      appState.auditLogs.forEach(l => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td style="font-family: var(--font-mono); font-size: 11px;">${l.time}</td>
          <td><strong>${l.actor}</strong></td>
          <td><span class="badge badge-navy">${l.action}</span></td>
          <td><span class="badge badge-gray">${l.module}</span></td>
          <td style="font-size: 12px;">${l.detail}</td>
          <td><span class="badge badge-${l.status === 'VALID' ? 'green' : 'amber'}">${l.status}</span></td>
        `;
        tbody.appendChild(tr);
      });
    }

    /* TIMELINE STEPPER (3-TAHAP ALUR UTAMA) */
    function renderTimelineStepper(shipmentId, elementId) {
      const el = document.getElementById(elementId);
      if (!el) return;

      const s = appState.shipments.find(item => item.id === shipmentId) || appState.shipments[0];
      const isShipperConfirmed = s && (s.status === 'CONFIRMED' || s.status === 'IN_TRANSIT' || s.status === 'DELIVERED' || s.status === 'COMPLETED');
      const isBuyerDelivered = s && (s.status === 'DELIVERED' || s.status === 'COMPLETED');

      const steps = [
        { label: '1. INPUT BARANG (OPERATOR)', done: true },
        { label: '2. KONFIRMASI (SHIPPER)', done: !!isShipperConfirmed },
        { label: '3. PENERIMAAN (BUYER)', done: !!isBuyerDelivered }
      ];

      el.innerHTML = '';
      steps.forEach((step, idx) => {
        const stepDiv = document.createElement('div');
        stepDiv.className = 'timeline-step ' + (step.done ? 'done' : '');
        stepDiv.innerHTML = `
          <div class="step-num">${idx + 1}</div>
          <div class="step-label">${step.label}</div>
        `;
        el.appendChild(stepDiv);
      });
    }

    /* WORKFLOW ACTION 1: OPERATOR INPUT DETAIL BARANG */
    async function handleOperatorInputSubmit(e) {
      e.preventDefault();

      const name = document.getElementById('op-cargo-name').value.trim();
      const category = document.getElementById('op-cargo-category').value;
      const weight = parseFloat(document.getElementById('op-cargo-weight').value) || 1000;
      const volume = parseFloat(document.getElementById('op-cargo-volume').value) || 10;
      const qty = document.getElementById('op-cargo-qty').value.trim() || '1 Kontainer';
      const condition = document.getElementById('op-cargo-condition').value;
      const containerNo = document.getElementById('op-cargo-container').value.trim().toUpperCase();
      const shipperName = document.getElementById('op-cargo-shipper').value;
      const buyerName = document.getElementById('op-cargo-buyer').value;
      const carrierName = document.getElementById('op-cargo-carrier').value;
      const originCode = document.getElementById('op-cargo-origin').value;
      const destCode = document.getElementById('op-cargo-dest').value;
      const departureDate = document.getElementById('op-cargo-departure').value;
      const arrivalDate = document.getElementById('op-cargo-arrival').value;
      const notes = document.getElementById('op-cargo-notes').value.trim();

      const newId = 'CTG-202611' + (appState.shipments.length + 1).toString().padStart(2, '0') + '-000' + (appState.shipments.length + 1);

      const newShipment = {
        id: newId,
        tracking_number: newId,
        container_number: containerNo,
        shipper_name: shipperName,
        buyer_name: buyerName,
        carrier_name: carrierName,
        commodity: name,
        category: category,
        cargo_type: qty,
        condition: condition,
        weight_kg: weight,
        volume_cbm: volume,
        origin: MASTER_LOCATIONS[originCode] || MASTER_LOCATIONS['IDTPR'],
        destination: MASTER_LOCATIONS[destCode] || MASTER_LOCATIONS['NLRTM'],
        current_location: MASTER_LOCATIONS[originCode] || MASTER_LOCATIONS['IDTPR'],
        status: 'CREATED',
        is_delayed: false,
        delay_hours: 0,
        departure_date: departureDate,
        expected_arrival: arrivalDate,
        tracking_events: [
          {
            time: new Date().toISOString().replace('T', ' ').substring(0, 16),
            location: (MASTER_LOCATIONS[originCode] || MASTER_LOCATIONS['IDTPR']).name,
            event: 'CREATED',
            remarks: `Kargo didaftarkan oleh Operator. Barang: ${name} (${qty}, ${weight} kg). Menunggu konfirmasi Shipper.`
          }
        ]
      };

      appState.shipments.unshift(newShipment);

      // Audit Log
      appState.auditLogs.unshift({
        time: new Date().toISOString().replace('T', ' ').substring(0, 19),
        actor: 'operator@cargotrace.com',
        action: 'OPERATOR_INPUT_CARGO',
        module: 'OPERATOR_PORTAL',
        detail: `Operator menginput barang baru ${newId} (${name}, ${containerNo}). Menunggu konfirmasi Shipper.`,
        status: 'VALID'
      });

      // API call to backend
      try {
        await fetch('/api/v1/shipments', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
          },
          body: JSON.stringify({
            tracking_number: newId,
            container_number: containerNo,
            origin_location_id: 1,
            destination_location_id: 3,
            carrier_id: 1,
            customer_id: 2,
            buyer_id: 4,
            scheduled_departure: departureDate + ' 08:00:00',
            estimated_arrival: arrivalDate + ' 18:00:00',
            status: 'CREATED',
            notes: notes,
            items: [
              {
                item_name: name,
                category: category,
                quantity: qty,
                weight_kg: weight,
                volume_cbm: volume,
                condition: condition,
                notes: notes
              }
            ]
          })
        });
      } catch (err) {
        console.warn('API post fallback to state:', err);
      }

      renderAllData();
      switchOpTab('list');
      showToast(`Data barang ${newId} berhasil diinput oleh Operator! Status: Menunggu Konfirmasi Shipper.`, 'success');
    }

    /* WORKFLOW ACTION 2: SHIPPER KONFIRMASI BARANG */
    async function confirmByShipper(shipmentId) {
      const s = appState.shipments.find(item => item.id === shipmentId);
      if (!s) return;

      s.status = 'IN_TRANSIT';

      s.tracking_events.unshift({
        time: new Date().toISOString().replace('T', ' ').substring(0, 16),
        location: s.origin.name,
        event: 'CONFIRMED_BY_SHIPPER',
        remarks: `Shipper telah memeriksa dan menyetujui rincian kargo. Kapal diberangkatkan menuju ${s.destination.name}.`
      });

      appState.auditLogs.unshift({
        time: new Date().toISOString().replace('T', ' ').substring(0, 19),
        actor: 'shipper@cargotrace.com',
        action: 'SHIPPER_CONFIRMATION',
        module: 'SHIPPER_APPROVAL',
        detail: `Shipper menyetujui rincian kargo ${s.tracking_number} yang diinput Operator. Status diubah menjadi IN_TRANSIT.`,
        status: 'VALID'
      });

      try {
        await fetch(`/api/v1/shipments/${shipmentId}/confirm-shipper`, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
          }
        });
      } catch (err) {
        console.warn('API confirm-shipper fallback to state:', err);
      }

      renderAllData();
      showToast(`Kargo ${s.tracking_number} berhasil disetujui & dikonfirmasi oleh Shipper! Kargo kini dalam pengiriman.`, 'success');
    }

    /* WORKFLOW ACTION 3: BUYER SETUJUI PENERIMAAN */
    async function confirmReceiptByBuyer(shipmentId) {
      const s = appState.shipments.find(item => item.id === shipmentId);
      if (!s) return;

      s.status = 'DELIVERED';
      s.current_location = s.destination;

      s.tracking_events.unshift({
        time: new Date().toISOString().replace('T', ' ').substring(0, 16),
        location: s.destination.name,
        event: 'DELIVERED',
        remarks: `Pesanan telah diterima dan disetujui dalam kondisi baik oleh Buyer di ${s.destination.name}. Siklus pengiriman selesai.`
      });

      appState.auditLogs.unshift({
        time: new Date().toISOString().replace('T', ' ').substring(0, 19),
        actor: 'buyer@cargotrace.com',
        action: 'BUYER_RECEIPT_APPROVAL',
        module: 'BUYER_PORTAL',
        detail: `Buyer menyetujui penerimaan barang ${s.tracking_number}. Seluruh alur pengiriman dinyatakan SELESAI.`,
        status: 'VALID'
      });

      try {
        await fetch(`/api/v1/shipments/${shipmentId}/confirm-buyer`, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
          }
        });
      } catch (err) {
        console.warn('API confirm-buyer fallback to state:', err);
      }

      renderAllData();
      showToast(`Penerimaan barang ${s.tracking_number} berhasil disetujui oleh Buyer! Siklus pengiriman selesai.`, 'success');
    }

    /* OPERATOR CHECKPOINT & EXCEPTION LOGGING */
    function handleAddCheckpoint(e) {
      e.preventDefault();
      const shipmentId = document.getElementById('op-checkpoint-shipment').value;
      const locCode = document.getElementById('op-checkpoint-location').value;
      const evType = document.getElementById('op-checkpoint-type').value;
      const remarks = document.getElementById('op-checkpoint-remarks').value;

      const s = appState.shipments.find(item => item.id === shipmentId);
      if (!s) return;

      const loc = MASTER_LOCATIONS[locCode] || s.origin;
      s.current_location = loc;
      if (evType === 'ARRIVAL') s.status = 'ARRIVED';

      s.tracking_events.push({
        time: new Date().toISOString().replace('T', ' ').substring(0, 16),
        location: loc.name,
        event: evType,
        remarks: remarks
      });

      appState.auditLogs.unshift({
        time: new Date().toISOString().replace('T', ' ').substring(0, 19),
        actor: 'operator@cargotrace.com',
        action: 'TRACKING_EVENT_LOGGED',
        module: 'TRACKING',
        detail: `Checkpoint baru dicatat: ${evType} di ${loc.name} untuk kargo ${shipmentId}.`,
        status: 'VALID'
      });

      renderAllData();
      showToast(`Checkpoint maritim berhasil dicatat untuk ${shipmentId}`, 'success');
      document.getElementById('op-checkpoint-remarks').value = '';
    }

    function handleAddException(e) {
      e.preventDefault();
      const shipmentId = document.getElementById('op-exception-shipment').value;
      const hours = parseInt(document.getElementById('op-exception-hours').value) || 24;
      const desc = document.getElementById('op-exception-desc').value;

      const s = appState.shipments.find(item => item.id === shipmentId);
      if (!s) return;

      s.is_delayed = true;
      s.delay_hours += hours;

      appState.auditLogs.unshift({
        time: new Date().toISOString().replace('T', ' ').substring(0, 19),
        actor: 'operator@cargotrace.com',
        action: 'DELAY_FLAGGED',
        module: 'EXCEPTION_MGMT',
        detail: `Kendala dicatat pada ${shipmentId}: +${hours} jam (${desc}). Status kargo: FLAG DELAYED.`,
        status: 'WARNING'
      });

      renderAllData();
      showToast(`Flag keterlambatan (+${hours} jam) berhasil diterapkan pada ${shipmentId}!`, 'warning');
    }

    function resolveCurrentException() {
      const shipmentId = document.getElementById('op-exception-shipment').value;
      const s = appState.shipments.find(item => item.id === shipmentId);
      if (!s) return;

      s.is_delayed = false;
      s.delay_hours = 0;

      appState.auditLogs.unshift({
        time: new Date().toISOString().replace('T', ' ').substring(0, 19),
        actor: 'operator@cargotrace.com',
        action: 'DELAY_RESOLVED',
        module: 'EXCEPTION_MGMT',
        detail: `Kendala pada ${shipmentId} telah diselesaikan. Status kargo kembali NORMAL.`,
        status: 'VALID'
      });

      renderAllData();
      showToast(`Kendala pada ${shipmentId} dinyatakan RESOLVED / SELESAI!`, 'success');
    }

    /* TABS NAVIGATION */
    function switchShipperTab(tab) {
      document.querySelectorAll('#view-shipper .nav-tab-btn').forEach(btn => btn.classList.remove('active'));
      const activeBtn = document.getElementById('tab-btn-shipper-' + tab);
      if (activeBtn) activeBtn.classList.add('active');

      const tabPending = document.getElementById('shipper-tab-pending');
      if (tabPending) tabPending.style.display = (tab === 'pending') ? 'block' : 'none';

      const tabList = document.getElementById('shipper-tab-list');
      if (tabList) tabList.style.display = (tab === 'list') ? 'block' : 'none';

      const tabTrack = document.getElementById('shipper-tab-tracking');
      if (tabTrack) tabTrack.style.display = (tab === 'tracking') ? 'block' : 'none';

      if (tab === 'tracking') {
        setTimeout(() => initLeafletMap('shipper', 'leaflet-map-shipper'), 200);
      }
    }

    function switchOpTab(tab) {
      document.querySelectorAll('#view-operator .nav-tab-btn').forEach(btn => btn.classList.remove('active'));
      const activeBtn = document.getElementById('tab-btn-op-' + tab);
      if (activeBtn) activeBtn.classList.add('active');

      const tabInput = document.getElementById('op-tab-input');
      if (tabInput) tabInput.style.display = (tab === 'input') ? 'block' : 'none';

      const tabList = document.getElementById('op-tab-list');
      if (tabList) tabList.style.display = (tab === 'list') ? 'block' : 'none';

      const tabTrack = document.getElementById('op-tab-tracking');
      if (tabTrack) tabTrack.style.display = (tab === 'tracking') ? 'block' : 'none';

      const tabAudit = document.getElementById('op-tab-audit');
      if (tabAudit) tabAudit.style.display = (tab === 'audit') ? 'block' : 'none';

      if (tab === 'tracking') {
        setTimeout(() => initLeafletMap('operator', 'leaflet-map-operator'), 200);
      }
    }

    function switchBuyerTab(tab) {
      document.querySelectorAll('#view-buyer .nav-tab-btn').forEach(btn => btn.classList.remove('active'));
      const activeBtn = document.getElementById('tab-btn-buyer-' + tab);
      if (activeBtn) activeBtn.classList.add('active');

      const tabPending = document.getElementById('buyer-tab-pending');
      if (tabPending) tabPending.style.display = (tab === 'pending') ? 'block' : 'none';

      const tabOrders = document.getElementById('buyer-tab-orders');
      if (tabOrders) tabOrders.style.display = (tab === 'orders') ? 'block' : 'none';

      const tabMap = document.getElementById('buyer-tab-map');
      if (tabMap) tabMap.style.display = (tab === 'map') ? 'block' : 'none';

      if (tab === 'map') {
        setTimeout(() => initLeafletMap('buyer', 'leaflet-map-buyer'), 200);
      }
    }

    function viewShipmentOnMap(id) {
      appState.selectedShipmentId = id;
      const el = document.getElementById('shipper-tracking-selected-id');
      if (el) el.innerText = id;
      switchShipperTab('tracking');
      setTimeout(() => initLeafletMap('shipper', 'leaflet-map-shipper'), 250);
    }

    /* LEAFLET GEOSPATIAL MAP INITIALIZATION */
    function initLeafletMap(role, containerId) {
      const container = document.getElementById(containerId);
      if (!container) return;

      if (mapInstances[role]) {
        mapInstances[role].remove();
        mapInstances[role] = null;
      }

      const map = L.map(containerId, {
        zoomControl: true,
        attributionControl: false
      }).setView([10.0, 70.0], 3);

      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18
      }).addTo(map);

      mapInstances[role] = map;

      const s = appState.shipments.find(item => item.id === appState.selectedShipmentId) || appState.shipments[0];

      const latlngs = [
        [s.origin.lat, s.origin.lng],
        [1.29027, 103.85196],
        [6.0, 80.0],
        [12.5, 43.5],
        [27.8, 34.0],
        [36.0, 14.5],
        [36.0, -5.5],
        [48.0, -5.0],
        [s.destination.lat, s.destination.lng]
      ];

      const routeLine = L.polyline(latlngs, {
        color: '#1d4ed8',
        weight: 3,
        opacity: 0.85,
        dashArray: '6, 8'
      }).addTo(map);

      L.circleMarker([s.origin.lat, s.origin.lng], {
        radius: 8,
        fillColor: '#059669',
        color: '#ffffff',
        weight: 2,
        fillOpacity: 1
      }).addTo(map).bindPopup(`<b>Pelabuhan Muat (Origin)</b><br>${s.origin.name} (${s.origin.code})`);

      L.circleMarker([s.destination.lat, s.destination.lng], {
        radius: 8,
        fillColor: '#dc2626',
        color: '#ffffff',
        weight: 2,
        fillOpacity: 1
      }).addTo(map).bindPopup(`<b>Pelabuhan Tujuan (Destination)</b><br>${s.destination.name} (${s.destination.code})`);

      const currentLoc = s.current_location || MASTER_LOCATIONS['SGSIN'];
      L.circleMarker([currentLoc.lat, currentLoc.lng], {
        radius: 10,
        fillColor: '#d97706',
        color: '#ffffff',
        weight: 3,
        fillOpacity: 1
      }).addTo(map).bindPopup(`
        <b>POSISI KAPAL SAAT INI:</b><br>
        Kapal: ${s.carrier_name}<br>
        Lokasi: ${currentLoc.name}<br>
        Kargo: ${s.commodity}<br>
        Status: <b>${s.status}</b><br>
        Nilai Escrow: $${s.value_usd.toLocaleString()} USD
      `).openPopup();

      map.fitBounds(routeLine.getBounds(), { padding: [40, 40] });
    }

    /* TOAST NOTIFICATION UTILITY */
    function showToast(msg, type = 'info') {
      const toast = document.getElementById('toast-notification');
      if (!toast) return;

      toast.innerText = msg;
      toast.style.display = 'block';

      if (type === 'success') {
        toast.style.background = '#065f46';
        toast.style.color = '#ffffff';
        toast.style.borderColor = '#047857';
      } else if (type === 'warning') {
        toast.style.background = '#92400e';
        toast.style.color = '#ffffff';
        toast.style.borderColor = '#b45309';
      } else {
        toast.style.background = '#0f2d59';
        toast.style.color = '#ffffff';
        toast.style.borderColor = '#1d4ed8';
      }

      setTimeout(() => {
        toast.style.display = 'none';
      }, 3500);
    }

    /* INITIAL ON LOAD */
    window.addEventListener('DOMContentLoaded', () => {
      // Default: select Operator auth portal
      selectAuthTab('operator');

      // Update live clock
      setInterval(() => {
        const now = new Date();
        const str = now.toISOString().substring(0, 10) + ' ' + now.toLocaleTimeString() + ' WIB';
        const el = document.getElementById('live-clock');
        if (el) el.innerText = str;
      }, 1000);
    });

  </script>
</body>
</html>