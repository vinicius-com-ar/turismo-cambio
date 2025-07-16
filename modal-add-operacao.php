<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../recursos/conexao/index.php';
    session_start();

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // DEBUG SESSÃO
    error_log('[DEBUG SESSION] ' . json_encode($_SESSION));

    $idEmpresa   = $_SESSION['idEmpresa'] ?? null;
    $usuario_id  = $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? 49);

    error_log("[DEBUG USUARIO] usuario_id: $usuario_id | idEmpresa: $idEmpresa");

    if (!$usuario_id) {
        echo json_encode(['success' => false, 'message' => 'Usuário não autenticado!', 'sessao' => $_SESSION]);
        exit;
    }
    if (!$idEmpresa) {
        echo json_encode(['success' => false, 'message' => 'Empresa não autenticada!', 'sessao' => $_SESSION]);
        exit;
    }

    $tipo             = $_POST['tipo'] ?? '';
    $moeda_origem     = $_POST['moeda_origem'] ?? '';
    $moeda_destino    = $_POST['moeda_destino'] ?? '';
    $id_conta_origem  = $_POST['id_conta_origem'] ?? null;
    $id_conta_destino = $_POST['id_conta_destino'] ?? null;
    $valor_origem     = floatval(str_replace(',', '.', $_POST['valor_origem'] ?? 0));
    $cotacao_praticada= floatval(str_replace(',', '.', $_POST['cotacao_praticada'] ?? 0));
    $cotacao_ref      = floatval(str_replace(',', '.', $_POST['cotacao_ref'] ?? 0));
    $valor_destino    = floatval(str_replace(',', '.', $_POST['valor_destino'] ?? 0));
    $lucro            = floatval(str_replace(',', '.', $_POST['lucro'] ?? 0));

    if (!$tipo || !$moeda_origem || !$moeda_destino || !$id_conta_origem || !$id_conta_destino) {
        echo json_encode(['success' => false, 'message' => 'Preencha todos os campos obrigatórios!']);
        exit;
    }
    if ($valor_origem <= 0 || $cotacao_praticada <= 0) {
        echo json_encode(['success' => false, 'message' => 'Valor de origem e cotação devem ser positivos!']);
        exit;
    }
    if ($moeda_origem === $moeda_destino) {
        echo json_encode(['success' => false, 'message' => 'Moeda de origem e destino devem ser diferentes!']);
        exit;
    }

    $parametros = [
        $idEmpresa,
        $moeda_origem,
        $moeda_destino,
        $valor_origem,
        $valor_destino,
        $cotacao_praticada,
        $cotacao_ref,
        $cotacao_praticada,
        $tipo,
        $id_conta_origem,
        $id_conta_destino,
        null,
        $lucro,
        $usuario_id
    ];

    error_log('[DEBUG PARAMS] ' . json_encode($parametros));

    try {
        $pdo->exec("SET @user_id = " . intval($usuario_id));
        $pdo->exec("SET @user_ip = '" . addslashes($_SERVER['REMOTE_ADDR']) . "'");
        $pdo->exec("SET @user_agent = '" . addslashes($_SERVER['HTTP_USER_AGENT']) . "'");

        $sql = "INSERT INTO cambio_operacoes (
            idEmpresa, moeda_origem, moeda_destino, valor_origem, valor_destino, 
            cotacao, cotacao_ref, cotacao_praticada, tipo, id_conta_origem, 
            id_conta_destino, id_compra_origem, lucro, id_usuario, data_operacao
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $ok = $stmt->execute($parametros);

        if ($ok) {
            $id = $pdo->lastInsertId();
            echo json_encode([
                'success' => true,
                'message' => 'Operação cadastrada com sucesso!',
                'operacao' => [
                    'id' => $id,
                    'data_operacao' => date('Y-m-d H:i:s'),
                    'tipo' => $tipo,
                    'moeda_origem' => $moeda_origem,
                    'moeda_destino' => $moeda_destino,
                    'valor_origem' => $valor_origem,
                    'valor_destino' => $valor_destino,
                    'cotacao' => $cotacao_praticada,
                    'cotacao_ref' => $cotacao_ref,
                    'lucro' => $lucro
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao cadastrar operação.',
                'errorInfo' => $stmt->errorInfo(),
                'params' => $parametros
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erro no banco de dados: ' . $e->getMessage(),
            'errorInfo' => isset($stmt) ? $stmt->errorInfo() : null,
            'params' => $parametros
        ]);
    }
    exit;
}

// Carrega contas (para selects)
if (!isset($contas)) {
    require_once '../recursos/conexao/index.php';
    session_start();
    $idEmpresa = $_SESSION['idEmpresa'] ?? null;

    if ($idEmpresa) {
        $stmt = $pdo->prepare("SELECT id, nome, moeda, tipo FROM cambio_contas WHERE idEmpresa = ? AND ativo = 1");
        $stmt->execute([$idEmpresa]);
        $contas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $contas = [];
    }
}
?>

<!-- Modal HTML -->
<div class="modal fade" id="modalAddOperacao" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form id="formAddOperacao" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Nova Operação</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="container-fluid">
          <div id="alert-area"></div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Tipo de Operação</label>
              <select name="tipo" id="tipo" class="form-control" required>
                <option value="">Selecione</option>
                <option value="COMPRA">COMPRA</option>
                <option value="VENDA">VENDA</option>
              </select>
              <div class="form-text">
                <b>COMPRA:</b> O cliente entrega moeda estrangeira (ex: BRL/USD), recebe pesos.<br>
                <b>VENDA:</b> O cliente entrega pesos, recebe moeda estrangeira.
              </div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Moeda de Origem</label>
              <select name="moeda_origem" id="moeda_origem" class="form-control" required>
                <option value="">Selecione</option>
                <option value="BRL">BRL</option>
                <option value="ARS">ARS</option>
                <option value="USD">USD</option>
                <option value="EUR">EUR</option>
                <option value="CLP">CLP</option>
                <option value="UYU">UYU</option>
              </select>
              <div class="form-text">Moeda que o cliente está entregando para a casa de câmbio</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Moeda de Destino</label>
              <select name="moeda_destino" id="moeda_destino" class="form-control" required>
                <option value="">Selecione</option>
                <option value="BRL">BRL</option>
                <option value="ARS">ARS</option>
                <option value="USD">USD</option>
                <option value="EUR">EUR</option>
                <option value="CLP">CLP</option>
                <option value="UYU">UYU</option>
              </select>
              <div class="form-text">Moeda que o cliente vai receber</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Conta de Origem</label>
              <select name="id_conta_origem" id="id_conta_origem" class="form-control" required>
                <option value="">Selecione</option>
                <?php foreach ($contas as $c): ?>
                  <option value="<?= htmlspecialchars($c['id']) ?>" data-moeda="<?= htmlspecialchars($c['moeda']) ?>">
                    <?= htmlspecialchars($c['nome']) ?> (<?= htmlspecialchars($c['moeda']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Conta de onde sairá o dinheiro</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Conta de Destino</label>
              <select name="id_conta_destino" id="id_conta_destino" class="form-control" required>
                <option value="">Selecione</option>
                <?php foreach ($contas as $c): ?>
                  <option value="<?= htmlspecialchars($c['id']) ?>" data-moeda="<?= htmlspecialchars($c['moeda']) ?>">
                    <?= htmlspecialchars($c['nome']) ?> (<?= htmlspecialchars($c['moeda']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Conta para onde vai o dinheiro</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Valor de Origem</label>
              <input type="text" inputmode="decimal" name="valor_origem" id="valor_origem" class="form-control" required>
              <div class="form-text">Quanto o cliente entrega</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Valor de Destino</label>
              <input type="text" inputmode="decimal" name="valor_destino" id="valor_destino" class="form-control" readonly>
              <div class="form-text">Quanto o cliente recebe</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Lucro estimado</label>
              <input type="text" inputmode="decimal" name="lucro" id="lucro" class="form-control" readonly>
              <div class="form-text">Calculado automaticamente</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Cotação de Referência</label>
              <input type="text" inputmode="decimal" name="cotacao_ref" id="cotacao_ref" class="form-control" readonly>
              <div class="form-text">
                <b>Lógica:</b>
                <ul class="mb-0">
                  <li><b>COMPRA</b>: Usa <b>valor_compra</b> da moeda de destino (estrangeira)</li>
                  <li><b>VENDA</b>: Usa <b>valor_venda</b> da moeda de destino (estrangeira)</li>
                </ul>
                <small>Exemplo: COMPRA BRL → ARS: Cotação ref = valor_compra do BRL.<br>
                VENDA ARS → BRL: Cotação ref = valor_venda do BRL.</small>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Cotação Praticada</label>
              <input type="text" inputmode="decimal" name="cotacao_praticada" id="cotacao_praticada" class="form-control" required>
              <div class="form-text">Cotação aplicada para o cliente</div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Salvar</button>
      </div>
    </form>
  </div>
</div>

<script>
function formatNumber(num) {
    return Number(num).toFixed(2).replace('.', ',');
}

function calcularOperacao() {
    const tipo = document.getElementById('tipo').value;
    const moedaOrigem = document.getElementById('moeda_origem').value;
    const moedaDestino = document.getElementById('moeda_destino').value;
    const valorOrigem = parseFloat((document.getElementById('valor_origem').value || '').replace(',', '.')) || 0;
    const cotacaoPraticada = parseFloat((document.getElementById('cotacao_praticada').value || '').replace(',', '.')) || 0;

    // Pega cotações do dia
    let cotacoesArr = window.cotacoesJSON || [];

    // A moeda estrangeira é sempre a moeda de destino se não for ARS (base do painel)
    let moedaEstrangeira = (moedaDestino !== "ARS") ? moedaDestino : moedaOrigem;
    let cotacao = cotacoesArr.find(c => c.moeda === moedaEstrangeira);

    let cotacaoRef = 0;

    if (cotacao && tipo && moedaEstrangeira !== "ARS") {
        // CORRETO: COMPRA usa valor_compra, VENDA usa valor_venda
        if (tipo === "COMPRA") {
            cotacaoRef = parseFloat(cotacao.valor_compra);
        } else if (tipo === "VENDA") {
            cotacaoRef = parseFloat(cotacao.valor_venda);
        }
        document.getElementById('cotacao_ref').value = cotacaoRef ? cotacaoRef.toFixed(4) : '';
    } else {
        document.getElementById('cotacao_ref').value = '';
    }

    // Calcula valores de destino e lucro
    if (valorOrigem > 0 && cotacaoPraticada > 0 && cotacaoRef > 0) {
        let valorDestino, lucro;
        if (tipo === "COMPRA") {
            valorDestino = valorOrigem * cotacaoPraticada;
            lucro = (cotacaoRef - cotacaoPraticada) * valorOrigem;
        } else {
            valorDestino = valorOrigem / cotacaoPraticada;
            lucro = (cotacaoPraticada - cotacaoRef) * valorOrigem;
        }
        document.getElementById('valor_destino').value = formatNumber(valorDestino);
        document.getElementById('lucro').value = formatNumber(lucro);
    } else {
        document.getElementById('valor_destino').value = '';
        document.getElementById('lucro').value = '';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    ['tipo','moeda_origem','moeda_destino','valor_origem','cotacao_praticada'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', calcularOperacao);
            el.addEventListener('input', calcularOperacao);
        }
    });
    calcularOperacao();

    // Configura o envio do formulário
    const form = document.getElementById('formAddOperacao');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(form);
            fetch('modal-add-operacao.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return response.json();
            })
            .then(data => {
                const alertArea = document.getElementById('alert-area');
                if (data.success) {
                    // Fecha o modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('modalAddOperacao'));
                    if (modal) modal.hide();
                    // Limpa o formulário
                    form.reset();
                    alertArea.innerHTML = `<div class="alert alert-success alert-dismissible fade show">${data.message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
                    if (typeof adicionarOperacaoNaTabela === 'function') {
                        adicionarOperacaoNaTabela(data.operacao);
                    }
                } else {
                    alertArea.innerHTML = `<div class="alert alert-danger alert-dismissible fade show">${data.message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
                }
            })
            .catch(error => {
                document.getElementById('alert-area').innerHTML = `<div class="alert alert-danger alert-dismissible fade show">Erro ao processar operação: ${error.message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
            });
        });
    }
});
</script>
