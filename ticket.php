
<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$inscricao_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($inscricao_id <= 0) {
    header('Location: /evento-fametro/index.php');
    exit;
}

// Buscar dados da inscrição e da palestra
$sql = "SELECT i.*, p.titulo, p.palestrante, p.horario_inicio, p.horario_fim 
        FROM inscricoes i 
        JOIN palestras p ON i.palestra_id = p.id 
        WHERE i.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$inscricao_id]);
$dados = $stmt->fetch();

if (!$dados) {
    header('Location: /evento-fametro/index.php');
    exit;
}

require_once __DIR__ . '/includes/header.php';

$codigoQR = $dados['codigo_qrcode'];
$qrUrl = "https://quickchart.io/qr?text=" . urlencode($codigoQR) . "&size=300";
?>

<div class="container-xl py-4">
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
      <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="card-header bg-fametro-red text-white text-center py-3">
          <h3 class="card-title m-0 fw-bold">Comprovante de Inscrição</h3>
        </div>
        <div class="card-body text-center p-4">
          
          <div class="badge bg-fametro-blue text-white mb-2 p-2">
            Jornada Acadêmica Imersão FAMETRO
          </div>

          <h2 class="text-fametro-blue my-2 fw-bold"><?= htmlspecialchars($dados['titulo']) ?></h2>
          <p class="text-muted mb-3">
            <i class="ti ti-user me-1"></i> Palestrante: <strong><?= htmlspecialchars($dados['palestrante']) ?></strong><br>
            <i class="ti ti-clock me-1"></i> Horário: <?= date('H:i', strtotime($dados['horario_inicio'])) ?> às <?= date('H:i', strtotime($dados['horario_fim'])) ?>
          </p>

          <hr>

          <div class="text-start bg-light p-3 rounded-3 mb-4 border">
            <p class="mb-1">
              <strong>Tipo:</strong> 
              <?php if (($dados['tipo_participante'] ?? 'aluno') === 'externo'): ?>
                <span class="badge bg-secondary">Público Externo</span>
              <?php else: ?>
                <span class="badge bg-primary">Aluno FAMETRO</span>
              <?php endif; ?>
            </p>
            <p class="mb-1"><strong>Nome:</strong> <?= htmlspecialchars($dados['nome_aluno']) ?></p>
            <?php if (!empty($dados['matricula'])): ?>
              <p class="mb-1"><strong>Matrícula:</strong> <?= htmlspecialchars($dados['matricula']) ?></p>
            <?php endif; ?>
            <p class="mb-0"><strong>E-mail:</strong> <?= htmlspecialchars($dados['email']) ?></p>
          </div>

          <!-- Gerador de QR Code de Imagem Nativa (Mais nítido e compatível) -->
          <div class="my-3 d-flex justify-content-center">
            <div class="p-3 border rounded bg-white shadow-sm">
              <img src="<?= $qrUrl ?>" alt="QR Code" class="img-fluid" style="max-width: 200px;">
            </div>
          </div>

          <div class="mb-3">
            <span class="badge bg-light text-dark border px-3 py-2 font-monospace fs-6">
              <?= htmlspecialchars($codigoQR) ?>
            </span>
          </div>

          <p class="small text-muted mb-4">
            Apresente este QR Code na entrada da palestra para registrar sua presença.
          </p>

          <div class="d-print-none d-flex justify-content-between align-items-center pt-2 border-top">
            <a href="/evento-fametro/index.php" class="btn btn-secondary rounded-3">
              <i class="ti ti-home me-1"></i> Início
            </a>
            <div class="d-flex gap-2">
              <a href="<?= $qrUrl ?>" download="Ticket_<?= $codigoQR ?>.png" target="_blank" class="btn btn-success rounded-3 fw-bold">
                <i class="ti ti-download me-1"></i> Baixar QR
              </a>
              <button onclick="window.print()" class="btn btn-fametro-blue rounded-3 fw-bold">
                <i class="ti ti-printer me-1"></i> Imprimir Ticket
              </button>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
