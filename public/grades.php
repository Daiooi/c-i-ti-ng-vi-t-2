<?php

require_once __DIR__ . '/../app/bootstrap.php';
require_once BASE_PATH . '/app/layout.php';

require_login();

$pdo = db();
$errors = [];

$students = $pdo->query('
    SELECT s.id, s.student_code, s.full_name, c.code AS class_code
    FROM students s
    LEFT JOIN classes c ON c.id = s.class_id
    ORDER BY s.full_name
')->fetchAll();

$classes = $pdo->query('SELECT id, code, name FROM classes ORDER BY code')->fetchAll();
$subjects = $pdo->query('
    SELECT id, code, name, credits, status
    FROM subjects
    ORDER BY status = "Dang mo" DESC, code
')->fetchAll();
$subjectsById = [];
$subjectIdByName = [];
foreach ($subjects as $subjectItem) {
    $subjectsById[(int) $subjectItem['id']] = $subjectItem;
    if (!isset($subjectIdByName[$subjectItem['name']])) {
        $subjectIdByName[$subjectItem['name']] = (int) $subjectItem['id'];
    }
}

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Phien lam viec khong hop le, vui long thu lai.';
    } else {
        $action = $_POST['action'] ?? 'save';
        $id = (int) ($_POST['id'] ?? 0);

        if ($action === 'delete') {
            $stmt = $pdo->prepare('DELETE FROM grades WHERE id = :id');
            $stmt->execute([':id' => $id]);
            flash('success', 'Da xoa bang diem.');
            redirect('grades.php');
        }

        $studentId = (int) ($_POST['student_id'] ?? 0);
        $subjectId = (int) ($_POST['subject_id'] ?? 0);
        $subjectName = input_string('subject_name');
        $semester = input_string('semester');
        $note = nullable_string((string) ($_POST['note'] ?? ''));
        $processScore = validate_score($_POST['process_score'] ?? '', 'Diem qua trinh', $errors);
        $midtermScore = validate_score($_POST['midterm_score'] ?? '', 'Diem giua ky', $errors);
        $finalScore = validate_score($_POST['final_score'] ?? '', 'Diem cuoi ky', $errors);
        $average = score_average($processScore, $midtermScore, $finalScore);
        $rank = score_rank($average);

        if ($studentId <= 0) {
            $errors[] = 'Vui long chon sinh vien.';
        }
        if ($subjectId > 0) {
            $selectedSubject = $subjectsById[$subjectId] ?? null;
            if (!$selectedSubject) {
                $errors[] = 'Mon hoc duoc chon khong hop le.';
            } else {
                $subjectName = $selectedSubject['name'];
            }
        }
        if ($subjectName === '') {
            $errors[] = 'Vui long chon hoac nhap ten mon hoc.';
        }
        if ($semester === '') {
            $errors[] = 'Vui long nhap hoc ky.';
        }

        if (!$errors) {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare('
                        UPDATE grades
                        SET student_id = :student_id,
                            subject_name = :subject_name,
                            semester = :semester,
                            process_score = :process_score,
                            midterm_score = :midterm_score,
                            final_score = :final_score,
                            average_score = :average_score,
                            rank = :rank,
                            note = :note
                        WHERE id = :id
                    ');
                    $stmt->execute([
                        ':student_id' => $studentId,
                        ':subject_name' => $subjectName,
                        ':semester' => $semester,
                        ':process_score' => $processScore,
                        ':midterm_score' => $midtermScore,
                        ':final_score' => $finalScore,
                        ':average_score' => $average,
                        ':rank' => $rank,
                        ':note' => $note,
                        ':id' => $id,
                    ]);
                    flash('success', 'Da cap nhat bang diem.');
                } else {
                    $stmt = $pdo->prepare('
                        INSERT INTO grades (student_id, subject_name, semester, process_score, midterm_score, final_score, average_score, rank, note)
                        VALUES (:student_id, :subject_name, :semester, :process_score, :midterm_score, :final_score, :average_score, :rank, :note)
                    ');
                    $stmt->execute([
                        ':student_id' => $studentId,
                        ':subject_name' => $subjectName,
                        ':semester' => $semester,
                        ':process_score' => $processScore,
                        ':midterm_score' => $midtermScore,
                        ':final_score' => $finalScore,
                        ':average_score' => $average,
                        ':rank' => $rank,
                        ':note' => $note,
                    ]);
                    flash('success', 'Da them bang diem.');
                }
                redirect('grades.php');
            } catch (PDOException $exception) {
                $errors[] = str_contains($exception->getMessage(), 'UNIQUE')
                    ? 'Sinh vien da co diem mon nay trong hoc ky nay.'
                    : 'Khong the luu bang diem. Vui long kiem tra lai du lieu.';
            }
        }
    }
}

$editing = null;
if ((int) ($_GET['edit'] ?? 0) > 0) {
    $stmt = $pdo->prepare('SELECT * FROM grades WHERE id = :id');
    $stmt->execute([':id' => (int) $_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

if (is_post() && $errors && ($_POST['action'] ?? '') !== 'delete') {
        $editing = [
            'id' => (int) ($_POST['id'] ?? 0),
            'student_id' => $_POST['student_id'] ?? '',
            'subject_id' => $_POST['subject_id'] ?? '',
            'subject_name' => $_POST['subject_name'] ?? '',
            'semester' => $_POST['semester'] ?? '',
            'process_score' => $_POST['process_score'] ?? '',
        'midterm_score' => $_POST['midterm_score'] ?? '',
        'final_score' => $_POST['final_score'] ?? '',
        'note' => $_POST['note'] ?? '',
    ];
}

$q = input_string('q');
$classFilter = (int) ($_GET['class_id'] ?? 0);
$semesterFilter = input_string('semester');
$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(s.student_code LIKE :q OR s.full_name LIKE :q OR g.subject_name LIKE :q OR sb.code LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
if ($classFilter > 0) {
    $where[] = 's.class_id = :class_id';
    $params[':class_id'] = $classFilter;
}
if ($semesterFilter !== '') {
    $where[] = 'g.semester LIKE :semester';
    $params[':semester'] = '%' . $semesterFilter . '%';
}

$sql = '
    SELECT g.*, s.student_code, s.full_name, c.code AS class_code, sb.code AS subject_code
    FROM grades g
    JOIN students s ON s.id = g.student_id
    LEFT JOIN classes c ON c.id = s.class_id
    LEFT JOIN subjects sb ON sb.name = g.subject_name
';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY g.semester DESC, s.full_name, g.subject_name';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$grades = $stmt->fetchAll();

if ($editing && !isset($editing['subject_id']) && ($editing['subject_name'] ?? '') !== '') {
    $editing['subject_id'] = $subjectIdByName[$editing['subject_name']] ?? '';
}

function grade_value(?array $record, string $key, string $default = ''): string
{
    return (string) ($record[$key] ?? $default);
}

render_header('Quan ly diem so', 'grades');
?>

<?php foreach ($errors as $error): ?>
    <div class="alert danger"><?= e($error) ?></div>
<?php endforeach; ?>

<section class="management-grid">
    <div class="panel">
        <div class="section-title">
            <h2>Bang diem</h2>
            <span class="count-badge"><?= count($grades) ?> ket qua</span>
        </div>

        <form class="filter-bar" method="get">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Tim sinh vien hoac mon hoc">
            <select name="class_id">
                <option value="0">Tat ca lop</option>
                <?php foreach ($classes as $class): ?>
                    <option value="<?= e($class['id']) ?>" <?= $classFilter === (int) $class['id'] ? 'selected' : '' ?>>
                        <?= e($class['code']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="semester" value="<?= e($semesterFilter) ?>" placeholder="Hoc ky">
            <button class="secondary-button" type="submit">Loc</button>
            <a class="ghost-button" href="<?= e(app_url('grades.php')) ?>">Lam moi</a>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Sinh vien</th>
                    <th>Mon hoc</th>
                    <th>Hoc ky</th>
                    <th>QT</th>
                    <th>GK</th>
                    <th>CK</th>
                    <th>TB</th>
                    <th>Xep loai</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($grades as $grade): ?>
                    <tr>
                        <td>
                            <strong><?= e($grade['full_name']) ?></strong>
                            <small><?= e($grade['student_code']) ?> - <?= e($grade['class_code'] ?? 'Chua co lop') ?></small>
                        </td>
                        <td>
                            <strong><?= e($grade['subject_name']) ?></strong>
                            <small><?= e($grade['subject_code'] ?? '') ?></small>
                        </td>
                        <td><?= e($grade['semester']) ?></td>
                        <td><?= e(number_format((float) $grade['process_score'], 1)) ?></td>
                        <td><?= e(number_format((float) $grade['midterm_score'], 1)) ?></td>
                        <td><?= e(number_format((float) $grade['final_score'], 1)) ?></td>
                        <td><strong><?= e(number_format((float) $grade['average_score'], 2)) ?></strong></td>
                        <td><span class="status-pill"><?= e($grade['rank']) ?></span></td>
                        <td class="row-actions">
                            <a class="small-action" href="<?= e(app_url('grades.php?edit=' . $grade['id'])) ?>">Sua</a>
                            <form method="post" onsubmit="return confirm('Xoa bang diem nay?');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= e($grade['id']) ?>">
                                <button class="danger-link" type="submit">Xoa</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$grades): ?>
                    <tr><td colspan="9" class="empty-cell">Khong co bang diem phu hop.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <aside class="panel form-panel">
        <div class="section-title">
            <h2><?= $editing ? 'Sua diem' : 'Nhap diem' ?></h2>
            <?php if ($editing): ?>
                <a class="small-action" href="<?= e(app_url('grades.php')) ?>">Nhap moi</a>
            <?php endif; ?>
        </div>

        <form method="post" class="form-stack">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= e(grade_value($editing, 'id')) ?>">
            <input type="hidden" name="action" value="save">

            <label>Sinh vien
                <select name="student_id" required>
                    <option value="">Chon sinh vien</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?= e($student['id']) ?>" <?= (int) grade_value($editing, 'student_id') === (int) $student['id'] ? 'selected' : '' ?>>
                            <?= e($student['full_name'] . ' - ' . $student['student_code'] . ' - ' . ($student['class_code'] ?? 'Chua co lop')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Mon hoc (chon nhanh)
                <select name="subject_id">
                    <option value="0">Chon tu danh muc mon hoc</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?= e($subject['id']) ?>" <?= (int) grade_value($editing, 'subject_id') === (int) $subject['id'] ? 'selected' : '' ?>>
                            <?= e($subject['code'] . ' - ' . $subject['name'] . ' (' . $subject['credits'] . ' tc, ' . $subject['status'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="muted">Neu mon hoc chua co trong danh muc, ban co the nhap tay o o ben duoi.</small>
            </label>
            <label>Ten mon hoc (nhap tay)
                <input type="text" name="subject_name" value="<?= e(grade_value($editing, 'subject_name')) ?>" placeholder="Nhap khi khong chon tu danh muc">
            </label>
            <label>Hoc ky
                <input type="text" name="semester" value="<?= e(grade_value($editing, 'semester', 'HK1 2025-2026')) ?>" required>
            </label>
            <div class="form-row three">
                <label>Qua trinh
                    <input type="number" name="process_score" min="0" max="10" step="0.1" value="<?= e(grade_value($editing, 'process_score')) ?>" required>
                </label>
                <label>Giua ky
                    <input type="number" name="midterm_score" min="0" max="10" step="0.1" value="<?= e(grade_value($editing, 'midterm_score')) ?>" required>
                </label>
                <label>Cuoi ky
                    <input type="number" name="final_score" min="0" max="10" step="0.1" value="<?= e(grade_value($editing, 'final_score')) ?>" required>
                </label>
            </div>
            <label>Ghi chu
                <textarea name="note" rows="3"><?= e(grade_value($editing, 'note')) ?></textarea>
            </label>
            <button class="primary-button" type="submit"><?= $editing ? 'Cap nhat' : 'Luu diem' ?></button>
        </form>
    </aside>
</section>

<?php render_footer(); ?>
