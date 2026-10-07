<?php
// Informações fixas do evento, usadas no texto da página inicial, nos dados
// estruturados (Schema.org) e no /llms.txt. Ficam num lugar só pra que a página, o
// Google e as buscas por IA (ChatGPT, Gemini, Claude, Perplexity) sempre digam a
// mesma coisa.

define('EVENTO_NOME', 'Jornada Acadêmica Imersão IA FAMETRO');
define('EVENTO_DATA_EXTENSO', '2 de outubro de 2026');
define('EVENTO_RESUMO', 'Evento acadêmico presencial e gratuito do Centro Universitário FAMETRO com palestras sobre Inteligência Artificial — "Descomplicando a Inteligência Artificial, da curiosidade à carreira". Aberto a alunos da FAMETRO e ao público externo, vale até 15 horas complementares. Quem teve presença confirmada emite o certificado pelo site.');

// Local do evento. Preencher o endereço e a cidade: sem isso, buscas por IA e o
// Google não conseguem responder "onde é o evento?". Enquanto estiverem vazios,
// o site mostra só o nome da instituição.
define('EVENTO_LOCAL_NOME', 'Centro Universitário FAMETRO');
define('EVENTO_LOCAL_ENDERECO', ''); // ex.: 'Av. Exemplo, 123 - Bairro'
define('EVENTO_LOCAL_CIDADE', '');   // ex.: 'Manaus'
define('EVENTO_LOCAL_UF', '');       // ex.: 'AM'

define('EVENTO_TEMA', 'Descomplicando a Inteligência Artificial, da curiosidade à carreira');

// O evento já aconteceu: com false, cadastro.php e api/cadastrar_aluno.php deixam de
// aceitar inscrições e a home passa a oferecer a emissão de certificado.
define('INSCRICOES_ABERTAS', false);

// --- Certificado -----------------------------------------------------------------
// Regra de carga horária: cada turno vale CERTIFICADO_HORAS_POR_TURNO horas e conta
// quando o participante tem presença confirmada em pelo menos
// CERTIFICADO_MIN_PALESTRAS_POR_TURNO palestras daquele turno.
define('CERTIFICADO_HORAS_POR_TURNO', 5);
define('CERTIFICADO_MIN_PALESTRAS_POR_TURNO', 1);

// Cidade/data que aparecem na linha "Manaus, 2 de outubro de 2026" do certificado.
// Usa EVENTO_LOCAL_CIDADE quando preenchida.
define('CERTIFICADO_CIDADE_EMISSAO', EVENTO_LOCAL_CIDADE !== '' ? EVENTO_LOCAL_CIDADE : 'Manaus');

// Quem assina o certificado. 'imagem' é opcional (caminho de um PNG com fundo
// transparente, ex.: '/assets/img/assinatura-coordenacao.png').
function certificadoAssinaturas() {
    return [
        ['nome' => '', 'cargo' => 'Coordenação da Jornada Acadêmica', 'imagem' => ''],
        ['nome' => '', 'cargo' => 'Centro Universitário FAMETRO', 'imagem' => ''],
    ];
}

// Turno de uma palestra pelo horário de início — mesma divisão usada na home.
function turnoDaPalestra($horarioInicio) {
    $hora = (int) date('H', strtotime($horarioInicio));
    if ($hora < 12) {
        return ['chave' => 'manha', 'rotulo' => 'Manhã', 'badge' => 'bg-blue'];
    }
    if ($hora < 18) {
        return ['chave' => 'tarde', 'rotulo' => 'Tarde', 'badge' => 'bg-orange'];
    }
    return ['chave' => 'noite', 'rotulo' => 'Noite', 'badge' => 'bg-purple'];
}

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
            'pergunta' => 'Quando aconteceu a ' . EVENTO_NOME . '?',
            'resposta' => 'No dia ' . EVENTO_DATA_EXTENSO . ', com palestras nos turnos da manhã, tarde e noite, no ' . eventoLocalTexto() . '.',
        ],
        [
            'pergunta' => 'Como emito meu certificado?',
            'resposta' => 'Na página inicial do site, informe o e-mail usado na inscrição ou sua matrícula e clique em "Emitir certificado". O certificado abre na tela, pronto para imprimir ou salvar em PDF.',
        ],
        [
            'pergunta' => 'Quantas horas complementares o certificado vale?',
            'resposta' => 'São ' . CERTIFICADO_HORAS_POR_TURNO . ' horas por turno em que você teve presença confirmada, até 15 horas no total (manhã, tarde e noite).',
        ],
        [
            'pergunta' => 'Quem tem direito ao certificado?',
            'resposta' => 'Quem se inscreveu e teve a presença confirmada pela leitura do QR Code na entrada de pelo menos uma palestra.',
        ],
        [
            'pergunta' => 'Não encontrei meu certificado. E agora?',
            'resposta' => 'Confira se digitou o mesmo e-mail da inscrição, ou tente pela matrícula. Se ainda assim não aparecer, use o botão "Reportar problema" informando seu nome, matrícula e as palestras que assistiu.',
        ],
        [
            'pergunta' => 'Como alguém confirma que meu certificado é verdadeiro?',
            'resposta' => 'Todo certificado tem um código de validação e um QR Code. Basta abrir ' . SITE_URL . '/validar-certificado.php e informar o código, ou ler o QR Code.',
        ],
    ];
}

// Os campos das palestras são gravados já com htmlspecialchars() (ver sanitize() em
// functions.php). Para texto puro (JSON-LD, llms.txt) é preciso desfazer isso, senão
// as IAs leem "&quot;" e "&amp;" no lugar de aspas e "&".
function textoPuro($valor) {
    return html_entity_decode((string) $valor, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
