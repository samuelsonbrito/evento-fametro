<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$palestra_id = filter_input(INPUT_GET, 'palestra_id', FILTER_VALIDATE_INT);

if (!$palestra_id) {
    header('Location: /evento-fametro/index.php');
    exit;
}

// Buscar dados da palestra
$stmt = $pdo->prepare("SELECT * FROM palestras WHERE id = ?");
$stmt->execute([$palestra_id]);
$palestra = $stmt->fetch();

if (!$palestra) {
    header('Location: /evento-fametro/index.php');
    exit;
}

$mensagem = '';
$tipoMensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_aluno       = sanitize($_POST['nome_aluno'] ?? '');
    $email            = sanitize($_POST['email'] ?? '');
    $tipo_participante= sanitize($_POST['tipo_participante'] ?? 'aluno');
    $matricula        = ($tipo_participante === 'aluno') ? sanitize($_POST['matricula'] ?? '') : null;

    if (!empty($nome_aluno) && !empty($email)) {
        try {
            // Verificar se já existe inscrição para este e-mail nesta palestra
            $stmtCheck = $pdo->prepare("SELECT codigo_qrcode FROM inscricoes WHERE palestra_id = ? AND email = ?");
            $stmtCheck->execute([$palestra_id, $email]);
            $jaInscrito = $stmtCheck->fetch();

            if ($jaInscrito) {
                // Se já estiver inscrito, redireciona direto para o comprovante
                header('Location: /evento-fametro/comprovante.php?codigo=' . urlencode($jaInscrito['codigo_qrcode']));
                exit;
            } else {
                $codigo_qrcode = 'QR-' . strtoupper(uniqid());

                $sql = "INSERT INTO inscricoes (palestra_id, nome_aluno, email, tipo_participante, matricula, codigo_qrcode)
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$palestra_id, $nome_aluno, $email, $tipo_participante, $matricula, $codigo_qrcode]);

                // Redireciona para a página de comprovante oficial
                header('Location: /evento-fametro/comprovante.php?codigo=' . urlencode($codigo_qrcode));
                exit;
            }
        } catch (PDOException $e) {
            $mensagem = "Erro ao processar inscrição: " . $e->getMessage();
            $tipoMensagem = "danger";
        }
    } else {
        $mensagem = "Por favor, preencha todos os campos obrigatórios.";
        $tipoMensagem = "warning";
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-xl py-4">
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
      
      <!-- Detalhes da Palestra -->
      <div class="card shadow-sm border-0 rounded-4 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #003a7a 0%, #001f42 100%); color: #ffffff;">
        <div class="card-body p-4">
          <span class="badge bg-danger text-uppercase px-3 py-1 rounded-pill mb-2 fw-bold" style="background-color: #e30613 !important;">Inscrição de Participante</span>
          <h2 class="fw-bold mb-1 fs-3"><?= htmlspecialchars($palestra['titulo']) ?></h2>
          <p class="mb-2 text-white-50"><i class="ti ti-user me-1"></i> Palestrante: <?= htmlspecialchars($palestra['palestrante']) ?></p>
          <div class="d-flex align-items-center gap-3 fs-6">
            <span class="badge bg-white text-dark px-2 py-1 rounded-2">
              <i class="ti ti-clock me-1 text-primary"></i> 
              <?= date('H:i', strtotime($palestra['horario_inicio'])) ?> - <?= date('H:i', strtotime($palestra['horario_fim'])) ?>
            </span>
          </div>
        </div>
      </div>

      <!-- Formulário de Inscrição -->
      <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="card-body p-4">

          <?php if (!empty($mensagem)): ?>
            <div class="alert alert-<?= $tipoMensagem ?> alert-dismissible fade show rounded-3 text-center" role="alert">
              <?= $mensagem ?>
            </div>
          <?php endif; ?>

          <form action="" method="POST">
            
            <div class="mb-3">
              <label class="form-label required fw-bold">Tipo de Participante</label>
              <select name="tipo_participante" id="tipoParticipante" class="form-select rounded-3 py-2" onchange="alternarCampoMatricula()">
                <option value="aluno" selected>Aluno FAMETRO</option>
                <option value="externo">Público Externo</option>
              </select>
            </div>

            <div class="mb-3" id="boxMatricula">
              <label class="form-label required fw-bold">Matrícula / RA</label>
              <input type="text" name="matricula" id="inputMatricula" class="form-control rounded-3" placeholder="Ex: 202310123" required>
            </div>

            <div class="mb-3">
              <label class="form-label required fw-bold">Nome Completo</label>
              <input type="text" name="nome_aluno" class="form-control rounded-3" placeholder="Digite seu nome completo" required>
            </div>

            <div class="mb-3">
              <label class="form-label required fw-bold">E-mail</label>
              <input type="email" name="email" class="form-control rounded-3" placeholder="seuemail@exemplo.com" required>
            </div>

            <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-2">
              <a href="/evento-fametro/index.php" class="btn btn-secondary rounded-3 px-4">
                <i class="ti ti-arrow-left me-1"></i> Voltar
              </a>
              <button type="submit" class="btn btn-danger rounded-3 px-4 fw-bold" style="background-color: #e30613; border-color: #e30613;">
                <i class="ti ti-check me-1"></i> Confirmar Inscrição
              </button>
            </div>

          </form>

        </div>
      </div>

    </div>
  </div>
</div>

<script>
function alternarCampoMatricula() {
  const tipo = document.getElementById('tipoParticipante').value;
  const boxMatricula = document.getElementById('boxMatricula');
  const inputMatricula = document.getElementById('inputMatricula');

  if (tipo === 'externo') {
    boxMatricula.style.display = 'none';
    inputMatricula.removeAttribute('required');
    inputMatricula.value = '';
  } else {
    boxMatricula.style.display = 'block';
    inputMatricula.setAttribute('required', 'required');
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
