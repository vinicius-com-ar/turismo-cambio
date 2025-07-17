<?php
// Processar POST se for envio do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'nova_operacao') {
    require_once '../recursos/conexao/index.php';
    session_start();

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $idEmpresa   = $_SESSION['idEmpresa'] ?? null;
    $usuario_id  = $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? 49);

    if (!$usuario_id) {
        echo json_encode(['success' => false, 'message' => 'Usuário não autenticado!']);
        exit;
    }
    if (!$idEmpresa) {
        echo json_encode(['success' => false, 'message' => 'Empresa não autenticada!']);
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

    try {
        $pdo->exec("SET @user_id = " . intval($usuario_id));
        
        $sql = "INSERT INTO cambio_operacoes (
            idEmpresa, moeda_origem, moeda_destino, valor_origem, valor_destino, 
            cotacao, cotacao_ref, cotacao_praticada, tipo, id_conta_origem, 
            id_conta_destino, id_compra_origem, lucro, id_usuario, data_operacao
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $ok = $stmt->execute([
            $idEmpresa, $moeda_origem, $moeda_destino, $valor_origem, $valor_destino,
            $cotacao_praticada, $cotacao_ref, $cotacao_praticada, $tipo, 
            $id_conta_origem, $id_conta_destino, null, $lucro, $usuario_id
        ]);

        if ($ok) {
            echo json_encode(['success' => true, 'message' => 'Operaão cadastrada com sucesso!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao cadastrar operação.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
    }
    exit;
}

// Carregar dados necessrios
if (!isset($contas)) {
    require_once '../recursos/conexao/index.php';
    session_start();
    $idEmpresa = $_SESSION['idEmpresa'] ?? null;

    if ($idEmpresa) {
        $stmt = $pdo->prepare("SELECT id, nome, moeda, tipo FROM cambio_contas WHERE idEmpresa = ? AND ativo = 1");
        $stmt->execute([$idEmpresa]);
        $contas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar cotações do dia
        $stmt = $pdo->prepare("SELECT moeda, valor_compra, valor_venda FROM cambio_cotacoes WHERE idEmpresa = ? AND data_cotacao = CURDATE()");
        $stmt->execute([$idEmpresa]);
        $cotacoes_dia = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $contas = [];
        $cotacoes_dia = [];
    }
}
?>

<!-- Modal Adicionar Operação -->
<div class="modal-overlay" id="modalAddOperacao">
    <div class="modal max-w-4xl">
        <form id="formAddOperacao" autocomplete="off" method="post">
            <input type="hidden" name="action" value="nova_operacao">
            
            <!-- Header -->
            <div class="p-6 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center">
                <h3 class="text-xl font-semibold text-slate-800 dark:text-slate-200">Nova Operaço</h3>
                <button type="button" id="closeModalOperacao" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                    <i class="fa-solid fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Body -->
            <div class="p-6">
                <div id="alert-area-operacao" class="mb-4"></div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Tipo de Operação -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Tipo de Operação</label>
                        <select name="tipo" id="tipo_operacao" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100" required>
                            <option value="">Selecione</option>
                            <option value="COMPRA">COMPRA</option>
                            <option value="VENDA">VENDA</option>
                        </select>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            <strong>COMPRA:</strong> Cliente entrega moeda estrangeira, recebe pesos.<br>
                            <strong>VENDA:</strong> Cliente entrega pesos, recebe moeda estrangeira.
                        </p>
                    </div>
                    
                    <!-- Moeda Origem -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Moeda de Origem</label>
                        <select name="moeda_origem" id="moeda_origem_operacao" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100" required>
                            <option value="">Selecione</option>
                            <option value="BRL">BRL</option>
                            <option value="ARS">ARS</option>
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="CLP">CLP</option>
                            <option value="UYU">UYU</option>
                        </select>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Moeda que o cliente está entregando</p>
                    </div>
                    
                    <!-- Moeda Destino -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Moeda de Destino</label>
                        <select name="moeda_destino" id="moeda_destino_operacao" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100" required>
                            <option value="">Selecione</option>
                            <option value="BRL">BRL</option>
                            <option value="ARS">ARS</option>
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="CLP">CLP</option>
                            <option value="UYU">UYU</option>
                        </select>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Moeda que o cliente vai receber</p>
                    </div>
                    
                    <!-- Conta Origem -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Conta de Origem</label>
                        <select name="id_conta_origem" id="id_conta_origem_operacao" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100" required>
                            <option value="">Selecione uma conta</option>
                            <?php foreach ($contas as $c): ?>
                                <option value="<?= htmlspecialchars($c['id']) ?>" data-moeda="<?= htmlspecialchars($c['moeda']) ?>">
                                    <?= htmlspecialchars($c['nome']) ?> (<?= htmlspecialchars($c['moeda']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Conta de onde sairá o dinheiro</p>
                    </div>
                    
                    <!-- Conta Destino -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Conta de Destino</label>
                        <select name="id_conta_destino" id="id_conta_destino_operacao" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100" required>
                            <option value="">Selecione uma conta</option>
                            <?php foreach ($contas as $c): ?>
                                <option value="<?= htmlspecialchars($c['id']) ?>" data-moeda="<?= htmlspecialchars($c['moeda']) ?>">
                                    <?= htmlspecialchars($c['nome']) ?> (<?= htmlspecialchars($c['moeda']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Conta para onde vai o dinheiro</p>
                    </div>
                    
                    <!-- Valor Origem -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Valor de Origem</label>
                        <input type="text" inputmode="decimal" name="valor_origem" id="valor_origem_operacao" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100" required>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Quanto o cliente entrega</p>
                    </div>
                    
                    <!-- Valor Destino -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Valor de Destino</label>
                        <input type="text" inputmode="decimal" name="valor_destino" id="valor_destino_operacao" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100" readonly>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Quanto o cliente recebe</p>
                    </div>
                    
                    <!-- Lucro -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Lucro Estimado</label>
                        <input type="text" inputmode="decimal" name="lucro" id="lucro_operacao" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100" readonly>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Calculado automaticamente</p>
                    </div>
                    
                    <!-- Cotação Referência -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Cotação de Referência</label>
                        <input type="text" inputmode="decimal" name="cotacao_ref" id="cotacao_ref_operacao" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100" readonly>
                        <div class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            <strong>Lógica:</strong>
                            <ul class="list-disc list-inside">
                                <li><strong>COMPRA</strong>: Usa valor_compra da moeda de destino</li>
                                <li><strong>VENDA</strong>: Usa valor_venda da moeda de destino</li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Cotação Praticada -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Cotação Praticada</label>
                        <input type="text" inputmode="decimal" name="cotacao_praticada" id="cotacao_praticada_operacao" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100" required>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Cotação aplicada para o cliente</p>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="p-6 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-3">
                <button type="button" id="cancelModalOperacao" class="px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm text-sm font-medium text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-md shadow-sm text-sm font-medium transition">
                    Salvar Operação
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s;
}

.modal-overlay.active {
    opacity: 1;
    pointer-events: all;
}

.modal {
    background: white;
    border-radius: 0.5rem;
    width: 100%;
    max-width: 48rem;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    transform: translateY(1rem);
    transition: transform 0.3s;
}

.dark .modal {
    background: #1e293b;
}

.modal-overlay.active .modal {
    transform: translateY(0);
}
</style>

<script>
// Dados das cotações do PHP
window.cotacoesOperacaoJSON = <?= json_encode($cotacoes_dia) ?>;

// Controle do Modal
const modalOperacao = document.getElementById('modalAddOperacao');
const closeModalOperacao = document.getElementById('closeModalOperacao');
const cancelModalOperacao = document.getElementById('cancelModalOperacao');

// Função para fechar modal
function fecharModalOperacao() {
    modalOperacao.classList.remove('active');
    document.getElementById('formAddOperacao').reset();
    document.getElementById('alert-area-operacao').innerHTML = '';
}

// Event listeners para fechar
closeModalOperacao.addEventListener('click', fecharModalOperacao);
cancelModalOperacao.addEventListener('click', fecharModalOperacao);

// Fechar ao clicar fora
modalOperacao.addEventListener('click', (e) => {
    if (e.target === modalOperacao) fecharModalOperacao();
});

// Função para formatar número
function formatNumberOperacao(num) {
    return Number(num).toFixed(2).replace('.', ',');
}

// Funão principal de cálculo
function calcularOperacaoModal() {
    const tipo = document.getElementById('tipo_operacao').value;
    const moedaOrigem = document.getElementById('moeda_origem_operacao').value;
    const moedaDestino = document.getElementById('moeda_destino_operacao').value;
    const valorOrigem = parseFloat((document.getElementById('valor_origem_operacao').value || '').replace(',', '.')) || 0;
    const cotacaoPraticada = parseFloat((document.getElementById('cotacao_praticada_operacao').value || '').replace(',', '.')) || 0;

    // Determinar moeda estrangeira
    let moedaEstrangeira = (moedaDestino !== "ARS") ? moedaDestino : moedaOrigem;
    let cotacao = window.cotacoesOperacaoJSON.find(c => c.moeda === moedaEstrangeira);

    let cotacaoRef = 0;

    if (cotacao && tipo && moedaEstrangeira !== "ARS") {
        if (tipo === "COMPRA") {
            cotacaoRef = parseFloat(cotacao.valor_compra);
        } else if (tipo === "VENDA") {
            cotacaoRef = parseFloat(cotacao.valor_venda);
        }
        document.getElementById('cotacao_ref_operacao').value = cotacaoRef ? cotacaoRef.toFixed(4) : '';
    } else {
        document.getElementById('cotacao_ref_operacao').value = '';
    }

    // Calcular valores
    if (valorOrigem > 0 && cotacaoPraticada > 0 && cotacaoRef > 0) {
        let valorDestino, lucro;
        if (tipo === "COMPRA") {
            valorDestino = valorOrigem * cotacaoPraticada;
            lucro = (cotacaoRef - cotacaoPraticada) * valorOrigem;
        } else {
            valorDestino = valorOrigem / cotacaoPraticada;
            lucro = (cotacaoPraticada - cotacaoRef) * valorOrigem;
        }
        document.getElementById('valor_destino_operacao').value = formatNumberOperacao(valorDestino);
        document.getElementById('lucro_operacao').value = formatNumberOperacao(lucro);
    } else {
        document.getElementById('valor_destino_operacao').value = '';
        document.getElementById('lucro_operacao').value = '';
    }
}

// Event listeners para cálculo automático
['tipo_operacao','moeda_origem_operacao','moeda_destino_operacao','valor_origem_operacao','cotacao_praticada_operacao'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('change', calcularOperacaoModal);
        el.addEventListener('input', calcularOperacaoModal);
    }
});

// Submit do formulário
document.getElementById('formAddOperacao').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const alertArea = document.getElementById('alert-area-operacao');
    
    // Mostrar loading
    alertArea.innerHTML = `
        <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded">
            <i class="fa-solid fa-spinner fa-spin mr-2"></i>Processando operação...
        </div>
    `;
    
    fetch('modal-add-operacao-tailwind.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alertArea.innerHTML = `
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    <i class="fa-solid fa-check mr-2"></i>${data.message}
                </div>
            `;
            
            // Limpar formulário
            this.reset();
            
            // Fechar modal e recarregar página após 2 segundos
            setTimeout(() => {
                fecharModalOperacao();
                window.location.reload();
            }, 2000);
        } else {
            alertArea.innerHTML = `
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <i class="fa-solid fa-exclamation-triangle mr-2"></i>${data.message}
                </div>
            `;
        }
    })
    .catch(error => {
        alertArea.innerHTML = `
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <i class="fa-solid fa-exclamation-triangle mr-2"></i>Erro ao processar operação: ${error.message}
            </div>
        `;
    });
});

// Função global para abrir o modal (chamada pelo botão flutuante)
window.abrirModalOperacao = function() {
    modalOperacao.classList.add('active');
};
</script>
