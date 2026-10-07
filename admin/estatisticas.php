<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/evento.php';
require_once __DIR__ . '/../includes/certificado.php';

checarAutenticacaoAdmin();

// ---------------------------------------------------------------------------------
// Certificados: quem tem direito (calculado pela mesma regra do certificado) x quem
// já abriu o próprio certificado (tabela certificados_emitidos).
// ---------------------------------------------------------------------------------
$turnosRotulos = ['manha' => 'Manhã', 'tarde' => 'Tarde', 'noite' => 'Noite'];
$turnosBadges = ['manha' => 'bg-blue', 'tarde' => 'bg-orange', 'noite' => 'bg-purple']; // mesmas cores da home

$elegiveis = certificadoElegiveis($pdo);

$emitidos = [];
$tabelaCertificadosOk = true;
try {
    foreach ($pdo->query('SELECT chave_pessoa, codigo, primeira_emissao, ultima_visualizacao, visualizacoes FROM certificados_emitidos')->fetchAll() as $linha) {
        $emitidos[$linha['chave_pessoa']] = $linha;
    }
} catch (PDOException $e) {
    // Migração harness/db/migracoes/2026-10-07-certificados-emitidos.sql ainda não rodou.
    $tabelaCertificadosOk = false;
}

foreach ($elegiveis as $chave => $pessoa) {
    $elegiveis[$chave]['emissao'] = $emitidos[$chave] ?? null;
}

// Filtros (GET, pra o link filtrado poder ser compartilhado e exportado).
$filtroValido = function ($valor, $permitidos) {
    return in_array($valor, $permitidos, true) ? $valor : 'todos';
};
$filtros = [
    'turno' => $filtroValido($_GET['turno'] ?? 'todos', ['todos', 'manha', 'tarde', 'noite']),
    'publico' => $filtroValido($_GET['publico'] ?? 'todos', ['todos', 'aluno', 'externo']),
    'situacao' => $filtroValido($_GET['situacao'] ?? 'todos', ['todos', 'emitido', 'pendente']),
    'horas' => $filtroValido($_GET['horas'] ?? 'todos', ['todos', '5', '10', '15']),
    'busca' => trim((string) ($_GET['busca'] ?? '')),
];
$filtrosAtivos = $filtros['turno'] !== 'todos' || $filtros['publico'] !== 'todos'
    || $filtros['situacao'] !== 'todos' || $filtros['horas'] !== 'todos' || $filtros['busca'] !== '';

$buscaNormalizada = mb_strtolower($filtros['busca'], 'UTF-8');
$certificadosFiltrados = array_filter($elegiveis, function ($p) use ($filtros, $buscaNormalizada) {
    if ($filtros['turno'] !== 'todos' && $p['horas_por_turno'][$filtros['turno']] === 0) {
        return false;
    }
    if ($filtros['publico'] !== 'todos' && $p['tipo_participante'] !== $filtros['publico']) {
        return false;
    }
    if ($filtros['situacao'] === 'emitido' && !$p['emissao']) {
        return false;
    }
    if ($filtros['situacao'] === 'pendente' && $p['emissao']) {
        return false;
    }
    if ($filtros['horas'] !== 'todos' && $p['horas_total'] !== (int) $filtros['horas']) {
        return false;
    }
    if ($buscaNormalizada !== '') {
        $alvo = mb_strtolower($p['nome'] . ' ' . ($p['matricula'] ?? '') . ' ' . $p['email'], 'UTF-8');
        if (mb_strpos($alvo, $buscaNormalizada) === false) {
            return false;
        }
    }
    return true;
});

// Emitidos primeiro (mais recentes no topo), depois quem ainda não emitiu, por nome.
uasort($certificadosFiltrados, function ($a, $b) {
    if ((bool) $a['emissao'] !== (bool) $b['emissao']) {
        return $a['emissao'] ? -1 : 1;
    }
    if ($a['emissao']) {
        return strcmp($b['emissao']['primeira_emissao'], $a['emissao']['primeira_emissao']);
    }
    return strcmp($a['nome'], $b['nome']);
});

// Exportação CSV com os mesmos filtros (abre direto no Excel: BOM + ponto e vírgula).
if (($_GET['exportar'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="certificados-' . date('Y-m-d') . '.csv"');
    $saida = fopen('php://output', 'w');
    fwrite($saida, "\xEF\xBB\xBF");
    fputcsv($saida, ['Nome', 'Matrícula', 'E-mail', 'Público', 'Manhã', 'Tarde', 'Noite', 'Carga horária', 'Situação', 'Primeira emissão', 'Visualizações', 'Código'], ';');
    // Evita que o Excel interprete um nome começando com =, +, - ou @ como fórmula.
    $semFormula = function ($valor) {
        return preg_match('/^[=+\-@]/', (string) $valor) ? "'" . $valor : $valor;
    };
    foreach ($certificadosFiltrados as $p) {
        fputcsv($saida, [
            $semFormula($p['nome']),
            $semFormula($p['matricula'] ?? ''),
            $semFormula($p['email']),
            $p['tipo_participante'] === 'externo' ? 'Externo' : 'Aluno',
            $p['horas_por_turno']['manha'] ? 'Sim' : 'Não',
            $p['horas_por_turno']['tarde'] ? 'Sim' : 'Não',
            $p['horas_por_turno']['noite'] ? 'Sim' : 'Não',
            $p['horas_total'] . 'h',
            $p['emissao'] ? 'Emitido' : 'Não emitiu',
            $p['emissao'] ? date('d/m/Y H:i', strtotime($p['emissao']['primeira_emissao'])) : '',
            $p['emissao'] ? (int) $p['emissao']['visualizacoes'] : 0,
            certificadoGerarCodigo($p['tipo_busca'], $p['id_ancora'], $p['identificador']),
        ], ';');
    }
    fclose($saida);
    exit;
}

// Números do bloco de certificados (sobre o conjunto filtrado).
$totalComDireito = count($certificadosFiltrados);
$totalEmitidos = count(array_filter($certificadosFiltrados, function ($p) { return (bool) $p['emissao']; }));
$totalVisualizacoes = array_sum(array_map(function ($p) { return $p['emissao'] ? (int) $p['emissao']['visualizacoes'] : 0; }, $certificadosFiltrados));
$taxaEmissao = $totalComDireito > 0 ? round($totalEmitidos * 100 / $totalComDireito) : 0;

$porTurno = [];
foreach ($turnosRotulos as $chave => $rotulo) {
    $porTurno[$chave] = ['rotulo' => $rotulo, 'direito' => 0, 'emitidos' => 0];
}
$porCarga = [5 => ['direito' => 0, 'emitidos' => 0], 10 => ['direito' => 0, 'emitidos' => 0], 15 => ['direito' => 0, 'emitidos' => 0]];
foreach ($certificadosFiltrados as $p) {
    foreach ($p['horas_por_turno'] as $chave => $horas) {
        if ($horas > 0) {
            $porTurno[$chave]['direito']++;
            $porTurno[$chave]['emitidos'] += $p['emissao'] ? 1 : 0;
        }
    }
    if (isset($porCarga[$p['horas_total']])) {
        $porCarga[$p['horas_total']]['direito']++;
        $porCarga[$p['horas_total']]['emitidos'] += $p['emissao'] ? 1 : 0;
    }
}

$limiteLinhas = 300;
$linhasTabela = array_slice($certificadosFiltrados, 0, $limiteLinhas);
$urlExportar = '/admin/estatisticas.php?' . http_build_query(array_merge($filtros, ['exportar' => 'csv']));

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

  <!-- ============================ CERTIFICADOS ============================ -->
  <section id="certificados" class="mb-5" style="scroll-margin-top: 1rem;">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
      <div>
        <h3 class="h2 fw-bold m-0" style="color: #003a7a;"><i class="ti ti-certificate me-1"></i>Certificados</h3>
        <p class="text-muted small m-0">
          "Com direito" = presença confirmada em pelo menos <?= (int) CERTIFICADO_MIN_PALESTRAS_POR_TURNO ?> palestra(s) de um turno.
          "Emitido" = a pessoa abriu o próprio certificado pelo site (aberturas pelo painel não contam).
        </p>
      </div>
      <a href="<?= htmlspecialchars($urlExportar) ?>" class="btn btn-outline-success rounded-3 fw-bold">
        <i class="ti ti-file-spreadsheet me-1"></i> Exportar CSV<?= $filtrosAtivos ? ' (filtrado)' : '' ?>
      </a>
    </div>

    <?php if (!$tabelaCertificadosOk): ?>
      <div class="alert alert-warning rounded-3">
        <i class="ti ti-alert-triangle me-1"></i>
        A contagem de emitidos ainda não está ativa: rode no phpMyAdmin o script
        <code>harness/db/migracoes/2026-10-07-certificados-emitidos.sql</code>.
        Os números de "com direito" abaixo já estão corretos.
      </div>
    <?php endif; ?>

    <!-- Filtros: uma linha, acima de tudo que eles afetam -->
    <form method="GET" action="/admin/estatisticas.php#certificados" class="card shadow-sm border-0 rounded-3 mb-3">
      <div class="card-body p-3">
        <div class="row g-2 align-items-end">
          <div class="col-6 col-md-2">
            <label class="form-label small text-muted mb-1" for="fTurno">Turno</label>
            <select id="fTurno" name="turno" class="form-select form-select-sm">
              <option value="todos">Todos</option>
              <?php foreach ($turnosRotulos as $chave => $rotulo): ?>
                <option value="<?= $chave ?>" <?= $filtros['turno'] === $chave ? 'selected' : '' ?>><?= $rotulo ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label small text-muted mb-1" for="fPublico">Público</label>
            <select id="fPublico" name="publico" class="form-select form-select-sm">
              <option value="todos">Todos</option>
              <option value="aluno" <?= $filtros['publico'] === 'aluno' ? 'selected' : '' ?>>Alunos</option>
              <option value="externo" <?= $filtros['publico'] === 'externo' ? 'selected' : '' ?>>Externo</option>
            </select>
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label small text-muted mb-1" for="fSituacao">Situação</label>
            <select id="fSituacao" name="situacao" class="form-select form-select-sm">
              <option value="todos">Todas</option>
              <option value="emitido" <?= $filtros['situacao'] === 'emitido' ? 'selected' : '' ?>>Emitiu</option>
              <option value="pendente" <?= $filtros['situacao'] === 'pendente' ? 'selected' : '' ?>>Ainda não emitiu</option>
            </select>
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label small text-muted mb-1" for="fHoras">Carga horária</label>
            <select id="fHoras" name="horas" class="form-select form-select-sm">
              <option value="todos">Todas</option>
              <?php foreach ([5, 10, 15] as $h): ?>
                <option value="<?= $h ?>" <?= $filtros['horas'] === (string) $h ? 'selected' : '' ?>><?= $h ?>h</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-2">
            <label class="form-label small text-muted mb-1" for="fBusca">Buscar</label>
            <input id="fBusca" type="search" name="busca" class="form-control form-control-sm" placeholder="Nome, matrícula ou e-mail" value="<?= htmlspecialchars($filtros['busca']) ?>">
          </div>
          <div class="col-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm flex-grow-1 fw-bold"><i class="ti ti-filter me-1"></i>Filtrar</button>
            <?php if ($filtrosAtivos): ?>
              <a href="/admin/estatisticas.php#certificados" class="btn btn-outline-secondary btn-sm" title="Limpar filtros"><i class="ti ti-x"></i></a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </form>

    <!-- Números principais -->
    <div class="row row-cards g-3 mb-3">
      <div class="col-6 col-lg-3">
        <div class="card card-sm shadow-sm border-0 rounded-3 h-100">
          <div class="card-body p-3">
            <div class="fw-bold fs-2 text-dark"><?= $totalComDireito ?></div>
            <div class="text-muted small">Com direito a certificado</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card card-sm shadow-sm border-0 rounded-3 h-100">
          <div class="card-body p-3">
            <div class="fw-bold fs-2" style="color: #003a7a;"><?= $totalEmitidos ?></div>
            <div class="text-muted small">Certificados emitidos</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card card-sm shadow-sm border-0 rounded-3 h-100">
          <div class="card-body p-3">
            <div class="fw-bold fs-2 text-dark"><?= $taxaEmissao ?>%</div>
            <div class="text-muted small">Taxa de emissão · <?= $totalComDireito - $totalEmitidos ?> ainda não emitiram</div>
            <div class="progress progress-sm mt-2" style="height: 6px;" role="progressbar" aria-label="Taxa de emissão" aria-valuenow="<?= $taxaEmissao ?>" aria-valuemin="0" aria-valuemax="100">
              <div class="progress-bar" style="width: <?= $taxaEmissao ?>%; background-color: #003a7a;"></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card card-sm shadow-sm border-0 rounded-3 h-100">
          <div class="card-body p-3">
            <div class="fw-bold fs-2 text-dark"><?= $totalVisualizacoes ?></div>
            <div class="text-muted small">Aberturas de certificado</div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <!-- Por turno -->
      <div class="col-lg-8">
        <div class="card shadow-sm border-0 rounded-4 h-100">
          <div class="card-header bg-white border-bottom py-3">
            <h4 class="card-title fw-bold m-0" style="color: #003a7a;">Por turno: com direito x emitidos</h4>
          </div>
          <div class="card-body">
            <?php if ($totalComDireito === 0): ?>
              <p class="text-muted text-center py-4 m-0">Nenhum participante com direito a certificado <?= $filtrosAtivos ? 'nesse filtro' : 'ainda' ?>.</p>
            <?php else: ?>
              <canvas id="graficoCertificadosTurno" height="110" aria-label="Certificados por turno" role="img"></canvas>
              <table class="table table-sm mt-3 mb-0 small">
                <thead><tr><th>Turno</th><th class="text-end">Com direito</th><th class="text-end">Emitidos</th><th class="text-end">Taxa</th></tr></thead>
                <tbody>
                  <?php foreach ($porTurno as $t): ?>
                    <tr>
                      <td><?= $t['rotulo'] ?></td>
                      <td class="text-end"><?= $t['direito'] ?></td>
                      <td class="text-end fw-bold"><?= $t['emitidos'] ?></td>
                      <td class="text-end text-muted"><?= $t['direito'] ? round($t['emitidos'] * 100 / $t['direito']) . '%' : '—' ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Por carga horária -->
      <div class="col-lg-4">
        <div class="card shadow-sm border-0 rounded-4 h-100">
          <div class="card-header bg-white border-bottom py-3">
            <h4 class="card-title fw-bold m-0" style="color: #003a7a;">Por carga horária</h4>
          </div>
          <div class="list-group list-group-flush">
            <?php foreach ($porCarga as $horas => $c): ?>
              <?php $pct = $c['direito'] ? round($c['emitidos'] * 100 / $c['direito']) : 0; ?>
              <div class="list-group-item p-3">
                <div class="d-flex justify-content-between align-items-baseline">
                  <span class="fw-bold fs-3 text-dark"><?= $horas ?>h</span>
                  <span class="small text-muted"><strong class="text-dark"><?= $c['emitidos'] ?></strong> emitidos de <?= $c['direito'] ?></span>
                </div>
                <div class="progress mt-2" style="height: 6px;" role="progressbar" aria-label="Emitidos com <?= $horas ?> horas" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
                  <div class="progress-bar" style="width: <?= $pct ?>%; background-color: #003a7a;"></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Lista -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
      <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h4 class="card-title fw-bold m-0" style="color: #003a7a;">Participantes com direito</h4>
        <span class="text-muted small">
          <?= $totalComDireito ?> pessoa(s)<?= $totalComDireito > $limiteLinhas ? ' · mostrando as ' . $limiteLinhas . ' primeiras (o CSV traz todas)' : '' ?>
        </span>
      </div>
      <div class="table-responsive">
        <table class="table card-table table-vcenter align-middle mb-0">
          <thead class="bg-light">
            <tr>
              <th>Participante</th>
              <th>Turnos</th>
              <th class="text-center">Horas</th>
              <th>Situação</th>
              <th class="text-end pe-3">Certificado</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$linhasTabela): ?>
              <tr><td colspan="5" class="text-center text-muted py-4">Nenhum participante encontrado com esses filtros.</td></tr>
            <?php endif; ?>
            <?php foreach ($linhasTabela as $p): ?>
              <?php $codigoLinha = certificadoGerarCodigo($p['tipo_busca'], $p['id_ancora'], $p['identificador']); ?>
              <tr>
                <td>
                  <div class="fw-bold text-dark"><?= htmlspecialchars($p['nome']) ?></div>
                  <div class="small text-muted">
                    <?= $p['matricula'] ? 'Mat. ' . htmlspecialchars($p['matricula']) . ' · ' : '' ?><?= htmlspecialchars($p['email']) ?>
                    <?php if ($p['tipo_participante'] === 'externo'): ?>
                      <span class="badge bg-light text-dark border ms-1">Externo</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <?php foreach ($p['horas_por_turno'] as $chave => $horas): ?>
                    <?php if ($horas > 0): ?>
                      <span class="badge <?= $turnosBadges[$chave] ?> text-white me-1"><?= $turnosRotulos[$chave] ?></span>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </td>
                <td class="text-center fw-bold"><?= (int) $p['horas_total'] ?>h</td>
                <td>
                  <?php if ($p['emissao']): ?>
                    <span class="badge bg-success text-white"><i class="ti ti-check me-1"></i>Emitido</span>
                    <div class="small text-muted mt-1">
                      <?= date('d/m/Y H:i', strtotime($p['emissao']['primeira_emissao'])) ?>
                      · <?= (int) $p['emissao']['visualizacoes'] ?> abertura(s)
                    </div>
                  <?php else: ?>
                    <span class="badge bg-light text-dark border"><i class="ti ti-clock me-1"></i>Ainda não emitiu</span>
                  <?php endif; ?>
                </td>
                <td class="text-end pe-3">
                  <?php if ($codigoLinha): ?>
                    <a href="/certificado.php?c=<?= rawurlencode($codigoLinha) ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-3 fw-bold">
                      <i class="ti ti-external-link me-1"></i> Abrir
                    </a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <h3 class="h2 fw-bold mb-3" style="color: #003a7a;"><i class="ti ti-users me-1"></i>Inscrições</h3>

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

<?php if ($totalComDireito > 0): ?>
<?php if (empty($palestras)): // senão o Chart.js já veio com o gráfico de inscrições ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<?php endif; ?>
<script>
  (function () {
    var turnos = <?= json_encode(array_values($porTurno), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    new Chart(document.getElementById('graficoCertificadosTurno').getContext('2d'), {
      type: 'bar',
      data: {
        labels: turnos.map(function (t) { return t.rotulo; }),
        datasets: [
          // Cinza = total possível; azul da marca = o que importa (emitidos).
          { label: 'Com direito', data: turnos.map(function (t) { return t.direito; }), backgroundColor: '#cbd5e1', borderRadius: 4, borderSkipped: 'start' },
          { label: 'Emitidos', data: turnos.map(function (t) { return t.emitidos; }), backgroundColor: '#003a7a', borderRadius: 4, borderSkipped: 'start' },
        ],
      },
      options: {
        responsive: true,
        datasets: { bar: { categoryPercentage: 0.6, barPercentage: 0.9 } },
        scales: {
          x: { grid: { display: false } },
          y: { beginAtZero: true, ticks: { precision: 0, color: '#64748b' }, grid: { color: '#eef2f7' }, border: { display: false } },
        },
        plugins: {
          legend: { position: 'top', align: 'end', labels: { boxWidth: 12, boxHeight: 12, color: '#334155' } },
          tooltip: {
            callbacks: {
              afterBody: function (items) {
                var t = turnos[items[0].dataIndex];
                return t.direito ? 'Taxa de emissão: ' + Math.round(t.emitidos * 100 / t.direito) + '%' : '';
              },
            },
          },
        },
      },
    });
  })();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
