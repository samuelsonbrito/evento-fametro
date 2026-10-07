<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/evento.php';
require_once __DIR__ . '/includes/certificado.php';

// Página pública pra quem recebe o certificado (coordenação de curso, empregador)
// conferir se ele é autêntico — é pra onde aponta o QR Code impresso no certificado.
$codigoBusca = strtoupper(trim((string) ($_GET['codigo'] ?? '')));
$certificado = null;

if ($codigoBusca !== '') {
    try {
        $certificado = certificadoPorCodigo($pdo, $codigoBusca);
    } catch (PDOException $e) {
        error_log('Erro ao validar certificado: ' . $e->getMessage());
    }
}

// Na validação pública mostra só o fim da matrícula.
function mascararMatricula($matricula) {
    $tamanho = mb_strlen($matricula, 'UTF-8');
    if ($tamanho <= 4) {
        return $matricula;
    }
    return str_repeat('•', $tamanho - 4) . mb_substr($matricula, -4, null, 'UTF-8');
}

$pageTitle = 'Validar Certificado | ' . EVENTO_NOME;
$pageDescription = 'Confira a autenticidade de um certificado da ' . EVENTO_NOME . ' pelo código de validação.';
$pageNoIndex = true;

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-xl py-4">
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">

      <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4" style="background: linear-gradient(135deg, #003a7a 0%, #001f42 100%); color: #ffffff;">
        <div class="card-body p-4">
          <span class="badge bg-danger text-uppercase px-3 py-1 rounded-pill mb-2 fw-bold" style="background-color: #e30613 !important;">Validar Certificado</span>
          <h1 class="fw-bold mb-1 fs-3">Este certificado é autêntico?</h1>
          <p class="mb-0 text-white-50">Informe o código de validação impresso no certificado (ex.: <span class="font-monospace">IMIA-E1C-8F3A9B21C0</span>) ou leia o QR Code.</p>
        </div>
      </div>

      <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
        <div class="card-body p-4">
          <form action="/validar-certificado.php" method="GET">
            <label class="form-label required fw-bold" for="codigo">Código de validação</label>
            <div class="d-flex flex-wrap gap-2">
              <input type="text" id="codigo" name="codigo" class="form-control rounded-3 font-monospace text-uppercase flex-grow-1"
                     style="min-width: 0; flex-basis: 220px;" placeholder="IMIA-..." autocomplete="off"
                     value="<?= htmlspecialchars($codigoBusca) ?>" required>
              <button type="submit" class="btn btn-danger rounded-3 px-4 fw-bold" style="background-color: #e30613; border-color: #e30613;">
                <i class="ti ti-shield-check me-1"></i> Validar
              </button>
            </div>
          </form>
        </div>
      </div>

      <?php if ($codigoBusca !== '' && $certificado): ?>
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden border-start border-success border-4">
          <div class="card-body p-4">
            <div class="d-flex align-items-center gap-2 mb-3 text-success">
              <i class="ti ti-circle-check-filled fs-1"></i>
              <h2 class="h3 fw-bold m-0">Certificado válido</h2>
            </div>
            <dl class="row mb-0">
              <dt class="col-sm-4 text-muted fw-normal">Participante</dt>
              <dd class="col-sm-8 fw-bold"><?= htmlspecialchars($certificado['nome']) ?></dd>
              <?php if ($certificado['matricula']): ?>
                <dt class="col-sm-4 text-muted fw-normal">Matrícula</dt>
                <dd class="col-sm-8 font-monospace"><?= htmlspecialchars(mascararMatricula($certificado['matricula'])) ?></dd>
              <?php endif; ?>
              <dt class="col-sm-4 text-muted fw-normal">Evento</dt>
              <dd class="col-sm-8"><?= htmlspecialchars(EVENTO_NOME) ?> — <?= htmlspecialchars(EVENTO_DATA_EXTENSO) ?></dd>
              <dt class="col-sm-4 text-muted fw-normal">Turnos</dt>
              <dd class="col-sm-8">
                <?php foreach ($certificado['turnos'] as $turno): ?>
                  <?php if ($turno['horas'] > 0): ?>
                    <span class="badge bg-light text-dark border me-1"><?= htmlspecialchars($turno['rotulo']) ?> · <?= (int) $turno['horas'] ?>h</span>
                  <?php endif; ?>
                <?php endforeach; ?>
              </dd>
              <dt class="col-sm-4 text-muted fw-normal">Carga horária</dt>
              <dd class="col-sm-8 fw-bold"><?= htmlspecialchars(certificadoHorasPorExtenso($certificado['horas_total'])) ?></dd>
            </dl>
            <a href="/certificado.php?c=<?= rawurlencode($certificado['codigo']) ?>" class="btn btn-outline-primary rounded-3 mt-3">
              <i class="ti ti-file-certificate me-1"></i> Ver certificado
            </a>
          </div>
        </div>
      <?php elseif ($codigoBusca !== ''): ?>
        <div class="alert alert-danger rounded-4 shadow-sm d-flex gap-3 align-items-start" role="alert">
          <i class="ti ti-alert-triangle fs-1"></i>
          <div>
            <h2 class="h4 fw-bold mb-1">Certificado não encontrado</h2>
            <p class="mb-0">Nenhum certificado válido corresponde ao código <span class="font-monospace fw-bold"><?= htmlspecialchars($codigoBusca) ?></span>. Confira se foi digitado exatamente como aparece no certificado.</p>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
