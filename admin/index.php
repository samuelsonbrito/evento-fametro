<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

checarAutenticacaoAdmin();

// Totais estatísticos
$totalPalestras = $pdo->query("SELECT COUNT(*) FROM palestras")->fetchColumn();
$totalInscritos = $pdo->query("SELECT COUNT(*) FROM inscricoes")->fetchColumn();
$totalPresencas = $pdo->query("SELECT COUNT(*) FROM inscricoes WHERE presenca_confirmada = 1")->fetchColumn();
$totalRelatosNovos = $pdo->query("SELECT COUNT(*) FROM relatos_erro WHERE status = 'novo'")->fetchColumn();

// Listagem das palestras cadastradas
$palestras = $pdo->query("
    SELECT p.*, COUNT(i.id) AS total_inscritos 
    FROM palestras p 
    LEFT JOIN inscricoes i ON p.id = i.palestra_id 
    GROUP BY p.id 
    ORDER BY p.horario_inicio ASC
")->fetchAll();

$pageTitle = 'Painel Administrativo | Jornada Acadêmica Imersão FAMETRO';
$pageNoIndex = true;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-xl py-4">
  <div class="page-header mb-4">
    <div class="row align-items-center">
      <div class="col">
        <h2 class="page-title fw-bold" style="color: #003a7a;">Painel Administrativo</h2>
        <div class="text-muted">Visão geral e controle da Jornada Acadêmica FAMETRO</div>
      </div>
      <div class="col-auto ms-auto d-flex gap-2">
        <a href="/admin/inscritos.php" class="btn btn-outline-primary px-3 rounded-3 fw-bold">
          <i class="ti ti-users me-1 fs-4"></i> Todos os Inscritos
        </a>
        <a href="/admin/validar-qrcode.php" class="btn btn-danger px-3 rounded-3 fw-bold" style="background-color: #e30613; border-color: #e30613;">
          <i class="ti ti-qrcode me-1 fs-4"></i> Ler QR Code
        </a>
        <a href="/admin/cadastrar-palestra.php" class="btn btn-primary px-3 rounded-3 fw-bold" style="background-color: #003a7a; border-color: #003a7a;">
          <i class="ti ti-plus me-1 fs-4"></i> Nova Palestra
        </a>
        <a href="/admin/relatos-erro.php" class="btn btn-outline-secondary px-3 rounded-3 fw-bold position-relative">
          <i class="ti ti-message-report me-1 fs-4"></i> Relatos
          <?php if ($totalRelatosNovos > 0): ?>
            <span class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle"><?= $totalRelatosNovos ?></span>
          <?php endif; ?>
        </a>
      </div>
    </div>
  </div>

  <!-- Cards de Estatísticas -->
  <div class="row row-cards mb-4 g-3">
    
    <!-- Palestras Cadastradas -->
    <div class="col-sm-6 col-lg-4">
      <div class="card card-sm shadow-sm border-0 rounded-3">
        <div class="card-body p-3">
          <div class="row align-items-center">
            <div class="col-auto">
              <span class="avatar rounded-3 text-white d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #003a7a !important;">
                <i class="ti ti-presentation fs-1"></i>
              </span>
            </div>
            <div class="col">
              <div class="fw-bold fs-3 text-dark"><?= $totalPalestras ?></div>
              <div class="text-muted small">Palestras Cadastradas</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Inscrições Realizadas -->
    <div class="col-sm-6 col-lg-4">
      <a href="/admin/inscritos.php" class="text-decoration-none">
        <div class="card card-sm shadow-sm border-0 rounded-3">
          <div class="card-body p-3">
            <div class="row align-items-center">
              <div class="col-auto">
                <span class="avatar rounded-3 bg-info text-white d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                  <i class="ti ti-users fs-1"></i>
                </span>
              </div>
              <div class="col">
                <div class="fw-bold fs-3 text-dark"><?= $totalInscritos ?></div>
                <div class="text-muted small">Inscrições Realizadas (Ver Todos)</div>
              </div>
            </div>
          </div>
        </div>
      </a>
    </div>

    <!-- Presenças Confirmadas -->
    <div class="col-sm-6 col-lg-4">
      <div class="card card-sm shadow-sm border-0 rounded-3">
        <div class="card-body p-3">
          <div class="row align-items-center">
            <div class="col-auto">
              <span class="avatar rounded-3 text-white d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #2fb344 !important;">
                <i class="ti ti-user-check fs-1 text-white"></i>
              </span>
            </div>
            <div class="col">
              <div class="fw-bold fs-3 text-dark"><?= $totalPresencas ?></div>
              <div class="text-muted small">Presenças Confirmadas</div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- Tabela de Palestras -->
  <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
    <div class="card-header bg-white border-bottom py-3">
      <h3 class="card-title fw-bold m-0" style="color: #003a7a;">Palestras e Inscrições</h3>
    </div>
    <div class="table-responsive">
      <table class="table card-table table-vcenter text-nowrap datatable align-middle">
        <thead class="bg-light">
          <tr>
            <th>Horário</th>
            <th>Palestra</th>
            <th>Palestrante</th>
            <th>Inscritos</th>
            <th class="text-end">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($palestras)): ?>
            <tr>
              <td colspan="5" class="text-center text-muted py-4">
                Nenhuma palestra cadastrada até o momento.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($palestras as $p): ?>
              <tr>
                <td>
                  <span class="badge bg-light text-dark border px-2 py-1">
                    <i class="ti ti-clock me-1"></i>
                    <?= date('H:i', strtotime($p['horario_inicio'])) ?> - <?= date('H:i', strtotime($p['horario_fim'])) ?>
                  </span>
                </td>
                <td class="fw-bold" style="color: #003a7a;"><?= htmlspecialchars($p['titulo']) ?></td>
                <td><?= htmlspecialchars($p['palestrante']) ?></td>
                <td>
                  <span class="badge bg-primary text-white me-1"><?= $p['total_inscritos'] ?></span> / <?= $p['vagas'] ?> vagas
                </td>
                <td class="text-end">
                  <a href="/admin/inscritos.php?palestra_id=<?= $p['id'] ?>" class="btn btn-outline-primary btn-sm rounded-2 fw-semibold">
                    <i class="ti ti-list me-1"></i> Lista de Inscritos
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
