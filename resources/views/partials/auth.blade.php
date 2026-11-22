<div id="page-auth" class="hidden">
  <div class="auth-glass-shell glass-surface">
    <!-- KIRI: FORM MASUK & DAFTAR -->
    <div class="auth-left">
      <div style="display:flex;align-items:center;justify-content:space-between;">
        @include('partials.brand-logo')
        <button type="button" class="btn btn-glass btn-sm" onclick="MeridianApp.navigate('home', '/')">&larr; Beranda</button>
      </div>

      <h1 class="auth-heading" id="auth-page-title">Masuk ke Meridian</h1>
      <p class="auth-sub" id="auth-page-sub">Setiap akun diarahkan otomatis ke dashboard khusus sesuai perannya.</p>

      <div class="auth-tabs">
        <button type="button" id="tab-auth-login" class="auth-tab active" onclick="MeridianApp.switchAuthTab('login')">Masuk</button>
        <button type="button" id="tab-auth-register" class="auth-tab" onclick="MeridianApp.switchAuthTab('register')">Daftar Akun</button>
      </div>

      <div id="auth-error-box" class="auth-alert hidden"></div>

      <!-- FORM LOGIN -->
      <form id="form-login" onsubmit="MeridianApp.handleLoginSubmit(event)">
        <div class="field">
          <label for="login-email">Email Akun</label>
          <input type="email" id="login-email" class="input" required placeholder="nama@perusahaan.com" value="operator@cargotrace.com">
        </div>

        <div class="field">
          <label for="login-password">Kata Sandi</label>
          <input type="password" id="login-password" class="input" required placeholder="Masukkan kata sandi" value="password">
        </div>

        <button type="submit" id="btn-login-submit" class="btn btn-primary btn-lg" style="width:100%;margin-top:6px;">
          Masuk ke Ruang Kerja
        </button>
      </form>

      <!-- FORM REGISTRASI -->
      <form id="form-register" class="hidden" onsubmit="MeridianApp.handleRegisterSubmit(event)">
        <div class="field">
          <label for="reg-name">Nama Lengkap / Perusahaan</label>
          <input type="text" id="reg-name" class="input" required placeholder="PT Nusantara Ekspor">
        </div>

        <div class="field">
          <label for="reg-email">Email Kerja</label>
          <input type="email" id="reg-email" class="input" required placeholder="ops@perusahaan.id">
        </div>

        <div class="field">
          <label for="reg-password">Kata Sandi</label>
          <input type="password" id="reg-password" class="input" required minlength="6" placeholder="Minimal 6 karakter">
        </div>

        <button type="submit" id="btn-register-submit" class="btn btn-primary btn-lg" style="width:100%;margin-top:6px;">
          Buat Akun Pelanggan (Shipper / Buyer)
        </button>
        <div style="font-size:11px;color:var(--ink-muted);margin-top:8px;text-align:center;">
          Akun pekerja Operator hanya dapat dibuat dan ditugaskan oleh Administrator.
        </div>
      </form>


    </div>

    <!-- KANAN: RINGKASAN ARSITEKTUR OBSERVATORIUM -->
    <div class="auth-right">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <span class="mono" style="font-size:10.5px;letter-spacing:0.1em;color:var(--accent-sky);">MERIDIAN OBSERVATORY</span>
        <span class="mono" style="font-size:10.5px;color:var(--ink-muted);">RBAC AKTIF</span>
      </div>

      <div style="margin:auto 0;">
        <div style="font-size:21px;font-weight:700;line-height:1.35;color:#ffffff;margin-bottom:16px;">
          Satu bola bumi di tengah, ruang kerja kaca terpisah untuk setiap peran.
        </div>
        <p style="font-size:13px;color:var(--ink-muted);line-height:1.65;margin-bottom:22px;">
          Administrator mengelola sistem &amp; pelabuhan, Operator mendaftarkan manifest serta checkpoint, Shipper menyetujui barang, dan Buyer memverifikasi penerimaan di pelabuhan tujuan.
        </p>
        <div style="display:flex;flex-wrap:wrap;gap:7px;">
          <span class="tag-mono">IDTPR · Jakarta</span>
          <span class="tag-mono">IDBLW · Belawan</span>
          <span class="tag-mono">SGSIN · Singapura</span>
          <span class="tag-mono">CNSHA · Shanghai</span>
          <span class="tag-mono">JPTYO · Tokyo</span>
          <span class="tag-mono">NLRTM · Rotterdam</span>
          <span class="tag-mono">DEHAM · Hamburg</span>
        </div>
      </div>

      <div class="mono" style="font-size:11px;color:var(--ink-faint);display:flex;justify-content:space-between;">
        <span>ISO 6346 Verified</span>
        <span>Open-Meteo Marine API</span>
      </div>
    </div>
  </div>
</div>
