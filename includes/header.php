<?php
iniciarSessaoSegura();

// Cada página pode definir $pageTitle/$pageDescription/$pageImage/$pageNoIndex/
// $pageCanonical antes de dar require neste arquivo. Sem isso, cai nos padrões
// abaixo (bons o bastante pra home, mas genéricos demais pra páginas internas).
$pageTitle       = $pageTitle ?? 'Jornada Acadêmica Imersão FAMETRO — Inscrições Abertas';
$pageDescription = $pageDescription ?? 'Inscreva-se gratuitamente na Jornada Acadêmica Imersão FAMETRO, dia 2 de outubro. Palestras sobre Inteligência Artificial, até 15h complementares e credenciamento por QR Code.';
$pageImage       = $pageImage ?? SITE_URL . '/assets/img/principal.png';
$pageNoIndex     = $pageNoIndex ?? false;
$pageCanonical   = $pageCanonical ?? SITE_URL . ($_SERVER['REQUEST_URI'] ?? '/index.php');
?>
<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>"/>
    <meta name="robots" content="<?= $pageNoIndex ? 'noindex, nofollow' : 'index, follow' ?>"/>
    <link rel="canonical" href="<?= htmlspecialchars($pageCanonical) ?>"/>

    <!-- Open Graph / compartilhamento em redes sociais -->
    <meta property="og:type" content="website"/>
    <meta property="og:locale" content="pt_BR"/>
    <meta property="og:site_name" content="Jornada Acadêmica Imersão FAMETRO"/>
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>"/>
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>"/>
    <meta property="og:url" content="<?= htmlspecialchars($pageCanonical) ?>"/>
    <meta property="og:image" content="<?= htmlspecialchars($pageImage) ?>"/>

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image"/>
    <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle) ?>"/>
    <meta name="twitter:description" content="<?= htmlspecialchars($pageDescription) ?>"/>
    <meta name="twitter:image" content="<?= htmlspecialchars($pageImage) ?>"/>

    <link rel="icon" href="/assets/img/logo-fametro.png" type="image/png"/>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
  </head>
  <body class="bg-light">
    <div class="page">
      <header class="navbar navbar-expand-md navbar-light bg-white d-print-none border-bottom-fametro shadow-sm">
        <div class="container-xl">
          <a href="/index.php" class="navbar-brand d-flex align-items-center">
            <img src="/assets/img/logo-fametro.png" alt="FAMETRO" style="height: 38px;" class="me-2" onerror="this.style.display='none'">
            <span class="fw-bold text-fametro-blue d-none d-sm-inline">Imersão FAMETRO</span>
          </a>
          <div class="navbar-nav ms-auto">
            <a href="/consultar-inscricao.php" class="btn btn-outline-secondary btn-sm me-2">
              <i class="ti ti-qrcode me-1"></i> Consultar Inscrição
            </a>
            <?php if (isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true): ?>
              <a href="/admin/index.php" class="btn btn-fametro-red btn-sm me-2">
                <i class="ti ti-dashboard me-1"></i> Painel ADM
              </a>
              <a href="/admin/logout.php" class="btn btn-outline-secondary btn-sm">Sair</a>
            <?php else: ?>
              <a href="/admin/login.php" class="btn btn-outline-primary btn-sm">Área Restrita</a>
            <?php endif; ?>
          </div>
        </div>
      </header>
      <div class="page-wrapper">