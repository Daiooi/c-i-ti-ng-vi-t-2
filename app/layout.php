<?php

declare(strict_types=1);

function render_header(string $title, string $active = ''): void
{
    $user = current_user();
    $navItems = [
        ['key' => 'dashboard', 'label' => 'Tong quan', 'url' => 'index.php'],
        ['key' => 'students', 'label' => 'Sinh vien', 'url' => 'students.php'],
        ['key' => 'classes', 'label' => 'Lop hoc', 'url' => 'classes.php'],
        ['key' => 'subjects', 'label' => 'Mon hoc', 'url' => 'subjects.php'],
        ['key' => 'grades', 'label' => 'Diem so', 'url' => 'grades.php'],
        ['key' => 'chatbot', 'label' => 'Tro ly AI', 'url' => 'chatbot.php'],
    ];
    ?>
    <!doctype html>
    <html lang="vi">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> - <?= e(app_config('app_name')) ?></title>
        <link rel="stylesheet" href="<?= e(app_url('assets/styles.css')) ?>">
    </head>
    <body>
    <div class="app-shell">
        <aside class="sidebar">
            <a class="brand" href="<?= e(app_url('index.php')) ?>">
                <span class="brand-mark">SV</span>
                <span>
                    <strong>Quan ly SV</strong>
                    <small>PHP co ban</small>
                </span>
            </a>

            <nav class="nav-list" aria-label="Dieu huong chinh">
                <?php foreach ($navItems as $item): ?>
                    <a class="<?= $active === $item['key'] ? 'active' : '' ?>" href="<?= e(app_url($item['url'])) ?>">
                        <?= e($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php if ($user): ?>
                <div class="user-panel">
                    <span><?= e($user['name']) ?></span>
                    <small><?= e($user['email']) ?></small>
                    <form method="post" action="<?= e(app_url('logout.php')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <button class="link-button" type="submit">Dang xuat</button>
                    </form>
                </div>
            <?php endif; ?>
        </aside>

        <main class="content">
            <header class="page-header">
                <div>
                    <p class="eyebrow">He thong quan ly sinh vien thong minh</p>
                    <h1><?= e($title) ?></h1>
                </div>
            </header>

            <?php foreach (consume_flash() as $message): ?>
                <div class="alert <?= e($message['type']) ?>"><?= e($message['message']) ?></div>
            <?php endforeach; ?>
    <?php
}

function render_footer(): void
{
    ?>
        </main>
    </div>
    <script src="<?= e(app_url('assets/app.js')) ?>"></script>
    </body>
    </html>
    <?php
}
