<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'erro' => 'Sessão expirada. Atualize a página e tente novamente.']);
    exit;
}

$tiposValidos = ['erro', 'sugestao', 'outro'];
$tipo = in_array($_POST['tipo'] ?? '', $tiposValidos, true) ? $_POST['tipo'] : 'erro';
$mensagem = trim($_POST['mensagem'] ?? '');
$nome = sanitize($_POST['nome'] ?? '');
$email = sanitize($_POST['email'] ?? '');
$paginaUrl = sanitize($_POST['pagina_url'] ?? '');
$userAgent = sanitize($_SERVER['HTTP_USER_AGENT'] ?? '');

if ($mensagem === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'erro' => 'Descreva o problema antes de enviar.']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO relatos_erro (tipo, mensagem, nome, email, pagina_url, user_agent)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $tipo,
        substr($mensagem, 0, 2000),
        $nome !== '' ? $nome : null,
        $email !== '' ? $email : null,
        $paginaUrl !== '' ? substr($paginaUrl, 0, 255) : null,
        $userAgent !== '' ? substr($userAgent, 0, 255) : null,
    ]);

    echo json_encode(['ok' => true]);
} catch (PDOException $e) {
    error_log('Erro ao salvar relato (api/reportar_erro.php): ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Não foi possível enviar seu relato agora. Tente novamente em instantes.']);
}
