<?php

$root = dirname(__DIR__, 2);
if (!chdir($root)) {
    http_response_code(500);
    return;
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    return;
}

ob_start();
require_once $root . '/inc/comprovarSessio.php';
ob_end_clean();

if (!isset($configOk) || !$configOk || !isset($_SESSION['usuari'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Session not authorized']);
    return;
}

$filename = trim((string) ($_POST['filename'] ?? ''));

if ($filename === ''
    || basename($filename) !== $filename
    || preg_match('/^[A-Za-z0-9._-]+\.pdf$/D', $filename) !== 1) {
    http_response_code(422);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Invalid temporary filename']);
    return;
}

$tempRoot = realpath(__DIR__);
$candidate = realpath(__DIR__ . DIRECTORY_SEPARATOR . $filename);

if ($tempRoot === false || $candidate === false || !is_file($candidate)) {
    http_response_code(204);
    return;
}

$prefix = rtrim($tempRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (!str_starts_with($candidate, $prefix)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Temporary file outside allowed directory']);
    return;
}

if (!unlink($candidate)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Could not delete temporary file']);
    return;
}

http_response_code(204);
