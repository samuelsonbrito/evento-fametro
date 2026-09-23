<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

checarAutenticacaoAdmin();

$mensagem = '';
$tipoMensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $mensagem = 'Sessão expirada. Atualize a página e tente novamente.';
        $tipoMensagem = 'warning';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $acao = $_POST['acao'] ?? '';

        if ($id > 0 && $acao === 'resolver') {
            $stmt = $pdo->prepare("UPDATE relatos_erro SET status = 'resolvido' WHERE id = ?");
            $stmt->execute([$id]);
        } elseif ($id > 0 && $acao === 'excluir') {
            $stmt = $pdo->prepare("DELETE FROM relatos_erro WHERE id = ?");
            $stmt->execute([$id]);
        }

        header('Location: /admin/relatos-erro.php');
        exit;
    }
}

$status = $_GET['status'] ?? 'novo';
$sql = "SELECT * FROM relatos_erro";
$params = [];
if (in_array($status, ['novo', 'resolvido'], true)) {
    $sql .= " WHERE status = ?";
    $params[] = $status;
}
$sql .= " ORDER BY criado_em DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$relatos = $stmt->fetchAll();

$totalNovos = $pdo->query("SELECT COUNT(*) FROM relatos_erro WHERE status = 'novo'")->fetchColumn();

$tipoLabel = ['erro' => 'Erro técnico', 'sugestao' => 'Sugestão', 'outro' => 'Outro'];
$tipoBadge = ['erro' => 'bg-danger', 'sugestao' => 'bg-info', 'outro' => 'bg-secondary'];

$pageTitle = 'Relatos de Problemas | Jornada Acadêmica Imersão FAMETRO';
$pageNoIndex = true;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-xl py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold text-fametro-blue m-0">
      <i class="ti ti-message-report me-2"></i>Relatos de Problemas
      <?php if ($totalNovos > 0): ?>
        <span class="badge bg-danger ms-1"><?= $totalNovos ?> novo<?= $totalNovos > 1 ? 's' : '' ?></span>
      <?php endif; ?>
    </h2>
    <a href="/admin/index.php" class="btn btn-secondary rounded-3">
      <i class="ti ti-arrow-left me-1"></i> Painel
    </a>
  </div>

  <?php if (!empty($mensagem)): ?>
    <div class="alert alert-<?= $tipoMensagem ?> alert-dismissible" role="alert"><?= htmlspecialchars($mensagem) ?></div>
  <?php endif; ?>

  <div class="mb-3 d-flex gap-2">
    <a href="/admin/relatos-erro.php?status=novo" class="btn btn-sm <?= $status === 'novo' ? 'btn-fametro-red' : 'btn-outline-secondary' ?> rounded-3">Novos</a>
    <a href="/admin/relatos-erro.php?status=resolvido" class="btn btn-sm <?= $status === 'resolvido' ? 'btn-fametro-red' : 'btn-outline-secondary' ?> rounded-3">Resolvidos</a>
    <a href="/admin/relatos-erro.php?status=todos" class="btn btn-sm <?= $status === 'todos' ? 'btn-fametro-red' : 'btn-outline-secondary' ?> rounded-3">Todos</a>
  </div>

  <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="bg-fametro-blue text-white">
            <tr>
              <th class="py-3 ps-3">Quando</th>
              <th class="py-3">Tipo</th>
              <th class="py-3">Mensagem</th>
              <th class="py-3">Contato</th>
              <th class="py-3">Página</th>
              <th class="py-3 text-end pe-3">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($relatos)): ?>
              <tr>
                <td colspan="6" class="text-center py-4 text-muted">Nenhum relato encontrado.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($relatos as $r): ?>
                <tr>
                  <td class="ps-3 text-nowrap"><?= date('d/m/Y H:i', strtotime($r['criado_em'])) ?></td>
                  <td>
                    <span class="badge <?= $tipoBadge[$r['tipo']] ?? 'bg-secondary' ?>"><?= $tipoLabel[$r['tipo']] ?? 'Outro' ?></span>
                  </td>
                  <td style="max-width: 320px;"><?= nl2br(htmlspecialchars($r['mensagem'])) ?></td>
                  <td>
                    <?php if (!empty($r['nome']) || !empty($r['email'])): ?>
                      <?= htmlspecialchars($r['nome'] ?? '') ?>
                      <?php if (!empty($r['email'])): ?><br><small class="text-muted"><?= htmlspecialchars($r['email']) ?></small><?php endif; ?>
                    <?php else: ?>
                      <span class="text-muted">Anônimo</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-truncate" style="max-width: 200px;">
                    <?php if (!empty($r['pagina_url'])): ?>
                      <a href="<?= htmlspecialchars($r['pagina_url']) ?>" target="_blank" class="small"><?= htmlspecialchars($r['pagina_url']) ?></a>
                    <?php endif; ?>
                  </td>
                  <td class="text-end pe-3 text-nowrap">
                    <?php if ($r['status'] === 'novo'): ?>
                      <form method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(gerarTokenCSRF()) ?>">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <input type="hidden" name="acao" value="resolver">
                        <button type="submit" class="btn btn-sm btn-success rounded-3" title="Marcar como resolvido">
                          <i class="ti ti-check"></i>
                        </button>
                      </form>
                    <?php else: ?>
                      <span class="badge bg-success-lt text-success">Resolvido</span>
                    <?php endif; ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Excluir este relato?');">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(gerarTokenCSRF()) ?>">
                      <input type="hidden" name="id" value="<?= $r['id'] ?>">
                      <input type="hidden" name="acao" value="excluir">
                      <button type="submit" class="btn btn-sm btn-outline-danger rounded-3" title="Excluir">
                        <i class="ti ti-trash"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
