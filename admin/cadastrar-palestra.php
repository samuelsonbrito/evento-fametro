<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

checarAutenticacaoAdmin();

$mensagem = '';
$tipoMensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validarTokenCSRF($_POST['csrf_token'] ?? '')) {
    $mensagem = "Sessão expirada. Atualize a página e tente novamente.";
    $tipoMensagem = "warning";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo         = sanitize($_POST['titulo'] ?? '');
    $palestrante    = sanitize($_POST['palestrante'] ?? '');
    $descricao      = sanitize($_POST['descricao'] ?? '');
    $horario_inicio = trim($_POST['horario_inicio'] ?? '');
    $horario_fim    = trim($_POST['horario_fim'] ?? '');
    $vagas          = (int)($_POST['vagas'] ?? 100);

    // Ajusta a formatação para garantir o padrão HH:MM:SS para o banco de dados
    if (strlen($horario_inicio) === 5) { $horario_inicio .= ':00'; }
    if (strlen($horario_fim) === 5) { $horario_fim .= ':00'; }

    $nomeFoto = 'default.jpg';

    // Processamento do upload da foto
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $extensao = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($extensao, $extensoesPermitidas)) {
            $pastaDestino = __DIR__ . '/../uploads/palestrantes/';
            if (!is_dir($pastaDestino)) {
                mkdir($pastaDestino, 0755, true);
            }

            $nomeFoto = uniqid('palestrante_') . '.' . $extensao;
            $destino = $pastaDestino . $nomeFoto;
            move_uploaded_file($_FILES['foto']['tmp_name'], $destino);
        }
    }

    if (!empty($titulo) && !empty($palestrante) && !empty($horario_inicio) && !empty($horario_fim)) {
        try {
            $sql = "INSERT INTO palestras (titulo, palestrante, foto, descricao, horario_inicio, horario_fim, vagas) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$titulo, $palestrante, $nomeFoto, $descricao, $horario_inicio, $horario_fim, $vagas]);

            $mensagem = "Palestra cadastrada com sucesso!";
            $tipoMensagem = "success";
        } catch (PDOException $e) {
            error_log('Erro ao salvar palestra: ' . $e->getMessage());
            $mensagem = "Erro ao salvar a palestra. Tente novamente em instantes.";
            $tipoMensagem = "danger";
        }
    } else {
        $mensagem = "Preencha todos os campos obrigatórios.";
        $tipoMensagem = "warning";
    }
}

$pageTitle = 'Cadastrar Palestra | Jornada Acadêmica Imersão FAMETRO';
$pageNoIndex = true;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-xl py-4">
  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="card-header bg-primary text-white p-3">
          <h3 class="card-title m-0 fw-bold fs-4">
            <i class="ti ti-plus me-1"></i> Cadastrar Nova Palestra
          </h3>
        </div>
        <div class="card-body p-4">

          <?php if (!empty($mensagem)): ?>
            <div class="alert alert-<?= $tipoMensagem ?> alert-dismissible fade show" role="alert">
              <?= $mensagem ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>

          <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(gerarTokenCSRF()) ?>">

            <div class="mb-3">
              <label class="form-label required fw-bold">Título da Palestra</label>
              <input type="text" name="titulo" class="form-control" placeholder="Ex: Aplicações Práticas de IA no Mercado" required>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label required fw-bold">Nome do Palestrante</label>
                <input type="text" name="palestrante" class="form-control" placeholder="Ex: Prof. Dr. João Silva" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Foto do Palestrante</label>
                <input type="file" name="foto" class="form-control" accept="image/*">
                <small class="form-hint text-muted">Formatos recomendados: JPG, PNG ou WEBP.</small>
              </div>
            </div>

            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label required fw-bold">Horário de Início (ex: 14:15)</label>
                <input type="text" name="horario_inicio" class="form-control" placeholder="14:15" pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]" maxlength="5" required>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label required fw-bold">Horário de Término (ex: 15:30)</label>
                <input type="text" name="horario_fim" class="form-control" placeholder="15:30" pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]" maxlength="5" required>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label fw-bold">Limite de Vagas</label>
                <input type="number" name="vagas" class="form-control" value="100" min="1">
              </div>
            </div>

            <div class="mb-4">
              <label class="form-label fw-bold">Descrição / Resumo</label>
              <textarea name="descricao" class="form-control" rows="4" placeholder="Breve descrição dos tópicos abordados..."></textarea>
            </div>

            <div class="d-flex justify-content-between pt-3 border-top">
              <a href="/admin/index.php" class="btn btn-secondary px-4 rounded-3">
                <i class="ti ti-arrow-left me-1"></i> Voltar ao Painel
              </a>
              <button type="submit" class="btn btn-danger px-4 rounded-3 fw-bold" style="background-color: #e30613; border-color: #e30613;">
                <i class="ti ti-device-floppy me-1"></i> Salvar Palestra
              </button>
            </div>

          </form>

        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
