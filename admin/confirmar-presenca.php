<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

checarAutenticacaoAdmin();

$code = $_GET['code'] ?? '';
$mensagem = '';
$sucesso = false;

if (!empty($code)) {
// Busca a inscrição e a palestra associada pelo código do QR Code
$stmt = $pdo->prepare("
SELECT i.*, p.titulo 
FROM inscricoes i 
JOIN palestras p ON i.palestra_id = p.id 
WHERE i.codigo_qrcode = ?
");
$stmt->execute([$code]);
$inscricao = $stmt->fetch();

if ($inscricao) {
if ($inscricao['presenca_confirmada'] == 1) {
$mensagem = "A presença de <strong>" . htmlspecialchars($inscricao['nome_aluno']) . "</strong> já havia sido confirmada anteriormente!";
$sucesso = true;
} else {
// Atualiza o status de presença para confirmada
$update = $pdo->prepare("UPDATE inscricoes SET presenca_confirmada = 1 WHERE id = ?");
$update->execute([$inscricao['id']]);
$mensagem = "Presença confirmada com sucesso para <strong>" . htmlspecialchars($inscricao['nome_aluno']) . "</strong>!";
$sucesso = true;
}
} else {
$mensagem = "Código QR Code inválido ou não encontrado no sistema.";
}
} else {
$mensagem = "Nenhum código fornecido para validação.";
}

$pageTitle = 'Confirmação de Presença | Jornada Acadêmica Imersão IA FAMETRO';
$pageNoIndex = true;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-xl py-5">
<div class="row justify-content-center">
<div class="col-md-6 text-center">
<div class="card shadow-sm border-0">
<div class="card-body p-4">
<?php if ($sucesso): ?>
<div class="mb-3 text-success">
<i class="ti ti-circle-check fs-1"></i>
</div>
<h2 class="text-success fw-bold mb-3">Validação de Presença</h2>
<?php else: ?>
<div class="mb-3 text-danger">
<i class="ti ti-alert-circle fs-1"></i>
</div>
<h2 class="text-danger fw-bold mb-3">Erro na Validação</h2>
<?php endif; ?>

<p class="fs-3 my-3"><?= $mensagem ?></p>

<a href="/index.php" class="btn btn-fametro-blue mt-3">
<i class="ti ti-home me-1"></i> Voltar à Página Inicial
</a>
</div>
</div>
</div>
</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
