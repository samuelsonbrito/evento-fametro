-- Dados de teste para o harness local.
-- `administradores` e `palestras` já vêm populados pelo schema.sql (é o dump real de
-- produção — id de admin=1 usuário 'admin', 9 palestras reais). Este arquivo só
-- acrescenta inscrições de exemplo, respeitando a UNIQUE KEY uk_aluno_palestra
-- (matricula, palestra_id) que existe de verdade no banco.
--
-- IMPORTANTE sobre a senha do admin: o hash original em schema.sql era MD5 puro,
-- copiado do banco real de produção — foi REDIGIDO antes de versionar (ver
-- harness/GIT_WORKFLOW.md) porque MD5 é quebrável e isto é um repositório público.
-- De qualquer forma, ninguém aqui sabe a senha em texto puro que gerava aquele hash
-- (não dá pra logar com o usuário 'admin' no harness, com ou sem o hash real). Desde a
-- correção de admin/login.php,
-- isso se resolve sozinho na produção: no primeiro login válido de alguém que souber a
-- senha real, o sistema detecta o MD5, confere a senha e já substitui pelo hash bcrypt
-- na mesma hora — não precisa resetar nada manualmente.
--
-- Pra testar esse fluxo de migração aqui no harness sem precisar da senha real, existe
-- uma segunda conta só de QA local:
INSERT INTO administradores (usuario, senha) VALUES
    -- Senha em texto puro DE PROPÓSITO: simula o mesmo estado legado do admin real.
    -- Logue em /admin/login.php com usuario=harness_qa senha=harness123 e depois
    -- confira a coluna `senha` dessa linha no phpMyAdmin — deve virar um hash
    -- começando com $2y$ (bcrypt) automaticamente após esse primeiro login.
    ('harness_qa', 'harness123');

INSERT INTO inscricoes (nome_aluno, matricula, email, palestra_id, codigo_qrcode, presenca_confirmada, tipo_participante, presente, data_presenca) VALUES
    -- Aluno com presença já confirmada (testa admin/imprimir-comprovante.php e o botão "Declaração")
    ('Ana Beatriz Lima', '202310123', 'ana.lima@exemplo.com', 1, 'QR-SEEDCONFIRMADO01', 1, 'aluno', 1, NOW()),
    -- Aluno pendente na MESMA palestra, matrícula diferente (testa admin/validar-qrcode.php e api/validar_presenca.php)
    ('Bruno Costa', '202310456', 'bruno.costa@exemplo.com', 1, 'QR-SEEDPENDENTE01', 0, 'aluno', 0, NULL),
    -- Mesma matrícula do Bruno, agora inscrito na palestra 2 (matricula+palestra_id
    -- diferente de (202310456,1), então a constraint não bloqueia — cobre o caso comum
    -- de um aluno se inscrever em várias palestras do evento).
    ('Bruno Costa', '202310456', 'bruno.costa@exemplo.com', 2, 'QR-SEEDPENDENTE02', 0, 'aluno', 0, NULL),
    -- Público externo, sem matrícula — vários externos podem ter matricula NULL na
    -- mesma palestra porque o MySQL não considera NULL=NULL em UNIQUE KEY.
    ('Carla Mendes', NULL, 'carla.mendes@exemplo.com', 2, 'QR-SEEDEXTERNO01', 0, 'externo', 0, NULL);

-- Nota sobre teste de conflito de horário (verificarConflitoHorario em
-- includes/functions.php): a grade real (palestras 1-9) não tem horários sobrepostos
-- entre si (é sequencial), só adjacentes — ex.: palestra 1 termina 09:35:00 e a 2 começa
-- exatamente 09:35:00, o que NÃO deve contar como conflito (checar isso é um bom teste
-- de regressão). Pra testar um conflito de verdade, insira temporariamente uma palestra
-- extra com horário sobreposto ao de outra já existente, só no banco local do harness.
