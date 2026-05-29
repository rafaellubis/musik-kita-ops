<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — Musik KITA</title>
<link href="https://fonts.bunny.net/css?family=dm-sans:300,400,500,600,700|playfair-display:600,700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'DM Sans', sans-serif;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #EDE0CC;
    position: relative;
    overflow: hidden;
}

body::before {
    content: '';
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 600px;
    height: 500px;
    background: radial-gradient(ellipse, rgba(101,65,27,0.08) 0%, transparent 70%);
    pointer-events: none;
}

body::after {
    content: '';
    position: fixed;
    inset: 0;
    background-image: radial-gradient(rgba(101,65,27,0.07) 1px, transparent 1px);
    background-size: 28px 28px;
    pointer-events: none;
}

.card {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 400px;
    margin: 24px;
    background: #FBF5EC;
    border: 1px solid rgba(101,65,27,0.18);
    border-radius: 16px;
    padding: 40px 36px 36px;
    box-shadow: 0 8px 40px rgba(101,65,27,0.14), 0 1px 0 rgba(255,255,255,0.80) inset;
}

.logo-wrap {
    display: flex;
    justify-content: center;
    margin-bottom: 28px;
}
.logo-wrap img {
    height: 44px;
    object-fit: contain;
}

.divider {
    height: 1px;
    background: linear-gradient(to right, transparent, rgba(101,65,27,0.20), transparent);
    margin-bottom: 28px;
}

.heading {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 20px;
    font-weight: 700;
    color: #2C1A07;
    letter-spacing: -0.01em;
    text-align: center;
    margin-bottom: 4px;
}
.subheading {
    font-size: 12px;
    color: #9A7050;
    text-align: center;
    margin-bottom: 28px;
    letter-spacing: 0.03em;
}

label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    color: #7A5C3A;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    margin-bottom: 6px;
}

input[type="email"],
input[type="text"].login-field,
input[type="password"] {
    width: 100%;
    background: #FFFFFF;
    border: 1px solid rgba(101,65,27,0.20);
    border-radius: 8px;
    padding: 10px 14px;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    color: #2C1A07;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
    margin-bottom: 4px;
}
input:focus {
    border-color: rgba(160,82,45,0.55);
    box-shadow: 0 0 0 3px rgba(160,82,45,0.10);
}
input::placeholder { color: #C0A882; }

.field-wrap { margin-bottom: 18px; }

.field-error {
    font-size: 11px;
    color: #A0522D;
    margin-top: 4px;
}

.alert-status {
    background: rgba(160,82,45,0.08);
    border: 1px solid rgba(160,82,45,0.20);
    color: #7A5C3A;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 12px;
    margin-bottom: 20px;
}

.remember {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 24px;
}
.remember input[type="checkbox"] {
    width: 15px;
    height: 15px;
    margin: 0;
    accent-color: #A0522D;
    cursor: pointer;
}
.remember label {
    font-size: 12px;
    font-weight: 400;
    color: #7A5C3A;
    text-transform: none;
    letter-spacing: 0;
    margin: 0;
    cursor: pointer;
}

button[type="submit"] {
    width: 100%;
    background: #A0522D;
    color: #FBF5EC;
    border: none;
    border-radius: 8px;
    padding: 11px 0;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.02em;
    cursor: pointer;
    transition: background 0.15s, box-shadow 0.15s;
    box-shadow: 0 2px 12px rgba(160,82,45,0.20);
}
button[type="submit"]:hover {
    background: #8B4226;
    box-shadow: 0 4px 18px rgba(160,82,45,0.30);
}

.card-footer {
    margin-top: 28px;
    padding-top: 18px;
    border-top: 1px solid rgba(101,65,27,0.12);
    text-align: center;
    font-size: 11px;
    color: #C0A882;
}
.card-footer span { color: #9A7050; }
</style>
</head>
<body>

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
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="field-wrap">
            <label for="password">Password</label>
            <input type="password" id="password" name="password"
                   placeholder="••••••••"
                   required autocomplete="current-password">
            @error('password')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="remember">
            <input type="checkbox" id="remember_me" name="remember">
            <label for="remember_me">Ingat saya</label>
        </div>

        <button type="submit">Masuk</button>
    </form>

    <div class="card-footer">
        <span>Musik KITA</span> &mdash; Sistem Internal Studio &mdash; <span>v1.0</span>
    </div>
</div>

</body>
</html>
