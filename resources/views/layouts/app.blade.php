<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
    
    <script src="{{ asset('js/shared.js') }}"></script>

    @stack('scripts')
</body>
</html>