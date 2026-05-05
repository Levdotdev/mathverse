function swMod(mode) {
    const l = document.getElementById('loginMod');
    const r = document.getElementById('regMod');
    l.classList.remove('module-active');
    r.classList.remove('module-active');
    setTimeout(() => {
        if (mode === 'reg') {
            l.style.display = 'none';
            r.style.display = 'block';
            setTimeout(() => r.classList.add('module-active'), 10);
        } else {
            r.style.display = 'none';
            l.style.display = 'block';
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

function previewAvatar(input) {
    const file    = input.files[0];
    const preview = document.getElementById('avatar-preview');
    const placeholder = document.getElementById('avatar-placeholder');

    if (!file) return;

    // Validate size (2MB max)
    if (file.size > 2 * 1024 * 1024) {
        alert('Image must be under 2MB.');
        input.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = e => {
        preview.src = e.target.result;
        preview.classList.remove('hidden');
        placeholder.classList.add('hidden');
    };
    reader.readAsDataURL(file);
}

function toggleGradeLevel(role) {
    const field = document.getElementById('grade-level-field');
    if (!field) return;
    if (role === 'student') {
        field.classList.remove('hidden');
    } else {
        field.classList.add('hidden');
    }
}