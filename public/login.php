<?php

require_once __DIR__ . '/../app/bootstrap.php';

if (current_user()) {
    redirect('index.php');
}

$error = '';

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Phien lam viec khong hop le, vui long thu lai.';
    } else {
        $username = input_string('username');
        $password = (string) ($_POST['password'] ?? '');

        $stmt = db()->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'username' => $user['username'],
                'role' => $user['role'],
            ];
            redirect('index.php');
        }

        $error = 'Ten dang nhap hoac mat khau khong dung.';
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dang nhap - <?= e(app_config('app_name')) ?></title>
    <link rel="stylesheet" href="<?= e(app_url('assets/styles.css')) ?>">
</head>
<body class="login-page">
    <main class="login-card">
        <div class="brand login-brand">
            <span class="brand-mark">SV</span>
            <span>
                <strong>Quan ly sinh vien</strong>
                <small>Tro ly ao AI</small>
            </span>
        </div>

        <h1>Dang nhap giang vien</h1>
        <p class="muted">Tai khoan mau: <strong>admin</strong> / <strong>admin123</strong></p>

        <?php if ($error): ?>
            <div class="alert danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" class="form-stack">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label>
                Ten dang nhap
                <input type="text" name="username" value="<?= e($_POST['username'] ?? 'admin') ?>" required autofocus>
            </label>
            <label>
                Mat khau
                <input type="password" name="password" required>
            </label>
            <button class="primary-button" type="submit">Dang nhap</button>
        </form>
    </main>
</body>
</html>
