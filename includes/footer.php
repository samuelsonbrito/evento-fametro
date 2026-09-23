<footer class="footer footer-transparent d-print-none mt-auto py-3 border-top">
          <div class="container-xl text-center">
            <p class="mb-0 text-muted">&copy; <?= date('Y') ?> <strong>Jornada Acadêmica Imersão FAMETRO</strong>. 2 de Outubro.</p>
          </div>
        </footer>
      </div>
    </div>

    <!-- Botão flutuante "Reportar problema" -->
    <button type="button" class="btn btn-fametro-red rounded-pill shadow d-print-none d-flex align-items-center gap-1"
            style="position: fixed; right: 1.25rem; bottom: 1.25rem; z-index: 1030; padding: 0.6rem 1rem;"
            data-bs-toggle="modal" data-bs-target="#modalReportarErro">
      <i class="ti ti-message-report fs-4"></i>
      <span class="d-none d-sm-inline">Reportar problema</span>
    </button>

    <div class="modal modal-blur fade" id="modalReportarErro" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form id="formReportarErro" novalidate>
            <div class="modal-header">
              <h5 class="modal-title"><i class="ti ti-message-report me-1"></i> Reportar um problema</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
              <div id="reportarErroAlerta" class="alert d-none mb-3" role="alert"></div>

              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(gerarTokenCSRF()) ?>">
              <input type="hidden" name="pagina_url" value="">

              <div class="mb-3">
                <label class="form-label">Tipo</label>
                <select name="tipo" class="form-select">
                  <option value="erro" selected>Erro técnico</option>
                  <option value="sugestao">Sugestão</option>
                  <option value="outro">Outro</option>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label required">O que aconteceu?</label>
                <textarea name="mensagem" class="form-control" rows="4" maxlength="2000" required placeholder="Descreva o problema que você encontrou..."></textarea>
              </div>

              <div class="mb-3">
                <label class="form-label">Seu nome (opcional)</label>
                <input type="text" name="nome" class="form-control" maxlength="150">
              </div>

              <div class="mb-0">
                <label class="form-label">Seu e-mail (opcional, caso queira retorno)</label>
                <input type="email" name="email" class="form-control" maxlength="150">
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-fametro-red">
                <i class="ti ti-send me-1"></i> Enviar relato
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/js/tabler.min.js"></script>
    <script>
      (function () {
        var modalEl = document.getElementById('modalReportarErro');
        var form = document.getElementById('formReportarErro');
        var alerta = document.getElementById('reportarErroAlerta');
        if (!modalEl || !form) return;

        modalEl.querySelector('input[name="pagina_url"]').value = window.location.href;

        function mostrarAlerta(tipo, texto) {
          alerta.className = 'alert alert-' + tipo + ' mb-3';
          alerta.textContent = texto;
        }

        form.addEventListener('submit', function (ev) {
          ev.preventDefault();
          var botao = form.querySelector('button[type="submit"]');
          botao.disabled = true;

          fetch('/api/reportar_erro.php', {
            method: 'POST',
            body: new FormData(form),
          })
            .then(function (resp) { return resp.json().then(function (data) { return { status: resp.status, data: data }; }); })
            .then(function (res) {
              if (res.data && res.data.ok) {
                mostrarAlerta('success', 'Relato enviado, obrigado por avisar!');
                form.reset();
                setTimeout(function () {
                  var instance = bootstrap.Modal.getOrCreateInstance(modalEl);
                  instance.hide();
                  alerta.className = 'alert d-none mb-3';
                }, 1500);
              } else {
                mostrarAlerta('danger', (res.data && res.data.erro) || 'Não foi possível enviar. Tente novamente.');
              }
            })
            .catch(function () {
              mostrarAlerta('danger', 'Falha de conexão. Tente novamente em instantes.');
            })
            .finally(function () {
              botao.disabled = false;
            });
        });
      })();
    </script>
  </body>
</html>