<?php
require_once 'config/database.php';

if (isLogin()) redirect('index.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $u = $stmt->fetch();

        if ($u && password_verify($password, $u['password'])) {
            $_SESSION['user_id']   = $u['id'];
            $_SESSION['user_nama'] = $u['nama'];
            $_SESSION['user_role'] = $u['role'];
            redirect('index.php');
        } else {
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login · Kasir BMT</title>
<link rel="stylesheet" href="assets/login.css">
</head>
<body>

<main class="login-card">

    <div class="brand">
        <div class="brand-logo">
            <img src="storage/loge.png" alt="Logo Kasir BMT">
        </div>
        <h1>Kasir BMT</h1>
        <p>BAITUL MAAL WAT TAMWIL</p>
    </div>

    <h2 class="form-title">Selamat datang kembali</h2>
    <p class="form-desc">Masuk untuk mengakses dashboard kasir.</p>

    <?php if ($error): ?>
        <div class="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form id="loginForm" method="POST" autocomplete="on">
        <div class="form-group">
            <label class="form-label" for="username">Username</label>
            <div class="input-wrapper">
                <span class="input-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="8" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/>
                    </svg>
                </span>
                <input class="form-input" type="text" name="username" id="username" placeholder="Masukkan username" required autofocus>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <div class="input-wrapper">
                <span class="input-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </span>
                <input class="form-input" type="password" name="password" id="password" placeholder="Masukkan password" required>
                <button class="password-toggle" type="button" id="togglePassword" aria-label="Tampilkan password">
                    <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>
        </div>

        <button class="login-button" type="submit" id="loginButton">
            <span id="buttonText">Masuk ke Dashboard</span>
        </button>
    </form>

    <footer class="form-footer">
        &copy; <?= date('Y') ?> Kasir BMT
    </footer>

</main>

<script src="assets/login.js"></script>
</body>
</html>