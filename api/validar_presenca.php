
<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método de requisição inválido.']);
    exit;
}

$codigoBruto = trim($_POST['codigo_qrcode'] ?? $_POST['codigo'] ?? '');

if (empty($codigoBruto)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhum código foi capturado pela câmera ou digitado.']);
    exit;
}

// Limpeza e tratamento do código lido
$codigoLimpo = $codigoBruto;

// Se o QR Code contiver uma URL completa, extrai os parâmetros query
if (filter_var($codigoBruto, FILTER_VALIDATE_URL) || strpos($codigoBruto, 'http') !== false) {
    $parsedUrl = parse_url($codigoBruto);
    if (isset($parsedUrl['query'])) {
        parse_str($parsedUrl['query'], $queryParams);
        if (!empty($queryParams['codigo'])) {
            $codigoLimpo = trim($queryParams['codigo']);
        } elseif (!empty($queryParams['codigo_qrcode'])) {
            $codigoLimpo = trim($queryParams['codigo_qrcode']);
        } elseif (!empty($queryParams['id'])) {
            $codigoLimpo = trim($queryParams['id']);
        }
    }
}

// Remover espaços em branco
$codigoLimpo = trim($codigoLimpo);

try {
    // Buscar no banco de dados flexibilizando comparações
    $sql = "SELECT i.*, p.titulo AS palestra_titulo 
            FROM inscricoes i 
            LEFT JOIN palestras p ON i.palestra_id = p.id 
            WHERE TRIM(i.codigo_qrcode) = ? 
               OR i.id = ? 
               OR LOWER(TRIM(i.codigo_qrcode)) = LOWER(?)";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$codigoLimpo, $codigoLimpo, $codigoLimpo]);
    $inscricao = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inscricao) {
        echo json_encode([
            'sucesso' => false, 
            'mensagem' => "Inscrição não encontrada no sistema para o código: [{$codigoLimpo}]"
        ]);
        exit;
    }

    // Checar se a presença já havia sido confirmada anteriormente
    $jaConfirmado = ((int)($inscricao['presenca_confirmada'] ?? 0) === 1 || (int)($inscricao['presente'] ?? 0) === 1);

    if ($jaConfirmado) {
        echo json_encode([
            'sucesso' => false,
            'mensagem' => "Presença JÁ FOI CONFIRMADA anteriormente para esta inscrição!",
            'aluno' => $inscricao['nome_aluno'],
            'matricula' => $inscricao['matricula'] ?? 'Não informada',
            'palestra' => $inscricao['palestra_titulo'] ?? 'Evento / Palestra FAMETRO'
        ]);
        exit;
    }

    // Confirmar e registrar presença com data/hora
    $updateSql = "UPDATE inscricoes SET presenca_confirmada = 1, presente = 1, data_presenca = NOW() WHERE id = ?";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute([$inscricao['id']]);

    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Presença confirmada com sucesso!',
        'aluno' => $inscricao['nome_aluno'],
        'matricula' => $inscricao['matricula'] ?? 'Não informada',
        'palestra' => $inscricao['palestra_titulo'] ?? 'Evento / Palestra FAMETRO'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'sucesso' => false, 
        'mensagem' => 'Erro ao validar no banco de dados: ' . $e->getMessage()
    ]);
}
