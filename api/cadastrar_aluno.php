<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php');
    exit;
}

$palestra_id = isset($_POST['palestra_id']) ? (int)$_POST['palestra_id'] : 0;
$nome_aluno  = sanitize($_POST['nome_aluno'] ?? '');
$matricula   = sanitize($_POST['matricula'] ?? '');
$email       = sanitize($_POST['email'] ?? '');

if (empty($palestra_id) || empty($nome_aluno) || empty($matricula) || empty($email)) {
    $_SESSION['erro'] = "Todos os campos do formulário são de preenchimento obrigatório.";
    header("Location: /cadastro.php?palestra_id={$palestra_id}");
    exit;
}

try {
    // 1. Verificar se o aluno já está inscrito na MESMA palestra
    $stmt = $pdo->prepare("SELECT id FROM inscricoes WHERE matricula = ? AND palestra_id = ?");
    $stmt->execute([$matricula, $palestra_id]);
    if ($stmt->fetch()) {
        $_SESSION['erro'] = "Você já está inscrito nesta palestra.";
        header("Location: /cadastro.php?palestra_id={$palestra_id}");
        exit;
    }

    // 2. Verificar CONFLITO de HORÁRIO com outras palestras
    if (verificarConflitoHorario($pdo, $matricula, $palestra_id)) {
        $_SESSION['erro'] = "Você já possui uma inscrição em outra palestra no mesmo horário!";
        header("Location: /cadastro.php?palestra_id={$palestra_id}");
        exit;
    }

    // 3. Gerar código único de QR Code para a presença
    $codigo_qrcode = gerarCodigoQRCode($matricula, $palestra_id);

    // 4. Salvar inscrição no MariaDB
    $sql = "INSERT INTO inscricoes (nome_aluno, matricula, email, palestra_id, codigo_qrcode) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nome_aluno, $matricula, $email, $palestra_id, $codigo_qrcode]);

    // Redireciona para o Ticket de Confirmação
    header("Location: /ticket.php?codigo=" . urlencode($codigo_qrcode));
    exit;

} catch (PDOException $e) {
    $_SESSION['erro'] = "Erro ao processar inscrição: " . $e->getMessage();
    header("Location: /cadastro.php?palestra_id={$palestra_id}");
    exit;
}