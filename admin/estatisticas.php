<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

checarAutenticacaoAdmin();

$stmt = $pdo->query("
    SELECT p.id, p.titulo, p.horario_inicio,
        SUM(CASE WHEN i.tipo_participante = 'aluno' THEN 1 ELSE 0 END) AS total_interno,
        SUM(CASE WHEN i.tipo_participante = 'externo' THEN 1 ELSE 0 END) AS total_externo,
        COUNT(i.id) AS total
    FROM palestras p
    LEFT JOIN inscricoes i ON i.palestra_id = p.id
    GROUP BY p.id
    ORDER BY p.horario_inicio ASC
");
$palestras = $stmt->fetchAll();

$totalInterno = 0;
$totalExterno = 0;
$maisInterno = null;
$maisExterno = null;

foreach ($palestras as $p) {
    $totalInterno += (int)$p['total_interno'];
    $totalExterno += (int)$p['total_externo'];

    if ($maisInterno === null || (int)$p['total_interno'] > (int)$maisInterno['total_interno']) {
        $maisInterno = $p;
    }
    if ($maisExterno === null || (int)$p['total_externo'] > (int)$maisExterno['total_externo']) {
        $maisExterno = $p;
    }
}

$totalGeral = $totalInterno + $totalExterno;

$pageTitle = 'Estatísticas | Jornada Acadêmica Imersão IA FAMETRO';
$pageNoIndex = true;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-xl py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold text-fametro-blue m-0">
      <i class="ti ti-chart-bar me-2"></i>Estatísticas
    </h2>
    <a href="/admin/index.php" class="btn btn-secondary rounded-3">
      <i class="ti ti-arrow-left me-1"></i> Painel
    </a>
  </div>

  <!-- Cards de resumo -->
  <div class="row row-cards mb-4 g-3">
    <div class="col-sm-6 col-lg-3">
      <div class="card card-sm shadow-sm border-0 rounded-3">
        <div class="card-body p-3">
          <div class="fw-bold fs-3 text-dark"><?= $totalGeral ?></div>
          <div class="text-muted small">Total de Inscrições</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card card-sm shadow-sm border-0 rounded-3">
        <div class="card-body p-3">
          <div class="fw-bold fs-3 text-primary"><?= $totalInterno ?></div>
          <div class="text-muted small">Público Interno (Alunos)</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card card-sm shadow-sm border-0 rounded-3">
        <div class="card-body p-3">
          <div class="fw-bold fs-3" style="color: #e30613;"><?= $totalExterno ?></div>
          <div class="text-muted small">Público Externo</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card card-sm shadow-sm border-0 rounded-3">
        <div class="card-body p-3">
          <div class="fw-bold fs-3 text-dark"><?= count($palestras) ?></div>
          <div class="text-muted small">Palestras Cadastradas</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Destaques -->
  <div class="row row-cards mb-4 g-3">
    <div class="col-md-6">
      <div class="card shadow-sm border-0 rounded-3 h-100">
        <div class="card-body p-3 d-flex align-items-center gap-3">
          <span class="avatar rounded-3 bg-primary text-white d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
            <i class="ti ti-school fs-1"></i>
          </span>
          <div>
            <div class="text-muted small">Mais público interno (alunos)</div>
            <?php if ($maisInterno && (int)$maisInterno['total_interno'] > 0): ?>
              <div class="fw-bold"><?= htmlspecialchars($maisInterno['titulo']) ?></div>
              <div class="small text-primary fw-bold"><?= (int)$maisInterno['total_interno'] ?> aluno(s)</div>
            <?php else: ?>
              <div class="text-muted">Ainda sem inscrições de alunos</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card shadow-sm border-0 rounded-3 h-100">
        <div class="card-body p-3 d-flex align-items-center gap-3">
          <span class="avatar rounded-3 text-white d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #e30613 !important;">
            <i class="ti ti-users fs-1"></i>
          </span>
          <div>
            <div class="text-muted small">Mais público externo</div>
            <?php if ($maisExterno && (int)$maisExterno['total_externo'] > 0): ?>
              <div class="fw-bold"><?= htmlspecialchars($maisExterno['titulo']) ?></div>
              <div class="small fw-bold" style="color: #e30613;"><?= (int)$maisExterno['total_externo'] ?> externo(s)</div>
            <?php else: ?>
              <div class="text-muted">Ainda sem inscrições externas</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Gráfico -->
  <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
      <h3 class="card-title fw-bold m-0" style="color: #003a7a;">Público Interno x Externo por Palestra</h3>
      <div class="d-flex align-items-center gap-2">
        <label for="filtroPalestra" class="form-label m-0 small text-muted">Filtrar:</label>
        <select id="filtroPalestra" class="form-select form-select-sm" style="width: auto;">
          <option value="todas">Todas as palestras</option>
          <?php foreach ($palestras as $p): ?>
            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['titulo']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="card-body">
      <?php if (empty($palestras)): ?>
        <p class="text-muted text-center py-4 m-0">Nenhuma palestra cadastrada até o momento.</p>
      <?php else: ?>
        <canvas id="graficoPublico" height="100"></canvas>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($palestras)): ?>
    <!-- Tabela de apoio -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
      <div class="table-responsive">
        <table class="table card-table table-vcenter align-middle mb-0">
          <thead class="bg-light">
            <tr>
              <th>Palestra</th>
              <th class="text-center">Interno (Alunos)</th>
              <th class="text-center">Externo</th>
              <th class="text-center">Total</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($palestras as $p): ?>
              <tr>
                <td class="fw-bold" style="color: #003a7a;"><?= htmlspecialchars($p['titulo']) ?></td>
                <td class="text-center"><span class="badge bg-primary text-white"><?= (int)$p['total_interno'] ?></span></td>
                <td class="text-center"><span class="badge" style="background-color: #e30613; color: #fff;"><?= (int)$p['total_externo'] ?></span></td>
                <td class="text-center fw-bold"><?= (int)$p['total'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php if (!empty($palestras)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
  (function () {
    var dados = <?= json_encode(array_map(function ($p) {
        return [
            'id' => (string)$p['id'],
            'titulo' => $p['titulo'],
            'interno' => (int)$p['total_interno'],
            'externo' => (int)$p['total_externo'],
        ];
    }, $palestras), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

    function truncar(texto, tamanho) {
      return texto.length > tamanho ? texto.slice(0, tamanho - 1) + '…' : texto;
    }

    var ctx = document.getElementById('graficoPublico').getContext('2d');
    var grafico = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: dados.map(function (d) { return truncar(d.titulo, 28); }),
        datasets: [
          {
            label: 'Interno (Alunos)',
            data: dados.map(function (d) { return d.interno; }),
            backgroundColor: '#0056b3',
          },
          {
            label: 'Externo',
            data: dados.map(function (d) { return d.externo; }),
            backgroundColor: '#e30613',
          },
        ],
      },
      options: {
        responsive: true,
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0 } },
        },
        plugins: {
          tooltip: {
            callbacks: {
              title: function (items) { return dados[items[0].dataIndex].titulo; },
            },
          },
        },
      },
    });

    var filtro = document.getElementById('filtroPalestra');
    filtro.addEventListener('change', function () {
      var selecionado = filtro.value;
      var filtrados = selecionado === 'todas' ? dados : dados.filter(function (d) { return d.id === selecionado; });

      grafico.data.labels = filtrados.map(function (d) { return truncar(d.titulo, 28); });
      grafico.data.datasets[0].data = filtrados.map(function (d) { return d.interno; });
      grafico.data.datasets[1].data = filtrados.map(function (d) { return d.externo; });
      grafico.update();
    });
  })();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
