<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/evento.php';

// Buscar todas as palestras cadastradas ordenadas pelo horário
try {
    $stmt = $pdo->query("SELECT * FROM palestras ORDER BY horario_inicio ASC");
    $palestras = $stmt->fetchAll();
} catch (PDOException $e) {
    $palestras = [];
}

// Erro da última tentativa de emissão (certificado.php redireciona pra cá).
$certificadoErro = $_SESSION['certificado_erro'] ?? '';
$certificadoBusca = $_SESSION['certificado_busca'] ?? '';
unset($_SESSION['certificado_erro'], $_SESSION['certificado_busca']);

$pageTitle = 'Jornada Acadêmica Imersão IA FAMETRO — Emita seu Certificado';
$pageDescription = 'Participou da Jornada Acadêmica Imersão IA FAMETRO em 2 de outubro? Emita seu certificado com até 15 horas complementares informando seu e-mail ou matrícula.';

require_once __DIR__ . '/includes/header.php';
?>

<style>
  .card-palestra {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
  }
  /* Só em dispositivos com mouse: no iPhone o :hover "gruda" depois do toque
     e o card fica levantado/deslocado até tocar em outro lugar. */
  @media (hover: hover) {
    .card-palestra:hover {
      transform: translateY(-4px);
      box-shadow: 0 10px 25px rgba(0, 58, 122, 0.12) !important;
    }
  }
  .certificado-form .form-control {
    font-size: 1.05rem;
  }
  .btn-emitir {
    background-color: #e30613;
    border-color: #e30613;
    white-space: nowrap;
  }
  .btn-emitir:hover {
    background-color: #c00410 !important;
    border-color: #c00410 !important;
    box-shadow: 0 4px 12px rgba(227, 6, 19, 0.3) !important;
  }
</style>

<div class="container-xl py-4">

  <!-- BANNER PRINCIPAL + EMISSÃO DE CERTIFICADO -->
  <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5 text-white" style="background: linear-gradient(135deg, #003a7a 0%, #001f42 100%);">
    <div class="card-body p-4 p-md-5 text-center">

      <!-- Badges Superiores -->
      <div class="d-flex flex-wrap align-items-center justify-content-center gap-2 mb-3">
        <span class="badge bg-danger text-white text-uppercase px-3 py-2 rounded-pill fs-6 fw-bold shadow-sm">
          <i class="ti ti-calendar-check me-1"></i> Realizado em 2 de Outubro
        </span>
        <span class="badge bg-white text-dark px-3 py-2 rounded-pill fs-6 fw-semibold shadow-sm">
          <i class="ti ti-certificate me-1 text-primary"></i> Certificados disponíveis
        </span>
      </div>

      <!-- Título Principal -->
      <h1 class="fw-bold mb-2 text-white text-uppercase" style="font-size: clamp(1.6rem, 6vw, 2.3rem); letter-spacing: -0.5px; overflow-wrap: break-word;">
        Jornada Acadêmica <span style="color: #ff4d5a;">Imersão IA FAMETRO</span>
      </h1>

      <!-- Descrição -->
      <p class="fs-5 text-white-50 mb-4 mx-auto" style="max-width: 650px; line-height: 1.4;">
        <?= htmlspecialchars(EVENTO_TEMA) ?>.
      </p>

      <!-- Emissão do certificado -->
      <div id="certificado" class="card border-0 rounded-4 shadow text-start mx-auto mb-4" style="max-width: 640px; scroll-margin-top: 1rem;">
        <div class="card-body p-4">
          <h2 class="h2 fw-bold mb-1" style="color: #003a7a;">
            <i class="ti ti-certificate me-1" style="color: #e30613;"></i> Emita seu certificado
          </h2>
          <p class="text-muted mb-3">Informe o <strong>e-mail usado na inscrição</strong> ou sua <strong>matrícula</strong>. O certificado sai com as horas de todos os turnos em que sua presença foi confirmada.</p>

          <?php if ($certificadoErro !== ''): ?>
            <div class="alert alert-warning rounded-3 mb-3" role="alert">
              <i class="ti ti-alert-circle me-1"></i> <?= htmlspecialchars($certificadoErro) ?>
            </div>
          <?php endif; ?>

          <form action="/certificado.php" method="POST" class="certificado-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(gerarTokenCSRF()) ?>">
            <label for="identificador" class="form-label fw-bold text-dark">E-mail ou matrícula</label>
            <div class="d-flex flex-wrap gap-2">
              <input type="text" id="identificador" name="identificador" class="form-control rounded-3 flex-grow-1"
                     style="min-width: 0; flex-basis: 240px;"
                     placeholder="seuemail@exemplo.com ou 202310123"
                     autocomplete="email" autocapitalize="off" spellcheck="false" required
                     value="<?= htmlspecialchars($certificadoBusca) ?>">
              <button type="submit" class="btn btn-danger btn-emitir rounded-3 px-4 py-2 fw-bold text-uppercase shadow-sm flex-grow-1 flex-sm-grow-0">
                <i class="ti ti-file-certificate me-1"></i> Emitir certificado
              </button>
            </div>
          </form>

          <p class="small text-muted mb-0 mt-3">
            Recebeu um certificado e quer conferir se é autêntico?
            <a href="/validar-certificado.php" class="fw-semibold">Validar certificado</a>
          </p>
        </div>
      </div>

      <!-- Destaques Centralizados -->
      <div class="d-flex flex-wrap align-items-center justify-content-center gap-3 pt-3 border-top border-white-10">
        <div class="d-flex align-items-center bg-black bg-opacity-25 px-3 py-2 rounded-3 text-white border border-white-10">
          <i class="ti ti-clock fs-2 me-2 text-warning"></i>
          <div class="text-start">
            <span class="d-block fw-bold fs-6">Até 15h Complementares</span>
            <small class="text-white-50"><?= (int) CERTIFICADO_HORAS_POR_TURNO ?> horas por turno com presença</small>
          </div>
        </div>

        <div class="d-flex align-items-center bg-black bg-opacity-25 px-3 py-2 rounded-3 text-white border border-white-10">
          <i class="ti ti-qrcode fs-2 me-2 text-info"></i>
          <div class="text-start">
            <span class="d-block fw-bold fs-6">Certificado com validação</span>
            <small class="text-white-50">Código e QR Code de autenticidade</small>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- Título da Programação -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="h3 text-primary m-0 fw-bold" style="color: #003a7a !important;">Programação das Palestras</h2>
      <p class="text-muted m-0 small">As palestras que fizeram parte da Jornada, em <?= EVENTO_DATA_EXTENSO ?>.</p>
    </div>
  </div>

  <!-- Grade de Palestras -->
  <?php if (empty($palestras)): ?>
    <div class="alert alert-info d-flex align-items-center shadow-sm rounded-4 p-4" role="alert">
      <i class="ti ti-info-circle me-3 fs-1 text-primary"></i>
      <div class="fs-6">
        Nenhuma palestra cadastrada até o momento. Utilize o painel administrativo para adicionar as palestras.
      </div>
    </div>
  <?php else: ?>
    <div class="row row-cards g-4">
      <?php foreach ($palestras as $palestra): ?>
        <?php
          $turno = turnoDaPalestra($palestra['horario_inicio']);
          $turnoLabel = $turno['rotulo'];
          $badgeClass = $turno['badge'];

          // Tratar foto do palestrante
          $nomeFoto = $palestra['foto'] ?? $palestra['imagem'] ?? '';
          $caminhoArquivo = __DIR__ . '/uploads/palestrantes/' . $nomeFoto;
          $temFoto = !empty($nomeFoto) && file_exists($caminhoArquivo);
          $fotoUrl = '/uploads/palestrantes/' . htmlspecialchars($nomeFoto);
        ?>
        <div class="col-md-6 col-lg-4">
          <div class="card card-palestra h-100 shadow-sm border-0 rounded-4 overflow-hidden d-flex flex-column" style="background: #ffffff;">
            
            <div class="card-body p-4 d-flex flex-column">
              
              <!-- Cabeçalho do Card: Foto do Palestrante (90x90px) -->
              <div class="d-flex align-items-center gap-3 mb-3">
                <div class="flex-shrink-0">
                  <?php if ($temFoto): ?>
                    <img src="<?= $fotoUrl ?>" 
                         alt="<?= htmlspecialchars($palestra['palestrante']) ?>" 
                         class="rounded-circle border border-3 border-primary shadow-sm"
                         style="width: 90px; height: 90px; object-fit: cover;">
                  <?php else: ?>
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center text-muted border border-2 shadow-sm" 
                         style="width: 90px; height: 90px;">
                      <i class="ti ti-user fs-1 text-secondary"></i>
                    </div>
                  <?php endif; ?>
                </div>

                <div class="flex-grow-1">
                  <div class="mb-1">
                    <span class="badge <?= $badgeClass ?> text-white me-1 px-2 py-1 rounded-2">
                      <?= $turnoLabel ?>
                    </span>
                    <span class="badge bg-light text-secondary border px-2 py-1 rounded-2">
                      <i class="ti ti-clock me-1"></i>
                      <?= date('H:i', strtotime($palestra['horario_inicio'])) ?> - <?= date('H:i', strtotime($palestra['horario_fim'])) ?>
                    </span>
                  </div>
                  <div class="fw-bold text-dark fs-5 text-uppercase">
                    <?= htmlspecialchars($palestra['palestrante']) ?>
                  </div>
                  <small class="text-muted d-block">Palestrante Convidado</small>
                </div>
              </div>

              <!-- Título da Palestra -->
              <h3 class="card-title text-primary fw-bold mb-2 fs-5" style="line-height: 1.35; color: #003a7a !important;">
                <?= htmlspecialchars($palestra['titulo']) ?>
              </h3>

              <!-- Descrição -->
              <p class="card-text text-muted mb-0 flex-grow-1 small" style="line-height: 1.5;">
                <?= nl2br(htmlspecialchars($palestra['descricao'])) ?>
              </p>

            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Sobre o evento + Perguntas frequentes. Texto visível (e não só em JSON-LD) de
       propósito: é daqui que Google e buscas por IA tiram as respostas. -->
  <section class="mt-5" id="sobre" aria-labelledby="tituloSobre">
    <h2 id="tituloSobre" class="h3 fw-bold mb-2" style="color: #003a7a;">Sobre a <?= EVENTO_NOME ?></h2>
    <p class="text-muted mb-4" style="max-width: 820px;">
      <?= htmlspecialchars(EVENTO_RESUMO) ?>
      Aconteceu em <strong><?= EVENTO_DATA_EXTENSO ?></strong>, no <?= htmlspecialchars(eventoLocalTexto()) ?>.
    </p>

    <h3 class="h4 fw-bold mb-3" style="color: #003a7a;">Perguntas frequentes</h3>
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
      <div class="list-group list-group-flush">
        <?php foreach (eventoPerguntasFrequentes() as $faq): ?>
          <details class="list-group-item p-3 faq-item">
            <summary class="fw-bold text-dark"><?= htmlspecialchars($faq['pergunta']) ?></summary>
            <p class="text-muted mt-2 mb-0"><?= htmlspecialchars($faq['resposta']) ?></p>
          </details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</div>

<?php
// Dados estruturados (Schema.org) num único @graph: a Jornada como Event "pai", cada
// palestra como subEvent, a organização e o FAQ. Isso é o que Google (rich results) e
// buscas por IA usam pra entender o evento sem depender do layout da página.
// Os campos do banco vêm com htmlspecialchars() aplicado na inserção, então passam
// por textoPuro(); JSON_HEX_TAG impede que algum "</script>" feche a tag.
$organizacaoLd = [
    '@type' => 'Organization',
    '@id' => SITE_URL . '/#organizacao',
    'name' => 'Centro Universitário FAMETRO',
    'url' => SITE_URL,
    'logo' => SITE_URL . '/assets/img/logo-fametro.png',
];

$palestrasLd = [];
foreach ($palestras as $palestraLd) {
    $imagemLd = !empty($palestraLd['foto']) && file_exists(__DIR__ . '/uploads/palestrantes/' . $palestraLd['foto'])
        ? SITE_URL . '/uploads/palestrantes/' . rawurlencode($palestraLd['foto'])
        : $pageImage;

    $palestrasLd[] = [
        '@type' => 'Event',
        'name' => textoPuro($palestraLd['titulo']),
        'description' => textoPuro($palestraLd['descricao']),
        'startDate' => EVENTO_DATA . 'T' . $palestraLd['horario_inicio'] . '-03:00',
        'endDate' => EVENTO_DATA . 'T' . $palestraLd['horario_fim'] . '-03:00',
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'eventStatus' => 'https://schema.org/EventScheduled',
        'inLanguage' => 'pt-BR',
        'isAccessibleForFree' => true,
        'image' => [$imagemLd],
        'location' => eventoLocalSchema(),
        'performer' => ['@type' => 'Person', 'name' => textoPuro($palestraLd['palestrante'])],
        'organizer' => ['@id' => SITE_URL . '/#organizacao'],
    ];
}

$jornadaLd = [
    '@type' => 'Event',
    '@id' => SITE_URL . '/#evento',
    'name' => EVENTO_NOME,
    'description' => EVENTO_RESUMO,
    'url' => SITE_URL . '/index.php',
    'startDate' => $palestrasLd ? min(array_column($palestrasLd, 'startDate')) : EVENTO_DATA,
    'endDate' => $palestrasLd ? max(array_column($palestrasLd, 'endDate')) : EVENTO_DATA,
    'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
    'eventStatus' => 'https://schema.org/EventScheduled',
    'inLanguage' => 'pt-BR',
    'isAccessibleForFree' => true,
    'image' => [$pageImage],
    'location' => eventoLocalSchema(),
    'organizer' => ['@id' => SITE_URL . '/#organizacao'],
    'subEvent' => $palestrasLd,
];

$faqLd = [
    '@type' => 'FAQPage',
    'mainEntity' => array_map(function ($faq) {
        return [
            '@type' => 'Question',
            'name' => $faq['pergunta'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['resposta']],
        ];
    }, eventoPerguntasFrequentes()),
];

$grafoLd = [
    '@context' => 'https://schema.org',
    '@graph' => [$organizacaoLd, $jornadaLd, $faqLd],
];
?>
<script type="application/ld+json"><?= json_encode($grafoLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
