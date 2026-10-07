<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/xml; charset=utf-8');

// As páginas de inscrição (cadastro.php?palestra_id=N) saíram do sitemap quando as
// inscrições foram encerradas (INSCRICOES_ABERTAS em includes/evento.php).
$baseUrl = SITE_URL;

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc><?= htmlspecialchars($baseUrl) ?>/index.php</loc>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc><?= htmlspecialchars($baseUrl) ?>/validar-certificado.php</loc>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>
</urlset>
