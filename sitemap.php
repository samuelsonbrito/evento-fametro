<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/xml; charset=utf-8');

// Gerado dinamicamente (em vez de um .xml estático) porque a lista de palestras muda
// pelo painel admin — assim o sitemap nunca fica desatualizado.
$baseUrl = SITE_URL;

try {
    $palestras = $pdo->query('SELECT id FROM palestras ORDER BY id ASC')->fetchAll();
} catch (PDOException $e) {
    $palestras = [];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc><?= htmlspecialchars($baseUrl) ?>/index.php</loc>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>
  <?php foreach ($palestras as $palestra): ?>
  <url>
    <loc><?= htmlspecialchars($baseUrl) ?>/cadastro.php?palestra_id=<?= (int) $palestra['id'] ?></loc>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
  </url>
  <?php endforeach; ?>
</urlset>
