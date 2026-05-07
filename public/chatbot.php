<?php

require_once __DIR__ . '/../app/bootstrap.php';
require_once BASE_PATH . '/app/layout.php';

require_login();

$provider = strtolower(trim((string) app_config('ai_provider')));
if ($provider === '') {
    $provider = 'ollama';
}

$providerLabel = 'Ollama';
$model = (string) app_config('ollama_model');
$hasAccess = true;
$statusTitle = 'Ollama local mode dang bat.';
$statusDescription = 'Khong can API key. Can cai va mo Ollama service tren may.';
$extraLine = 'Endpoint: ' . ((string) app_config('ollama_base_url') ?: 'http://127.0.0.1:11434');

if ($provider === 'gemini') {
    $providerLabel = 'Gemini';
    $model = (string) app_config('gemini_model');
    $hasAccess = (bool) app_config('gemini_api_key');
    $statusTitle = $hasAccess ? 'Gemini API key da duoc cau hinh.' : 'Chua co GEMINI_API_KEY.';
    $statusDescription = $hasAccess
        ? 'Chatbot dang goi Gemini API.'
        : 'Chatbot se fallback du lieu noi bo cho den khi ban them API key.';
    $extraLine = '';
} elseif ($provider === 'openai') {
    $providerLabel = 'OpenAI';
    $model = (string) app_config('openai_model');
    $hasAccess = (bool) app_config('openai_api_key');
    $statusTitle = $hasAccess ? 'OpenAI API key da duoc cau hinh.' : 'Chua co OPENAI_API_KEY.';
    $statusDescription = $hasAccess
        ? 'Chatbot dang goi OpenAI API.'
        : 'Chatbot se fallback du lieu noi bo cho den khi ban them API key.';
    $extraLine = '';
}

render_header('Tro ly ao AI', 'chatbot');
?>

<section class="chat-layout">
    <div class="panel chat-panel" data-chat-endpoint="<?= e(app_url('api/chat.php')) ?>" data-csrf-token="<?= e(csrf_token()) ?>">
        <div class="chat-messages" id="chat-messages" aria-live="polite">
            <div class="chat-bubble assistant">
                Xin chao, toi co the tra cuu sinh vien, lop hoc va diem so trong he thong.
            </div>
        </div>

        <div class="quick-prompts" aria-label="Cau hoi nhanh">
            <button type="button" data-prompt="Cho toi danh sach sinh vien lop CTK46">Sinh vien lop CTK46</button>
            <button type="button" data-prompt="Diem mon Lap trinh Web cua SV001">Diem SV001</button>
            <button type="button" data-prompt="Lop nao co bao nhieu sinh vien?">Si so cac lop</button>
        </div>

        <form class="chat-form" id="chat-form">
            <textarea id="chat-input" rows="2" placeholder="Nhap cau hoi..." required></textarea>
            <button class="primary-button" type="submit">Gui</button>
        </form>
    </div>

    <aside class="panel">
        <div class="section-title">
            <h2>Trang thai AI</h2>
        </div>
        <?php if ($hasAccess): ?>
            <p class="status-line good"><?= e($statusTitle) ?></p>
        <?php else: ?>
            <p class="status-line warn"><?= e($statusTitle) ?></p>
        <?php endif; ?>
        <p class="muted"><?= e($statusDescription) ?></p>
        <p class="muted">Model dang dung: <strong><?= e($model) ?></strong></p>
        <?php if ($extraLine !== ''): ?>
            <p class="muted"><?= e($extraLine) ?></p>
        <?php endif; ?>
        <p class="muted">Provider hien tai: <strong><?= e($providerLabel) ?></strong></p>
    </aside>
</section>

<?php render_footer(); ?>
