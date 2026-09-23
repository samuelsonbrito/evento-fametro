<?php

// Detecta HTTPS mesmo atrás de um proxy reverso — o site passa pelo proxy da Umbler
// em produção, então $_SERVER['HTTPS'] sozinho pode não refletir o que o navegador
// do visitante realmente usou.
function conexaoEhHttps() {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    return !empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443;
}

function iniciarSessaoSegura() {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => conexaoEhHttps(),
        ]);
        session_start();
    }
}

iniciarSessaoSegura();

// Headers de segurança — em toda página que passa por aqui, inclusive as APIs em
// api/*.php. CSP em modo report-only de propósito: o app carrega recursos de
// cdn.jsdelivr.net, unpkg.com, quickchart.io e actions.google.com, e usa bastante
// estilo/script inline — uma política enforced mal calibrada quebraria isso sem
// avisar. Depois de um tempo monitorando violações, trocar pra
// "Content-Security-Policy" de verdade.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy-Report-Only: default-src 'self'; "
    . "script-src 'self' 'unsafe-inline' cdn.jsdelivr.net unpkg.com; "
    . "style-src 'self' 'unsafe-inline' cdn.jsdelivr.net; "
    . "font-src 'self' cdn.jsdelivr.net; "
    . "img-src 'self' data: quickchart.io; "
    . "media-src 'self' actions.google.com; "
    . "connect-src 'self';");

// URL canônica de produção — usada nas tags de SEO (canonical, Open Graph, sitemap).
// Fixa de propósito (não deriva de $_SERVER['HTTP_HOST']): evita que acesso por IP,
// domínio antigo ou ambiente de teste vaze pra dentro das tags de SEO.
define('SITE_URL', 'https://eventofametro.com.br');

// Data do evento, usada nos dados estruturados Schema.org/Event em index.php. Não há
// coluna de data do evento no banco — o resto do app já trata "2 de Outubro" como
// texto fixo (ver includes/footer.php, index.php). Atualizar aqui se o evento se
// repetir em outro ano.
define('EVENTO_DATA', '2026-10-02');

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
        header('Location: /admin/login.php');
        exit;
    }
}