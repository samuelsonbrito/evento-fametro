<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$mensagem = '';
$tipoMensagem = '';
$inscricoes = null;
$emailBusca = '';
$matriculaBusca = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailBusca = sanitize($_POST['email'] ?? '');
    $matriculaBusca = sanitize($_POST['matricula'] ?? '');

    $identificadorConsulta = 'consulta:' . ipDoCliente();

    if (estaLimitadoPorTentativas($pdo, $identificadorConsulta)) {
        $mensagem = 'Muitas consultas. Tente novamente em alguns minutos.';
        $tipoMensagem = 'warning';
    } elseif (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $mensagem = 'Sessão expirada. Atualize a página e tente novamente.';
        $tipoMensagem = 'warning';
    } elseif ($emailBusca === '') {
        $mensagem = 'Informe seu e-mail.';
        $tipoMensagem = 'warning';
    } else {
        registrarTentativaFalha($pdo, $identificadorConsulta);

        $sql = "SELECT i.*, p.titulo, p.horario_inicio, p.horario_fim
                FROM inscricoes i
                JOIN palestras p ON i.palestra_id = p.id
                WHERE i.email = ?";
        $params = [$emailBusca];

        if ($matriculaBusca !== '') {
            $sql .= " AND i.matricula = ?";
            $params[] = $matriculaBusca;
        }

        $sql .= " ORDER BY p.horario_inicio ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $inscricoes = $stmt->fetchAll();

        if (empty($inscricoes)) {
            $mensagem = 'Nenhuma inscrição encontrada com esses dados.';
            $tipoMensagem = 'warning';
        }
    }
}

$pageTitle = 'Consultar Inscrição | Jornada Acadêmica Imersão IA FAMETRO';
$pageDescription = 'Consulte sua inscrição na Jornada Acadêmica Imersão IA FAMETRO e acesse seu QR Code de credenciamento.';
$pageNoIndex = true;

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-xl py-4">
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">

      <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4" style="background: linear-gradient(135deg, #003a7a 0%, #001f42 100%); color: #ffffff;">
        <div class="card-body p-4">
          <span class="badge bg-danger text-uppercase px-3 py-1 rounded-pill mb-2 fw-bold" style="background-color: #e30613 !important;">Consultar Inscrição</span>
          <h2 class="fw-bold mb-1 fs-3">Já se inscreveu? Encontre seu QR Code</h2>
          <p class="mb-0 text-white-50">Informe o e-mail usado na inscrição pra ver suas palestras e mostrar o QR Code no credenciamento.</p>
        </div>
      </div>

      <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
        <div class="card-body p-4">

          <?php if (!empty($mensagem)): ?>
            <div class="alert alert-<?= $tipoMensagem ?> alert-dismissible fade show rounded-3 text-center" role="alert">
              <?= htmlspecialchars($mensagem) ?>
            </div>
          <?php endif; ?>

          <form action="/consultar-inscricao.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(gerarTokenCSRF()) ?>">

            <div class="mb-3">
              <label class="form-label required fw-bold">E-mail</label>
              <input type="email" name="email" class="form-control rounded-3" placeholder="seuemail@exemplo.com"
                     value="<?= htmlspecialchars($emailBusca) ?>" required>
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold">Matrícula / RA <span class="fw-normal text-muted">(opcional — se você for aluno e quiser filtrar com mais precisão)</span></label>
              <input type="text" name="matricula" class="form-control rounded-3" placeholder="Ex: 202310123"
                     value="<?= htmlspecialchars($matriculaBusca) ?>">
            </div>

            <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-2">
              <a href="/index.php" class="btn btn-secondary rounded-3 px-4">
                <i class="ti ti-arrow-left me-1"></i> Voltar
              </a>
              <button type="submit" class="btn btn-danger rounded-3 px-4 fw-bold" style="background-color: #e30613; border-color: #e30613;">
                <i class="ti ti-search me-1"></i> Buscar
              </button>
            </div>
          </form>

        </div>
      </div>

      <?php if (!empty($inscricoes)): ?>
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
          <div class="card-header bg-white border-bottom py-3">
            <h3 class="card-title fw-bold m-0" style="color: #003a7a;">Suas inscrições</h3>
          </div>
          <div class="list-group list-group-flush">
            <?php foreach ($inscricoes as $i): ?>
              <?php $estaPresente = ((int)($i['presenca_confirmada'] ?? 0) === 1 || (int)($i['presente'] ?? 0) === 1); ?>
              <div class="list-group-item p-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                  <div>
                    <div class="fw-bold" style="color: #003a7a;"><?= htmlspecialchars($i['titulo']) ?></div>
                    <div class="small text-muted">
                      <i class="ti ti-clock me-1"></i>
                      <?= date('H:i', strtotime($i['horario_inicio'])) ?> - <?= date('H:i', strtotime($i['horario_fim'])) ?>
                      <?php if (($i['tipo_participante'] ?? 'aluno') === 'externo'): ?>
                        <span class="badge bg-secondary ms-2">Externo</span>
                      <?php else: ?>
                        <span class="badge bg-primary ms-2">Aluno</span>
                      <?php endif; ?>
                      <?php if ($estaPresente): ?>
                        <span class="badge bg-success ms-1"><i class="ti ti-check me-1"></i>Presença confirmada</span>
                      <?php else: ?>
                        <span class="badge bg-warning text-dark ms-1">Aguardando confirmação</span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <a href="/ticket.php?codigo=<?= urlencode($i['codigo_qrcode']) ?>" class="btn btn-sm btn-fametro-red rounded-3 fw-bold">
                    <i class="ti ti-qrcode me-1"></i> Ver QR Code
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
