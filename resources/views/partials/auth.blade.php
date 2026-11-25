<div id="page-auth" class="hidden">
  <div class="auth-shell surface-card">
    <!-- KIRI: FORM MASUK & DAFTAR AKUN (MURNI ENTERPRISE, TANPA TOMBOL DEMO) -->
    <div class="auth-left">
      <div class="auth-top-row">
        @include('partials.brand-logo')
        <div style="display:flex;gap:8px;">
          <button type="button" class="btn btn-outline btn-sm" onclick="MeridianApp.openHelpModal()">Bantuan Admin</button>
          <button type="button" class="btn btn-outline btn-sm" onclick="MeridianApp.navigate('home', '/')">&larr; Beranda</button>
        </div>
      </div>

      <h1 class="auth-heading" id="auth-page-title">Masuk ke Meridian</h1>
      <p class="auth-sub" id="auth-page-sub">Masukkan email dan kata sandi akun Anda untuk membuka ruang kerja sesuai peran.</p>

      <div class="seg-tabs">
        <button type="button" id="tab-auth-login" class="seg-tab active" onclick="MeridianApp.switchAuthTab('login')">Masuk</button>
        <button type="button" id="tab-auth-register" class="seg-tab" onclick="MeridianApp.switchAuthTab('register')">Daftar Akun Baru</button>
      </div>

      <div id="auth-error-box" class="alert-box alert-danger hidden"></div>

      <!-- FORM LOGIN -->
      <form id="form-login" onsubmit="MeridianApp.handleLoginSubmit(event)">
        <div class="field">
          <label for="login-email">Alamat Email</label>
          <input type="email" id="login-email" class="input" required placeholder="nama@email.com">
        </div>

        <div class="field">
          <label for="login-password">Kata Sandi</label>
          <input type="password" id="login-password" class="input" required placeholder="Masukkan kata sandi">
        </div>

        <button type="submit" id="btn-login-submit" class="btn btn-primary btn-lg" style="width:100%;margin-top:8px;">
          Masuk ke Ruang Kerja
        </button>
      </form>

      <!-- FORM REGISTRASI PELANGGAN (DENGAN KELENGKAPAN IDENTITAS) -->
      <form id="form-register" class="hidden" onsubmit="MeridianApp.handleRegisterSubmit(event)">
        <div class="form-grid-2">
          <div class="field">
            <label for="reg-name">Nama Lengkap <span class="req">*</span></label>
            <input type="text" id="reg-name" class="input" required placeholder="Nama penanggung jawab">
          </div>
          <div class="field">
            <label for="reg-company">Nama Perusahaan / Koperasi</label>
            <input type="text" id="reg-company" class="input" placeholder="PT / CV / Koperasi">
          </div>
        </div>

        <div class="form-grid-2">
          <div class="field">
            <label for="reg-email">Email Aktif <span class="req">*</span></label>
            <input type="email" id="reg-email" class="input" required placeholder="nama@perusahaan.com">
          </div>
          <div class="field">
            <label for="reg-phone">Nomor Telepon / WhatsApp <span class="req">*</span></label>
            <input type="text" id="reg-phone" class="input" required placeholder="+62 812 xxxx xxxx">
          </div>
        </div>

        <div class="form-grid-2">
          <div class="field">
            <label for="reg-city">Kota / Daerah</label>
            <input type="text" id="reg-city" class="input" placeholder="Cth: Lhokseumawe / Los Angeles">
          </div>
          <div class="field">
            <label for="reg-country">Negara</label>
            <input type="text" id="reg-country" class="input" value="Indonesia" placeholder="Indonesia / Amerika Serikat">
          </div>
        </div>

        <div class="field">
          <label for="reg-identity">Nomor Identitas / NIB / NPWP (Untuk Verifikasi Admin)</label>
          <input type="text" id="reg-identity" class="input mono" placeholder="Opsional: NIB / NPWP / Izin Usaha">
        </div>

        <div class="field">
          <label for="reg-password">Kata Sandi <span class="req">*</span></label>
          <input type="password" id="reg-password" class="input" required minlength="6" placeholder="Minimal 6 karakter">
        </div>

        <button type="submit" id="btn-register-submit" class="btn btn-primary btn-lg" style="width:100%;margin-top:6px;">
          Daftar Sebagai Pengirim / Pembeli
        </button>
        <div class="field-hint" style="text-align:center;margin-top:8px;">
          Akun petugas Operator Cabang hanya dibuat dan ditugaskan langsung oleh Administrator.
        </div>
      </form>
    </div>

    <!-- KANAN: RINGKASAN KEAMANAN & PROFIL VERIFIKASI -->
    <div class="auth-right">
      <div class="badge badge-sky" style="align-self:flex-start;">PLATFORM EKSPOR &amp; REKENING JAMINAN</div>

      <div style="margin:auto 0;">
        <h2 style="font-size:22px;font-weight:800;line-height:1.35;color:var(--ink-1);margin-bottom:14px;">
          Transaksi kopi lintas negara yang jelas, jujur, dan terverifikasi.
        </h2>
        <p style="font-size:13.5px;color:var(--ink-2);line-height:1.65;margin-bottom:20px;">
          Identitas pengirim dan pembeli diverifikasi oleh Administrator. Barang diperiksa fisiknya oleh petugas cabang di 5 pulau Indonesia sebelum diberangkatkan via kapal laut atau pesawat kargo mitra.
        </p>

        <div class="auth-feature-list">
          <div class="auth-feature-item">
            <strong>1. Bukan Marketplace Harga</strong>
            <span>Pembeli &amp; Pengirim negosiasi langsung via Chat; kami fokus pada pelacakan &amp; Rekening Jaminan.</span>
          </div>
          <div class="auth-feature-item">
            <strong>2. Cek Fisik di 5 Pulau Indonesia</strong>
            <span>Sumatra (Belawan/Kualanamu), Jawa (Priok/Soetta), Kalimantan (Pontianak/Supadio), Sulawesi (Makassar/Hasanuddin), Papua (Jayapura/Sentani).</span>
          </div>
          <div class="auth-feature-item">
            <strong>3. Surat Rilis Digital &amp; Bantuan Admin</strong>
            <span>Dukungan surat pengambilan barang digital dan tombol Bantuan Admin setiap saat.</span>
          </div>
        </div>
      </div>

      <div class="muted-sm" style="display:flex;justify-content:space-between;border-top:1px solid var(--border);padding-top:14px;">
        <span>Validasi Kontainer ISO 6346</span>
        <span>Data Cuaca Open-Meteo</span>
      </div>
    </div>
  </div>
</div>
