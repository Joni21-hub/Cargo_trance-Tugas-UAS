<div id="page-workspace" class="hidden">
  <div id="workspace-shell">
    <!-- BOLA BUMI 3D TERPADU PENUH DI TENGAH ALAM SEMESTA -->
    <div id="workspace-globe-map"></div>

    <!-- BAR NAVIGASI KACA MELAYANG DI ATAS -->
    <header class="ws-topbar glass-surface">
      <div class="ws-topbar-left">
        @include('partials.brand-logo')
        <span class="brand-divider"></span>
        <span class="ws-role-badge role-public" id="ws-role-badge">PELACAKAN PUBLIK</span>
        <span class="tag-mono hidden" id="ws-branch-badge"></span>
      </div>

      <!-- METRIK RINGKAS KHUSUS SESUAI PERAN -->
      <div class="ws-topbar-metrics" id="ws-topbar-metrics"></div>

      <!-- UTILITAS DOMPET, NOTIFIKASI & AKUN -->
      <div class="ws-topbar-right">
        <button type="button" id="ws-btn-wallet" class="btn btn-glass btn-sm mono hidden" onclick="MeridianApp.toggleUtilityDrawer('wallet')">
          Saldo: $0
        </button>
        <button type="button" id="ws-btn-notif" class="btn btn-glass btn-sm hidden" onclick="MeridianApp.toggleUtilityDrawer('notifications')">
          Notifikasi <span class="notif-dot-badge" id="ws-notif-count">0</span>
        </button>
        <span class="ws-user-tag hidden" id="ws-user-name"></span>
        <button type="button" id="ws-btn-logout" class="btn btn-glass btn-sm hidden" onclick="MeridianApp.handleLogout()">
          Keluar
        </button>
        <button type="button" id="ws-btn-login" class="btn btn-primary btn-sm hidden" onclick="MeridianApp.navigate('login', '/login')">
          Masuk
        </button>
      </div>
    </header>

    <!-- BINGKAI KACA KIRI -->
    <aside class="ws-panel ws-panel-left glass-surface" id="ws-panel-left">
      <div class="panel-head">
        <div>
          <div class="panel-title" id="left-panel-title">Ruang Kerja</div>
          <div class="panel-sub" id="left-panel-sub">Memuat data...</div>
        </div>
        <div id="left-panel-head-action"></div>
      </div>
      <div class="panel-body glass-scroll" id="left-panel-body"></div>
    </aside>

    <!-- BINGKAI KACA KANAN -->
    <aside class="ws-panel ws-panel-right glass-surface" id="ws-panel-right">
      <div class="panel-head">
        <div>
          <div class="panel-title" id="right-panel-title">Telemetri &amp; Detail</div>
          <div class="panel-sub" id="right-panel-sub">Pilih kargo atau titik di bola bumi</div>
        </div>
        <div id="right-panel-head-action"></div>
      </div>
      <div class="panel-body glass-scroll" id="right-panel-body"></div>
    </aside>

    <!-- DRAWER NOTIFIKASI & DOMPET VIRTUAL ESCROW -->
    <section class="ws-utility-drawer glass-surface hidden" id="ws-utility-drawer">
      <div class="panel-head">
        <div>
          <div class="panel-title" id="drawer-title">Notifikasi</div>
          <div class="panel-sub" id="drawer-sub">Pembaruan sistem langsung</div>
        </div>
        <button type="button" class="btn btn-glass btn-sm" onclick="MeridianApp.closeUtilityDrawer()">Tutup</button>
      </div>
      <div class="panel-body glass-scroll" id="drawer-body"></div>
    </section>

    <!-- DOCK KONTROL BOLA BUMI MELAYANG DI TENGAH BAWAH (DENGAN ATRIBUSI RESMI) -->
    <div class="ws-earth-dock glass-surface">
      <button type="button" id="btn-layer-sat" class="dock-btn active" onclick="MeridianGlobe.setLayerMode('satellite')">Satelit Bumi</button>
      <button type="button" id="btn-layer-osm" class="dock-btn" onclick="MeridianGlobe.setLayerMode('osm')">Peta OSM</button>
      <span class="dock-sep"></span>
      <button type="button" class="dock-btn mono" onclick="MeridianGlobe.zoomIn()" title="Perbesar">+</button>
      <button type="button" class="dock-btn mono" onclick="MeridianGlobe.zoomOut()" title="Perkecil">&minus;</button>
      <button type="button" class="dock-btn" onclick="MeridianApp.resetEarthView()">Reset Bumi</button>
      <span class="dock-sep"></span>
      <button type="button" id="btn-toggle-panels" class="dock-btn" onclick="MeridianApp.toggleGlassPanels()">Sembunyikan Panel</button>
      <span class="dock-sep"></span>
      <span class="dock-attribution" title="© OpenStreetMap contributors · Esri, Maxar">&copy; OSM &middot; Esri</span>
    </div>
  </div>
</div>
