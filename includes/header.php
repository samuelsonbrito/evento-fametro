<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <title>Jornada Acadêmica Imersão FAMETRO</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css">
    <link rel="stylesheet" href="/evento-fametro/assets/css/style.css">
  </head>
  <body class="bg-light">
    <div class="page">
      <header class="navbar navbar-expand-md navbar-light bg-white d-print-none border-bottom-fametro shadow-sm">
        <div class="container-xl">
          <a href="/evento-fametro/index.php" class="navbar-brand d-flex align-items-center">
            <img src="/evento-fametro/assets/img/logo-fametro.png" alt="FAMETRO" style="height: 38px;" class="me-2" onerror="this.style.display='none'">
            <span class="fw-bold text-fametro-blue d-none d-sm-inline">Imersão FAMETRO</span>
          </a>
          <div class="navbar-nav ms-auto">
            <?php if (isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true): ?>
              <a href="/evento-fametro/admin/index.php" class="btn btn-fametro-red btn-sm me-2">
                <i class="ti ti-dashboard me-1"></i> Painel ADM
              </a>
              <a href="/evento-fametro/admin/logout.php" class="btn btn-outline-secondary btn-sm">Sair</a>
            <?php else: ?>
              <a href="/evento-fametro/admin/login.php" class="btn btn-outline-primary btn-sm">Área Restrita</a>
            <?php endif; ?>
          </div>
        </div>
      </header>
      <div class="page-wrapper">