<?php

require_once __DIR__ . '/../app/bootstrap.php';
require_once BASE_PATH . '/app/layout.php';

require_login();

$pdo = db();
$errors = [];
$statuses = ['Dang mo', 'Tam dung', 'Da dong'];
$subjectTypes = ['Bat buoc', 'Tu chon', 'Chuyen nganh'];

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Phien lam viec khong hop le, vui long thu lai.';
    } else {
        $action = $_POST['action'] ?? 'save';
        $id = (int) ($_POST['id'] ?? 0);

        if ($action === 'delete') {
            $stmt = $pdo->prepare('SELECT name FROM subjects WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $subject = $stmt->fetch();

            if (!$subject) {
                $errors[] = 'Mon hoc khong ton tai.';
            } else {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM grades WHERE subject_name = :subject_name');
                $stmt->execute([':subject_name' => $subject['name']]);
                $gradeCount = (int) $stmt->fetchColumn();

                if ($gradeCount > 0) {
                    $errors[] = 'Khong the xoa mon hoc da co du lieu diem. Hay chuyen trang thai sang "Da dong".';
                } else {
                    $stmt = $pdo->prepare('DELETE FROM subjects WHERE id = :id');
                    $stmt->execute([':id' => $id]);
                    flash('success', 'Da xoa mon hoc.');
                    redirect('subjects.php');
                }
            }
        } else {
            $code = strtoupper(input_string('code'));
            $name = input_string('name');
            $creditsRaw = $_POST['credits'] ?? '';
            $department = nullable_string((string) ($_POST['department'] ?? ''));
            $subjectType = in_array($_POST['subject_type'] ?? '', $subjectTypes, true) ? $_POST['subject_type'] : 'Bat buoc';
            $status = in_array($_POST['status'] ?? '', $statuses, true) ? $_POST['status'] : 'Dang mo';
            $notes = nullable_string((string) ($_POST['notes'] ?? ''));

            if ($code === '') {
                $errors[] = 'Vui long nhap ma mon hoc.';
            }
            if ($name === '') {
                $errors[] = 'Vui long nhap ten mon hoc.';
            }
            if (!is_numeric($creditsRaw)) {
                $errors[] = 'So tin chi phai la so.';
            }

            $credits = (int) $creditsRaw;
            if ($credits < 1 || $credits > 10) {
                $errors[] = 'So tin chi can trong khoang 1 den 10.';
            }

            if (!$errors) {
                try {
                    if ($id > 0) {
                        $stmt = $pdo->prepare('
                            UPDATE subjects
                            SET code = :code,
                                name = :name,
                                credits = :credits,
                                department = :department,
                                subject_type = :subject_type,
                                status = :status,
                                notes = :notes
                            WHERE id = :id
                        ');
                        $stmt->execute([
                            ':code' => $code,
                            ':name' => $name,
                            ':credits' => $credits,
                            ':department' => $department,
                            ':subject_type' => $subjectType,
                            ':status' => $status,
                            ':notes' => $notes,
                            ':id' => $id,
                        ]);
                        flash('success', 'Da cap nhat mon hoc.');
                    } else {
                        $stmt = $pdo->prepare('
                            INSERT INTO subjects (code, name, credits, department, subject_type, status, notes)
                            VALUES (:code, :name, :credits, :department, :subject_type, :status, :notes)
                        ');
                        $stmt->execute([
                            ':code' => $code,
                            ':name' => $name,
                            ':credits' => $credits,
                            ':department' => $department,
                            ':subject_type' => $subjectType,
                            ':status' => $status,
                            ':notes' => $notes,
                        ]);
                        flash('success', 'Da them mon hoc moi.');
                    }
                    redirect('subjects.php');
                } catch (PDOException $exception) {
                    $errors[] = str_contains($exception->getMessage(), 'UNIQUE')
                        ? 'Ma mon hoc da ton tai.'
                        : 'Khong the luu mon hoc. Vui long kiem tra lai du lieu.';
                }
            }
        }
    }
}

$editing = null;
if ((int) ($_GET['edit'] ?? 0) > 0) {
    $stmt = $pdo->prepare('SELECT * FROM subjects WHERE id = :id');
    $stmt->execute([':id' => (int) $_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

if (is_post() && $errors && ($_POST['action'] ?? '') !== 'delete') {
    $editing = [
        'id' => (int) ($_POST['id'] ?? 0),
        'code' => $_POST['code'] ?? '',
        'name' => $_POST['name'] ?? '',
        'credits' => $_POST['credits'] ?? '',
        'department' => $_POST['department'] ?? '',
        'subject_type' => $_POST['subject_type'] ?? 'Bat buoc',
        'status' => $_POST['status'] ?? 'Dang mo',
        'notes' => $_POST['notes'] ?? '',
    ];
}

$q = input_string('q');
$statusFilter = input_string('status');
$typeFilter = input_string('subject_type');
$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(s.code LIKE :q OR s.name LIKE :q OR s.department LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
if ($statusFilter !== '' && in_array($statusFilter, $statuses, true)) {
    $where[] = 's.status = :status';
    $params[':status'] = $statusFilter;
}
if ($typeFilter !== '' && in_array($typeFilter, $subjectTypes, true)) {
    $where[] = 's.subject_type = :subject_type';
    $params[':subject_type'] = $typeFilter;
}

$sql = '
    SELECT s.*,
           (SELECT COUNT(*) FROM grades g WHERE g.subject_name = s.name) AS grade_count
    FROM subjects s
';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY s.code';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$subjects = $stmt->fetchAll();

function subject_value(?array $record, string $key, string $default = ''): string
{
    return (string) ($record[$key] ?? $default);
}

render_header('Quan ly mon hoc', 'subjects');
?>

<?php foreach ($errors as $error): ?>
    <div class="alert danger"><?= e($error) ?></div>
<?php endforeach; ?>

<section class="management-grid">
    <div class="panel">
        <div class="section-title">
            <h2>Danh muc mon hoc</h2>
            <span class="count-badge"><?= count($subjects) ?> ket qua</span>
        </div>

        <form class="filter-bar" method="get">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Tim ma mon, ten mon, bo mon">
            <select name="subject_type">
                <option value="">Tat ca loai mon</option>
                <?php foreach ($subjectTypes as $subjectType): ?>
                    <option value="<?= e($subjectType) ?>" <?= $typeFilter === $subjectType ? 'selected' : '' ?>><?= e($subjectType) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status">
                <option value="">Tat ca trang thai</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="secondary-button" type="submit">Loc</button>
            <a class="ghost-button" href="<?= e(app_url('subjects.php')) ?>">Lam moi</a>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Ma mon</th>
                    <th>Ten mon</th>
                    <th>Tin chi</th>
                    <th>Loai mon</th>
                    <th>Trang thai</th>
                    <th>So ban ghi diem</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($subjects as $subject): ?>
                    <tr>
                        <td><?= e($subject['code']) ?></td>
                        <td>
                            <strong><?= e($subject['name']) ?></strong>
                            <small><?= e($subject['department'] ?? '') ?></small>
                        </td>
                        <td><?= e($subject['credits']) ?></td>
                        <td><?= e($subject['subject_type']) ?></td>
                        <td><span class="status-pill"><?= e($subject['status']) ?></span></td>
                        <td><?= e($subject['grade_count']) ?></td>
                        <td class="row-actions">
                            <a class="small-action" href="<?= e(app_url('subjects.php?edit=' . $subject['id'])) ?>">Sua</a>
                            <form method="post" onsubmit="return confirm('Xoa mon hoc nay?');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= e($subject['id']) ?>">
                                <button class="danger-link" type="submit">Xoa</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$subjects): ?>
                    <tr><td colspan="7" class="empty-cell">Khong co mon hoc phu hop.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <aside class="panel form-panel">
        <div class="section-title">
            <h2><?= $editing ? 'Sua mon hoc' : 'Them mon hoc' ?></h2>
            <?php if ($editing): ?>
                <a class="small-action" href="<?= e(app_url('subjects.php')) ?>">Them moi</a>
            <?php endif; ?>
        </div>

        <form method="post" class="form-stack">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= e(subject_value($editing, 'id')) ?>">
            <input type="hidden" name="action" value="save">

            <label>Ma mon hoc
                <input type="text" name="code" value="<?= e(subject_value($editing, 'code')) ?>" required>
            </label>
            <label>Ten mon hoc
                <input type="text" name="name" value="<?= e(subject_value($editing, 'name')) ?>" required>
            </label>
            <div class="form-row">
                <label>So tin chi
                    <input type="number" name="credits" min="1" max="10" value="<?= e(subject_value($editing, 'credits', '3')) ?>" required>
                </label>
                <label>Bo mon/Khoa
                    <input type="text" name="department" value="<?= e(subject_value($editing, 'department')) ?>">
                </label>
            </div>
            <div class="form-row">
                <label>Loai mon
                    <select name="subject_type">
                        <?php foreach ($subjectTypes as $subjectType): ?>
                            <option value="<?= e($subjectType) ?>" <?= subject_value($editing, 'subject_type', 'Bat buoc') === $subjectType ? 'selected' : '' ?>><?= e($subjectType) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Trang thai
                    <select name="status">
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= e($status) ?>" <?= subject_value($editing, 'status', 'Dang mo') === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label>Mo ta/Ghi chu
                <textarea name="notes" rows="4"><?= e(subject_value($editing, 'notes')) ?></textarea>
            </label>
            <button class="primary-button" type="submit"><?= $editing ? 'Cap nhat' : 'Them mon hoc' ?></button>
        </form>
    </aside>
</section>

<?php render_footer(); ?>
