/* ==========================================================================
   MERIDIAN — Full-Screen Clean Daylight Enterprise Application
   100% Bahasa Indonesia · 4 Role Consoles (Admin, Operator, Shipper, Buyer)
   Non-Marketplace Coffee Directory · Anti-Fraud Verification · Multimodal 3D Map
   ========================================================================== */

window.MeridianApp = (function () {
  const state = {
    page: 'home',
    user: null,
    locations: [],
    carriers: [],
    customers: [],
    operators: [],
    stats: {},
    delayThresholdHours: 12,
    demoToolsEnabled: true,
    unreadNotifications: 0,
    unreadChats: 0,

    // Active data
    shipments: [],
    selectedShipment: null,
    pickupRequests: [],
    exportProducts: [],
    supportTickets: [],
    notifications: [],
    wallet: { balance_usd: 0, mutations: [] },
    chatThreads: [],
    chatContacts: [],
    chatMessages: [],
    activeChatPeerId: null,
    activeChatProductId: null,

    // Admin data
    adminOverview: null,
    adminUsers: [],
    adminCarriers: [],
    auditLogs: [],
    editingUserId: null,
    editingCarrierId: null,
    editingLocationId: null,

    // UI tabs & filters
    adminTab: 'overview',
    operatorTab: 'pickups',
    shipperTab: 'shipments',
    buyerTab: 'directory',
    operatorBranchOnly: false,
    coffeeIslandFilter: 'ALL',
    coffeeSearch: '',
    searchQuery: '',
    prefillPickupId: null,
    utilityDrawer: null,
  };

  /* ========================================================================
     100% BAHASA INDONESIA LABEL TRANSLATORS
     ======================================================================== */
  const STATUS_ID = {
    CREATED: { text: 'Menunggu Persetujuan Pengirim', cls: 'badge-warn' },
    BOOKED: { text: 'Terjadwal Muat', cls: 'badge-warn' },
    CONFIRMED: { text: 'Disetujui Pengirim', cls: 'badge-sky' },
    PICKED_UP: { text: 'Dijemput Armada Truk', cls: 'badge-sky' },
    IN_TRANSIT: { text: 'Dalam Perjalanan', cls: 'badge-sky' },
    DELIVERED: { text: 'Tiba & Selesai', cls: 'badge-ok' },
    CANCELLED: { text: 'Dibatalkan', cls: 'badge-neutral' },
  };

  const EXCEPTION_ID = {
    CUSTOMS_HOLD: 'Ditahan Bea Cukai',
    WEATHER_DELAY: 'Tunda Cuaca Buruk',
    PORT_CONGESTION: 'Antrean Pelabuhan',
    DAMAGED: 'Kerusakan Barang',
    MISROUTED: 'Salah Rute',
    DOCUMENTATION_ISSUE: 'Kendala Dokumen',
  };

  const EVENT_ID = {
    DEPARTURE: 'Berangkat dari Pelabuhan/Bandara',
    IN_TRANSIT_CHECKPOINT: 'Singgah / Dalam Perjalanan',
    CUSTOMS_INSPECTION: 'Pemeriksaan Bea Cukai',
    ARRIVAL: 'Tiba di Pelabuhan/Bandara Tujuan',
    OUT_FOR_DELIVERY: 'Diantar Truk ke Gudang Pembeli',
    DELIVERED: 'Barang Diterima Pembeli',
    CONFIRMED_BY_SHIPPER: 'Disetujui oleh Pengirim',
  };

  const ESCROW_ID = {
    PENDING_DEPOSIT: { text: 'Menunggu Setoran Pembeli', cls: 'badge-warn' },
    HELD_IN_ESCROW: { text: 'Dana Aman di Rekening Jaminan', cls: 'badge-sky' },
    FROZEN_ON_EXCEPTION: { text: 'Dana Ditahan Sementara (Ada Kendala)', cls: 'badge-danger' },
    RELEASED: { text: 'Dana Sudah Cair ke Pengirim', cls: 'badge-ok' },
    DISPUTED: { text: 'Dalam Sengketa (Tinjauan Admin)', cls: 'badge-danger' },
    REFUNDED: { text: 'Dana Dikembalikan ke Pembeli', cls: 'badge-neutral' },
  };

  const MODE_ID = {
    SEA: 'Kapal Laut (Mitra Pelayaran)',
    AIR: 'Pesawat Kargo (Jalur Cepat)',
    LAND: 'Truk Darat (Armada Cabang)',
  };

  const PICKUP_ID = {
    PENDING_INSPECTION: { text: 'Menunggu Cek Fisik Operator', cls: 'badge-warn' },
    OPERATOR_DISPATCHED: { text: 'Mobil Operator Menuju Lokasi', cls: 'badge-sky' },
    CONVERTED_TO_SHIPMENT: { text: 'Sudah Dicatat Manifest Resmi', cls: 'badge-ok' },
    CANCELLED: { text: 'Dibatalkan', cls: 'badge-neutral' },
  };

  const ROLE_ID = {
    admin: 'Administrator',
    operator: 'Operator Cabang',
    customer: 'Pengirim / Pembeli',
    surveyor: 'Pemeriksa Mutu',
    public: 'Pengunjung Publik',
  };

  /* ========================================================================
     UTILITIES & API FETCH CLIENT
     ======================================================================== */
  function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  }

  function esc(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function fmtDate(d) {
    if (!d) return '—';
    const dt = new Date(d);
    if (isNaN(dt.getTime())) return '—';
    return dt.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
  }

  function fmtDateTime(d) {
    if (!d) return '—';
    const dt = new Date(d);
    if (isNaN(dt.getTime())) return '—';
    return dt.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
  }

  function fmtMoney(v) {
    const n = Number(v || 0);
    return '$' + n.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  }

  function statusBadge(status, isDelayed) {
    if (isDelayed && status !== 'DELIVERED') {
      return `<span class="badge badge-danger">Terlambat · ${esc(STATUS_ID[status]?.text || status)}</span>`;
    }
    const info = STATUS_ID[status] || { text: status || '—', cls: 'badge-neutral' };
    return `<span class="badge ${info.cls}">${esc(info.text)}</span>`;
  }

  function escrowBadge(escrow) {
    if (!escrow) return '<span class="badge badge-neutral">Tanpa Jaminan</span>';
    const info = ESCROW_ID[escrow.status] || { text: escrow.status, cls: 'badge-neutral' };
    return `<span class="badge ${info.cls}">${esc(info.text)}</span>`;
  }

  function modeBadge(mode) {
    const m = (mode || 'SEA').toUpperCase();
    if (m === 'AIR') return '<span class="badge badge-indigo">Pesawat (Cepat)</span>';
    if (m === 'LAND') return '<span class="badge badge-warn">Truk Darat</span>';
    return '<span class="badge badge-sky">Kapal Laut</span>';
  }

  function toast(msg, type = 'info') {
    const c = document.getElementById('toast-container');
    if (!c) return;
    const el = document.createElement('div');
    el.className = `toast-item ${type === 'ok' ? 'toast-ok' : type === 'err' ? 'toast-err' : ''}`;
    el.innerHTML = `<span class="toast-dot"></span><span>${esc(msg)}</span>`;
    c.appendChild(el);
    setTimeout(() => {
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 220);
    }, 3600);
  }

  async function api(path, options = {}) {
    const opts = {
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        ...(options.body ? { 'Content-Type': 'application/json' } : {}),
        ...(options.headers || {}),
      },
      ...options,
    };
    const res = await fetch(path, opts);
    const json = await res.json().catch(() => ({ success: false, message: 'Respons server tidak valid.' }));
    if (!res.ok) {
      const err = new Error(json.message || `Permintaan gagal (${res.status})`);
      err.status = res.status;
      err.errors = json.data || json.errors || null;
      throw err;
    }
    return json;
  }

  /* ISO 6346 Client Generator & Validator */
  const ISO_MAP = {
    A: 10, B: 12, C: 13, D: 14, E: 15, F: 16, G: 17, H: 18, I: 19, J: 20, K: 21, L: 23, M: 24,
    N: 25, O: 26, P: 27, Q: 28, R: 29, S: 30, T: 31, U: 32, V: 34, W: 35, X: 36, Y: 37, Z: 38,
  };
  function calcIsoCheckDigit(prefix10) {
    let sum = 0;
    for (let i = 0; i < 10; i++) {
      const ch = prefix10[i];
      const val = /[A-Z]/.test(ch) ? ISO_MAP[ch] : parseInt(ch, 10);
      sum += val * Math.pow(2, i);
    }
    const rem = sum % 11;
    return rem === 10 ? 0 : rem;
  }
  function generateIso6346(owner = 'MSKU') {
    const serial = String(Math.floor(100000 + Math.random() * 899999));
    const p10 = owner.toUpperCase().slice(0, 4) + serial;
    return p10 + calcIsoCheckDigit(p10);
  }
  function validateIso6346(raw) {
    const clean = (raw || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
    if (clean.length !== 11 || !/^[A-Z]{4}\d{7}$/.test(clean)) return { valid: false, clean };
    const expected = calcIsoCheckDigit(clean.slice(0, 10));
    return { valid: parseInt(clean[10], 10) === expected, clean, expected };
  }

  /* ========================================================================
     BOOTSTRAP & DATA LOADERS
     ======================================================================== */
  function resolveHomeWorkspaceForUser(user) {
    if (!user) return 'login';
    if (user.role === 'admin') return 'admin';
    if (user.role === 'operator') return 'operator';
    if ((user.email || '').toLowerCase().includes('buyer')) return 'buyer';
    return 'shipper';
  }

  async function loadBootstrap() {
    const res = await api('/api/v1/bootstrap');
    const d = res.data || {};
    state.user = d.user || null;
    state.locations = d.locations || [];
    state.carriers = d.carriers || [];
    state.customers = d.customers || [];
    state.operators = d.operators || [];
    state.stats = d.stats || {};
    state.delayThresholdHours = d.delay_threshold_hours || 12;
    state.demoToolsEnabled = d.demo_tools_enabled !== false;
    state.unreadNotifications = d.unread_notifications || 0;
    state.unreadChats = d.unread_chats || 0;
    renderHomeSummary();
  }

  async function loadRoleShipments() {
    if (state.page === 'track' && !state.user) {
      state.shipments = [];
      return;
    }
    const params = new URLSearchParams({ per_page: '50' });
    if (state.searchQuery.trim()) params.set('search', state.searchQuery.trim());
    if (state.page === 'shipper' && state.user) params.set('customer_id', state.user.id);
    else if (state.page === 'buyer' && state.user) params.set('buyer_id', state.user.id);
    else if (state.page === 'operator' && state.operatorBranchOnly && state.user?.location_id) {
      params.set('branch_location_id', state.user.location_id);
    }

    const res = await api('/api/v1/shipments?' + params.toString());
    state.shipments = res.data?.data || res.data || [];
    if (state.selectedShipment) {
      const fresh = state.shipments.find(s => s.id === state.selectedShipment.id);
      if (fresh) state.selectedShipment = fresh;
    }
  }

  async function loadExportProducts() {
    const params = new URLSearchParams();
    if (state.coffeeIslandFilter && state.coffeeIslandFilter !== 'ALL') params.set('island', state.coffeeIslandFilter);
    if (state.coffeeSearch.trim()) params.set('search', state.coffeeSearch.trim());
    if (state.page === 'shipper' && state.user) params.set('shipper_id', state.user.id);
    const res = await api('/api/v1/export-products?' + params.toString());
    state.exportProducts = res.data || [];
  }

  async function loadPickupRequests() {
    if (!state.user) return;
    const params = new URLSearchParams();
    if (state.page === 'operator' && state.operatorBranchOnly && state.user.location_id) {
      params.set('branch_location_id', state.user.location_id);
    }
    const res = await api('/api/v1/pickup-requests?' + params.toString());
    state.pickupRequests = res.data || [];
  }

  async function loadChatData() {
    if (!state.user) return;
    const res = await api('/api/v1/chats/threads');
    state.chatThreads = res.data?.threads || [];
    state.chatContacts = res.data?.contacts || [];
    if (!state.activeChatPeerId && state.chatThreads.length > 0) {
      state.activeChatPeerId = state.chatThreads[0].peer.id;
    }
    if (state.activeChatPeerId) {
      const mRes = await api(`/api/v1/chats/messages?peer_id=${state.activeChatPeerId}`);
      state.chatMessages = mRes.data || [];
    } else {
      state.chatMessages = [];
    }
  }

  async function loadSupportTickets() {
    if (!state.user) return;
    const res = await api('/api/v1/support-tickets');
    state.supportTickets = res.data || [];
  }

  async function loadUtilityData() {
    if (!state.user) return;
    try {
      const nRes = await api('/api/v1/notifications');
      state.notifications = nRes.data?.items || [];
      state.unreadNotifications = nRes.data?.unread_count ?? 0;
    } catch {}
    if (state.user.role === 'customer') {
      try {
        const wRes = await api('/api/v1/wallet');
        state.wallet = wRes.data || { balance_usd: 0, mutations: [] };
      } catch {}
    }
  }

  async function loadAdminExtras() {
    if (!state.user || state.user.role !== 'admin') return;
    try {
      const [ov, us, ca, au, tk, pr] = await Promise.all([
        api('/api/v1/admin/overview'),
        api('/api/v1/admin/users'),
        api('/api/v1/admin/carriers'),
        api('/api/v1/audit-logs'),
        api('/api/v1/support-tickets'),
        api('/api/v1/export-products?include_inactive=1'),
      ]);
      state.adminOverview = ov.data;
      state.adminUsers = us.data || [];
      state.adminCarriers = ca.data || [];
      state.auditLogs = au.data || [];
      state.supportTickets = tk.data || [];
      state.exportProducts = pr.data || [];
    } catch (e) {
      toast(e.message, 'err');
    }
  }

  async function refreshCurrentWorkspace() {
    await loadBootstrap();
    await loadUtilityData();
    if (state.page === 'admin') {
      await Promise.all([loadRoleShipments(), loadAdminExtras()]);
    } else if (state.page === 'operator') {
      await Promise.all([loadRoleShipments(), loadPickupRequests(), loadChatData()]);
    } else if (state.page === 'shipper') {
      await Promise.all([loadRoleShipments(), loadPickupRequests(), loadExportProducts(), loadChatData()]);
    } else if (state.page === 'buyer') {
      await Promise.all([loadRoleShipments(), loadExportProducts(), loadChatData()]);
    }
    renderWorkspace();
    window.MeridianGlobe.syncWorkspaceData(state.locations, state.shipments, state.selectedShipment);
  }

  /* ========================================================================
     SPA ROUTING & PAGE VISIBILITY
     ======================================================================== */
  async function navigate(page, urlPath = null, opts = {}) {
    if (['admin', 'operator', 'shipper', 'buyer'].includes(page)) {
      if (!state.user) {
        toast('Silakan masuk terlebih dahulu untuk mengakses ruang kerja.', 'info');
        return navigate('login', '/login');
      }
      if (page === 'admin' && state.user.role !== 'admin') {
        const own = resolveHomeWorkspaceForUser(state.user);
        toast('Halaman Administrator khusus akun Admin.', 'err');
        return navigate(own, '/' + own);
      }
      if (page === 'operator' && !['operator', 'admin'].includes(state.user.role)) {
        const own = resolveHomeWorkspaceForUser(state.user);
        toast('Halaman Operator khusus petugas cabang.', 'err');
        return navigate(own, '/' + own);
      }
    }

    state.page = page;
    closeUtilityDrawer();
    if (urlPath && window.location.pathname !== urlPath) {
      window.history.pushState({ page }, '', urlPath);
    }

    const isWorkspace = ['admin', 'operator', 'shipper', 'buyer', 'track'].includes(page);
    document.getElementById('page-home')?.classList.toggle('hidden', page !== 'home');
    document.getElementById('page-auth')?.classList.toggle('hidden', !['login', 'register'].includes(page));
    document.getElementById('page-workspace')?.classList.toggle('hidden', !isWorkspace);

    if (page === 'home') {
      renderHomeSummary();
      setTimeout(() => window.MeridianGlobe.initHeroGlobe('hero-globe-map', state.locations), 50);
    } else if (page === 'login' || page === 'register') {
      switchAuthTab(page === 'register' ? 'register' : 'login');
    } else if (isWorkspace) {
      if (page === 'track' && opts.trackingNumber) {
        await performPublicTrackLookup(opts.trackingNumber);
      } else {
        await refreshCurrentWorkspace();
      }
    }
  }

  function openUserDashboard() {
    if (!state.user) return navigate('login', '/login');
    const target = resolveHomeWorkspaceForUser(state.user);
    navigate(target, '/' + target);
  }

  /* ========================================================================
     PAGE 1: PUBLIC HOME RENDERING
     ======================================================================== */
  function renderHomeSummary() {
    const elPorts = document.getElementById('home-stat-ports');
    const elCarriers = document.getElementById('home-stat-carriers');
    const elCoffee = document.getElementById('home-stat-coffee');
    const elShip = document.getElementById('home-stat-shipments');
    if (elPorts) elPorts.textContent = String(state.locations.length);
    if (elCarriers) elCarriers.textContent = String(state.carriers.length);
    if (elCoffee) elCoffee.textContent = String(state.stats.coffee_products ?? 9);
    if (elShip) elShip.textContent = String(state.stats.total_shipments ?? 0);

    document.getElementById('nav-btn-signin')?.classList.toggle('hidden', !!state.user);
    document.getElementById('nav-btn-console')?.classList.toggle('hidden', !state.user);

    const tbody = document.getElementById('home-ports-tbody');
    if (tbody) {
      tbody.innerHTML = state.locations.map(l => {
        const isAir = l.type === 'AIRPORT';
        return `
          <tr>
            <td class="mono" style="font-weight:700;color:var(--primary);">${esc(l.code)}</td>
            <td style="font-weight:600;">${esc(l.name)}</td>
            <td><span class="badge ${l.island === 'Internasional' ? 'badge-neutral' : 'badge-sky'}">${esc(l.island || l.country)}</span></td>
            <td><span class="badge ${isAir ? 'badge-indigo' : 'badge-ok'}">${isAir ? 'Bandara Pesawat' : 'Pelabuhan Kapal'}</span></td>
            <td>${esc(l.country)}</td>
          </tr>`;
      }).join('');
    }
  }

  async function handlePublicTrackSubmit(e) {
    e.preventDefault();
    const val = (document.getElementById('home-track-input')?.value || '').trim();
    if (!val) return toast('Masukkan nomor resi atau nomor kontainer terlebih dahulu.', 'err');
    await navigate('track', `/track/${encodeURIComponent(val)}`, { trackingNumber: val });
  }

  async function performPublicTrackLookup(trackingNumber) {
    try {
      const res = await api(`/api/v1/public/track/${encodeURIComponent(trackingNumber)}`);
      state.selectedShipment = res.data;
      state.shipments = [res.data];
      renderWorkspace();
      window.MeridianGlobe.syncWorkspaceData(state.locations, state.shipments, state.selectedShipment);
    } catch (e) {
      state.selectedShipment = null;
      state.shipments = [];
      renderWorkspace();
      toast(e.message || 'Nomor resi tidak ditemukan.', 'err');
    }
  }

  /* ========================================================================
     PAGE 2: AUTHENTICATION (PURE ENTERPRISE LOGIN & REGISTER)
     ======================================================================== */
  function switchAuthTab(tab) {
    const isReg = tab === 'register';
    document.getElementById('tab-auth-login')?.classList.toggle('active', !isReg);
    document.getElementById('tab-auth-register')?.classList.toggle('active', isReg);
    document.getElementById('form-login')?.classList.toggle('hidden', isReg);
    document.getElementById('form-register')?.classList.toggle('hidden', !isReg);
    document.getElementById('auth-error-box')?.classList.add('hidden');
  }

  function showAuthError(msg) {
    const box = document.getElementById('auth-error-box');
    if (!box) return;
    box.textContent = msg;
    box.classList.remove('hidden');
  }

  async function handleLoginSubmit(e) {
    e.preventDefault();
    const email = (document.getElementById('login-email')?.value || '').trim();
    const password = document.getElementById('login-password')?.value || '';
    const btn = document.getElementById('btn-login-submit');
    if (btn) btn.disabled = true;
    try {
      const res = await api('/api/v1/login', { method: 'POST', body: JSON.stringify({ email, password }) });
      state.user = res.data.user;
      toast(`Selamat datang, ${state.user.name}`, 'ok');
      const target = resolveHomeWorkspaceForUser(state.user);
      await navigate(target, '/' + target);
    } catch (err) {
      showAuthError(err.message || 'Email atau kata sandi salah.');
    } finally {
      if (btn) btn.disabled = false;
    }
  }

  async function handleRegisterSubmit(e) {
    e.preventDefault();
    const payload = {
      name: (document.getElementById('reg-name')?.value || '').trim(),
      company_name: (document.getElementById('reg-company')?.value || '').trim(),
      email: (document.getElementById('reg-email')?.value || '').trim(),
      phone: (document.getElementById('reg-phone')?.value || '').trim(),
      city: (document.getElementById('reg-city')?.value || '').trim(),
      country: (document.getElementById('reg-country')?.value || 'Indonesia').trim(),
      identity_number: (document.getElementById('reg-identity')?.value || '').trim(),
      password: document.getElementById('reg-password')?.value || '',
    };
    const btn = document.getElementById('btn-register-submit');
    if (btn) btn.disabled = true;
    try {
      const res = await api('/api/v1/register', { method: 'POST', body: JSON.stringify(payload) });
      state.user = res.data.user;
      toast('Akun berhasil dibuat. Identitas Anda dapat diverifikasi oleh Admin.', 'ok');
      await navigate('shipper', '/shipper');
    } catch (err) {
      showAuthError(err.message || 'Pendaftaran gagal.');
    } finally {
      if (btn) btn.disabled = false;
    }
  }

  async function handleLogout() {
    try { await api('/api/v1/logout', { method: 'POST' }); } catch {}
    state.user = null;
    state.selectedShipment = null;
    state.shipments = [];
    toast('Anda telah keluar dari sesi.', 'info');
    await loadBootstrap();
    await navigate('home', '/');
  }

  /* ========================================================================
     CONSOLE SHELL RENDERER (SIDEBAR + TOPBAR + MOBILE BOTTOM NAV)
     ======================================================================== */
  function renderWorkspace() {
    const roleMeta = {
      admin: { text: 'ADMINISTRATOR', cls: 'role-admin' },
      operator: { text: 'OPERATOR CABANG', cls: 'role-operator' },
      shipper: { text: 'PENGIRIM (SHIPPER)', cls: 'role-shipper' },
      buyer: { text: 'PEMBELI (BUYER)', cls: 'role-buyer' },
      track: { text: 'LACAK RESI PUBLIK', cls: '' },
    }[state.page] || { text: 'LACAK PUBLIK', cls: '' };

    const badge = document.getElementById('ws-role-badge');
    if (badge) {
      badge.textContent = roleMeta.text;
      badge.className = 'role-pill ' + roleMeta.cls;
    }

    const branchEl = document.getElementById('ws-branch-badge');
    if (branchEl) {
      if (state.page === 'operator' && state.user?.branch_location) {
        branchEl.textContent = `${state.user.branch_location.island || ''} · ${state.user.branch_location.code}`;
        branchEl.classList.remove('hidden');
      } else {
        branchEl.classList.add('hidden');
      }
    }

    const logged = !!state.user;
    document.getElementById('ws-btn-logout')?.classList.toggle('hidden', !logged);
    document.getElementById('ws-btn-login')?.classList.toggle('hidden', logged);
    document.getElementById('ws-btn-notif')?.classList.toggle('hidden', !logged);
    const pill = document.getElementById('ws-btn-profile-pill');
    if (pill) {
      pill.classList.toggle('hidden', !logged);
      if (logged) {
        document.getElementById('ws-user-name').textContent = state.user.name;
        document.getElementById('ws-user-avatar').textContent = (state.user.name || 'U')[0].toUpperCase();
      }
    }

    const notifCount = document.getElementById('ws-notif-count');
    if (notifCount) notifCount.textContent = String(state.unreadNotifications || 0);

    const btnWallet = document.getElementById('ws-btn-wallet');
    if (btnWallet) {
      const showW = logged && ['shipper', 'buyer'].includes(state.page);
      btnWallet.classList.toggle('hidden', !showW);
      if (showW) {
        const bal = state.wallet.balance_usd ?? state.user.wallet_balance_usd ?? 0;
        btnWallet.textContent = `Dompet: ${fmtMoney(bal)}`;
      }
    }

    renderSidebarAndMobileNav();

    if (state.page === 'admin') renderAdminConsole();
    else if (state.page === 'operator') renderOperatorConsole();
    else if (state.page === 'shipper') renderShipperConsole();
    else if (state.page === 'buyer') renderBuyerConsole();
    else renderPublicTrackConsole();
  }

  function setHeader(title, sub) {
    const t = document.getElementById('header-page-title');
    const s = document.getElementById('header-page-sub');
    if (t) t.textContent = title;
    if (s) s.textContent = sub;
  }

  function renderSidebarAndMobileNav() {
    const navEl = document.getElementById('sidebar-nav');
    const mobEl = document.getElementById('mobile-bottom-nav');
    if (!navEl || !mobEl) return;

    let items = [];
    let currentTab = '';
    let setter = '';

    if (state.page === 'admin') {
      currentTab = state.adminTab;
      setter = 'setAdminTab';
      const openTickets = state.supportTickets.filter(t => t.status !== 'RESOLVED').length;
      items = [
        { id: 'overview', label: 'Ringkasan Utama', short: 'Ringkas' },
        { id: 'users', label: 'Pengguna & Verifikasi', short: 'Akun', count: state.adminUsers.length },
        { id: 'shipments', label: 'Semua Pengiriman', short: 'Kargo', count: state.shipments.length },
        { id: 'products', label: 'Direktori Kopi', short: 'Kopi', count: state.exportProducts.length },
        { id: 'locations', label: 'Pelabuhan & Bandara', short: '5 Pulau', count: state.locations.length },
        { id: 'carriers', label: 'Mitra Kapal & Pesawat', short: 'Mitra', count: state.adminCarriers.length },
        { id: 'support', label: 'Kotak Bantuan Masuk', short: 'Bantuan', count: openTickets },
        { id: 'audit', label: 'Riwayat Aktivitas', short: 'Audit' },
        { id: 'profile', label: 'Profil Saya', short: 'Profil' },
      ];
    } else if (state.page === 'operator') {
      currentTab = state.operatorTab;
      setter = 'setOperatorTab';
      const pendingCalls = state.pickupRequests.filter(p => ['PENDING_INSPECTION', 'OPERATOR_DISPATCHED'].includes(p.status)).length;
      items = [
        { id: 'pickups', label: 'Panggilan Jemput Shipper', short: 'Jemput', count: pendingCalls },
        { id: 'create', label: 'Input Barang Baru', short: '+ Input' },
        { id: 'fleet', label: 'Antrean Pengiriman', short: 'Kargo', count: state.shipments.length },
        { id: 'ops', label: 'Perjalanan, Kendala & Besok', short: 'Progres' },
        { id: 'chat', label: 'Obrolan (Chat)', short: 'Chat', count: state.unreadChats },
        { id: 'profile', label: 'Profil & Cabang', short: 'Profil' },
      ];
    } else if (state.page === 'shipper') {
      currentTab = state.shipperTab;
      setter = 'setShipperTab';
      items = [
        { id: 'shipments', label: 'Pengiriman & Konfirmasi', short: 'Kirim', count: state.shipments.length },
        { id: 'products', label: 'Kopi Nusantara Saya', short: 'Kopi', count: state.exportProducts.length },
        { id: 'chat', label: 'Obrolan (Chat)', short: 'Chat', count: state.unreadChats },
        { id: 'branches', label: 'Kantor Cabang 5 Pulau', short: 'Cabang' },
        { id: 'profile', label: 'Profil & Identitas', short: 'Profil' },
      ];
    } else if (state.page === 'buyer') {
      currentTab = state.buyerTab;
      setter = 'setBuyerTab';
      items = [
        { id: 'directory', label: 'Direktori Kopi Nusantara', short: 'Kopi', count: state.exportProducts.length },
        { id: 'orders', label: 'Pengiriman & Jaminan', short: 'Pesanan', count: state.shipments.length },
        { id: 'chat', label: 'Obrolan (Chat)', short: 'Chat', count: state.unreadChats },
        { id: 'profile', label: 'Profil & Identitas', short: 'Profil' },
      ];
    } else {
      items = [{ id: 'track', label: 'Hasil Pelacakan', short: 'Lacak' }];
      currentTab = 'track';
      setter = '';
    }

    navEl.innerHTML = items.map(it => `
      <button type="button" class="nav-item ${currentTab === it.id ? 'active' : ''}" ${setter ? `onclick="MeridianApp.${setter}('${it.id}')"` : ''}>
        <span>${esc(it.label)}</span>
        ${it.count !== undefined ? `<span class="nav-count">${it.count}</span>` : ''}
      </button>
    `).join('');

    // Mobile Bottom Nav: top 4 primary tabs + Bantuan/Peta
    const mobItems = items.slice(0, 4);
    mobEl.innerHTML = mobItems.map(it => `
      <button type="button" class="mob-nav-btn ${currentTab === it.id ? 'active' : ''}" ${setter ? `onclick="MeridianApp.${setter}('${it.id}')"` : ''}>
        <span>${esc(it.short)}</span>
      </button>
    `).join('') + `
      <button type="button" class="mob-nav-btn" onclick="MeridianApp.openHelpModal()">
        <span>Bantuan</span>
      </button>
    `;
  }

  /* ========================================================================
     DASHBOARD 1: ADMINISTRATOR (/admin) — FULL-SCREEN ADMIN CONSOLE
     ======================================================================== */
  function renderAdminConsole() {
    const main = document.getElementById('console-main');
    const m = state.adminOverview?.metrics || {};
    setHeader('Pusat Kendali Administrator', 'Verifikasi identitas pengguna, kelola jaringan 5 pulau, mitra kapal/pesawat & kotak bantuan');

    if (state.adminTab === 'overview') {
      const byStatus = state.adminOverview?.by_status || {};
      const total = Math.max(1, m.total_shipments || 0);
      const bars = ['CREATED', 'CONFIRMED', 'PICKED_UP', 'IN_TRANSIT', 'DELIVERED'].map(k => {
        const val = byStatus[k] || 0;
        const pct = Math.round((val / total) * 100);
        return `
          <div style="margin-bottom:12px;">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:5px;">
              <span style="font-weight:600;color:var(--ink-2);">${esc(STATUS_ID[k]?.text || k)}</span>
              <span class="mono" style="font-weight:700;">${val} (${pct}%)</span>
            </div>
            <div class="progress-track"><div class="progress-fill ${k === 'DELIVERED' ? 'ok' : ''}" style="width:${pct}%"></div></div>
          </div>`;
      }).join('');

      main.innerHTML = `
        <div class="kpi-grid">
          <div class="kpi-card">
            <div class="kpi-label">Total Pengiriman</div>
            <div class="kpi-val">${m.total_shipments ?? 0}</div>
            <div class="kpi-sub">Ketepatan waktu: <strong style="color:var(--ok);">${m.on_time_rate ?? 100}%</strong></div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Pengguna Terverifikasi</div>
            <div class="kpi-val" style="color:var(--primary);">${m.verified_users ?? 0} / ${m.total_users ?? state.adminUsers.length}</div>
            <div class="kpi-sub">Identitas diverifikasi Admin</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Jaringan 5 Pulau &amp; Dunia</div>
            <div class="kpi-val">${m.total_locations ?? state.locations.length}</div>
            <div class="kpi-sub">${m.total_carriers ?? state.adminCarriers.length} mitra kapal, pesawat &amp; truk</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Tiket Bantuan Terbuka</div>
            <div class="kpi-val" style="color:${(m.open_tickets || 0) > 0 ? 'var(--danger)' : 'var(--ok)'};">${m.open_tickets ?? 0}</div>
            <div class="kpi-sub">${m.pending_pickups ?? 0} panggilan cek fisik aktif</div>
          </div>
        </div>

        <div class="form-grid-2">
          <div class="surface-card card-pad">
            <div class="section-bar">
              <div class="section-title">Sebaran Status Pengiriman</div>
              <button type="button" class="btn btn-outline btn-sm" onclick="MeridianApp.setAdminTab('shipments')">Lihat Semua &rarr;</button>
            </div>
            ${bars}
          </div>

          <div class="surface-card card-pad">
            <div class="section-bar">
              <div class="section-title">Aktivitas Sistem Terbaru</div>
              <button type="button" class="btn btn-outline btn-sm" onclick="MeridianApp.setAdminTab('audit')">Semua Log &rarr;</button>
            </div>
            ${state.auditLogs.length === 0
              ? `<div class="muted-sm">Belum ada riwayat aktivitas transaksi. Semua perubahan penting akan tampil di sini.</div>`
              : state.auditLogs.slice(0, 6).map(l => `
                  <div style="padding:9px 0;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;gap:10px;font-size:13px;">
                    <div>
                      <strong>${esc(l.user?.name || 'Sistem')}</strong>
                      <div class="muted-sm">${esc(l.action)}</div>
                    </div>
                    <span class="mono muted-sm">${fmtDateTime(l.created_at)}</span>
                  </div>`).join('')}
          </div>
        </div>
      `;
    } else if (state.adminTab === 'users') {
      const editU = state.editingUserId ? state.adminUsers.find(u => u.id === state.editingUserId) : null;
      const formCard = editU
        ? `
          <div class="surface-card card-pad" style="margin-bottom:18px;border-color:var(--primary);">
            <div class="section-bar">
              <div class="section-title">Edit Data &amp; Verifikasi: ${esc(editU.name)}</div>
              <button type="button" class="btn btn-outline btn-sm" onclick="MeridianApp.editAdminUser(null)">Batal</button>
            </div>
            <form onsubmit="MeridianApp.handleAdminUpdateUser(event, ${editU.id})">
              <div class="form-grid-3">
                <div class="field"><label>Nama Lengkap</label><input id="adm-eu-name" class="input" required value="${esc(editU.name)}"></div>
                <div class="field"><label>Email</label><input id="adm-eu-email" type="email" class="input" required value="${esc(editU.email)}"></div>
                <div class="field"><label>No. Telepon / WA</label><input id="adm-eu-phone" class="input" value="${esc(editU.phone || '')}"></div>
              </div>
              <div class="form-grid-3">
                <div class="field"><label>Perusahaan / Koperasi</label><input id="adm-eu-company" class="input" value="${esc(editU.company_name || '')}"></div>
                <div class="field"><label>No. Identitas (NIB/NPWP)</label><input id="adm-eu-idnum" class="input mono" value="${esc(editU.identity_number || '')}"></div>
                <div class="field"><label>Kota / Negara</label><input id="adm-eu-city" class="input" value="${esc(editU.city || '')}"></div>
              </div>
              <div class="form-grid-3">
                <div class="field">
                  <label>Peran Akun</label>
                  <select id="adm-eu-role" class="select">
                    ${['admin', 'operator', 'customer', 'surveyor'].map(r => `<option value="${r}" ${editU.role === r ? 'selected' : ''}>${esc(ROLE_ID[r] || r)}</option>`).join('')}
                  </select>
                </div>
                <div class="field">
                  <label>Cabang Tugas Operator</label>
                  <select id="adm-eu-loc" class="select">
                    <option value="">Semua Cabang</option>
                    ${state.locations.map(l => `<option value="${l.id}" ${Number(editU.location_id) === l.id ? 'selected' : ''}>${esc(l.code)} · ${esc(l.name)}</option>`).join('')}
                  </select>
                </div>
                <div class="field"><label>Ganti Kata Sandi (Opsional)</label><input id="adm-eu-pass" type="password" class="input" minlength="6" placeholder="Kosongkan jika tetap"></div>
              </div>
              <button type="submit" class="btn btn-primary">Simpan Perubahan Pengguna</button>
            </form>
          </div>`
        : `
          <div class="surface-card card-pad" style="margin-bottom:18px;">
            <div class="section-title" style="margin-bottom:12px;">Buat Akun Baru (Khusus Admin — Termasuk Operator Cabang)</div>
            <form onsubmit="MeridianApp.handleAdminAddUser(event)">
              <div class="form-grid-4">
                <div class="field"><label>Nama Lengkap <span class="req">*</span></label><input id="adm-u-name" class="input" required placeholder="Nama petugas / perusahaan"></div>
                <div class="field"><label>Email <span class="req">*</span></label><input id="adm-u-email" type="email" class="input" required placeholder="petugas@meridian.id"></div>
                <div class="field"><label>No. Telepon / WA</label><input id="adm-u-phone" class="input" placeholder="+62 812 xxxx"></div>
                <div class="field"><label>Kata Sandi <span class="req">*</span></label><input id="adm-u-pass" type="password" class="input" required minlength="6" placeholder="Min 6 karakter"></div>
              </div>
              <div class="form-grid-3">
                <div class="field"><label>Perusahaan / Kantor</label><input id="adm-u-company" class="input" placeholder="Cabang / Perusahaan"></div>
                <div class="field">
                  <label>Peran Akun</label>
                  <select id="adm-u-role" class="select">
                    <option value="operator">Operator Cabang</option>
                    <option value="customer">Pengirim / Pembeli</option>
                    <option value="admin">Administrator</option>
                    <option value="surveyor">Pemeriksa Mutu</option>
                  </select>
                </div>
                <div class="field">
                  <label>Cabang Pelabuhan / Bandara (Operator)</label>
                  <select id="adm-u-loc" class="select">
                    <option value="">Global / Semua Cabang</option>
                    ${state.locations.map(l => `<option value="${l.id}">${esc(l.code)} · ${esc(l.name)}</option>`).join('')}
                  </select>
                </div>
              </div>
              <button type="submit" class="btn btn-primary">+ Daftarkan Akun Baru</button>
            </form>
          </div>`;

      main.innerHTML = formCard + `
        <div class="surface-card table-card">
          <div class="card-pad" style="padding-bottom:12px;"><div class="section-title">Daftar Seluruh Akun &amp; Status Identitas (${state.adminUsers.length})</div></div>
          <div class="table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Nama &amp; Perusahaan</th>
                  <th>Kontak &amp; Identitas</th>
                  <th>Peran &amp; Cabang</th>
                  <th>Verifikasi</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                ${state.adminUsers.map(u => `
                  <tr>
                    <td>
                      <strong>${esc(u.name)}</strong>
                      <div class="muted-sm">${esc(u.company_name || '—')}</div>
                    </td>
                    <td>
                      <div class="mono" style="font-size:12.5px;">${esc(u.email)}</div>
                      <div class="muted-sm">${esc(u.phone || 'Belum isi telepon')} · ${esc(u.identity_number || 'Belum isi NIB')}</div>
                    </td>
                    <td>
                      <span class="badge badge-sky">${esc(ROLE_ID[u.role] || u.role)}</span>
                      <div class="muted-sm" style="margin-top:3px;">${u.branch_location ? esc(u.branch_location.name) : 'Semua Wilayah'}</div>
                    </td>
                    <td>
                      ${u.is_verified ? '<span class="verified-badge">Terverifikasi</span>' : '<span class="badge badge-warn">Belum Diverifikasi</span>'}
                    </td>
                    <td>
                      <div class="btn-group">
                        <button type="button" class="btn btn-sm ${u.is_verified ? 'btn-outline' : 'btn-ok'}" onclick="MeridianApp.handleAdminToggleVerify(${u.id})">${u.is_verified ? 'Cabut' : 'Verifikasi'}</button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="MeridianApp.editAdminUser(${u.id})">Edit</button>
                      </div>
                    </td>
                  </tr>`).join('')}
              </tbody>
            </table>
          </div>
        </div>
      `;
    } else if (state.adminTab === 'shipments') {
      main.innerHTML = renderShipmentsFullTable('admin', 'Seluruh Data Pengiriman di Sistem');
    } else if (state.adminTab === 'products') {
      main.innerHTML = `
        <div class="section-bar">
          <div class="section-title">Direktori Komoditas Kopi Nusantara (${state.exportProducts.length} Item · Non-Marketplace Tanpa Harga)</div>
        </div>
        <div class="surface-card table-card">
          <div class="table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Nama Komoditas Kopi</th>
                  <th>Pulau &amp; Daerah</th>
                  <th>Jenis Biji</th>
                  <th>Pelabuhan Muat</th>
                  <th>Pengirim (Shipper)</th>
                </tr>
              </thead>
              <tbody>
                ${state.exportProducts.map(p => `
                  <tr>
                    <td><strong>${esc(p.name)}</strong><div class="muted-sm">${esc(p.process_method || '')}</div></td>
                    <td><span class="badge badge-sky">${esc(p.island)}</span><div class="muted-sm">${esc(p.region)}</div></td>
                    <td>${esc(p.bean_type)}</td>
                    <td class="mono" style="font-size:12.5px;">${esc(p.origin_location?.code || '—')} · ${esc(p.origin_location?.name || '—')}</td>
                    <td>
                      <a class="text-link" onclick="MeridianApp.openUserProfileModal(${p.shipper_id})">${esc(p.shipper?.name || 'Pengirim')}</a>
                      ${p.shipper?.is_verified ? ' <span class="verified-badge">Terverifikasi</span>' : ''}
                    </td>
                  </tr>`).join('')}
              </tbody>
            </table>
          </div>
        </div>
      `;
    } else if (state.adminTab === 'locations') {
      const editL = state.editingLocationId ? state.locations.find(l => l.id === state.editingLocationId) : null;
      const locForm = editL
        ? `
          <div class="surface-card card-pad" style="margin-bottom:18px;border-color:var(--primary);">
            <div class="section-bar"><div class="section-title">Edit Lokasi: ${esc(editL.code)}</div><button type="button" class="btn btn-outline btn-sm" onclick="MeridianApp.editAdminLocation(null)">Batal</button></div>
            <form onsubmit="MeridianApp.handleAdminUpdateLocation(event, ${editL.id})">
              <div class="form-grid-4">
                <div class="field"><label>Kode</label><input id="adm-el-code" class="input mono" required value="${esc(editL.code)}"></div>
                <div class="field"><label>Nama Pelabuhan / Bandara</label><input id="adm-el-name" class="input" required value="${esc(editL.name)}"></div>
                <div class="field"><label>Tipe</label><select id="adm-el-type" class="select">${['PORT', 'AIRPORT', 'HUB', 'WAREHOUSE'].map(t => `<option value="${t}" ${editL.type === t ? 'selected' : ''}>${t === 'AIRPORT' ? 'Bandara Pesawat' : 'Pelabuhan Kapal'}</option>`).join('')}</select></div>
                <div class="field"><label>Pulau / Wilayah</label><input id="adm-el-island" class="input" value="${esc(editL.island || '')}"></div>
              </div>
              <div class="form-grid-3">
                <div class="field"><label>Negara</label><input id="adm-el-country" class="input" required value="${esc(editL.country)}"></div>
                <div class="field"><label>Lintang (Latitude)</label><input id="adm-el-lat" type="number" step="0.0001" class="input mono" required value="${editL.latitude}"></div>
                <div class="field"><label>Bujur (Longitude)</label><input id="adm-el-lng" type="number" step="0.0001" class="input mono" required value="${editL.longitude}"></div>
              </div>
              <button type="submit" class="btn btn-primary">Simpan Perubahan Lokasi</button>
            </form>
          </div>`
        : `
          <div class="surface-card card-pad" style="margin-bottom:18px;">
            <div class="section-title" style="margin-bottom:12px;">Tambah Pelabuhan Kapal / Bandara Kargo Mitra</div>
            <form onsubmit="MeridianApp.handleAdminAddLocation(event)">
              <div class="form-grid-4">
                <div class="field"><label>Kode Resmi <span class="req">*</span></label><input id="adm-l-code" class="input mono" required maxlength="10" placeholder="IDSUB"></div>
                <div class="field"><label>Nama Lokasi <span class="req">*</span></label><input id="adm-l-name" class="input" required placeholder="Pelabuhan Tanjung Perak"></div>
                <div class="field"><label>Jalur</label><select id="adm-l-type" class="select"><option value="PORT">Pelabuhan Kapal (Laut)</option><option value="AIRPORT">Bandara Kargo (Udara)</option></select></div>
                <div class="field"><label>Pulau / Wilayah</label><input id="adm-l-island" class="input" placeholder="Sumatra / Jawa / Internasional"></div>
              </div>
              <div class="form-grid-3">
                <div class="field"><label>Negara <span class="req">*</span></label><input id="adm-l-country" class="input" required value="Indonesia"></div>
                <div class="field"><label>Lintang <span class="req">*</span></label><input id="adm-l-lat" type="number" step="0.0001" class="input mono" required placeholder="-7.2333"></div>
                <div class="field"><label>Bujur <span class="req">*</span></label><input id="adm-l-lng" type="number" step="0.0001" class="input mono" required placeholder="112.7500"></div>
              </div>
              <button type="submit" class="btn btn-primary">+ Tambah ke Jaringan Peta</button>
            </form>
          </div>`;

      main.innerHTML = locForm + `
        <div class="surface-card table-card">
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Kode</th><th>Nama Pelabuhan / Bandara</th><th>Pulau / Wilayah</th><th>Jalur</th><th>Koordinat</th><th>Aksi</th></tr></thead>
              <tbody>
                ${state.locations.map(l => `
                  <tr>
                    <td class="mono" style="font-weight:700;color:var(--primary);">${esc(l.code)}</td>
                    <td><strong>${esc(l.name)}</strong><div class="muted-sm">${esc(l.country)}</div></td>
                    <td><span class="badge ${l.island === 'Internasional' ? 'badge-neutral' : 'badge-sky'}">${esc(l.island || '—')}</span></td>
                    <td><span class="badge ${l.type === 'AIRPORT' ? 'badge-indigo' : 'badge-ok'}">${l.type === 'AIRPORT' ? 'Bandara Pesawat' : 'Pelabuhan Kapal'}</span></td>
                    <td class="mono muted-sm">${Number(l.latitude).toFixed(3)}, ${Number(l.longitude).toFixed(3)}</td>
                    <td>
                      <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline" onclick="MeridianApp.openMapAtPort(${l.latitude}, ${l.longitude}, '${esc(l.name)}', '${esc(l.code)}')">Lihat Peta</button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="MeridianApp.editAdminLocation(${l.id})">Edit</button>
                      </div>
                    </td>
                  </tr>`).join('')}
              </tbody>
            </table>
          </div>
        </div>
      `;
    } else if (state.adminTab === 'carriers') {
      const editC = state.editingCarrierId ? state.adminCarriers.find(c => c.id === state.editingCarrierId) : null;
      const carForm = editC
        ? `
          <div class="surface-card card-pad" style="margin-bottom:18px;border-color:var(--primary);">
            <div class="section-bar"><div class="section-title">Edit Mitra: ${esc(editC.code)}</div><button type="button" class="btn btn-outline btn-sm" onclick="MeridianApp.editAdminCarrier(null)">Batal</button></div>
            <form onsubmit="MeridianApp.handleAdminUpdateCarrier(event, ${editC.id})">
              <div class="form-grid-4">
                <div class="field"><label>Kode</label><input id="adm-ec-code" class="input mono" required value="${esc(editC.code)}"></div>
                <div class="field"><label>Nama Mitra</label><input id="adm-ec-name" class="input" required value="${esc(editC.name)}"></div>
                <div class="field"><label>Jalur</label><select id="adm-ec-mode" class="select">${['SEA', 'AIR', 'LAND'].map(m => `<option value="${m}" ${editC.transport_mode === m ? 'selected' : ''}>${esc(MODE_ID[m])}</option>`).join('')}</select></div>
                <div class="field"><label>Email Kontak</label><input id="adm-ec-email" type="email" class="input" value="${esc(editC.contact_email || '')}"></div>
              </div>
              <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </form>
          </div>`
        : `
          <div class="surface-card card-pad" style="margin-bottom:18px;">
            <div class="section-title" style="margin-bottom:12px;">Tambah Mitra Kapal Laut / Pesawat Udara / Armada Truk</div>
            <form onsubmit="MeridianApp.handleAdminAddCarrier(event)">
              <div class="form-grid-4">
                <div class="field"><label>Kode <span class="req">*</span></label><input id="adm-c-code" class="input mono" required maxlength="10" placeholder="SPIL"></div>
                <div class="field"><label>Nama Perusahaan <span class="req">*</span></label><input id="adm-c-name" class="input" required placeholder="Salam Pacific Indonesia Lines"></div>
                <div class="field"><label>Jalur</label><select id="adm-c-mode" class="select"><option value="SEA">Kapal Laut (Mitra)</option><option value="AIR">Pesawat Kargo (Mitra)</option><option value="LAND">Truk Darat (Armada Sendiri)</option></select></div>
                <div class="field"><label>Email Kontak</label><input id="adm-c-email" type="email" class="input" placeholder="cargo@perusahaan.id"></div>
              </div>
              <button type="submit" class="btn btn-primary">+ Tambah Mitra</button>
            </form>
          </div>`;

      main.innerHTML = carForm + `
        <div class="surface-card table-card">
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Kode</th><th>Nama Mitra Pengangkut</th><th>Jalur</th><th>Kontak</th><th>Status</th><th>Aksi</th></tr></thead>
              <tbody>
                ${state.adminCarriers.map(c => `
                  <tr>
                    <td class="mono" style="font-weight:700;color:var(--primary);">${esc(c.code)}</td>
                    <td><strong>${esc(c.name)}</strong><div class="muted-sm">${c.shipments_count || 0} pengiriman tercatat</div></td>
                    <td>${modeBadge(c.transport_mode)}</td>
                    <td class="muted-sm">${esc(c.contact_email || '—')}</td>
                    <td><span class="badge ${c.is_active ? 'badge-ok' : 'badge-danger'}">${c.is_active ? 'Aktif' : 'Nonaktif'}</span></td>
                    <td>
                      <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline" onclick="MeridianApp.editAdminCarrier(${c.id})">Edit</button>
                        <button type="button" class="btn btn-sm ${c.is_active ? 'btn-danger-soft' : 'btn-ok'}" onclick="MeridianApp.handleAdminToggleCarrier(${c.id})">${c.is_active ? 'Nonaktifkan' : 'Aktifkan'}</button>
                      </div>
                    </td>
                  </tr>`).join('')}
              </tbody>
            </table>
          </div>
        </div>
      `;
    } else if (state.adminTab === 'support') {
      main.innerHTML = `
        <div class="section-bar">
          <div class="section-title">Kotak Bantuan &amp; Pengaduan Masuk (${state.supportTickets.length})</div>
        </div>
        ${state.supportTickets.length === 0
          ? `<div class="empty-box"><div class="empty-title">Belum Ada Laporan Bantuan Masuk</div><div class="empty-desc">Pesan yang dikirim pengguna melalui tombol <strong>Bantuan Admin</strong> akan tampil di sini agar dapat Anda tanggapi langsung.</div></div>`
          : state.supportTickets.map(t => `
              <div class="surface-card card-pad" style="margin-bottom:14px;">
                <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:8px;">
                  <div>
                    <span class="mono" style="font-weight:700;color:var(--primary);">${esc(t.ticket_code)}</span>
                    <span class="badge badge-neutral" style="margin-left:6px;">${esc(t.category)}</span>
                    <span class="badge ${t.status === 'RESOLVED' ? 'badge-ok' : 'badge-warn'}" style="margin-left:6px;">${t.status === 'RESOLVED' ? 'Selesai Dijawab' : 'Menunggu Balasan Admin'}</span>
                  </div>
                  <span class="mono muted-sm">${fmtDateTime(t.created_at)}</span>
                </div>
                <div style="font-size:15px;font-weight:700;margin-bottom:4px;">${esc(t.subject)}</div>
                <div class="muted-sm" style="margin-bottom:8px;">Pengirim: <strong>${esc(t.guest_name)}</strong> (${esc(ROLE_ID[t.user_role] || t.user_role || 'Publik')}) &middot; Kontak: ${esc(t.guest_contact)}</div>
                <div style="background:var(--bg-subtle);padding:12px;border-radius:8px;border:1px solid var(--border);margin-bottom:12px;">${esc(t.message)}</div>
                ${t.admin_reply
                  ? `<div class="alert-box alert-ok" style="margin-bottom:0;"><strong>Balasan Admin (${fmtDateTime(t.replied_at)}):</strong><br>${esc(t.admin_reply)}</div>`
                  : `<form onsubmit="MeridianApp.handleAdminReplyTicket(event, ${t.id})" style="display:flex;gap:10px;flex-wrap:wrap;">
                       <input id="reply-ticket-${t.id}" class="input" style="flex:1;min-width:220px;" required placeholder="Tulis balasan bantuan untuk pengguna ini...">
                       <button type="submit" class="btn btn-primary">Kirim Balasan &amp; Selesaikan</button>
                     </form>`}
              </div>`).join('')}
      `;
    } else if (state.adminTab === 'audit') {
      main.innerHTML = `
        <div class="surface-card table-card">
          <div class="card-pad" style="padding-bottom:12px;"><div class="section-title">Riwayat Aktivitas &amp; Keamanan Sistem (${state.auditLogs.length})</div></div>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Waktu</th><th>Pelaku</th><th>Aksi</th><th>Objek</th><th>IP</th></tr></thead>
              <tbody>
                ${state.auditLogs.length === 0
                  ? `<tr><td colspan="5" style="text-align:center;color:var(--ink-3);padding:24px;">Belum ada catatan audit.</td></tr>`
                  : state.auditLogs.map(l => `
                      <tr>
                        <td class="mono muted-sm">${fmtDateTime(l.created_at)}</td>
                        <td><strong>${esc(l.user?.name || 'Sistem')}</strong><div class="muted-sm">${esc(ROLE_ID[l.user?.role] || '')}</div></td>
                        <td><span class="badge badge-neutral">${esc(l.action)}</span></td>
                        <td class="mono muted-sm">${esc((l.model_type || '').split('\\').pop())} #${esc(l.model_id)}</td>
                        <td class="mono muted-sm">${esc(l.ip_address || '-')}</td>
                      </tr>`).join('')}
              </tbody>
            </table>
          </div>
        </div>
      `;
    } else if (state.adminTab === 'profile') {
      main.innerHTML = renderProfileEditorHtml();
    }
  }

  /* ========================================================================
     DASHBOARD 2: OPERATOR CABANG (/operator) — FULL-SCREEN WORKSPACE
     ======================================================================== */
  function renderOperatorConsole() {
    const main = document.getElementById('console-main');
    const branchInfo = state.user?.branch_location
      ? `Cabang Tugas: ${state.user.branch_location.name}`
      : 'Semua Pelabuhan & Bandara 5 Pulau';
    setHeader('Meja Kerja Operator Cabang (Layar Penuh)', branchInfo);

    if (state.operatorTab === 'pickups') {
      const pending = state.pickupRequests.filter(p => p.status !== 'CANCELLED');
      main.innerHTML = `
        <div class="kpi-grid">
          <div class="kpi-card">
            <div class="kpi-label">Panggilan Cek Fisik Masuk</div>
            <div class="kpi-val" style="color:var(--warn);">${pending.filter(p => p.status === 'PENDING_INSPECTION').length}</div>
            <div class="kpi-sub">Dari tombol Buat Pengiriman Shipper</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Armada Truk Sedang Menjemput</div>
            <div class="kpi-val" style="color:var(--primary);">${pending.filter(p => p.status === 'OPERATOR_DISPATCHED').length}</div>
            <div class="kpi-sub">Mobil cabang menuju lokasi Pengirim</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Total Kargo Aktif</div>
            <div class="kpi-val">${state.stats.total_shipments ?? 0}</div>
            <div class="kpi-sub">${state.stats.in_transit ?? 0} sedang dalam perjalanan</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Gangguan Rute Aktif</div>
            <div class="kpi-val" style="color:${(state.stats.open_exceptions || 0) > 0 ? 'var(--danger)' : 'var(--ok)'};">${state.stats.open_exceptions ?? 0}</div>
            <div class="kpi-sub">Ambang keterlambatan &ge; ${state.delayThresholdHours} jam</div>
          </div>
        </div>

        <div class="section-bar">
          <div class="section-title">Panggilan Penjemputan &amp; Cek Fisik dari Pengirim (Shipper)</div>
          <div class="btn-group">
            ${state.user?.location_id ? `
              <button type="button" class="btn btn-sm ${!state.operatorBranchOnly ? 'btn-primary' : 'btn-outline'}" onclick="MeridianApp.setOperatorBranchFilter(false)">Semua Cabang</button>
              <button type="button" class="btn btn-sm ${state.operatorBranchOnly ? 'btn-primary' : 'btn-outline'}" onclick="MeridianApp.setOperatorBranchFilter(true)">Cabang Saya (${esc(state.user.branch_location?.code || '')})</button>` : ''}
            <button type="button" class="btn btn-sm btn-primary" onclick="MeridianApp.setOperatorTab('create')">+ Input Barang Langsung</button>
          </div>
        </div>

        ${pending.length === 0
          ? `<div class="empty-box">
               <div class="empty-title">Belum Ada Panggilan Cek Fisik dari Pengirim</div>
               <div class="empty-desc">Saat Pengirim (Shipper) menekan tombol <strong>Buat Pengiriman</strong>, lokasi penjemputan akan muncul di sini agar armada mobil cabang kita berangkat mengecek barang. Anda juga bisa langsung menginput barang baru.</div>
               <button type="button" class="btn btn-primary" onclick="MeridianApp.setOperatorTab('create')">+ Buka Formulir Input Barang Baru</button>
             </div>`
          : pending.map(p => {
              const pInfo = PICKUP_ID[p.status] || { text: p.status, cls: 'badge-neutral' };
              return `
                <div class="surface-card card-pad" style="margin-bottom:14px;">
                  <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:10px;">
                    <div>
                      <span class="mono" style="font-weight:800;color:var(--primary);">${esc(p.request_code)}</span>
                      <span class="badge ${pInfo.cls}" style="margin-left:6px;">${esc(pInfo.text)}</span>
                      ${modeBadge(p.preferred_mode)}
                    </div>
                    <span class="mono muted-sm">${fmtDateTime(p.created_at)}</span>
                  </div>
                  <div class="form-grid-3" style="margin-bottom:12px;">
                    <div>
                      <div class="muted-sm">Pengirim &amp; Lokasi Jemput</div>
                      <strong><a class="text-link" onclick="MeridianApp.openUserProfileModal(${p.shipper_id})">${esc(p.shipper?.name || 'Pengirim')}</a></strong>
                      <div style="font-size:13px;">${esc(p.pickup_city)} — ${esc(p.pickup_address)}</div>
                    </div>
                    <div>
                      <div class="muted-sm">Barang &amp; Kesepakatan Jaminan</div>
                      <strong>${esc(p.commodity_summary)}</strong>
                      <div style="font-size:13px;">Pembeli: ${esc(p.buyer?.name || 'Belum dipilih')} · Jaminan: <strong>${fmtMoney(p.agreed_escrow_usd)}</strong></div>
                    </div>
                    <div>
                      <div class="muted-sm">Cabang Muat &rarr; Tujuan &amp; Target</div>
                      <strong>${esc(p.branch_location?.name || '—')}</strong>
                      <div style="font-size:13px;">&rarr; ${esc(p.destination_location?.name || '—')} ${p.target_days ? `(Target ${p.target_days} hari)` : ''}</div>
                    </div>
                  </div>
                  ${p.scheduled_note ? `<div class="muted-sm" style="margin-bottom:10px;">Catatan janjian: "${esc(p.scheduled_note)}"</div>` : ''}
                  <div class="btn-group" style="flex-wrap:wrap;">
                    ${p.status === 'PENDING_INSPECTION' ? `<button type="button" class="btn btn-sm btn-primary" onclick="MeridianApp.handleDispatchTruck(${p.id})">Kirim Mobil Cabang ke Lokasi Shipper</button>` : ''}
                    ${p.status !== 'CONVERTED_TO_SHIPMENT' ? `<button type="button" class="btn btn-sm btn-ok" onclick="MeridianApp.prefillShipmentFromPickup(${p.id})">Selesai Cek Fisik &rarr; Input Manifest Resmi</button>` : `<span class="badge badge-ok">Resi Resmi: ${esc(p.shipment?.tracking_number || 'Tercatat')}</span>`}
                    <button type="button" class="btn btn-sm btn-outline" onclick="MeridianApp.startChatWith(${p.shipper_id})">Chat Pengirim</button>
                  </div>
                </div>`;
            }).join('')}
      `;
    } else if (state.operatorTab === 'create') {
      const pkp = state.prefillPickupId ? state.pickupRequests.find(x => x.id === state.prefillPickupId) : null;
      const mode = pkp?.preferred_mode || 'SEA';
      const today = new Date();
      const dep = new Date(today.getTime() + 86400000).toISOString().slice(0, 10);
      const arrDays = mode === 'AIR' ? (pkp?.target_days || 5) : 18;
      const arr = new Date(today.getTime() + arrDays * 86400000).toISOString().slice(0, 10);
      const defaultIso = generateIso6346('MSKU');
      const defaultOriginId = pkp?.branch_location_id || state.user?.location_id || state.locations[0]?.id;
      const defaultDestId = pkp?.destination_location_id || state.locations.find(l => l.code === 'USLAX')?.id || state.locations.find(l => l.code === 'NLRTM')?.id;
      const defaultShipperId = pkp?.shipper_id || state.customers.find(c => (c.email || '').includes('shipper'))?.id || state.customers[0]?.id;
      const defaultBuyerId = pkp?.buyer_id || state.customers.find(c => (c.email || '').includes('buyer'))?.id || state.customers[1]?.id || state.customers[0]?.id;

      main.innerHTML = `
        <div class="surface-card card-pad">
          <div class="section-bar">
            <div>
              <div class="section-title">Formulir Input Barang &amp; Verifikasi Fisik Resmi (Layar Penuh)</div>
              <div class="muted-sm">Hanya Operator Cabang yang berwenang mencatat data resmi setelah memeriksa fisik barang untuk mencegah penipuan.</div>
            </div>
            ${pkp ? `<span class="badge badge-sky">Dari Panggilan: ${esc(pkp.request_code)} (${esc(pkp.pickup_city)})</span>` : ''}
          </div>

          <form id="op-create-form" onsubmit="MeridianApp.handleNewShipmentSubmit(event)">
            <input type="hidden" id="ns-pickup-req-id" value="${pkp ? pkp.id : ''}">
            <input type="hidden" id="ns-pickup-lat" value="${pkp?.pickup_latitude || ''}">
            <input type="hidden" id="ns-pickup-lng" value="${pkp?.pickup_longitude || ''}">

            <div class="form-grid-2" style="gap:20px;align-items:start;">
              <!-- KOLOM KIRI: SPESIFIKASI BARANG & VERIFIKASI FISIK -->
              <div style="background:var(--bg-subtle);padding:18px;border-radius:12px;border:1px solid var(--border);">
                <div style="font-weight:800;font-size:14px;color:var(--primary);margin-bottom:12px;">1. Hasil Penimbangan &amp; Spesifikasi Fisik Barang</div>
                <div class="form-grid-2">
                  <div class="field">
                    <label>Nama Komoditas / Barang <span class="req">*</span></label>
                    <input id="ns-item-name" class="input" required value="${esc(pkp?.commodity_summary || '')}" placeholder="Cth: Kopi Arabika Gayo Grade 1">
                  </div>
                  <div class="field">
                    <label>Kategori Barang</label>
                    <select id="ns-category" class="select">
                      <option value="Kopi Ekspor Nusantara">Kopi Ekspor Nusantara</option>
                      <option value="Rempah-Rempah">Rempah-Rempah</option>
                      <option value="Kakao & Hasil Bumi">Kakao &amp; Hasil Bumi</option>
                      <option value="Manufaktur & Umum">Manufaktur &amp; Umum</option>
                    </select>
                  </div>
                </div>

                <div class="form-grid-3">
                  <div class="field"><label>Jumlah Karung/Koli <span class="req">*</span></label><input id="ns-quantity" type="number" min="1" value="500" class="input mono" required></div>
                  <div class="field"><label>Berat Sah (kg) <span class="req">*</span></label><input id="ns-weight" type="number" step="0.01" min="1" value="18500" class="input mono" required></div>
                  <div class="field"><label>Volume (m&sup3;)</label><input id="ns-volume" type="number" step="0.1" min="0.1" value="58" class="input mono"></div>
                </div>

                <div class="form-grid-2">
                  <div class="field">
                    <label>Nomor Kontainer (Standar ISO 6346)</label>
                    <div style="display:flex;gap:6px;">
                      <input id="ns-container" class="input mono" value="${defaultIso}" oninput="MeridianApp.handleIsoLiveInput()">
                      <button type="button" class="btn btn-outline btn-sm" onclick="MeridianApp.fillGeneratedIso()">Buat Otomatis</button>
                    </div>
                    <span class="field-hint" id="ns-iso-hint" style="color:var(--ok);">Format ISO 6346 valid (${defaultIso})</span>
                  </div>
                  <div class="field">
                    <label>Nomor Segel Pengaman Kontainer</label>
                    <input id="ns-seal" class="input mono" value="SEAL-MRD-${Math.floor(1000 + Math.random() * 8999)}" placeholder="Cth: SEAL-BLW-8821">
                  </div>
                </div>

                <div class="field">
                  <label>Ceklis Verifikasi Fisik Pelabuhan (Anti-Penipuan)</label>
                  <div class="checklist-grid">
                    <label class="check-item"><input type="checkbox" id="chk-content" checked> Isi barang sesuai pesanan (bukan palsu)</label>
                    <label class="check-item"><input type="checkbox" id="chk-weight" checked> Berat tertimbang sah di timbangan</label>
                    <label class="check-item"><input type="checkbox" id="chk-dry" checked> Kemasan kering, bebas lembap/busuk</label>
                    <label class="check-item"><input type="checkbox" id="chk-seal" checked> Segel fisik terpasang utuh</label>
                    <label class="check-item"><input type="checkbox" id="chk-docs" checked> Surat izin ekspor awal lengkap</label>
                  </div>
                </div>
              </div>

              <!-- KOLOM KANAN: PIHAK TRANSAKSI, JALUR KAPAL/PESAWAT & RUTE -->
              <div style="background:var(--bg-subtle);padding:18px;border-radius:12px;border:1px solid var(--border);">
                <div style="font-weight:800;font-size:14px;color:var(--primary);margin-bottom:12px;">2. Pihak Transaksi, Jalur (Kapal/Pesawat) &amp; Jaminan</div>
                <div class="form-grid-2">
                  <div class="field">
                    <label>Pengirim (Shipper) <span class="req">*</span></label>
                    <select id="ns-customer" class="select" required>
                      ${state.customers.map(c => `<option value="${c.id}" ${Number(defaultShipperId) === c.id ? 'selected' : ''}>${esc(c.name)}</option>`).join('')}
                    </select>
                  </div>
                  <div class="field">
                    <label>Pembeli (Buyer)</label>
                    <select id="ns-buyer" class="select">
                      ${state.customers.map(c => `<option value="${c.id}" ${Number(defaultBuyerId) === c.id ? 'selected' : ''}>${esc(c.name)}</option>`).join('')}
                    </select>
                  </div>
                </div>

                <div class="form-grid-2">
                  <div class="field">
                    <label>Jalur Pengiriman Utama <span class="req">*</span></label>
                    <select id="ns-mode" class="select" onchange="MeridianApp.handleModeChangeFilter(this.value)">
                      <option value="SEA" ${mode === 'SEA' ? 'selected' : ''}>Kapal Laut — Mitra Pelayaran (Reguler)</option>
                      <option value="AIR" ${mode === 'AIR' ? 'selected' : ''}>Pesawat Kargo — Mitra Udara (Cepat &le; 7 Hari)</option>
                    </select>
                  </div>
                  <div class="field">
                    <label>Perusahaan Kapal / Maskapai Mitra <span class="req">*</span></label>
                    <select id="ns-carrier" class="select" required>
                      ${(() => {
                        const list = state.carriers.filter(c => c.transport_mode !== 'LAND');
                        const defCarrier = list.find(c => c.transport_mode === mode) || list[0];
                        return list.map(c => `<option value="${c.id}" ${defCarrier && c.id === defCarrier.id ? 'selected' : ''}>[${c.transport_mode === 'AIR' ? 'PESAWAT' : 'KAPAL'}] ${esc(c.name)}</option>`).join('');
                      })()}
                    </select>
                  </div>
                </div>

                <div class="field">
                  <label>Kota / Alamat Jemput Armada Truk Cabang (Opsional — Memunculkan Animasi Truk Darat)</label>
                  <input id="ns-pickup-addr" class="input" value="${esc(pkp?.pickup_city || 'Lhokseumawe, Aceh')}" placeholder="Contoh: Lhokseumawe, Aceh atau Curup, Bengkulu">
                </div>

                <div class="form-grid-2">
                  <div class="field">
                    <label>Pelabuhan / Bandara Asal (5 Pulau) <span class="req">*</span></label>
                    <select id="ns-origin" class="select" required>
                      ${state.locations.map(l => `<option value="${l.id}" ${Number(defaultOriginId) === l.id ? 'selected' : ''}>${esc(l.code)} · ${esc(l.name)}</option>`).join('')}
                    </select>
                  </div>
                  <div class="field">
                    <label>Pelabuhan / Bandara Tujuan <span class="req">*</span></label>
                    <select id="ns-dest" class="select" required>
                      ${state.locations.map(l => `<option value="${l.id}" ${Number(defaultDestId) === l.id ? 'selected' : ''}>${esc(l.code)} · ${esc(l.name)}</option>`).join('')}
                    </select>
                  </div>
                </div>

                <div class="form-grid-3">
                  <div class="field"><label>Tanggal Berangkat <span class="req">*</span></label><input id="ns-departure" type="date" value="${dep}" class="input mono" required></div>
                  <div class="field"><label>Perkiraan Tiba <span class="req">*</span></label><input id="ns-arrival" type="date" value="${arr}" class="input mono" required></div>
                  <div class="field"><label>Nilai Rekening Jaminan ($ USD)</label><input id="ns-contract-usd" type="number" step="100" min="0" value="${pkp?.agreed_escrow_usd ?? 25000}" class="input mono"></div>
                </div>

                <div class="field">
                  <label>Catatan Pemeriksaan Lapangan</label>
                  <textarea id="ns-notes" class="textarea" rows="2" placeholder="Opsional: hasil cek fisik, kondisi segel, atau catatan dokumen ekspor"></textarea>
                </div>
              </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:18px;">
              <button type="button" class="btn btn-outline" onclick="MeridianApp.clearPickupPrefill(); MeridianApp.setOperatorTab('fleet')">Batal</button>
              <button type="submit" id="btn-submit-shipment" class="btn btn-primary btn-lg">
                Simpan Manifest Resmi &amp; Kirim ke Pengirim untuk Disetujui
              </button>
            </div>
          </form>
        </div>
      `;
    } else if (state.operatorTab === 'fleet') {
      main.innerHTML = renderShipmentsFullTable('operator', 'Antrean Pengiriman Kargo');
    } else if (state.operatorTab === 'ops') {
      const activeList = state.shipments.filter(s => s.status !== 'DELIVERED' && s.status !== 'CANCELLED');
      const selId = state.selectedShipment?.id || (activeList[0]?.id ?? '');

      if (activeList.length === 0) {
        main.innerHTML = `<div class="empty-box"><div class="empty-title">Belum Ada Pengiriman Aktif</div><div class="empty-desc">Input barang terlebih dahulu di menu <strong>Input Barang Baru</strong> agar Anda bisa mencatat progres perjalanan, kendala, dan mencoba simulasi tombol <strong>Besok (+1 Hari)</strong>.</div><button type="button" class="btn btn-primary" onclick="MeridianApp.setOperatorTab('create')">+ Input Barang Baru</button></div>`;
      } else {
        main.innerHTML = `
          ${state.demoToolsEnabled ? `
            <div class="surface-card card-pad" style="margin-bottom:18px;border-color:#7dd3fc;background:#f0f9ff;">
              <div class="section-bar" style="margin-bottom:10px;">
                <div>
                  <div class="section-title" style="color:var(--primary-text);">Simulasi Percepat Hari untuk Demo ("Klik Sekali Jadi Besok")</div>
                  <div class="muted-sm">Setiap klik <strong>Besok (+1 Hari)</strong> akan memajukan jadwal perjalanan dan menggerakkan animasi Truk / Kapal / Pesawat di Peta 3D!</div>
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="MeridianApp.openMapModal(Number(document.getElementById('tt-shipment-id')?.value || 0))">Buka Peta 3D &amp; Lihat Animasi</button>
              </div>
              <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <select id="tt-shipment-id" class="select" style="flex:1;min-width:240px;">
                  ${activeList.map(s => `<option value="${s.id}" ${s.id === selId ? 'selected' : ''}>${esc(s.tracking_number)} · ${esc(s.cargo_description).slice(0, 42)} (Progres ${s.travel_progress_pct || 0}%)</option>`).join('')}
                </select>
                <button type="button" class="btn btn-primary mono" onclick="MeridianApp.runTimeTravel(24)">Besok (+1 Hari)</button>
                <button type="button" class="btn btn-outline mono" onclick="MeridianApp.runTimeTravel(6)">+6 Jam</button>
                <button type="button" class="btn btn-outline mono" onclick="MeridianApp.runTimeTravel(72)">+3 Hari</button>
                <button type="button" class="btn btn-outline mono" onclick="MeridianApp.runTimeTravel(168)">+7 Hari</button>
              </div>
            </div>` : ''}

          <div class="form-grid-2" style="align-items:start;">
            <div class="surface-card card-pad">
              <div class="section-title" style="margin-bottom:14px;">Catat Progres Perjalanan (Checkpoint)</div>
              <form onsubmit="MeridianApp.handleCheckpointSubmit(event)">
                <div class="field">
                  <label>Pilih Pengiriman</label>
                  <select id="cp-shipment-id" class="select" required>
                    ${activeList.map(s => `<option value="${s.id}" ${s.id === selId ? 'selected' : ''}>${esc(s.tracking_number)} · ${esc(s.cargo_description).slice(0, 35)}</option>`).join('')}
                  </select>
                </div>
                <div class="field">
                  <label>Tahap Perjalanan</label>
                  <select id="cp-event-type" class="select" required>
                    <option value="DEPARTURE">Berangkat dari Pelabuhan / Bandara</option>
                    <option value="IN_TRANSIT_CHECKPOINT">Singgah / Dalam Perjalanan</option>
                    <option value="CUSTOMS_INSPECTION">Pemeriksaan Bea Cukai</option>
                    <option value="ARRIVAL">Tiba di Pelabuhan / Bandara Tujuan</option>
                    <option value="OUT_FOR_DELIVERY">Diantar Truk ke Gudang Pembeli</option>
                    <option value="DELIVERED">Barang Diterima (Selesai)</option>
                  </select>
                </div>
                <div class="field">
                  <label>Lokasi Saat Ini</label>
                  <select id="cp-location" class="select" required>
                    ${state.locations.map(l => `<option value="${l.id}">${esc(l.code)} · ${esc(l.name)}</option>`).join('')}
                  </select>
                </div>
                <div class="field"><label>Catatan Petugas</label><textarea id="cp-remarks" class="textarea" rows="2" placeholder="Keterangan kondisi barang atau posisi kendaraan..."></textarea></div>
                <button type="submit" class="btn btn-primary" style="width:100%;">Simpan Progres Perjalanan</button>
              </form>
            </div>

            <div class="surface-card card-pad">
              <div class="section-title" style="margin-bottom:14px;color:var(--danger);">Laporkan Kendala / Keterlambatan</div>
              <form onsubmit="MeridianApp.handleExceptionSubmit(event)">
                <div class="field">
                  <label>Pengiriman Terdampak</label>
                  <select id="ex-shipment-id" class="select" required>
                    ${activeList.map(s => `<option value="${s.id}" ${s.id === selId ? 'selected' : ''}>${esc(s.tracking_number)} · ${esc(s.cargo_description).slice(0, 35)}</option>`).join('')}
                  </select>
                </div>
                <div class="field">
                  <label>Jenis Kendala</label>
                  <select id="ex-type" class="select" required>
                    <option value="CUSTOMS_HOLD">Ditahan Bea Cukai</option>
                    <option value="WEATHER_DELAY">Tunda Cuaca Buruk</option>
                    <option value="PORT_CONGESTION">Antrean Pelabuhan</option>
                    <option value="DAMAGED">Kerusakan Barang</option>
                    <option value="MISROUTED">Salah Rute</option>
                    <option value="DOCUMENTATION_ISSUE">Kendala Dokumen</option>
                  </select>
                </div>
                <div class="form-grid-2">
                  <div class="field"><label>Tingkat Kendala</label><select id="ex-severity" class="select"><option value="LOW">Ringan</option><option value="MEDIUM" selected>Sedang</option><option value="HIGH">Berat</option><option value="CRITICAL">Kritis</option></select></div>
                  <div class="field"><label>Perkiraan Tunda (Jam)</label><input id="ex-hours" type="number" min="1" max="720" value="18" class="input mono" required></div>
                </div>
                <div class="field"><label>Penjelasan Kendala</label><textarea id="ex-desc" class="textarea" rows="2" required placeholder="Alasan penahanan atau keterlambatan..."></textarea></div>
                <button type="submit" class="btn btn-danger" style="width:100%;">Catat Kendala &amp; Hitung Ulang Estimasi Tiba</button>
              </form>
            </div>
          </div>
        `;
      }
    } else if (state.operatorTab === 'chat') {
      main.innerHTML = renderChatWorkspaceHtml();
    } else if (state.operatorTab === 'profile') {
      main.innerHTML = renderProfileEditorHtml();
    }
  }

  /* ========================================================================
     DASHBOARD 3: PENGIRIM / SHIPPER (/shipper) — TOMBOL BUAT PENGIRIMAN & KONFIRMASI
     ======================================================================== */
  function renderShipperConsole() {
    const main = document.getElementById('console-main');
    setHeader('Portal Pengirim (Shipper / Eksportir)', 'Panggil Operator untuk cek fisik barang, setujui data manifest resmi, dan kelola komoditas kopi Anda');

    if (state.shipperTab === 'shipments') {
      const waitingApproval = state.shipments.filter(s => ['CREATED', 'BOOKED'].includes(s.status));
      const activeCalls = state.pickupRequests.filter(p => p.status !== 'CANCELLED' && p.status !== 'CONVERTED_TO_SHIPMENT');

      main.innerHTML = `
        <div class="surface-card card-pad" style="margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;background:linear-gradient(135deg,#ffffff 0%,#f0f9ff 100%);border-color:#bae6fd;">
          <div>
            <div style="font-size:17px;font-weight:800;color:var(--ink-1);">Sudah Sepakat Harga &amp; Jumlah dengan Pembeli di Chat?</div>
            <div class="muted-sm">Klik tombol di samping untuk memanggil <strong>Operator Cabang</strong> datang ke lokasi Anda menggunakan mobil kami guna mengecek fisik barang dan menginput data resminya.</div>
          </div>
          <button type="button" class="btn btn-primary btn-lg" onclick="MeridianApp.openCreatePickupModal()">
            + Buat Pengiriman (Panggil Operator)
          </button>
        </div>

        <div class="kpi-grid">
          <div class="kpi-card">
            <div class="kpi-label">Panggilan Cek Fisik Berjalan</div>
            <div class="kpi-val" style="color:var(--primary);">${activeCalls.length}</div>
            <div class="kpi-sub">Operator cabang datang ke lokasi Anda</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Perlu Persetujuan Anda</div>
            <div class="kpi-val" style="color:var(--warn);">${waitingApproval.length}</div>
            <div class="kpi-sub">Cek &amp; setujui data inputan Operator</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Total Pengiriman Resmi</div>
            <div class="kpi-val">${state.shipments.length}</div>
            <div class="kpi-sub">Tercatat dengan segel &amp; verifikasi fisik</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Saldo Dompet Pencairan</div>
            <div class="kpi-val" style="color:var(--ok);">${fmtMoney(state.wallet.balance_usd ?? state.user?.wallet_balance_usd ?? 0)}</div>
            <div class="kpi-sub"><a class="text-link" onclick="MeridianApp.toggleUtilityDrawer('wallet')">Tarik dana ke bank &rarr;</a></div>
          </div>
        </div>

        ${activeCalls.length > 0 ? `
          <div class="surface-card card-pad" style="margin-bottom:18px;">
            <div class="section-title" style="margin-bottom:12px;">Status Panggilan Operator Cabang (${activeCalls.length})</div>
            ${activeCalls.map(p => {
              const pInfo = PICKUP_ID[p.status] || { text: p.status, cls: 'badge-neutral' };
              return `
                <div style="padding:12px;border:1px solid var(--border);border-radius:10px;margin-bottom:10px;display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px;align-items:center;">
                  <div>
                    <span class="mono" style="font-weight:700;color:var(--primary);">${esc(p.request_code)}</span>
                    <span class="badge ${pInfo.cls}" style="margin-left:6px;">${esc(pInfo.text)}</span>
                    ${modeBadge(p.preferred_mode)}
                    <div style="font-weight:700;margin-top:4px;">${esc(p.commodity_summary)}</div>
                    <div class="muted-sm">Jemput di: ${esc(p.pickup_city)} &rarr; Cabang: ${esc(p.branch_location?.name || '—')} &rarr; Tujuan: ${esc(p.destination_location?.name || '—')}</div>
                  </div>
                  <div class="btn-group">
                    ${p.assigned_operator ? `<button type="button" class="btn btn-sm btn-outline" onclick="MeridianApp.startChatWith(${p.assigned_operator.id})">Chat Operator</button>` : ''}
                    <button type="button" class="btn btn-sm btn-danger-soft" onclick="MeridianApp.handleCancelPickup(${p.id})">Batalkan</button>
                  </div>
                </div>`;
            }).join('')}
          </div>` : ''}

        ${renderShipmentsFullTable('shipper', 'Daftar Pengiriman Resmi Anda')}
      `;
    } else if (state.shipperTab === 'products') {
      main.innerHTML = `
        <div class="surface-card card-pad" style="margin-bottom:18px;">
          <div class="section-title" style="margin-bottom:6px;">Tambah Komoditas Kopi ke Direktori Pembeli (Tanpa Harga)</div>
          <div class="muted-sm" style="margin-bottom:14px;">Menampilkan kopi daerah Anda agar Pembeli luar negeri menemukan profil Anda dan negosiasi harga langsung lewat Chat.</div>
          <form onsubmit="MeridianApp.handleAddExportProduct(event)">
            <div class="form-grid-3">
              <div class="field"><label>Nama Kopi <span class="req">*</span></label><input id="ep-name" class="input" required placeholder="Cth: Kopi Arabika Gayo Grade 1"></div>
              <div class="field">
                <label>Pulau Asal <span class="req">*</span></label>
                <select id="ep-island" class="select">
                  <option value="Sumatra">Pulau Sumatra</option>
                  <option value="Jawa">Pulau Jawa</option>
                  <option value="Kalimantan">Pulau Kalimantan</option>
                  <option value="Sulawesi">Pulau Sulawesi</option>
                  <option value="Papua">Pulau Papua</option>
                </select>
              </div>
              <div class="field"><label>Daerah / Kabupaten <span class="req">*</span></label><input id="ep-region" class="input" required placeholder="Cth: Curup, Rejang Lebong, Bengkulu"></div>
            </div>
            <div class="form-grid-4">
              <div class="field"><label>Jenis Biji</label><select id="ep-bean" class="select"><option value="Arabika">Arabika</option><option value="Robusta">Robusta</option><option value="Liberika">Liberika</option></select></div>
              <div class="field"><label>Proses Pengolahan</label><input id="ep-process" class="input" placeholder="Full Washed / Natural / Honey"></div>
              <div class="field"><label>Kapasitas Pasok</label><input id="ep-cap" class="input" placeholder="Cth: 15 Ton / Bulan"></div>
              <div class="field">
                <label>Pelabuhan Muat</label>
                <select id="ep-port" class="select">
                  ${state.locations.filter(l => l.island !== 'Internasional' && l.type === 'PORT').map(l => `<option value="${l.id}">${esc(l.island)} · ${esc(l.name)}</option>`).join('')}
                </select>
              </div>
            </div>
            <div class="form-grid-2">
              <div class="field"><label>Karakter Cita Rasa</label><input id="ep-notes" class="input" placeholder="Cth: Cokelat, rempah, karamel, body kuat"></div>
              <div class="field"><label>Deskripsi Singkat</label><input id="ep-desc" class="input" placeholder="Mutu ekspor, kadar air 11-12%, siap negosiasi via Chat"></div>
            </div>
            <button type="submit" class="btn btn-primary">+ Pajang di Direktori Kopi Pembeli</button>
          </form>
        </div>

        <div class="coffee-grid">
          ${state.exportProducts.map(p => `
            <div class="surface-card coffee-card">
              <div>
                <div style="display:flex;justify-content:space-between;gap:6px;">
                  <span class="badge badge-sky">Pulau ${esc(p.island)}</span>
                  <span class="badge badge-neutral">${esc(p.bean_type)}</span>
                </div>
                <div class="coffee-title">${esc(p.name)}</div>
                <div class="muted-sm" style="margin:4px 0 8px;">${esc(p.region)} · ${esc(p.supply_capacity || 'Siap Ekspor')}</div>
                <div style="font-size:13px;color:var(--ink-2);">${esc(p.tasting_notes || p.description || '')}</div>
              </div>
              <div style="display:flex;justify-content:space-between;align-items:center;padding-top:10px;border-top:1px solid var(--border);">
                <span class="muted-sm">Negosiasi harga via Chat</span>
                <button type="button" class="btn btn-sm btn-danger-soft" onclick="MeridianApp.handleDeleteExportProduct(${p.id})">Hapus</button>
              </div>
            </div>`).join('')}
        </div>
      `;
    } else if (state.shipperTab === 'chat') {
      main.innerHTML = renderChatWorkspaceHtml();
    } else if (state.shipperTab === 'branches') {
      main.innerHTML = `
        <div class="section-bar">
          <div>
            <div class="section-title">Kantor Cabang Operator 5 Pulau Indonesia (Armada Truk Darat Sendiri)</div>
            <div class="muted-sm">Hubungi langsung petugas cabang terdekat untuk koordinasi penjemputan &amp; pemeriksaan fisik barang.</div>
          </div>
          <button type="button" class="btn btn-primary" onclick="MeridianApp.openCreatePickupModal()">+ Buat Pengiriman Sekarang</button>
        </div>
        <div class="coffee-grid">
          ${state.operators.map(op => `
            <div class="surface-card card-pad card-hover">
              <div style="display:flex;justify-content:space-between;gap:6px;margin-bottom:8px;">
                <span class="badge badge-sky">Pulau ${esc(op.branch_location?.island || 'Indonesia')}</span>
                ${op.is_verified ? '<span class="verified-badge">Petugas Resmi</span>' : ''}
              </div>
              <div style="font-size:15.5px;font-weight:800;margin-bottom:4px;">${esc(op.name)}</div>
              <div class="muted-sm" style="margin-bottom:10px;">${esc(op.company_name || op.branch_location?.name || '')}</div>
              <div style="font-size:13px;color:var(--ink-2);margin-bottom:12px;">
                <div><strong>Cabang:</strong> ${esc(op.branch_location?.name || 'Semua Cabang')}</div>
                <div><strong>Telepon / WA:</strong> <span class="mono">${esc(op.phone || '—')}</span></div>
                <div><strong>Alamat:</strong> ${esc(op.address || op.city || '—')}</div>
              </div>
              <button type="button" class="btn btn-primary btn-sm" style="width:100%;" onclick="MeridianApp.startChatWith(${op.id})">Chat Operator Cabang Ini</button>
            </div>`).join('')}
        </div>
      `;
    } else if (state.shipperTab === 'profile') {
      main.innerHTML = renderProfileEditorHtml();
    }
  }

  /* ========================================================================
     DASHBOARD 4: PEMBELI / BUYER (/buyer) — DIREKTORI KOPI NUSANTARA & ESCROW
     ======================================================================== */
  function renderBuyerConsole() {
    const main = document.getElementById('console-main');
    setHeader('Portal Pembeli (Buyer / Importir)', 'Temukan komoditas kopi 5 pulau Indonesia beserta profil pengirim terverifikasi, negosiasi via Chat, dan amankan dana lewat Rekening Jaminan');

    if (state.buyerTab === 'directory') {
      const islands = ['ALL', 'Sumatra', 'Jawa', 'Kalimantan', 'Sulawesi', 'Papua'];
      main.innerHTML = `
        <div class="alert-box alert-info" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
          <div>
            <strong>Direktori Komoditas Ekspor (Bukan Marketplace):</strong> Harga tidak ditampilkan di sini—silakan lihat profil lengkap Pengirim lalu tekan <strong>Chat Pengirim</strong> untuk mendiskusikan harga &amp; jumlah secara langsung.
          </div>
          <button type="button" class="btn btn-sm btn-primary" onclick="MeridianApp.openRoutePlannerModal()">Cek Estimasi Jalur Kapal vs Pesawat</button>
        </div>

        <div class="section-bar">
          <div class="filter-chips">
            ${islands.map(isl => `
              <button type="button" class="chip-btn ${state.coffeeIslandFilter === isl ? 'active' : ''}" onclick="MeridianApp.setCoffeeIslandFilter('${isl}')">
                ${isl === 'ALL' ? 'Semua 5 Pulau' : 'Pulau ' + isl}
              </button>`).join('')}
          </div>
          <input type="text" class="input" style="max-width:280px;" value="${esc(state.coffeeSearch)}" placeholder="Cari kopi (Gayo, Curup, Wamena...)" oninput="MeridianApp.handleCoffeeSearch(this.value)">
        </div>

        ${state.exportProducts.length === 0
          ? `<div class="empty-box"><div class="empty-title">Komoditas Kopi Tidak Ditemukan</div><div class="empty-desc">Coba pilih pulau lain atau hapus kata pencarian.</div></div>`
          : `<div class="coffee-grid">
              ${state.exportProducts.map(p => `
                <div class="surface-card coffee-card card-hover">
                  <div>
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:6px;">
                      <span class="badge badge-sky">Pulau ${esc(p.island)}</span>
                      <span class="badge badge-neutral">${esc(p.bean_type)}</span>
                    </div>
                    <div class="coffee-title">${esc(p.name)}</div>
                    <div class="coffee-meta" style="margin:6px 0 10px;">
                      <span>Daerah: <strong>${esc(p.region)}</strong></span>
                      ${p.altitude_mdpl ? `<span>&middot; ${esc(p.altitude_mdpl)}</span>` : ''}
                    </div>
                    <div style="font-size:13px;color:var(--ink-2);margin-bottom:8px;">
                      <strong>Cita Rasa:</strong> ${esc(p.tasting_notes || 'Khas daerah asal')}
                    </div>
                    <div class="muted-sm" style="margin-bottom:10px;">
                      Proses: ${esc(p.process_method || 'Standar Ekspor')} &middot; Pasokan: ${esc(p.supply_capacity || 'Tersedia')} &middot; Muat: <strong>${esc(p.origin_location?.code || '—')}</strong>
                    </div>
                  </div>

                  <div>
                    <div class="coffee-shipper-strip" style="margin-bottom:10px;">
                      <div style="display:flex;justify-content:space-between;align-items:center;gap:6px;">
                        <strong>${esc(p.shipper?.company_name || p.shipper?.name || 'Pengirim')}</strong>
                        ${p.shipper?.is_verified ? '<span class="verified-badge">Terverifikasi</span>' : '<span class="badge badge-warn">Belum Verifikasi</span>'}
                      </div>
                      <div class="muted-sm" style="margin-top:3px;">
                        ${esc(p.shipper?.city || 'Indonesia')} &middot; Telp: <span class="mono">${esc(p.shipper?.phone || '—')}</span>
                      </div>
                    </div>

                    <div class="btn-group" style="width:100%;">
                      <button type="button" class="btn btn-outline btn-sm" style="flex:1;" onclick="MeridianApp.openUserProfileModal(${p.shipper_id})">Profil Pengirim</button>
                      <button type="button" class="btn btn-primary btn-sm" style="flex:1;" onclick="MeridianApp.startChatWith(${p.shipper_id}, ${p.id}, '${esc(p.name)}')">Chat Negosiasi</button>
                    </div>
                  </div>
                </div>`).join('')}
            </div>`}
      `;
    } else if (state.buyerTab === 'orders') {
      const bal = state.wallet.balance_usd ?? state.user?.wallet_balance_usd ?? 0;
      const needDeposit = state.shipments.filter(s => s.escrow && s.escrow.status === 'PENDING_DEPOSIT').length;
      const inTransit = state.shipments.filter(s => !['DELIVERED', 'CANCELLED'].includes(s.status)).length;
      main.innerHTML = `
        <div class="kpi-grid">
          <div class="kpi-card">
            <div class="kpi-label">Saldo Dompet Tersedia</div>
            <div class="kpi-val" style="color:var(--ok);">${fmtMoney(bal)}</div>
            <div class="kpi-sub"><a class="text-link" onclick="MeridianApp.toggleUtilityDrawer('wallet')">+ Top Up saldo simulasi</a></div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Menunggu Setor Jaminan</div>
            <div class="kpi-val" style="color:var(--warn);">${needDeposit}</div>
            <div class="kpi-sub">Dana ditahan aman hingga barang tiba</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Pengiriman Berjalan</div>
            <div class="kpi-val" style="color:var(--primary);">${inTransit}</div>
            <div class="kpi-sub">Terpantau dari truk, kapal hingga pesawat</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Pesanan Selesai</div>
            <div class="kpi-val">${state.shipments.filter(s => s.status === 'DELIVERED').length}</div>
            <div class="kpi-sub">Diterima &amp; dana sudah dicairkan</div>
          </div>
        </div>
        ${renderShipmentsFullTable('buyer', 'Daftar Pengiriman & Rekening Jaminan Anda')}
      `;
    } else if (state.buyerTab === 'chat') {
      main.innerHTML = renderChatWorkspaceHtml();
    } else if (state.buyerTab === 'profile') {
      main.innerHTML = renderProfileEditorHtml();
    }
  }

  /* ========================================================================
     SHARED FULL-WIDTH SHIPMENTS TABLE + DETAIL INSPECTOR
     ======================================================================== */
  function renderShipmentsFullTable(role, title) {
    return `
      <div class="surface-card table-card">
        <div class="card-pad" style="padding-bottom:14px;display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px;align-items:center;">
          <div class="section-title">${esc(title)} (${state.shipments.length})</div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <input type="text" class="input" style="width:260px;" value="${esc(state.searchQuery)}" placeholder="Cari resi, kontainer, komoditas..." oninput="MeridianApp.handleSearchInput(this.value)">
            ${role === 'operator' ? `<button type="button" class="btn btn-primary btn-sm" onclick="MeridianApp.setOperatorTab('create')">+ Input Barang Baru</button>` : ''}
          </div>
        </div>
        ${state.shipments.length === 0
          ? `<div style="padding:28px 20px;text-align:center;">
               <div class="empty-title">Belum Ada Data Pengiriman</div>
               <div class="empty-desc">Data pengiriman saat ini bersih (0) agar Anda bisa mengisi sendiri dari awal saat latihan atau presentasi demo.</div>
             </div>`
          : `<div class="table-wrap">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Resi &amp; Kontainer</th>
                    <th>Komoditas &amp; Jalur</th>
                    <th>Asal &rarr; Tujuan</th>
                    <th>Status &amp; Progres</th>
                    <th>Rekening Jaminan</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  ${state.shipments.map(s => {
                    const pct = s.travel_progress_pct ?? 10;
                    const needsShipperOk = role === 'shipper' && ['CREATED', 'BOOKED'].includes(s.status);
                    const needsBuyerPay = role === 'buyer' && s.escrow && s.escrow.status === 'PENDING_DEPOSIT';
                    const canBuyerReceive = role === 'buyer' && s.status !== 'DELIVERED' && s.status !== 'CREATED';
                    return `
                      <tr>
                        <td>
                          <div class="mono" style="font-weight:800;color:var(--primary);">${esc(s.tracking_number)}</div>
                          <div class="mono muted-sm">${esc(s.container_number || 'Non-Kontainer')} ${s.seal_number ? '· ' + esc(s.seal_number) : ''}</div>
                        </td>
                        <td>
                          <strong style="display:block;max-width:240px;">${esc(s.cargo_description)}</strong>
                          <div style="margin-top:4px;display:flex;gap:5px;flex-wrap:wrap;">
                            ${modeBadge(s.transport_mode || s.carrier?.transport_mode)}
                            <span class="muted-sm">${Number(s.weight_kg || 0).toLocaleString('id-ID')} kg</span>
                          </div>
                        </td>
                        <td>
                          <div class="mono" style="font-weight:700;font-size:12.5px;">${esc(s.origin_location?.code || '—')} &rarr; ${esc(s.destination_location?.code || '—')}</div>
                          <div class="muted-sm">Tiba: ${fmtDate(s.calculated_eta || s.expected_arrival)}</div>
                        </td>
                        <td style="min-width:170px;">
                          ${statusBadge(s.status, s.is_delayed)}
                          <div class="progress-track" style="margin-top:6px;"><div class="progress-fill ${s.status === 'DELIVERED' ? 'ok' : (s.is_delayed ? 'danger' : '')}" style="width:${pct}%"></div></div>
                        </td>
                        <td>
                          ${escrowBadge(s.escrow)}
                          ${s.escrow ? `<div class="mono" style="font-weight:700;font-size:12.5px;margin-top:4px;">${fmtMoney(s.escrow.amount_usd)}</div>` : ''}
                        </td>
                        <td>
                          <div class="btn-group" style="flex-wrap:wrap;">
                            <button type="button" class="btn btn-sm btn-outline" onclick="MeridianApp.openShipmentDetailModal(${s.id})">Detail</button>
                            <button type="button" class="btn btn-sm btn-soft-primary" onclick="MeridianApp.openMapModal(${s.id})">Peta 3D</button>
                            ${needsShipperOk ? `<button type="button" class="btn btn-sm btn-ok" onclick="MeridianApp.handleShipperConfirm(${s.id})">Setujui Data</button>` : ''}
                            ${needsBuyerPay ? `<button type="button" class="btn btn-sm btn-primary" onclick="MeridianApp.handleEscrowDeposit(${s.id})">Setor Jaminan</button>` : ''}
                            ${canBuyerReceive ? `<button type="button" class="btn btn-sm btn-ok" onclick="MeridianApp.handleBuyerConfirm(${s.id})">Barang Diterima</button>` : ''}
                          </div>
                        </td>
                      </tr>`;
                  }).join('')}
                </tbody>
              </table>
            </div>`}
      </div>
    `;
  }

  /* ========================================================================
     SHARED CHAT WORKSPACE (BUYER <-> SHIPPER <-> OPERATOR)
     ======================================================================== */
  function renderChatWorkspaceHtml() {
    const myId = state.user?.id;
    const activePeer = state.chatContacts.find(c => c.id === state.activeChatPeerId)
      || state.chatThreads.find(t => t.peer.id === state.activeChatPeerId)?.peer
      || null;

    return `
      <div class="chat-layout">
        <div class="surface-card chat-sidebar">
          <div style="padding:14px;border-bottom:1px solid var(--border);font-weight:800;font-size:14px;">
            Daftar Kontak &amp; Percakapan
          </div>
          <div class="chat-list">
            ${state.chatThreads.length > 0 ? `<div style="padding:8px 14px;font-size:11px;font-weight:700;color:var(--ink-3);background:var(--bg-subtle);">PERCAKAPAN TERBARU</div>` : ''}
            ${state.chatThreads.map(t => `
              <div class="chat-peer-row ${state.activeChatPeerId === t.peer.id ? 'active' : ''}" onclick="MeridianApp.selectChatPeer(${t.peer.id})">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:6px;">
                  <strong style="font-size:13px;">${esc(t.peer.name)}</strong>
                  ${t.unread_count > 0 ? `<span class="notif-dot-badge">${t.unread_count}</span>` : ''}
                </div>
                <div class="muted-sm" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(t.last_message)}</div>
              </div>`).join('')}

            <div style="padding:8px 14px;font-size:11px;font-weight:700;color:var(--ink-3);background:var(--bg-subtle);">KONTAK PENGGUNA &amp; OPERATOR CABANG</div>
            ${state.chatContacts.map(c => `
              <div class="chat-peer-row ${state.activeChatPeerId === c.id ? 'active' : ''}" onclick="MeridianApp.selectChatPeer(${c.id})">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:6px;">
                  <strong style="font-size:13px;">${esc(c.name)}</strong>
                  <span class="badge ${c.role === 'operator' ? 'badge-sky' : (c.role === 'admin' ? 'badge-indigo' : 'badge-neutral')}">${esc(ROLE_ID[c.role] || c.role)}</span>
                </div>
                <div class="muted-sm">${esc(c.branch_location?.name || c.company_name || c.city || c.email)}</div>
              </div>`).join('')}
          </div>
        </div>

        <div class="surface-card chat-window">
          ${activePeer
            ? `
              <div style="padding:14px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:10px;">
                <div>
                  <div style="font-weight:800;font-size:14.5px;">${esc(activePeer.name)} ${activePeer.is_verified ? '<span class="verified-badge">Terverifikasi</span>' : ''}</div>
                  <div class="muted-sm">${esc(ROLE_ID[activePeer.role] || activePeer.role)} &middot; ${esc(activePeer.company_name || activePeer.city || '')} &middot; Telp: ${esc(activePeer.phone || '—')}</div>
                </div>
                <button type="button" class="btn btn-sm btn-outline" onclick="MeridianApp.openUserProfileModal(${activePeer.id})">Lihat Profil Lengkap</button>
              </div>
              <div class="chat-messages" id="chat-messages-box">
                ${state.chatMessages.length === 0
                  ? `<div style="margin:auto;text-align:center;color:var(--ink-3);max-width:340px;">Belum ada pesan. Mulailah berdiskusi soal kualitas kopi, negosiasi harga, atau jadwal penjemputan barang.</div>`
                  : state.chatMessages.map(m => {
                      const mine = Number(m.sender_id) === Number(myId);
                      return `
                        <div class="chat-bubble ${mine ? 'mine' : 'theirs'}">
                          ${m.export_product ? `<div style="font-size:11px;font-weight:700;opacity:0.85;margin-bottom:4px;">Topik Kopi: ${esc(m.export_product.name)} (${esc(m.export_product.island)})</div>` : ''}
                          <div>${esc(m.message)}</div>
                          <div class="chat-bubble-meta">${esc(m.sender?.name || '')} &middot; ${fmtDateTime(m.created_at)}</div>
                        </div>`;
                    }).join('')}
              </div>
              <form class="chat-compose" onsubmit="MeridianApp.handleSendChatMessage(event)">
                <input id="chat-input-msg" class="input" required placeholder="Tulis pesan negosiasi atau koordinasi pengiriman..." autocomplete="off">
                <button type="submit" class="btn btn-primary">Kirim</button>
              </form>`
            : `<div style="margin:auto;padding:30px;text-align:center;color:var(--ink-3);">Pilih salah satu kontak di sebelah kiri (Pembeli, Pengirim, atau Operator Cabang) untuk memulai obrolan langsung.</div>`}
        </div>
      </div>
    `;
  }

  /* ========================================================================
     SHARED COMPLETE USER IDENTITY PROFILE EDITOR
     ======================================================================== */
  function renderProfileEditorHtml() {
    const u = state.user || {};
    return `
      <div class="surface-card card-pad" style="max-width:860px;">
        <div class="section-bar">
          <div>
            <div class="section-title">Profil &amp; Kelengkapan Identitas Akun</div>
            <div class="muted-sm">Identitas ini ditampilkan kepada Admin, Pembeli, Pengirim, dan Operator cabang untuk membangun kepercayaan transaksi.</div>
          </div>
          ${u.is_verified
            ? '<span class="verified-badge" style="font-size:12.5px;padding:5px 12px;">Identitas Terverifikasi Admin</span>'
            : '<span class="badge badge-warn" style="font-size:12.5px;padding:5px 12px;">Menunggu Verifikasi Admin</span>'}
        </div>

        <form onsubmit="MeridianApp.handleSaveProfile(event)">
          <div class="form-grid-2">
            <div class="field"><label>Nama Lengkap / Penanggung Jawab <span class="req">*</span></label><input id="pf-name" class="input" required value="${esc(u.name || '')}"></div>
            <div class="field"><label>Nama Perusahaan / Koperasi</label><input id="pf-company" class="input" value="${esc(u.company_name || '')}" placeholder="PT / CV / Koperasi"></div>
          </div>
          <div class="form-grid-2">
            <div class="field"><label>Nomor Telepon / WhatsApp Aktif</label><input id="pf-phone" class="input mono" value="${esc(u.phone || '')}" placeholder="+62 812 xxxx xxxx"></div>
            <div class="field"><label>Nomor Identitas / NIB / NPWP / Izin Usaha</label><input id="pf-idnum" class="input mono" value="${esc(u.identity_number || '')}" placeholder="NIB / NPWP / EIN"></div>
          </div>
          <div class="form-grid-2">
            <div class="field"><label>Kota / Kabupaten</label><input id="pf-city" class="input" value="${esc(u.city || '')}" placeholder="Cth: Lhokseumawe, Aceh"></div>
            <div class="field"><label>Negara</label><input id="pf-country" class="input" value="${esc(u.country || 'Indonesia')}"></div>
          </div>
          <div class="field"><label>Alamat Lengkap Gudang / Kantor</label><textarea id="pf-address" class="textarea" rows="2">${esc(u.address || '')}</textarea></div>
          <div class="field"><label>Deskripsi Singkat Usaha / Komoditas</label><textarea id="pf-bio" class="textarea" rows="2" placeholder="Ceritakan profil perusahaan atau komoditas kopi Anda...">${esc(u.bio || '')}</textarea></div>
          <button type="submit" class="btn btn-primary">Simpan Kelengkapan Profil</button>
        </form>
      </div>
    `;
  }

  /* ========================================================================
     PUBLIC TRACKING VIEW (/track/{number})
     ======================================================================== */
  function renderPublicTrackConsole() {
    const main = document.getElementById('console-main');
    setHeader('Pelacakan Resi Publik', 'Pantau perjalanan kargo ekspor tanpa perlu masuk akun');
    const s = state.selectedShipment;

    main.innerHTML = `
      <div class="surface-card card-pad" style="margin-bottom:18px;">
        <form style="display:flex;gap:10px;flex-wrap:wrap;" onsubmit="MeridianApp.handlePublicTrackInline(event)">
          <input id="pub-track-inline" class="input mono" style="flex:1;min-width:220px;" value="${esc(s?.tracking_number || '')}" placeholder="Masukkan nomor resi atau nomor kontainer ISO 6346...">
          <button type="submit" class="btn btn-primary">Cari Pengiriman</button>
          <button type="button" class="btn btn-outline" onclick="MeridianApp.navigate('home', '/')">&larr; Beranda</button>
        </form>
      </div>

      ${!s
        ? `<div class="empty-box"><div class="empty-title">Masukkan Nomor Resi untuk Melacak</div><div class="empty-desc">Contoh: nomor resi yang dibuat Operator Cabang setelah pemeriksaan fisik barang.</div></div>`
        : `
          <div class="surface-card card-pad">
            <div class="section-bar">
              <div>
                <div class="mono" style="font-size:18px;font-weight:800;color:var(--primary);">${esc(s.tracking_number)}</div>
                <div style="font-size:15px;font-weight:700;margin-top:4px;">${esc(s.cargo_description)}</div>
              </div>
              <div class="btn-group">
                ${statusBadge(s.status, s.is_delayed)}
                ${modeBadge(s.transport_mode || s.carrier?.transport_mode)}
                <button type="button" class="btn btn-sm btn-primary" onclick="MeridianApp.openMapModal(${s.id})">Lihat Peta Rute 3D</button>
              </div>
            </div>

            <div style="margin:14px 0 18px;">
              <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;">
                <span>Progres Perjalanan: <strong>${s.travel_progress_pct ?? 20}%</strong></span>
                <span>Perkiraan Tiba: <strong>${fmtDate(s.calculated_eta || s.expected_arrival)}</strong></span>
              </div>
              <div class="progress-track"><div class="progress-fill ${s.status === 'DELIVERED' ? 'ok' : (s.is_delayed ? 'danger' : '')}" style="width:${s.travel_progress_pct ?? 20}%"></div></div>
            </div>

            <div class="form-grid-3" style="margin-bottom:20px;">
              <div class="kpi-card"><div class="kpi-label">Asal</div><strong>${esc(s.origin_location?.name || '—')}</strong>${s.pickup_address ? `<div class="muted-sm">Jemput Truk: ${esc(s.pickup_address)}</div>` : ''}</div>
              <div class="kpi-card"><div class="kpi-label">Tujuan</div><strong>${esc(s.destination_location?.name || '—')}</strong></div>
              <div class="kpi-card"><div class="kpi-label">Cuaca Lokasi Saat Ini</div><strong>${s.live_weather?.current ? `${esc(s.live_weather.current.temperature_c)}°C · ${esc(s.live_weather.current.condition)}` : 'Cerah / Normal'}</strong></div>
            </div>

            <div class="section-title" style="margin-bottom:12px;">Riwayat Perjalanan Kargo</div>
            <div class="timeline">
              ${(s.tracking_events || []).length === 0
                ? `<div class="muted-sm">Menunggu pembaruan titik perjalanan pertama dari Operator Cabang.</div>`
                : (s.tracking_events || []).map(ev => `
                    <div class="timeline-item">
                      <div style="font-weight:700;font-size:13.5px;">${esc(EVENT_ID[ev.event_type] || ev.event_type)}</div>
                      <div class="muted-sm">${esc(ev.location?.name || '—')} &middot; ${fmtDateTime(ev.event_time)}</div>
                      ${ev.remarks ? `<div style="font-size:13px;margin-top:2px;">${esc(ev.remarks)}</div>` : ''}
                    </div>`).join('')}
            </div>
          </div>`}
    `;
  }

  /* ========================================================================
     MODALS: DETAIL SHIPMENT, BUAT PENGIRIMAN (SHIPPER), PROFIL, SURAT RILIS,
     KALKULATOR KAPAL VS PESAWAT, BANTUAN ADMIN & PETA 3D MULTIMODA
     ======================================================================== */
  function openModal(title, sub, bodyHtml) {
    document.getElementById('app-modal-title').textContent = title;
    document.getElementById('app-modal-sub').textContent = sub || '';
    document.getElementById('app-modal-body').innerHTML = bodyHtml;
    document.getElementById('app-modal-overlay')?.classList.remove('hidden');
  }

  function closeModal() {
    document.getElementById('app-modal-overlay')?.classList.add('hidden');
  }

  async function openShipmentDetailModal(id) {
    try {
      const res = await api(`/api/v1/shipments/${id}`);
      const s = res.data;
      state.selectedShipment = s;
      const role = state.page;
      const chk = s.verification_checklist || null;
      const canIssueDoc = ['operator', 'admin', 'shipper'].includes(role);
      const canResolve = ['operator', 'admin'].includes(role);

      const body = `
        <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:14px;">
          <div style="display:flex;gap:6px;flex-wrap:wrap;">
            ${statusBadge(s.status, s.is_delayed)}
            ${modeBadge(s.transport_mode || s.carrier?.transport_mode)}
            ${escrowBadge(s.escrow)}
          </div>
          <div class="btn-group">
            <button type="button" class="btn btn-sm btn-primary" onclick="MeridianApp.closeModal(); MeridianApp.openMapModal(${s.id})">Lihat Peta 3D</button>
            <button type="button" class="btn btn-sm btn-outline" onclick="MeridianApp.openReleaseDocModal(${s.id})">${s.release_document_number ? 'Lihat / Cetak Surat Rilis' : (canIssueDoc ? 'Terbitkan Surat Rilis' : 'Surat Rilis')}</button>
          </div>
        </div>

        <div class="form-grid-2" style="margin-bottom:14px;">
          <div style="padding:12px;border:1px solid var(--border);border-radius:10px;">
            <div class="muted-sm">Pengirim (Shipper)</div>
            <strong><a class="text-link" onclick="MeridianApp.openUserProfileModal(${s.customer_id})">${esc(s.customer?.name || '—')}</a></strong>
            <div class="muted-sm">Asal: ${esc(s.origin_location?.name || '—')}</div>
            ${s.pickup_address ? `<div class="muted-sm">Penjemputan Truk: ${esc(s.pickup_address)}</div>` : ''}
          </div>
          <div style="padding:12px;border:1px solid var(--border);border-radius:10px;">
            <div class="muted-sm">Pembeli (Buyer)</div>
            <strong>${s.buyer_id ? `<a class="text-link" onclick="MeridianApp.openUserProfileModal(${s.buyer_id})">${esc(s.buyer?.name || '—')}</a>` : '—'}</strong>
            <div class="muted-sm">Tujuan: ${esc(s.destination_location?.name || '—')}</div>
            <div class="muted-sm">Perkiraan Tiba: <strong>${fmtDate(s.calculated_eta || s.expected_arrival)}</strong></div>
          </div>
        </div>

        ${chk ? `
          <div class="alert-box alert-ok">
            <strong>Terverifikasi Fisik oleh Operator (${esc(chk.verified_by || 'Petugas Cabang')}):</strong>
            Isi sesuai · Berat ${Number(s.weight_kg || 0).toLocaleString('id-ID')} kg sah · Segel <span class="mono">${esc(s.seal_number || 'Utuh')}</span> · Kemasan kering &amp; aman.
          </div>` : ''}

        ${(s.exceptions || []).length > 0 ? `
          <div style="margin-bottom:14px;">
            <div style="font-weight:700;margin-bottom:8px;">Catatan Kendala (${s.exceptions.length})</div>
            ${s.exceptions.map(ex => `
              <div class="alert-box ${ex.status === 'RESOLVED' ? 'alert-ok' : 'alert-danger'}" style="margin-bottom:8px;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                  <strong>${esc(EXCEPTION_ID[ex.exception_type] || ex.exception_type)} (+${ex.impact_hours} Jam)</strong>
                  <span class="badge ${ex.status === 'RESOLVED' ? 'badge-ok' : 'badge-danger'}">${ex.status === 'RESOLVED' ? 'Selesai' : (ex.status === 'INVESTIGATING' ? 'Sedang Ditangani' : 'Aktif')}</span>
                </div>
                <div style="margin-top:4px;">${esc(ex.description)}</div>
                ${canResolve && ex.status !== 'RESOLVED' ? `
                  <div class="btn-group" style="margin-top:8px;">
                    ${ex.status === 'OPEN' ? `<button type="button" class="btn btn-sm btn-warn" onclick="MeridianApp.handleInvestigateException(${ex.id}, ${s.id})">Tandai Ditangani</button>` : ''}
                    <button type="button" class="btn btn-sm btn-ok" onclick="MeridianApp.handleResolveException(${ex.id}, ${s.id})">Selesaikan Kendala</button>
                  </div>` : ''}
              </div>`).join('')}
          </div>` : ''}

        <div style="font-weight:700;margin-bottom:8px;">Riwayat Perjalanan (${(s.tracking_events || []).length})</div>
        <div class="timeline" style="margin-bottom:14px;">
          ${(s.tracking_events || []).length === 0
            ? `<div class="muted-sm">Belum ada catatan titik perjalanan.</div>`
            : s.tracking_events.map(ev => `
                <div class="timeline-item">
                  <strong>${esc(EVENT_ID[ev.event_type] || ev.event_type)}</strong>
                  <div class="muted-sm">${esc(ev.location?.name || '—')} &middot; ${fmtDateTime(ev.event_time)}</div>
                  ${ev.remarks ? `<div style="font-size:13px;">${esc(ev.remarks)}</div>` : ''}
                </div>`).join('')}
        </div>

        <div class="btn-group" style="flex-wrap:wrap;padding-top:12px;border-top:1px solid var(--border);">
          ${role === 'shipper' && ['CREATED', 'BOOKED'].includes(s.status) ? `<button type="button" class="btn btn-ok" onclick="MeridianApp.handleShipperConfirm(${s.id}); MeridianApp.closeModal();">Setujui &amp; Konfirmasi Data Operator</button>` : ''}
          ${role === 'buyer' && s.escrow?.status === 'PENDING_DEPOSIT' ? `<button type="button" class="btn btn-primary" onclick="MeridianApp.handleEscrowDeposit(${s.id}); MeridianApp.closeModal();">Setor ke Rekening Jaminan (${fmtMoney(s.escrow.amount_usd)})</button>` : ''}
          ${role === 'buyer' && s.status !== 'DELIVERED' && s.status !== 'CREATED' ? `<button type="button" class="btn btn-ok" onclick="MeridianApp.handleBuyerConfirm(${s.id}); MeridianApp.closeModal();">Konfirmasi Barang Diterima (Cairkan Dana)</button>` : ''}
          ${role === 'buyer' && s.escrow && ['HELD_IN_ESCROW', 'FROZEN_ON_EXCEPTION'].includes(s.escrow.status) ? `<button type="button" class="btn btn-danger-soft" onclick="MeridianApp.handleEscrowDispute(${s.id})">Ajukan Sengketa ke Admin</button>` : ''}
          ${role === 'admin' ? `<button type="button" class="btn btn-danger-soft" onclick="MeridianApp.handleAdminDeleteShipment(${s.id})">Hapus Pengiriman</button>` : ''}
        </div>
      `;
      openModal(`Detail Pengiriman · ${s.tracking_number}`, s.cargo_description, body);
    } catch (e) {
      toast(e.message, 'err');
    }
  }

  /* Shipper "Buat Pengiriman" Modal -> Calls Branch Operator */
  function openCreatePickupModal() {
    const buyers = state.customers.filter(c => c.id !== state.user?.id);
    const idLocs = state.locations.filter(l => l.island !== 'Internasional');
    const worldLocs = state.locations.filter(l => l.island === 'Internasional');
    const defaultBuyer = buyers.find(b => (b.email || '').includes('buyer')) || buyers[0];
    const defaultBranch = idLocs.find(l => l.code === 'IDBLW') || idLocs[0];
    const defaultDest = worldLocs.find(l => l.code === 'USLAX') || worldLocs[0];

    const body = `
      <div class="alert-box alert-info">
        <strong>Aturan Anti-Penipuan:</strong> Anda cukup memanggil <strong>Operator Cabang</strong> di sini. Petugas akan datang ke lokasi Anda menggunakan mobil/truk kami untuk memeriksa fisik barang dan menginput manifest resminya.
      </div>
      <form onsubmit="MeridianApp.handleCreatePickupSubmit(event)">
        <div class="form-grid-2">
          <div class="field">
            <label>Pembeli (Buyer) Hasil Deal Chat <span class="req">*</span></label>
            <select id="pk-buyer" class="select" required>
              ${buyers.map(b => `<option value="${b.id}" ${defaultBuyer && b.id === defaultBuyer.id ? 'selected' : ''}>${esc(b.name)} (${esc(b.country || 'Luar Negeri')})</option>`).join('')}
            </select>
          </div>
          <div class="field">
            <label>Nominal Jaminan Kesepakatan ($ USD)</label>
            <input id="pk-escrow" type="number" min="0" step="100" value="25000" class="input mono">
          </div>
        </div>

        <div class="form-grid-2">
          <div class="field">
            <label>Kota / Daerah Lokasi Barang <span class="req">*</span></label>
            <input id="pk-city" class="input" required value="${esc(state.user?.city || 'Lhokseumawe, Aceh')}" placeholder="Contoh: Lhokseumawe, Aceh / Curup, Bengkulu">
          </div>
          <div class="field">
            <label>Ringkasan Barang untuk Dicek <span class="req">*</span></label>
            <input id="pk-commodity" class="input" required placeholder="Cth: Kopi Arabika Gayo Grade 1 (500 karung)">
          </div>
        </div>

        <div class="field">
          <label>Alamat Lengkap Lokasi Penjemputan Truk Operator <span class="req">*</span></label>
          <textarea id="pk-address" class="textarea" rows="2" required placeholder="Nama jalan, gudang, patokan agar mobil cabang kami mudah menemukan lokasi">${esc(state.user?.address || 'Jl. Merdeka Barat No. 45, Lhokseumawe, Aceh')}</textarea>
        </div>

        <div class="form-grid-2">
          <div class="field">
            <label>Target Lama Tiba Diminta Pembeli (Hari)</label>
            <input id="pk-days" type="number" min="1" max="90" value="5" class="input mono" oninput="MeridianApp.syncPickupModeHint(this.value)">
            <span class="field-hint" id="pk-mode-hint" style="color:var(--indigo);font-weight:600;">Target &le; 7 hari: Sistem otomatis merekomendasikan jalur PESAWAT KARGO (Bandara Mitra).</span>
          </div>
          <div class="field">
            <label>Pilihan Jalur Pengiriman</label>
            <select id="pk-mode" class="select">
              <option value="AIR">Pesawat Kargo (Cepat &le; 7 Hari)</option>
              <option value="SEA">Kapal Laut (Reguler &gt; 7 Hari)</option>
            </select>
          </div>
        </div>

        <div class="form-grid-2">
          <div class="field">
            <label>Pelabuhan / Bandara Cabang Pulau Terdekat <span class="req">*</span></label>
            <select id="pk-branch" class="select" required>
              ${idLocs.map(l => `<option value="${l.id}" ${defaultBranch && l.id === defaultBranch.id ? 'selected' : ''}>Pulau ${esc(l.island)} · ${esc(l.name)}</option>`).join('')}
            </select>
          </div>
          <div class="field">
            <label>Pelabuhan / Bandara Negara Tujuan</label>
            <select id="pk-dest" class="select">
              ${worldLocs.map(l => `<option value="${l.id}" ${defaultDest && l.id === defaultDest.id ? 'selected' : ''}>${esc(l.country)} · ${esc(l.name)}</option>`).join('')}
            </select>
          </div>
        </div>

        <div class="field">
          <label>Jadwal / Catatan Kedatangan Petugas</label>
          <input id="pk-note" class="input" placeholder="Cth: Barang siap dicek besok pagi jam 09.00 WIB">
        </div>

        <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">Kirim Panggilan ke Operator Cabang Sekarang</button>
      </form>
    `;
    openModal('Buat Pengiriman — Panggil Operator Cabang', 'Operator cabang akan datang mengecek fisik barang sebelum menginput manifest resmi', body);
  }

  function syncPickupModeHint(val) {
    const days = Number(val || 0);
    const modeSel = document.getElementById('pk-mode');
    const hint = document.getElementById('pk-mode-hint');
    if (days > 0 && days <= 7) {
      if (modeSel) modeSel.value = 'AIR';
      if (hint) hint.innerHTML = 'Target &le; 7 hari: Sistem otomatis memilih <strong>PESAWAT KARGO (Bandara Mitra)</strong> karena kapal laut memakan waktu berminggu-minggu.';
    } else {
      if (modeSel) modeSel.value = 'SEA';
      if (hint) hint.innerHTML = 'Target &gt; 7 hari: Cocok memakai <strong>KAPAL LAUT (Pelabuhan Mitra)</strong> yang lebih hemat untuk volume besar.';
    }
  }

  /* Route Calculator Modal for Buyer (Kapal vs Pesawat <= 7 Hari) */
  function openRoutePlannerModal() {
    const body = `
      <div class="field">
        <label>Dalam berapa hari Pembeli butuh kopi tiba di negara tujuan?</label>
        <input id="calc-days" type="number" min="1" max="90" value="7" class="input mono" oninput="MeridianApp.updateRouteCalc(this.value)">
      </div>
      <div id="calc-result" class="alert-box alert-info">
        <strong>Rekomendasi Sistem: JALUR PESAWAT KARGO (Bandara Mitra)</strong><br>
        Untuk target tiba &le; 7 hari (misal dari Lhokseumawe ke Amerika Serikat), mobil cabang kami mengantar ke Bandara Kualanamu (IDKNO) lalu terbang ke Bandara JFK / LAX dalam 3–6 hari.
      </div>
      <div class="muted-sm">Anda tetap bebas mendiskusikan pilihan Kapal atau Pesawat dengan Pengirim lewat fitur Chat sebelum Pengirim menekan tombol <strong>Buat Pengiriman</strong>.</div>
    `;
    openModal('Panduan Pilih Jalur: Pesawat vs Kapal Laut', 'Sistem otomatis membantu menentukan jalur terbaik sesuai kecepatan yang dibutuhkan Pembeli', body);
  }

  function updateRouteCalc(val) {
    const d = Number(val || 0);
    const box = document.getElementById('calc-result');
    if (!box) return;
    if (d > 0 && d <= 7) {
      box.className = 'alert-box alert-info';
      box.innerHTML = `<strong>Rekomendasi: JALUR PESAWAT KARGO (Bandara Mitra)</strong><br>Target ${d} hari tidak mungkin ditempuh kapal laut (3–6 minggu). Sistem mengarahkan lewat Bandara Kargo mitra 5 pulau menuju Bandara Tujuan (misal USJFK / USLAX).`;
    } else {
      box.className = 'alert-box alert-ok';
      box.innerHTML = `<strong>Rekomendasi: JALUR KAPAL LAUT (Pelabuhan Mitra)</strong><br>Dengan waktu ${d} hari, kontainer kapal laut jauh lebih efisien untuk volume besar dari Pelabuhan Utama 5 Pulau (Belawan, Priok, Pontianak, Makassar, Jayapura).`;
    }
  }

  /* Transparent Public Identity Card Modal */
  async function openUserProfileModal(userId) {
    try {
      const res = await api(`/api/v1/users/${userId}/public-profile`);
      const u = res.data;
      const body = `
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
          <div>
            <div style="font-size:17px;font-weight:800;">${esc(u.name)}</div>
            <div class="muted-sm">${esc(u.company_name || 'Perusahaan / Perorangan')}</div>
          </div>
          ${u.is_verified ? '<span class="verified-badge" style="padding:5px 12px;font-size:12px;">Terverifikasi Admin</span>' : '<span class="badge badge-warn">Belum Diverifikasi</span>'}
        </div>
        <div class="form-grid-2" style="margin-bottom:14px;">
          <div style="padding:10px 12px;background:var(--bg-subtle);border-radius:8px;border:1px solid var(--border);"><div class="muted-sm">Peran</div><strong>${esc(ROLE_ID[u.role] || u.role)}</strong></div>
          <div style="padding:10px 12px;background:var(--bg-subtle);border-radius:8px;border:1px solid var(--border);"><div class="muted-sm">Telepon / WhatsApp</div><strong class="mono">${esc(u.phone || 'Belum dilengkapi')}</strong></div>
          <div style="padding:10px 12px;background:var(--bg-subtle);border-radius:8px;border:1px solid var(--border);"><div class="muted-sm">Email</div><strong class="mono">${esc(u.email)}</strong></div>
          <div style="padding:10px 12px;background:var(--bg-subtle);border-radius:8px;border:1px solid var(--border);"><div class="muted-sm">No. Identitas / NIB / Izin</div><strong class="mono">${esc(u.identity_number || 'Belum dilengkapi')}</strong></div>
        </div>
        <div style="padding:12px;background:var(--bg-subtle);border-radius:8px;border:1px solid var(--border);margin-bottom:14px;">
          <div class="muted-sm">Alamat &amp; Wilayah</div>
          <div>${esc(u.address || '—')} (${esc(u.city || '—')}, ${esc(u.country || '—')})</div>
          ${u.bio ? `<div class="muted-sm" style="margin-top:6px;">${esc(u.bio)}</div>` : ''}
        </div>
        ${state.user && state.user.id !== u.id ? `<button type="button" class="btn btn-primary" style="width:100%;" onclick="MeridianApp.closeModal(); MeridianApp.startChatWith(${u.id})">Mulai Chat Langsung</button>` : ''}
      `;
      openModal('Kartu Identitas Pengguna', 'Transparansi profil untuk mencegah penipuan transaksi', body);
    } catch (e) {
      toast(e.message, 'err');
    }
  }

  /* Digital Release / Pickup Document Modal (e-B/L / Air Waybill / Telex Release) */
  async function openReleaseDocModal(shipmentId) {
    try {
      const res = await api(`/api/v1/shipments/${shipmentId}`);
      const s = res.data;
      const isAir = (s.transport_mode || s.carrier?.transport_mode) === 'AIR';
      const canIssue = ['operator', 'admin', 'shipper'].includes(state.page);

      if (s.release_document_number) {
        const docLabel = s.release_document_type === 'AIR_WAYBILL'
          ? 'SURAT MUAT PESAWAT (AIR WAYBILL DIGITAL)'
          : (s.release_document_type === 'PHYSICAL_COURIER' ? 'SURAT MUAT ASLI (VIA KURIR DOKUMEN)' : 'SURAT RILIS ELEKTRONIK KAPAL (e-B/L TELEX RELEASE)');
        const body = `
          <div class="release-doc-sheet">
            <div style="display:flex;justify-content:space-between;align-items:start;border-bottom:2px solid var(--ink-1);padding-bottom:12px;margin-bottom:14px;">
              <div>
                <div style="font-size:11px;font-weight:800;letter-spacing:0.08em;color:var(--primary);">MERIDIAN NUSANTARA · DOKUMEN PENGAMBILAN RESMI</div>
                <div style="font-size:16px;font-weight:800;margin-top:3px;">${docLabel}</div>
              </div>
              <div style="text-align:right;">
                <div class="mono" style="font-weight:800;font-size:15px;">${esc(s.release_document_number)}</div>
                <div class="muted-sm">Terbit: ${fmtDateTime(s.release_issued_at)}</div>
              </div>
            </div>
            <div class="form-grid-2" style="margin-bottom:12px;font-size:13px;">
              <div><strong>Pengirim (Shipper):</strong><br>${esc(s.customer?.company_name || s.customer?.name || '—')}<br>${esc(s.customer?.city || '')} · ${esc(s.customer?.phone || '')}</div>
              <div><strong>Penerima Sah (Buyer):</strong><br>${esc(s.buyer?.company_name || s.buyer?.name || '—')}<br>${esc(s.buyer?.city || '')} · ${esc(s.buyer?.phone || '')}</div>
            </div>
            <div class="form-grid-2" style="margin-bottom:12px;font-size:13px;">
              <div><strong>Nomor Resi &amp; Kontainer:</strong><br><span class="mono">${esc(s.tracking_number)} / ${esc(s.container_number || '—')}</span><br>Segel: <span class="mono">${esc(s.seal_number || 'Utuh')}</span></div>
              <div><strong>Rute &amp; Pengangkut:</strong><br>${esc(s.origin_location?.name || '—')} &rarr; ${esc(s.destination_location?.name || '—')}<br>Mitra: ${esc(s.carrier?.name || '—')}</div>
            </div>
            <div style="padding:10px 12px;background:var(--bg-subtle);border:1px solid var(--border);border-radius:6px;font-size:13px;margin-bottom:12px;">
              <strong>Isi Muatan Terverifikasi:</strong> ${esc(s.cargo_description)} (${Number(s.weight_kg || 0).toLocaleString('id-ID')} kg)
              ${s.release_courier_tracking ? `<div style="margin-top:4px;"><strong>No. Resi Kurir Dokumen Fisik (DHL/FedEx):</strong> <span class="mono">${esc(s.release_courier_tracking)}</span></div>` : ''}
            </div>
            <div class="muted-sm">Tunjukkan surat digital resmi ini bersama identitas perusahaan kepada agen pelabuhan/bandara tujuan untuk penukaran <em>Delivery Order (DO)</em> dan pengambilan barang.</div>
          </div>
          <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px;">
            <button type="button" class="btn btn-primary" onclick="window.print()">Cetak / Simpan PDF</button>
          </div>
        `;
        openModal('Surat Rilis & Pengambilan Barang Digital', `No. Dokumen: ${s.release_document_number}`, body);
      } else if (canIssue) {
        const body = `
          <div class="alert-box alert-info">Terbitkan Surat Rilis Digital agar Pembeli bisa melihat dan mencetak dokumen resmi pengambilan barang di pelabuhan/bandara tujuan.</div>
          <form onsubmit="MeridianApp.handleIssueReleaseDoc(event, ${s.id})">
            <div class="field">
              <label>Jenis Surat Rilis Pengambilan</label>
              <select id="rd-type" class="select">
                <option value="TELEX_RELEASE" ${!isAir ? 'selected' : ''}>Rilis Elektronik Kapal (e-B/L / Telex Release — File Digital)</option>
                <option value="AIR_WAYBILL" ${isAir ? 'selected' : ''}>Surat Muat Pesawat (Air Waybill PDF Digital)</option>
                <option value="PHYSICAL_COURIER">Dokumen Kertas Asli via Kurir Udara (DHL / FedEx)</option>
              </select>
            </div>
            <div class="field">
              <label>Nomor Resi Kurir Dokumen Fisik (Opsional jika pakai DHL/FedEx)</label>
              <input id="rd-courier" class="input mono" placeholder="Opsional: Cth DHL-8829104421">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Terbitkan Surat Rilis Digital Sekarang</button>
          </form>
        `;
        openModal('Terbitkan Surat Pengambilan Barang', `Resi: ${s.tracking_number}`, body);
      } else {
        openModal('Surat Pengambilan Barang', `Resi: ${s.tracking_number}`, `<div class="empty-box"><div class="empty-title">Surat Rilis Belum Diterbitkan</div><div class="empty-desc">Surat Rilis Digital (e-B/L / Air Waybill) akan muncul di sini setelah dana Rekening Jaminan aman dan Operator/Pengirim menerbitkannya.</div></div>`);
      }
    } catch (e) {
      toast(e.message, 'err');
    }
  }

  /* "Bantuan Admin" Modal (Public & Authenticated) */
  async function openHelpModal() {
    if (state.user) {
      try { await loadSupportTickets(); } catch {}
    }
    const u = state.user;
    const myTicketsHtml = u && state.supportTickets.length > 0
      ? `
        <div style="margin-top:18px;padding-top:14px;border-top:1px solid var(--border);">
          <div style="font-weight:700;font-size:13.5px;margin-bottom:8px;">Riwayat Pesan Bantuan Anda (${state.supportTickets.length})</div>
          ${state.supportTickets.slice(0, 5).map(t => `
            <div style="padding:10px 12px;border:1px solid var(--border);border-radius:8px;margin-bottom:8px;font-size:12.5px;">
              <div style="display:flex;justify-content:space-between;gap:6px;">
                <strong>${esc(t.subject)}</strong>
                <span class="badge ${t.status === 'RESOLVED' ? 'badge-ok' : 'badge-warn'}">${t.status === 'RESOLVED' ? 'Dibalas Admin' : 'Menunggu'}</span>
              </div>
              <div class="muted-sm" style="margin-top:3px;">${esc(t.message)}</div>
              ${t.admin_reply ? `<div class="alert-box alert-ok" style="margin:8px 0 0;padding:8px 10px;"><strong>Admin:</strong> ${esc(t.admin_reply)}</div>` : ''}
            </div>`).join('')}
        </div>` : '';

    const body = `
      <form onsubmit="MeridianApp.handleSubmitSupportTicket(event)">
        ${!u ? `
          <div class="form-grid-2">
            <div class="field"><label>Nama Anda <span class="req">*</span></label><input id="hlp-name" class="input" required placeholder="Nama lengkap"></div>
            <div class="field"><label>Email / Nomor WhatsApp <span class="req">*</span></label><input id="hlp-contact" class="input" required placeholder="Kontak yang bisa dihubungi"></div>
          </div>` : ''}
        <div class="form-grid-2">
          <div class="field">
            <label>Kategori Bantuan</label>
            <select id="hlp-cat" class="select">
              <option value="PENGIRIMAN">Kendala Pengiriman / Penjemputan</option>
              <option value="REKENING_JAMINAN">Rekening Jaminan / Pencairan Dana</option>
              <option value="AKUN_VERIFIKASI">Verifikasi Identitas Akun</option>
              <option value="SENGKETA">Laporan Sengketa / Penipuan</option>
              <option value="UMUM">Pertanyaan Umum</option>
            </select>
          </div>
          <div class="field"><label>Judul Singkat <span class="req">*</span></label><input id="hlp-subj" class="input" required placeholder="Cth: Mohon verifikasi NIB perusahaan kami"></div>
        </div>
        <div class="field"><label>Jelaskan Kendala Anda <span class="req">*</span></label><textarea id="hlp-msg" class="textarea" rows="3" required placeholder="Tuliskan detail kendala atau pertanyaan agar Admin dapat segera membantu..."></textarea></div>
        <button type="submit" class="btn btn-primary" style="width:100%;">Kirim Pesan ke Administrator</button>
      </form>
      ${myTicketsHtml}
    `;
    openModal('Bantuan Admin — Pusat Layanan Meridian', 'Hubungi Administrator langsung jika terjadi kendala akun, pengiriman, atau sengketa dana', body);
  }

  /* Multimodal 3D Earth Map Modal */
  function openMapModal(shipmentId = null) {
    document.getElementById('map-modal-overlay')?.classList.remove('hidden');
    if (shipmentId) {
      const found = state.shipments.find(s => s.id === Number(shipmentId));
      if (found) state.selectedShipment = found;
    }

    setTimeout(() => {
      window.MeridianGlobe.initWorkspaceGlobe('workspace-globe-map', id => {
        const found = state.shipments.find(s => s.id === id);
        if (found) {
          state.selectedShipment = found;
          renderMapModalHud();
        }
      });
      window.MeridianGlobe.syncWorkspaceData(state.locations, state.shipments, state.selectedShipment);
      if (state.selectedShipment) {
        window.MeridianGlobe.focusShipment(state.selectedShipment);
      }
      renderMapModalHud();
    }, 60);
  }

  function openMapAtPort(lat, lng, name, code) {
    openMapModal();
    setTimeout(() => window.MeridianGlobe.zoomToPort(lat, lng, name, code), 350);
  }

  function closeMapModal() {
    document.getElementById('map-modal-overlay')?.classList.add('hidden');
  }

  function renderMapModalHud() {
    const hud = document.getElementById('map-modal-hud');
    if (!hud) return;
    const s = state.selectedShipment || state.shipments[0] || null;
    const canAdvance = state.demoToolsEnabled && state.user && ['operator', 'admin'].includes(state.user.role) && s && !['DELIVERED', 'CANCELLED'].includes(s.status);

    hud.innerHTML = `
      <div class="map-hud-card">
        <div style="font-size:12px;font-weight:800;margin-bottom:4px;">Legenda Jalur Multimoda:</div>
        <div style="display:flex;gap:12px;flex-wrap:wrap;font-size:12px;color:var(--ink-2);">
          <span><strong style="color:#ea580c;">---</strong> Truk Darat Cabang</span>
          <span><strong style="color:#0284c7;">&mdash;&mdash;</strong> Kapal Laut Mitra</span>
          <span><strong style="color:#4f46e5;">- - -</strong> Pesawat Kargo Mitra</span>
        </div>
      </div>

      ${s ? `
        <div class="map-hud-card">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:6px;">
            <span class="mono" style="font-weight:800;color:var(--primary);">${esc(s.tracking_number)}</span>
            ${modeBadge(s.transport_mode || s.carrier?.transport_mode)}
          </div>
          <div style="font-size:13px;font-weight:700;">${esc(s.cargo_description)}</div>
          <div class="muted-sm" style="margin:3px 0 8px;">Progres: <strong>${s.travel_progress_pct ?? 20}%</strong> ${s.simulated_days_elapsed > 0 ? `· Simulasi Hari ke-+${s.simulated_days_elapsed}` : ''}</div>
          ${canAdvance ? `
            <div class="btn-group">
              <button type="button" class="btn btn-sm btn-primary mono" onclick="MeridianApp.runTimeTravel(24, ${s.id})">Besok (+1 Hari)</button>
              <button type="button" class="btn btn-sm btn-outline mono" onclick="MeridianApp.runTimeTravel(72, ${s.id})">+3 Hari</button>
            </div>` : ''}
        </div>` : ''}
    `;
  }

  /* ========================================================================
     UTILITY DRAWER (NOTIFICATIONS & VIRTUAL WALLET)
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
      title.textContent = `Notifikasi (${state.unreadNotifications} belum dibaca)`;
      sub.textContent = 'Pemberitahuan panggilan jemput, chat, kendala & jaminan';
      const items = state.notifications || [];
      body.innerHTML = `
        ${items.length > 0 ? `<button type="button" class="btn btn-outline btn-sm" style="width:100%;margin-bottom:12px;" onclick="MeridianApp.markAllNotificationsRead()">Tandai Semua Sudah Dibaca</button>` : ''}
        ${items.length === 0
          ? `<div class="empty-box"><div class="empty-title">Belum Ada Notifikasi</div><div class="empty-desc">Pemberitahuan penting akan tampil otomatis di sini.</div></div>`
          : items.map(n => `
              <div class="notif-item ${n.is_read ? '' : 'unread'}" onclick="MeridianApp.clickNotification(${n.id})">
                <div style="display:flex;justify-content:space-between;gap:8px;margin-bottom:3px;">
                  <strong style="font-size:13px;">${esc(n.title)}</strong>
                  <span class="mono muted-sm" style="font-size:11px;">${fmtDateTime(n.created_at)}</span>
                </div>
                <div style="font-size:12.5px;color:var(--ink-2);">${esc(n.message)}</div>
              </div>`).join('')}
      `;
    } else if (state.utilityDrawer === 'wallet') {
      const bal = state.wallet.balance_usd ?? state.user?.wallet_balance_usd ?? 0;
      const isBuyer = state.page === 'buyer';
      title.textContent = 'Dompet & Rekening Jaminan';
      sub.textContent = 'Simulasi saldo dagang & mutasi jaminan';
      const formHtml = isBuyer
        ? `<form onsubmit="MeridianApp.handleWalletTopUp(event)" style="display:flex;gap:8px;margin-bottom:16px;">
             <input id="w-topup-amt" type="number" min="100" step="100" value="100000" class="input mono" required>
             <button type="submit" class="btn btn-ok">Top Up</button>
           </form>`
        : `<form onsubmit="MeridianApp.handleWalletWithdraw(event)" style="margin-bottom:16px;">
             <div class="form-grid-2">
               <div class="field"><label>Nominal ($ USD)</label><input id="w-withdraw-amt" type="number" min="10" step="10" value="1000" class="input mono" required></div>
               <div class="field"><label>Bank Tujuan</label><input id="w-withdraw-bank" class="input" value="Bank Mandiri #108-00-8821" required></div>
             </div>
             <button type="submit" class="btn btn-primary" style="width:100%;">Tarik Dana ke Rekening Bank</button>
           </form>`;
      const muts = state.wallet.mutations || [];
      body.innerHTML = `
        <div class="kpi-card" style="margin-bottom:14px;">
          <div class="kpi-label">Saldo Tersedia</div>
          <div class="kpi-val" style="color:var(--ok);">${fmtMoney(bal)} USD</div>
        </div>
        ${formHtml}
        <div style="font-weight:700;font-size:13px;margin-bottom:8px;">Riwayat Mutasi (${muts.length})</div>
        ${muts.length === 0
          ? `<div class="muted-sm">Belum ada mutasi saldo.</div>`
          : muts.map(m => `
              <div style="padding:10px;border:1px solid var(--border);border-radius:8px;margin-bottom:8px;font-size:12.5px;">
                <div style="display:flex;justify-content:space-between;">
                  <span class="badge badge-neutral">${esc(m.type)}</span>
                  <strong class="mono" style="color:${m.amount_usd >= 0 ? 'var(--ok)' : 'var(--warn)'};">${m.amount_usd >= 0 ? '+' : ''}${fmtMoney(m.amount_usd)}</strong>
                </div>
                <div style="margin-top:4px;">${esc(m.description)}</div>
                <div class="mono muted-sm" style="margin-top:2px;">Saldo akhir: ${fmtMoney(m.balance_after_usd)}</div>
              </div>`).join('')}
      `;
    }
  }

  /* ========================================================================
     ACTION HANDLERS (ALL ENDPOINTS CONNECTED)
     ======================================================================== */
  async function handleSaveProfile(e) {
    e.preventDefault();
    try {
      const res = await api('/api/v1/profile', {
        method: 'PUT',
        body: JSON.stringify({
          name: document.getElementById('pf-name').value.trim(),
          company_name: document.getElementById('pf-company').value.trim(),
          phone: document.getElementById('pf-phone').value.trim(),
          identity_number: document.getElementById('pf-idnum').value.trim(),
          city: document.getElementById('pf-city').value.trim(),
          country: document.getElementById('pf-country').value.trim(),
          address: document.getElementById('pf-address').value.trim(),
          bio: document.getElementById('pf-bio').value.trim(),
        }),
      });
      state.user = res.data;
      toast('Profil dan identitas Anda berhasil disimpan.', 'ok');
      renderWorkspace();
    } catch (err) { toast(err.message, 'err'); }
  }

  async function handleAddExportProduct(e) {
    e.preventDefault();
    try {
      await api('/api/v1/export-products', {
        method: 'POST',
        body: JSON.stringify({
          name: document.getElementById('ep-name').value.trim(),
          island: document.getElementById('ep-island').value,
          region: document.getElementById('ep-region').value.trim(),
          bean_type: document.getElementById('ep-bean').value,
          process_method: document.getElementById('ep-process').value.trim(),
          supply_capacity: document.getElementById('ep-cap').value.trim(),
          origin_location_id: Number(document.getElementById('ep-port').value) || null,
          tasting_notes: document.getElementById('ep-notes').value.trim(),
          description: document.getElementById('ep-desc').value.trim(),
        }),
      });
      toast('Kopi berhasil ditambahkan ke Direktori Pembeli.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) { toast(err.message, 'err'); }
  }

  async function handleDeleteExportProduct(id) {
    if (!confirm('Hapus produk kopi ini dari direktori?')) return;
    try {
      await api(`/api/v1/export-products/${id}`, { method: 'DELETE' });
      toast('Produk dihapus.', 'info');
      await refreshCurrentWorkspace();
    } catch (err) { toast(err.message, 'err'); }
  }

  async function handleCreatePickupSubmit(e) {
    e.preventDefault();
    try {
      const res = await api('/api/v1/pickup-requests', {
        method: 'POST',
        body: JSON.stringify({
          buyer_id: Number(document.getElementById('pk-buyer').value) || null,
          agreed_escrow_usd: Number(document.getElementById('pk-escrow').value) || 0,
          pickup_city: document.getElementById('pk-city').value.trim(),
          pickup_address: document.getElementById('pk-address').value.trim(),
          commodity_summary: document.getElementById('pk-commodity').value.trim(),
          target_days: Number(document.getElementById('pk-days').value) || null,
          preferred_mode: document.getElementById('pk-mode').value,
          branch_location_id: Number(document.getElementById('pk-branch').value),
          destination_location_id: Number(document.getElementById('pk-dest').value) || null,
          scheduled_note: document.getElementById('pk-note').value.trim(),
        }),
      });
      closeModal();
      toast(res.message || 'Panggilan terkirim ke Operator Cabang.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) { toast(err.message, 'err'); }
  }

  async function handleDispatchTruck(pickupId) {
    try {
      const res = await api(`/api/v1/pickup-requests/${pickupId}/dispatch`, { method: 'PATCH' });
      toast(res.message, 'ok');
      await refreshCurrentWorkspace();
    } catch (err) { toast(err.message, 'err'); }
  }

  async function handleCancelPickup(pickupId) {
    try {
      await api(`/api/v1/pickup-requests/${pickupId}/cancel`, { method: 'PATCH' });
      toast('Panggilan dibatalkan.', 'info');
      await refreshCurrentWorkspace();
    } catch (err) { toast(err.message, 'err'); }
  }

  function prefillShipmentFromPickup(pickupId) {
    state.prefillPickupId = pickupId;
    state.operatorTab = 'create';
    renderWorkspace();
  }

  function clearPickupPrefill() {
    state.prefillPickupId = null;
  }

  function handleModeChangeFilter(mode) {
    const sel = document.getElementById('ns-carrier');
    if (!sel) return;
    const match = state.carriers.find(c => c.transport_mode === mode);
    if (match) sel.value = String(match.id);
  }

  function handleIsoLiveInput() {
    const val = document.getElementById('ns-container')?.value || '';
    const hint = document.getElementById('ns-iso-hint');
    if (!hint) return;
    if (!val.trim()) { hint.textContent = 'Opsional'; return; }
    const r = validateIso6346(val);
    hint.textContent = r.valid ? `Format ISO 6346 valid (${r.clean})` : `Digit pemeriksa belum cocok (seharusnya ${r.expected ?? '?'})`;
    hint.style.color = r.valid ? 'var(--ok)' : 'var(--danger)';
  }

  function fillGeneratedIso() {
    const inp = document.getElementById('ns-container');
    if (!inp) return;
    inp.value = generateIso6346('MSKU');
    handleIsoLiveInput();
  }

  async function handleNewShipmentSubmit(e) {
    e.preventDefault();
    const itemName = document.getElementById('ns-item-name').value.trim();
    const qty = Number(document.getElementById('ns-quantity').value || 1);
    const weight = Number(document.getElementById('ns-weight').value || 0);
    const vol = Number(document.getElementById('ns-volume').value || 0) || null;
    const container = document.getElementById('ns-container').value.trim();
    const pickupAddr = document.getElementById('ns-pickup-addr').value.trim();
    const pLat = document.getElementById('ns-pickup-lat').value;
    const pLng = document.getElementById('ns-pickup-lng').value;
    const pReqId = document.getElementById('ns-pickup-req-id').value;

    const payload = {
      pickup_request_id: pReqId ? Number(pReqId) : null,
      customer_id: Number(document.getElementById('ns-customer').value),
      buyer_id: Number(document.getElementById('ns-buyer').value) || null,
      carrier_id: Number(document.getElementById('ns-carrier').value),
      origin_location_id: Number(document.getElementById('ns-origin').value),
      destination_location_id: Number(document.getElementById('ns-dest').value),
      transport_mode: document.getElementById('ns-mode').value,
      pickup_address: pickupAddr || null,
      pickup_latitude: pLat ? Number(pLat) : (pickupAddr.toLowerCase().includes('lhokseumawe') ? 5.1801 : null),
      pickup_longitude: pLng ? Number(pLng) : (pickupAddr.toLowerCase().includes('lhokseumawe') ? 97.1507 : null),
      seal_number: document.getElementById('ns-seal').value.trim() || null,
      verification_checklist: {
        content_matched: !!document.getElementById('chk-content')?.checked,
        weight_verified: !!document.getElementById('chk-weight')?.checked,
        packaging_dry: !!document.getElementById('chk-dry')?.checked,
        seal_intact: !!document.getElementById('chk-seal')?.checked,
        documents_complete: !!document.getElementById('chk-docs')?.checked,
      },
      container_number: container || null,
      cargo_description: `${itemName} (${qty} Koli)`,
      weight_kg: weight,
      volume_cbm: vol,
      contract_amount_usd: Number(document.getElementById('ns-contract-usd').value || 0),
      expected_departure: document.getElementById('ns-departure').value,
      expected_arrival: document.getElementById('ns-arrival').value,
      notes: document.getElementById('ns-notes').value.trim() || null,
      items: [{ item_name: itemName, category: document.getElementById('ns-category').value, quantity: qty, weight_kg: weight, condition: 'TERVERIFIKASI FISIK & SEGEL UTUH' }],
    };

    try {
      const res = await api('/api/v1/shipments', { method: 'POST', body: JSON.stringify(payload) });
      state.selectedShipment = res.data;
      state.prefillPickupId = null;
      state.operatorTab = 'fleet';
      toast(`Manifest resmi ${res.data.tracking_number} berhasil dicatat & dikirim ke Pengirim.`, 'ok');
      await refreshCurrentWorkspace();
    } catch (err) { toast(err.message, 'err'); }
  }

  async function handleCheckpointSubmit(e) {
    e.preventDefault();
    const id = document.getElementById('cp-shipment-id').value;
    try {
      await api(`/api/v1/shipments/${id}/tracking`, {
        method: 'POST',
        body: JSON.stringify({
          event_type: document.getElementById('cp-event-type').value,
          location_id: Number(document.getElementById('cp-location').value),
          remarks: document.getElementById('cp-remarks').value.trim() || 'Pembaruan lokasi oleh Operator Cabang.',
        }),
      });
      toast('Progres perjalanan berhasil disimpan.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) { toast(err.message, 'err'); }
  }

  async function handleExceptionSubmit(e) {
    e.preventDefault();
    const id = document.getElementById('ex-shipment-id').value;
    try {
      await api(`/api/v1/shipments/${id}/exceptions`, {
        method: 'POST',
        body: JSON.stringify({
          exception_type: document.getElementById('ex-type').value,
          severity: document.getElementById('ex-severity').value,
          impact_hours: Number(document.getElementById('ex-hours').value),
          description: document.getElementById('ex-desc').value.trim(),
        }),
      });
      toast('Kendala tercatat dan estimasi tiba dihitung ulang.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) { toast(err.message, 'err'); }
  }

  async function handleInvestigateException(exId, shipmentId) {
    try {
      await api(`/api/v1/exceptions/${exId}/investigate`, { method: 'POST' });
      toast('Status kendala diperbarui ke Sedang Ditangani.', 'ok');
      await refreshCurrentWorkspace();
      openShipmentDetailModal(shipmentId);
    } catch (err) { toast(err.message, 'err'); }
  }

  async function handleResolveException(exId, shipmentId) {
    const notes = prompt(
      'Masukkan catatan penyelesaian kendala:',
      'Kendala telah diverifikasi dan diselesaikan oleh Operator Cabang.'
    );
    if (!notes || !notes.trim()) return;
    try {
      await api(`/api/v1/exceptions/${exId}/resolve`, {
        method: 'POST',
        body: JSON.stringify({ resolution_notes: notes.trim() }),
      });
      toast('Kendala selesai ditangani & estimasi tiba diperbarui.', 'ok');
      await refreshCurrentWorkspace();
      openShipmentDetailModal(shipmentId);
    } catch (err) { toast(err.message, 'err'); }
  }

  async function runTimeTravel(hours, explicitId = null) {
    const id = explicitId || document.getElementById('tt-shipment-id')?.value || state.selectedShipment?.id;
    if (!id) return toast('Pilih pengiriman terlebih dahulu.', 'err');
    try {
      const res = await api('/api/v1/demo/time-travel', {
        method: 'POST',
        body: JSON.stringify({ shipment_id: Number(id), hours: Number(hours) }),
      });
      state.selectedShipment = res.data.shipment;
      toast(hours === 24 ? 'Simulasi maju +1 Hari (Besok)! Perhatikan animasi kendaraan di Peta 3D.' : `Simulasi waktu maju +${hours} jam berhasil.`, 'ok');
      await refreshCurrentWorkspace();
      if (!document.getElementById('map-modal-overlay')?.classList.contains('hidden')) {
        window.MeridianGlobe.focusShipment(state.selectedShipment);
        renderMapModalHud();
      }
    } catch (err) { toast(err.message, 'err'); }
  }

  async function handleShipperConfirm(id) {
    try {
      const res = await api(`/api/v1/shipments/${id}/confirm-shipper`, { method: 'POST' });
      toast(res.message || 'Data disetujui Pengirim.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) { toast(err.message, 'err'); }
  }

  async function handleBuyerConfirm(id) {
    try {
      const res = await api(`/api/v1/shipments/${id}/confirm-buyer`, { method: 'POST' });
      toast(res.message || 'Barang dikonfirmasi diterima & dana cair ke Pengirim.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) { toast(err.message, 'err'); }
  }

  async function handleIssueReleaseDoc(e, shipmentId) {
    e.preventDefault();
    try {
      const res = await api(`/api/v1/shipments/${shipmentId}/release-document`, {
        method: 'POST',
        body: JSON.stringify({
          release_document_type: document.getElementById('rd-type').value,
          release_courier_tracking: document.getElementById('rd-courier').value.trim() || null,
        }),
      });
      toast(res.message, 'ok');
      await refreshCurrentWorkspace();
      openReleaseDocModal(shipmentId);
    } catch (err) { toast(err.message, 'err'); }
  }

  async function handleEscrowDeposit(id) {
    try {
      const res = await api(`/api/v1/shipments/${id}/escrow/deposit`, { method: 'POST' });
      toast(res.message || 'Dana berhasil diamankan di Rekening Jaminan.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) { toast(err.message, 'err'); }
  }

  async function handleEscrowDispute(id) {
    const reason = prompt('Tuliskan alasan pengajuan sengketa untuk ditinjau Admin:');
    if (!reason || reason.trim().length < 5) return;
    try {
      await api(`/api/v1/shipments/${id}/escrow/dispute`, { method: 'POST', body: JSON.stringify({ reason: reason.trim() }) });
      toast('Sengketa diajukan. Pencairan dana dibekukan untuk pemeriksaan Admin.', 'info');
      closeModal();
      await refreshCurrentWorkspace();
    } catch (err) { toast(err.message, 'err'); }
  }

  async function handleWalletTopUp(e) {
    e.preventDefault();
    try {
      await api('/api/v1/wallet/topup', { method: 'POST', body: JSON.stringify({ amount_usd: Number(document.getElementById('w-topup-amt').value) }) });
      toast('Top Up saldo simulasi berhasil.', 'ok');
      await refreshCurrentWorkspace();
      renderUtilityDrawer();
    } catch (err) { toast(err.message, 'err'); }
  }

  async function handleWalletWithdraw(e) {
    e.preventDefault();
    try {
      await api('/api/v1/wallet/withdraw', {
        method: 'POST',
        body: JSON.stringify({
          amount_usd: Number(document.getElementById('w-withdraw-amt').value),
          bank_destination: document.getElementById('w-withdraw-bank').value.trim(),
        }),
      });
      toast('Penarikan dana simulasi dicatat.', 'ok');
      await refreshCurrentWorkspace();
      renderUtilityDrawer();
    } catch (err) { toast(err.message, 'err'); }
  }

  /* Chat Actions */
  async function startChatWith(peerId, productId = null, productName = '') {
    state.activeChatPeerId = Number(peerId);
    state.activeChatProductId = productId ? Number(productId) : null;
    if (state.page === 'buyer') state.buyerTab = 'chat';
    else if (state.page === 'shipper') state.shipperTab = 'chat';
    else if (state.page === 'operator') state.operatorTab = 'chat';
    await loadChatData();
    renderWorkspace();
    if (productName) {
      const inp = document.getElementById('chat-input-msg');
      if (inp) {
        inp.value = `Halo, saya tertarik berdiskusi mengenai ${productName}. Bagaimana ketersediaan stok dan penawaran harganya?`;
        inp.focus();
      }
    }
  }

  async function selectChatPeer(peerId) {
    state.activeChatPeerId = Number(peerId);
    state.activeChatProductId = null;
    await loadChatData();
    renderWorkspace();
    const box = document.getElementById('chat-messages-box');
    if (box) box.scrollTop = box.scrollHeight;
  }

  async function handleSendChatMessage(e) {
    e.preventDefault();
    const inp = document.getElementById('chat-input-msg');
    const msg = (inp?.value || '').trim();
    if (!msg || !state.activeChatPeerId) return;
    try {
      await api('/api/v1/chats/messages', {
        method: 'POST',
        body: JSON.stringify({
          recipient_id: state.activeChatPeerId,
          export_product_id: state.activeChatProductId,
          message: msg,
        }),
      });
      state.activeChatProductId = null;
      if (inp) inp.value = '';
      await loadChatData();
      renderWorkspace();
      const box = document.getElementById('chat-messages-box');
      if (box) box.scrollTop = box.scrollHeight;
    } catch (err) { toast(err.message, 'err'); }
  }

  /* Support Ticket ("Bantuan Admin") Actions */
  async function handleSubmitSupportTicket(e) {
    e.preventDefault();
    try {
      const payload = {
        category: document.getElementById('hlp-cat').value,
        subject: document.getElementById('hlp-subj').value.trim(),
        message: document.getElementById('hlp-msg').value.trim(),
      };
      if (!state.user) {
        payload.guest_name = document.getElementById('hlp-name').value.trim();
        payload.guest_contact = document.getElementById('hlp-contact').value.trim();
      }
      const res = await api('/api/v1/support-tickets', { method: 'POST', body: JSON.stringify(payload) });
      toast(res.message || 'Pesan berhasil dikirim ke Admin.', 'ok');
      closeModal();
      if (state.user) await refreshCurrentWorkspace();
    } catch (err) { toast(err.message, 'err'); }
  }

  async function handleAdminReplyTicket(e, ticketId) {
    e.preventDefault();
    const val = (document.getElementById(`reply-ticket-${ticketId}`)?.value || '').trim();
    if (!val) return;
    try {
      await api(`/api/v1/admin/support-tickets/${ticketId}/reply`, {
        method: 'POST',
        body: JSON.stringify({ admin_reply: val, status: 'RESOLVED' }),
      });
      toast('Balasan Admin terkirim ke pengguna.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) { toast(err.message, 'err'); }
  }

  /* Admin CRUD Handlers */
  async function handleAdminToggleVerify(id) {
    try {
      const res = await api(`/api/v1/admin/users/${id}/verify`, { method: 'PATCH' });
      toast(res.message, 'ok');
      await refreshCurrentWorkspace();
    } catch (err) { toast(err.message, 'err'); }
  }

  async function handleAdminAddUser(e) {
    e.preventDefault();
    try {
      await api('/api/v1/admin/users', {
        method: 'POST',
        body: JSON.stringify({
          name: document.getElementById('adm-u-name').value.trim(),
          email: document.getElementById('adm-u-email').value.trim(),
          phone: document.getElementById('adm-u-phone').value.trim(),
          company_name: document.getElementById('adm-u-company').value.trim(),
          password: document.getElementById('adm-u-pass').value,
          role: document.getElementById('adm-u-role').value,
          location_id: document.getElementById('adm-u-loc').value ? Number(document.getElementById('adm-u-loc').value) : null,
        }),
      });
      toast('Akun baru berhasil dibuat oleh Admin.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) { toast(err.message, 'err'); }
  }

  async function handleAdminUpdateUser(e, id) {
    e.preventDefault();
    const payload = {
      name: document.getElementById('adm-eu-name').value.trim(),
      email: document.getElementById('adm-eu-email').value.trim(),
      phone: document.getElementById('adm-eu-phone').value.trim(),
      company_name: document.getElementById('adm-eu-company').value.trim(),
      identity_number: document.getElementById('adm-eu-idnum').value.trim(),
      city: document.getElementById('adm-eu-city').value.trim(),
      role: document.getElementById('adm-eu-role').value,
      location_id: document.getElementById('adm-eu-loc').value ? Number(document.getElementById('adm-eu-loc').value) : null,
    };
    const pass = document.getElementById('adm-eu-pass').value;
    if (pass) payload.password = pass;
    try {
      await api(`/api/v1/admin/users/${id}`, { method: 'PUT', body: JSON.stringify(payload) });
      state.editingUserId = null;
      toast('Data pengguna diperbarui.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) { toast(err.message, 'err'); }
  }

  async function handleAdminAddCarrier(e) {
    e.preventDefault();
    try {
      await api('/api/v1/admin/carriers', {
        method: 'POST',
        body: JSON.stringify({
          code: document.getElementById('adm-c-code').value.trim(),
          name: document.getElementById('adm-c-name').value.trim(),
          transport_mode: document.getElementById('adm-c-mode').value,
          contact_email: document.getElementById('adm-c-email').value.trim() || null,
        }),
      });
      toast('Mitra baru ditambahkan.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) { toast(err.message, 'err'); }
  }

  async function handleAdminUpdateCarrier(e, id) {
    e.preventDefault();
    try {
      await api(`/api/v1/admin/carriers/${id}`, {
        method: 'PUT',
        body: JSON.stringify({
          code: document.getElementById('adm-ec-code').value.trim(),
          name: document.getElementById('adm-ec-name').value.trim(),
          transport_mode: document.getElementById('adm-ec-mode').value,
          contact_email: document.getElementById('adm-ec-email').value.trim() || null,
        }),
      });
      state.editingCarrierId = null;
      toast('Data mitra diperbarui.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) { toast(err.message, 'err'); }
  }

  async function handleAdminToggleCarrier(id) {
    try {
      await api(`/api/v1/admin/carriers/${id}/toggle`, { method: 'PATCH' });
      await refreshCurrentWorkspace();
    } catch (err) { toast(err.message, 'err'); }
  }

  async function handleAdminAddLocation(e) {
    e.preventDefault();
    try {
      await api('/api/v1/admin/locations', {
        method: 'POST',
        body: JSON.stringify({
          code: document.getElementById('adm-l-code').value.trim(),
          name: document.getElementById('adm-l-name').value.trim(),
          type: document.getElementById('adm-l-type').value,
          island: document.getElementById('adm-l-island').value.trim() || null,
          country: document.getElementById('adm-l-country').value.trim(),
          latitude: Number(document.getElementById('adm-l-lat').value),
          longitude: Number(document.getElementById('adm-l-lng').value),
        }),
      });
      toast('Lokasi baru ditambahkan ke jaringan.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) { toast(err.message, 'err'); }
  }

  async function handleAdminUpdateLocation(e, id) {
    e.preventDefault();
    try {
      await api(`/api/v1/admin/locations/${id}`, {
        method: 'PUT',
        body: JSON.stringify({
          code: document.getElementById('adm-el-code').value.trim(),
          name: document.getElementById('adm-el-name').value.trim(),
          type: document.getElementById('adm-el-type').value,
          island: document.getElementById('adm-el-island').value.trim() || null,
          country: document.getElementById('adm-el-country').value.trim(),
          latitude: Number(document.getElementById('adm-el-lat').value),
          longitude: Number(document.getElementById('adm-el-lng').value),
        }),
      });
      state.editingLocationId = null;
      toast('Lokasi diperbarui.', 'ok');
      await refreshCurrentWorkspace();
    } catch (err) { toast(err.message, 'err'); }
  }

  async function handleAdminDeleteShipment(id) {
    if (!confirm('Hapus pengiriman ini dari database?')) return;
    try {
      await api(`/api/v1/shipments/${id}`, { method: 'DELETE' });
      state.selectedShipment = null;
      closeModal();
      toast('Pengiriman berhasil dihapus.', 'info');
      await refreshCurrentWorkspace();
    } catch (err) { toast(err.message, 'err'); }
  }

  async function markAllNotificationsRead() {
    await api('/api/v1/notifications/read-all', { method: 'POST' });
    await loadUtilityData();
    renderWorkspace();
    renderUtilityDrawer();
  }

  async function clickNotification(id) {
    try { await api(`/api/v1/notifications/${id}/read`, { method: 'PATCH' }); } catch {}
    await loadUtilityData();
    renderWorkspace();
    renderUtilityDrawer();
  }

  /* Tab & Filter Switchers */
  let searchTimer = null;
  function handleSearchInput(val) {
    state.searchQuery = val;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(async () => { await loadRoleShipments(); renderWorkspace(); }, 250);
  }
  function handleCoffeeSearch(val) {
    state.coffeeSearch = val;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(async () => { await loadExportProducts(); renderWorkspace(); }, 220);
  }
  async function setCoffeeIslandFilter(island) {
    state.coffeeIslandFilter = island;
    await loadExportProducts();
    renderWorkspace();
  }
  async function setOperatorBranchFilter(flag) {
    state.operatorBranchOnly = flag;
    await Promise.all([loadRoleShipments(), loadPickupRequests()]);
    renderWorkspace();
  }
  function handlePublicTrackInline(e) {
    e.preventDefault();
    const val = (document.getElementById('pub-track-inline')?.value || '').trim();
    if (val) performPublicTrackLookup(val);
  }

  function setAdminTab(t) { state.adminTab = t; state.editingUserId = null; state.editingCarrierId = null; state.editingLocationId = null; renderWorkspace(); }
  function setOperatorTab(t) { state.operatorTab = t; renderWorkspace(); }
  function setShipperTab(t) { state.shipperTab = t; renderWorkspace(); }
  function setBuyerTab(t) { state.buyerTab = t; renderWorkspace(); }
  function openProfileTab() {
    if (state.page === 'admin') setAdminTab('profile');
    else if (state.page === 'operator') setOperatorTab('profile');
    else if (state.page === 'shipper') setShipperTab('profile');
    else if (state.page === 'buyer') setBuyerTab('profile');
  }
  function editAdminUser(id) { state.editingUserId = id; renderWorkspace(); }
  function editAdminCarrier(id) { state.editingCarrierId = id; renderWorkspace(); }
  function editAdminLocation(id) { state.editingLocationId = id; renderWorkspace(); }

  /* ========================================================================
     APP INITIALIZATION
     ======================================================================== */
  async function init() {
    await loadBootstrap();
    const initCfg = window.__MERIDIAN_INIT__ || { page: 'home', trackingNumber: null };

    window.addEventListener('popstate', () => {
      const p = window.location.pathname;
      if (p === '/login') navigate('login');
      else if (p === '/register') navigate('register');
      else if (p === '/admin') navigate('admin');
      else if (p === '/operator') navigate('operator');
      else if (p === '/shipper') navigate('shipper');
      else if (p === '/buyer') navigate('buyer');
      else if (p.startsWith('/track')) navigate('track', null, { trackingNumber: p.split('/')[2] || null });
      else navigate('home');
    });

    if (initCfg.page === 'track') {
      await navigate('track', null, { trackingNumber: initCfg.trackingNumber });
    } else if (['dashboard', 'admin', 'operator', 'shipper', 'buyer'].includes(initCfg.page)) {
      if (!state.user) {
        await navigate('login', '/login');
      } else {
        const dest = initCfg.page === 'dashboard' ? resolveHomeWorkspaceForUser(state.user) : initCfg.page;
        await navigate(dest, '/' + dest);
      }
    } else if (initCfg.page === 'login' || initCfg.page === 'register') {
      if (state.user) {
        const dest = resolveHomeWorkspaceForUser(state.user);
        await navigate(dest, '/' + dest);
      } else {
        await navigate(initCfg.page);
      }
    } else {
      await navigate('home');
    }
  }

  document.addEventListener('DOMContentLoaded', init);

  return {
    navigate,
    openUserDashboard,
    switchAuthTab,
    handleLoginSubmit,
    handleRegisterSubmit,
    handleLogout,
    handlePublicTrackSubmit,
    handlePublicTrackInline,
    toggleUtilityDrawer,
    closeUtilityDrawer,
    markAllNotificationsRead,
    clickNotification,
    openModal,
    closeModal,
    openHelpModal,
    openMapModal,
    openMapAtPort,
    closeMapModal,
    openShipmentDetailModal,
    openCreatePickupModal,
    syncPickupModeHint,
    openRoutePlannerModal,
    updateRouteCalc,
    openUserProfileModal,
    openReleaseDocModal,
    openProfileTab,
    handleSaveProfile,
    handleAddExportProduct,
    handleDeleteExportProduct,
    handleCreatePickupSubmit,
    handleDispatchTruck,
    handleCancelPickup,
    prefillShipmentFromPickup,
    clearPickupPrefill,
    handleModeChangeFilter,
    handleIsoLiveInput,
    fillGeneratedIso,
    handleNewShipmentSubmit,
    handleCheckpointSubmit,
    handleExceptionSubmit,
    handleInvestigateException,
    handleResolveException,
    runTimeTravel,
    handleShipperConfirm,
    handleBuyerConfirm,
    handleIssueReleaseDoc,
    handleEscrowDeposit,
    handleEscrowDispute,
    handleWalletTopUp,
    handleWalletWithdraw,
    startChatWith,
    selectChatPeer,
    handleSendChatMessage,
    handleSubmitSupportTicket,
    handleAdminReplyTicket,
    handleAdminToggleVerify,
    handleAdminAddUser,
    handleAdminUpdateUser,
    handleAdminAddCarrier,
    handleAdminUpdateCarrier,
    handleAdminToggleCarrier,
    handleAdminAddLocation,
    handleAdminUpdateLocation,
    handleAdminDeleteShipment,
    handleSearchInput,
    handleCoffeeSearch,
    setCoffeeIslandFilter,
    setOperatorBranchFilter,
    setAdminTab,
    setOperatorTab,
    setShipperTab,
    setBuyerTab,
    editAdminUser,
    editAdminCarrier,
    editAdminLocation,
  };
})();
