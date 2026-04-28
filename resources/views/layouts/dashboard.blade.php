@extends('layouts.app')

@section('body-class', 'flex min-h-screen overflow-x-hidden text-white bg-black')

@section('content')

<div id="sidebar-overlay" class="fixed inset-0 bg-black/80 z-40 hidden" onclick="toggleSidebar()"></div>

<aside id="sidebar" class="fixed inset-y-0 left-0 w-64 border-r @yield('sidebar-border', 'border-white/10') backdrop-blur-xl bg-black/60 flex flex-col p-6 z-50 transform -translate-x-full transition-transform duration-300 md:relative md:translate-x-0">
    <div class="mb-10 text-center">
        <h1 class="font-orbitron text-xl font-black tracking-tighter text-white">
            MATH<span class="@yield('accent-color', 'text-cyan-400')">VERSE</span>
        </h1>
        <p class="text-[9px] @yield('sidebar-subtitle-color', 'text-slate-500') tracking-[0.3em] uppercase font-bold">
            @yield('sidebar-subtitle')
        </p>
    </div>

    <nav class="flex-1 space-y-2">
        @yield('sidebar-nav')
    </nav>

    <button type="button" onclick="openModal('logoutModal')" class="btn-rect-secondary w-full">
        <i class="fas fa-power-off mr-2"></i> Log Out
    </button>
</aside>

<main class="flex-1 p-4 md:p-8 overflow-y-auto z-20 relative">

    {{-- Mobile header --}}
    <div class="md:hidden flex items-center justify-between mb-8 p-4 portal-frame @yield('mobile-border', '')">
        <button onclick="toggleSidebar()" class="text-2xl @yield('accent-color', 'text-cyan-400')">
            <i class="fas fa-bars"></i>
        </button>
        <h1 class="font-orbitron font-bold text-xs tracking-widest uppercase">
            @yield('mobile-title')
        </h1>
    </div>

    @yield('dashboard-content')

</main>

{{-- All modals injected per-dashboard --}}
@yield('modals')

@endsection

@push('scripts')
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('-translate-x-full');
    document.getElementById('sidebar-overlay').classList.toggle('hidden');
}

function showSection(id) {
    document.querySelectorAll('.content-section').forEach(s => {
        s.classList.add('hidden');
        s.classList.remove('animate-fade-in');
    });
    document.querySelectorAll('.nav-link').forEach(b => b.classList.remove('active'));

    const sec = document.getElementById('sec-' + id);
    sec.classList.remove('hidden');
    void sec.offsetWidth;
    sec.classList.add('animate-fade-in');
    document.getElementById('btn-' + id).classList.add('active');

    if (window.innerWidth < 768) toggleSidebar();
}

function openModal(id) {
    const m = document.getElementById(id);
    m.classList.remove('hidden');
    const f = m.querySelector('.portal-frame');
    if (f) { f.classList.remove('animate-fade-in'); void f.offsetWidth; f.classList.add('animate-fade-in'); }
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}
</script>
@endpush