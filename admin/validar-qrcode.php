
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

checarAutenticacaoAdmin();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold text-fametro-blue m-0">
      <i class="ti ti-qrcode me-2"></i>Validador de QR Code
    </h2>
    <a href="/evento-fametro/admin/index.php" class="btn btn-secondary rounded-3">
      <i class="ti ti-arrow-left me-1"></i> Painel
    </a>
  </div>

  <div class="row justify-content-center">
    <div class="col-md-7">
      <div class="card shadow border-0 rounded-4 p-4 text-center">
        <h5 class="fw-bold mb-2">Aproxime o QR Code da Câmera</h5>
        <p class="text-muted small mb-3">O leitor está ativo continuamente para leituras em sequência.</p>
        
        <!-- Área do Leitor de Vídeo -->
        <div id="reader" style="width: 100%; min-height: 320px; background: #1e1e2d;" class="rounded-3 border overflow-hidden position-relative mb-3"></div>
        
        <!-- Mensagem de Resultado da Leitura -->
        <div id="resultadoValidacao" class="mt-3 d-none"></div>

        <hr class="my-4">

        <form id="formManual">
          <label class="form-label fw-bold">Ou digite / leitor manual de código:</label>
          <div class="input-group">
            <input type="text" id="codigoManual" class="form-control rounded-start-3" placeholder="Ex: QR-6AB1D50B9BD1A..." autocomplete="off" required>
            <button type="submit" class="btn btn-primary rounded-end-3 fw-bold">
              <i class="ti ti-check me-1"></i> Validar
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://unpkg.com/html5-qrcode"></script>
<script>
let processando = false;
let ultimoCodigoLido = "";
let timerLimpeza = null;
let html5QrCode = null;

const audioSucesso = new Audio('https://actions.google.com/sounds/v1/cartoon/clack.ogg');
const audioErro = new Audio('https://actions.google.com/sounds/v1/emergency/beeps_high_pitch.ogg');

function extrairCodigoLimpo(texto) {
    if (!texto) return "";
    texto = texto.trim();
    
    try {
        if (texto.includes('http://') || texto.includes('https://')) {
            const url = new URL(texto);
            const codigo = url.searchParams.get("codigo") || url.searchParams.get("codigo_qrcode") || url.searchParams.get("id");
            if (codigo) return codigo.trim();
        }
    } catch (e) {}
    return texto;
}

function enviarCodigo(codigoBruto) {
    const codigo = extrairCodigoLimpo(codigoBruto);
    
    // Ignora se for o mesmo código lido nos últimos segundos ou se já estiver enviando
    if (!codigo || processando || codigo === ultimoCodigoLido) return;
    
    processando = true;
    ultimoCodigoLido = codigo;

    // Reseta o temporizador de duplicados (após 2.5s permite ler o mesmo código novamente se necessário)
    if (timerLimpeza) clearTimeout(timerLimpeza);
    timerLimpeza = setTimeout(() => {
        ultimoCodigoLido = "";
    }, 2500);

    const resDiv = document.getElementById('resultadoValidacao');
    resDiv.classList.remove('d-none');
    resDiv.innerHTML = '<div class="alert alert-info border-0 shadow-sm fw-bold mb-0"><i class="ti ti-loader me-2 spinner-border spinner-border-sm"></i> Validando...</div>';

    const formData = new FormData();
    formData.append('codigo_qrcode', codigo);

    fetch('/evento-fametro/api/validar_presenca.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        processando = false; // Libera imediatamente para a próxima leitura de OUTRO código

        if (data.sucesso) {
            try { audioSucesso.play(); } catch(e){}
            resDiv.innerHTML = `
                <div class="alert alert-success border-0 shadow-lg text-center p-3 rounded-4 mb-0">
                    <div class="h4 fw-bold text-success mb-1">
                        <i class="ti ti-circle-check-filled me-2"></i>AUTENTICADA
                    </div>
                    <p class="fs-5 fw-bold mb-1 text-dark">${data.aluno || 'Participante'}</p>
                    <p class="text-muted small mb-0">Palestra: <strong>${data.palestra || 'Palestra FAMETRO'}</strong></p>
                </div>`;
        } else {
            try { audioErro.play(); } catch(e){}
            resDiv.innerHTML = `
                <div class="alert alert-danger border-0 shadow-lg text-center p-3 rounded-4 mb-0">
                    <div class="h4 fw-bold text-danger mb-1">
                        <i class="ti ti-alert-circle-filled me-2"></i>NÃO AUTENTICADA
                    </div>
                    <p class="fs-6 fw-bold mb-1">${data.mensagem || 'Código inválido.'}</p>
                    ${data.aluno ? `<p class="small mb-0 text-dark">Aluno: <strong>${data.aluno}</strong></p>` : ''}
                </div>`;
        }
    })
    .catch(err => {
        processando = false;
        try { audioErro.play(); } catch(e){}
        resDiv.innerHTML = `
            <div class="alert alert-warning border-0 shadow text-center p-3 mb-0">
                <h6 class="fw-bold mb-0"><i class="ti ti-wifi-off me-2"></i>Erro de Conexão com o Servidor</h6>
            </div>`;
    });
}

document.getElementById('formManual').addEventListener('submit', function(e) {
    e.preventDefault();
    const codigoInput = document.getElementById('codigoManual');
    const codigo = codigoInput.value.trim();
    if (codigo) {
        ultimoCodigoLido = ""; // Libera validação manual
        enviarCodigo(codigo);
        codigoInput.value = '';
    }
});

function iniciarLeitorCamara() {
    html5QrCode = new Html5Qrcode("reader");

    Html5Qrcode.getCameras().then(devices => {
        if (devices && devices.length) {
            const cameraId = devices[0].id;
            
            html5QrCode.start(
                cameraId, 
                {
                    fps: 20, // Leitura ultra rápida
                    qrbox: { width: 250, height: 250 }
                },
                (decodedText) => {
                    enviarCodigo(decodedText);
                },
                (errorMessage) => {}
            ).catch(err => {
                console.error("Erro ao iniciar câmera:", err);
            });
        } else {
            document.getElementById('reader').innerHTML = '<div class="p-4 text-white">Nenhuma câmara detetada. Use a validação manual.</div>';
        }
    }).catch(err => {
        console.error("Erro nas câmeras:", err);
    });
}

window.addEventListener('load', iniciarLeitorCamara);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
