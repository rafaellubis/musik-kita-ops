<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — Musik KITA</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
<style>
:root {
    /* Colors from DESIGN.md */
    --color-background: #f6faf8;
    --color-surface: #ffffff;
    --color-on-surface: #181d1c;
    --color-on-surface-variant: #544341;
    --color-primary: #2a0002; /* Deep Mahogany */
    --color-on-primary: #ffffff;
    --color-secondary: #2c694e; /* Mint Secondary */
    --color-on-secondary: #ffffff;
    --color-outline: #877270;
    --color-outline-variant: #dac1bf;
    --color-border-input: #d1eadf;
    --color-error: #ba1a1a;
    --color-error-container: #ffdad6;
    --color-on-error-container: #93000a;
    
    /* Typography */
    --font-sans: 'Hanken Grotesk', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    
    /* Spacing & Rounded */
    --rounded-sm: 0.25rem;
    --rounded-default: 0.5rem;
    --rounded-md: 0.75rem;
    --rounded-lg: 1rem;
    --rounded-xl: 1.5rem;
    --rounded-full: 9999px;
    
    --spacing-xs: 4px;
    --spacing-base: 8px;
    --spacing-sm: 12px;
    --spacing-md: 16px;
    --spacing-lg: 24px;
    --spacing-xl: 32px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: var(--font-sans);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: var(--color-background);
    position: relative;
    overflow: hidden;
}

/* Background elements for premium aesthetic */
body::before {
    content: '';
    position: absolute;
    width: 600px;
    height: 600px;
    background: radial-gradient(circle, rgba(44, 105, 78, 0.06) 0%, rgba(246, 250, 248, 0) 70%);
    top: -100px;
    right: -100px;
    pointer-events: none;
    z-index: 0;
}

body::after {
    content: '';
    position: absolute;
    width: 500px;
    height: 500px;
    background: radial-gradient(circle, rgba(42, 0, 2, 0.04) 0%, rgba(246, 250, 248, 0) 70%);
    bottom: -100px;
    left: -100px;
    pointer-events: none;
    z-index: 0;
}

.bg-pattern {
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(44, 105, 78, 0.05) 1px, transparent 1px);
    background-size: 28px 28px;
    pointer-events: none;
    z-index: 0;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.card {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 420px;
    margin: var(--spacing-md);
    background: var(--color-surface);
    border: 1px solid #e0eae5;
    border-radius: var(--rounded-lg); /* 1rem = 16px */
    padding: var(--spacing-xl) var(--spacing-lg);
    box-shadow: 0 10px 30px rgba(74, 14, 14, 0.04), 
                0 1px 3px rgba(74, 14, 14, 0.02),
                inset 0 1px 0 rgba(255, 255, 255, 0.6);
    backdrop-filter: blur(10px);
    animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
}

@media (min-width: 768px) {
    .card {
        padding: 48px 40px 40px;
        margin: var(--spacing-lg);
    }
}

.logo-wrap {
    display: flex;
    justify-content: center;
    margin-bottom: var(--spacing-lg);
    transition: transform 0.3s ease;
}

.logo-wrap:hover {
    transform: scale(1.03);
}

.logo-wrap img {
    height: 48px;
    object-fit: contain;
}

.divider {
    height: 1px;
    background: linear-gradient(to right, transparent, rgba(44, 105, 78, 0.15), transparent);
    margin-bottom: var(--spacing-lg);
}

.heading {
    font-size: 24px;
    font-weight: 700;
    color: var(--color-primary); /* Deep Mahogany */
    letter-spacing: -0.02em;
    text-align: center;
    margin-bottom: 6px;
    line-height: 32px;
}

.subheading {
    font-size: 11px;
    font-weight: 600;
    color: var(--color-secondary); /* Mint */
    text-align: center;
    margin-bottom: var(--spacing-lg);
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.field-wrap {
    margin-bottom: 20px;
}

label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--color-on-surface-variant);
    letter-spacing: 0.05em;
    text-transform: uppercase;
    margin-bottom: var(--spacing-xs);
}

input[type="email"],
input[type="text"],
input[type="password"] {
    width: 100%;
    background-color: var(--color-surface);
    border: 1px solid var(--color-border-input);
    border-radius: var(--rounded-default); /* 8px */
    padding: 12px 14px;
    font-family: var(--font-sans);
    font-size: 14px;
    color: var(--color-on-surface);
    outline: none;
    transition: border-color 0.2s cubic-bezier(0.4, 0, 0.2, 1), 
                box-shadow 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

input:focus {
    border-color: var(--color-primary); /* Deep Mahogany */
    box-shadow: 0 0 0 3px rgba(42, 0, 2, 0.08);
}

input::placeholder {
    color: #a3b8b0;
}

.password-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.password-wrap input {
    padding-right: 44px;
}

.password-toggle {
    position: absolute;
    right: 12px;
    background: none;
    border: none;
    cursor: pointer;
    color: var(--color-outline);
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s ease;
    z-index: 2;
}

.password-toggle:hover {
    color: var(--color-primary);
}

.password-toggle svg {
    width: 20px;
    height: 20px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.field-error {
    font-size: 12px;
    color: var(--color-error);
    margin-top: 6px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.alert-status {
    background-color: var(--color-error-container);
    border: 1px solid rgba(186, 26, 26, 0.15);
    color: var(--color-on-error-container);
    border-radius: var(--rounded-default);
    padding: 12px var(--spacing-md);
    font-size: 13px;
    margin-bottom: var(--spacing-lg);
    line-height: 1.4;
}

.remember {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: var(--spacing-lg);
}

.remember input[type="checkbox"] {
    appearance: none;
    -webkit-appearance: none;
    width: 16px;
    height: 16px;
    border: 1px solid var(--color-border-input);
    border-radius: 4px;
    background-color: var(--color-surface);
    display: inline-grid;
    place-content: center;
    cursor: pointer;
    transition: border-color 0.2s, background-color 0.2s;
}

.remember input[type="checkbox"]::before {
    content: "";
    width: 10px;
    height: 10px;
    transform: scale(0);
    transition: 120ms transform ease-in-out;
    box-shadow: inset 1em 1em var(--color-on-secondary);
    background-color: currentColor;
    clip-path: polygon(14% 44%, 0 58%, 38% 96%, 100% 23%, 86% 9%, 38% 70%);
}

.remember input[type="checkbox"]:checked {
    background-color: var(--color-secondary); /* Mint background */
    border-color: var(--color-secondary);
}

.remember input[type="checkbox"]:checked::before {
    transform: scale(1);
}

.remember input[type="checkbox"]:focus-visible {
    outline: 2px solid var(--color-primary);
    outline-offset: 2px;
}

.remember label {
    font-size: 13px;
    font-weight: 400;
    color: var(--color-on-surface-variant);
    text-transform: none;
    letter-spacing: 0;
    margin: 0;
    cursor: pointer;
    user-select: none;
}

button[type="submit"] {
    width: 100%;
    background-color: var(--color-primary); /* Deep Mahogany */
    color: var(--color-on-primary);
    border: none;
    border-radius: var(--rounded-default); /* 8px */
    padding: 12px 16px;
    font-family: var(--font-sans);
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    cursor: pointer;
    transition: background-color 0.2s ease, transform 0.1s ease, box-shadow 0.2s ease;
    box-shadow: 0 4px 12px rgba(42, 0, 2, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

button[type="submit"]:hover {
    background-color: #4a0e0e; /* Slightly lighter/richer mahogany for hover */
    box-shadow: 0 6px 18px rgba(42, 0, 2, 0.25);
}

button[type="submit"]:active {
    transform: scale(0.98);
}

button[type="submit"]:focus-visible {
    outline: 2px solid var(--color-secondary);
    outline-offset: 2px;
}

.card-footer {
    margin-top: var(--spacing-xl);
    padding-top: var(--spacing-md);
    border-top: 1px solid rgba(44, 105, 78, 0.08);
    text-align: center;
    font-size: 11px;
    color: var(--color-outline);
    letter-spacing: 0.02em;
    line-height: 1.5;
}

.card-footer span {
    color: var(--color-primary);
    font-weight: 600;
}
</style>
</head>
<body>

<div class="bg-pattern"></div>

<div class="card">

    <div class="logo-wrap">
        <img src="{{ asset('images/logo-musikkita-light-mode.PNG') }}" alt="Musik KITA">
    </div>

    <div class="divider"></div>

    <h1 class="heading">Selamat Datang</h1>
    <p class="subheading">SISTEM ADMINISTRASI STUDIO</p>

    @if (session('status'))
        <div class="alert-status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="field-wrap">
            <label for="login">Email atau Username</label>
            <input type="text" id="login" name="login"
                   class="login-field"
                   value="{{ old('login') }}"
                   placeholder="thomas atau nama@musikkita.local"
                   required autofocus
                   autocomplete="username"
                   autocapitalize="off"
                   autocorrect="off"
                   spellcheck="false"
                   inputmode="text">
            @error('login')
                <div class="field-error">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    {{ $message }}
                </div>
            @enderror
        </div>

        <div class="field-wrap">
            <label for="password">Password</label>
            <div class="password-wrap">
                <input type="password" id="password" name="password"
                       placeholder="••••••••"
                       required autocomplete="current-password">
                <button type="button" class="password-toggle" id="password-toggle-btn" aria-label="Tampilkan password">
                    <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <svg class="eye-closed" style="display: none;" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path>
                        <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path>
                        <path d="M6.61 6.61A13.52 13.52 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path>
                        <line x1="2" y1="2" x2="22" y2="22"></line>
                    </svg>
                </button>
            </div>
            @error('password')
                <div class="field-error">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    {{ $message }}
                </div>
            @enderror
        </div>

        <div class="remember">
            <input type="checkbox" id="remember_me" name="remember">
            <label for="remember_me">Ingat saya</label>
        </div>

        <button type="submit">Masuk</button>
    </form>

    <div class="card-footer">
        <span>Musik KITA</span> &mdash; Sistem Internal Studio &mdash; <span>v2.0</span>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    const toggleBtn = document.getElementById('password-toggle-btn');
    
    if (toggleBtn && passwordInput) {
        toggleBtn.addEventListener('click', function() {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            
            const eyeOpen = toggleBtn.querySelector('.eye-open');
            const eyeClosed = toggleBtn.querySelector('.eye-closed');
            
            if (isPassword) {
                eyeOpen.style.display = 'none';
                eyeClosed.style.display = 'block';
                toggleBtn.setAttribute('aria-label', 'Sembunyikan password');
            } else {
                eyeOpen.style.display = 'block';
                eyeClosed.style.display = 'none';
                toggleBtn.setAttribute('aria-label', 'Tampilkan password');
            }
        });
    }
});
</script>

</body>
</html>
