<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/evento.php';
require_once __DIR__ . '/includes/certificado.php';

// Volta pra home com uma mensagem no bloco "Emita seu certificado".
function voltarComErroCertificado($mensagem, $busca = '') {
    $_SESSION['certificado_erro'] = $mensagem;
    $_SESSION['certificado_busca'] = $busca;
    header('Location: /index.php#certificado');
    exit;
}

// POST vindo do formulário da home: acha a pessoa e redireciona pra URL com o código,
// assim o link que fica no navegador (e que a pessoa compartilha) não expõe e-mail
// nem matrícula.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $busca = sanitize($_POST['identificador'] ?? '');
    $identificadorLimite = 'certificado:' . ipDoCliente();

    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        voltarComErroCertificado('Sessão expirada. Atualize a página e tente novamente.', $busca);
    }
    if (estaLimitadoPorTentativas($pdo, $identificadorLimite, CERTIFICADO_MAX_BUSCAS)) {
        voltarComErroCertificado('Muitas consultas seguidas. Tente novamente em alguns minutos.', $busca);
    }
    if ($busca === '') {
        voltarComErroCertificado('Informe o e-mail usado na inscrição ou sua matrícula.');
    }
    if (certificadoSegredo() === null) {
        voltarComErroCertificado('A emissão de certificados está temporariamente indisponível. Tente novamente mais tarde.', $busca);
    }

    registrarTentativaFalha($pdo, $identificadorLimite);

    try {
        $certificado = certificadoMontar($pdo, certificadoTipoIdentificador($busca), $busca);
    } catch (PDOException $e) {
        error_log('Erro ao emitir certificado: ' . $e->getMessage());
        voltarComErroCertificado('Não foi possível emitir o certificado agora. Tente novamente em instantes.', $busca);
    }

    if (!$certificado) {
        voltarComErroCertificado('Não encontramos presença confirmada com esses dados. Confira se é o mesmo e-mail da inscrição ou tente pela matrícula.', $busca);
    }

    header('Location: /certificado.php?c=' . rawurlencode($certificado['codigo']));
    exit;
}

$codigo = $_GET['c'] ?? '';
try {
    $certificado = $codigo !== '' ? certificadoPorCodigo($pdo, $codigo) : null;
} catch (PDOException $e) {
    error_log('Erro ao abrir certificado: ' . $e->getMessage());
    $certificado = null;
}

if (!$certificado) {
    voltarComErroCertificado('Certificado não encontrado. Emita novamente informando seu e-mail ou matrícula.');
}

// Conta pras estatísticas — mas não quando é a equipe abrindo pelo painel.
if (empty($_SESSION['admin_logged'])) {
    certificadoRegistrarEmissao($pdo, $certificado);
}

$turnosComHoras = array_filter($certificado['turnos'], function ($t) { return $t['horas'] > 0; });
$rotulosTurnos = array_map(function ($t) { return 'da ' . mb_strtolower($t['rotulo'], 'UTF-8'); }, array_values($turnosComHoras));
$textoTurnos = count($rotulosTurnos) > 1
    ? implode(', ', array_slice($rotulosTurnos, 0, -1)) . ' e ' . end($rotulosTurnos)
    : $rotulosTurnos[0];
$textoTurnos = (count($rotulosTurnos) > 1 ? 'nos turnos ' : 'no turno ') . $textoTurnos;

$urlValidacao = certificadoUrlValidacao($certificado['codigo']);
$qrValidacao = 'https://quickchart.io/qr?text=' . urlencode($urlValidacao) . '&size=300&margin=1';
$assinaturas = certificadoAssinaturas();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow">
  <title>Certificado — <?= htmlspecialchars($certificado['nome']) ?> | <?= htmlspecialchars(EVENTO_NOME) ?></title>
  <link rel="icon" href="/assets/img/logo-fametro.png" type="image/png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css">
  <style>
    :root {
      --azul: #003a7a;
      --azul-escuro: #001f42;
      --vermelho: #e30613;
      --texto: #1e293b;
      --suave: #64748b;
      --linha: #dbe3ee;
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      background: #e9eef5;
      color: var(--texto);
      font-family: 'Segoe UI', system-ui, -apple-system, Roboto, sans-serif;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }

    /* ---- Barra de ações (some na impressão) ---- */
    .acoes {
      max-width: 1123px;
      margin: 0 auto;
      padding: 16px;
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
      justify-content: space-between;
    }
    .acoes .grupo { display: flex; flex-wrap: wrap; gap: 8px; }
    .btn {
      display: inline-flex; align-items: center; gap: 6px;
      border: 1px solid var(--linha); background: #fff; color: var(--texto);
      padding: 10px 16px; border-radius: 10px; font-size: 15px; font-weight: 600;
      text-decoration: none; cursor: pointer; font-family: inherit;
    }
    .btn-principal { background: var(--vermelho); border-color: var(--vermelho); color: #fff; }
    .dica { max-width: 1123px; margin: 0 auto; padding: 0 16px 12px; color: var(--suave); font-size: 14px; }

    /* ---- Folha A4 paisagem. Tudo dentro dela usa cqw (1% da largura da folha),
       então o certificado escala igual no celular, no desktop e no papel. ---- */
    .folhas { padding: 0 16px 32px; }
    .folha {
      position: relative;
      width: 100%;
      max-width: 1123px;
      aspect-ratio: 297 / 210;
      margin: 0 auto 24px;
      background: #fff;
      box-shadow: 0 10px 30px rgba(0, 31, 66, 0.15);
      overflow: hidden;
      container-type: inline-size;
    }
    .folha-verso { aspect-ratio: auto; min-height: calc(min(100vw - 32px, 1123px) * 210 / 297); }

    /* Faixas diagonais nos cantos, ecoando a arte do evento */
    .canto { position: absolute; pointer-events: none; }
    .canto-sup { top: 0; left: 0; width: 8cqw; height: 5.8cqw;
      background: linear-gradient(135deg, var(--azul-escuro), var(--azul));
      clip-path: polygon(0 0, 100% 0, 0 100%); }
    .canto-sup-faixa { top: 0; left: 0; width: 10cqw; height: 7.4cqw; background: var(--vermelho);
      clip-path: polygon(86% 0, 93% 0, 0 93%, 0 86%); }
    .canto-inf { bottom: 0; right: 0; width: 8cqw; height: 5.8cqw;
      background: linear-gradient(315deg, var(--azul-escuro), var(--azul));
      clip-path: polygon(100% 0, 100% 100%, 0 100%); }
    .canto-inf-faixa { bottom: 0; right: 0; width: 10cqw; height: 7.4cqw; background: var(--vermelho);
      clip-path: polygon(100% 7%, 100% 14%, 14% 100%, 7% 100%); }

    .moldura {
      position: absolute; inset: 2.2cqw;
      border: 0.18cqw solid var(--azul);
      outline: 0.08cqw solid rgba(0, 58, 122, 0.35);
      outline-offset: -0.7cqw;
    }

    .conteudo {
      position: absolute; inset: 4cqw 6cqw 3.6cqw;
      display: flex; flex-direction: column; align-items: center; text-align: center;
    }

    .marcas { width: 100%; display: flex; justify-content: space-between; align-items: center; }
    .marcas img { height: 5.4cqw; width: auto; }
    .selo-evento { display: flex; flex-direction: column; align-items: stretch; gap: 0.35cqw; }
    .selo-evento span {
      display: block; color: #fff; font-weight: 800; text-transform: uppercase;
      letter-spacing: 0.06cqw; border-radius: 0.6cqw; padding: 0.35cqw 1.2cqw; text-align: center;
    }
    .selo-evento .s1 { background: var(--vermelho); font-size: 1.05cqw; }
    .selo-evento .s2 { background: linear-gradient(135deg, var(--azul), var(--azul-escuro)); font-size: 1.45cqw; }

    .titulo {
      margin: 3cqw 0 0;
      font-family: Georgia, 'Times New Roman', serif;
      font-size: 5.6cqw; letter-spacing: 0.9cqw; font-weight: 700; color: var(--azul);
      line-height: 1;
    }
    .subtitulo {
      margin: 0.8cqw 0 0; font-size: 1.35cqw; letter-spacing: 0.5cqw;
      text-transform: uppercase; color: var(--vermelho); font-weight: 700;
    }

    .certificamos { margin: 3.4cqw 0 0; font-size: 1.6cqw; color: var(--suave); }
    .nome {
      margin: 0.8cqw 0 0; padding: 0 3cqw 0.6cqw;
      font-family: Georgia, 'Times New Roman', serif;
      font-size: 3cqw; font-weight: 700; color: var(--azul-escuro);
      border-bottom: 0.15cqw solid var(--vermelho);
      line-height: 1.15;
    }
    .matricula { margin: 0.6cqw 0 0; font-size: 1.2cqw; color: var(--suave); }

    .texto {
      margin: 2.4cqw auto 0; max-width: 74cqw;
      font-size: 1.65cqw; line-height: 1.65; color: var(--texto);
    }
    .texto strong { color: var(--azul-escuro); }

    .rodape {
      margin-top: auto; width: 100%;
      display: grid; grid-template-columns: 1fr 1fr 15cqw; gap: 3cqw; align-items: end;
    }
    .local-data { grid-column: 1 / 3; margin: 0 0 1.6cqw; font-size: 1.3cqw; color: var(--suave); }
    .assinatura { text-align: center; }
    .assinatura img { height: 4cqw; width: auto; display: block; margin: 0 auto -0.6cqw; }
    .assinatura .linha { border-top: 0.1cqw solid var(--texto); padding-top: 0.5cqw; }
    .assinatura .nome-ass { font-size: 1.2cqw; font-weight: 700; color: var(--texto); }
    .assinatura .cargo { font-size: 1.05cqw; color: var(--suave); }

    .validacao { text-align: center; font-size: 0.85cqw; color: var(--suave); line-height: 1.35; }
    .validacao img { width: 8.5cqw; height: 8.5cqw; display: block; margin: 0 auto 0.5cqw; }
    .validacao .codigo { font-family: ui-monospace, Menlo, Consolas, monospace; font-weight: 700; color: var(--texto); font-size: 1cqw; }

    /* ---- Verso: atividades com presença confirmada ---- */
    .verso { position: relative; padding: 4.4cqw 5.4cqw 4cqw; }
    .verso-topo { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.8cqw; }
    .verso-topo img { height: 3.6cqw; width: auto; }
    .verso h2 {
      margin: 0; font-family: Georgia, 'Times New Roman', serif;
      color: var(--azul); font-size: 2.2cqw; letter-spacing: 0.2cqw;
    }
    .verso .apoio { margin: 0.4cqw 0 0; color: var(--suave); font-size: 1.1cqw; }

    table { width: 100%; border-collapse: collapse; font-size: 1.12cqw; }
    th {
      text-align: left; background: var(--azul); color: #fff; font-weight: 700;
      padding: 0.8cqw 1cqw; font-size: 1cqw; text-transform: uppercase; letter-spacing: 0.08cqw;
    }
    td { padding: 0.55cqw 1cqw; border-bottom: 0.08cqw solid var(--linha); vertical-align: top; line-height: 1.4; }
    td.turno { font-weight: 700; color: var(--azul-escuro); white-space: nowrap; }
    td.horas { font-weight: 700; color: var(--azul-escuro); text-align: center; white-space: nowrap; }
    th.horas { text-align: center; }
    .palestrante { display: block; color: var(--suave); font-size: 0.95cqw; margin-top: 0.2cqw; }
    tr.total td { border-bottom: none; border-top: 0.18cqw solid var(--azul); font-weight: 800; color: var(--azul-escuro); font-size: 1.25cqw; }

    .verso-rodape {
      margin-top: 1.6cqw; display: flex; justify-content: space-between; gap: 2cqw;
      font-size: 0.95cqw; color: var(--suave); line-height: 1.5;
    }
    .verso-rodape .codigo { font-family: ui-monospace, Menlo, Consolas, monospace; font-weight: 700; color: var(--texto); }

    @page { size: A4 landscape; margin: 0; }
    @media print {
      body { background: #fff; }
      .acoes, .dica { display: none !important; }
      .folhas { padding: 0; }
      .folha {
        width: 297mm; max-width: none; height: 210mm; aspect-ratio: auto; min-height: 0;
        margin: 0; box-shadow: none; break-after: page; page-break-after: always;
      }
      .folha:last-child { break-after: auto; page-break-after: auto; }
    }
  </style>
</head>
<body>

  <div class="acoes">
    <a href="/index.php" class="btn"><i class="ti ti-arrow-left"></i> Voltar</a>
    <div class="grupo">
      <button type="button" class="btn" id="copiarLink" data-url="<?= htmlspecialchars($urlValidacao) ?>">
        <i class="ti ti-link"></i> <span>Copiar link de validação</span>
      </button>
      <button type="button" class="btn btn-principal" onclick="window.print()">
        <i class="ti ti-download"></i> Imprimir / Salvar PDF
      </button>
    </div>
  </div>
  <p class="dica">Para guardar o certificado, toque em <strong>Imprimir / Salvar PDF</strong> e escolha <strong>Salvar como PDF</strong>. São duas páginas: o certificado e a relação de palestras.</p>

  <main class="folhas">

    <!-- Frente -->
    <section class="folha" aria-label="Certificado">
      <div class="canto canto-sup-faixa"></div>
      <div class="canto canto-sup"></div>
      <div class="canto canto-inf-faixa"></div>
      <div class="canto canto-inf"></div>
      <div class="moldura"></div>

      <div class="conteudo">
        <div class="marcas">
          <img src="/assets/img/logo-fametro.png" alt="Centro Universitário FAMETRO">
          <div class="selo-evento" aria-label="<?= htmlspecialchars(EVENTO_NOME) ?>">
            <span class="s1">Jornada Acadêmica</span>
            <span class="s2">Imersão IA FAMETRO</span>
          </div>
        </div>

        <h1 class="titulo">CERTIFICADO</h1>
        <p class="subtitulo">de participação</p>

        <p class="certificamos">Certificamos que</p>
        <p class="nome"><?= htmlspecialchars($certificado['nome']) ?></p>
        <?php if ($certificado['matricula']): ?>
          <p class="matricula">Matrícula nº <?= htmlspecialchars($certificado['matricula']) ?></p>
        <?php endif; ?>

        <p class="texto">
          participou da <strong><?= htmlspecialchars(EVENTO_NOME) ?></strong> —
          “<?= htmlspecialchars(EVENTO_TEMA) ?>”, evento acadêmico presencial promovido pelo
          <?= htmlspecialchars(EVENTO_LOCAL_NOME) ?> em <?= htmlspecialchars(EVENTO_DATA_EXTENSO) ?>,
          com presença confirmada <?= htmlspecialchars($textoTurnos) ?>, totalizando a carga horária de
          <strong><?= htmlspecialchars(certificadoHorasPorExtenso($certificado['horas_total'])) ?></strong>.
        </p>

        <div class="rodape">
          <p class="local-data"><?= htmlspecialchars(CERTIFICADO_CIDADE_EMISSAO) ?>, <?= htmlspecialchars(EVENTO_DATA_EXTENSO) ?>.</p>
          <div class="validacao" style="grid-row: 1 / 3; grid-column: 3;">
            <img src="<?= htmlspecialchars($qrValidacao) ?>" alt="QR Code de validação do certificado">
            <span class="codigo"><?= htmlspecialchars($certificado['codigo']) ?></span><br>
            Valide em <?= htmlspecialchars(preg_replace('#^https?://#', '', SITE_URL)) ?>/validar-certificado.php
          </div>
          <?php foreach (array_slice($assinaturas, 0, 2) as $assinatura): ?>
            <div class="assinatura">
              <?php if (!empty($assinatura['imagem'])): ?>
                <img src="<?= htmlspecialchars($assinatura['imagem']) ?>" alt="">
              <?php endif; ?>
              <div class="linha">
                <?php if (!empty($assinatura['nome'])): ?>
                  <div class="nome-ass"><?= htmlspecialchars($assinatura['nome']) ?></div>
                <?php endif; ?>
                <div class="cargo"><?= htmlspecialchars($assinatura['cargo']) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- Verso -->
    <section class="folha folha-verso" aria-label="Relação de atividades">
      <div class="moldura"></div>
      <div class="verso">
        <div class="verso-topo">
          <div>
            <h2>Atividades com presença confirmada</h2>
            <p class="apoio"><?= htmlspecialchars($certificado['nome']) ?> · <?= htmlspecialchars(EVENTO_NOME) ?> · <?= htmlspecialchars(EVENTO_DATA_EXTENSO) ?></p>
          </div>
          <img src="/assets/img/logo-fametro.png" alt="FAMETRO">
        </div>

        <table>
          <thead>
            <tr>
              <th style="width: 11%;">Turno</th>
              <th>Palestra</th>
              <th style="width: 14%;">Horário</th>
              <th class="horas" style="width: 13%;">Carga horária</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($turnosComHoras as $turno): ?>
              <?php foreach ($turno['palestras'] as $indice => $palestra): ?>
                <tr>
                  <?php if ($indice === 0): ?>
                    <td class="turno" rowspan="<?= count($turno['palestras']) ?>"><?= htmlspecialchars($turno['rotulo']) ?></td>
                  <?php endif; ?>
                  <td>
                    <?= htmlspecialchars($palestra['titulo']) ?>
                    <span class="palestrante"><?= htmlspecialchars($palestra['palestrante']) ?></span>
                  </td>
                  <td><?= htmlspecialchars($palestra['horario']) ?></td>
                  <?php if ($indice === 0): ?>
                    <td class="horas" rowspan="<?= count($turno['palestras']) ?>"><?= (int) $turno['horas'] ?>h</td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
            <tr class="total">
              <td colspan="3">Carga horária total</td>
              <td class="horas"><?= (int) $certificado['horas_total'] ?>h</td>
            </tr>
          </tbody>
        </table>

        <div class="verso-rodape">
          <div>
            Carga horária de <?= (int) CERTIFICADO_HORAS_POR_TURNO ?> horas por turno com presença confirmada
            (credenciamento por QR Code na entrada das palestras), até 15 horas no total.
          </div>
          <div style="text-align: right; white-space: nowrap;">
            Código de validação: <span class="codigo"><?= htmlspecialchars($certificado['codigo']) ?></span><br>
            Emitido em <?= (new DateTime('now', new DateTimeZone('America/Manaus')))->format('d/m/Y') ?>
          </div>
        </div>
      </div>
    </section>

  </main>

  <script>
    (function () {
      var botao = document.getElementById('copiarLink');
      if (!botao) return;
      botao.addEventListener('click', function () {
        var url = botao.getAttribute('data-url');
        var rotulo = botao.querySelector('span');
        function avisar(texto) {
          rotulo.textContent = texto;
          setTimeout(function () { rotulo.textContent = 'Copiar link de validação'; }, 2000);
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(url).then(function () { avisar('Link copiado!'); }, function () { window.prompt('Copie o link:', url); });
        } else {
          window.prompt('Copie o link:', url);
        }
      });
    })();
  </script>
</body>
</html>
