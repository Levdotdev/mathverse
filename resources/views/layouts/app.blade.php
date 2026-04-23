<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>MathVerse | @yield('title', 'Academic Portal')</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" href="{{ asset('logo.png') }}" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@500;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">

    @stack('head')
</head>
<body class="@yield('body-class', 'flex items-center justify-center p-4 min-h-screen')">

    {{-- Background effects used on every page --}}
    <div class="stars-container"></div>
    <div class="digital-rain"></div>
    <div class="cyber-grid"></div>
    <div id="particle-container"></div>

    @yield('content')

    {{-- Toast notification - available on every page --}}
    <div id="toast" class="fixed bottom-4 right-4 bg-cyan-500 text-black font-bold px-6 py-3 rounded shadow-2xl translate-y-20 transition-all duration-300 z-[10000] text-xs uppercase">
        <i class="fas fa-info-circle mr-2"></i>
        <span id="toast-msg">Success</span>
    </div>

    {{-- Flash messages from Laravel converted to toast --}}
    @if(session('success') || session('error'))
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const msg   = @json(session('success') ?? session('error'));
            const toast = document.getElementById('toast');
            const span  = document.getElementById('toast-msg');
            if (toast && msg) {
                span.innerText = msg;
                @if(session('error'))
                toast.classList.remove('bg-cyan-500');
                toast.classList.add('bg-red-500');
                @endif
                toast.classList.remove('translate-y-20');
                setTimeout(() => toast.classList.add('translate-y-20'), 3000);
            }
        });
    </script>
    @endif

    {{-- Shared particle background script --}}
    <script>
    const _syms = ['+','−','×','÷','=','π','∑','√','Δ','∞','∫','f(x)','y²','x³'];
    function _spawn() {
        const c = document.getElementById('particle-container');
        if (!c || c.children.length > 10) return;
        const p = document.createElement('div');
        p.className   = 'particle';
        p.innerText   = _syms[Math.floor(Math.random() * _syms.length)];
        p.style.left  = Math.random() * 100 + 'vw';
        p.style.color = Math.random() > 0.5 ? '#00f2ff' : '#bc13fe';
        p.style.fontSize = (Math.random() * 15 + 15) + 'px';
        c.appendChild(p);
        setTimeout(() => p.remove(), 10000);
    }
    setInterval(_spawn, 1500);

    function showToast(message, isError = false) {
        const toast = document.getElementById('toast');
        if (!toast) return;
        document.getElementById('toast-msg').innerText = message;
        toast.classList.toggle('bg-red-500', isError);
        toast.classList.toggle('bg-cyan-500', !isError);
        toast.classList.remove('translate-y-20');
        setTimeout(() => toast.classList.add('translate-y-20'), 2500);
    }

    function tglPass(id, icoId) {
        const inp = document.getElementById(id);
        const ico = document.getElementById(icoId);
        if (inp.type === 'password') {
            inp.type = 'text';
            ico.classList.replace('fa-eye-slash', 'fa-eye');
            ico.classList.add('text-cyan-400');
        } else {
            inp.type = 'password';
            ico.classList.replace('fa-eye', 'fa-eye-slash');
            ico.classList.remove('text-cyan-400');
        }
    }

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text);
        showToast('Code Copied: ' + text);
    }
    </script>

    @stack('scripts')
</body>
</html>