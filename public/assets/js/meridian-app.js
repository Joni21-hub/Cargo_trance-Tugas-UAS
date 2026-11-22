/* ==========================================================================
   MERIDIAN — Aplikasi 4 Dashboard Peran Terpisah + Bola Bumi 3D Kosmik
   Admin (/admin) · Operator (/operator) · Shipper (/shipper) · Buyer (/buyer)
   Escrow Simulasi · Dompet Virtual · Notifikasi In-App · Time Travel Demo
   ========================================================================== */

window.MeridianApp = (function () {
  const state = {
    page: 'home',
    user: null,
    locations: [],
    carriers: [],
    customers: [],
    stats: {},
    delayThresholdHours: 12,
    demoToolsEnabled: true,
    unreadNotifications: 0,
    notifications: [],
    wallet: { balance_usd: 0, mutations: [], escrows: [] },
    shipments: [],
    selectedShipment: null,
    searchQuery: '',
    operatorBranchOnly: false,

    // Per-role sub-tabs & active edit targets
    adminTab: 'overview',
    operatorTab: 'fleet',
    shipperTab: 'pending',
    buyerTab: 'incoming',
    utilityDrawer: null, // null | 'notifications' | 'wallet'

    // Admin live data & inline edit states
    adminOverview: null,
    adminUsers: [],
    adminCarriers: [],
    auditLogs: [],
    editingUserId: null,
    editingCarrierId: null,
    editingLocationId: null,
    editingShipment: false,
  };

  /* ------------------------------------------------------------------------
     API CLIENT HELPER
     ------------------------------------------------------------------------ */
  async function api(url, options = {}) {
    const res = await fetch(url, {
      method: options.method || 'GET',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      },
      ...(options.body ? { body: JSON.stringify(options.body) } : {}),
    });

    const json = await res.json().catch(() => ({ success: false, message: 'Respons server tidak valid' }));
    if (!res.ok) {
      const err = new Error(json.message || `Permintaan gagal (${res.status})`);
      err.status = res.status;
      err.errors = json.errors || null;
      throw err;
    }
    return json;
  }

  /* ------------------------------------------------------------------------
     ISO 6346 CHECK-DIGIT HELPER
     ------------------------------------------------------------------------ */
  const ISO_VALS = {
    A: 10, B: 12, C: 13, D: 14, E: 15, F: 16, G: 17, H: 18, I: 19,
    J: 20, K: 21, L: 23, M: 24, N: 25, O: 26, P: 27, Q: 28, R: 29,
    S: 30, T: 31, U: 32, V: 34, W: 35, X: 36, Y: 37, Z: 38,
    0: 0, 1: 1, 2: 2, 3: 3, 4: 4, 5: 5, 6: 6, 7: 7, 8: 8, 9: 9,
  };

  function calcIsoDigit(first10) {
    const s = first10.toUpperCase();
    let sum = 0;
    for (let i = 0; i < 10; i++) sum += (ISO_VALS[s[i]] ?? 0) * (1 << i);
    return (sum % 11) % 10;
  }

  function validateIso6346(val) {
    const clean = (val || '').toUpperCase().replace(/[\s-]/g, '');
    if (!/^[A-Z]{4}\d{7}$/.test(clean)) return { valid: false, reason: 'Format wajib: 4 huruf + 7 angka (cth: CSQU3054383)' };
    const exp = calcIsoDigit(clean.slice(0, 10));
    const act = parseInt(clean[10], 10);
    if (exp !== act) return { valid: false, reason: `Check digit salah (seharusnya ${exp}, bukan ${act})` };
    return { valid: true, clean };
  }

  function generateIso6346(prefix = 'MSKU') {
    const p = (prefix || 'MSKU').toUpperCase().replace(/[^A-Z]/g, '').padEnd(4, 'U').slice(0, 4);
    let num = '';
    for (let i = 0; i < 6; i++) num += Math.floor(Math.random() * 10);
    return p + num + calcIsoDigit(p + num);
  }

  /* ------------------------------------------------------------------------
     UTILITAS FORMAT & STATUS
     ------------------------------------------------------------------------ */
  function esc(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function fmtMoney(val) {
    return '$' + Number(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function fmtDate(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return String(iso).slice(0, 10);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
  }

  function fmtDateTime(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return String(iso).replace('T', ' ').slice(0, 16);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' }) + ' ' +
      d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
  }

  function statusPill(s) {
    if (s.is_delayed && s.status !== 'DELIVERED') {
      const sev = s.delay_severity && s.delay_severity !== 'NONE' ? ` · ${s.delay_severity}` : '';
      return `<span class="status-pill status-delayed">Terlambat +${s.delay_hours}j${sev}</span>`;
    }
    const st = (s.status || 'CREATED').toUpperCase();
    const map = {
      CREATED: 'Menunggu Konfirmasi',
      BOOKED: 'Terjadwal',
      CONFIRMED: 'Dikonfirmasi',
      PICKED_UP: 'Dimuat',
      IN_TRANSIT: 'Dalam Pelayaran',
      DELIVERED: 'Terkirim',
      CANCELLED: 'Dibatalkan',
    };
    return `<span class="status-pill status-${st.toLowerCase()}">${map[st] || st}</span>`;
  }

  function escrowStatusLabel(status) {
    const map = {
      PENDING_DEPOSIT: 'Menunggu Setoran Buyer',
      HELD_IN_ESCROW: 'Dana Aman Ditahan Escrow',
      FROZEN_ON_EXCEPTION: 'Dibekukan (Ada Kendala)',
      RELEASED: 'Cair ke Shipper',
      DISPUTED: 'Dalam Sengketa',
      REFUNDED: 'Dikembalikan ke Buyer',
    };
    return map[status] || status;
  }

  function toast(msg, type = 'info') {
    const c = document.getElementById('toast-container');
    if (!c) return;
    const el = document.createElement('div');
    el.className = `toast-item ${type === 'ok' ? 'toast-ok' : (type === 'err' ? 'toast-err' : '')}`;
    el.innerHTML = `<span class="toast-dot"></span><span>${esc(msg)}</span>`;
    c.appendChild(el);
    setTimeout(() => {
      el.style.opacity = '0';
      el.style.transition = 'opacity 0.25s ease';
      setTimeout(() => el.remove(), 260);
    }, 3600);
  }

  function getRoleDashboardKey(user) {
    if (!user) return 'login';
    if (user.role === 'admin') return 'admin';
    if (user.role === 'operator') return 'operator';
    if (user.role === 'customer' && String(user.email || '').toLowerCase().includes('buyer')) return 'buyer';
    return 'shipper';
  }

  function openUserDashboard() {
    if (!state.user) return navigate('login', '/login');
    const target = getRoleDashboardKey(state.user);
    navigate(target, '/' + target);
  }

  /* ------------------------------------------------------------------------
     DATA FETCHING (REAL DATABASE)
     ------------------------------------------------------------------------ */
  async function loadBootstrap() {
    const res = await api('/api/v1/bootstrap');
    const d = res.data;
    state.user = d.user;
    state.locations = d.locations || [];
    state.carriers = d.carriers || [];
    state.customers = d.customers || [];
    state.stats = d.stats || {};
    state.delayThresholdHours = d.delay_threshold_hours || 12;
    state.demoToolsEnabled = d.demo_tools_enabled !== false;
    state.unreadNotifications = d.unread_notifications || 0;
    renderHomeSummary();
  }

  async function loadRoleShipments() {
    if (state.page === 'track' && !state.user) {
      state.shipments = [];
      window.MeridianGlobe.syncWorkspaceData(state.locations, [], state.selectedShipment);
      return;
    }

    const params = new URLSearchParams({ per_page: '50' });
    if (state.searchQuery.trim()) params.set('search', state.searchQuery.trim());

    if (state.page === 'shipper' && state.user) {
      params.set('customer_id', String(state.user.id));
    } else if (state.page === 'buyer' && state.user) {
      params.set('buyer_id', String(state.user.id));
    } else if (state.page === 'operator' && state.operatorBranchOnly && state.user?.location_id) {
      params.set('branch_location_id', String(state.user.location_id));
    }

    const res = await api('/api/v1/shipments?' + params.toString());
    state.shipments = (res.data && Array.isArray(res.data.data)) ? res.data.data : [];
    window.MeridianGlobe.syncWorkspaceData(state.locations, state.shipments, state.selectedShipment);
  }

  async function loadNotifications() {
    if (!state.user) return;
    try {
      const res = await api('/api/v1/notifications');
      state.notifications = res.data?.items || [];
      state.unreadNotifications = res.data?.unread_count || 0;
    } catch (_) {}
  }

  async function loadWallet() {
    if (!state.user) return;
    try {
      const res = await api('/api/v1/wallet');
      state.wallet = res.data || { balance_usd: 0, mutations: [], escrows: [] };
    } catch (_) {}
  }

  async function loadAdminData() {
    if (!state.user || state.user.role !== 'admin') return;
    const [ov, us, cr, au] = await Promise.all([
      api('/api/v1/admin/overview'),
      api('/api/v1/admin/users'),
      api('/api/v1/admin/carriers'),
      api('/api/v1/audit-logs'),
    ]);
    state.adminOverview = ov.data;
    state.adminUsers = us.data || [];
    state.adminCarriers = cr.data || [];
    state.auditLogs = au.data || [];
  }

  async function refreshCurrentWorkspace() {
    await loadBootstrap();
    await loadRoleShipments();
    await Promise.all([loadNotifications(), loadWallet()]);
    if (state.page === 'admin') await loadAdminData();

    if (state.selectedShipment) {
      try {
        const endpoint = state.user
          ? '/api/v1/shipments/' + encodeURIComponent(state.selectedShipment.tracking_number)
          : '/api/v1/public/track/' + encodeURIComponent(state.selectedShipment.tracking_number);
        const det = await api(endpoint);
        state.selectedShipment = det.data;
        window.MeridianGlobe.syncWorkspaceData(state.locations, state.shipments, state.selectedShipment);
      } catch (_) {
        state.selectedShipment = null;
      }
    }
    renderWorkspace();
    if (state.utilityDrawer) renderUtilityDrawer();
  }

  /* ------------------------------------------------------------------------
     NAVIGATION & STRICT ROLE ROUTE PROTECTION
     ------------------------------------------------------------------------ */
  async function navigate(page, pushUrl = null, extra = {}) {
    if (page === 'dashboard') {
      if (!state.user) return navigate('login', '/login');
      const roleKey = getRoleDashboardKey(state.user);
      return navigate(roleKey, '/' + roleKey);
    }

    if (['admin', 'operator', 'shipper', 'buyer'].includes(page)) {
      if (!state.user) return navigate('login', '/login');
      const trueRole = getRoleDashboardKey(state.user);
      if (page !== trueRole) {
        return navigate(trueRole, '/' + trueRole);
      }
    }

    state.page = page;
    state.utilityDrawer = null;
    document.getElementById('ws-utility-drawer')?.classList.add('hidden');

    document.getElementById('page-home')?.classList.toggle('hidden', page !== 'home');
    document.getElementById('page-auth')?.classList.toggle('hidden', page !== 'login' && page !== 'register');
    const isWorkspace = ['admin', 'operator', 'shipper', 'buyer', 'track'].includes(page);
    document.getElementById('page-workspace')?.classList.toggle('hidden', !isWorkspace);

    const shell = document.getElementById('workspace-shell');
    if (shell) {
      shell.classList.toggle('operator-workstation', page === 'operator');
      shell.classList.remove('panels-collapsed');
    }

    if (pushUrl && window.location.pathname !== pushUrl) {
      window.history.pushState({ page }, '', pushUrl);
    }

    if (page === 'home') {
      renderHomeSummary();
      setTimeout(() => window.MeridianGlobe.initHeroGlobe('hero-globe-map', state.locations), 60);
    } else if (page === 'login' || page === 'register') {
      switchAuthTab(page === 'register' ? 'register' : 'login');
    } else if (isWorkspace) {
      await loadRoleShipments();
      await Promise.all([loadNotifications(), loadWallet()]);
      if (page === 'admin') await loadAdminData();

      renderWorkspace();

      setTimeout(() => {
        window.MeridianGlobe.initWorkspaceGlobe(
          'workspace-globe-map',
          state.locations,
          state.shipments,
          (trackingNum) => selectShipment(trackingNum)
        );
        window.MeridianGlobe.resizeAll();
        if (extra.trackNumber) selectShipment(extra.trackNumber);
      }, 60);
    }
  }

  /* ------------------------------------------------------------------------
     BERANDA PUBLIK
     ------------------------------------------------------------------------ */
  function renderHomeSummary() {
    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
    set('home-stat-ports', String(state.locations.length || 8).padStart(2, '0'));
    set('home-stat-carriers', String(state.carriers.length || 6).padStart(2, '0'));
    set('home-stat-shipments', String(state.stats.total_shipments ?? 0));

    document.getElementById('nav-btn-signin')?.classList.toggle('hidden', !!state.user);
    document.getElementById('nav-btn-console')?.classList.toggle('hidden', !state.user);

    const tbody = document.getElementById('home-ports-tbody');
    if (tbody) {
      tbody.innerHTML = state.locations
        .map(l => `
          <tr style="border-bottom:1px solid rgba(148,163,184,0.12);">
            <td style="padding:12px 18px;"><span class="tag-mono">${esc(l.code)}</span></td>
            <td style="padding:12px 18px;font-weight:600;color:#ffffff;">${esc(l.name)}</td>
            <td style="padding:12px 18px;color:var(--ink-body);">${esc(l.country)}</td>
            <td style="padding:12px 18px;"><span class="tag-mono">${esc(l.type)}</span></td>
            <td class="mono" style="padding:12px 18px;font-size:12px;color:var(--ink-muted);">${Number(l.latitude).toFixed(4)}, ${Number(l.longitude).toFixed(4)}</td>
          </tr>`)
        .join('');
    }
  }

  async function handlePublicTrackSubmit(e) {
    e.preventDefault();
    const input = document.getElementById('home-track-input') || document.getElementById('public-track-side-input');
    const code = (input?.value || '').trim();
    if (!code) return toast('Masukkan nomor resi atau kontainer ISO 6346.', 'err');

    try {
      const endpoint = state.user
        ? '/api/v1/shipments/' + encodeURIComponent(code)
        : '/api/v1/public/track/' + encodeURIComponent(code);
      const res = await api(endpoint);
      if (!res.data) throw new Error('Pengiriman tidak ditemukan');
      state.selectedShipment = res.data;
      await navigate('track', '/track/' + encodeURIComponent(res.data.tracking_number), {
        trackNumber: res.data.tracking_number,
      });
    } catch (err) {
      toast(err.message || `Data pengiriman "${code}" tidak ditemukan.`, 'err');
    }
  }

  /* ------------------------------------------------------------------------
     AUTENTIKASI NYATA (SESI LARAVEL)
     ------------------------------------------------------------------------ */
  function switchAuthTab(mode) {
    const isReg = mode === 'register';
    document.getElementById('tab-auth-login')?.classList.toggle('active', !isReg);
    document.getElementById('tab-auth-register')?.classList.toggle('active', isReg);
    document.getElementById('form-login')?.classList.toggle('hidden', isReg);
    document.getElementById('form-register')?.classList.toggle('hidden', !isReg);
    document.getElementById('auth-error-box')?.classList.add('hidden');
  }

  function showAuthErr(msg) {
    const el = document.getElementById('auth-error-box');
    if (el) {
      el.textContent = msg;
      el.classList.remove('hidden');
    }
  }

  async function handleLoginSubmit(e) {
    e.preventDefault();
    document.getElementById('auth-error-box')?.classList.add('hidden');
    const email = document.getElementById('login-email').value.trim();
    const password = document.getElementById('login-password').value;

    try {
      await api('/api/v1/login', { method: 'POST', body: { email, password } });
      await loadBootstrap();
      state.selectedShipment = null;
      const target = getRoleDashboardKey(state.user);
      toast(`Selamat datang, ${state.user.name}`, 'ok');
      await navigate(target, '/' + target);
    } catch (err) {
      showAuthErr(err.message || 'Email atau kata sandi salah.');
    }
  }

  async function handleRegisterSubmit(e) {
    e.preventDefault();
    document.getElementById('auth-error-box')?.classList.add('hidden');
    const name = document.getElementById('reg-name').value.trim();
    const email = document.getElementById('reg-email').value.trim();
    const password = document.getElementById('reg-password').value;

    try {
      await api('/api/v1/register', { method: 'POST', body: { name, email, password } });
      await loadBootstrap();
      state.selectedShipment = null;
      toast(`Akun terdaftar atas nama ${state.user.name}`, 'ok');
      await navigate('shipper', '/shipper');
    } catch (err) {
      const firstErr = err.errors ? Object.values(err.errors).flat()[0] : null;
      showAuthErr(firstErr || err.message || 'Registrasi gagal.');
    }
  }

  async function quickLogin(email, password = 'password') {
    document.getElementById('auth-error-box')?.classList.add('hidden');
    try {
      await api('/api/v1/login', { method: 'POST', body: { email, password } });
      await loadBootstrap();
      state.selectedShipment = null;
      const target = getRoleDashboardKey(state.user);
      toast(`Masuk sebagai ${state.user.name}`, 'ok');
      await navigate(target, '/' + target);
    } catch (err) {
      showAuthErr(err.message || 'Login cepat gagal.');
    }
  }

  async function handleLogout() {
    try { await api('/api/v1/logout', { method: 'POST' }); } catch (_) {}
    state.user = null;
    state.selectedShipment = null;
    state.utilityDrawer = null;
    await loadBootstrap();
    toast('Sesi berakhir.');
    await navigate('login', '/login');
  }

  /* ------------------------------------------------------------------------
     WORKSPACE MASTER RENDERER
     ------------------------------------------------------------------------ */
  function renderWorkspace() {
    const badge = document.getElementById('ws-role-badge');
    const branchBadge = document.getElementById('ws-branch-badge');
    const userTag = document.getElementById('ws-user-name');
    const btnOut = document.getElementById('ws-btn-logout');
    const btnIn = document.getElementById('ws-btn-login');
    const btnWallet = document.getElementById('ws-btn-wallet');
    const btnNotif = document.getElementById('ws-btn-notif');
    const notifCountEl = document.getElementById('ws-notif-count');

    const roleMeta = {
      admin: { text: 'KONSOL ADMINISTRATOR', cls: 'role-admin' },
      operator: { text: 'MEJA KERJA OPERATOR', cls: 'role-operator' },
      shipper: { text: 'PORTAL PENGIRIM (SHIPPER)', cls: 'role-shipper' },
      buyer: { text: 'PORTAL PENERIMA (BUYER)', cls: 'role-buyer' },
      track: { text: 'PELACAKAN PUBLIK LIVE', cls: 'role-public' },
    }[state.page] || { text: 'MERIDIAN', cls: 'role-public' };

    if (badge) {
      badge.className = `ws-role-badge ${roleMeta.cls}`;
      badge.textContent = roleMeta.text;
    }

    if (branchBadge) {
      if (state.page === 'operator' && state.user?.branch_location) {
        branchBadge.textContent = `Cabang ${state.user.branch_location.code}`;
        branchBadge.classList.remove('hidden');
      } else {
        branchBadge.classList.add('hidden');
      }
    }

    if (state.user) {
      if (userTag) {
        userTag.textContent = state.user.name;
        userTag.classList.remove('hidden');
      }
      btnOut?.classList.remove('hidden');
      btnIn?.classList.add('hidden');
      btnNotif?.classList.remove('hidden');
      if (notifCountEl) notifCountEl.textContent = String(state.unreadNotifications || 0);

      if (btnWallet) {
        if (['shipper', 'buyer'].includes(state.page)) {
          btnWallet.textContent = `Dompet: ${fmtMoney(state.wallet.balance_usd ?? state.user.wallet_balance_usd)}`;
          btnWallet.classList.remove('hidden');
        } else {
          btnWallet.classList.add('hidden');
        }
      }
    } else {
      userTag?.classList.add('hidden');
      btnOut?.classList.add('hidden');
      btnIn?.classList.remove('hidden');
      btnWallet?.classList.add('hidden');
      btnNotif?.classList.add('hidden');
    }

    if (state.page === 'admin') renderAdminDashboard();
    else if (state.page === 'operator') renderOperatorDashboard();
    else if (state.page === 'shipper') renderShipperDashboard();
    else if (state.page === 'buyer') renderBuyerDashboard();
    else renderPublicTrackDashboard();
  }

  function setTopbarMetrics(items) {
    const el = document.getElementById('ws-topbar-metrics');
    if (!el) return;
    el.innerHTML = items
      .map(it => `
        <div class="ws-top-stat">
          <span class="ws-top-stat-val" style="${it.color ? 'color:' + it.color : ''}">${esc(it.val)}</span>
          <span class="ws-top-stat-lbl">${esc(it.lbl)}</span>
        </div>`)
      .join('');
  }

  function setPanelHeader(side, title, sub, actionHtml = '') {
    const t = document.getElementById(`${side}-panel-title`);
    const s = document.getElementById(`${side}-panel-sub`);
    const a = document.getElementById(`${side}-panel-head-action`);
    if (t) t.textContent = title;
    if (s) s.textContent = sub;
    if (a) a.innerHTML = actionHtml;
  }

  /* ========================================================================
     UTILITY DRAWER: NOTIFIKASI IN-APP & DOMPET VIRTUAL ESCROW
     ======================================================================== */
  function toggleUtilityDrawer(mode) {
    const drawer = document.getElementById('ws-utility-drawer');
    if (!drawer) return;
    if (state.utilityDrawer === mode && !drawer.classList.contains('hidden')) {
      closeUtilityDrawer();
      return;
    }
    state.utilityDrawer = mode;
    drawer.classList.remove('hidden');
    renderUtilityDrawer();
  }

  function closeUtilityDrawer() {
    state.utilityDrawer = null;
    document.getElementById('ws-utility-drawer')?.classList.add('hidden');
  }

  function renderUtilityDrawer() {
    const title = document.getElementById('drawer-title');
    const sub = document.getElementById('drawer-sub');
    const body = document.getElementById('drawer-body');
    if (!body) return;

    if (state.utilityDrawer === 'notifications') {
      if (title) title.textContent = `Notifikasi (${state.unreadNotifications} belum dibaca)`;
      if (sub) sub.textContent = 'Pemberitahuan otomatis kargo, kendala & escrow';

      const items = state.notifications || [];
      body.innerHTML = `
        ${items.length > 0 ? `<button type="button" class="btn btn-glass btn-sm" style="width:100%;margin-bottom:12px;" onclick="MeridianApp.markAllNotificationsRead()">Tandai Semua Sudah Dibaca</button>` : ''}
        ${items.length === 0
          ? `<div class="glass-empty"><div class="glass-empty-title">Belum Ada Notifikasi</div><div class="glass-empty-desc">Pemberitahuan perubahan status kargo, delay, dan pencairan Escrow akan muncul di sini.</div></div>`
          : items.map(n => `
              <div class="notif-item ${n.is_read ? '' : 'unread'}" onclick="MeridianApp.clickNotification(${n.id}, '${esc(n.shipment?.tracking_number || '')}')">
                <div style="display:flex;justify-content:space-between;gap:8px;margin-bottom:3px;">
                  <strong style="font-size:12px;color:#fff;">${esc(n.title)}</strong>
                  <span class="mono" style="font-size:10px;color:var(--ink-muted);">${fmtDateTime(n.created_at)}</span>
                </div>
                <div style="font-size:11.5px;color:var(--ink-body);">${esc(n.message)}</div>
              </div>`).join('')}
      `;
    } else if (state.utilityDrawer === 'wallet') {
      const bal = state.wallet.balance_usd ?? state.user?.wallet_balance_usd ?? 0;
      const isBuyer = state.page === 'buyer';
      if (title) title.textContent = 'Dompet Virtual & Mutasi Escrow';
      if (sub) sub.textContent = 'Simulasi saldo dagang untuk testing & demo UAS';

      const actionForm = isBuyer
        ? `
          <div class="glass-section-lbl">Top Up Saldo Simulasi (Buyer)</div>
          <form onsubmit="MeridianApp.handleWalletTopUp(event)" style="display:flex;gap:8px;margin-bottom:14px;">
            <input id="w-topup-amt" type="number" min="100" step="100" value="100000" class="input mono" style="flex:1;" required>
            <button type="submit" class="btn btn-ok btn-sm">Top Up</button>
          </form>`
        : `
          <div class="glass-section-lbl">Tarik Dana ke Rekening Bank (Simulasi)</div>
          <form onsubmit="MeridianApp.handleWalletWithdraw(event)" style="margin-bottom:14px;">
            <div class="form-grid-2">
              <div class="field"><label>Nominal ($ USD)</label><input id="w-withdraw-amt" type="number" min="10" step="10" value="1000" class="input mono" required></div>
              <div class="field"><label>Bank Tujuan</label><input id="w-withdraw-bank" class="input" value="Mandiri #108-00-8821" required></div>
            </div>
            <button type="submit" class="btn btn-accent btn-sm" style="width:100%;">Tarik Dana Simulasi</button>
          </form>`;

      const muts = state.wallet.mutations || [];
      body.innerHTML = `
        <div class="glass-kv" style="margin-bottom:12px;">
          <div class="glass-k">Saldo Dompet Tersedia</div>
          <div class="glass-v mono" style="font-size:20px;color:var(--accent-ok);">${fmtMoney(bal)} USD</div>
        </div>
        ${actionForm}
        <div class="glass-section-lbl">Riwayat Mutasi (${muts.length})</div>
        ${muts.length === 0
          ? `<div style="font-size:12px;color:var(--ink-muted);">Belum ada mutasi saldo tercatat.</div>`
          : muts.map(m => `
              <div class="cargo-item-card" style="cursor:default;">
                <div class="cargo-card-top">
                  <span class="tag-mono">${esc(m.type)}</span>
                  <span class="mono" style="font-size:12px;font-weight:700;color:${m.amount_usd >= 0 ? 'var(--accent-ok)' : 'var(--accent-warn)'};">
                    ${m.amount_usd >= 0 ? '+' : ''}${fmtMoney(m.amount_usd)}
                  </span>
                </div>
                <div style="font-size:11.5px;color:#fff;">${esc(m.description)}</div>
                <div class="cargo-card-meta" style="margin-top:4px;">
                  <span class="mono">${esc(m.reference_code)}</span>
                  <span class="mono">Saldo: ${fmtMoney(m.balance_after_usd)}</span>
                </div>
              </div>`).join('')}
      `;
    }
  }

  /* ========================================================================
     DASHBOARD 1: ADMINISTRATOR (/admin) — LIHAT & EDIT SEMUA DATA
     ======================================================================== */
  function renderAdminDashboard() {
    const m = state.adminOverview?.metrics || {};
    setTopbarMetrics([
      { val: m.total_users ?? state.adminUsers.length, lbl: 'Pengguna' },
      { val: m.total_carriers ?? state.carriers.length, lbl: 'Maskapai' },
      { val: m.total_locations ?? state.locations.length, lbl: 'Pelabuhan' },
      { val: (m.on_time_rate ?? 100) + '%', lbl: 'Tepat Waktu', color: 'var(--accent-ok)' },
    ]);

    setPanelHeader('left', 'Kendali Penuh Administrator', 'Kelola & Edit User, Cabang, Maskapai, Pelabuhan, Audit');
    const left = document.getElementById('left-panel-body');
    const tabs = `
      <div class="glass-tabs">
        <button class="glass-tab ${state.adminTab === 'overview' ? 'active' : ''}" onclick="MeridianApp.setAdminTab('overview')">Ringkasan</button>
        <button class="glass-tab ${state.adminTab === 'shipments' ? 'active' : ''}" onclick="MeridianApp.setAdminTab('shipments')">Kargo (${state.shipments.length})</button>
        <button class="glass-tab ${state.adminTab === 'users' ? 'active' : ''}" onclick="MeridianApp.setAdminTab('users')">User</button>
        <button class="glass-tab ${state.adminTab === 'carriers' ? 'active' : ''}" onclick="MeridianApp.setAdminTab('carriers')">Maskapai</button>
        <button class="glass-tab ${state.adminTab === 'locations' ? 'active' : ''}" onclick="MeridianApp.setAdminTab('locations')">Pelabuhan</button>
        <button class="glass-tab ${state.adminTab === 'audit' ? 'active' : ''}" onclick="MeridianApp.setAdminTab('audit')">Audit</button>
      </div>`;

    if (state.adminTab === 'overview') {
      const byStatus = state.adminOverview?.by_status || {};
      const total = Math.max(1, m.total_shipments || 0);
      const statusRows = [
        { k: 'CREATED', lbl: 'Menunggu Konfirmasi' },
        { k: 'CONFIRMED', lbl: 'Dikonfirmasi' },
        { k: 'IN_TRANSIT', lbl: 'Dalam Pelayaran' },
        { k: 'DELIVERED', lbl: 'Terkirim' },
      ]
        .map(r => {
          const val = byStatus[r.k] || 0;
          const pct = Math.round((val / total) * 100);
          return `
            <div class="bar-row">
              <div class="bar-row-head"><span>${r.lbl}</span><span class="mono">${val}</span></div>
              <div class="bar-track"><div class="bar-fill" style="width:${pct}%"></div></div>
            </div>`;
        })
        .join('');

      left.innerHTML = tabs + `
        <div class="glass-section-lbl">Kesehatan Operasional</div>
        <div class="glass-kv-grid">
          <div class="glass-kv"><div class="glass-k">Total Kargo</div><div class="glass-v mono">${m.total_shipments ?? 0}</div></div>
          <div class="glass-kv"><div class="glass-k">Ketepatan Waktu</div><div class="glass-v mono" style="color:var(--accent-ok);">${m.on_time_rate ?? 100}%</div></div>
          <div class="glass-kv"><div class="glass-k">Terlambat</div><div class="glass-v mono" style="color:var(--accent-danger);">${m.delayed ?? 0}</div></div>
          <div class="glass-kv"><div class="glass-k">Gangguan Aktif</div><div class="glass-v mono" style="color:var(--accent-warn);">${m.open_exceptions ?? 0}</div></div>
        </div>
        <div class="glass-section-lbl">Distribusi Status Pengiriman</div>
        ${statusRows}
        <div class="glass-section-lbl">Aturan Sistem</div>
        <div class="glass-kv-grid">
          <div class="glass-kv"><div class="glass-k">Ambang Delay</div><div class="glass-v mono">&ge; ${state.delayThresholdHours} Jam</div></div>
          <div class="glass-kv"><div class="glass-k">Validasi Kontainer</div><div class="glass-v mono">ISO 6346</div></div>
        </div>
      `;
    } else if (state.adminTab === 'shipments') {
      left.innerHTML = tabs + `
        <div class="glass-section-lbl">Seluruh Pengiriman (${state.shipments.length})</div>
        ${renderShipmentCardList(state.shipments, 'admin', 'Belum ada pengiriman terdaftar di sistem.')}
      `;
    } else if (state.adminTab === 'users') {
      const editUser = state.editingUserId ? state.adminUsers.find(u => u.id === state.editingUserId) : null;
      const formBlock = editUser
        ? `
          <div class="glass-section-lbl">Edit Pengguna: ${esc(editUser.name)}</div>
          <form onsubmit="MeridianApp.handleAdminUpdateUser(event, ${editUser.id})" style="margin-bottom:16px;">
            <div class="field"><label>Nama Lengkap</label><input id="adm-eu-name" class="input" required value="${esc(editUser.name)}"></div>
            <div class="field"><label>Email</label><input id="adm-eu-email" type="email" class="input" required value="${esc(editUser.email)}"></div>
            <div class="form-grid-2">
              <div class="field">
                <label>Peran RBAC</label>
                <select id="adm-eu-role" class="select">
                  ${['admin', 'operator', 'customer', 'surveyor'].map(r => `<option value="${r}" ${editUser.role === r ? 'selected' : ''}>${r.toUpperCase()}</option>`).join('')}
                </select>
              </div>
              <div class="field">
                <label>Cabang Pelabuhan</label>
                <select id="adm-eu-loc" class="select">
                  <option value="">Semua Cabang</option>
                  ${state.locations.map(l => `<option value="${l.id}" ${Number(editUser.location_id) === l.id ? 'selected' : ''}>${esc(l.code)} · ${esc(l.name)}</option>`).join('')}
                </select>
              </div>
            </div>
            <div class="field"><label>Kata Sandi Baru (Opsional)</label><input id="adm-eu-pass" type="password" class="input" minlength="6" placeholder="Kosongkan jika tetap"></div>
            <div style="display:flex;gap:8px;">
              <button type="submit" class="btn btn-accent btn-sm" style="flex:1;">Simpan Perubahan</button>
              <button type="button" class="btn btn-glass btn-sm" onclick="MeridianApp.editAdminUser(null)">Batal</button>
            </div>
          </form>`
        : `
          <div class="glass-section-lbl">Buat Akun Baru (Khusus Admin)</div>
          <form onsubmit="MeridianApp.handleAdminAddUser(event)" style="margin-bottom:16px;">
            <div class="field"><label>Nama Lengkap</label><input id="adm-u-name" class="input" required placeholder="Nama Pekerja / Perusahaan"></div>
            <div class="field"><label>Email</label><input id="adm-u-email" type="email" class="input" required placeholder="operator.us@meridian.id"></div>
            <div class="form-grid-2">
              <div class="field"><label>Kata Sandi</label><input id="adm-u-pass" type="password" class="input" required minlength="6" placeholder="Min 6 kar"></div>
              <div class="field">
                <label>Peran RBAC</label>
                <select id="adm-u-role" class="select">
                  <option value="operator">OPERATOR</option>
                  <option value="customer">CUSTOMER</option>
                  <option value="admin">ADMIN</option>
                  <option value="surveyor">SURVEYOR</option>
                </select>
              </div>
            </div>
            <div class="field">
              <label>Penugasan Cabang Pelabuhan (Untuk Operator)</label>
              <select id="adm-u-loc" class="select">
                <option value="">Semua Cabang / Global</option>
                ${state.locations.map(l => `<option value="${l.id}">${esc(l.code)} · ${esc(l.name)}</option>`).join('')}
              </select>
            </div>
            <button type="submit" class="btn btn-accent btn-sm" style="width:100%;">Daftarkan Akun</button>
          </form>`;

      left.innerHTML = tabs + formBlock + `
        <div class="glass-section-lbl">Daftar Akun Terdaftar (${state.adminUsers.length})</div>
        ${state.adminUsers.map(u => `
          <div class="cargo-item-card" style="cursor:default;">
            <div class="cargo-card-top">
              <span style="font-weight:700;color:#fff;font-size:12.5px;">${esc(u.name)}</span>
              <button type="button" class="btn btn-glass btn-sm" onclick="MeridianApp.editAdminUser(${u.id})">Edit</button>
            </div>
            <div class="mono" style="font-size:11px;color:var(--ink-muted);">${esc(u.email)}</div>
            <div class="cargo-card-meta" style="margin-top:5px;">
              <span class="tag-mono">${esc(u.role.toUpperCase())}</span>
              <span class="mono">${u.branch_location ? 'Cabang: ' + esc(u.branch_location.code) : 'Global'}</span>
            </div>
          </div>`).join('')}
      `;
    } else if (state.adminTab === 'carriers') {
      const editCar = state.editingCarrierId ? state.adminCarriers.find(c => c.id === state.editingCarrierId) : null;
      const carForm = editCar
        ? `
          <div class="glass-section-lbl">Edit Maskapai: ${esc(editCar.code)}</div>
          <form onsubmit="MeridianApp.handleAdminUpdateCarrier(event, ${editCar.id})" style="margin-bottom:16px;">
            <div class="form-grid-2">
              <div class="field"><label>Kode (SCAC)</label><input id="adm-ec-code" class="input mono" required value="${esc(editCar.code)}"></div>
              <div class="field"><label>Moda</label><select id="adm-ec-mode" class="select">${['SEA', 'AIR', 'LAND'].map(m => `<option value="${m}" ${editCar.transport_mode === m ? 'selected' : ''}>${m}</option>`).join('')}</select></div>
            </div>
            <div class="field"><label>Nama Maskapai</label><input id="adm-ec-name" class="input" required value="${esc(editCar.name)}"></div>
            <div class="field"><label>Email</label><input id="adm-ec-email" type="email" class="input" value="${esc(editCar.contact_email || '')}"></div>
            <div style="display:flex;gap:8px;">
              <button type="submit" class="btn btn-accent btn-sm" style="flex:1;">Simpan Perubahan</button>
              <button type="button" class="btn btn-glass btn-sm" onclick="MeridianApp.editAdminCarrier(null)">Batal</button>
            </div>
          </form>`
        : `
          <div class="glass-section-lbl">Tambah Maskapai Pelayaran</div>
          <form onsubmit="MeridianApp.handleAdminAddCarrier(event)" style="margin-bottom:16px;">
            <div class="form-grid-2">
              <div class="field"><label>Kode (SCAC)</label><input id="adm-c-code" class="input mono" required maxlength="10" placeholder="ZIMU"></div>
              <div class="field"><label>Moda</label><select id="adm-c-mode" class="select"><option value="SEA">SEA</option><option value="AIR">AIR</option><option value="LAND">LAND</option></select></div>
            </div>
            <div class="field"><label>Nama Maskapai</label><input id="adm-c-name" class="input" required placeholder="ZIM Integrated Shipping"></div>
            <div class="field"><label>Email</label><input id="adm-c-email" type="email" class="input" placeholder="ops@carrier.com"></div>
            <button type="submit" class="btn btn-accent btn-sm" style="width:100%;">Simpan Maskapai</button>
          </form>`;

      left.innerHTML = tabs + carForm + `
        <div class="glass-section-lbl">Master Maskapai (${state.adminCarriers.length})</div>
        ${state.adminCarriers.map(c => `
          <div class="cargo-item-card" style="cursor:default;">
            <div class="cargo-card-top">
              <span class="cargo-card-id">${esc(c.code)} · ${esc(c.transport_mode)}</span>
              <div style="display:flex;gap:6px;">
                <button type="button" class="btn btn-glass btn-sm" onclick="MeridianApp.editAdminCarrier(${c.id})">Edit</button>
                <button type="button" class="btn ${c.is_active ? 'btn-ok' : 'btn-danger'} btn-sm" onclick="MeridianApp.handleAdminToggleCarrier(${c.id})">${c.is_active ? 'Aktif' : 'Nonaktif'}</button>
              </div>
            </div>
            <div class="cargo-card-name">${esc(c.name)}</div>
            <div class="cargo-card-meta"><span>${c.shipments_count || 0} pengiriman</span><span>${esc(c.contact_email || '—')}</span></div>
          </div>`).join('')}
      `;
    } else if (state.adminTab === 'locations') {
      const editLoc = state.editingLocationId ? state.locations.find(l => l.id === state.editingLocationId) : null;
      const locForm = editLoc
        ? `
          <div class="glass-section-lbl">Edit Pelabuhan: ${esc(editLoc.code)}</div>
          <form onsubmit="MeridianApp.handleAdminUpdateLocation(event, ${editLoc.id})" style="margin-bottom:16px;">
            <div class="form-grid-2">
              <div class="field"><label>UN/LOCODE</label><input id="adm-el-code" class="input mono" required value="${esc(editLoc.code)}"></div>
              <div class="field"><label>Tipe</label><select id="adm-el-type" class="select">${['PORT', 'HUB', 'WAREHOUSE', 'AIRPORT'].map(t => `<option value="${t}" ${editLoc.type === t ? 'selected' : ''}>${t}</option>`).join('')}</select></div>
            </div>
            <div class="field"><label>Nama Pelabuhan</label><input id="adm-el-name" class="input" required value="${esc(editLoc.name)}"></div>
            <div class="field"><label>Negara</label><input id="adm-el-country" class="input" required value="${esc(editLoc.country)}"></div>
            <div class="form-grid-2">
              <div class="field"><label>Latitude</label><input id="adm-el-lat" type="number" step="0.0001" class="input mono" required value="${editLoc.latitude}"></div>
              <div class="field"><label>Longitude</label><input id="adm-el-lng" type="number" step="0.0001" class="input mono" required value="${editLoc.longitude}"></div>
            </div>
            <div style="display:flex;gap:8px;">
              <button type="submit" class="btn btn-accent btn-sm" style="flex:1;">Simpan Perubahan</button>
              <button type="button" class="btn btn-glass btn-sm" onclick="MeridianApp.editAdminLocation(null)">Batal</button>
            </div>
          </form>`
        : `
          <div class="glass-section-lbl">Tambah Pelabuhan / Hub ke Globe 3D</div>
          <form onsubmit="MeridianApp.handleAdminAddLocation(event)" style="margin-bottom:16px;">
            <div class="form-grid-2">
              <div class="field"><label>UN/LOCODE</label><input id="adm-l-code" class="input mono" required maxlength="10" placeholder="USLAX"></div>
              <div class="field"><label>Tipe</label><select id="adm-l-type" class="select"><option value="PORT">PORT</option><option value="HUB">HUB</option><option value="WAREHOUSE">WAREHOUSE</option><option value="AIRPORT">AIRPORT</option></select></div>
            </div>
            <div class="field"><label>Nama Pelabuhan</label><input id="adm-l-name" class="input" required placeholder="Port of Los Angeles"></div>
            <div class="field"><label>Negara</label><input id="adm-l-country" class="input" required placeholder="United States"></div>
            <div class="form-grid-2">
              <div class="field"><label>Latitude</label><input id="adm-l-lat" type="number" step="0.0001" class="input mono" required placeholder="33.7405"></div>
              <div class="field"><label>Longitude</label><input id="adm-l-lng" type="number" step="0.0001" class="input mono" required placeholder="-118.2720"></div>
            </div>
            <button type="submit" class="btn btn-accent btn-sm" style="width:100%;">Simpan ke Globe 3D</button>
          </form>`;

      left.innerHTML = tabs + locForm + `
        <div class="glass-section-lbl">Pelabuhan Terhubung (${state.locations.length})</div>
        ${state.locations.map(l => `
          <div class="cargo-item-card" onclick="MeridianGlobe.zoomToPort(${l.latitude}, ${l.longitude}, '${esc(l.name)}', '${esc(l.code)}')">
            <div class="cargo-card-top">
              <span class="cargo-card-id">${esc(l.code)}</span>
              <button type="button" class="btn btn-glass btn-sm" onclick="event.stopPropagation(); MeridianApp.editAdminLocation(${l.id})">Edit</button>
            </div>
            <div class="cargo-card-name">${esc(l.name)}</div>
            <div class="cargo-card-meta"><span>${esc(l.country)} · ${esc(l.type)}</span><span class="mono">${Number(l.latitude).toFixed(2)}, ${Number(l.longitude).toFixed(2)}</span></div>
          </div>`).join('')}
      `;
    } else if (state.adminTab === 'audit') {
      left.innerHTML = tabs + `
        <div class="glass-section-lbl">Jejak Audit Keamanan (${state.auditLogs.length})</div>
        ${state.auditLogs.length === 0
          ? `<div class="glass-empty"><div class="glass-empty-title">Belum ada log audit</div><div class="glass-empty-desc">Semua aktivitas sistem dicatat permanen di sini.</div></div>`
          : state.auditLogs.map(l => `
              <div class="cargo-item-card" style="cursor:default;">
                <div class="cargo-card-top">
                  <span class="tag-mono">${esc(l.action)}</span>
                  <span class="mono" style="font-size:10px;color:var(--ink-muted);">${fmtDateTime(l.created_at)}</span>
                </div>
                <div style="font-size:12px;font-weight:600;color:#fff;">${esc(l.user?.name || 'Sistem')}</div>
                <div class="mono" style="font-size:10.5px;color:var(--ink-muted);">${esc((l.model_type || '').split('\\').pop())} #${esc(l.model_id)}</div>
              </div>`).join('')}
      `;
    }

    if (state.selectedShipment) {
      renderRightShipmentInspector(state.selectedShipment, 'admin');
    } else {
      setPanelHeader('right', 'Pengawasan & Edit Kargo', `${state.shipments.length} pengiriman terdaftar di sistem`);
      document.getElementById('right-panel-body').innerHTML = renderShipmentCardList(
        state.shipments,
        'admin',
        'Belum ada pengiriman di sistem. Operator yang dibuat Admin dapat mulai menginput kargo.'
      );
    }
  }

  /* ========================================================================
     DASHBOARD 2: OPERATOR LOGISTIK (/operator) — MEJA KERJA LEGA PEKERJA
     ======================================================================== */
  function renderOperatorDashboard() {
    setTopbarMetrics([
      { val: state.stats.total_shipments ?? 0, lbl: 'Total Armada' },
      { val: state.stats.in_transit ?? 0, lbl: 'Berlayar', color: 'var(--accent-sky)' },
      { val: state.stats.pending_confirmation ?? 0, lbl: 'Menunggu Shipper', color: 'var(--accent-warn)' },
      { val: state.stats.open_exceptions ?? 0, lbl: 'Gangguan Aktif', color: 'var(--accent-danger)' },
    ]);

    const branchInfo = state.user?.branch_location
      ? `Cabang Tugas: ${state.user.branch_location.name} (${state.user.branch_location.code})`
      : 'Semua Pelabuhan Internasional';

    setPanelHeader('left', 'Meja Kerja Operator Lapangan', branchInfo);
    const left = document.getElementById('left-panel-body');

    const tabs = `
      <div class="glass-tabs">
        <button class="glass-tab ${state.operatorTab === 'fleet' ? 'active' : ''}" onclick="MeridianApp.setOperatorTab('fleet')">Antrean Kargo (${state.shipments.length})</button>
        <button class="glass-tab ${state.operatorTab === 'create' ? 'active' : ''}" onclick="MeridianApp.setOperatorTab('create')">Input Barang Baru</button>
        <button class="glass-tab ${state.operatorTab === 'ops' ? 'active' : ''}" onclick="MeridianApp.setOperatorTab('ops')">Checkpoint &amp; Kendala</button>
      </div>`;

    if (state.operatorTab === 'fleet') {
      const branchToggle = state.user?.location_id
        ? `<div style="display:flex;gap:6px;margin-bottom:10px;">
             <button type="button" class="btn ${!state.operatorBranchOnly ? 'btn-accent' : 'btn-glass'} btn-sm" style="flex:1;" onclick="MeridianApp.setOperatorBranchFilter(false)">Semua Cabang</button>
             <button type="button" class="btn ${state.operatorBranchOnly ? 'btn-accent' : 'btn-glass'} btn-sm" style="flex:1;" onclick="MeridianApp.setOperatorBranchFilter(true)">Khusus Cabang ${esc(state.user.branch_location?.code || '')}</button>
           </div>`
        : '';

      left.innerHTML = tabs + branchToggle + `
        <div class="glass-search">
          <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="9" r="6"/><path d="M13.6 13.6L17.5 17.5" stroke-linecap="round"/></svg>
          <input type="text" value="${esc(state.searchQuery)}" placeholder="Cari nomor resi, kontainer ISO 6346, komoditas..." oninput="MeridianApp.handleSearchInput(this.value)">
        </div>
        ${renderShipmentCardList(
          state.shipments,
          'operator',
          'Database pengiriman masih kosong dan siap untuk diinput manual oleh pekerja.',
          `<button type="button" class="btn btn-accent btn-sm" onclick="MeridianApp.setOperatorTab('create')">+ Input Barang Pertama</button>`
        )}
      `;
    } else if (state.operatorTab === 'create') {
      const today = new Date();
      const dep = new Date(today.getTime() + 86400000).toISOString().slice(0, 10);
      const arr = new Date(today.getTime() + 18 * 86400000).toISOString().slice(0, 10);
      const defaultIso = generateIso6346('MSKU');
      const rtm = state.locations.find(l => l.code === 'NLRTM');
      const defaultOriginId = state.user?.location_id || state.locations[0]?.id;
      const defaultBuyer = state.customers.find(c => c.email.includes('buyer')) || state.customers[1] || state.customers[0];

      left.innerHTML = tabs + `
        <form id="op-create-form" onsubmit="MeridianApp.handleNewShipmentSubmit(event)">
          <div class="glass-section-lbl">1. Penimbangan &amp; Spesifikasi Fisik Barang</div>
          <div class="form-grid-2">
            <div class="field">
              <label>Nama Komoditas / Barang <span class="req">*</span></label>
              <input id="ns-item-name" class="input" required placeholder="Cth: Kopi Arabika Gayo Grade 1">
            </div>
            <div class="field">
              <label>Kategori Komoditas</label>
              <select id="ns-category" class="select">
                <option value="Hasil Bumi & Kopi">Hasil Bumi &amp; Kopi</option>
                <option value="Rempah-Rempah">Rempah-Rempah</option>
                <option value="Kakao & Olahan">Kakao &amp; Olahan</option>
                <option value="Tekstil & Garmen">Tekstil &amp; Garmen</option>
                <option value="Mesin & Manufaktur">Mesin &amp; Manufaktur</option>
                <option value="Elektronik">Elektronik</option>
              </select>
            </div>
          </div>

          <div class="form-grid-3">
            <div class="field"><label>Jumlah Koli/Unit <span class="req">*</span></label><input id="ns-quantity" type="number" min="1" value="500" class="input mono" required></div>
            <div class="field"><label>Berat Bersih (kg) <span class="req">*</span></label><input id="ns-weight" type="number" step="0.01" min="1" class="input mono" required placeholder="18500"></div>
            <div class="field"><label>Volume (CBM)</label><input id="ns-volume" type="number" step="0.1" min="0.1" class="input mono" placeholder="64"></div>
          </div>

          <div class="form-grid-2">
            <div class="field">
              <label>Nomor Kontainer (ISO 6346)</label>
              <div style="display:flex;gap:6px;">
                <input id="ns-container" class="input mono" style="flex:1;min-width:0;" value="${defaultIso}" oninput="MeridianApp.handleIsoLiveInput()">
                <button type="button" class="btn btn-glass btn-sm" onclick="MeridianApp.fillGeneratedIso()">Auto</button>
              </div>
              <span class="field-hint" id="ns-iso-hint" style="color:var(--accent-ok);">Valid ISO 6346 (${defaultIso})</span>
            </div>
            <div class="field">
              <label>Nilai Kontrak Escrow ($ USD)</label>
              <input id="ns-contract-usd" type="number" step="100" min="0" value="125000" class="input mono" placeholder="125000">
              <span class="field-hint">Isi 0 jika tanpa simulasi Escrow</span>
            </div>
          </div>

          <div class="field">
            <label>Kondisi Fisik &amp; Segel</label>
            <select id="ns-condition" class="select">
              <option value="BAIK & SEGEL UTUH">Baik &amp; Segel Utuh</option>
              <option value="STANDAR EKSPOR">Standar Ekspor</option>
              <option value="REEFER SUHU TERKONTROL">Reefer Suhu Terkontrol</option>
            </select>
          </div>

          <div class="glass-section-lbl">2. Pihak Transaksi &amp; Rute Pelayaran</div>
          <div class="form-grid-2">
            <div class="field">
              <label>Pengirim (Shipper) <span class="req">*</span></label>
              <select id="ns-customer" class="select" required>
                ${state.customers.map(c => `<option value="${c.id}">${esc(c.name)}</option>`).join('')}
              </select>
            </div>
            <div class="field">
              <label>Penerima (Buyer)</label>
              <select id="ns-buyer" class="select">
                ${state.customers.map(c => `<option value="${c.id}" ${defaultBuyer && c.id === defaultBuyer.id ? 'selected' : ''}>${esc(c.name)}</option>`).join('')}
              </select>
            </div>
          </div>

          <div class="form-grid-3">
            <div class="field">
              <label>Maskapai <span class="req">*</span></label>
              <select id="ns-carrier" class="select" required>
                ${state.carriers.map(c => `<option value="${c.id}">${esc(c.code)} · ${esc(c.name)}</option>`).join('')}
              </select>
            </div>
            <div class="field">
              <label>Pelabuhan Asal <span class="req">*</span></label>
              <select id="ns-origin" class="select" required>
                ${state.locations.map(l => `<option value="${l.id}" ${Number(defaultOriginId) === l.id ? 'selected' : ''}>${esc(l.code)} · ${esc(l.name)}</option>`).join('')}
              </select>
            </div>
            <div class="field">
              <label>Pelabuhan Tujuan <span class="req">*</span></label>
              <select id="ns-dest" class="select" required>
                ${state.locations.map(l => `<option value="${l.id}" ${rtm && l.id === rtm.id ? 'selected' : ''}>${esc(l.code)} · ${esc(l.name)}</option>`).join('')}
              </select>
            </div>
          </div>

          <div class="form-grid-2">
            <div class="field"><label>Jadwal Berangkat (ETD) <span class="req">*</span></label><input id="ns-departure" type="date" value="${dep}" class="input mono" required></div>
            <div class="field"><label>Estimasi Tiba (ETA) <span class="req">*</span></label><input id="ns-arrival" type="date" value="${arr}" class="input mono" required></div>
          </div>
          <div class="field"><label>Catatan Dermaga / B/L</label><textarea id="ns-notes" class="textarea" rows="2" placeholder="Opsional: nomor B/L, hasil timbang VGM, atau sertifikat karantina"></textarea></div>

          <button type="submit" id="btn-submit-shipment" class="btn btn-primary" style="width:100%;">
            Simpan Manifest &amp; Kirim ke Shipper
          </button>
        </form>
      `;
    } else if (state.operatorTab === 'ops') {
      const activeList = state.shipments.filter(s => s.status !== 'DELIVERED' && s.status !== 'CANCELLED');
      const selId = state.selectedShipment?.id || (activeList[0]?.id ?? '');

      if (activeList.length === 0) {
        left.innerHTML = tabs + `<div class="glass-empty"><div class="glass-empty-title">Belum ada kargo aktif</div><div class="glass-empty-desc">Daftarkan pengiriman di tab Input Barang Baru untuk mencatat perjalanan, kendala, atau uji simulasi waktu.</div></div>`;
      } else {
        left.innerHTML = tabs + `
          <div class="form-grid-2" style="gap:16px;align-items:start;">
            <div>
              <div class="glass-section-lbl">Catat Checkpoint Perjalanan</div>
              <form onsubmit="MeridianApp.handleCheckpointSubmit(event)">
                <div class="field">
                  <label>Pilih Kargo</label>
                  <select id="cp-shipment-id" class="select" required>
                    ${activeList.map(s => `<option value="${s.id}" ${s.id === selId ? 'selected' : ''}>${esc(s.tracking_number)} · ${esc(s.cargo_description).slice(0, 24)}</option>`).join('')}
                  </select>
                </div>
                <div class="field">
                  <label>Milestone</label>
                  <select id="cp-event-type" class="select" required>
                    <option value="DEPARTURE">DEPARTURE (Berangkat)</option>
                    <option value="IN_TRANSIT_CHECKPOINT">IN_TRANSIT_CHECKPOINT (Transit)</option>
                    <option value="CUSTOMS_INSPECTION">CUSTOMS_INSPECTION (Bea Cukai)</option>
                    <option value="ARRIVAL">ARRIVAL (Tiba di Pelabuhan)</option>
                    <option value="OUT_FOR_DELIVERY">OUT_FOR_DELIVERY (Antar Gudang)</option>
                    <option value="DELIVERED">DELIVERED (Selesai)</option>
                  </select>
                </div>
                <div class="field">
                  <label>Lokasi Pelabuhan</label>
                  <select id="cp-location" class="select" required>
                    ${state.locations.map(l => `<option value="${l.id}">${esc(l.code)} · ${esc(l.name)}</option>`).join('')}
                  </select>
                </div>
                <div class="field"><label>Keterangan</label><textarea id="cp-remarks" class="textarea" rows="2" placeholder="Catatan checkpoint..."></textarea></div>
                <button type="submit" class="btn btn-accent btn-sm" style="width:100%;">Simpan Checkpoint</button>
              </form>
            </div>

            <div>
              <div class="glass-section-lbl">Laporkan Gangguan (Exception)</div>
              <form onsubmit="MeridianApp.handleExceptionSubmit(event)">
                <div class="field">
                  <label>Kargo Terdampak</label>
                  <select id="ex-shipment-id" class="select" required>
                    ${activeList.map(s => `<option value="${s.id}" ${s.id === selId ? 'selected' : ''}>${esc(s.tracking_number)} · ${esc(s.cargo_description).slice(0, 24)}</option>`).join('')}
                  </select>
                </div>
                <div class="field">
                  <label>Jenis Gangguan</label>
                  <select id="ex-type" class="select" required>
                    <option value="CUSTOMS_HOLD">CUSTOMS_HOLD (Tahan Bea Cukai)</option>
                    <option value="WEATHER_DELAY">WEATHER_DELAY (Cuaca Buruk)</option>
                    <option value="PORT_CONGESTION">PORT_CONGESTION (Antrean Dermaga)</option>
                    <option value="DAMAGED">DAMAGED (Kerusakan Kargo)</option>
                    <option value="MISROUTED">MISROUTED (Salah Rute)</option>
                    <option value="DOCUMENTATION_ISSUE">DOCUMENTATION_ISSUE (Masalah Dokumen)</option>
                  </select>
                </div>
                <div class="form-grid-2">
                  <div class="field"><label>Keparahan</label><select id="ex-severity" class="select"><option value="LOW">LOW</option><option value="MEDIUM" selected>MEDIUM</option><option value="HIGH">HIGH</option><option value="CRITICAL">CRITICAL</option></select></div>
                  <div class="field"><label>Dampak (Jam)</label><input id="ex-hours" type="number" min="1" max="720" value="18" class="input mono" required></div>
                </div>
                <div class="field"><label>Deskripsi Kendala</label><textarea id="ex-desc" class="textarea" rows="2" required placeholder="Alasan penahanan atau keterlambatan..."></textarea></div>
                <button type="submit" class="btn btn-danger btn-sm" style="width:100%;">Catat Kendala &amp; Update ETA</button>
              </form>
            </div>
          </div>

          ${state.demoToolsEnabled ? `
            <div class="glass-section-lbl" style="margin-top:18px;">Simulasi Lompat Waktu (Demo &amp; Testing UAS)</div>
            <div class="glass-kv">
              <div style="font-size:11.5px;color:var(--ink-body);margin-bottom:8px;">
                Majukan waktu jadwal pelayaran secara instan untuk menguji deteksi keterlambatan (ambang ${state.delayThresholdHours} jam):
              </div>
              <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <select id="tt-shipment-id" class="select" style="flex:1;min-width:170px;">
                  ${activeList.map(s => `<option value="${s.id}" ${s.id === selId ? 'selected' : ''}>${esc(s.tracking_number)}</option>`).join('')}
                </select>
                <button type="button" class="btn btn-glass btn-sm mono" onclick="MeridianApp.runTimeTravel(6)">+6 Jam</button>
                <button type="button" class="btn btn-glass btn-sm mono" onclick="MeridianApp.runTimeTravel(12)">+12 Jam</button>
                <button type="button" class="btn btn-glass btn-sm mono" onclick="MeridianApp.runTimeTravel(24)">+24 Jam</button>
                <button type="button" class="btn btn-glass btn-sm mono" onclick="MeridianApp.runTimeTravel(72)">+3 Hari</button>
              </div>
            </div>` : ''}
        `;
      }
    }

    if (state.selectedShipment) {
      renderRightShipmentInspector(state.selectedShipment, 'operator');
    } else {
      renderPortExplorerRightPanel('Verifikasi Pelabuhan & Rute', 'Klik pelabuhan di bawah untuk mengecek lokasi satelit pada Bola Bumi 3D');
    }
  }

  /* ========================================================================
     DASHBOARD 3: SHIPPER / PENGIRIM (/shipper) — ESCROW & KONFIRMASI
     ======================================================================== */
  function renderShipperDashboard() {
    const pending = state.shipments.filter(s => ['CREATED', 'BOOKED'].includes(s.status));
    const sailing = state.shipments.filter(s => ['CONFIRMED', 'PICKED_UP', 'IN_TRANSIT'].includes(s.status));
    const delivered = state.shipments.filter(s => s.status === 'DELIVERED');

    setTopbarMetrics([
      { val: state.shipments.length, lbl: 'Kiriman Saya' },
      { val: pending.length, lbl: 'Perlu Persetujuan', color: 'var(--accent-warn)' },
      { val: sailing.length, lbl: 'Berlayar', color: 'var(--accent-sky)' },
      { val: delivered.length, lbl: 'Selesai', color: 'var(--accent-ok)' },
    ]);

    setPanelHeader('left', 'Pengiriman Ekspor Saya', 'Cek jaminan Escrow, konfirmasi barang & lacak di Globe 3D');
    const left = document.getElementById('left-panel-body');

    const tabs = `
      <div class="glass-tabs">
        <button class="glass-tab ${state.shipperTab === 'pending' ? 'active' : ''}" onclick="MeridianApp.setShipperTab('pending')">Perlu Konfirmasi (${pending.length})</button>
        <button class="glass-tab ${state.shipperTab === 'all' ? 'active' : ''}" onclick="MeridianApp.setShipperTab('all')">Semua Kiriman (${state.shipments.length})</button>
      </div>`;

    const list = state.shipperTab === 'pending' ? pending : state.shipments;
    const emptyMsg = state.shipperTab === 'pending'
      ? 'Tidak ada kiriman baru yang menunggu konfirmasi Anda.'
      : 'Belum ada kiriman atas nama akun Anda. Setelah Operator mendaftarkan manifest, kargo akan tampil di sini.';

    left.innerHTML = tabs + renderShipmentCardList(list, 'shipper', emptyMsg);

    if (state.selectedShipment) {
      renderRightShipmentInspector(state.selectedShipment, 'shipper');
    } else {
      renderPortExplorerRightPanel('Panduan Pengirim (Shipper)', 'Klik kiriman di kiri untuk melihat status jaminan Escrow & rute pelayaran di Bola Bumi 3D');
    }
  }

  /* ========================================================================
     DASHBOARD 4: BUYER / PENERIMA (/buyer) — DEPOSIT ESCROW & SERAH TERIMA
     ======================================================================== */
  function renderBuyerDashboard() {
    const incoming = state.shipments.filter(s => s.status !== 'DELIVERED' && s.status !== 'CANCELLED');
    const received = state.shipments.filter(s => s.status === 'DELIVERED');
    const delayed = incoming.filter(s => s.is_delayed);

    setTopbarMetrics([
      { val: state.shipments.length, lbl: 'Total Pesanan' },
      { val: incoming.length, lbl: 'Dalam Perjalanan', color: 'var(--accent-sky)' },
      { val: received.length, lbl: 'Diterima', color: 'var(--accent-ok)' },
      { val: delayed.length, lbl: 'Terlambat', color: 'var(--accent-danger)' },
    ]);

    setPanelHeader('left', 'Pesanan Menuju Anda', 'Setor Escrow, pantau kapal & konfirmasi penerimaan barang');
    const left = document.getElementById('left-panel-body');

    const tabs = `
      <div class="glass-tabs">
        <button class="glass-tab ${state.buyerTab === 'incoming' ? 'active' : ''}" onclick="MeridianApp.setBuyerTab('incoming')">Dalam Perjalanan (${incoming.length})</button>
        <button class="glass-tab ${state.buyerTab === 'delivered' ? 'active' : ''}" onclick="MeridianApp.setBuyerTab('delivered')">Diterima (${received.length})</button>
      </div>`;

    const list = state.buyerTab === 'incoming' ? incoming : received;
    const emptyMsg = state.buyerTab === 'incoming'
      ? 'Tidak ada pesanan yang sedang menuju pelabuhan Anda saat ini.'
      : 'Belum ada riwayat pesanan yang telah diterima.';

    left.innerHTML = tabs + renderShipmentCardList(list, 'buyer', emptyMsg);

    if (state.selectedShipment) {
      renderRightShipmentInspector(state.selectedShipment, 'buyer');
    } else {
      renderPortExplorerRightPanel('Pelabuhan Tujuan Anda', 'Klik pesanan di kiri untuk menyetor Escrow, melihat cuaca tujuan, dan konfirmasi terima');
    }
  }

  /* ========================================================================
     PUBLIC TRACKING WORKSPACE (/track/{number})
     ======================================================================== */
  function renderPublicTrackDashboard() {
    setTopbarMetrics([
      { val: state.locations.length, lbl: 'Pelabuhan Global' },
      { val: state.carriers.length, lbl: 'Maskapai' },
    ]);

    setPanelHeader('left', 'Pelacakan Publik', 'Cari nomor resi atau kontainer ISO 6346');
    const left = document.getElementById('left-panel-body');
    left.innerHTML = `
      <form onsubmit="MeridianApp.handlePublicTrackSubmit(event)" style="margin-bottom:16px;">
        <div class="field"><label>Nomor Resi / Kontainer</label><input id="public-track-side-input" class="input mono" value="${esc(state.selectedShipment?.tracking_number || '')}" placeholder="MRD-... atau CSQU3054383"></div>
        <button type="submit" class="btn btn-accent btn-sm" style="width:100%;">Cari di Bola Bumi</button>
      </form>
      <div class="glass-section-lbl">Jelajahi Pelabuhan Dunia</div>
      ${state.locations.map(l => `
        <div class="cargo-item-card" onclick="MeridianGlobe.zoomToPort(${l.latitude}, ${l.longitude}, '${esc(l.name)}', '${esc(l.code)}')">
          <div class="cargo-card-top"><span class="cargo-card-id">${esc(l.code)}</span><span class="tag-mono">${esc(l.country)}</span></div>
          <div class="cargo-card-name">${esc(l.name)}</div>
        </div>`).join('')}
    `;

    if (state.selectedShipment) {
      renderRightShipmentInspector(state.selectedShipment, 'track');
    } else {
      renderPortExplorerRightPanel('Hasil Pelacakan', 'Masukkan nomor resi atau klik pelabuhan pada bola bumi');
    }
  }

  /* ------------------------------------------------------------------------
     SHARED COMPONENTS: CARD LIST + RIGHT INSPECTOR (WITH ESCROW & EDITING)
     ------------------------------------------------------------------------ */
  function renderShipmentCardList(list, role, emptyDesc, extraBtnHtml = '') {
    if (!list || list.length === 0) {
      return `
        <div class="glass-empty">
          <div class="glass-empty-title">Belum Ada Data</div>
          <div class="glass-empty-desc">${esc(emptyDesc)}</div>
          ${extraBtnHtml}
        </div>`;
    }

    const selId = state.selectedShipment?.id;
    return list.map(s => {
      const o = s.origin_location;
      const d = s.destination_location;
      const isSel = selId === s.id;
      const escInfo = s.escrow
        ? `<div class="mono" style="font-size:10.5px;color:${s.escrow.status === 'RELEASED' ? 'var(--accent-ok)' : (s.escrow.status === 'PENDING_DEPOSIT' ? 'var(--accent-warn)' : 'var(--accent-sky)')};margin-top:4px;">Escrow ${fmtMoney(s.escrow.amount_usd)} · ${esc(escrowStatusLabel(s.escrow.status))}</div>`
        : '';

      let roleAction = '';
      if (role === 'shipper' && ['CREATED', 'BOOKED'].includes(s.status)) {
        roleAction = `<div class="cargo-card-actions"><button type="button" class="btn btn-ok btn-sm" style="width:100%;" onclick="event.stopPropagation(); MeridianApp.confirmShipper(${s.id})">Setujui &amp; Konfirmasi Kargo</button></div>`;
      } else if (role === 'buyer') {
        const btns = [];
        if (s.escrow && s.escrow.status === 'PENDING_DEPOSIT') {
          btns.push(`<button type="button" class="btn btn-accent btn-sm" style="flex:1;" onclick="event.stopPropagation(); MeridianApp.depositEscrow(${s.id})">Setor Escrow</button>`);
        }
        if (['CONFIRMED', 'IN_TRANSIT', 'PICKED_UP'].includes(s.status)) {
          btns.push(`<button type="button" class="btn btn-ok btn-sm" style="flex:1;" onclick="event.stopPropagation(); MeridianApp.confirmBuyer(${s.id})">Terima Barang</button>`);
        }
        if (btns.length > 0) roleAction = `<div class="cargo-card-actions">${btns.join('')}</div>`;
      }

      return `
        <div class="cargo-item-card ${isSel ? 'selected' : ''}" onclick="MeridianApp.selectShipment('${esc(s.tracking_number)}')">
          <div class="cargo-card-top"><span class="cargo-card-id">${esc(s.tracking_number)}</span>${statusPill(s)}</div>
          <div class="cargo-card-name">${esc(s.cargo_description)}</div>
          <div class="cargo-card-route">${o ? esc(o.code) : '—'} &rarr; ${d ? esc(d.code) : '—'} · ${esc(s.container_number || 'Tanpa Kontainer')}</div>
          <div class="cargo-card-meta"><span>${esc(s.carrier?.name || 'Maskapai')}</span><span class="mono">ETA ${fmtDate(s.calculated_eta || s.expected_arrival)}</span></div>
          ${escInfo}
          ${roleAction}
        </div>`;
    }).join('');
  }

  function renderPortExplorerRightPanel(title, sub) {
    setPanelHeader('right', title, sub);
    document.getElementById('right-panel-body').innerHTML = `
      <div class="glass-section-lbl">Jelajahi Terminal di Bola Bumi</div>
      <p style="font-size:12px;color:var(--ink-muted);margin-bottom:12px;">
        Anda bebas memutar dan memperbesar bola bumi ke lokasi mana saja. Klik pelabuhan di bawah untuk langsung zoom ke dermaga satelitnya.
      </p>
      ${state.locations.map(l => `
        <div class="cargo-item-card" onclick="MeridianGlobe.zoomToPort(${l.latitude}, ${l.longitude}, '${esc(l.name)}', '${esc(l.code)}')">
          <div class="cargo-card-top"><span class="cargo-card-id">${esc(l.code)}</span><span class="tag-mono">${esc(l.country)}</span></div>
          <div class="cargo-card-name">${esc(l.name)}</div>
          <div class="cargo-card-meta"><span>${esc(l.type)}</span><span class="mono">${Number(l.latitude).toFixed(3)}, ${Number(l.longitude).toFixed(3)}</span></div>
        </div>`).join('')}
    `;
  }

  function renderRightShipmentInspector(s, role) {
    setPanelHeader(
      'right',
      s.tracking_number,
      `${s.container_number || 'Kontainer'} · ${s.carrier?.name || ''}`,
      `<button type="button" class="btn btn-glass btn-sm" onclick="MeridianApp.closeInspector()">Tutup</button>`
    );

    const right = document.getElementById('right-panel-body');
    const stages = [{ lbl: 'Daftar' }, { lbl: 'Setuju' }, { lbl: 'Muat' }, { lbl: 'Berlayar' }, { lbl: 'Tiba' }];
    const rankMap = { CREATED: 0, BOOKED: 1, CONFIRMED: 1, PICKED_UP: 2, IN_TRANSIT: 3, DELIVERED: 4 };
    const rank = rankMap[s.status] ?? 0;

    // Admin inline shipment editor
    let adminEditForm = '';
    if (role === 'admin' && state.editingShipment) {
      adminEditForm = `
        <form onsubmit="MeridianApp.handleAdminUpdateShipment(event, ${s.id})" style="margin-bottom:14px;padding:12px;border-radius:10px;background:rgba(2,6,17,0.6);border:1px solid rgba(56,189,248,0.3);">
          <div class="glass-section-lbl" style="margin-top:0;">Edit Data Pengiriman (Admin)</div>
          <div class="field"><label>Deskripsi Kargo</label><input id="adm-es-desc" class="input" required value="${esc(s.cargo_description)}"></div>
          <div class="form-grid-2">
            <div class="field"><label>Kontainer ISO 6346</label><input id="adm-es-cont" class="input mono" value="${esc(s.container_number || '')}"></div>
            <div class="field"><label>Berat (kg)</label><input id="adm-es-weight" type="number" step="0.01" class="input mono" required value="${s.weight_kg}"></div>
          </div>
          <div class="field"><label>Catatan</label><textarea id="adm-es-notes" class="textarea" rows="2">${esc(s.notes || '')}</textarea></div>
          <div style="display:flex;gap:8px;">
            <button type="submit" class="btn btn-accent btn-sm" style="flex:1;">Simpan Perubahan</button>
            <button type="button" class="btn btn-glass btn-sm" onclick="MeridianApp.toggleAdminEditShipment(false)">Batal</button>
          </div>
        </form>`;
    }

    let actionBar = '';
    if (role === 'shipper' && ['CREATED', 'BOOKED'].includes(s.status)) {
      actionBar = `<button type="button" class="btn btn-ok" style="width:100%;margin-bottom:10px;" onclick="MeridianApp.confirmShipper(${s.id})">Setujui &amp; Konfirmasi Keberangkatan</button>`;
    } else if (role === 'buyer') {
      const btns = [];
      if (s.escrow && s.escrow.status === 'PENDING_DEPOSIT') {
        btns.push(`<button type="button" class="btn btn-accent btn-sm" style="flex:1;" onclick="MeridianApp.depositEscrow(${s.id})">Setor ${fmtMoney(s.escrow.amount_usd)} ke Escrow</button>`);
      }
      if (['CONFIRMED', 'IN_TRANSIT', 'PICKED_UP'].includes(s.status)) {
        btns.push(`<button type="button" class="btn btn-ok btn-sm" style="flex:1;" onclick="MeridianApp.confirmBuyer(${s.id})">Konfirmasi Terima Barang</button>`);
      }
      if (s.escrow && ['HELD_IN_ESCROW', 'FROZEN_ON_EXCEPTION'].includes(s.escrow.status)) {
        btns.push(`<button type="button" class="btn btn-danger btn-sm" onclick="MeridianApp.disputeEscrow(${s.id})">Sengketa</button>`);
      }
      if (btns.length > 0) actionBar = `<div style="display:flex;gap:7px;flex-wrap:wrap;margin-bottom:10px;">${btns.join('')}</div>`;
    } else if (role === 'operator' || role === 'admin') {
      actionBar = `
        <div style="display:flex;gap:7px;margin-bottom:10px;flex-wrap:wrap;">
          ${role === 'admin' ? `<button type="button" class="btn btn-glass btn-sm" onclick="MeridianApp.toggleAdminEditShipment(true)">Edit Data</button>` : ''}
          ${role === 'operator' && s.status !== 'DELIVERED' ? `<button type="button" class="btn btn-accent btn-sm" style="flex:1;" onclick="MeridianApp.setOperatorTab('ops')">Update Checkpoint / Kendala</button>` : ''}
          <button type="button" class="btn btn-danger btn-sm" onclick="MeridianApp.deleteShipment(${s.id})">Hapus</button>
        </div>`;
    }

    // Escrow Status Banner
    let escrowBlock = '';
    if (s.escrow) {
      const es = s.escrow.status;
      const cls = es === 'PENDING_DEPOSIT' ? 'warn' : (['FROZEN_ON_EXCEPTION', 'DISPUTED'].includes(es) ? 'danger' : '');
      escrowBlock = `
        <div class="escrow-banner ${cls}">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
            <span class="mono" style="font-size:10.5px;font-weight:700;letter-spacing:0.06em;color:#fff;">ESCROW MERIDIAN</span>
            <span class="mono" style="font-size:13px;font-weight:700;color:#fff;">${fmtMoney(s.escrow.amount_usd)} USD</span>
          </div>
          <div style="font-size:12px;font-weight:600;color:var(--ink-bright);margin-top:3px;">${esc(escrowStatusLabel(es))}</div>
          ${s.escrow.notes ? `<div style="font-size:11px;color:var(--ink-body);margin-top:2px;">${esc(s.escrow.notes)}</div>` : ''}
        </div>`;
    }

    const lw = s.live_weather || {};
    const weatherBlocks = [];
    if (lw.origin) weatherBlocks.push({ lbl: `Asal · ${lw.origin.location_code}`, w: lw.origin });
    if (lw.current && lw.current.location_code !== lw.origin?.location_code && lw.current.location_code !== lw.destination?.location_code) {
      weatherBlocks.push({ lbl: `Transit · ${lw.current.location_code}`, w: lw.current });
    }
    if (lw.destination) weatherBlocks.push({ lbl: `Tujuan · ${lw.destination.location_code}`, w: lw.destination });

    right.innerHTML = `
      <div style="margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;gap:8px;">
        ${statusPill(s)}
        <button type="button" class="btn btn-glass btn-sm" onclick="MeridianGlobe.focusShipment(MeridianApp.getSelected())">Fokus Rute</button>
      </div>

      ${actionBar}
      ${adminEditForm}
      ${escrowBlock}

      <div class="lifecycle-pills">
        ${stages.map((st, idx) => `<div class="lc-step ${idx < rank ? 'done' : (idx === rank ? 'current' : '')}">${st.lbl}</div>`).join('')}
      </div>

      <div class="glass-section-lbl">Spesifikasi &amp; Jadwal</div>
      <div class="glass-kv-grid">
        <div class="glass-kv" style="cursor:pointer;" onclick="MeridianGlobe.zoomToPort(${s.origin_location?.latitude}, ${s.origin_location?.longitude}, '${esc(s.origin_location?.name)}', '${esc(s.origin_location?.code)}')">
          <div class="glass-k">Asal (Klik Zoom)</div>
          <div class="glass-v">${esc(s.origin_location?.code || '—')} · ${esc(s.origin_location?.name || '')}</div>
        </div>
        <div class="glass-kv" style="cursor:pointer;" onclick="MeridianGlobe.zoomToPort(${s.destination_location?.latitude}, ${s.destination_location?.longitude}, '${esc(s.destination_location?.name)}', '${esc(s.destination_location?.code)}')">
          <div class="glass-k">Tujuan (Klik Zoom)</div>
          <div class="glass-v">${esc(s.destination_location?.code || '—')} · ${esc(s.destination_location?.name || '')}</div>
        </div>
        <div class="glass-kv"><div class="glass-k">Pengirim</div><div class="glass-v">${esc(s.customer?.name || '—')}</div></div>
        <div class="glass-kv"><div class="glass-k">Penerima</div><div class="glass-v">${esc(s.buyer?.name || '—')}</div></div>
        <div class="glass-kv"><div class="glass-k">Berat / Volume</div><div class="glass-v mono">${Number(s.weight_kg || 0).toLocaleString()} kg${s.volume_cbm ? ' · ' + s.volume_cbm + ' CBM' : ''}</div></div>
        <div class="glass-kv"><div class="glass-k">Estimasi Tiba</div><div class="glass-v mono" style="color:${s.is_delayed ? 'var(--accent-danger)' : 'var(--accent-sky)'};">${fmtDate(s.calculated_eta || s.expected_arrival)}${s.delay_hours > 0 ? ' (+' + s.delay_hours + 'j)' : ''}</div></div>
      </div>

      <div class="glass-section-lbl">Rincian Barang (${(s.items || []).length})</div>
      ${(s.items || []).length === 0
        ? `<div class="glass-kv"><div class="glass-v">${esc(s.cargo_description)}</div></div>`
        : (s.items || []).map(it => `
            <div class="glass-kv" style="margin-bottom:7px;">
              <div style="display:flex;justify-content:space-between;gap:8px;">
                <strong style="color:#fff;font-size:12px;">${esc(it.item_name)}</strong>
                <span class="mono" style="font-size:11px;color:var(--accent-sky);">${esc(it.quantity)} unit</span>
              </div>
              <div style="font-size:11px;color:var(--ink-muted);margin-top:2px;">${esc(it.category || 'Kargo')} · ${Number(it.weight_kg || 0).toLocaleString()} kg · ${esc(it.condition || 'Baik')}</div>
            </div>`).join('')}

      <div class="glass-section-lbl">Gangguan &amp; Delay (Ambang ${state.delayThresholdHours}j)</div>
      ${(s.exceptions || []).length === 0
        ? `<div style="font-size:11.5px;color:var(--ink-muted);">Tidak ada gangguan aktif.</div>`
        : (s.exceptions || []).map(ex => {
            const resolved = ex.status === 'RESOLVED';
            const investigating = ex.status === 'INVESTIGATING' || ex.status === 'UNDER_INVESTIGATION';
            return `
              <div class="exc-box ${resolved ? 'resolved' : ''}">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:6px;">
                  <strong style="font-size:11.5px;color:${resolved ? '#fff' : '#fca5a5'};">${esc(ex.exception_type)} (+${ex.impact_hours}j)</strong>
                  <span class="tag-mono">${esc(ex.status)}</span>
                </div>
                <div style="font-size:11.5px;color:var(--ink-body);margin-top:4px;">${esc(ex.description)}</div>
                ${resolved
                  ? `<div style="font-size:11px;color:var(--accent-ok);margin-top:4px;">Selesai: ${esc(ex.resolution_notes || 'Terselesaikan')}</div>`
                  : (['operator', 'admin'].includes(role)
                      ? `<div style="display:flex;gap:6px;margin-top:7px;">
                           ${!investigating ? `<button type="button" class="btn btn-glass btn-sm" onclick="MeridianApp.investigateException(${ex.id})">Investigasi</button>` : ''}
                           <button type="button" class="btn btn-ok btn-sm" onclick="MeridianApp.resolveException(${ex.id})">Selesaikan</button>
                         </div>`
                      : '')}
              </div>`;
          }).join('')}

      <div class="glass-section-lbl">Cuaca Pelabuhan Live (Open-Meteo)</div>
      <div class="glass-kv-grid">
        ${weatherBlocks.map(b => `
          <div class="glass-kv">
            <div class="glass-k">${esc(b.lbl)}</div>
            <div class="glass-v mono">${b.w.temperature_c ?? '—'}°C · ${b.w.wind_speed_kmh ?? 0} km/j</div>
            <div style="font-size:10.5px;color:var(--ink-muted);">${esc(b.w.condition || 'Cerah')} · Risiko ${esc(b.w.maritime_risk || 'NORMAL')}</div>
          </div>`).join('')}
      </div>

      <div class="glass-section-lbl">Riwayat Perjalanan (${(s.tracking_events || []).length})</div>
      <div class="g-timeline">
        ${(s.tracking_events || []).length === 0
          ? `<div style="font-size:11.5px;color:var(--ink-muted);">Belum ada checkpoint perjalanan tercatat.</div>`
          : (s.tracking_events || []).map(ev => `
              <div class="g-timeline-item ${String(ev.event_type || '').includes('INSPECTION') ? 'is-exc' : ''}">
                <span class="g-timeline-dot"></span>
                <div style="display:flex;justify-content:space-between;gap:6px;">
                  <span class="g-timeline-type">${esc(ev.event_type)}</span>
                  <span class="g-timeline-time">${fmtDateTime(ev.event_time)}</span>
                </div>
                <div class="g-timeline-loc">${esc(ev.location?.name || 'Titik Laut')}</div>
                ${ev.remarks ? `<div class="g-timeline-note">${esc(ev.remarks)}</div>` : ''}
              </div>`).join('')}
      </div>
    `;
  }

  /* ------------------------------------------------------------------------
     ACTIONS & MUTATIONS
     ------------------------------------------------------------------------ */
  async function selectShipment(trackingOrId) {
    try {
      const endpoint = state.user
        ? '/api/v1/shipments/' + encodeURIComponent(trackingOrId)
        : '/api/v1/public/track/' + encodeURIComponent(trackingOrId);
      const res = await api(endpoint);
      state.selectedShipment = res.data;
      state.editingShipment = false;
      document.getElementById('workspace-shell')?.classList.add('inspector-open');
      renderWorkspace();
      window.MeridianGlobe.focusShipment(state.selectedShipment);
    } catch (err) {
      toast(err.message || 'Gagal memuat detail pengiriman.', 'err');
    }
  }

  function closeInspector() {
    state.selectedShipment = null;
    state.editingShipment = false;
    document.getElementById('workspace-shell')?.classList.remove('inspector-open');
    renderWorkspace();
    window.MeridianGlobe.resetGlobe();
  }

  function resetEarthView() { window.MeridianGlobe.resetGlobe(); }

  function toggleGlassPanels() {
    const shell = document.getElementById('workspace-shell');
    const btn = document.getElementById('btn-toggle-panels');
    if (!shell) return;
    const collapsed = shell.classList.toggle('panels-collapsed');
    if (btn) btn.textContent = collapsed ? 'Tampilkan Panel' : 'Sembunyikan Panel';
  }

  function handleIsoLiveInput() {
    const inp = document.getElementById('ns-container');
    const hint = document.getElementById('ns-iso-hint');
    if (!inp || !hint) return;
    const v = inp.value.trim();
    if (!v) {
      hint.textContent = 'Opsional — 4 huruf + 7 angka ISO 6346';
      hint.style.color = 'var(--ink-muted)';
      return;
    }
    const r = validateIso6346(v);
    hint.textContent = r.valid ? `Valid ISO 6346 (${r.clean})` : r.reason;
    hint.style.color = r.valid ? 'var(--accent-ok)' : 'var(--accent-danger)';
  }

  function fillGeneratedIso() {
    const sel = document.getElementById('ns-carrier');
    const car = state.carriers.find(c => String(c.id) === sel?.value);
    const inp = document.getElementById('ns-container');
    if (inp) {
      inp.value = generateIso6346(car ? car.code : 'MSKU');
      handleIsoLiveInput();
    }
  }

  async function handleNewShipmentSubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-submit-shipment');
    if (btn) btn.disabled = true;

    const itemName = document.getElementById('ns-item-name').value.trim();
    const category = document.getElementById('ns-category').value;
    const qty = parseInt(document.getElementById('ns-quantity').value, 10) || 1;
    const weight = parseFloat(document.getElementById('ns-weight').value) || 1000;
    const volume = parseFloat(document.getElementById('ns-volume').value) || null;
    const condition = document.getElementById('ns-condition').value;
    const container = document.getElementById('ns-container').value.trim().toUpperCase() || null;
    const contractUsd = parseFloat(document.getElementById('ns-contract-usd')?.value) || 0;

    if (container && !validateIso6346(container).valid) {
      toast(validateIso6346(container).reason, 'err');
      if (btn) btn.disabled = false;
      return;
    }

    try {
      const res = await api('/api/v1/shipments', {
        method: 'POST',
        body: {
          container_number: container,
          customer_id: parseInt(document.getElementById('ns-customer').value, 10),
          buyer_id: parseInt(document.getElementById('ns-buyer').value, 10),
          carrier_id: parseInt(document.getElementById('ns-carrier').value, 10),
          origin_location_id: parseInt(document.getElementById('ns-origin').value, 10),
          destination_location_id: parseInt(document.getElementById('ns-dest').value, 10),
          cargo_description: `${itemName} (${qty} unit · ${category})`,
          weight_kg: weight,
          volume_cbm: volume,
          contract_amount_usd: contractUsd,
          expected_departure: document.getElementById('ns-departure').value + ' 08:00:00',
          expected_arrival: document.getElementById('ns-arrival').value + ' 18:00:00',
          notes: document.getElementById('ns-notes').value.trim() || null,
          items: [{ item_name: itemName, category, quantity: qty, weight_kg: weight, condition }],
        },
      });
      state.operatorTab = 'fleet';
      toast(`Pengiriman ${res.data.tracking_number} berhasil didaftarkan.`, 'ok');
      await refreshCurrentWorkspace();
      await selectShipment(res.data.tracking_number);
    } catch (err) {
      const firstErr = err.errors ? Object.values(err.errors).flat()[0] : null;
      toast(firstErr || err.message || 'Gagal mendaftarkan kargo.', 'err');
    } finally {
      if (btn) btn.disabled = false;
    }
  }

  async function confirmShipper(id) {
    try {
      await api(`/api/v1/shipments/${id}/confirm-shipper`, { method: 'POST' });
      toast('Kargo disetujui dan dikonfirmasi oleh Shipper.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) {
      toast(err.message || 'Gagal mengonfirmasi kargo.', 'err');
    }
  }

  async function confirmBuyer(id) {
    try {
      await api(`/api/v1/shipments/${id}/confirm-buyer`, { method: 'POST' });
      toast('Penerimaan dikonfirmasi. Status DELIVERED & Escrow diproses.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) {
      toast(err.message || 'Gagal mengonfirmasi penerimaan.', 'err');
    }
  }

  async function depositEscrow(id) {
    try {
      const res = await api(`/api/v1/shipments/${id}/escrow/deposit`, { method: 'POST' });
      toast(res.message || 'Dana ditahan di Escrow.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) {
      toast(err.message || 'Gagal menyetor Escrow.', 'err');
    }
  }

  async function disputeEscrow(id) {
    const reason = window.prompt('Jelaskan alasan pengajuan sengketa Escrow:', 'Barang diterima tidak sesuai kondisi kontrak.');
    if (!reason) return;
    try {
      await api(`/api/v1/shipments/${id}/escrow/dispute`, { method: 'POST', body: { reason } });
      toast('Sengketa Escrow tercatat.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) {
      toast(err.message || 'Gagal mengajukan sengketa.', 'err');
    }
  }

  async function handleWalletTopUp(e) {
    e.preventDefault();
    const amt = parseFloat(document.getElementById('w-topup-amt').value) || 0;
    try {
      await api('/api/v1/wallet/topup', { method: 'POST', body: { amount_usd: amt } });
      toast(`Top Up simulasi ${fmtMoney(amt)} berhasil.`, 'ok');
      await refreshCurrentWorkspace();
    } catch (err) {
      toast(err.message || 'Top Up gagal.', 'err');
    }
  }

  async function handleWalletWithdraw(e) {
    e.preventDefault();
    const amt = parseFloat(document.getElementById('w-withdraw-amt').value) || 0;
    const bank = document.getElementById('w-withdraw-bank').value.trim();
    try {
      await api('/api/v1/wallet/withdraw', { method: 'POST', body: { amount_usd: amt, bank_destination: bank } });
      toast(`Penarikan simulasi ${fmtMoney(amt)} ke ${bank} berhasil.`, 'ok');
      await refreshCurrentWorkspace();
    } catch (err) {
      toast(err.message || 'Penarikan gagal.', 'err');
    }
  }

  async function clickNotification(id, trackingNumber) {
    try { await api(`/api/v1/notifications/${id}/read`, { method: 'PATCH' }); } catch (_) {}
    await loadNotifications();
    renderWorkspace();
    if (state.utilityDrawer === 'notifications') renderUtilityDrawer();
    if (trackingNumber) {
      closeUtilityDrawer();
      await selectShipment(trackingNumber);
    }
  }

  async function markAllNotificationsRead() {
    try { await api('/api/v1/notifications/read-all', { method: 'POST' }); } catch (_) {}
    await loadNotifications();
    renderWorkspace();
    renderUtilityDrawer();
  }

  async function handleCheckpointSubmit(e) {
    e.preventDefault();
    const id = document.getElementById('cp-shipment-id').value;
    try {
      await api(`/api/v1/shipments/${id}/tracking`, {
        method: 'POST',
        body: {
          location_id: parseInt(document.getElementById('cp-location').value, 10),
          event_type: document.getElementById('cp-event-type').value,
          remarks: document.getElementById('cp-remarks').value.trim(),
        },
      });
      toast('Checkpoint perjalanan berhasil dicatat.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) {
      toast(err.message || 'Gagal mencatat checkpoint.', 'err');
    }
  }

  async function handleExceptionSubmit(e) {
    e.preventDefault();
    const id = document.getElementById('ex-shipment-id').value;
    try {
      await api(`/api/v1/shipments/${id}/exceptions`, {
        method: 'POST',
        body: {
          exception_type: document.getElementById('ex-type').value,
          severity: document.getElementById('ex-severity').value,
          impact_hours: parseInt(document.getElementById('ex-hours').value, 10) || 12,
          description: document.getElementById('ex-desc').value.trim(),
        },
      });
      toast('Gangguan dicatat & ETA otomatis dihitung ulang.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) {
      toast(err.message || 'Gagal mencatat gangguan.', 'err');
    }
  }

  async function investigateException(exId) {
    try {
      await api(`/api/v1/exceptions/${exId}/investigate`, { method: 'POST', body: { notes: 'Sedang ditangani tim operasional pelabuhan.' } });
      toast('Status kendala diubah menjadi INVESTIGATING.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) {
      toast(err.message || 'Gagal memperbarui status kendala.', 'err');
    }
  }

  async function resolveException(exId) {
    const notes = window.prompt('Catatan penyelesaian kendala:', 'Inspeksi selesai dan kapal melanjutkan pelayaran normal.');
    if (!notes) return;
    try {
      await api(`/api/v1/exceptions/${exId}/resolve`, { method: 'POST', body: { resolution_notes: notes } });
      toast('Gangguan diselesaikan & ETA dipulihkan.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) {
      toast(err.message || 'Gagal menyelesaikan gangguan.', 'err');
    }
  }

  async function runTimeTravel(hours) {
    const id = document.getElementById('tt-shipment-id')?.value;
    if (!id) return;
    try {
      const res = await api('/api/v1/demo/time-travel', { method: 'POST', body: { shipment_id: parseInt(id, 10), hours } });
      toast(res.message || `Simulasi lompat waktu +${hours} jam berhasil.`, 'ok');
      await refreshCurrentWorkspace();
      if (res.data?.shipment?.tracking_number) await selectShipment(res.data.shipment.tracking_number);
    } catch (err) {
      toast(err.message || 'Simulasi waktu gagal.', 'err');
    }
  }

  async function deleteShipment(id) {
    if (!window.confirm('Hapus permanen pengiriman ini beserta riwayat pelacakannya?')) return;
    try {
      await api(`/api/v1/shipments/${id}`, { method: 'DELETE' });
      state.selectedShipment = null;
      document.getElementById('workspace-shell')?.classList.remove('inspector-open');
      toast('Pengiriman dihapus.', 'ok');
      await refreshCurrentWorkspace();
      window.MeridianGlobe.resetGlobe();
    } catch (err) {
      toast(err.message || 'Gagal menghapus pengiriman.', 'err');
    }
  }

  // Admin CRUD & Full Edit Handlers
  function editAdminUser(id) { state.editingUserId = id; renderWorkspace(); }
  function editAdminCarrier(id) { state.editingCarrierId = id; renderWorkspace(); }
  function editAdminLocation(id) { state.editingLocationId = id; renderWorkspace(); }
  function toggleAdminEditShipment(flag) { state.editingShipment = flag; renderWorkspace(); }

  async function handleAdminAddUser(e) {
    e.preventDefault();
    try {
      const locVal = document.getElementById('adm-u-loc')?.value;
      await api('/api/v1/admin/users', {
        method: 'POST',
        body: {
          name: document.getElementById('adm-u-name').value.trim(),
          email: document.getElementById('adm-u-email').value.trim(),
          password: document.getElementById('adm-u-pass').value,
          role: document.getElementById('adm-u-role').value,
          location_id: locVal ? parseInt(locVal, 10) : null,
        },
      });
      toast('Pengguna baru berhasil didaftarkan Admin.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) {
      const firstErr = err.errors ? Object.values(err.errors).flat()[0] : null;
      toast(firstErr || err.message || 'Gagal menambah pengguna.', 'err');
    }
  }

  async function handleAdminUpdateUser(e, userId) {
    e.preventDefault();
    try {
      const locVal = document.getElementById('adm-eu-loc')?.value;
      const passVal = document.getElementById('adm-eu-pass')?.value;
      await api(`/api/v1/admin/users/${userId}`, {
        method: 'PATCH',
        body: {
          name: document.getElementById('adm-eu-name').value.trim(),
          email: document.getElementById('adm-eu-email').value.trim(),
          role: document.getElementById('adm-eu-role').value,
          location_id: locVal ? parseInt(locVal, 10) : null,
          ...(passVal ? { password: passVal } : {}),
        },
      });
      state.editingUserId = null;
      toast('Data pengguna diperbarui Admin.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) {
      const firstErr = err.errors ? Object.values(err.errors).flat()[0] : null;
      toast(firstErr || err.message || 'Gagal memperbarui pengguna.', 'err');
    }
  }

  async function handleAdminAddCarrier(e) {
    e.preventDefault();
    try {
      await api('/api/v1/admin/carriers', {
        method: 'POST',
        body: {
          code: document.getElementById('adm-c-code').value.trim().toUpperCase(),
          name: document.getElementById('adm-c-name').value.trim(),
          transport_mode: document.getElementById('adm-c-mode').value,
          contact_email: document.getElementById('adm-c-email').value.trim() || null,
        },
      });
      toast('Maskapai baru ditambahkan.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) {
      const firstErr = err.errors ? Object.values(err.errors).flat()[0] : null;
      toast(firstErr || err.message || 'Gagal menambah maskapai.', 'err');
    }
  }

  async function handleAdminUpdateCarrier(e, id) {
    e.preventDefault();
    try {
      await api(`/api/v1/admin/carriers/${id}`, {
        method: 'PATCH',
        body: {
          code: document.getElementById('adm-ec-code').value.trim().toUpperCase(),
          name: document.getElementById('adm-ec-name').value.trim(),
          transport_mode: document.getElementById('adm-ec-mode').value,
          contact_email: document.getElementById('adm-ec-email').value.trim() || null,
        },
      });
      state.editingCarrierId = null;
      toast('Data maskapai diperbarui.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) {
      toast(err.message || 'Gagal memperbarui maskapai.', 'err');
    }
  }

  async function handleAdminToggleCarrier(id) {
    try {
      await api(`/api/v1/admin/carriers/${id}/toggle`, { method: 'PATCH' });
      toast('Status maskapai diperbarui.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) {
      toast(err.message || 'Gagal memperbarui maskapai.', 'err');
    }
  }

  async function handleAdminAddLocation(e) {
    e.preventDefault();
    try {
      const res = await api('/api/v1/admin/locations', {
        method: 'POST',
        body: {
          code: document.getElementById('adm-l-code').value.trim().toUpperCase(),
          name: document.getElementById('adm-l-name').value.trim(),
          type: document.getElementById('adm-l-type').value,
          country: document.getElementById('adm-l-country').value.trim(),
          latitude: parseFloat(document.getElementById('adm-l-lat').value),
          longitude: parseFloat(document.getElementById('adm-l-lng').value),
        },
      });
      toast(`Pelabuhan ${res.data.code} ditambahkan ke Bola Bumi 3D.`, 'ok');
      await refreshCurrentWorkspace();
      window.MeridianGlobe.zoomToPort(res.data.latitude, res.data.longitude, res.data.name, res.data.code);
    } catch (err) {
      const firstErr = err.errors ? Object.values(err.errors).flat()[0] : null;
      toast(firstErr || err.message || 'Gagal menambah pelabuhan.', 'err');
    }
  }

  async function handleAdminUpdateLocation(e, id) {
    e.preventDefault();
    try {
      const res = await api(`/api/v1/admin/locations/${id}`, {
        method: 'PATCH',
        body: {
          code: document.getElementById('adm-el-code').value.trim().toUpperCase(),
          name: document.getElementById('adm-el-name').value.trim(),
          type: document.getElementById('adm-el-type').value,
          country: document.getElementById('adm-el-country').value.trim(),
          latitude: parseFloat(document.getElementById('adm-el-lat').value),
          longitude: parseFloat(document.getElementById('adm-el-lng').value),
        },
      });
      state.editingLocationId = null;
      toast(`Pelabuhan ${res.data.code} diperbarui.`, 'ok');
      await refreshCurrentWorkspace();
    } catch (err) {
      toast(err.message || 'Gagal memperbarui pelabuhan.', 'err');
    }
  }

  async function handleAdminUpdateShipment(e, id) {
    e.preventDefault();
    try {
      const cont = document.getElementById('adm-es-cont').value.trim().toUpperCase() || null;
      await api(`/api/v1/shipments/${id}`, {
        method: 'PATCH',
        body: {
          cargo_description: document.getElementById('adm-es-desc').value.trim(),
          container_number: cont,
          weight_kg: parseFloat(document.getElementById('adm-es-weight').value),
          notes: document.getElementById('adm-es-notes').value.trim() || null,
        },
      });
      state.editingShipment = false;
      toast('Data pengiriman diperbarui oleh Admin.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) {
      const firstErr = err.errors ? Object.values(err.errors).flat()[0] : null;
      toast(firstErr || err.message || 'Gagal memperbarui pengiriman.', 'err');
    }
  }

  let searchTimer = null;
  function handleSearchInput(val) {
    state.searchQuery = val;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(async () => {
      await loadRoleShipments();
      renderWorkspace();
    }, 240);
  }

  function setOperatorBranchFilter(flag) {
    state.operatorBranchOnly = flag;
    loadRoleShipments().then(() => renderWorkspace());
  }

  function setAdminTab(t) { state.adminTab = t; state.editingUserId = null; state.editingCarrierId = null; state.editingLocationId = null; renderWorkspace(); }
  function setOperatorTab(t) { state.operatorTab = t; renderWorkspace(); }
  function setShipperTab(t) { state.shipperTab = t; renderWorkspace(); }
  function setBuyerTab(t) { state.buyerTab = t; renderWorkspace(); }

  /* ------------------------------------------------------------------------
     BOOT
     ------------------------------------------------------------------------ */
  async function boot() {
    window.MeridianGlobe.initStarfield('cosmic-starfield');
    const initPage = window.__MERIDIAN_INIT__?.page || 'home';
    const initTrack = window.__MERIDIAN_INIT__?.trackingNumber || null;

    try {
      await loadBootstrap();
    } catch (err) {
      console.error('Bootstrap error:', err);
    }

    window.addEventListener('popstate', () => {
      const p = window.location.pathname;
      if (p === '/login') navigate('login');
      else if (p === '/register') navigate('register');
      else if (p === '/admin') navigate('admin');
      else if (p === '/operator') navigate('operator');
      else if (p === '/shipper') navigate('shipper');
      else if (p === '/buyer') navigate('buyer');
      else if (p === '/dashboard') navigate('dashboard');
      else if (p.startsWith('/track/')) navigate('track', null, { trackNumber: decodeURIComponent(p.replace('/track/', '')) });
      else navigate('home');
    });

    if (initPage === 'track' && initTrack) {
      await navigate('track', null, { trackNumber: initTrack });
    } else if (['dashboard', 'admin', 'operator', 'shipper', 'buyer'].includes(initPage)) {
      await navigate(initPage);
    } else if (initPage === 'login' || initPage === 'register') {
      if (state.user) openUserDashboard();
      else await navigate(initPage);
    } else {
      await navigate('home');
    }
  }

  document.addEventListener('DOMContentLoaded', boot);

  return {
    navigate,
    openUserDashboard,
    switchAuthTab,
    handleLoginSubmit,
    handleRegisterSubmit,
    quickLogin,
    handleLogout,
    handlePublicTrackSubmit,
    selectShipment,
    closeInspector,
    resetEarthView,
    toggleGlassPanels,
    toggleUtilityDrawer,
    closeUtilityDrawer,
    handleIsoLiveInput,
    fillGeneratedIso,
    handleNewShipmentSubmit,
    confirmShipper,
    confirmBuyer,
    depositEscrow,
    disputeEscrow,
    handleWalletTopUp,
    handleWalletWithdraw,
    clickNotification,
    markAllNotificationsRead,
    handleCheckpointSubmit,
    handleExceptionSubmit,
    investigateException,
    resolveException,
    runTimeTravel,
    deleteShipment,
    editAdminUser,
    editAdminCarrier,
    editAdminLocation,
    toggleAdminEditShipment,
    handleAdminAddUser,
    handleAdminUpdateUser,
    handleAdminAddCarrier,
    handleAdminUpdateCarrier,
    handleAdminToggleCarrier,
    handleAdminAddLocation,
    handleAdminUpdateLocation,
    handleAdminUpdateShipment,
    handleSearchInput,
    setOperatorBranchFilter,
    setAdminTab,
    setOperatorTab,
    setShipperTab,
    setBuyerTab,
    getSelected: () => state.selectedShipment,
  };
})();
