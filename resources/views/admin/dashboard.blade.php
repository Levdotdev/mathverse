@extends('layouts.dashboard')

@section('title', 'Admin Control')
@section('sidebar-border', 'border-red-500/20')
@section('sidebar-subtitle', 'System Administrator')
@section('sidebar-subtitle-color', 'text-red-500/60')
@section('accent-color', 'text-red-500')
@section('mobile-title', 'Admin_Panel')
@section('logout-btn-class', '!border-red-500/30 !text-red-500')

@section('sidebar-nav')
<button onclick="showSection('overview')"   class="nav-link active w-full" id="btn-overview">
    <i class="fas fa-microchip mr-3 w-5 text-red-500"></i> Mainframe
</button>
<button onclick="showSection('user-lists')" class="nav-link w-full" id="btn-user-lists">
    <i class="fas fa-database mr-3 w-5 text-cyan-400"></i> User Registry
</button>
<button onclick="showSection('role-verify')" class="nav-link w-full" id="btn-role-verify">
    <i class="fas fa-user-shield mr-3 w-5 text-orange-400"></i> Verification
</button>
<button onclick="showSection('profile')"    class="nav-link w-full" id="btn-profile">
    <i class="fas fa-user-cog mr-3 w-5 text-blue-400"></i> Root Settings
</button>
@endsection

@section('dashboard-content')

{{-- OVERVIEW --}}
<section id="sec-overview" class="content-section">
    <h2 class="text-xl md:text-2xl font-orbitron font-bold mb-6 uppercase border-b border-red-500/20 pb-2 text-white">
        System <span class="text-red-500">Metrics</span>
    </h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="portal-frame !p-5 border-l-2 border-red-500 flex justify-between items-center">
            <div>
                <p class="text-slate-500 text-[9px] uppercase font-bold tracking-widest">Total Users</p>
                <h3 class="text-2xl font-orbitron mt-1">{{ $totalUsers }}</h3>
            </div>
            <i class="fas fa-users text-3xl opacity-10"></i>
        </div>
        <div class="portal-frame !p-5 border-l-2 border-cyan-500 flex justify-between items-center">
            <div>
                <p class="text-slate-500 text-[9px] uppercase font-bold tracking-widest">Teachers</p>
                <h3 class="text-2xl font-orbitron mt-1">{{ $totalTeachers }}</h3>
            </div>
            <i class="fas fa-chalkboard-teacher text-3xl opacity-10"></i>
        </div>
        <div class="portal-frame !p-5 border-l-2 border-orange-500 flex justify-between items-center">
            <div>
                <p class="text-slate-500 text-[9px] uppercase font-bold tracking-widest">Students</p>
                <h3 class="text-2xl font-orbitron mt-1">{{ $totalStudents }}</h3>
            </div>
            <i class="fas fa-user-graduate text-3xl opacity-10"></i>
        </div>
        <div class="portal-frame !p-5 border-l-2 border-purple-500 flex justify-between items-center">
            <div>
                <p class="text-slate-500 text-[9px] uppercase font-bold tracking-widest">Total Quizzes</p>
                <h3 class="text-2xl font-orbitron mt-1 text-purple-400">{{ $totalQuizzes }}</h3>
            </div>
            <i class="fas fa-book-open text-3xl opacity-10"></i>
        </div>
    </div>

    <div class="portal-frame !p-6 border-red-500/10">
        <h4 class="font-orbitron text-xs text-red-500 uppercase mb-6 tracking-widest">
            <i class="fas fa-terminal mr-2"></i> System Security Logs
        </h4>
        <div class="space-y-3 font-mono text-[10px] text-slate-400">
            <p><span class="text-red-500">[AUTH_ALERT]</span> Admin Root successfully mounted session.</p>
            <p><span class="text-cyan-500">[DB_INFO]</span> Registry synchronized with Supabase node.</p>
            <p><span class="text-orange-500">[VERIFY]</span> {{ count($pendingTeachers) }} teacher request(s) pending validation.</p>
        </div>
    </div>
</section>

{{-- USER REGISTRY --}}
<section id="sec-user-lists" class="content-section hidden">
    <div class="portal-frame !p-6 md:!p-8">
        <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
            <h2 class="text-xl font-orbitron font-bold uppercase">
                User <span class="text-cyan-400">Database</span>
            </h2>
            <div class="relative w-full sm:w-64">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                <input type="text" id="admin-search" placeholder="Search email or name..."
                       class="input-mobile-ultra !py-2">
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left min-w-[500px]">
                <thead class="text-slate-500 text-[10px] uppercase border-b border-white/5">
                    <tr>
                        <th class="pb-4">Email</th>
                        <th class="pb-4">Full Name</th>
                        <th class="pb-4">Role</th>
                        <th class="pb-4">Actions</th>
                    </tr>
                </thead>
                <tbody id="admin-user-tbody" class="text-sm font-rajdhani text-white">
                    @foreach($profiles as $p)
                        @php
                            $roleColor = match($p['role']) {
                                'admin'          => 'text-red-500 bg-red-500/10',
                                'teacher'        => 'text-purple-400 bg-purple-500/10',
                                'pending_teacher'=> 'text-orange-400 bg-orange-500/10',
                                default          => 'text-cyan-400 bg-cyan-500/10',
                            };
                            $roleLabel = $p['role'] === 'pending_teacher' ? 'Pending' : ucfirst($p['role']);
                        @endphp
                        <tr class="border-b border-white/5 hover:bg-white/5 user-row">
                            <td class="py-4 font-mono text-cyan-400">{{ $p['email'] ?? substr($p['id'],0,8) }}</td>
                            <td class="py-4">{{ $p['last_name'] ?? '—' }}, {{ $p['username'] ?? '—' }}</td>
                            <td class="py-4">
                                <span class="px-2 py-1 rounded text-[9px] font-black uppercase {{ $roleColor }}">
                                    {{ $roleLabel }}
                                </span>
                            </td>
                            <td class="py-4">
                                @if($p['role'] === 'admin')
                                    <span class="text-slate-500 italic text-[10px]">LOCKED_BY_CORE</span>
                                @else
                                    <button onclick="openEditModal('{{ $p['id'] }}','{{ addslashes($p['username'] ?? '') }}','{{ $p['role'] }}')"
                                            class="text-cyan-400 hover:text-white mr-4 text-[10px] font-bold uppercase">
                                        <i class="fas fa-edit mr-1"></i> Edit
                                    </button>
                                    <button onclick="confirmDelete('{{ $p['id'] }}')"
                                            class="text-red-500 hover:text-white text-[10px] font-bold uppercase">
                                        <i class="fas fa-trash-alt mr-1"></i> Delete
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>

{{-- VERIFICATION --}}
<section id="sec-role-verify" class="content-section hidden">
    <div class="portal-frame !p-6 md:!p-8 border-orange-500/20">
        <h2 class="text-xl font-orbitron font-bold mb-6 uppercase">
            Pending <span class="text-orange-400">Verifications</span>
        </h2>
        <div class="space-y-4">
            @forelse($pendingTeachers as $pt)
                <div class="flex flex-col sm:flex-row justify-between items-center p-4 bg-white/5 border border-white/10 rounded gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded bg-purple-500/20 flex items-center justify-center text-purple-400 text-xl">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div>
                            <p class="font-bold">{{ $pt['last_name'] ?? '—' }}, {{ $pt['username'] ?? '—' }}</p>
                            <p class="text-[9px] text-slate-500 uppercase font-bold">Teacher Application</p>
                            <p class="text-[10px] text-cyan-500 font-mono">{{ $pt['email'] ?? '' }}</p>
                        </div>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <form method="POST" action="/admin/approve-teacher/{{ $pt['id'] }}" class="flex-1 sm:flex-none">
                            @csrf
                            <button type="submit"
                                    class="w-full bg-green-600 hover:bg-green-500 px-6 py-2 text-[10px] font-bold text-black uppercase rounded">
                                Grant Access
                            </button>
                        </form>
                        <form method="POST" action="/admin/deny-teacher/{{ $pt['id'] }}"
                              onsubmit="return confirm('Reject this application?')" class="flex-1 sm:flex-none">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="w-full border border-red-500 text-red-500 px-6 py-2 text-[10px] font-bold uppercase rounded">
                                Reject
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-slate-500 text-xs uppercase">No pending verifications at this time.</p>
            @endforelse
        </div>
    </div>
</section>

{{-- PROFILE --}}
<section id="sec-profile" class="content-section hidden">
    <div class="portal-frame !p-6 md:!p-10 border-red-500/20">
        <h2 class="text-xl font-orbitron font-bold mb-10 uppercase">
            <i class="fas fa-user-secret mr-2"></i> Root <span class="text-red-500">Management</span>
        </h2>
        <form method="POST" action="/admin/profile" class="space-y-6 max-w-2xl mx-auto">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div class="form-group">
                    <label class="input-label">Administrator ID</label>
                    <input type="text" value="ADM-ROOT-2026" readonly
                           class="input-mobile-ultra !bg-white/5 font-mono text-red-500 !pl-4">
                </div>
                <div class="form-group">
                    <label class="input-label">Email Address</label>
                    <input type="text" value="{{ $user['email'] }}" readonly
                           class="input-mobile-ultra !bg-white/5 text-slate-400 !pl-4">
                </div>
                <div class="form-group sm:col-span-2 border-t border-white/10 pt-4 mt-2">
                    <label class="input-label text-orange-400">Current Password (required)</label>
                    <div class="relative">
                        <i class="fas fa-unlock-alt input-icon"></i>
                        <input type="password" id="a-curr-pass" name="current_password"
                               class="input-mobile-ultra pr-12" placeholder="Enter current password">
                        <button type="button" onclick="tglPass('a-curr-pass','a-ico-curr')"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500">
                            <i id="a-ico-curr" class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="input-label">New Password</label>
                    <div class="relative">
                        <i class="fas fa-key input-icon"></i>
                        <input type="password" id="a-pass" name="new_password"
                               class="input-mobile-ultra pr-12" placeholder="••••••••">
                        <button type="button" onclick="tglPass('a-pass','a-ico')"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500">
                            <i id="a-ico" class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="input-label">Confirm Password</label>
                    <div class="relative">
                        <i class="fas fa-shield-alt input-icon"></i>
                        <input type="password" id="a-conf" name="new_password_confirmation"
                               class="input-mobile-ultra pr-12" placeholder="••••••••">
                        <button type="button" onclick="tglPass('a-conf','a-ico-conf')"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500">
                            <i id="a-ico-conf" class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn-rect-primary !bg-red-600 !text-white mt-4">
                <i class="fas fa-database mr-2"></i> Update Root Config
            </button>
        </form>
    </div>
</section>

@endsection

@section('modals')

<div id="editUserModal" class="modal-overlay hidden">
    <div class="portal-frame !p-10 w-full max-w-sm text-center border-cyan-500/30">
        <i class="fas fa-user-edit text-4xl text-cyan-400 mb-4"></i>
        <h3 class="font-orbitron font-bold mb-6 uppercase text-white">
            Edit <span class="text-cyan-400">User</span>
        </h3>
        <div class="space-y-4 text-left">
            <div class="form-group">
                <label class="input-label">Full Name</label>
                <input type="text" id="edit-u-name" class="input-mobile-ultra !pl-4">
            </div>
            <div class="form-group">
                <label class="input-label">System Role</label>
                <select id="edit-u-role" class="input-mobile-ultra !pl-4 bg-slate-900 text-white">
                    <option value="student">Student</option>
                    <option value="teacher">Teacher</option>
                    <option value="pending_teacher">Pending Teacher</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
        </div>
        <div class="flex flex-col gap-2 mt-8">
            <button onclick="saveEditUser()"
                    class="btn-rect-primary !bg-cyan-500 !text-black uppercase text-xs">Save Changes</button>
            <button onclick="closeModal('editUserModal')"
                    class="text-[10px] font-bold mt-4 uppercase text-slate-500">Cancel</button>
        </div>
    </div>
</div>

<div id="deleteUserModal" class="modal-overlay hidden">
    <div class="portal-frame !p-10 w-full max-w-xs text-center border-red-500/50">
        <i class="fas fa-user-minus text-4xl text-red-600 mb-4"></i>
        <h3 class="font-orbitron font-bold mb-2 uppercase text-white">Delete User?</h3>
        <p class="text-[10px] text-slate-500 mb-8 uppercase">This removes the account permanently.</p>
        <div class="flex flex-col gap-2">
            <button onclick="executeDelete()"
                    class="btn-rect-primary !bg-red-600 !text-white uppercase text-xs">Purge User Data</button>
            <button onclick="closeModal('deleteUserModal')"
                    class="text-[10px] font-bold mt-4 uppercase text-slate-500">Cancel</button>
        </div>
    </div>
</div>

<div id="logoutModal" class="modal-overlay hidden">
    <div class="portal-frame !p-10 w-full max-w-xs text-center border-red-500/30 shadow-[0_0_60px_rgba(255,0,0,0.2)]">
        <i class="fas fa-power-off text-4xl text-red-500 mb-4 animate-pulse"></i>
        <h3 class="font-orbitron font-bold mb-2 uppercase text-white">Are you sure you want to logout?</h3>
        <p class="text-[10px] text-slate-500 mb-8 uppercase tracking-widest">Ending Root Session</p>
        <div class="space-y-3">
            <form id="logoutForm" method="POST" action="/logout" class="mt-10">
                @csrf
                <button onclick="handleLogout()" class="btn-rect-primary !bg-red-600 !text-white">Confirm Logout</button>
            </form>
            <button onclick="closeModal('logoutModal')" class="text-[10px] font-bold mt-4 uppercase text-slate-500">Cancel</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('js/admin.js') }}"></script>
@endpush