<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = sanitize($_POST['usuario'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (!empty($usuario) && !empty($senha)) {
        try {
            // Procura o utilizador na base de dados
            $stmt = $pdo->prepare("SELECT * FROM administradores WHERE usuario = ?");
            $stmt->execute([$usuario]);
            $admin = $stmt->fetch();

            $autenticado = false;

            if ($admin && password_verify($senha, $admin['senha'])) {
                $autenticado = true;
            } elseif ($admin && ($admin['senha'] === md5($senha) || $admin['senha'] === $senha)) {
                // Migração transparente: a senha ainda está em MD5/texto puro (herança
                // de um banco antigo). Como a senha confere, aproveita este login pra
                // gerar um hash bcrypt novo e substituir na hora — ninguém precisa
                // trocar a senha que já usa, e o texto puro/MD5 some do banco.
                $autenticado = true;
                $novoHash = password_hash($senha, PASSWORD_DEFAULT);
                $stmtUpgrade = $pdo->prepare("UPDATE administradores SET senha = ? WHERE id = ?");
                $stmtUpgrade->execute([$novoHash, $admin['id']]);
            }

            if ($autenticado) {
                session_regenerate_id(true);
                $_SESSION['admin_logged'] = true;
                $_SESSION['admin_user'] = $admin['usuario'];

                header('Location: /evento-fametro/admin/index.php');
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

          <form action="/evento-fametro/admin/login.php" method="POST" autocomplete="off">
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