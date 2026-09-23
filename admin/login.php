<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = sanitize($_POST['usuario'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $erro = 'Sessão expirada. Atualize a página e tente novamente.';
    } elseif (!empty($usuario) && !empty($senha)) {
        try {
            // Procura o utilizador na base de dados
            $stmt = $pdo->prepare("SELECT * FROM administradores WHERE usuario = ?");
            $stmt->execute([$usuario]);
            $admin = $stmt->fetch();

            // Toda conta de admin já foi migrada pra bcrypt (ver
            // harness/db/incidente-2026-09-22-saneamento-producao.sql) — não existe
            // mais fallback pra MD5/texto puro. Só password_verify() a partir daqui.
            if ($admin && password_verify($senha, $admin['senha'])) {
                // Se o hash foi gerado com parâmetros mais fracos do que os atuais
                // (custo menor, ou versão antiga do bcrypt), regrava com os
                // parâmetros de hoje de forma transparente, sem pedir nada a quem
                // logou. Mantém a senha sempre com o hashing mais forte disponível.
                if (password_needs_rehash($admin['senha'], PASSWORD_BCRYPT, ['cost' => 12])) {
                    $novoHash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
                    $stmtUpgrade = $pdo->prepare("UPDATE administradores SET senha = ? WHERE id = ?");
                    $stmtUpgrade->execute([$novoHash, $admin['id']]);
                }

                session_regenerate_id(true);
                $_SESSION['admin_logged'] = true;
                $_SESSION['admin_user'] = $admin['usuario'];

                header('Location: /admin/index.php');
                exit;
            } else {
                $erro = 'Usuário ou senha incorretos.';
            }
        } catch (PDOException $e) {
            $erro = 'Erro ao processar login. Tente novamente em instantes.';
        }
    } else {
        $erro = 'Preencha todos os campos.';
    }
}

$pageTitle = 'Login Administrativo | Jornada Acadêmica Imersão FAMETRO';
$pageNoIndex = true;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-xl py-5">
  <div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
      <div class="card shadow-sm border-0">
        <div class="card-body p-4">
          <div class="text-center mb-4">
            <h2 class="text-fametro-blue font-weight-bold">Painel ADM</h2>
            <p class="text-muted small">Jornada Acadêmica Imersão FAMETRO</p>
          </div>

          <?php if (!empty($erro)): ?>
            <div class="alert alert-danger alert-dismissible" role="alert">
              <i class="ti ti-alert-circle me-2"></i><?= $erro ?>
            </div>
          <?php endif; ?>

          <form action="/admin/login.php" method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(gerarTokenCSRF()) ?>">

            <div class="mb-3">
              <label class="form-label required">Usuário</label>
              <input type="text" name="usuario" class="form-control" placeholder="admin" required autofocus>
            </div>

            <div class="mb-3">
              <label class="form-label required">Senha</label>
              <input type="password" name="senha" class="form-control" placeholder="••••••••" required>
            </div>

            <div class="form-footer mt-4">
              <button type="submit" class="btn btn-fametro-red w-100">
                <i class="ti ti-lock-open me-1"></i> Entrar no Sistema
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>