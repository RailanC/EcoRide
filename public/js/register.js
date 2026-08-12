function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    btn.innerHTML = isText
        ? `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`
        : `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`;
}

function checkStrength(value) {
    const bars = document.querySelectorAll('.strength-bar');
    const label = document.getElementById('strength-label');
    let score = 0;
    if (value.length >= 8) score++;
    if (/[A-Z]/.test(value)) score++;
    if (/[0-9]/.test(value)) score++;
    if (/[^A-Za-z0-9]/.test(value)) score++;

    const colors = ['#f28b82', '#f7c948', '#a7d930', 'var(--vert)'];
    const labels = ['Très faible', 'Faible', 'Bon', 'Fort'];

    bars.forEach((bar, i) => {
        bar.style.background = i < score ? colors[score - 1] : 'rgba(255,255,255,0.08)';
    });

    label.textContent = value.length ? labels[score - 1] || '' : '';
    label.style.color = score > 0 ? colors[score - 1] : 'var(--text-muted)';
}

function checkMatch() {
    const pw = document.getElementById('regPassword').value;
    const confirm = document.getElementById('confirmPassword').value;
    const label = document.getElementById('match-label');
    if (!confirm) { label.textContent = ''; return; }
    if (pw === confirm) {
        label.textContent = '✓ Les mots de passe correspondent';
        label.style.color = 'var(--vert)';
    } else {
        label.textContent = '✗ Les mots de passe ne correspondent pas';
        label.style.color = '#f28b82';
    }
}

function setRole(btn, role) {
    document.querySelectorAll('.trip-toggle .trip-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('roleInput').value = role;
}