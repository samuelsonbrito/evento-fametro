<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function verificarConflitoHorario($pdo, $matricula, $palestra_id) {
    $stmt = $pdo->prepare("SELECT horario_inicio, horario_fim FROM palestras WHERE id = ?");
    $stmt->execute([$palestra_id]);
    $novaPalestra = $stmt->fetch();

    if (!$novaPalestra) {
        return true;
    }

    $novoInicio = $novaPalestra['horario_inicio'];
    $novoFim = $novaPalestra['horario_fim'];

    $sql = "SELECT p.horario_inicio, p.horario_fim 
            FROM inscricoes i 
            JOIN palestras p ON i.palestra_id = p.id 
            WHERE i.matricula = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$matricula]);
    $inscricoesExistentes = $stmt->fetchAll();

    foreach ($inscricoesExistentes as $inscricao) {
        $existenteInicio = $inscricao['horario_inicio'];
        $existenteFim = $inscricao['horario_fim'];

        if ($novoInicio < $existenteFim && $novoFim > $existenteInicio) {
            return true;
        }
    }

    return false;
}

function gerarCodigoQRCode($matricula, $palestra_id) {
    return strtoupper(md5($matricula . '_' . $palestra_id . '_' . uniqid(rand(), true)));
}

function checarAutenticacaoAdmin() {
    if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
        header('Location: /evento-fametro/admin/login.php');
        exit;
    }
}