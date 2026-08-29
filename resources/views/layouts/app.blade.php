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

    {{-- Reusable image-size alert for registration and profile forms --}}
    <div id="imageSizeModal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="image-size-title">
        <div class="portal-frame !p-8 w-full max-w-sm text-center border-red-500/50">
            <i class="fas fa-image text-4xl text-red-500 mb-4"></i>
            <h3 id="image-size-title" class="font-orbitron font-bold mb-2 uppercase text-white">
                Image <span class="text-red-500">Too Large</span>
            </h3>
            <p id="image-size-message" class="text-xs text-slate-400 mb-3">
                The selected image must be 2 MB or less.
            </p>
            <p id="image-size-file" class="text-[10px] font-mono text-red-400 break-all mb-8">
                Please choose a smaller image.
            </p>
            <div class="flex flex-col gap-3">
                <button type="button" onclick="chooseAnotherAvatar()"
                        class="btn-rect-primary !bg-red-600 !text-white">
                    <i class="fas fa-folder-open mr-2"></i> Choose Another Image
                </button>
                <button type="button" onclick="closeModal('imageSizeModal')"
                        class="text-[10px] font-bold uppercase text-slate-500">
                    Close
                </button>
            </div>
        </div>
    </div>

    @if(session('image_size_error'))
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const message = document.getElementById('image-size-message');
            if (message) {
                message.textContent = @json(session('image_size_error'));
            }
            openModal('imageSizeModal');
        });
    </script>
    @endif

    {{-- Toast notification - available on every page --}}
    <div id="toast" class="fixed bottom-6 right-4 bg-cyan-500 text-black font-bold px-6 py-3 rounded shadow-2xl opacity-0 pointer-events-none transition-all duration-300 z-[10000] text-xs uppercase">
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
                toast.classList.remove('opacity-0', 'pointer-events-none');
                toast.classList.add('opacity-100');
                setTimeout(() => {
                    toast.classList.remove('opacity-100');
                    toast.classList.add('opacity-0', 'pointer-events-none');
                }, 3000);
            }
        });
    </script>
    @endif

    @if($errors->any())
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            showToast(@json($errors->first()), true);
        });
    </script>
    @endif
    
    <script src="{{ asset('js/shared.js') }}?v={{ filemtime(public_path('js/shared.js')) }}"></script>

    @stack('scripts')
</body>
</html>
