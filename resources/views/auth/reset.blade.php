@extends('layouts.app')

@section('title', 'Reset Password')

@section('content')
<div class="w-full max-w-sm z-20 transition-all duration-500" id="main-content">

    <div class="text-center mb-8">
        <h1 class="font-orbitron text-4xl font-black tracking-tighter text-white glitch-title leading-none">
            MATH<span class="text-cyan-400">VERSE</span>
        </h1>
        <p class="text-slate-400 uppercase text-[9px] tracking-[0.4em] mt-2 font-bold">Official Academic Gateway</p>
    </div>

    <div class="portal-frame relative">
        <div class="corner top-0 left-0 border-r-0 border-b-0"></div>
        <div class="corner top-0 right-0 border-l-0 border-b-0"></div>
        <div class="corner bottom-0 left-0 border-r-0 border-t-0"></div>
        <div class="corner bottom-0 right-0 border-l-0 border-t-0"></div>

        <div class="p-6">

            {{-- REGISTER PANEL --}}
            <div id="regMod">
                <div class="flex justify-between items-center mb-6 border-b border-purple-500/30 pb-4">
                    <h2 class="text-2xl font-orbitron font-black text-white tracking-tighter uppercase">
                        Register <span class="text-purple-500">Account</span>
                    </h2>
                    <i class="fas fa-user-plus text-2xl text-purple-500/40"></i>
                </div>

                <form id="resetForm" method="POST" action="/update-password" class="space-y-4">
                    @csrf

                    <input type="hidden" id="token" name="token">

                    <div class="form-group">
                        <label class="input-label">New Password</label>
                        <div class="relative">
                            <i class="fas fa-key input-icon"></i>
                            <input type="password" id="rPass" name="password" placeholder="Enter new password" required
                                   class="input-mobile-ultra pr-12">
                            <button type="button" onclick="tglPass('rPass','rIco')"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-8 flex items-center justify-center text-slate-500">
                                <i id="rIco" class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="input-label">Confirm New Password</label>
                        <div class="relative">
                            <i class="fas fa-shield-alt input-icon"></i>
                            <input type="password" id="rcPass" name="password_confirmation"
                                   placeholder="Re-type password" required class="input-mobile-ultra pr-12">
                            <button type="button" onclick="tglPass('rcPass','rcIco')"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-8 flex items-center justify-center text-slate-500">
                                <i id="rcIco" class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-mobile-ultra mt-2">Change Password</button>
                </form>

                <button onclick="window.location.href='/'"
                        class="mt-6 w-full text-slate-500 text-[9px] font-bold uppercase tracking-widest">
                    <i class="fas fa-arrow-left mr-1"></i> Return to Login
                </button>
            </div>

        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    const params = new URLSearchParams(window.location.search);
    const token  = params.get('token_hash');  // reads token_hash from URL

    if (!token) {
        alert('No reset token found in URL. Please use the link from your email.');
    }

    document.getElementById('token').value = token;
    console.log('Token set:', token); // check this in console
</script>
@endpush