@extends('layouts.app')

@section('title', 'Academic Portal')

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

            {{-- LOGIN PANEL --}}
            <div id="loginMod" class="module module-active">
                <div class="flex justify-between items-center mb-6 border-b border-cyan-500/30 pb-4">
                    <h2 class="text-2xl font-orbitron font-black text-white tracking-tighter uppercase">
                        Login <span class="text-cyan-400">Account</span>
                    </h2>
                    <i class="fas fa-user-shield text-2xl text-cyan-500/40"></i>
                </div>

                <div class="text-center mb-6 bg-white/5 border border-white/10 rounded py-2">
                    <p class="text-[9px] text-slate-400 uppercase tracking-widest font-bold">
                        <i class="fas fa-microchip text-red-500 mr-1"></i> Admin &nbsp;|&nbsp;
                        <i class="fas fa-chalkboard-teacher text-purple-400 mr-1"></i> Teacher &nbsp;|&nbsp;
                        <i class="fas fa-user-graduate text-cyan-400 mr-1"></i> Student
                    </p>
                </div>

                {{-- Laravel form: POST to /login --}}
                <form method="POST" action="/login" class="space-y-5">
                    @csrf
                    <div class="form-group">
                        <label class="input-label">Email Address</label>
                        <div class="relative">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" name="email" required
                                   value="{{ old('email') }}"
                                   class="input-mobile-ultra" placeholder="Enter email">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="input-label">Password</label>
                        <div class="relative">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" id="lPass" name="password" required
                                   class="input-mobile-ultra pr-12" placeholder="Enter password">
                            <button type="button" onclick="tglPass('lPass','lIcon')"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-8 flex items-center justify-center text-slate-500">
                                <i id="lIcon" class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="button" onclick="openForgotModal()"
                                class="text-cyan-500 text-[10px] font-bold uppercase tracking-wider hover:text-white transition-all">
                            Forgot Password?
                        </button>
                    </div>

                    <button type="submit" class="btn-mobile-ultra">Sign In</button>
                </form>

                <div class="mt-8 text-center border-t border-white/5 pt-6">
                    <button onclick="swMod('reg')"
                            class="text-slate-300 text-[10px] font-black uppercase tracking-[0.2em] border border-white/20 px-6 py-2 hover:bg-white/5 transition-all">
                        Create New Account
                    </button>
                </div>
            </div>

            {{-- REGISTER PANEL --}}
            <div id="regMod" class="module">
                <div class="flex justify-between items-center mb-6 border-b border-purple-500/30 pb-4">
                    <h2 class="text-2xl font-orbitron font-black text-white tracking-tighter uppercase">
                        Register <span class="text-purple-500">Account</span>
                    </h2>
                    <i class="fas fa-user-plus text-2xl text-purple-500/40"></i>
                </div>

                <form method="POST" action="/register" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-2 mb-2">
                        <label class="cursor-pointer">
                            <input type="radio" name="role" value="student" class="hidden peer" checked>
                            <div class="role-card peer-checked:border-cyan-500 peer-checked:bg-cyan-500/10">
                                <i class="fas fa-user-graduate mb-1 text-sm block"></i>
                                <span class="text-[9px] font-bold uppercase">Student</span>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="role" value="pending_teacher" class="hidden peer">
                            <div class="role-card peer-checked:border-purple-500 peer-checked:bg-purple-500/10">
                                <i class="fas fa-chalkboard-teacher mb-1 text-sm block"></i>
                                <span class="text-[9px] font-bold uppercase">Teacher</span>
                            </div>
                        </label>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="form-group">
                            <label class="input-label">First Name</label>
                            <div class="relative">
                                <i class="fas fa-user input-icon"></i>
                                <input type="text" name="first_name" placeholder="First Name" required
                                       class="input-mobile-ultra">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="input-label">Last Name</label>
                            <div class="relative">
                                <i class="fas fa-id-card input-icon"></i>
                                <input type="text" name="last_name" placeholder="Last Name" required
                                       class="input-mobile-ultra">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="input-label">Email Address</label>
                        <div class="relative">
                            <i class="fas fa-at input-icon"></i>
                            <input type="email" name="email" placeholder="Enter email" required
                                   class="input-mobile-ultra">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="input-label">Password</label>
                        <div class="relative">
                            <i class="fas fa-key input-icon"></i>
                            <input type="password" id="rPass" name="password" placeholder="Create password" required
                                   class="input-mobile-ultra pr-12">
                            <button type="button" onclick="tglPass('rPass','rIco')"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-8 flex items-center justify-center text-slate-500">
                                <i id="rIco" class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="input-label">Confirm Password</label>
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

                    <button type="submit" class="btn-mobile-ultra mt-2">Register</button>
                </form>

                <button onclick="swMod('login')"
                        class="mt-6 w-full text-slate-500 text-[9px] font-bold uppercase tracking-widest">
                    <i class="fas fa-arrow-left mr-1"></i> Return to Login
                </button>
            </div>

        </div>
    </div>
</div>

{{-- Forgot Password Modal --}}
<div id="forgotModal" class="fixed inset-0 bg-black/40 z-[3000] flex items-center justify-center p-6 hidden">
    <div class="portal-frame w-full max-w-xs p-8 shadow-[0_0_100px_rgba(0,0,0,1)]">
        <div class="flex justify-between items-center mb-6 border-b border-cyan-500/30 pb-4">
            <h2 class="text-2xl font-orbitron font-black text-white tracking-tighter uppercase">
                Account <span class="text-cyan-400">Recovery</span>
            </h2>
        </div>
        <form method="POST" action="/forgot-password">
            @csrf
            <div class="form-group mb-6">
                <label class="input-label">Registered Email</label>
                <div class="relative">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" name="email" placeholder="Enter your email" class="input-mobile-ultra">
                </div>
            </div>
            <button type="submit" class="btn-mobile-ultra !py-4 mb-4">Send Reset Link</button>
        </form>
        <button onclick="closeForgotModal()"
                class="w-full text-slate-500 text-[10px] font-bold uppercase tracking-widest text-center">
            Cancel
        </button>
    </div>
</div>
@endsection

@push('scripts')
<script>
function swMod(mode) {
    const l = document.getElementById('loginMod');
    const r = document.getElementById('regMod');
    l.classList.remove('module-active');
    r.classList.remove('module-active');
    setTimeout(() => {
        if (mode === 'reg') {
            l.style.display = 'none'; r.style.display = 'block';
            setTimeout(() => r.classList.add('module-active'), 10);
        } else {
            r.style.display = 'none'; l.style.display = 'block';
            setTimeout(() => l.classList.add('module-active'), 10);
        }
    }, 200);
}
function openForgotModal() {
    document.getElementById('forgotModal').classList.remove('hidden');
    document.getElementById('main-content').classList.add('blur-bg');
}
function closeForgotModal() {
    document.getElementById('forgotModal').classList.add('hidden');
    document.getElementById('main-content').classList.remove('blur-bg');
}

{{-- If registration failed, re-open the register panel --}}
@if(old('role'))
document.addEventListener('DOMContentLoaded', () => swMod('reg'));
@endif
</script>
@endpush