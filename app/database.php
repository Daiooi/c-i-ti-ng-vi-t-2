<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dbPath = (string) app_config('db_path');
    $dbDir = dirname($dbPath);

    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0775, true);
    }

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    return $pdo;
}

function initialize_database(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'lecturer',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS classes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            faculty TEXT,
            school_year TEXT,
            homeroom_teacher TEXT,
            notes TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS students (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_code TEXT NOT NULL UNIQUE,
            full_name TEXT NOT NULL,
            gender TEXT NOT NULL DEFAULT 'Khac',
            birthday TEXT,
            email TEXT,
            phone TEXT,
            address TEXT,
            class_id INTEGER,
            status TEXT NOT NULL DEFAULT 'Dang hoc',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS subjects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            credits INTEGER NOT NULL DEFAULT 3,
            department TEXT,
            subject_type TEXT NOT NULL DEFAULT 'Bat buoc',
            status TEXT NOT NULL DEFAULT 'Dang mo',
            notes TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS grades (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL,
            subject_name TEXT NOT NULL,
            semester TEXT NOT NULL,
            process_score REAL NOT NULL,
            midterm_score REAL NOT NULL,
            final_score REAL NOT NULL,
            average_score REAL NOT NULL,
            rank TEXT NOT NULL,
            note TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            UNIQUE (student_id, subject_name, semester)
        );
    ");

    seed_database($pdo);
}

function seed_database(PDO $pdo): void
{
    $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($userCount === 0) {
        $stmt = $pdo->prepare('
            INSERT INTO users (name, email, username, password_hash, role)
            VALUES (:name, :email, :username, :password_hash, :role)
        ');
        $stmt->execute([
            ':name' => 'Giang vien quan tri',
            ':email' => 'giangvien@example.com',
            ':username' => 'admin',
            ':password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
            ':role' => 'lecturer',
        ]);
    }

    $classCount = (int) $pdo->query('SELECT COUNT(*) FROM classes')->fetchColumn();
    if ($classCount === 0) {
        $classes = [
            ['CTK46', 'Cong nghe thong tin K46', 'Cong nghe thong tin', '2022-2026', 'Tran Minh Chau', 'Lop mau cho khoi ky thuat'],
            ['QTKD01', 'Quan tri kinh doanh 01', 'Kinh te', '2023-2027', 'Le Hoang Nam', 'Lop mau cho khoi kinh te'],
            ['MKT02', 'Marketing so 02', 'Kinh te', '2024-2028', 'Pham Thu Ha', 'Lop mau ve truyen thong so'],
        ];

        $stmt = $pdo->prepare('
            INSERT INTO classes (code, name, faculty, school_year, homeroom_teacher, notes)
            VALUES (?, ?, ?, ?, ?, ?)
        ');

        foreach ($classes as $class) {
            $stmt->execute($class);
        }
    }

    $studentCount = (int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
    if ($studentCount === 0) {
        $classIds = [];
        foreach ($pdo->query('SELECT id, code FROM classes') as $class) {
            $classIds[$class['code']] = (int) $class['id'];
        }

        $students = [
            ['SV001', 'Nguyen Minh Anh', 'Nu', '2004-03-12', 'minhanh@example.com', '0901000001', 'Ha Noi', $classIds['CTK46'] ?? null, 'Dang hoc'],
            ['SV002', 'Tran Quoc Bao', 'Nam', '2004-08-21', 'quocbao@example.com', '0901000002', 'Da Nang', $classIds['CTK46'] ?? null, 'Dang hoc'],
            ['SV003', 'Le Thu Trang', 'Nu', '2005-01-05', 'thutrang@example.com', '0901000003', 'TP Ho Chi Minh', $classIds['QTKD01'] ?? null, 'Dang hoc'],
            ['SV004', 'Pham Gia Huy', 'Nam', '2005-11-17', 'giahuy@example.com', '0901000004', 'Can Tho', $classIds['QTKD01'] ?? null, 'Bao luu'],
            ['SV005', 'Hoang Ngoc Linh', 'Nu', '2006-05-23', 'ngoclinh@example.com', '0901000005', 'Hue', $classIds['MKT02'] ?? null, 'Dang hoc'],
        ];

        $stmt = $pdo->prepare('
            INSERT INTO students (student_code, full_name, gender, birthday, email, phone, address, class_id, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        foreach ($students as $student) {
            $stmt->execute($student);
        }
    }

    $subjectCount = (int) $pdo->query('SELECT COUNT(*) FROM subjects')->fetchColumn();
    if ($subjectCount === 0) {
        $subjects = [
            ['WEB101', 'Lap trinh Web', 3, 'Cong nghe thong tin', 'Bat buoc', 'Dang mo', 'Mon co so ve HTML/CSS/JS va backend can ban'],
            ['DB201', 'Co so du lieu', 3, 'Cong nghe thong tin', 'Bat buoc', 'Dang mo', 'Thuc hanh SQL va thiet ke CSDL'],
            ['MKT110', 'Marketing can ban', 2, 'Kinh te', 'Bat buoc', 'Dang mo', 'Nen tang cho khoi kinh te va truyen thong'],
            ['MKT220', 'Truyen thong so', 3, 'Kinh te', 'Chuyen nganh', 'Dang mo', 'Mon nang cao ve nen tang va noi dung so'],
            ['MGT120', 'Quan tri hoc', 2, 'Kinh te', 'Bat buoc', 'Dang mo', 'Kien thuc quan tri tong hop'],
        ];

        $stmt = $pdo->prepare('
            INSERT INTO subjects (code, name, credits, department, subject_type, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');

        foreach ($subjects as $subject) {
            $stmt->execute($subject);
        }
    }

    $gradeCount = (int) $pdo->query('SELECT COUNT(*) FROM grades')->fetchColumn();
    if ($gradeCount === 0) {
        $studentIds = [];
        foreach ($pdo->query('SELECT id, student_code FROM students') as $student) {
            $studentIds[$student['student_code']] = (int) $student['id'];
        }

        $grades = [
            ['SV001', 'Lap trinh Web', 'HK1 2025-2026', 8.5, 8.0, 9.0, 'Tich cuc'],
            ['SV001', 'Co so du lieu', 'HK1 2025-2026', 7.5, 8.0, 8.5, 'On dinh'],
            ['SV002', 'Lap trinh Web', 'HK1 2025-2026', 6.5, 7.0, 7.5, 'Can luyen them bai tap'],
            ['SV003', 'Marketing can ban', 'HK1 2025-2026', 8.0, 8.5, 8.0, 'Tham gia tot'],
            ['SV004', 'Quan tri hoc', 'HK1 2025-2026', 5.0, 5.5, 6.0, 'Can theo doi them'],
            ['SV005', 'Truyen thong so', 'HK1 2025-2026', 9.0, 8.5, 9.5, 'Noi bat'],
        ];

        $stmt = $pdo->prepare('
            INSERT INTO grades (student_id, subject_name, semester, process_score, midterm_score, final_score, average_score, rank, note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        foreach ($grades as $grade) {
            $average = score_average((float) $grade[3], (float) $grade[4], (float) $grade[5]);
            $stmt->execute([
                $studentIds[$grade[0]] ?? 0,
                $grade[1],
                $grade[2],
                $grade[3],
                $grade[4],
                $grade[5],
                $average,
                score_rank($average),
                $grade[6],
            ]);
        }
    }
}
