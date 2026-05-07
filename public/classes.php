<?php

require_once __DIR__ . '/../app/bootstrap.php';
require_once BASE_PATH . '/app/layout.php';

require_login();

$pdo = db();
$errors = [];

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Phien lam viec khong hop le, vui long thu lai.';
    } else {
        $action = $_POST['action'] ?? 'save';
        $id = (int) ($_POST['id'] ?? 0);

        if ($action === 'delete') {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM students WHERE class_id = :id');
            $stmt->execute([':id' => $id]);
            if ((int) $stmt->fetchColumn() > 0) {
                $errors[] = 'Khong the xoa lop dang co sinh vien. Hay chuyen sinh vien sang lop khac truoc.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM classes WHERE id = :id');
                $stmt->execute([':id' => $id]);
                flash('success', 'Da xoa lop hoc.');
                redirect('classes.php');
            }
        } else {
            $code = input_string('code');
            $name = input_string('name');
            $faculty = nullable_string((string) ($_POST['faculty'] ?? ''));
            $schoolYear = nullable_string((string) ($_POST['school_year'] ?? ''));
            $homeroomTeacher = nullable_string((string) ($_POST['homeroom_teacher'] ?? ''));
            $notes = nullable_string((string) ($_POST['notes'] ?? ''));

            if ($code === '') {
                $errors[] = 'Vui long nhap ma lop.';
            }
            if ($name === '') {
                $errors[] = 'Vui long nhap ten lop.';
            }

            if (!$errors) {
                try {
                    if ($id > 0) {
                        $stmt = $pdo->prepare('
                            UPDATE classes
                            SET code = :code,
                                name = :name,
                                faculty = :faculty,
                                school_year = :school_year,
                                homeroom_teacher = :homeroom_teacher,
                                notes = :notes
                            WHERE id = :id
                        ');
                        $stmt->execute([
                            ':code' => $code,
                            ':name' => $name,
                            ':faculty' => $faculty,
                            ':school_year' => $schoolYear,
                            ':homeroom_teacher' => $homeroomTeacher,
                            ':notes' => $notes,
                            ':id' => $id,
                        ]);
                        flash('success', 'Da cap nhat lop hoc.');
                    } else {
                        $stmt = $pdo->prepare('
                            INSERT INTO classes (code, name, faculty, school_year, homeroom_teacher, notes)
                            VALUES (:code, :name, :faculty, :school_year, :homeroom_teacher, :notes)
                        ');
                        $stmt->execute([
                            ':code' => $code,
                            ':name' => $name,
                            ':faculty' => $faculty,
                            ':school_year' => $schoolYear,
                            ':homeroom_teacher' => $homeroomTeacher,
                            ':notes' => $notes,
                        ]);
                        flash('success', 'Da them lop hoc moi.');
                    }
                    redirect('classes.php');
                } catch (PDOException $exception) {
                    $errors[] = str_contains($exception->getMessage(), 'UNIQUE')
                        ? 'Ma lop da ton tai.'
                        : 'Khong the luu lop hoc. Vui long kiem tra lai du lieu.';
                }
            }
        }
    }
}

$editing = null;
if ((int) ($_GET['edit'] ?? 0) > 0) {
    $stmt = $pdo->prepare('SELECT * FROM classes WHERE id = :id');
    $stmt->execute([':id' => (int) $_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

if (is_post() && $errors && ($_POST['action'] ?? '') !== 'delete') {
    $editing = [
        'id' => (int) ($_POST['id'] ?? 0),
        'code' => $_POST['code'] ?? '',
        'name' => $_POST['name'] ?? '',
        'faculty' => $_POST['faculty'] ?? '',
        'school_year' => $_POST['school_year'] ?? '',
        'homeroom_teacher' => $_POST['homeroom_teacher'] ?? '',
        'notes' => $_POST['notes'] ?? '',
    ];
}

$q = input_string('q');
$params = [];
$where = '';
if ($q !== '') {
    $where = 'WHERE c.code LIKE :q OR c.name LIKE :q OR c.faculty LIKE :q OR c.homeroom_teacher LIKE :q';
    $params[':q'] = '%' . $q . '%';
}

$stmt = $pdo->prepare("
    SELECT c.*, COUNT(s.id) AS student_count
    FROM classes c
    LEFT JOIN students s ON s.class_id = c.id
    $where
    GROUP BY c.id
    ORDER BY c.code
");
$stmt->execute($params);
$classes = $stmt->fetchAll();

function class_value(?array $record, string $key, string $default = ''): string
{
    return (string) ($record[$key] ?? $default);
}

render_header('Quan ly lop hoc', 'classes');
?>

<?php foreach ($errors as $error): ?>
    <div class="alert danger"><?= e($error) ?></div>
<?php endforeach; ?>

<section class="management-grid">
    <div class="panel">
        <div class="section-title">
            <h2>Danh sach lop</h2>
            <span class="count-badge"><?= count($classes) ?> ket qua</span>
        </div>

        <form class="filter-bar" method="get">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Tim ma lop, ten lop, khoa, co van">
            <button class="secondary-button" type="submit">Tim</button>
            <a class="ghost-button" href="<?= e(app_url('classes.php')) ?>">Lam moi</a>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Ma lop</th>
                    <th>Ten lop</th>
                    <th>Khoa</th>
                    <th>Co van</th>
                    <th>Si so</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($classes as $class): ?>
                    <tr>
                        <td><?= e($class['code']) ?></td>
                        <td>
                            <strong><?= e($class['name']) ?></strong>
                            <small><?= e($class['school_year'] ?? '') ?></small>
                        </td>
                        <td><?= e($class['faculty'] ?? '') ?></td>
                        <td><?= e($class['homeroom_teacher'] ?? '') ?></td>
                        <td><?= e($class['student_count']) ?></td>
                        <td class="row-actions">
                            <a class="small-action" href="<?= e(app_url('classes.php?edit=' . $class['id'])) ?>">Sua</a>
                            <form method="post" onsubmit="return confirm('Xoa lop hoc nay?');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= e($class['id']) ?>">
                                <button class="danger-link" type="submit">Xoa</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$classes): ?>
                    <tr><td colspan="6" class="empty-cell">Khong co lop hoc phu hop.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <aside class="panel form-panel">
        <div class="section-title">
            <h2><?= $editing ? 'Sua lop hoc' : 'Them lop hoc' ?></h2>
            <?php if ($editing): ?>
                <a class="small-action" href="<?= e(app_url('classes.php')) ?>">Them moi</a>
            <?php endif; ?>
        </div>

        <form method="post" class="form-stack">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= e(class_value($editing, 'id')) ?>">
            <input type="hidden" name="action" value="save">

            <label>Ma lop
                <input type="text" name="code" value="<?= e(class_value($editing, 'code')) ?>" required>
            </label>
            <label>Ten lop
                <input type="text" name="name" value="<?= e(class_value($editing, 'name')) ?>" required>
            </label>
            <label>Khoa
                <input type="text" name="faculty" value="<?= e(class_value($editing, 'faculty')) ?>">
            </label>
            <div class="form-row">
                <label>Nam hoc
                    <input type="text" name="school_year" value="<?= e(class_value($editing, 'school_year')) ?>" placeholder="2025-2029">
                </label>
                <label>Co van hoc tap
                    <input type="text" name="homeroom_teacher" value="<?= e(class_value($editing, 'homeroom_teacher')) ?>">
                </label>
            </div>
            <label>Ghi chu
                <textarea name="notes" rows="4"><?= e(class_value($editing, 'notes')) ?></textarea>
            </label>
            <button class="primary-button" type="submit"><?= $editing ? 'Cap nhat' : 'Them lop hoc' ?></button>
        </form>
    </aside>
</section>

<?php render_footer(); ?>
