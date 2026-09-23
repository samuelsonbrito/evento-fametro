<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

checarAutenticacaoAdmin();

$palestra_id = filter_input(INPUT_GET, 'palestra_id', FILTER_VALIDATE_INT);

if (!$palestra_id) {
    die("Por favor, selecione uma palestra válida para gerar as declarações em lote.");
}

// Procurar dados da palestra
$stmtPalestra = $pdo->prepare("SELECT * FROM palestras WHERE id = ?");
$stmtPalestra->execute([$palestra_id]);
$palestra = $stmtPalestra->fetch(PDO::FETCH_ASSOC);

if (!$palestra) {
    die("Palestra não encontrada.");
}

// Procurar inscritos com presença confirmada nesta palestra
$sql = "SELECT * FROM inscricoes 
        WHERE palestra_id = ? AND (presenca_confirmada = 1 OR presente = 1) 
        ORDER BY nome_aluno ASC";
$stmtInscritos = $pdo->prepare($sql);
$stmtInscritos->execute([$palestra_id]);
$inscritos = $stmtInscritos->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="robots" content="noindex, nofollow">
  <title>Declarações em Lote - <?= htmlspecialchars($palestra['titulo']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
  <style>
    :root {
      --fametro-blue: #003399;
      --fametro-red: #cc0000;
      --fametro-dark: #1a1a1a;
    }

    body {
      background-color: #f3f4f6;
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
      color: var(--fametro-dark);
    }

    .declaracao-card {
      background: #ffffff;
      border-radius: 16px;
      padding: 50px 60px;
      position: relative;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(0,0,0,0.08);
      border: 1px solid #e5e7eb;
      margin-bottom: 40px;
    }

    .declaracao-card::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 8px;
      background: linear-gradient(90deg, var(--fametro-blue) 70%, var(--fametro-red) 70%);
    }

    .corner-decoration {
      position: absolute;
      width: 100px;
      height: 100px;
      border: 2px solid rgba(0, 51, 153, 0.1);
      pointer-events: none;
    }
    .top-left { top: 20px; left: 20px; border-right: none; border-bottom: none; }
    .bottom-right { bottom: 20px; right: 20px; border-left: none; border-top: none; }

    .logo-img {
      max-height: 85px;
      width: auto;
      object-fit: contain;
    }

    .titulo-declaracao {
      color: var(--fametro-blue);
      font-weight: 800;
      letter-spacing: 2px;
      font-size: 1.8rem;
      text-transform: uppercase;
      position: relative;
      display: inline-block;
    }

    .titulo-declaracao::after {
      content: "";
      display: block;
      width: 60px;
      height: 4px;
      background: var(--fametro-red);
      margin: 10px auto 0;
      border-radius: 2px;
    }

    .nome-participante {
      color: var(--fametro-blue);
      font-size: 1.5rem;
      font-weight: 700;
      border-bottom: 2px solid #e5e7eb;
      padding-bottom: 2px;
    }

    .titulo-palestra {
      background: #f8fafc;
      border-left: 4px solid var(--fametro-blue);
      padding: 18px 24px;
      border-radius: 0 12px 12px 0;
      color: var(--fametro-dark);
      font-size: 1.25rem;
      font-weight: 600;
      margin: 20px 0;
    }

    .carga-horaria-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background-color: #e0e7ff;
      color: var(--fametro-blue);
      font-weight: 700;
      padding: 8px 20px;
      border-radius: 50px;
      font-size: 1rem;
      border: 1px solid #c7d2fe;
    }

    .selo-autenticidade {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #f0fdf4;
      color: #166534;
      border: 1px solid #bbf7d0;
      padding: 6px 16px;
      border-radius: 50px;
      font-size: 0.85rem;
      font-weight: 600;
    }

    @media print {
      .no-print { display: none !important; }
      body { background: white !important; padding: 0 !important; }
      .container { max-width: 100% !important; width: 100% !important; padding: 0 !important; }
      .declaracao-card {
        box-shadow: none !important;
        border: 2px solid var(--fametro-blue) !important;
        page-break-after: always;
        break-after: page;
        margin: 0 !important;
        padding: 40px !important;
        border-radius: 0 !important;
      }
      .declaracao-card:last-child {
        page-break-after: auto;
        break-after: auto;
      }
    }
  </style>
</head>
<body class="py-4">

  <div class="container no-print mb-4">
    <div class="d-flex justify-content-between align-items-center bg-white p-3 rounded-4 shadow-sm border">
      <div>
        <h5 class="fw-bold mb-0 text-dark">Emissão em Lote</h5>
        <small class="text-muted">Palestra: <strong><?= htmlspecialchars($palestra['titulo']) ?></strong></small>
      </div>
      <div class="d-flex align-items-center gap-3">
        <span class="badge bg-primary fs-6 px-3 py-2 rounded-pill">
          <?= count($inscritos) ?> Declarações Geradas
        </span>
        <button onclick="window.print()" class="btn btn-danger fw-bold rounded-3 px-4">
          <i class="ti ti-printer me-2"></i> Imprimir / Salvar PDF
        </button>
      </div>
    </div>
  </div>

  <div class="container">
    <?php if (empty($inscritos)): ?>
      <div class="alert alert-warning text-center my-5 p-5 rounded-4 shadow-sm">
        <i class="ti ti-alert-circle fs-1 text-warning d-block mb-3"></i>
        <h4>Nenhuma presença confirmada encontrada para esta palestra.</h4>
        <p class="mb-0 text-muted">Apenas participantes com presença confirmada serão incluídos na impressão em lote.</p>
      </div>
    <?php else: ?>
      <?php foreach ($inscritos as $index => $aluno): ?>
        <div class="declaracao-card text-center my-3">
          
          <div class="corner-decoration top-left"></div>
          <div class="corner-decoration bottom-right"></div>

          <!-- Logo Centralizado -->
          <div class="mb-4">
            <img src="/evento-fametro/assets/img/logo-fametro.png" alt="FAMETRO" class="logo-img">
          </div>

          <h2 class="titulo-declaracao mb-4">Declaração de Participação</h2>

          <p class="fs-5 lh-lg my-3 text-secondary">
            Declaramos para os devidos fins que <br>
            <span class="nome-participante text-dark"><?= htmlspecialchars($aluno['nome_aluno']) ?></span>
            <?php if (!empty($aluno['matricula'])): ?>
              <br><small class="text-muted fs-6">(Matrícula: <strong><?= htmlspecialchars($aluno['matricula']) ?></strong>)</small>
            <?php endif; ?>
          </p>

          <p class="fs-6 text-muted mb-2">participou ativamente da atividade acadêmica com a palestra:</p>

          <div class="titulo-palestra text-center mx-auto" style="max-width: 800px;">
            "<?= htmlspecialchars($palestra['titulo']) ?>"
          </div>

          <!-- Destaque da Carga Horária -->
          <div class="my-3">
            <div class="carga-horaria-badge">
              <i class="ti ti-clock me-1"></i> Carga Horária: 1 Hora e 40 Minutos (1h 40m)
            </div>
          </div>

          <div class="my-3">
            <div class="selo-autenticidade">
              <i class="ti ti-circle-check-filled"></i> Presença Confirmada no Sistema
            </div>
          </div>

          <!-- Rodapé de Validação -->
          <div class="mt-4 pt-4 border-top w-75 mx-auto d-flex justify-content-between align-items-end">
            <div class="text-start">
              <small class="text-muted d-block fw-bold">CENTRO UNIVERSITÁRIO FAMETRO</small>
              <small class="text-muted">Coordenação de Eventos Acadêmicos</small>
            </div>
            <div class="text-end">
              <small class="text-muted d-block">Código de Validação:</small>
              <span class="font-monospace fw-bold text-dark"><?= htmlspecialchars($aluno['codigo_qrcode']) ?></span>
            </div>
          </div>

        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</body>
</html>
