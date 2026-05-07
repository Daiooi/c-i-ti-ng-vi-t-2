<?php

declare(strict_types=1);

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function question_tokens(string $question): array
{
    $normalized = mb_strtolower($question, 'UTF-8');
    $parts = preg_split('/[^\p{L}\p{N}_]+/u', $normalized) ?: [];
    $stopwords = [
        'cho', 'toi', 'cua', 'la', 'co', 'va', 've', 'hay', 'giup', 'xem',
        'thong', 'tin', 'sinh', 'vien', 'lop', 'diem', 'hoc', 'mon', 'ky',
    ];
    $tokens = [];

    foreach ($parts as $part) {
        $part = trim($part);
        if (mb_strlen($part, 'UTF-8') < 2 || in_array($part, $stopwords, true)) {
            continue;
        }
        $tokens[] = $part;
    }

    return array_slice(array_values(array_unique($tokens)), 0, 8);
}

function like_condition(array $fields, array $tokens, string $prefix, array &$params): string
{
    $groups = [];
    foreach ($tokens as $index => $token) {
        $key = ':' . $prefix . $index;
        $parts = [];
        foreach ($fields as $field) {
            $parts[] = $field . ' LIKE ' . $key;
        }
        $groups[] = '(' . implode(' OR ', $parts) . ')';
        $params[$key] = '%' . $token . '%';
    }

    return implode(' OR ', $groups);
}

function build_ai_context(PDO $pdo, string $question): array
{
    $tokens = question_tokens($question);
    $overview = [
        'students' => (int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn(),
        'classes' => (int) $pdo->query('SELECT COUNT(*) FROM classes')->fetchColumn(),
        'subjects' => (int) $pdo->query('SELECT COUNT(*) FROM subjects')->fetchColumn(),
        'grades' => (int) $pdo->query('SELECT COUNT(*) FROM grades')->fetchColumn(),
    ];

    $studentParams = [];
    $studentWhere = $tokens
        ? 'WHERE ' . like_condition(['s.student_code', 's.full_name', 's.email', 's.phone', 'c.code', 'c.name'], $tokens, 'st', $studentParams)
        : '';
    $stmt = $pdo->prepare("
        SELECT s.student_code, s.full_name, s.gender, s.birthday, s.email, s.phone, s.status,
               c.code AS class_code, c.name AS class_name
        FROM students s
        LEFT JOIN classes c ON c.id = s.class_id
        $studentWhere
        ORDER BY s.full_name
        LIMIT 8
    ");
    $stmt->execute($studentParams);
    $students = $stmt->fetchAll();

    $classParams = [];
    $classWhere = $tokens
        ? 'WHERE ' . like_condition(['c.code', 'c.name', 'c.faculty', 'c.school_year', 'c.homeroom_teacher'], $tokens, 'cl', $classParams)
        : '';
    $stmt = $pdo->prepare("
        SELECT c.code, c.name, c.faculty, c.school_year, c.homeroom_teacher, COUNT(s.id) AS student_count
        FROM classes c
        LEFT JOIN students s ON s.class_id = c.id
        $classWhere
        GROUP BY c.id
        ORDER BY c.code
        LIMIT 8
    ");
    $stmt->execute($classParams);
    $classes = $stmt->fetchAll();

    $subjectParams = [];
    $subjectWhere = $tokens
        ? 'WHERE ' . like_condition(['sb.code', 'sb.name', 'sb.department', 'sb.subject_type', 'sb.status'], $tokens, 'sb', $subjectParams)
        : '';
    $stmt = $pdo->prepare("
        SELECT sb.code, sb.name, sb.credits, sb.department, sb.subject_type, sb.status,
               (SELECT COUNT(*) FROM grades g WHERE g.subject_name = sb.name) AS grade_count
        FROM subjects sb
        $subjectWhere
        ORDER BY sb.code
        LIMIT 10
    ");
    $stmt->execute($subjectParams);
    $subjects = $stmt->fetchAll();

    $gradeParams = [];
    $gradeWhere = $tokens
        ? 'WHERE ' . like_condition(['s.student_code', 's.full_name', 'c.code', 'c.name', 'g.subject_name', 'g.semester'], $tokens, 'gr', $gradeParams)
        : '';
    $stmt = $pdo->prepare("
        SELECT s.student_code, s.full_name, c.code AS class_code,
               g.subject_name, g.semester, g.process_score, g.midterm_score, g.final_score,
               g.average_score, g.rank, g.note
        FROM grades g
        JOIN students s ON s.id = g.student_id
        LEFT JOIN classes c ON c.id = s.class_id
        $gradeWhere
        ORDER BY g.semester DESC, s.full_name, g.subject_name
        LIMIT 12
    ");
    $stmt->execute($gradeParams);
    $grades = $stmt->fetchAll();

    return [
        'overview' => $overview,
        'tokens' => $tokens,
        'students' => $students,
        'classes' => $classes,
        'subjects' => $subjects,
        'grades' => $grades,
    ];
}

function context_to_text(array $context): string
{
    $lines = [];
    $overview = $context['overview'];
    $lines[] = 'Tong quan: ' . $overview['students'] . ' sinh vien, ' . $overview['classes'] . ' lop, ' . $overview['subjects'] . ' mon hoc, ' . $overview['grades'] . ' bang diem.';

    $lines[] = '';
    $lines[] = 'Sinh vien lien quan:';
    if ($context['students']) {
        foreach ($context['students'] as $student) {
            $lines[] = '- ' . $student['student_code'] . ' | ' . $student['full_name']
                . ' | Lop: ' . ($student['class_code'] ?: 'Chua co lop')
                . ' | Trang thai: ' . $student['status']
                . ' | Email: ' . ($student['email'] ?: 'Khong co')
                . ' | SDT: ' . ($student['phone'] ?: 'Khong co');
        }
    } else {
        $lines[] = '- Khong tim thay sinh vien phu hop voi cau hoi.';
    }

    $lines[] = '';
    $lines[] = 'Lop hoc lien quan:';
    if ($context['classes']) {
        foreach ($context['classes'] as $class) {
            $lines[] = '- ' . $class['code'] . ' | ' . $class['name']
                . ' | Khoa: ' . ($class['faculty'] ?: 'Khong co')
                . ' | Nam hoc: ' . ($class['school_year'] ?: 'Khong co')
                . ' | Co van: ' . ($class['homeroom_teacher'] ?: 'Khong co')
                . ' | Si so: ' . $class['student_count'];
        }
    } else {
        $lines[] = '- Khong tim thay lop hoc phu hop voi cau hoi.';
    }

    $lines[] = '';
    $lines[] = 'Mon hoc lien quan:';
    if ($context['subjects']) {
        foreach ($context['subjects'] as $subject) {
            $lines[] = '- ' . $subject['code'] . ' | ' . $subject['name']
                . ' | Tin chi: ' . $subject['credits']
                . ' | Loai: ' . $subject['subject_type']
                . ' | Trang thai: ' . $subject['status']
                . ' | So ban ghi diem: ' . $subject['grade_count']
                . ' | Bo mon: ' . ($subject['department'] ?: 'Khong co');
        }
    } else {
        $lines[] = '- Khong tim thay mon hoc phu hop voi cau hoi.';
    }

    $lines[] = '';
    $lines[] = 'Diem so lien quan:';
    if ($context['grades']) {
        foreach ($context['grades'] as $grade) {
            $lines[] = '- ' . $grade['student_code'] . ' | ' . $grade['full_name']
                . ' | Lop: ' . ($grade['class_code'] ?: 'Chua co lop')
                . ' | Mon: ' . $grade['subject_name']
                . ' | Hoc ky: ' . $grade['semester']
                . ' | QT: ' . $grade['process_score']
                . ' | GK: ' . $grade['midterm_score']
                . ' | CK: ' . $grade['final_score']
                . ' | TB: ' . $grade['average_score']
                . ' | Xep loai: ' . $grade['rank']
                . ' | Ghi chu: ' . ($grade['note'] ?: 'Khong co');
        }
    } else {
        $lines[] = '- Khong tim thay diem so phu hop voi cau hoi.';
    }

    return implode("\n", $lines);
}

function local_fallback_answer(array $context, string $reason): string
{
    $parts = [];
    $parts[] = $reason;

    if ($context['students']) {
        $parts[] = 'Sinh vien tim thay:';
        foreach ($context['students'] as $student) {
            $parts[] = '- ' . $student['student_code'] . ' - ' . $student['full_name']
                . ' - Lop ' . ($student['class_code'] ?: 'chua co lop')
                . ' - Trang thai ' . $student['status'] . '.';
        }
    }

    if ($context['grades']) {
        $parts[] = 'Diem lien quan:';
        foreach ($context['grades'] as $grade) {
            $parts[] = '- ' . $grade['full_name'] . ' | ' . $grade['subject_name']
                . ' | ' . $grade['semester'] . ' | TB ' . number_format((float) $grade['average_score'], 2)
                . ' | ' . $grade['rank'] . '.';
        }
    }

    if ($context['subjects']) {
        $parts[] = 'Mon hoc lien quan:';
        foreach ($context['subjects'] as $subject) {
            $parts[] = '- ' . $subject['code'] . ' - ' . $subject['name']
                . ' - ' . $subject['credits'] . ' tin chi'
                . ' - ' . $subject['status'] . '.';
        }
    }

    if (!$context['students'] && !$context['grades'] && !$context['classes'] && !$context['subjects']) {
        $parts[] = 'Khong tim thay du lieu phu hop. Hay thu tim bang ma sinh vien, ten sinh vien, ma lop hoac ten mon hoc.';
    }

    return implode("\n", $parts);
}

function extract_openai_text(array $data): string
{
    if (isset($data['output_text']) && is_string($data['output_text'])) {
        return trim($data['output_text']);
    }

    $texts = [];
    foreach (($data['output'] ?? []) as $item) {
        foreach (($item['content'] ?? []) as $content) {
            if (($content['type'] ?? '') === 'output_text' && isset($content['text'])) {
                $texts[] = $content['text'];
            }
        }
    }

    return trim(implode("\n", $texts));
}

function extract_gemini_text(array $data): string
{
    $texts = [];
    foreach (($data['candidates'] ?? []) as $candidate) {
        foreach (($candidate['content']['parts'] ?? []) as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $texts[] = $part['text'];
            }
        }
    }

    return trim(implode("\n", $texts));
}

function ask_openai(string $question, string $contextText): array
{
    $apiKey = (string) app_config('openai_api_key');
    if ($apiKey === '') {
        return ['ok' => false, 'error' => 'Chua cau hinh OPENAI_API_KEY nen dang tra loi bang ket qua tra cuu cuc bo.'];
    }

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'May chu PHP chua bat extension cURL de goi OpenAI API.'];
    }

    $payload = [
        'model' => (string) app_config('openai_model'),
        'instructions' => 'Ban la tro ly ao cua he thong quan ly sinh vien. Chi tra loi dua tren DU LIEU NOI BO duoc cung cap. Neu khong co du lieu phu hop, noi ro la khong tim thay. Khong bia ma sinh vien, diem so, email, so dien thoai. Tra loi bang tieng Viet, ngan gon, uu tien bang hoac gach dau dong khi can.',
        'input' => "Cau hoi cua giang vien:\n" . $question . "\n\nDU LIEU NOI BO:\n" . $contextText,
        'max_output_tokens' => 700,
    ];

    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 30,
    ]);

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'error' => 'Khong goi duoc OpenAI API: ' . $curlError];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'OpenAI API tra ve du lieu khong hop le.'];
    }

    if ($status >= 400) {
        $message = $data['error']['message'] ?? ('Ma loi HTTP ' . $status);
        return ['ok' => false, 'error' => 'OpenAI API bao loi: ' . $message];
    }

    $answer = extract_openai_text($data);
    if ($answer === '') {
        return ['ok' => false, 'error' => 'OpenAI API khong tra ve noi dung van ban.'];
    }

    return ['ok' => true, 'answer' => $answer];
}

function ask_gemini(string $question, string $contextText): array
{
    $apiKey = (string) app_config('gemini_api_key');
    if ($apiKey === '') {
        return ['ok' => false, 'error' => 'Chua cau hinh GEMINI_API_KEY nen dang tra loi bang ket qua tra cuu cuc bo.'];
    }

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'May chu PHP chua bat extension cURL de goi Gemini API.'];
    }

    $model = trim((string) app_config('gemini_model'));
    if ($model === '') {
        $model = 'gemini-2.5-flash';
    }

    $payload = [
        'systemInstruction' => [
            'parts' => [[
                'text' => 'Ban la tro ly ao cua he thong quan ly sinh vien. Chi tra loi dua tren DU LIEU NOI BO duoc cung cap. Neu khong co du lieu phu hop, noi ro la khong tim thay. Khong tu tao ma sinh vien, diem so, email, so dien thoai. Tra loi bang tieng Viet, ngan gon, uu tien bang hoac gach dau dong khi can.',
            ]],
        ],
        'contents' => [[
            'role' => 'user',
            'parts' => [[
                'text' => "Cau hoi cua giang vien:\n" . $question . "\n\nDU LIEU NOI BO:\n" . $contextText,
            ]],
        ]],
        'generationConfig' => [
            'maxOutputTokens' => 700,
        ],
    ];

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 30,
    ]);

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'error' => 'Khong goi duoc Gemini API: ' . $curlError];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Gemini API tra ve du lieu khong hop le.'];
    }

    if ($status >= 400) {
        $message = $data['error']['message'] ?? ('Ma loi HTTP ' . $status);
        return ['ok' => false, 'error' => 'Gemini API bao loi: ' . $message];
    }

    $answer = extract_gemini_text($data);
    if ($answer === '') {
        return ['ok' => false, 'error' => 'Gemini API khong tra ve noi dung van ban.'];
    }

    return ['ok' => true, 'answer' => $answer];
}

function ask_ollama(string $question, string $contextText): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'May chu PHP chua bat extension cURL de goi Ollama API.'];
    }

    $baseUrl = rtrim(trim((string) app_config('ollama_base_url')), '/');
    if ($baseUrl === '') {
        $baseUrl = 'http://127.0.0.1:11434';
    }

    $model = trim((string) app_config('ollama_model'));
    if ($model === '') {
        $model = 'qwen2.5:1.5b';
    }

    $numCtx = (int) app_config('ollama_num_ctx');
    if ($numCtx <= 0) {
        $numCtx = 768;
    }

    $numPredict = (int) app_config('ollama_num_predict');
    if ($numPredict <= 0) {
        $numPredict = 120;
    }

    $keepAlive = trim((string) app_config('ollama_keep_alive'));
    if ($keepAlive === '') {
        $keepAlive = '0';
    }

    $systemPrompt = 'Ban la tro ly ao cua he thong quan ly sinh vien. Chi tra loi dua tren DU LIEU NOI BO duoc cung cap. Neu khong co du lieu phu hop, noi ro la khong tim thay. Khong tu tao ma sinh vien, diem so, email, so dien thoai. Tra loi bang tieng Viet, ngan gon, uu tien bang hoac gach dau dong khi can.';
    $userPrompt = "Cau hoi cua giang vien:\n" . $question . "\n\nDU LIEU NOI BO:\n" . $contextText;

    $payload = [
        'model' => $model,
        'system' => $systemPrompt,
        'prompt' => $userPrompt,
        'stream' => false,
        'keep_alive' => $keepAlive,
        'options' => [
            'temperature' => 0.2,
            'num_ctx' => $numCtx,
            'num_predict' => $numPredict,
        ],
    ];

    $ch = curl_init($baseUrl . '/api/generate');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 60,
    ]);

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        $message = 'Khong goi duoc Ollama API: ' . $curlError;
        if (str_contains(strtolower($curlError), 'failed to connect') || str_contains(strtolower($curlError), 'connection refused')) {
            $message .= '. Hay mo Ollama truoc.';
        }
        return ['ok' => false, 'error' => $message];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Ollama API tra ve du lieu khong hop le.'];
    }

    $errorText = '';
    if (isset($data['error']) && is_string($data['error'])) {
        $errorText = trim($data['error']);
    } elseif (isset($data['error']['message']) && is_string($data['error']['message'])) {
        $errorText = trim($data['error']['message']);
    }

    if ($status >= 400 || $errorText !== '') {
        $message = $errorText !== '' ? $errorText : ('Ma loi HTTP ' . $status);
        if (str_contains(strtolower($message), 'not found')) {
            $message .= '. Hay tai model bang lenh: ollama pull ' . $model;
        }
        return ['ok' => false, 'error' => 'Ollama API bao loi: ' . $message];
    }

    $answer = trim((string) ($data['response'] ?? ''));
    if ($answer === '') {
        return ['ok' => false, 'error' => 'Ollama API khong tra ve noi dung van ban.'];
    }

    return ['ok' => true, 'answer' => $answer];
}

function ask_ai(string $question, string $contextText): array
{
    $provider = strtolower(trim((string) app_config('ai_provider')));
    if ($provider === '') {
        $provider = 'ollama';
    }

    if ($provider === 'openai') {
        return ask_openai($question, $contextText);
    }

    if ($provider === 'gemini') {
        return ask_gemini($question, $contextText);
    }

    return ask_ollama($question, $contextText);
}
