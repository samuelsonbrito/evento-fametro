<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Buscar todas as palestras cadastradas ordenadas pelo horário
try {
    $stmt = $pdo->query("SELECT * FROM palestras ORDER BY horario_inicio ASC");
    $palestras = $stmt->fetchAll();
} catch (PDOException $e) {
    $palestras = [];
}

$pageTitle = 'Jornada Acadêmica Imersão FAMETRO — 2 de Outubro | Inscrições Abertas';
$pageDescription = 'Inscreva-se gratuitamente na Jornada Acadêmica Imersão FAMETRO: palestras sobre Inteligência Artificial, até 15h de horas complementares e credenciamento por QR Code. Vagas limitadas.';

require_once __DIR__ . '/includes/header.php';
?>

<style>
  .card-palestra {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
  }
  .card-palestra:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(0, 58, 122, 0.12) !important;
  }
  .btn-inscrever {
    background-color: #e30613;
    border-color: #e30613;
    transition: all 0.2s ease-in-out;
  }
  .btn-inscrever:hover {
    background-color: #c00410 !important;
    border-color: #c00410 !important;
    box-shadow: 0 4px 12px rgba(227, 6, 19, 0.3) !important;
  }
</style>

<div class="container-xl py-4">

  <!-- BANNER PRINCIPAL CENTRALIZADO -->
  <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 text-white" style="background: linear-gradient(135deg, #003a7a 0%, #001f42 100%);">
    <div class="card-body p-4 p-md-5 text-center">
      
      <!-- Badges Superiores -->
      <div class="d-flex align-items-center justify-content-center gap-2 mb-3">
        <span class="badge bg-danger text-white text-uppercase px-3 py-2 rounded-pill fs-6 fw-bold shadow-sm">
          <i class="ti ti-calendar me-1"></i> 2 de Outubro
        </span>
        <span class="badge bg-white text-dark px-3 py-2 rounded-pill fs-6 fw-semibold shadow-sm">
          <i class="ti ti-map-pin me-1 text-primary"></i> Evento Presencial
        </span>
      </div>

      <!-- Título Principal -->
      <h1 class="fw-bold mb-2 text-white text-uppercase" style="font-size: 2.3rem; letter-spacing: -0.5px;">
        Jornada Acadêmica <span style="color: #ff4d5a;">Imersão IA FAMETRO</span>
      </h1>

      <!-- Descrição -->
      <p class="fs-5 text-white-50 mb-4 mx-auto" style="max-width: 650px; line-height: 1.4;">
        Descomplicando a Inteligência Artificial, da curiosidade à carreira.
      </p>

      <!-- Destaques Centralizados -->
      <div class="d-flex flex-wrap align-items-center justify-content-center gap-3 pt-3 border-top border-white-10">
        <div class="d-flex align-items-center bg-black bg-opacity-25 px-3 py-2 rounded-3 text-white border border-white-10">
          <i class="ti ti-clock fs-2 me-2 text-warning"></i>
          <div class="text-start">
            <span class="d-block fw-bold fs-6">Até 15h Complementares</span>
            <small class="text-white-50">5 horas por turno</small>
          </div>
        </div>

        <div class="d-flex align-items-center bg-black bg-opacity-25 px-3 py-2 rounded-3 text-white border border-white-10">
          <i class="ti ti-qrcode fs-2 me-2 text-info"></i>
          <div class="text-start">
            <span class="d-block fw-bold fs-6">Credenciamento via QR Code</span>
            <small class="text-white-50">Validação instantânea</small>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- Título da Programação -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="h3 text-primary m-0 fw-bold" style="color: #003a7a !important;">Programação das Palestras</h2>
      <p class="text-muted m-0 small">Escolha a palestra desejada e garanta sua vaga.</p>
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
          // Determinar o turno
          $horaInicio = (int) date('H', strtotime($palestra['horario_inicio']));
          if ($horaInicio < 12) {
              $turnoLabel = 'Manhã';
              $badgeClass = 'bg-blue';
          } elseif ($horaInicio < 18) {
              $turnoLabel = 'Tarde';
              $badgeClass = 'bg-orange';
          } else {
              $turnoLabel = 'Noite';
              $badgeClass = 'bg-purple';
          }

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
                  <div class="fw-bold text-dark fs-5">
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
              <p class="card-text text-muted mb-4 flex-grow-1 small" style="line-height: 1.5;">
                <?= nl2br(htmlspecialchars($palestra['descricao'])) ?>
              </p>

              <!-- Botão de Inscrição -->
              <div class="pt-3 border-top mt-auto">
                <a href="/cadastro.php?palestra_id=<?= $palestra['id'] ?>" 
                   class="btn btn-danger btn-inscrever w-100 rounded-3 py-2 fw-bold text-uppercase shadow-sm d-flex align-items-center justify-content-center gap-2">
                  <i class="ti ti-edit fs-5"></i> Inscrever-se
                </a>
              </div>

            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php
// Dados estruturados (Schema.org/Event) — um bloco por palestra, pra habilitar rich
// results de evento no Google. titulo/palestrante/descricao já vêm com
// htmlspecialchars() aplicado na inserção (ver sanitize() em includes/functions.php),
// então não há risco de fechar a tag <script> com conteúdo vindo do banco.
foreach ($palestras as $palestraLd):
    $imagemLd = !empty($palestraLd['foto']) && file_exists(__DIR__ . '/uploads/palestrantes/' . $palestraLd['foto'])
        ? SITE_URL . '/uploads/palestrantes/' . rawurlencode($palestraLd['foto'])
        : $pageImage;

    $eventoLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => $palestraLd['titulo'],
        'description' => $palestraLd['descricao'],
        'startDate' => EVENTO_DATA . 'T' . $palestraLd['horario_inicio'] . '-03:00',
        'endDate' => EVENTO_DATA . 'T' . $palestraLd['horario_fim'] . '-03:00',
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'eventStatus' => 'https://schema.org/EventScheduled',
        'image' => [$imagemLd],
        'location' => [
            '@type' => 'Place',
            'name' => 'Centro Universitário FAMETRO',
        ],
        'performer' => [
            '@type' => 'Person',
            'name' => $palestraLd['palestrante'],
        ],
        'organizer' => [
            '@type' => 'Organization',
            'name' => 'Centro Universitário FAMETRO',
            'url' => SITE_URL,
        ],
        'offers' => [
            '@type' => 'Offer',
            'url' => SITE_URL . '/cadastro.php?palestra_id=' . $palestraLd['id'],
            'price' => '0',
            'priceCurrency' => 'BRL',
            'availability' => 'https://schema.org/InStock',
        ],
    ];
?>
<script type="application/ld+json"><?= json_encode($eventoLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php endforeach; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
