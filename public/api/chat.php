<?php

require_once __DIR__ . '/../../app/bootstrap.php';
require_once BASE_PATH . '/app/ai.php';

require_login();

if (!is_post()) {
    json_response(['ok' => false, 'message' => 'Chi ho tro POST.'], 405);
}

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf($csrf)) {
    json_response(['ok' => false, 'message' => 'Phien lam viec khong hop le.'], 419);
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    json_response(['ok' => false, 'message' => 'Du lieu gui len khong hop le.'], 400);
}

$question = trim((string) ($payload['message'] ?? ''));
if ($question === '') {
    json_response(['ok' => false, 'message' => 'Vui long nhap cau hoi.'], 422);
}

if (mb_strlen($question, 'UTF-8') > 500) {
    json_response(['ok' => false, 'message' => 'Cau hoi qua dai, vui long rut gon duoi 500 ky tu.'], 422);
}

$context = build_ai_context(db(), $question);
$contextText = context_to_text($context);
$aiResult = ask_ai($question, $contextText);

if ($aiResult['ok']) {
    json_response([
        'ok' => true,
        'answer' => $aiResult['answer'],
        'used_ai' => true,
    ]);
}

json_response([
    'ok' => true,
    'answer' => local_fallback_answer($context, $aiResult['error']),
    'used_ai' => false,
]);
