<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

checarAutenticacaoAdmin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
}

if (!$id) {
    header('Location: /admin/index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM palestras WHERE id = ?");
$stmt->execute([$id]);
$palestra = $stmt->fetch();

if (!$palestra) {
    header('Location: /admin/index.php');
    exit;
}

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

    if (strlen($horario_inicio) === 5) { $horario_inicio .= ':00'; }
    if (strlen($horario_fim) === 5) { $horario_fim .= ':00'; }

    // Diferente do cadastro: aqui uma extensão inválida ou nenhum arquivo
    // enviado NÃO deve apagar a foto que já existe — só troca quando um
    // upload novo e válido realmente acontece.
    $nomeFoto = $palestra['foto'];
    $fotoAntiga = null;

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $extensao = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($extensao, $extensoesPermitidas)) {
            $pastaDestino = __DIR__ . '/../uploads/palestrantes/';
            if (!is_dir($pastaDestino)) {
                mkdir($pastaDestino, 0755, true);
            }

            $novoNomeFoto = uniqid('palestrante_') . '.' . $extensao;
            $destino = $pastaDestino . $novoNomeFoto;

            if (move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
                $fotoAntiga = $palestra['foto'];
                $nomeFoto = $novoNomeFoto;
            }
        }
    }

    if (!empty($titulo) && !empty($palestrante) && !empty($horario_inicio) && !empty($horario_fim)) {
        try {
            $sql = "UPDATE palestras
                    SET titulo = ?, palestrante = ?, descricao = ?, horario_inicio = ?, horario_fim = ?, vagas = ?, foto = ?
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$titulo, $palestrante, $descricao, $horario_inicio, $horario_fim, $vagas, $nomeFoto, $id]);

            // Só apaga o arquivo antigo depois do UPDATE confirmado, e nunca a
            // foto padrão (pode estar em uso por outras palestras).
            if ($fotoAntiga && $fotoAntiga !== 'default.jpg') {
                $caminhoAntigo = __DIR__ . '/../uploads/palestrantes/' . $fotoAntiga;
                if (is_file($caminhoAntigo)) {
                    unlink($caminhoAntigo);
                }
            }

            $mensagem = "Palestra atualizada com sucesso!";
            $tipoMensagem = "success";

            // Recarrega os dados atuais pra refletir no formulário
            $stmt = $pdo->prepare("SELECT * FROM palestras WHERE id = ?");
            $stmt->execute([$id]);
            $palestra = $stmt->fetch();
        } catch (PDOException $e) {
            error_log('Erro ao atualizar palestra: ' . $e->getMessage());
            $mensagem = "Erro ao atualizar a palestra. Tente novamente em instantes.";
            $tipoMensagem = "danger";
        }
    } else {
        $mensagem = "Preencha todos os campos obrigatórios.";
        $tipoMensagem = "warning";
    }
}

$pageTitle = 'Editar Palestra | Jornada Acadêmica Imersão IA FAMETRO';
$pageNoIndex = true;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-xl py-4">
  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="card-header bg-primary text-white p-3">
          <h3 class="card-title m-0 fw-bold fs-4">
            <i class="ti ti-pencil me-1"></i> Editar Palestra
          </h3>
        </div>
        <div class="card-body p-4">

          <?php if (!empty($mensagem)): ?>
            <div class="alert alert-<?= $tipoMensagem ?> alert-dismissible fade show" role="alert">
              <?= htmlspecialchars($mensagem) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>

          <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?id=<?= $id ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(gerarTokenCSRF()) ?>">
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="mb-3">
              <label class="form-label required fw-bold">Título da Palestra</label>
              <input type="text" name="titulo" class="form-control" value="<?= htmlspecialchars($palestra['titulo']) ?>" required>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label required fw-bold">Nome do Palestrante</label>
                <input type="text" name="palestrante" class="form-control" value="<?= htmlspecialchars($palestra['palestrante']) ?>" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Foto do Palestrante</label>
                <?php if (!empty($palestra['foto']) && $palestra['foto'] !== 'default.jpg'): ?>
                  <div class="mb-2">
                    <img src="/uploads/palestrantes/<?= htmlspecialchars($palestra['foto']) ?>" alt="Foto atual" class="rounded-3 border" style="width: 64px; height: 64px; object-fit: cover;">
                  </div>
                <?php endif; ?>
                <input type="file" name="foto" class="form-control" accept="image/*">
                <small class="form-hint text-muted">Deixe em branco pra manter a foto atual.</small>
              </div>
            </div>

            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label required fw-bold">Horário de Início (ex: 14:15)</label>
                <input type="text" name="horario_inicio" class="form-control" value="<?= htmlspecialchars(substr($palestra['horario_inicio'], 0, 5)) ?>" pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]" maxlength="5" required>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label required fw-bold">Horário de Término (ex: 15:30)</label>
                <input type="text" name="horario_fim" class="form-control" value="<?= htmlspecialchars(substr($palestra['horario_fim'], 0, 5)) ?>" pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]" maxlength="5" required>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label fw-bold">Limite de Vagas</label>
                <input type="number" name="vagas" class="form-control" value="<?= (int)$palestra['vagas'] ?>" min="1">
              </div>
            </div>

            <div class="mb-4">
              <label class="form-label fw-bold">Descrição / Resumo</label>
              <textarea name="descricao" class="form-control" rows="4"><?= htmlspecialchars($palestra['descricao']) ?></textarea>
            </div>

            <div class="d-flex justify-content-between pt-3 border-top">
              <a href="/admin/index.php" class="btn btn-secondary px-4 rounded-3">
                <i class="ti ti-arrow-left me-1"></i> Voltar ao Painel
              </a>
              <button type="submit" class="btn btn-danger px-4 rounded-3 fw-bold" style="background-color: #e30613; border-color: #e30613;">
                <i class="ti ti-device-floppy me-1"></i> Salvar Alterações
              </button>
            </div>

          </form>

        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
