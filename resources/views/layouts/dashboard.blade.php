@extends('layouts.app')

@section('body-class', 'flex min-h-screen text-white bg-black')

@section('content')

<div id="sidebar-overlay" class="fixed inset-0 bg-black/80 z-40 hidden" onclick="toggleSidebar()"></div>

<aside id="sidebar" class="fixed inset-y-0 left-0 w-64 border-r @yield('sidebar-border', 'border-white/10') backdrop-blur-xl bg-black/60 flex flex-col p-6 z-50 transform -translate-x-full transition-transform duration-300 md:sticky md:top-0 md:h-screen md:translate-x-0">
    <div class="mb-10 text-center">
        <h1 class="font-orbitron text-xl font-black tracking-tighter text-white">
            MATH<span class="@yield('accent-color', 'text-cyan-400')">VERSE</span>
        </h1>
        <p class="text-[9px] @yield('sidebar-subtitle-color', 'text-slate-500') tracking-[0.3em] uppercase font-bold">
            @yield('sidebar-subtitle')
        </p>
    </div>

    <nav class="flex-1 space-y-2 overflow-y-auto">
        @yield('sidebar-nav')
    </nav>

    <div class="relative mb-6">
        <button onclick="toggleProfileMenu(event)"
            class="flex items-center gap-3 w-full">

            <img src="{{ $user['avatar_url'] ?: asset('default.png') }}"
                class="w-10 h-10 rounded-full object-cover border border-white/10">

            <div class="text-left">
                <p class="text-xs font-bold">
                    {{ trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'User' }}
                </p>
                <p class="text-[10px] text-slate-500">
                    {{ ucwords($user['role']) }}
                </p>
            </div>

            <i id="profileArrow"
            class="fas fa-chevron-down ml-auto text-xs transition-transform duration-200"></i>
        </button>

        <div id="profileMenu"
            class="absolute left-0 bottom-full mb-2 w-full bg-black/90 border border-white/10 rounded-lg overflow-hidden z-50 transition-all duration-200 scale-95 opacity-0 pointer-events-none">

            @php
                $roleDashboard = match ($user['role'] ?? 'student') {
                    'teacher' => '/teacher/dashboard',
                    'admin' => '/admin/dashboard',
                    default => '/student/dashboard',
                };
            @endphp

            <a href="{{ $roleDashboard }}?section=profile" id="btn-profile"
                class="block w-full text-left px-4 py-3 text-xs hover:bg-white/5">
                <i class="fas fa-user mr-2"></i> Profile
            </a>

            <a href="{{ $roleDashboard }}?section=security" id="btn-security"
                class="block w-full text-left px-4 py-3 text-xs hover:bg-white/5">
                <i class="fas fa-shield-halved mr-2"></i> Account Security
            </a>

            <form method="POST" action="/logout">
                @csrf
                <button type="button"
                    onclick="openModal('logoutModal')"
                    class="w-full text-left px-4 py-3 text-xs text-red-400 hover:bg-red-500/10">
                    <i class="fas fa-power-off mr-2"></i> Logout
                </button>
            </form>

        </div>
    </div>
</aside>

<main class="flex-1 min-w-0 p-4 md:p-8 z-20 relative">

    {{-- Mobile header --}}
    <div class="md:hidden relative z-[300] overflow-visible flex items-center justify-between mb-8 p-4 rounded-xl border border-white/10 bg-black/80 backdrop-blur-xl shadow-2xl @yield('mobile-border', '')">
        <button onclick="toggleSidebar()" class="text-2xl @yield('accent-color', 'text-cyan-400')">
            <i class="fas fa-bars"></i>
        </button>
        <h1 class="font-orbitron font-bold text-xs tracking-widest uppercase">
            @yield('mobile-title')
        </h1>
        @include('partials.notifications')
    </div>

    <div class="hidden md:flex justify-end mb-4">
        @include('partials.notifications')
    </div>

    @yield('dashboard-content')

</main>

{{-- All modals injected per-dashboard --}}
@yield('modals')

@endsection

@push('scripts')
<script src="{{ asset('js/dashboard.js') }}"></script>
<script src="{{ asset('js/notifications.js') }}?v={{ filemtime(public_path('js/notifications.js')) }}"></script>
<script src="{{ asset('js/admin-push.js') }}?v={{ filemtime(public_path('js/admin-push.js')) }}"></script>
@endpush
