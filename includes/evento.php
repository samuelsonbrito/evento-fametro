<?php
// Informações fixas do evento, usadas no texto da página inicial, nos dados
// estruturados (Schema.org) e no /llms.txt. Ficam num lugar só pra que a página, o
// Google e as buscas por IA (ChatGPT, Gemini, Claude, Perplexity) sempre digam a
// mesma coisa.

define('EVENTO_NOME', 'Jornada Acadêmica Imersão IA FAMETRO');
define('EVENTO_DATA_EXTENSO', '2 de outubro de 2026');
define('EVENTO_RESUMO', 'Evento acadêmico presencial e gratuito do Centro Universitário FAMETRO com palestras sobre Inteligência Artificial — "Descomplicando a Inteligência Artificial, da curiosidade à carreira". Aberto a alunos da FAMETRO e ao público externo, vale até 15 horas complementares e o credenciamento é feito por QR Code.');

// Local do evento. Preencher o endereço e a cidade: sem isso, buscas por IA e o
// Google não conseguem responder "onde é o evento?". Enquanto estiverem vazios,
// o site mostra só o nome da instituição.
define('EVENTO_LOCAL_NOME', 'Centro Universitário FAMETRO');
define('EVENTO_LOCAL_ENDERECO', ''); // ex.: 'Av. Exemplo, 123 - Bairro'
define('EVENTO_LOCAL_CIDADE', '');   // ex.: 'Manaus'
define('EVENTO_LOCAL_UF', '');       // ex.: 'AM'

// Texto do local para exibir em frases ("Centro Universitário FAMETRO — Av. ..., Cidade/UF").
function eventoLocalTexto() {
    $partes = array_filter([
        EVENTO_LOCAL_ENDERECO,
        trim(EVENTO_LOCAL_CIDADE . (EVENTO_LOCAL_UF !== '' ? '/' . EVENTO_LOCAL_UF : ''), '/'),
    ]);
    return EVENTO_LOCAL_NOME . ($partes ? ' — ' . implode(', ', $partes) : '');
}

// Bloco "location" do Schema.org/Event; só inclui o endereço se ele foi preenchido.
function eventoLocalSchema() {
    $local = ['@type' => 'Place', 'name' => EVENTO_LOCAL_NOME];
    if (EVENTO_LOCAL_ENDERECO !== '' || EVENTO_LOCAL_CIDADE !== '') {
        $local['address'] = array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => EVENTO_LOCAL_ENDERECO,
            'addressLocality' => EVENTO_LOCAL_CIDADE,
            'addressRegion' => EVENTO_LOCAL_UF,
            'addressCountry' => 'BR',
        ]);
    }
    return $local;
}

// Perguntas frequentes — aparecem visíveis na página inicial e como FAQPage no
// Schema.org. Respostas curtas e diretas são o formato que as IAs mais citam.
function eventoPerguntasFrequentes() {
    return [
        [
            'pergunta' => 'Quando acontece a ' . EVENTO_NOME . '?',
            'resposta' => 'No dia ' . EVENTO_DATA_EXTENSO . ', com palestras nos turnos da manhã, tarde e noite. Os horários de cada palestra estão na programação.',
        ],
        [
            'pergunta' => 'Onde é o evento?',
            'resposta' => 'O evento é presencial, no ' . eventoLocalTexto() . '.',
        ],
        [
            'pergunta' => 'Quanto custa para participar?',
            'resposta' => 'Nada. A inscrição é gratuita, mas as vagas são limitadas.',
        ],
        [
            'pergunta' => 'Quem pode participar?',
            'resposta' => 'Alunos da FAMETRO (informando a matrícula) e o público externo. Basta escolher a palestra e preencher a inscrição no site.',
        ],
        [
            'pergunta' => 'O evento vale horas complementares?',
            'resposta' => 'Sim. São até 15 horas complementares no total, 5 horas por turno.',
        ],
        [
            'pergunta' => 'Como faço a inscrição?',
            'resposta' => 'Na página inicial do site, escolha a palestra e clique em "Inscrever-se". Ao concluir, você recebe um comprovante com QR Code.',
        ],
        [
            'pergunta' => 'Como funciona o credenciamento?',
            'resposta' => 'Apresente o QR Code do comprovante na entrada da palestra. A equipe lê o código e confirma sua presença na hora. Se perder o comprovante, é possível recuperá-lo em "Consultar Inscrição", informando o e-mail usado na inscrição.',
        ],
        [
            'pergunta' => 'Posso me inscrever em mais de uma palestra?',
            'resposta' => 'Sim. Cada palestra tem inscrição própria e gera um QR Code próprio; só confira na programação se os horários não coincidem.',
        ],
    ];
}

// Os campos das palestras são gravados já com htmlspecialchars() (ver sanitize() em
// functions.php). Para texto puro (JSON-LD, llms.txt) é preciso desfazer isso, senão
// as IAs leem "&quot;" e "&amp;" no lugar de aspas e "&".
function textoPuro($valor) {
    return html_entity_decode((string) $valor, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
