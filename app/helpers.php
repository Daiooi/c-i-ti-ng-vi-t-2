<?php

declare(strict_types=1);

function app_config(?string $key = null)
{
    $config = $GLOBALS['app_config'] ?? [];
    return $key === null ? $config : ($config[$key] ?? null);
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function app_url(string $path = ''): string
{
    $base = rtrim((string) app_config('base_url'), '/');
    $path = ltrim($path, '/');
    return $base === '' ? $path : $base . '/' . $path;
}

function redirect(string $path): void
{
    header('Location: ' . app_url($path));
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function consume_flash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!current_user()) {
        redirect('login.php');
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input_string(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $_GET[$key] ?? $default));
}

function nullable_string(string $value): ?string
{
    $value = trim($value);
    return $value === '' ? null : $value;
}

function score_average(float $process, float $midterm, float $final): float
{
    return round(($process * 0.2) + ($midterm * 0.3) + ($final * 0.5), 2);
}

function score_rank(float $average): string
{
    if ($average >= 8.5) {
        return 'Gioi';
    }
    if ($average >= 7.0) {
        return 'Kha';
    }
    if ($average >= 5.5) {
        return 'Trung binh';
    }
    if ($average >= 4.0) {
        return 'Yeu';
    }

    return 'Kem';
}

function validate_score($value, string $label, array &$errors): float
{
    if (!is_numeric($value)) {
        $errors[] = $label . ' phai la so.';
        return 0.0;
    }

    $score = (float) $value;
    if ($score < 0 || $score > 10) {
        $errors[] = $label . ' phai nam trong khoang 0 den 10.';
    }

    return $score;
}
