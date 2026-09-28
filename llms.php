<?php
// Servido como /llms.txt (ver .htaccess): resumo do site em Markdown puro, no formato
// proposto em https://llmstxt.org, pra assistentes de IA (ChatGPT, Claude, Gemini,
// Perplexity) entenderem o evento sem precisar interpretar o HTML. Gerado
// dinamicamente pelo mesmo motivo do sitemap.php: a programação muda pelo painel admin.
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/evento.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $palestras = $pdo->query('SELECT id, titulo, palestrante, descricao, horario_inicio, horario_fim FROM palestras ORDER BY horario_inicio ASC')->fetchAll();
} catch (PDOException $e) {
    $palestras = [];
}

// Quebras de linha na descrição virariam itens de lista soltos no Markdown.
function linhaUnica($texto) {
    return trim(preg_replace('/\s+/u', ' ', textoPuro($texto)));
}

echo '# ' . EVENTO_NOME . "\n\n";
echo '> ' . EVENTO_RESUMO . "\n\n";

echo "## Informações principais\n\n";
echo '- Data: ' . EVENTO_DATA_EXTENSO . "\n";
echo '- Local: ' . eventoLocalTexto() . " (evento presencial)\n";
echo "- Inscrição: gratuita, vagas limitadas, pelo site " . SITE_URL . "/index.php\n";
echo "- Público: alunos da FAMETRO e público externo\n";
echo "- Horas complementares: até 15 horas (5 horas por turno)\n";
echo "- Credenciamento: por QR Code, recebido no comprovante de inscrição\n";
echo '- Recuperar comprovante/QR Code: ' . SITE_URL . "/consultar-inscricao.php\n\n";

echo "## Programação\n\n";
if (!$palestras) {
    echo "A programação ainda não foi publicada.\n\n";
}
foreach ($palestras as $p) {
    echo '### ' . linhaUnica($p['titulo']) . "\n\n";
    echo '- Palestrante: ' . linhaUnica($p['palestrante']) . "\n";
    echo '- Horário: ' . date('H:i', strtotime($p['horario_inicio'])) . ' às ' . date('H:i', strtotime($p['horario_fim'])) . "\n";
    echo '- Inscrição: ' . SITE_URL . '/cadastro.php?palestra_id=' . (int) $p['id'] . "\n";
    if (trim($p['descricao']) !== '') {
        echo "\n" . linhaUnica($p['descricao']) . "\n";
    }
    echo "\n";
}

echo "## Perguntas frequentes\n\n";
foreach (eventoPerguntasFrequentes() as $faq) {
    echo '### ' . $faq['pergunta'] . "\n\n" . $faq['resposta'] . "\n\n";
}
