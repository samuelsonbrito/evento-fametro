<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

checarAutenticacaoAdmin();

$palestra_id = filter_input(INPUT_GET, 'palestra_id', FILTER_VALIDATE_INT);

// Procurar todas as palestras para o filtro
$stmtPalestras = $pdo->query("SELECT id, titulo FROM palestras ORDER BY titulo ASC");
$palestras = $stmtPalestras->fetchAll();

// Procurar inscritos
$params = [];
$sql = "SELECT i.*, p.titulo AS palestra_titulo 
        FROM inscricoes i 
        JOIN palestras p ON i.palestra_id = p.id ";

if ($palestra_id) {
    $sql .= " WHERE i.palestra_id = ? ";
    $params[] = $palestra_id;
}

$sql .= " ORDER BY i.id DESC";
$stmtInscritos = $pdo->prepare($sql);
$stmtInscritos->execute($params);
$inscritos = $stmtInscritos->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-xl py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold text-fametro-blue m-0">
      <i class="ti ti-users me-2"></i>Inscritos por Palestra
    </h2>
    <div class="d-flex gap-2">
      <?php if ($palestra_id): ?>
        <a href="/evento-fametro/admin/imprimir-comprovantes-lote.php?palestra_id=<?= $palestra_id ?>" target="_blank" class="btn btn-success rounded-3 fw-bold">
          <i class="ti ti-printer me-1"></i> Imprimir Declarações em Lote
        </a>
      <?php endif; ?>
      <a href="/evento-fametro/admin/index.php" class="btn btn-secondary rounded-3">
        <i class="ti ti-arrow-left me-1"></i> Painel
      </a>
    </div>
  </div>

  <!-- Filtro por Palestra -->
  <div class="card shadow-sm border-0 rounded-4 mb-4">
    <div class="card-body p-3">
      <form method="GET" action="" class="row g-3 align-items-center">
        <div class="col-md-8">
          <label class="form-label fw-bold mb-1">Selecionar Palestra:</label>
          <select name="palestra_id" class="form-select rounded-3" onchange="this.form.submit()">
            <option value="">-- Selecione uma Palestra --</option>
            <?php foreach ($palestras as $palestra): ?>
              <option value="<?= $palestra['id'] ?>" <?= $palestra_id == $palestra['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($palestra['titulo']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <?php if ($palestra_id): ?>
            <a href="/evento-fametro/admin/inscritos.php" class="btn btn-outline-secondary rounded-3 w-100">
              Ver Todos
            </a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- Tabela de Inscritos -->
  <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="bg-fametro-blue text-white">
            <tr>
              <th class="py-3 ps-3">ID</th>
              <th class="py-3">Participante</th>
              <th class="py-3">Tipo</th>
              <th class="py-3">Palestra</th>
              <th class="py-3 text-center">Presença</th>
              <th class="py-3 text-end pe-3">Declaração</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($inscritos)): ?>
              <tr>
                <td colspan="6" class="text-center py-4 text-muted">
                  Nenhum inscrito encontrado.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($inscritos as $row): ?>
                <?php $estaPresente = ((int)($row['presenca_confirmada'] ?? 0) === 1 || (int)($row['presente'] ?? 0) === 1); ?>
                <tr>
                  <td class="ps-3 font-monospace">#<?= $row['id'] ?></td>
                  <td>
                    <strong class="d-block text-dark"><?= htmlspecialchars($row['nome_aluno']) ?></strong>
                    <small class="text-muted"><?= htmlspecialchars($row['email']) ?></small>
                    <?php if (!empty($row['matricula'])): ?>
                      <br><small class="text-primary fw-bold">Matrícula: <?= htmlspecialchars($row['matricula']) ?></small>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (($row['tipo_participante'] ?? 'aluno') === 'externo'): ?>
                      <span class="badge bg-secondary">Externo</span>
                    <?php else: ?>
                      <span class="badge bg-primary">Aluno</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($row['palestra_titulo']) ?></td>
                  <td class="text-center">
                    <?php if ($estaPresente): ?>
                      <span class="badge bg-success"><i class="ti ti-check me-1"></i>Confirmada</span>
                    <?php else: ?>
                      <span class="badge bg-warning text-dark">Pendente</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end pe-3">
                    <?php if ($estaPresente): ?>
                      <a href="/evento-fametro/admin/imprimir-comprovante.php?id=<?= $row['id'] ?>" target="_blank" class="btn btn-sm btn-success rounded-3 fw-bold">
                        <i class="ti ti-file-text me-1"></i> Declaração
                      </a>
                    <?php else: ?>
                      <button class="btn btn-sm btn-light border text-muted rounded-3" disabled title="Aguardando confirmação de presença">
                        <i class="ti ti-lock me-1"></i> Declaração
                      </button>
                    <?php endif; ?>
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
