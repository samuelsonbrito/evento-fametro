-- ============================================================================
-- ⚠️  NUNCA RODE ESTE ARQUIVO CONTRA UM BANCO QUE TENHA DADOS REAIS.  ⚠️
-- Ele começa com DROP TABLE nas três tabelas. Em 2026-09-22 isso foi executado
-- por engano contra a PRODUÇÃO e apagou inscrições reais de alunos, sem
-- backup — ver harness/db/incidente-2026-09-22-saneamento-producao.sql e
-- harness/ISSUES.md. Este arquivo serve SÓ para popular o banco descartável
-- do harness (harness/docker-compose.yml). Se o objetivo é ajustar produção,
-- use um script de migração pontual (ALTER TABLE / UPDATE / DELETE
-- específicos), nunca este arquivo.
-- ============================================================================
--
-- Schema REAL de produção (InfinityFree), como enviado pelo usuário em 2026-09-22.
-- Este arquivo é a fonte da verdade a partir de agora — qualquer schema inferido
-- anteriormente foi substituído por este dump. Não editar a estrutura aqui sem
-- confirmar a mudança correspondente no banco real primeiro.
--
-- Diferenças importantes vs. o schema inferido anterior, ver harness/ISSUES.md:
--   - administradores.senha guarda MD5 puro em produção (não bcrypt).
--   - inscricoes tem UNIQUE KEY uk_aluno_palestra (matricula, palestra_id), que
--     cadastro.php não respeita no seu check de duplicidade (ele checa por e-mail).
--   - inscricoes.palestra_id é ON DELETE CASCADE.
--   - Nomes de coluna reais: `criado_em` (admin/palestras) e `data_inscricao`
--     (inscricoes), não `created_at`.

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Apaga primeiro a tabela dependente 'inscricoes'
DROP TABLE IF EXISTS inscricoes;

-- 2. Apaga as restantes tabelas
DROP TABLE IF EXISTS palestras;
DROP TABLE IF EXISTS administradores;
DROP TABLE IF EXISTS tentativas_login;

-- 3. Cria a tabela 'administradores'
CREATE TABLE administradores (
  id int(11) NOT NULL AUTO_INCREMENT,
  usuario varchar(50) NOT NULL,
  senha varchar(255) NOT NULL,
  criado_em timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY usuario (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- O hash real de produção foi REDIGIDO antes de versionar este arquivo (era MD5 puro,
-- ou seja, quebrável — não faz sentido nenhum publicar isso num repositório público).
-- Ver harness/GIT_WORKFLOW.md e harness/ISSUES.md item 2. O valor abaixo é só um
-- placeholder no mesmo formato (32 hex) para manter o schema executável; ninguém
-- consegue logar como 'admin' com ele, nem localmente. Use a conta harness_qa
-- (harness/db/seed.sql) para testar o fluxo de login/migração.
INSERT INTO administradores (id, usuario, senha, criado_em) VALUES
(1, 'admin', '00000000000000000000000000000000', '2026-09-19 22:56:04');

-- 4. Cria a tabela 'palestras'
CREATE TABLE palestras (
  id int(11) NOT NULL AUTO_INCREMENT,
  titulo varchar(255) NOT NULL,
  palestrante varchar(150) NOT NULL,
  foto varchar(255) DEFAULT 'default.jpg',
  descricao text DEFAULT NULL,
  horario_inicio time NOT NULL,
  horario_fim time NOT NULL,
  vagas int(11) DEFAULT 100,
  criado_em timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO palestras (id, titulo, palestrante, foto, descricao, horario_inicio, horario_fim, vagas, criado_em) VALUES
(1, 'IA Agêntica na prática: Agentes, Skills, MCP e Ferramentas', 'RHEDSON FRANCISCO FERNANDES ESASHIKA', 'palestrante_6ab14af1a9b58.jpeg', 'Apresentar, de forma prática, os conceitos de I.A Agêntica e mostrar como agentes, skills, ferramentas e MCP podem ser usados na criação de aplicações inteligentes e novas oportunidades.', '08:45:00', '09:35:00', 600, '2026-09-21 15:19:13'),
(2, 'Como desenvolver competências, utilizar ferramentas de IA e transformar conhecimento em projetos e oportunidades profissionais', 'JEAN DA SILVA HOLGUIM', 'palestrante_6ab14bfc19ef7.jpeg', 'Apresentar aos estudantes uma visão prática e acessível sobre o uso da Inteligência Artificial, destacando suas aplicações no mercado de trabalho, as competências necessárias para atuar na área e as possibilidades de desenvolvimento de projetos de IA ainda durante a graduação.', '09:35:00', '10:25:00', 600, '2026-09-21 15:23:40'),
(3, 'O profissional que a IA não substitui: competências para os próximos 10 anos', 'Daniel Lins da Silva', 'palestrante_6ab14ccb02a86.jpeg', 'A Inteligência Artificial está reescrevendo o que significa ser um profissional de tecnologia. Ferramentas que programam, testam e documentam já são realidade, e a pergunta que todo estudante de computação se faz é inevitável, o que sobra para mim? A palestra mostra que a resposta não está em competir com a IA, mas em desenvolver o que ela não entrega, julgamento, capacidade de definir problemas, visão de sistemas e responsabilidade sobre resultados. A partir de casos reais da indústria, apresenta um mapa prático das competências que valem para os próximos 10 anos e o que os alunos podem começar a fazer ainda na graduação para se posicionar nesse novo mercado.', '10:40:00', '11:30:00', 600, '2026-09-21 15:27:07'),
(4, 'Da Informação à Inteligência: como transformar dados, IA e conhecimento em projetos e oportunidades profissionais', 'MARCIO ANDRÉ PRESTES LINS', 'palestrante_6ab1657c4382e.jpeg', 'Apresentar aos estudantes uma visão prática sobre como dados, conhecimento e Inteligência Artificial se conectam para transformar problemas reais em soluções, projetos e oportunidades profissionais, destacando que o uso eficaz da IA começa pela capacidade de compreender, organizar e utilizar dados para gerar conhecimento e apoiar decisões.', '14:15:00', '15:05:00', 600, '2026-09-21 17:12:28'),
(5, 'A IA vai acabar com a sua profissão? O futuro do trabalho na era da Inteligência Artificial', 'SAMUELSON BRITO', 'palestrante_6ab166a6c708d.jpeg', 'Apresentar uma visão prática e realista sobre como a Inteligência Artificial está transformando profissões e o mercado de trabalho. A palestra busca desconstruir o medo em torno da IA, mostrando que a mudança também pode representar oportunidades de aprendizado, adaptação e crescimento profissional, além de apresentar caminhos para que os profissionais desenvolvam novas habilidades e se destaquem nesse cenário de transformação.', '15:05:00', '15:55:00', 700, '2026-09-21 17:17:26'),
(6, 'O que os consumidores dizem? IA aplicada à análise de opiniões no comércio eletrônico', 'Tiago Eugenio de Melo', 'palestrante_6ab1678507703.jpeg', 'Mostrar aos estudantes como a Inteligência Artificial e o Processamento de Linguagem Natural transformam grandes volumes de avaliações e comentários de consumidores em informação útil para a tomada de decisão. A partir de casos de comércio eletrônico, a palestra apresenta como a análise de sentimentos e a mineração de opiniões revelam percepções sobre produtos e serviços, e discute o uso responsável desses modelos, incluindo modelos de linguagem de grande escala, na compreensão da voz do consumidor.', '16:10:00', '17:00:00', 600, '2026-09-21 17:21:09'),
(7, 'Mulheres na Era da Inteligência Artificial: Engenharia de Prompt, Inovação e protagonismo no Mercado de Tecnologia', 'LUANA MAGALHÃES LEAL', 'palestrante_6ab1689b811ac.jpeg', 'Apresentar o potencial da Inteligência Artificial e da Engenharia de Prompt como ferramentas de inovação e desenvolvimento profissional, destacando oportunidades, desafios e novas possibilidades de atuação para mulheres no mercado de tecnologia. A palestra busca estimular o protagonismo feminino, a autonomia e o desenvolvimento de competências digitais, mostrando como a IA pode potencializar carreiras e incentivar mulheres a ocuparem espaços de criação, liderança e tomada de decisão no ecossistema tecnológico.', '18:40:00', '19:30:00', 600, '2026-09-21 17:25:47'),
(8, 'Do Prompt ao Protagonismo. Como usar a IA sem deixar de pensar', 'Alexsand Farias de Souza', 'palestrante_6ab16bb2d9750.jpeg', 'Apresentar de forma prática e acessível como a Inteligência Artificial está transformando a educação, o mercado de trabalho e a sociedade, demonstrando possibilidades de uso da IA para aprendizagem, criatividade, produtividade e resolução de problemas. A palestra busca estimular o pensamento crítico e o protagonismo humano, mostrando que a IA deve ser utilizada como ferramenta de apoio e potencialização das capacidades humanas, e não como substituta do conhecimento, da criatividade e da tomada de decisão.', '19:30:00', '20:20:00', 600, '2026-09-21 17:38:58'),
(9, 'Dados não são inteligentes. Você precisa treiná-los!', 'Jean Mark Lobo de Oliveira', 'palestrante_6ab16c8432e8c.jpeg', 'Demonstrar aos estudantes, de forma prática, como dados podem ser preparados, analisados e utilizados no treinamento de modelos de Inteligência Artificial, explorando a integração entre dados, programação, automação e IA para o desenvolvimento de sistemas e projetos aplicáveis a problemas reais.', '20:35:00', '21:25:00', 600, '2026-09-21 17:42:28');

-- 5. Cria a tabela 'inscricoes'
CREATE TABLE inscricoes (
  id int(11) NOT NULL AUTO_INCREMENT,
  nome_aluno varchar(150) NOT NULL,
  matricula varchar(50) DEFAULT NULL,
  email varchar(150) NOT NULL,
  palestra_id int(11) NOT NULL,
  codigo_qrcode varchar(100) NOT NULL,
  presenca_confirmada tinyint(1) DEFAULT 0,
  data_inscricao timestamp NULL DEFAULT current_timestamp(),
  tipo_participante enum('aluno','externo') DEFAULT 'aluno',
  presente tinyint(1) DEFAULT 0,
  data_presenca datetime DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY codigo_qrcode (codigo_qrcode),
  UNIQUE KEY uk_aluno_palestra (matricula,palestra_id),
  KEY palestra_id (palestra_id),
  CONSTRAINT inscricoes_ibfk_1 FOREIGN KEY (palestra_id) REFERENCES palestras (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Cria a tabela 'tentativas_login' (rate limiting no login admin — ver ISSUES.md
-- item 6). Adicionada em 2026-09-23; se produção já tiver as 3 tabelas anteriores
-- criadas manualmente antes desta data, rodar só este CREATE TABLE lá (nunca o
-- schema.sql inteiro, ver aviso no topo deste arquivo).
CREATE TABLE tentativas_login (
  id int(11) NOT NULL AUTO_INCREMENT,
  identificador varchar(255) NOT NULL,
  tentativas int(11) NOT NULL DEFAULT 1,
  ultima_tentativa timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY identificador (identificador)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
