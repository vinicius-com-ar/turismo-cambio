<?php
require_once '../recursos/conexao/index.php';
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- AUTENTICAÇÃO ---
if (!isset($_SESSION['idEmpresa'])) die('Empresa não autenticada.');
$idEmpresa = $_SESSION['idEmpresa'];

// --- PAGINAÇÃO ---
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// --- POSTS (EXCLUIR, EDITAR, COMISSAO, GET_COMISSAO) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    try {
        // Exclusão
        if ($action === 'excluir' && isset($_POST['id'])) {
            $id = intval($_POST['id']);
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM cambio_comissoes WHERE id_operacao = ? AND idEmpresa = ?")->execute([$id, $idEmpresa]);
            $pdo->prepare("DELETE FROM cambio_operacoes WHERE id = ? AND idEmpresa = ?")->execute([$id, $idEmpresa]);
            $pdo->commit();
            echo json_encode(['success' => true]);
            exit;
        }
        // Edição
        if ($action === 'editar' && isset($_POST['id'])) {
            $id = intval($_POST['id']);
            $campos = ['moeda_origem','moeda_destino','valor_origem','valor_destino','cotacao_praticada','cotacao_ref','tipo','lucro'];
            $set = [];
            $params = [];
            foreach($campos as $c){
                // Se cotacao_ref for vazio ou null, salva 0.0000
                if ($c == 'cotacao_ref' && ($_POST[$c] === '' || is_null($_POST[$c]))) {
                    $params[] = 0.0000;
                } else {
                    $params[] = $_POST[$c];
                }
                $set[] = "$c = ?";
            }
            $params[] = $id;
            $params[] = $idEmpresa;
            $sql = "UPDATE cambio_operacoes SET ".implode(', ', $set)." WHERE id = ? AND idEmpresa = ?";
            $pdo->prepare($sql)->execute($params);
            echo json_encode(['success'=>true]);
            exit;
        }
        // Comissão
        if ($action === 'comissao' && isset($_POST['id_operacao'], $_POST['comissoes'])) {
            $id_operacao = intval($_POST['id_operacao']);
            $comissoes = json_decode($_POST['comissoes'], true);
            $pdo->exec("SET @user_id = " . intval($_SESSION['usuario_id'] ?? $_SESSION['id_usuario'] ?? 49));
            $pdo->exec("SET @user_ip = '" . addslashes($_SERVER['REMOTE_ADDR']) . "'");
            $pdo->exec("SET @user_agent = '" . addslashes($_SERVER['HTTP_USER_AGENT']) . "'");
            foreach ($comissoes as &$c) {
                $c['percentual'] = floatval(str_replace([',','%'],'',$c['percentual']));
                $c['valor'] = floatval(str_replace([',','R$','%'],'',$c['valor']));
                if(!$c['idPessoa']) $c['idPessoa'] = null;
            } unset($c);
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM cambio_comissoes WHERE idEmpresa = ? AND id_operacao = ?")->execute([$idEmpresa, $id_operacao]);
            foreach ($comissoes as $c) {
                $stmt = $pdo->prepare("INSERT INTO cambio_comissoes 
                    (idEmpresa, id_operacao, id_pessoa, tipo_pessoa, percentual, valor, pago) 
                    VALUES (?, ?, ?, ?, ?, ?, 0)");
                $stmt->execute([
                    $idEmpresa, $id_operacao, $c['idPessoa'] ?: null, $c['tipo'], $c['percentual'], $c['valor']
                ]);
            }
            $pdo->commit();
            echo json_encode(['success'=>true]);
            exit;
        }
        // GET comissão
        if ($action === 'get_comissao' && isset($_POST['id_operacao'])) {
            $id_operacao = intval($_POST['id_operacao']);
            $coms = $pdo->prepare("SELECT * FROM cambio_comissoes WHERE idEmpresa=? AND id_operacao=?");
            $coms->execute([$idEmpresa, $id_operacao]);
            echo json_encode($coms->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
        exit;
    }
}

// --- BUSCA PAGINADA ---
$total = $pdo->query("SELECT COUNT(*) FROM cambio_operacoes WHERE idEmpresa = $idEmpresa")->fetchColumn();
$stmt = $pdo->prepare("SELECT * FROM cambio_operacoes WHERE idEmpresa = ? ORDER BY data_operacao DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $idEmpresa, PDO::PARAM_INT);
$stmt->bindValue(2, $perPage, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$operacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$usuarios = $pdo->query("SELECT id, nome FROM usuarios WHERE idEmpresa = $idEmpresa AND ativo = 1")->fetchAll(PDO::FETCH_ASSOC);
$promotores = $pdo->query("SELECT id, nome FROM promotor WHERE idEmpresa = $idEmpresa AND ativo = 1")->fetchAll(PDO::FETCH_ASSOC);
$cotacoes = $pdo->query("SELECT moeda, valor_compra, valor_venda FROM cambio_cotacoes WHERE idEmpresa = $idEmpresa AND data_cotacao = CURDATE()")->fetchAll(PDO::FETCH_ASSOC);

// --- Função para data ---
function formatarData($data) {
    if (!$data) return '';
    $dt = new DateTime($data);
    return $dt->format('d/m H:i');
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Tabela de Operações</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f4f6fa;}
        .table-striped>tbody>tr:nth-of-type(odd) { background-color: #f9fafb; }
        .table-striped>tbody>tr:nth-of-type(even) { background-color: #fff; }
        .table thead { background: #f4f6fa; }
        .table td, .table th { vertical-align: middle; }
        .minimal-icon {
            background: none;
            border: none;
            color: #64748b;
            font-size: 1.2rem;
            padding: 0.22rem 0.38rem;
            margin: 0 1px;
            transition: color .15s;
            box-shadow: none !important;
        }
        .minimal-icon:hover, .minimal-icon:focus { color: #2465d8; background: none;}
        .lucro-neg { color: #ef4444 !important; font-weight: 500; }
        .lucro-pos { color: #22c55e !important; font-weight: 500; }
        .ref-cotacao { font-size: 0.8em; color: #888; line-height: 1; }
        .pagination .page-link { color: #2465d8; border-radius: 100px; border: none; margin:0 2px; }
        .pagination .page-link.active, .pagination .active>.page-link {
            background: #2465d8;
            color: #fff;
            border: none;
        }
        .pagination .page-item { margin:0;}
    </style>
</head>
<body>
<div class="container py-4">
    <h3 class="mb-4">Operações de Câmbio</h3>
    <div id="alert-area"></div>
    <div class="table-responsive">
        <table class="table align-middle table-striped border rounded shadow-sm" id="tabela">
            <thead>
                <tr>
                    <th class="text-secondary small">ID</th>
                    <th class="text-secondary small">Data</th>
                    <th class="text-secondary small">Tipo</th>
                    <th class="text-secondary small">Moedas</th>
                    <th class="text-secondary small">Valores</th>
                    <th class="text-secondary small">Cotação</th>
                    <th class="text-secondary small">Lucro</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tabela-operacoes">
                <?php foreach($operacoes as $op): 
                    $lucro = floatval($op['lucro']);
                ?>
                <tr id="row-<?= $op['id'] ?>">
                    <td><?= $op['id'] ?></td>
                    <td><?= formatarData($op['data_operacao']) ?></td>
                    <td><?= $op['tipo']=='COMPRA'
                        ? '<span class="badge bg-primary-subtle text-primary">Compra</span>'
                        : '<span class="badge bg-warning-subtle text-warning">Venda</span>' ?></td>
                    <td><?= htmlspecialchars($op['moeda_origem']) ?> <i class="bi bi-arrow-right-short"></i> <?= htmlspecialchars($op['moeda_destino']) ?></td>
                    <td>
                        <span><?= number_format($op['valor_origem'],2,',','.') ?></span>
                        <span class="mx-1">/</span>
                        <span><?= number_format($op['valor_destino'],2,',','.') ?></span>
                    </td>
                    <td>
                        <span><?= number_format($op['cotacao_praticada'],4,',','.') ?></span>
                        <div class="ref-cotacao">ref: <?= number_format($op['cotacao_ref'],4,',','.') ?></div>
                    </td>
                    <td>
                        <span class="<?= $lucro<0?'lucro-neg':'lucro-pos' ?>">
                        <?= number_format($lucro,2,',','.') ?>
                        </span>
                    </td>
                    <td class="text-end">
                        <button 
                            class="minimal-icon" 
                            title="Editar" 
                            type="button"
                            data-obj='<?= htmlspecialchars(json_encode($op), ENT_QUOTES, "UTF-8") ?>'
                            onclick="abrirModalEditar(this)"
                        >
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="minimal-icon" title="Comissão" onclick="abrirModalComissao(<?= $op['id'] ?>,<?= floatval($op['lucro']) ?>)">
                            <i class="bi bi-people"></i>
                        </button>
                        <button class="minimal-icon" title="Excluir" onclick="excluirOperacao(<?= $op['id'] ?>)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php $totalPages = ceil($total/$perPage);
    if ($totalPages > 1): ?>
    <nav class="d-flex justify-content-center mt-3">
      <ul class="pagination pagination-sm">
        <?php for($i=1;$i<=$totalPages;$i++): ?>
            <li class="page-item <?= $i==$page ? 'active' : '' ?>">
                <a class="page-link<?= $i==$page ? ' active' : '' ?>" href="?page=<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
      </ul>
    </nav>
    <?php endif; ?>
</div>

<!-- MODAIS -->
<div class="modal fade" id="modalEditar" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" id="formEditar" autocomplete="off">
      <div class="modal-header">
        <h5 class="modal-title">Editar Operação</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body row g-3">
        <input type="hidden" name="id" id="edit_id">
        <input type="hidden" name="action" value="editar">
        <div class="col-md-3">
          <label class="form-label">Tipo</label>
          <select name="tipo" id="edit_tipo" class="form-control" required>
            <option value="COMPRA">COMPRA</option>
            <option value="VENDA">VENDA</option>
          </select>
          <div class="form-text">Selecione o tipo da operação</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Moeda Origem</label>
          <select name="moeda_origem" id="edit_moeda_origem" class="form-control" required>
            <option value="BRL">BRL</option>
            <option value="ARS">ARS</option>
            <option value="USD">USD</option>
            <option value="EUR">EUR</option>
            <option value="CLP">CLP</option>
            <option value="UYU">UYU</option>
          </select>
          <div class="form-text">Moeda entregue</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Moeda Destino</label>
          <select name="moeda_destino" id="edit_moeda_destino" class="form-control" required>
            <option value="BRL">BRL</option>
            <option value="ARS">ARS</option>
            <option value="USD">USD</option>
            <option value="EUR">EUR</option>
            <option value="CLP">CLP</option>
            <option value="UYU">UYU</option>
          </select>
          <div class="form-text">Moeda recebida</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Valor Origem</label>
          <input type="number" step="0.01" name="valor_origem" id="edit_valor_origem" class="form-control" required>
          <div class="form-text">Valor entregue</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Valor Destino</label>
          <input type="number" step="0.01" name="valor_destino" id="edit_valor_destino" class="form-control" required readonly>
          <div class="form-text">Valor a receber (auto)</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Cotação</label>
          <input type="number" step="0.0001" name="cotacao_praticada" id="edit_cotacao" class="form-control" required>
          <div class="form-text">Cotação aplicada</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Cotação Ref.</label>
          <input type="number" step="0.0001" name="cotacao_ref" id="edit_cotacao_ref" class="form-control" readonly>
          <div class="form-text">Cotação do sistema</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Lucro</label>
          <input type="number" step="0.01" name="lucro" id="edit_lucro" class="form-control" readonly>
          <div class="form-text">Calculado automaticamente</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Salvar</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalComissao" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" id="formComissao">
      <div class="modal-header">
        <h5 class="modal-title">Comissão da Operação</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_operacao" id="id_operacao_comissao">
        <input type="hidden" name="action" value="comissao">
        <div id="comissoesModalLinhas"></div>
        <button type="button" class="btn btn-link" onclick="adicionarLinhaComissao()">+ Adicionar Comissão</button>
        <div class="alert alert-warning mt-2 d-none" id="avisoComissao">
            A soma dos percentuais deve ser 100%!
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Salvar</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const usuarios = <?= json_encode($usuarios) ?>;
const promotores = <?= json_encode($promotores) ?>;
const cotacoesDia = <?= json_encode($cotacoes) ?>;

// ------------ MODAL EDITAR --------------
function calcularCamposEditar(){
    let tipo = document.getElementById('edit_tipo').value;
    let moedaOrigem = document.getElementById('edit_moeda_origem').value;
    let moedaDestino = document.getElementById('edit_moeda_destino').value;
    let valorOrigem = parseFloat(document.getElementById('edit_valor_origem').value.replace(',','.'))||0;
    let cotacaoPraticada = parseFloat(document.getElementById('edit_cotacao').value.replace(',','.'))||0;
    let cotacaoRef = parseFloat(document.getElementById('edit_cotacao_ref').value.replace(',','.'))||0;
    let cotMoeda = cotacoesDia.find(c=>c.moeda=== (tipo==='COMPRA'? moedaDestino : moedaOrigem) );
    if (cotMoeda) {
        cotacaoRef = tipo==='COMPRA' ? parseFloat(cotMoeda.valor_venda) : parseFloat(cotMoeda.valor_compra);
        document.getElementById('edit_cotacao_ref').value = cotacaoRef.toFixed(4);
    }
    let valorDestino=0, lucro=0;
    if(valorOrigem && cotacaoPraticada){
        if(tipo==="COMPRA"){
            valorDestino = valorOrigem * cotacaoPraticada;
            lucro = (cotacaoRef - cotacaoPraticada) * valorOrigem;
        } else {
            valorDestino = valorOrigem / cotacaoPraticada;
            lucro = (cotacaoPraticada - cotacaoRef) * valorOrigem;
        }
    }
    document.getElementById('edit_valor_destino').value = valorDestino?valorDestino.toFixed(2):'';
    document.getElementById('edit_lucro').value = lucro?lucro.toFixed(2):'';
}

function abrirModalEditar(btn) {
    let op = JSON.parse(btn.dataset.obj);
    // DEBUG: mostrar o valor de cotacao_ref ao abrir o modal
    console.log('[DEBUG] abrindo modal, cotacao_ref da linha:', op.cotacao_ref, typeof op.cotacao_ref);
    document.getElementById('edit_id').value = op.id;
    document.getElementById('edit_tipo').value = op.tipo;
    document.getElementById('edit_moeda_origem').value = op.moeda_origem;
    document.getElementById('edit_moeda_destino').value = op.moeda_destino;
    document.getElementById('edit_valor_origem').value = op.valor_origem;
    document.getElementById('edit_cotacao').value = op.cotacao_praticada;

    // CORRETO: Mostra EXATAMENTE o valor da linha (nunca zera se vier um valor válido)
    let ref = (op.cotacao_ref !== null && op.cotacao_ref !== undefined && op.cotacao_ref !== '' && !isNaN(Number(op.cotacao_ref)))
        ? Number(op.cotacao_ref).toFixed(4)
        : '0.0000';
    document.getElementById('edit_cotacao_ref').value = ref;

    document.getElementById('edit_valor_destino').value = op.valor_destino;
    document.getElementById('edit_lucro').value = op.lucro;
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}
['edit_tipo','edit_moeda_origem','edit_moeda_destino','edit_valor_origem','edit_cotacao'].forEach(id=>{
    document.getElementById(id).addEventListener('change',calcularCamposEditar);
    document.getElementById(id).addEventListener('input',calcularCamposEditar);
});
document.getElementById('formEditar').onsubmit = function(e){
    e.preventDefault();
    const fd = new FormData(this);
    // Garante que cotacao_ref vai sempre como número válido
    let ref = document.getElementById('edit_cotacao_ref').value;
    if (ref === '' || isNaN(ref)) {
        fd.set('cotacao_ref', '0.0000');
    }
    fetch('tabela.php', {method:'POST', body:fd})
    .then(async r=>{
        let txt = await r.text();
        try { 
            let resp = JSON.parse(txt); 
            if(resp.success) location.reload(); 
            else alert('Erro ao salvar! ' + (resp.error||'')); 
        } catch(e){ 
            alert('Erro ao salvar (parse JSON)'); 
        }
    })
    .catch(err=>console.error('[DEBUG] CATCH editar:',err));
};

// ------------ EXCLUIR --------------
function excluirOperacao(id){
    if(!confirm('Excluir operação?')) return;
    fetch('tabela.php', {
        method:'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=excluir&id='+id
    })
    .then(async r=>{
        let txt = await r.text();
        try { 
            let res = JSON.parse(txt);
            if(res.success) document.getElementById('row-'+id).remove();
            else alert('Erro ao excluir! ' + (res.error||''));
        } catch(e){
            alert('Erro ao excluir (parse JSON)');
        }
    })
    .catch(err=>console.error('[DEBUG] CATCH excluir:',err));
}

// ----------- MODAL COMISSAO ----------
let linhasComissao = [];
let lucroTotalComissao = 0;
function abrirModalComissao(id_operacao, lucro) {
    linhasComissao = [];
    lucroTotalComissao = lucro;
    document.getElementById('id_operacao_comissao').value = id_operacao;
    fetch('tabela.php', {
        method:'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get_comissao&id_operacao='+id_operacao
    })
    .then(async r => {
        let txt = await r.text();
        try {
            let data = JSON.parse(txt);
            if (data.length > 0) {
                linhasComissao = data.map(c=>({
                    tipo: c.tipo_pessoa,
                    idPessoa: c.id_pessoa,
                    percentual: parseFloat(c.percentual),
                    valor: parseFloat(c.valor)
                }));
            } else {
                linhasComissao = [{tipo:'EMPRESA',idPessoa:'',percentual:100,valor:lucro}];
            }
            renderizarComissoesModal(lucro);
            new bootstrap.Modal(document.getElementById('modalComissao')).show();
        } catch (e) {
            linhasComissao = [{tipo:'EMPRESA',idPessoa:'',percentual:100,valor:lucro}];
            renderizarComissoesModal(lucro);
            new bootstrap.Modal(document.getElementById('modalComissao')).show();
        }
    });
}
function adicionarLinhaComissao() {
    linhasComissao.push({tipo:'EMPRESA',idPessoa:'',percentual:0,valor:0});
    renderizarComissoesModal(lucroTotalComissao);
}
function removerLinhaComissao(i) {
    if (linhasComissao.length>1) {
        linhasComissao.splice(i,1);
        renderizarComissoesModal(lucroTotalComissao);
    }
}
function atualizarLinhaComissao(i, campo, val, lucro=0){
    if(campo==='percentual') {
        val = Math.max(0,Math.min(100,parseFloat(val)||0));
        linhasComissao[i].percentual = val;
        linhasComissao[i].valor = ((val*lucroTotalComissao)/100);
    } else {
        linhasComissao[i][campo]=val;
    }
    renderizarComissoesModal(lucroTotalComissao);
}
function renderizarComissoesModal(lucro=0) {
    let html = '';
    let tipos = ['EMPRESA','FUNCIONARIO','PROMOTOR'];
    let somaPercentual = 0;
    linhasComissao.forEach((l,i)=>{
        somaPercentual += parseFloat(l.percentual)||0;
        let selectPessoa = '';
        if(l.tipo==='EMPRESA') selectPessoa = 'Empresa';
        else if(l.tipo==='FUNCIONARIO') {
            selectPessoa = `<select class="form-select" onchange="atualizarLinhaComissao(${i},'idPessoa',this.value,${lucro})">
            <option value="">Selecione</option>
            ${usuarios.map(u=>`<option value="${u.id}"${l.idPessoa==u.id?' selected':''}>${u.nome}</option>`).join('')}
            </select>`;
        } else if(l.tipo==='PROMOTOR') {
            selectPessoa = `<select class="form-select" onchange="atualizarLinhaComissao(${i},'idPessoa',this.value,${lucro})">
            <option value="">Selecione</option>
            ${promotores.map(u=>`<option value="${u.id}"${l.idPessoa==u.id?' selected':''}>${u.nome}</option>`).join('')}
            </select>`;
        }
        html += `<div class="row mb-2 align-items-center">
            <div class="col-md-3">
                <select class="form-select" onchange="atualizarLinhaComissao(${i},'tipo',this.value,${lucro})">
                    ${tipos.map(t=>`<option value="${t}"${l.tipo===t?' selected':''}>${t}</option>`).join('')}
                </select>
            </div>
            <div class="col-md-3">${selectPessoa}</div>
            <div class="col-md-2">
                <input type="number" min="0" max="100" class="form-control" value="${l.percentual}" 
                onchange="atualizarLinhaComissao(${i},'percentual',this.value,${lucro})">
            </div>
            <div class="col-md-3">
                <input type="number" min="0" step="0.01" class="form-control" value="${l.valor||0}" readonly>
            </div>
            <div class="col-md-1">
                ${linhasComissao.length>1?`<button class="btn btn-sm btn-danger" onclick="removerLinhaComissao(${i})">X</button>`:''}
            </div>
        </div>`;
    });
    document.getElementById('comissoesModalLinhas').innerHTML = html;
    document.getElementById('avisoComissao').classList.toggle('d-none', Math.abs(somaPercentual-100)<0.01);
}
// Salvar comissão
document.getElementById('formComissao').onsubmit = function(e){
    e.preventDefault();
    let soma = linhasComissao.reduce((a,b)=>a+(parseFloat(b.percentual)||0),0);
    if (Math.abs(soma-100)>0.01) {
        document.getElementById('avisoComissao').classList.remove('d-none');
        return false;
    }
    let fd = new FormData(this);
    fd.append('comissoes',JSON.stringify(linhasComissao));
    fetch('tabela.php', {method:'POST',body:fd})
    .then(async r=>{
        let txt = await r.text();
        try { 
            let res = JSON.parse(txt);
            if(res.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalComissao')).hide();
            }
            else alert('Erro ao salvar comissão! '+(res.error||''));
        } catch(e){
            alert('Erro ao salvar comissão (parse JSON)');
        }
    })
    .catch(err=>console.error('[DEBUG] CATCH comissão:',err));
};
</script>
</body>
</html>
