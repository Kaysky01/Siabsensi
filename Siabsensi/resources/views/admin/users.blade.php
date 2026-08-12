@extends('layouts.admin')
@section('title', 'Admin Management — SIABSEN')

@section('content')
<section>
  <style>
    .user-stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
      gap: 14px;
      margin-bottom: 24px;
    }
    
    .user-stat-card {
      background: var(--surface, #ffffff);
      border: 1px solid var(--border, #e2e8f0);
      border-radius: 14px;
      padding: 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      text-decoration: none;
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
      position: relative;
    }
    
    .user-stat-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.08);
      border-color: #cbd5e1;
    }

    .user-stat-card.active {
      background: #f8fafc;
      border-color: #4f46e5 !important;
      box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2), 0 4px 12px rgba(79, 70, 229, 0.08);
    }

    .user-stat-icon-wrapper {
      width: 42px;
      height: 42px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .user-stat-info {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .user-stat-label {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #64748b;
    }

    .user-stat-value {
      font-size: 22px;
      font-weight: 800;
      color: #0f172a;
      line-height: 1.1;
      font-family: var(--font-mono, monospace);
    }

    .icon-admin { background: #fef2f2; color: #ef4444; }
    .icon-timdis { background: #eff6ff; color: #3b82f6; }
    .icon-garda { background: #ecfdf5; color: #10b981; }
    .icon-acara { background: #faf5ff; color: #a855f7; }
    .icon-mahasiswa { background: #fffbeb; color: #f59e0b; }
    .icon-total { background: #f1f5f9; color: #6366f1; }

    @media (max-width: 640px) {
      .user-stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
      }
      .user-stat-card {
        padding: 12px 14px;
      }
      .user-stat-icon-wrapper {
        width: 36px;
        height: 36px;
        border-radius: 10px;
      }
      .user-stat-icon-wrapper .material-symbols-outlined {
        font-size: 20px !important;
      }
      .user-stat-value {
        font-size: 18px;
      }
      .user-stat-label {
        font-size: 10px;
      }
    }
  </style>

  <div class="page-header" style="margin-bottom:20px">
    <div>
      <div class="page-title">Admin Management</div>
      <div class="page-sub">Kelola akun pengguna sistem</div>
    </div>
    <button class="btn btn-primary btn-sm" onclick="document.getElementById('modal-add-user').classList.add('show')">
      <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">person_add</span> Tambah User
    </button>
  </div>

  {{-- Stat Cards --}}
  <div class="user-stats-grid">
    <a href="{{ route('admin.users', array_merge(request()->except('role', 'page'), ['role' => 'admin'])) }}" class="user-stat-card {{ request('role') === 'admin' ? 'active' : '' }}">
      <div style="display:flex; align-items:center; gap:12px">
        <div class="user-stat-icon-wrapper icon-admin">
          <span class="material-symbols-outlined" style="font-size:24px">admin_panel_settings</span>
        </div>
        <div class="user-stat-info">
          <span class="user-stat-label">Admin</span>
          <span class="user-stat-value">{{ $statsAdmin }}</span>
        </div>
      </div>
    </a>

    <a href="{{ route('admin.users', array_merge(request()->except('role', 'page'), ['role' => 'timdis'])) }}" class="user-stat-card {{ request('role') === 'timdis' ? 'active' : '' }}">
      <div style="display:flex; align-items:center; gap:12px">
        <div class="user-stat-icon-wrapper icon-timdis">
          <span class="material-symbols-outlined" style="font-size:24px">shield</span>
        </div>
        <div class="user-stat-info">
          <span class="user-stat-label">Tim Disiplin</span>
          <span class="user-stat-value">{{ $statsTimdis }}</span>
        </div>
      </div>
    </a>

    <a href="{{ route('admin.users', array_merge(request()->except('role', 'page'), ['role' => 'garda'])) }}" class="user-stat-card {{ request('role') === 'garda' ? 'active' : '' }}">
      <div style="display:flex; align-items:center; gap:12px">
        <div class="user-stat-icon-wrapper icon-garda">
          <span class="material-symbols-outlined" style="font-size:24px">security</span>
        </div>
        <div class="user-stat-info">
          <span class="user-stat-label">Garda</span>
          <span class="user-stat-value">{{ $statsGarda }}</span>
        </div>
      </div>
    </a>

    <a href="{{ route('admin.users', array_merge(request()->except('role', 'page'), ['role' => 'acara'])) }}" class="user-stat-card {{ request('role') === 'acara' ? 'active' : '' }}">
      <div style="display:flex; align-items:center; gap:12px">
        <div class="user-stat-icon-wrapper icon-acara">
          <span class="material-symbols-outlined" style="font-size:24px">event</span>
        </div>
        <div class="user-stat-info">
          <span class="user-stat-label">Acara</span>
          <span class="user-stat-value">{{ $statsAcara ?? 0 }}</span>
        </div>
      </div>
    </a>

    <a href="{{ route('admin.users', array_merge(request()->except('role', 'page'), ['role' => 'mahasiswa'])) }}" class="user-stat-card {{ request('role') === 'mahasiswa' ? 'active' : '' }}">
      <div style="display:flex; align-items:center; gap:12px">
        <div class="user-stat-icon-wrapper icon-mahasiswa">
          <span class="material-symbols-outlined" style="font-size:24px">school</span>
        </div>
        <div class="user-stat-info">
          <span class="user-stat-label">Mahasiswa</span>
          <span class="user-stat-value">{{ $statsMahasiswa }}</span>
        </div>
      </div>
    </a>

    <a href="{{ route('admin.users', request()->except('role', 'page')) }}" class="user-stat-card {{ request('role') === null || request('role') === '' ? 'active' : '' }}">
      <div style="display:flex; align-items:center; gap:12px">
        <div class="user-stat-icon-wrapper icon-total">
          <span class="material-symbols-outlined" style="font-size:24px">groups</span>
        </div>
        <div class="user-stat-info">
          <span class="user-stat-label">Total Staf</span>
          <span class="user-stat-value">{{ $statsTotal }}</span>
        </div>
      </div>
    </a>
  </div>

  {{-- Filter Panel --}}
  <div class="panel" style="margin-bottom:16px;padding:14px 20px">
    <form method="GET" action="{{ route('admin.users') }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div style="flex:1;min-width:200px">
        <label class="form-label">Cari</label>
        <input name="search" class="form-input" value="{{ request('search') }}" placeholder="Username atau nama..." style="padding:7px 10px">
      </div>
      <div>
        <label class="form-label">Role</label>
        <select name="role" class="form-input" style="width:160px;padding:7px 10px">
          <option value="">Semua (Pengelola)</option>
          <option value="admin" {{ request('role')=='admin'?'selected':'' }}>Admin</option>
          <option value="timdis" {{ request('role')=='timdis'?'selected':'' }}>Tim Disiplin</option>
          <option value="garda" {{ request('role')=='garda'?'selected':'' }}>Garda</option>
          <option value="acara" {{ request('role')=='acara'?'selected':'' }}>Acara</option>
          <option value="mahasiswa" {{ request('role')=='mahasiswa'?'selected':'' }}>Mahasiswa</option>
        </select>
      </div>
      <div>
        <label class="form-label">Status</label>
        <select name="status" class="form-input" style="width:120px;padding:7px 10px">
          <option value="">Semua</option>
          <option value="1" {{ request('status')==='1'?'selected':'' }}>Aktif</option>
          <option value="0" {{ request('status')==='0'?'selected':'' }}>Nonaktif</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      <a href="{{ route('admin.users') }}" class="btn btn-ghost btn-sm">Reset</a>
    </form>
  </div>

  {{-- Table Users --}}
  <div class="panel">
    <table class="att-table">
      <thead>
        <tr>
          <th>User</th>
          <th>Email</th>
          <th>Role</th>
          <th>Status</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($usersList as $u)
        <tr>
          <td>
            <div class="mahasiswa-cell">
              <div class="avatar" style="background:var(--primary-light);color:var(--primary)">{{ strtoupper(substr($u->full_name, 0, 2)) }}</div>
              <div>
                <div class="mhs-name">{{ $u->full_name }}</div>
                <div class="mhs-dept">{{ $u->username }}</div>
              </div>
            </div>
          </td>
          <td style="font-size:13px;color:var(--text-muted)">{{ $u->email ?? '-' }}</td>
          <td>
            @php $roleClass = match($u->role) { 'admin' => 'badge-red', 'timdis' => 'badge-blue', 'garda' => 'badge-green', 'acara' => 'badge-purple', 'mahasiswa' => 'badge-yellow', default => 'badge-gray' }; @endphp
            <span class="badge {{ $roleClass }}">{{ strtoupper($u->role) }}</span>
          </td>
          <td>
            <span class="badge {{ $u->is_active ? 'badge-green' : 'badge-red' }}">{{ $u->is_active ? 'Aktif' : 'Nonaktif' }}</span>
          </td>
          <td>
            <div style="display:flex;gap:4px">
              <button class="btn btn-ghost btn-sm" onclick="openEditUser({{ $u->id }}, '{{ addslashes($u->full_name) }}', '{{ $u->email }}', '{{ $u->assigned_kompi }}', '{{ $u->role }}')" title="Edit">
                <span class="material-symbols-outlined" style="font-size:16px">edit</span>
              </button>
              @if($u->is_active)
              <form method="POST" action="{{ route('admin.users.deactivate', $u->id) }}" style="display:inline">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm" title="Nonaktifkan">
                  <span class="material-symbols-outlined" style="font-size:16px;color:var(--danger)">block</span>
                </button>
              </form>
              @else
              <form method="POST" action="{{ route('admin.users.activate', $u->id) }}" style="display:inline">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm" title="Aktifkan">
                  <span class="material-symbols-outlined" style="font-size:16px;color:var(--success)">check_circle</span>
                </button>
              </form>
              @endif
              <button class="btn btn-ghost btn-sm" onclick="openResetPw({{ $u->id }}, '{{ $u->username }}')" title="Reset Password">
                <span class="material-symbols-outlined" style="font-size:16px">lock_reset</span>
              </button>
              @if($u->id !== Auth::id())
              <button class="btn btn-ghost btn-sm" onclick="openDeleteUser({{ $u->id }}, '{{ addslashes($u->full_name) }}', '{{ $u->username }}', '{{ $u->role }}')" title="Hapus User">
                <span class="material-symbols-outlined" style="font-size:16px;color:var(--danger)">delete</span>
              </button>
              @endif
            </div>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="5" style="text-align:center;color:var(--text-muted);padding:30px">Tidak ada user</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
  
  <div style="margin-top: 16px;">
    {{ $usersList->links('pagination::bootstrap-4') }}
  </div>
</section>

{{-- Modal Tambah User --}}
<div class="modal-backdrop" id="modal-add-user">
  <div class="modal">
    <div class="modal-title">Tambah User</div>
    <form method="POST" action="{{ route('admin.users.store') }}">@csrf
      <div class="form-row"><label class="form-label">Username *</label><input name="username" class="form-input" required></div>
      <div class="form-row"><label class="form-label">Password *</label><input type="password" name="password" class="form-input" required minlength="6"></div>
      <div class="form-row"><label class="form-label">Nama Lengkap *</label><input name="full_name" class="form-input" required></div>
      <div class="form-row"><label class="form-label">Email</label><input type="email" name="email" class="form-input"></div>
      <div class="form-row">
        <label class="form-label">Role *</label>
        <select name="role" class="form-input" required id="add-user-role" onchange="document.getElementById('add-kompi-row').style.display=(this.value==='garda'||this.value==='timdis')?'block':'none'">
          <option value="">-- Pilih --</option>
          <option value="admin">Admin</option>
          <option value="timdis">Tim Disiplin</option>
          <option value="garda">Garda</option>
          <option value="acara">Acara</option>
        </select>
      </div>
      <div class="form-row" id="add-kompi-row" style="display:none">
        <label class="form-label">Kompi *</label>
        <select name="assigned_kompi" class="form-input">
          <option value="">-- Pilih Kompi --</option>
          @foreach($kompiOptions as $k)
            <option value="{{ $k }}">{{ $k }}</option>
          @endforeach
        </select>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-backdrop').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

{{-- Modal Edit User --}}
<div class="modal-backdrop" id="modal-edit-user">
  <div class="modal">
    <div class="modal-title">Edit User</div>
    <form method="POST" id="edit-user-form">@csrf @method('PUT')
      <div class="form-row"><label class="form-label">Nama Lengkap *</label><input name="full_name" id="eu-name" class="form-input" required></div>
      <div class="form-row"><label class="form-label">Email</label><input type="email" name="email" id="eu-email" class="form-input"></div>
      <div class="form-row" id="edit-kompi-row" style="display:none">
        <label class="form-label">Kompi</label>
        <select name="assigned_kompi" id="eu-kompi" class="form-input">
          <option value="">-- Tidak ada --</option>
          @foreach($kompiOptions as $k)
            <option value="{{ $k }}">{{ $k }}</option>
          @endforeach
        </select>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-backdrop').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-primary">Update</button>
      </div>
    </form>
  </div>
</div>

{{-- Modal Reset Password --}}
<div class="modal-backdrop" id="modal-reset-pw">
  <div class="modal">
    <div class="modal-title">Reset Password</div>
    <p style="margin-bottom:16px;color:var(--text-muted)">User: <strong id="rp-username"></strong></p>
    <form method="POST" id="reset-pw-form">@csrf
      <div class="form-row"><label class="form-label">Password Baru *</label><input type="password" name="new_password" class="form-input" required minlength="6"></div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-backdrop').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-primary">Reset</button>
      </div>
    </form>
  </div>
</div>

{{-- Modal Hapus User --}}
<div class="modal-backdrop" id="modal-delete-user">
  <div class="modal">
    <div class="modal-title" style="color:var(--danger)">
      <span class="material-symbols-outlined" style="font-size:20px;vertical-align:middle;margin-right:4px">warning</span>
      Hapus User
    </div>
    <div style="margin-bottom:16px">
      <p style="color:var(--text-muted);margin-bottom:8px">Apakah Anda yakin ingin menghapus user ini?</p>
      <div style="background:var(--bg-dark);border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:12px">
        <div style="font-weight:600" id="du-name"></div>
        <div style="font-size:13px;color:var(--text-muted)">Username: <span id="du-username"></span></div>
        <div style="font-size:13px;color:var(--text-muted)">Role: <span id="du-role" style="text-transform:uppercase;font-weight:600"></span></div>
      </div>
      <div id="du-admin-warning" style="display:none;background:#fff7ed;color:#9a3412;padding:10px 14px;border-radius:var(--radius-sm);font-size:13px;border:1px solid #fdba74">
        <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle">info</span>
        Anda akan menghapus akun admin lain. Pastikan ini disengaja.
      </div>
    </div>
    <form method="POST" id="delete-user-form">
      @csrf @method('DELETE')
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-backdrop').classList.remove('show')">Batal</button>
        <button type="submit" class="btn" style="background:var(--danger);color:#fff">Hapus Permanen</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditUser(id, name, email, kompi, role) {
  document.getElementById('edit-user-form').action = '/admin/users/' + id;
  document.getElementById('eu-name').value = name;
  document.getElementById('eu-email').value = email || '';
  document.getElementById('eu-kompi').value = kompi || '';
  document.getElementById('edit-kompi-row').style.display = (role === 'garda' || role === 'timdis') ? 'block' : 'none';
  document.getElementById('modal-edit-user').classList.add('show');
}
function openResetPw(id, username) {
  document.getElementById('reset-pw-form').action = '/admin/users/' + id + '/reset-password';
  document.getElementById('rp-username').textContent = username;
  document.getElementById('modal-reset-pw').classList.add('show');
}
function openDeleteUser(id, name, username, role) {
  document.getElementById('delete-user-form').action = '/admin/users/' + id;
  document.getElementById('du-name').textContent = name;
  document.getElementById('du-username').textContent = username;
  document.getElementById('du-role').textContent = role;
  
  // Show/hide admin warning if deleting admin
  document.getElementById('du-admin-warning').style.display = role === 'admin' ? 'block' : 'none';
  
  document.getElementById('modal-delete-user').classList.add('show');
}
</script>
@endsection
