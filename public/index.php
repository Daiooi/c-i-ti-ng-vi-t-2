<?php

require_once __DIR__ . '/../app/bootstrap.php';
require_once BASE_PATH . '/app/layout.php';

require_login();

$pdo = db();
$stats = [
    'students' => (int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn(),
    'classes' => (int) $pdo->query('SELECT COUNT(*) FROM classes')->fetchColumn(),
    'subjects' => (int) $pdo->query('SELECT COUNT(*) FROM subjects')->fetchColumn(),
    'grades' => (int) $pdo->query('SELECT COUNT(*) FROM grades')->fetchColumn(),
    'active' => (int) $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Dang hoc'")->fetchColumn(),
];

$classes = $pdo->query('
    SELECT c.*, COUNT(s.id) AS student_count
    FROM classes c
    LEFT JOIN students s ON s.class_id = c.id
    GROUP BY c.id
    ORDER BY c.code
')->fetchAll();

$recentGrades = $pdo->query('
    SELECT g.*, s.student_code, s.full_name, c.code AS class_code
    FROM grades g
    JOIN students s ON s.id = g.student_id
    LEFT JOIN classes c ON c.id = s.class_id
    ORDER BY g.created_at DESC, g.id DESC
    LIMIT 6
')->fetchAll();

render_header('Tong quan', 'dashboard');
?>

<section class="stat-grid">
    <article class="stat-card">
        <span>Sinh vien</span>
        <strong><?= e($stats['students']) ?></strong>
    </article>
    <article class="stat-card">
        <span>Dang hoc</span>
        <strong><?= e($stats['active']) ?></strong>
    </article>
    <article class="stat-card">
        <span>Lop hoc</span>
        <strong><?= e($stats['classes']) ?></strong>
    </article>
    <article class="stat-card">
        <span>Mon hoc</span>
        <strong><?= e($stats['subjects']) ?></strong>
    </article>
    <article class="stat-card">
        <span>Bang diem</span>
        <strong><?= e($stats['grades']) ?></strong>
    </article>
</section>

<section class="two-column">
    <div class="panel">
        <div class="section-title">
            <h2>Lop hoc</h2>
            <a class="small-action" href="<?= e(app_url('classes.php')) ?>">Quan ly</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Ma lop</th>
                    <th>Ten lop</th>
                    <th>Nam hoc</th>
                    <th>Si so</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($classes as $class): ?>
                    <tr>
                        <td><?= e($class['code']) ?></td>
                        <td><?= e($class['name']) ?></td>
                        <td><?= e($class['school_year']) ?></td>
                        <td><?= e($class['student_count']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="section-title">
            <h2>Diem moi cap nhat</h2>
            <a class="small-action" href="<?= e(app_url('grades.php')) ?>">Nhap diem</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Sinh vien</th>
                    <th>Mon</th>
                    <th>TB</th>
                    <th>Xep loai</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recentGrades as $grade): ?>
                    <tr>
                        <td>
                            <strong><?= e($grade['full_name']) ?></strong>
                            <small><?= e($grade['student_code']) ?> - <?= e($grade['class_code'] ?? 'Chua co lop') ?></small>
                        </td>
                        <td><?= e($grade['subject_name']) ?></td>
                        <td><?= e(number_format((float) $grade['average_score'], 2)) ?></td>
                        <td><span class="status-pill"><?= e($grade['rank']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="panel">
    <div class="section-title">
        <h2>Tro ly ao</h2>
        <a class="small-action" href="<?= e(app_url('chatbot.php')) ?>">Mo chatbot</a>
    </div>
    <p class="muted">Chatbot tra cuu sinh vien, lop hoc va diem so bang ngon ngu tu nhien. Du lieu gui sang OpenAI duoc rut gon theo ket qua lien quan, khong gui mat khau hay cau truc he thong.</p>
</section>

<?php render_footer(); ?>
