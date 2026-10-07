<?php
// Emissão e validação do certificado da Jornada. Depende de functions.php e
// evento.php já carregados.
//
// Não existe tabela de certificados: o certificado é sempre recalculado a partir das
// inscrições com presença confirmada. O código de validação carrega o id de uma
// inscrição "âncora" + o tipo de busca (E = e-mail, M = matrícula) e é assinado com
// HMAC (CERTIFICADO_SECRET no .env), então não dá pra forjar nem adivinhar o código
// de outra pessoa trocando o id.
//
// Formato: IMIA-<E|M><id em base 36>-<10 hex do HMAC>, ex.: IMIA-E1C-8F3A9B21C0

define('CERTIFICADO_PREFIXO', 'IMIA');

// Limite de buscas por IP mais folgado que o do login: depois do evento muita gente
// emite o certificado ao mesmo tempo pela rede do campus (mesmo IP público).
define('CERTIFICADO_MAX_BUSCAS', 30);

function certificadoSegredo() {
    $segredo = getenv('CERTIFICADO_SECRET');
    if ($segredo === false || strlen($segredo) < 16) {
        error_log('CERTIFICADO_SECRET ausente ou curto demais (mínimo 16 caracteres) — emissão de certificados desativada.');
        return null;
    }
    return $segredo;
}

// "@" no texto digitado = e-mail; qualquer outra coisa = matrícula.
function certificadoTipoIdentificador($valor) {
    return strpos($valor, '@') !== false ? 'E' : 'M';
}

function certificadoNormalizarIdentificador($tipo, $valor) {
    $valor = trim((string) $valor);
    return $tipo === 'E' ? mb_strtolower($valor, 'UTF-8') : $valor;
}

function certificadoAssinatura($tipo, $idAncora, $identificador) {
    $segredo = certificadoSegredo();
    if ($segredo === null) {
        return null;
    }
    $mensagem = 'certificado|' . $tipo . '|' . (int) $idAncora . '|' . certificadoNormalizarIdentificador($tipo, $identificador);
    return strtoupper(substr(hash_hmac('sha256', $mensagem, $segredo), 0, 10));
}

function certificadoGerarCodigo($tipo, $idAncora, $identificador) {
    $assinatura = certificadoAssinatura($tipo, $idAncora, $identificador);
    if ($assinatura === null) {
        return null;
    }
    return CERTIFICADO_PREFIXO . '-' . $tipo . strtoupper(base_convert((string) (int) $idAncora, 10, 36)) . '-' . $assinatura;
}

// Inscrições com presença confirmada de uma pessoa, já com os dados da palestra.
function certificadoBuscarParticipacoes($pdo, $tipo, $identificador) {
    $coluna = $tipo === 'E' ? 'i.email' : 'i.matricula';
    $stmt = $pdo->prepare(
        "SELECT i.id, i.nome_aluno, i.matricula, i.email, i.tipo_participante, i.palestra_id,
                p.titulo, p.palestrante, p.horario_inicio, p.horario_fim
         FROM inscricoes i
         JOIN palestras p ON p.id = i.palestra_id
         WHERE $coluna = ? AND (i.presenca_confirmada = 1 OR i.presente = 1)
         ORDER BY p.horario_inicio ASC, i.id ASC"
    );
    $stmt->execute([trim((string) $identificador)]);
    return $stmt->fetchAll();
}

// Regra de carga horária, num lugar só (certificado e estatísticas usam a mesma):
// recebe ['manha' => nº de palestras com presença, ...] e devolve as horas por turno.
function certificadoHorasPorTurno($palestrasPorTurno) {
    $horas = [];
    foreach (['manha', 'tarde', 'noite'] as $chave) {
        $horas[$chave] = ($palestrasPorTurno[$chave] ?? 0) >= CERTIFICADO_MIN_PALESTRAS_POR_TURNO
            ? CERTIFICADO_HORAS_POR_TURNO
            : 0;
    }
    return $horas;
}

// Identifica a pessoa para fins de contagem: a matrícula quando existe (o mesmo aluno
// pode ter emitido pelo e-mail e pela matrícula), senão o e-mail.
function certificadoChavePessoa($matricula, $email) {
    $matricula = trim((string) $matricula);
    return $matricula !== ''
        ? 'M:' . mb_strtolower($matricula, 'UTF-8')
        : 'E:' . mb_strtolower(trim((string) $email), 'UTF-8');
}

// Monta tudo o que a página do certificado precisa. Retorna null se a pessoa não tem
// presença confirmada suficiente pra somar alguma hora.
function certificadoMontar($pdo, $tipo, $identificador, $idAncora = null) {
    if ($tipo === 'M' && trim((string) $identificador) === '') {
        return null;
    }

    $linhas = certificadoBuscarParticipacoes($pdo, $tipo, $identificador);
    if (!$linhas) {
        return null;
    }

    $turnos = [
        'manha' => ['rotulo' => 'Manhã', 'palestras' => [], 'horas' => 0],
        'tarde' => ['rotulo' => 'Tarde', 'palestras' => [], 'horas' => 0],
        'noite' => ['rotulo' => 'Noite', 'palestras' => [], 'horas' => 0],
    ];
    $palestrasVistas = [];
    $maisRecente = null;
    $matricula = null;

    foreach ($linhas as $linha) {
        if ($maisRecente === null || (int) $linha['id'] > (int) $maisRecente['id']) {
            $maisRecente = $linha;
        }
        if (!empty($linha['matricula'])) {
            $matricula = $linha['matricula'];
        }
        if (isset($palestrasVistas[$linha['palestra_id']])) {
            continue;
        }
        $palestrasVistas[$linha['palestra_id']] = true;

        $turno = turnoDaPalestra($linha['horario_inicio']);
        $turnos[$turno['chave']]['palestras'][] = [
            'titulo' => textoPuro($linha['titulo']),
            'palestrante' => textoPuro($linha['palestrante']),
            'horario' => date('H:i', strtotime($linha['horario_inicio'])) . ' às ' . date('H:i', strtotime($linha['horario_fim'])),
        ];
    }

    $horasPorTurno = certificadoHorasPorTurno(array_map(function ($t) { return count($t['palestras']); }, $turnos));
    $horasTotal = array_sum($horasPorTurno);
    foreach ($horasPorTurno as $chave => $horas) {
        $turnos[$chave]['horas'] = $horas;
    }

    if ($horasTotal === 0) {
        return null;
    }

    // Âncora = menor id com presença. Na validação, o id vem do próprio código e só
    // precisa continuar pertencendo à pessoa (não precisa ser o menor), pra um código
    // já emitido não mudar se outra presença for confirmada depois.
    $ids = array_map('intval', array_column($linhas, 'id'));
    if ($idAncora === null) {
        $idAncora = min($ids);
    } elseif (!in_array((int) $idAncora, $ids, true)) {
        return null;
    }

    // Assina com o valor gravado na inscrição âncora (não com o texto digitado): o
    // MySQL compara sem diferenciar maiúsculas, a validação lê do banco.
    $colunaIdentificador = $tipo === 'E' ? 'email' : 'matricula';
    $identificadorGravado = $identificador;
    foreach ($linhas as $linha) {
        if ((int) $linha['id'] === (int) $idAncora) {
            $identificadorGravado = $linha[$colunaIdentificador];
            break;
        }
    }

    $codigo = certificadoGerarCodigo($tipo, $idAncora, $identificadorGravado);
    if ($codigo === null) {
        return null;
    }

    return [
        'codigo' => $codigo,
        'chave_pessoa' => certificadoChavePessoa($matricula, $maisRecente['email']),
        'nome' => mb_strtoupper(textoPuro($maisRecente['nome_aluno']), 'UTF-8'),
        'matricula' => $matricula !== null ? textoPuro($matricula) : null,
        'tipo_participante' => $maisRecente['tipo_participante'],
        'turnos' => $turnos,
        'horas_total' => $horasTotal,
    ];
}

// Valida um código digitado/lido no QR e devolve o certificado, ou null se for
// inválido, adulterado ou se a presença tiver sido removida depois.
function certificadoPorCodigo($pdo, $codigo) {
    $codigo = strtoupper(trim((string) $codigo));
    if (!preg_match('/^' . CERTIFICADO_PREFIXO . '-([EM])([0-9A-Z]{1,10})-([0-9A-F]{10})$/', $codigo, $m)) {
        return null;
    }
    list(, $tipo, $idBase36, $assinatura) = $m;
    $idAncora = (int) base_convert(strtolower($idBase36), 36, 10);

    $stmt = $pdo->prepare('SELECT email, matricula FROM inscricoes WHERE id = ?');
    $stmt->execute([$idAncora]);
    $ancora = $stmt->fetch();
    if (!$ancora) {
        return null;
    }

    $identificador = $tipo === 'E' ? $ancora['email'] : $ancora['matricula'];
    $esperada = certificadoAssinatura($tipo, $idAncora, $identificador);
    if ($esperada === null || !hash_equals($esperada, $assinatura)) {
        return null;
    }

    return certificadoMontar($pdo, $tipo, $identificador, $idAncora);
}

function certificadoUrlValidacao($codigo) {
    return SITE_URL . '/validar-certificado.php?codigo=' . rawurlencode($codigo);
}

function certificadoHorasPorExtenso($horas) {
    $extenso = [5 => 'cinco', 10 => 'dez', 15 => 'quinze'];
    return isset($extenso[$horas]) ? $horas . ' (' . $extenso[$horas] . ') horas' : $horas . ' horas';
}

// Registra que a pessoa abriu o próprio certificado (tabela certificados_emitidos).
// Uma linha por pessoa: a primeira emissão fica guardada e as aberturas seguintes só
// somam visualizações. Nunca derruba a página: se a tabela ainda não existir em
// produção, só loga.
function certificadoRegistrarEmissao($pdo, $certificado) {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO certificados_emitidos (chave_pessoa, codigo, primeira_emissao, ultima_visualizacao, visualizacoes)
             VALUES (?, ?, NOW(), NOW(), 1)
             ON DUPLICATE KEY UPDATE
                codigo = VALUES(codigo),
                ultima_visualizacao = NOW(),
                visualizacoes = visualizacoes + 1"
        );
        $stmt->execute([$certificado['chave_pessoa'], $certificado['codigo']]);
    } catch (PDOException $e) {
        error_log('Não foi possível registrar a emissão do certificado: ' . $e->getMessage());
    }
}

// Todas as pessoas com direito a certificado, calculadas pela mesma regra do
// certificado. Usado nas estatísticas do admin.
function certificadoElegiveis($pdo) {
    $linhas = $pdo->query(
        "SELECT i.id, i.nome_aluno, i.matricula, i.email, i.tipo_participante, i.palestra_id, p.horario_inicio
         FROM inscricoes i
         JOIN palestras p ON p.id = i.palestra_id
         WHERE i.presenca_confirmada = 1 OR i.presente = 1
         ORDER BY i.id ASC"
    )->fetchAll();

    $pessoas = [];
    foreach ($linhas as $linha) {
        $chave = certificadoChavePessoa($linha['matricula'], $linha['email']);
        if (!isset($pessoas[$chave])) {
            // Primeira inscrição com presença = âncora do código (mesma escolha do
            // certificado), pra o admin conseguir abrir o certificado de quem ainda
            // não emitiu.
            $pessoas[$chave] = [
                'chave_pessoa' => $chave,
                'id_ancora' => (int) $linha['id'],
                'tipo_busca' => !empty($linha['matricula']) ? 'M' : 'E',
                'identificador' => !empty($linha['matricula']) ? $linha['matricula'] : $linha['email'],
                'palestras' => [],
                'contagem' => ['manha' => 0, 'tarde' => 0, 'noite' => 0],
            ];
        }
        // A inscrição mais recente define nome/e-mail/tipo, igual ao certificado.
        $pessoas[$chave]['nome'] = mb_strtoupper(textoPuro($linha['nome_aluno']), 'UTF-8');
        $pessoas[$chave]['email'] = textoPuro($linha['email']);
        $pessoas[$chave]['tipo_participante'] = $linha['tipo_participante'];
        if (!empty($linha['matricula'])) {
            $pessoas[$chave]['matricula'] = textoPuro($linha['matricula']);
        }
        if (!isset($pessoas[$chave]['palestras'][$linha['palestra_id']])) {
            $pessoas[$chave]['palestras'][$linha['palestra_id']] = true;
            $pessoas[$chave]['contagem'][turnoDaPalestra($linha['horario_inicio'])['chave']]++;
        }
    }

    $resultado = [];
    foreach ($pessoas as $chave => $pessoa) {
        $horas = certificadoHorasPorTurno($pessoa['contagem']);
        if (array_sum($horas) === 0) {
            continue;
        }
        $pessoa['matricula'] = $pessoa['matricula'] ?? null;
        $pessoa['horas_por_turno'] = $horas;
        $pessoa['horas_total'] = array_sum($horas);
        unset($pessoa['palestras'], $pessoa['contagem']);
        $resultado[$chave] = $pessoa;
    }
    return $resultado;
}
