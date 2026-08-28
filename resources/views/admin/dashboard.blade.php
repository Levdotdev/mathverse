@extends('layouts.dashboard')

@section('title', 'Admin Control')
@section('sidebar-border', 'border-red-500/20')
@section('sidebar-subtitle', 'System Administrator')
@section('sidebar-subtitle-color', 'text-red-500/60')
@section('accent-color', 'text-red-500')
@section('mobile-title', 'Admin_Panel')
@section('logout-btn-class', '!border-red-500/30 !text-red-500')

@section('sidebar-nav')
    @include('admin.partials.sidebar-nav')
@endsection

@section('dashboard-content')

{{-- STATS --}}
<section id="sec-stats" class="content-section hidden">
    <h2 class="text-xl md:text-2xl font-orbitron font-bold mb-6 uppercase border-b border-red-500/20 pb-2">
        Platform <span class="text-red-500">Analytics</span>
    </h2>

    <div id="stats-loading" class="flex flex-col items-center justify-center" style="min-height: 80vh;">
        <i class="fas fa-circle-notch fa-spin text-4xl text-red-500 mb-4"></i>
        <p class="text-xs uppercase tracking-widest font-orbitron text-slate-500">Fetching Analytics...</p>
    </div>

    <div id="stats-content" class="hidden">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            <div class="portal-frame !p-5 border-l-2 border-red-500">
                <p class="text-slate-500 text-[10px] uppercase font-bold tracking-widest">Total Attempts</p>
                <h3 class="text-2xl font-orbitron mt-1" id="stat-total-attempts">—</h3>
            </div>
            <div class="portal-frame !p-5 border-l-2 border-cyan-500">
                <p class="text-slate-500 text-[10px] uppercase font-bold tracking-widest">Avg Accuracy</p>
                <h3 class="text-2xl font-orbitron mt-1" id="stat-avg-accuracy">—</h3>
            </div>
            <div class="portal-frame !p-5 border-l-2 border-purple-500">
                <p class="text-slate-500 text-[10px] uppercase font-bold tracking-widest">Total Users</p>
                <h3 class="text-2xl font-orbitron mt-1" id="stat-total-users">—</h3>
            </div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="portal-frame !p-6">
                <h4 class="font-orbitron text-xs text-cyan-400 uppercase tracking-widest mb-4">
                    <i class="fas fa-chart-line mr-2"></i> Quiz Attempts (14 days)
                </h4>
                <canvas id="chart-attempts" height="200"></canvas>
            </div>
            <div class="portal-frame !p-6">
                <h4 class="font-orbitron text-xs text-green-400 uppercase tracking-widest mb-4">
                    <i class="fas fa-user-plus mr-2"></i> New Registrations (14 days)
                </h4>
                <canvas id="chart-registrations" height="200"></canvas>
            </div>
            <div class="portal-frame !p-6">
                <h4 class="font-orbitron text-xs text-orange-400 uppercase tracking-widest mb-4">
                    <i class="fas fa-users mr-2"></i> User Role Breakdown
                </h4>
                <canvas id="chart-roles" height="200"></canvas>
            </div>
            <div class="portal-frame !p-6">
                <h4 class="font-orbitron text-xs text-pink-400 uppercase tracking-widest mb-4">
                    <i class="fas fa-chart-pie mr-2"></i> Score Distribution
                </h4>
                <canvas id="chart-distribution" height="200"></canvas>
            </div>
        </div>
    </div>
</section>

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

{{-- STUDENT REGISTRY --}}
<section id="sec-students" class="content-section hidden">
    <div class="portal-frame !p-6 md:!p-8">
        <div class="flex flex-col lg:flex-row justify-between lg:items-end mb-6 gap-4">
            <h2 class="text-xl font-orbitron font-bold uppercase">
                Student <span class="text-cyan-400">Registry</span>
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-[220px_190px_auto] gap-3 w-full lg:w-auto">
                <input type="search" data-registry-search="students" placeholder="Search student..." class="input-mobile-ultra !py-2 !pl-4">
                <select id="student-grade-filter" class="input-mobile-ultra !py-2 !pl-4 bg-slate-900 text-white">
                    <option value="">All grade levels</option>
                    @for($g = 1; $g <= 6; $g++)
                        <option value="{{ $g }}" {{ $selectedGrade === $g ? 'selected' : '' }}>Grade {{ $g }}</option>
                    @endfor
                </select>
                <button type="button" data-registry-clear="students"
                        class="btn-rect-secondary !py-2 !px-4 !text-[10px] {{ $selectedGrade === 0 ? 'hidden' : '' }}">
                    Clear Filters
                </button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left min-w-[650px]">
                <thead class="text-slate-500 text-[10px] uppercase border-b border-white/5">
                    <tr><th class="pb-4">Email</th><th class="pb-4">Full Name</th><th class="pb-4">Grade Level</th><th class="pb-4 text-right">Action</th></tr>
                </thead>
                <tbody class="text-sm font-rajdhani text-white">
                    @forelse($students as $p)
                        <tr class="border-b border-white/5 hover:bg-white/5 registry-row" data-registry="students">
                            <td class="py-4 font-mono text-cyan-400">{{ $p['email'] ?? substr($p['id'],0,8) }}</td>
                            <td class="py-4">{{ $p['last_name'] ?? '—' }}, {{ $p['first_name'] ?? '—' }}</td>
                            <td class="py-4 text-cyan-400">Grade {{ $p['grade_level'] ?? 'N/A' }}</td>
                            <td class="py-4 text-right">
                                <button onclick='confirmDelete(@json($p["id"]), @json(trim(($p["first_name"] ?? "") . " " . ($p["last_name"] ?? ""))))'
                                        class="text-red-500 hover:text-white text-[10px] font-bold uppercase"><i class="fas fa-trash-alt mr-1"></i> Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-slate-500 text-xs uppercase">No students match this grade filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>

{{-- TEACHER REGISTRY --}}
<section id="sec-teachers" class="content-section hidden">
    <div class="portal-frame !p-6 md:!p-8">
        <div class="flex flex-col sm:flex-row justify-between sm:items-center mb-6 gap-4">
            <h2 class="text-xl font-orbitron font-bold uppercase">Teacher <span class="text-blue-400">Registry</span></h2>
            <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                <input type="search" data-registry-search="teachers" placeholder="Search teacher..." class="input-mobile-ultra !py-2 !pl-4 w-full sm:w-64">
                <button type="button" data-registry-clear="teachers"
                        class="btn-rect-secondary !py-2 !px-4 !text-[10px] hidden">
                    Clear Filters
                </button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left min-w-[560px]">
                <thead class="text-slate-500 text-[10px] uppercase border-b border-white/5">
                    <tr><th class="pb-4">Email</th><th class="pb-4">Full Name</th><th class="pb-4">Joined</th><th class="pb-4 text-right">Action</th></tr>
                </thead>
                <tbody class="text-sm font-rajdhani text-white">
                    @forelse($teachers as $p)
                        <tr class="border-b border-white/5 hover:bg-white/5 registry-row" data-registry="teachers">
                            <td class="py-4 font-mono text-blue-400">{{ $p['email'] ?? substr($p['id'],0,8) }}</td>
                            <td class="py-4">{{ $p['last_name'] ?? '—' }}, {{ $p['first_name'] ?? '—' }}</td>
                            <td class="py-4 text-slate-400">{{ isset($p['created_at']) ? \Carbon\Carbon::parse($p['created_at'])->format('M d, Y') : 'N/A' }}</td>
                            <td class="py-4 text-right">
                                <button onclick='confirmDelete(@json($p["id"]), @json(trim(($p["first_name"] ?? "") . " " . ($p["last_name"] ?? ""))))'
                                        class="text-red-500 hover:text-white text-[10px] font-bold uppercase"><i class="fas fa-trash-alt mr-1"></i> Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-slate-500 text-xs uppercase">No teachers registered.</td></tr>
                    @endforelse
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
                            <p class="font-bold">{{ $pt['last_name'] ?? '—' }}, {{ $pt['first_name'] ?? '—' }}</p>
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
                        <form method="POST" action="/admin/deny-teacher/{{ $pt['id'] }}" class="flex-1 sm:flex-none">
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

<section id="sec-password" class="content-section hidden">
    <div class="portal-frame !p-6 md:!p-10 border-red-500/20">
        <h2 class="text-xl font-orbitron font-bold mb-10 uppercase">
            <i class="fas fa-user-secret mr-2"></i> CHANGE <span class="text-red-500">PASSWORD</span>
        </h2>
        <form method="POST" action="/change-password" class="space-y-6 max-w-2xl mx-auto">
            @csrf
            <div class="form-group sm:col-span-2 mt-2">
                <label class="input-label text-orange-400">Current Password</label>
                <div class="relative">
                    <i class="fas fa-unlock-alt input-icon"></i>
                    <input type="password" id="s-curr-pass" name="current_password"
                            class="input-mobile-ultra pr-12" placeholder="Enter current password" required>
                    <button type="button" onclick="tglPass('s-curr-pass','s-ico-curr')"
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500">
                        <i id="s-ico-curr" class="fas fa-eye-slash"></i>
                    </button>
                </div>
            </div>
            <div class="form-group border-t border-white/10 pt-4 mt-2">
                <label class="input-label">New Password</label>
                <div class="relative">
                    <i class="fas fa-key input-icon"></i>
                    <input type="password" id="s-new-pass" name="new_password"
                           class="input-mobile-ultra pr-12" placeholder="••••••••" required>
                    <button type="button" onclick="tglPass('s-new-pass','s-ico-new')"
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500">
                        <i id="s-ico-new" class="fas fa-eye-slash"></i>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label class="input-label">Confirm Password</label>
                <div class="relative">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="s-conf-pass" name="new_password_confirmation"
                           class="input-mobile-ultra pr-12" placeholder="••••••••" required>
                    <button type="button" onclick="tglPass('s-conf-pass','s-ico-conf')"
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500">
                            <i id="s-ico-conf" class="fas fa-eye-slash"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn-rect-primary !bg-red-600 !text-white mt-4">
                <i class="fas fa-database mr-2"></i> Update Password
            </button>
        </form>
    </div>
</section>

{{-- PROFILE SECTION --}}
<section id="sec-profile" class="content-section hidden">
    <div class="portal-frame !p-6 md:!p-10 border-red-500/20">
        <h2 class="text-xl font-orbitron font-bold mb-10 uppercase">
            <i class="fas fa-user-secret mr-2"></i> ROOT <span class="text-red-500">PROFILE</span>
        </h2>
        <form method="POST" action="/admin/profile" class="space-y-6 max-w-2xl mx-auto" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label class="input-label">Profile Picture</label>
                <div class="flex items-center gap-4">
                    {{-- Preview circle --}}
                    <div class="w-16 h-16 rounded-full border-2 border-white/10 bg-white/5 flex items-center justify-center overflow-hidden shrink-0" id="avatar-preview-wrap">
                        <img id="avatar-preview"
                            src="{{ $user['avatar_url'] ?: asset('default.png') }}"
                            class="w-full h-full object-cover">
                        </div>
                        <div class="flex-1">
                            <label for="avatar-input"
                                class="cursor-pointer block w-full text-center border border-white/10 bg-white/5 hover:bg-white/10 transition-all rounded px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400 hover:text-white">
                                <i class="fas fa-upload mr-2"></i> Choose Photo
                            </label>
                            <input type="file" id="avatar-input" name="avatar"
                                accept="image/*" class="hidden">
                            <p class="text-[9px] text-slate-600 mt-1 text-center">JPG, PNG • Less than 3 MB</p>
                        </div>
                    </div>
                </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div class="form-group">
                    <label class="input-label">First Name</label>
                    <div class="relative">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="first_name" value="{{ $user['first_name'] ?? '' }}"
                               class="input-mobile-ultra" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="input-label">Last Name</label>
                    <div class="relative">
                        <i class="fas fa-id-card input-icon"></i>
                        <input type="text" name="last_name" value="{{ $user['last_name'] ?? '' }}"
                               class="input-mobile-ultra" required>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn-rect-primary !bg-red-600 !text-white mt-4">
                <i class="fas fa-database mr-2"></i> Update Profile
            </button>
        </form>
    </div>
</section>

{{-- REPORTS --}}
<section id="sec-reports" class="content-section hidden">
    <h2 class="text-xl md:text-2xl font-orbitron font-bold mb-6 uppercase border-b border-red-500/20 pb-2">
        Export <span class="text-green-400">Reports</span>
    </h2>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <div class="portal-frame !p-6 border-l-4 border-cyan-500">
            <i class="fas fa-user-graduate text-3xl text-cyan-400 mb-4 block"></i>
            <h3 class="font-orbitron font-bold uppercase mb-1">Student Registry</h3>
            <p class="text-slate-500 text-xs mb-6">All students with grade, level, trophies, and join date.</p>
            <div class="flex gap-2">
                <a href="/admin/report/students?format=pdf"
                   class="flex-1 btn-rect-primary !py-2 !text-[10px] text-center">
                    <i class="fas fa-file-pdf mr-1"></i> PDF
                </a>
                <a href="/admin/report/students?format=csv"
                   class="flex-1 btn-rect-secondary !py-2 !text-[10px] text-center">
                    <i class="fas fa-file-csv mr-1"></i> CSV
                </a>
            </div>
        </div>

        <div class="portal-frame !p-6 border-l-4 border-purple-500">
            <i class="fas fa-chalkboard-teacher text-3xl text-purple-400 mb-4 block"></i>
            <h3 class="font-orbitron font-bold uppercase mb-1">Teacher Registry</h3>
            <p class="text-slate-500 text-xs mb-6">All teachers with quizzes created and join date.</p>
            <div class="flex gap-2">
                <a href="/admin/report/teachers?format=pdf"
                   class="flex-1 btn-rect-primary !py-2 !text-[10px] text-center">
                    <i class="fas fa-file-pdf mr-1"></i> PDF
                </a>
                <a href="/admin/report/teachers?format=csv"
                   class="flex-1 btn-rect-secondary !py-2 !text-[10px] text-center">
                    <i class="fas fa-file-csv mr-1"></i> CSV
                </a>
            </div>
        </div>

        <div class="portal-frame !p-6 border-l-4 border-red-500">
            <i class="fas fa-chart-pie text-3xl text-red-400 mb-4 block"></i>
            <h3 class="font-orbitron font-bold uppercase mb-1">Platform Summary</h3>
            <p class="text-slate-500 text-xs mb-6">Full snapshot: users, quizzes, accuracy, top students.</p>
            <div class="flex gap-2">
                <a href="/admin/report/summary?format=pdf"
                   class="flex-1 btn-rect-primary !bg-red-600 !text-white !py-2 !text-[10px] text-center">
                    <i class="fas fa-file-pdf mr-1"></i> PDF
                </a>
                <a href="/admin/report/summary?format=csv"
                   class="flex-1 btn-rect-secondary !py-2 !text-[10px] text-center">
                    <i class="fas fa-file-csv mr-1"></i> CSV
                </a>
            </div>
        </div>

    </div>
</section>

@endsection

@section('modals')

<div id="deleteUserModal" class="modal-overlay hidden">
    <div class="portal-frame !p-10 w-full max-w-xs text-center border-red-500/50">
        <i class="fas fa-user-minus text-4xl text-red-600 mb-4"></i>
        <h3 class="font-orbitron font-bold mb-2 uppercase text-white">Delete User?</h3>
        <p id="delete-user-name" class="text-xs text-slate-400 mb-2"></p>
        <p class="text-[10px] text-slate-500 mb-8 uppercase">This removes the account permanently.</p>
        <div class="flex flex-col gap-2">
            <form id="deleteUserForm" method="POST">@csrf @method('DELETE')
                <button class="btn-rect-primary !bg-red-600 !text-white uppercase text-xs">Purge User Data</button>
            </form>
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
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/admin.js') }}?v={{ filemtime(public_path('js/admin.js')) }}"></script>
<script src="{{ asset('js/charts.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        applyChartDefaults();
        document.getElementById('btn-stats')?.addEventListener('click', () => {
            requestAnimationFrame(() => requestAnimationFrame(() => loadAdminStats()));
        });
    });
</script>
@endpush
