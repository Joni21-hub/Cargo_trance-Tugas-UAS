<div id="page-workspace" class="hidden">
  <div id="app-console" class="app-console">
    <!-- SIDEBAR NAVIGASI KIRI (DESKTOP / LAPTOP LAYAR PENUH) -->
    <aside class="console-sidebar" id="console-sidebar">
      <div class="sidebar-top">
        @include('partials.brand-logo')
        <div class="sidebar-role-wrap">
          <span class="role-pill" id="ws-role-badge">PELACAKAN PUBLIK</span>
          <span class="branch-pill hidden" id="ws-branch-badge"></span>
        </div>
      </div>

      <nav class="sidebar-nav" id="sidebar-nav"></nav>

      <div class="sidebar-footer">
        <button type="button" class="btn btn-outline btn-sm" style="width:100%;justify-content:center;" onclick="MeridianApp.openMapModal()">
          Buka Peta Rute 3D
        </button>
        <button type="button" class="btn btn-soft-primary btn-sm" style="width:100%;justify-content:center;" onclick="MeridianApp.openHelpModal()">
          Bantuan Admin
        </button>
      </div>
    </aside>

    <!-- KOLOM KANAN UTAMA (100% LEBAR KERJA FOKUS — TANPA BOLA BUMI MENIMPA FORM) -->
    <div class="console-body">
      <header class="console-header">
        <div class="console-header-left">
          <span class="mobile-brand-only">@include('partials.brand-logo')</span>
          <div class="header-titles">
            <h1 class="header-page-title" id="header-page-title">Ruang Kerja</h1>
            <p class="header-page-sub" id="header-page-sub">Memuat data sistem...</p>
          </div>
        </div>

        <div class="console-header-right">
          <button type="button" id="ws-btn-map-top" class="btn btn-outline btn-sm" onclick="MeridianApp.openMapModal()">
            Peta 3D
          </button>
          <button type="button" id="ws-btn-help-top" class="btn btn-soft-primary btn-sm" onclick="MeridianApp.openHelpModal()">
            Bantuan Admin
          </button>
          <button type="button" id="ws-btn-wallet" class="btn btn-outline btn-sm mono hidden" onclick="MeridianApp.toggleUtilityDrawer('wallet')">
            Saldo: $0
          </button>
          <button type="button" id="ws-btn-notif" class="btn btn-outline btn-sm hidden" onclick="MeridianApp.toggleUtilityDrawer('notifications')">
            <span class="hide-xs">Notifikasi</span> <span class="notif-dot-badge" id="ws-notif-count">0</span>
          </button>
          <button type="button" id="ws-btn-profile-pill" class="user-chip hidden" onclick="MeridianApp.openProfileTab()">
            <span class="user-avatar" id="ws-user-avatar">U</span>
            <span class="user-chip-name" id="ws-user-name">Pengguna</span>
          </button>
          <button type="button" id="ws-btn-logout" class="btn btn-outline btn-sm hidden" onclick="MeridianApp.handleLogout()">
            Keluar
          </button>
          <button type="button" id="ws-btn-login" class="btn btn-primary btn-sm hidden" onclick="MeridianApp.navigate('login', '/login')">
            Masuk
          </button>
        </div>
      </header>

      <!-- KONTEN UTAMA FULL-SCREEN (DINAMIS PER PERAN & TAB) -->
      <main class="console-main" id="console-main"></main>
    </div>

    <!-- DRAWER UTILITAS KANAN (NOTIFIKASI & DOMPET REKENING JAMINAN) -->
    <aside class="utility-drawer hidden" id="ws-utility-drawer">
      <div class="drawer-head">
        <div>
          <div class="modal-title" id="drawer-title">Notifikasi</div>
          <div class="modal-sub" id="drawer-sub">Pembaruan langsung sistem</div>
        </div>
        <button type="button" class="btn btn-outline btn-sm" onclick="MeridianApp.closeUtilityDrawer()">Tutup</button>
      </div>
      <div class="drawer-body" id="drawer-body"></div>
    </aside>

    <!-- NAVIGASI BAWAH KHUSUS LAYAR HP / MOBILE (< 960px) -->
    <nav class="mobile-bottom-nav" id="mobile-bottom-nav"></nav>
  </div>
</div>
