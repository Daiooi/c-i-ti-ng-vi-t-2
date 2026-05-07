<?php

require_once __DIR__ . '/../app/bootstrap.php';
require_once BASE_PATH . '/app/layout.php';

require_login();

$pdo = db();
$errors = [];
$statuses = ['Dang hoc', 'Bao luu', 'Da tot nghiep', 'Da nghi'];
$genders = ['Nam', 'Nu', 'Khac'];

$classes = $pdo->query('SELECT id, code, name FROM classes ORDER BY code')->fetchAll();

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Phien lam viec khong hop le, vui long thu lai.';
    } else {
        $action = $_POST['action'] ?? 'save';

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM students WHERE id = :id');
            $stmt->execute([':id' => $id]);
            flash('success', 'Da xoa sinh vien va cac bang diem lien quan.');
            redirect('students.php');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $studentCode = input_string('student_code');
        $fullName = input_string('full_name');
        $gender = in_array($_POST['gender'] ?? '', $genders, true) ? $_POST['gender'] : 'Khac';
        $birthday = nullable_string((string) ($_POST['birthday'] ?? ''));
        $email = nullable_string((string) ($_POST['email'] ?? ''));
        $phone = nullable_string((string) ($_POST['phone'] ?? ''));
        $address = nullable_string((string) ($_POST['address'] ?? ''));
        $classId = (int) ($_POST['class_id'] ?? 0);
        $status = in_array($_POST['status'] ?? '', $statuses, true) ? $_POST['status'] : 'Dang hoc';

        if ($studentCode === '') {
            $errors[] = 'Vui long nhap ma sinh vien.';
        }
        if ($fullName === '') {
            $errors[] = 'Vui long nhap ho ten sinh vien.';
        }
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email khong dung dinh dang.';
        }

        if (!$errors) {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare('
                        UPDATE students
                        SET student_code = :student_code,
                            full_name = :full_name,
                            gender = :gender,
                            birthday = :birthday,
                            email = :email,
                            phone = :phone,
                            address = :address,
                            class_id = :class_id,
                            status = :status
                        WHERE id = :id
                    ');
                    $stmt->execute([
                        ':student_code' => $studentCode,
                        ':full_name' => $fullName,
                        ':gender' => $gender,
                        ':birthday' => $birthday,
                        ':email' => $email,
                        ':phone' => $phone,
                        ':address' => $address,
                        ':class_id' => $classId ?: null,
                        ':status' => $status,
                        ':id' => $id,
                    ]);
                    flash('success', 'Da cap nhat thong tin sinh vien.');
                } else {
                    $stmt = $pdo->prepare('
                        INSERT INTO students (student_code, full_name, gender, birthday, email, phone, address, class_id, status)
                        VALUES (:student_code, :full_name, :gender, :birthday, :email, :phone, :address, :class_id, :status)
                    ');
                    $stmt->execute([
                        ':student_code' => $studentCode,
                        ':full_name' => $fullName,
                        ':gender' => $gender,
                        ':birthday' => $birthday,
                        ':email' => $email,
                        ':phone' => $phone,
                        ':address' => $address,
                        ':class_id' => $classId ?: null,
                        ':status' => $status,
                    ]);
                    flash('success', 'Da them sinh vien moi.');
                }
                redirect('students.php');
            } catch (PDOException $exception) {
                $errors[] = str_contains($exception->getMessage(), 'UNIQUE')
                    ? 'Ma sinh vien da ton tai.'
                    : 'Khong the luu sinh vien. Vui long kiem tra lai du lieu.';
            }
        }
    }
}

$editing = null;
if ((int) ($_GET['edit'] ?? 0) > 0) {
    $stmt = $pdo->prepare('SELECT * FROM students WHERE id = :id');
    $stmt->execute([':id' => (int) $_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

if (is_post() && $errors) {
    $editing = [
        'id' => (int) ($_POST['id'] ?? 0),
        'student_code' => $_POST['student_code'] ?? '',
        'full_name' => $_POST['full_name'] ?? '',
        'gender' => $_POST['gender'] ?? 'Khac',
        'birthday' => $_POST['birthday'] ?? '',
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'address' => $_POST['address'] ?? '',
        'class_id' => $_POST['class_id'] ?? '',
        'status' => $_POST['status'] ?? 'Dang hoc',
    ];
}

$q = input_string('q');
$classFilter = (int) ($_GET['class_id'] ?? 0);
$statusFilter = input_string('status');
$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(s.student_code LIKE :q OR s.full_name LIKE :q OR s.email LIKE :q OR s.phone LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
if ($classFilter > 0) {
    $where[] = 's.class_id = :class_id';
    $params[':class_id'] = $classFilter;
}
if ($statusFilter !== '' && in_array($statusFilter, $statuses, true)) {
    $where[] = 's.status = :status';
    $params[':status'] = $statusFilter;
}

$sql = '
    SELECT s.*, c.code AS class_code, c.name AS class_name
    FROM students s
    LEFT JOIN classes c ON c.id = s.class_id
';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY s.created_at DESC, s.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

function student_value(?array $record, string $key, string $default = ''): string
{
    return (string) ($record[$key] ?? $default);
}

render_header('Quan ly sinh vien', 'students');
?>

<?php foreach ($errors as $error): ?>
    <div class="alert danger"><?= e($error) ?></div>
<?php endforeach; ?>

<section class="management-grid">
    <div class="panel">
        <div class="section-title">
            <h2>Danh sach sinh vien</h2>
            <span class="count-badge"><?= count($students) ?> ket qua</span>
        </div>

        <form class="filter-bar" method="get">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Tim ma, ten, email, so dien thoai">
            <select name="class_id">
                <option value="0">Tat ca lop</option>
                <?php foreach ($classes as $class): ?>
                    <option value="<?= e($class['id']) ?>" <?= $classFilter === (int) $class['id'] ? 'selected' : '' ?>>
                        <?= e($class['code']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="status">
                <option value="">Tat ca trang thai</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="secondary-button" type="submit">Loc</button>
            <a class="ghost-button" href="<?= e(app_url('students.php')) ?>">Lam moi</a>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Ma SV</th>
                    <th>Ho ten</th>
                    <th>Lop</th>
                    <th>Lien he</th>
                    <th>Trang thai</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?= e($student['student_code']) ?></td>
                        <td>
                            <strong><?= e($student['full_name']) ?></strong>
                            <small><?= e($student['gender']) ?><?= $student['birthday'] ? ' - ' . e($student['birthday']) : '' ?></small>
                        </td>
                        <td>
                            <?= e($student['class_code'] ?? 'Chua co lop') ?>
                            <small><?= e($student['class_name'] ?? '') ?></small>
                        </td>
                        <td>
                            <?= e($student['email'] ?? '') ?>
                            <small><?= e($student['phone'] ?? '') ?></small>
                        </td>
                        <td><span class="status-pill"><?= e($student['status']) ?></span></td>
                        <td class="row-actions">
                            <a class="small-action" href="<?= e(app_url('students.php?edit=' . $student['id'])) ?>">Sua</a>
                            <form method="post" onsubmit="return confirm('Xoa sinh vien nay? Cac bang diem lien quan cung bi xoa.');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= e($student['id']) ?>">
                                <button class="danger-link" type="submit">Xoa</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$students): ?>
                    <tr><td colspan="6" class="empty-cell">Khong co sinh vien phu hop.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <aside class="panel form-panel">
        <div class="section-title">
            <h2><?= $editing ? 'Sua sinh vien' : 'Them sinh vien' ?></h2>
            <?php if ($editing): ?>
                <a class="small-action" href="<?= e(app_url('students.php')) ?>">Them moi</a>
            <?php endif; ?>
        </div>

        <form method="post" class="form-stack">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= e(student_value($editing, 'id')) ?>">
            <input type="hidden" name="action" value="save">

            <label>Ma sinh vien
                <input type="text" name="student_code" value="<?= e(student_value($editing, 'student_code')) ?>" required>
            </label>
            <label>Ho ten
                <input type="text" name="full_name" value="<?= e(student_value($editing, 'full_name')) ?>" required>
            </label>
            <div class="form-row">
                <label>Gioi tinh
                    <select name="gender">
                        <?php foreach ($genders as $gender): ?>
                            <option value="<?= e($gender) ?>" <?= student_value($editing, 'gender', 'Khac') === $gender ? 'selected' : '' ?>><?= e($gender) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Ngay sinh
                    <input type="date" name="birthday" value="<?= e(student_value($editing, 'birthday')) ?>">
                </label>
            </div>
            <label>Lop hoc
                <select name="class_id">
                    <option value="0">Chua gan lop</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?= e($class['id']) ?>" <?= (int) student_value($editing, 'class_id') === (int) $class['id'] ? 'selected' : '' ?>>
                            <?= e($class['code'] . ' - ' . $class['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="form-row">
                <label>Email
                    <input type="email" name="email" value="<?= e(student_value($editing, 'email')) ?>">
                </label>
                <label>So dien thoai
                    <input type="text" name="phone" value="<?= e(student_value($editing, 'phone')) ?>">
                </label>
            </div>
            <label>Dia chi
                <textarea name="address" rows="3"><?= e(student_value($editing, 'address')) ?></textarea>
            </label>
            <label>Trang thai
                <select name="status">
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= e($status) ?>" <?= student_value($editing, 'status', 'Dang hoc') === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="primary-button" type="submit"><?= $editing ? 'Cap nhat' : 'Them sinh vien' ?></button>
        </form>
    </aside>
</section>

<?php render_footer(); ?>
