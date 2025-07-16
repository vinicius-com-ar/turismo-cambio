<?php
session_start();
require_once '../recursos/conexao/index.php';

if (!isset($_SESSION['idEmpresa'])) die('Empresa não autenticada.');
$idEmpresa = $_SESSION['idEmpresa'];

$moedas = ['BRL','ARS','USD','EUR','CLP','UYU'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // AJAX para salvar cotação
    if ($_POST['action'] === 'nova_cotacao') {
        header('Content-Type: application/json');
        $moeda = $_POST['moeda'] ?? '';
        $valor_compra = str_replace(',', '.', $_POST['valor_compra'] ?? '0');
        $valor_venda = str_replace(',', '.', $_POST['valor_venda'] ?? '0');
        if (!in_array($moeda, $moedas)) {
            echo json_encode(['success' => false, 'message' => 'Moeda inválida!']); exit;
        }
        if (!is_numeric($valor_compra) || !is_numeric($valor_venda)) {
            echo json_encode(['success' => false, 'message' => 'Valores devem ser numéricos!']); exit;
        }
        $valor_compra = (float) $valor_compra;
        $valor_venda = (float) $valor_venda;
        if ($valor_compra <= 0 || $valor_venda <= 0) {
            echo json_encode(['success' => false, 'message' => 'Valores devem ser positivos!']); exit;
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO cambio_cotacoes (idEmpresa, data_cotacao, moeda, valor_compra, valor_venda) VALUES (?, CURDATE(), ?, ?, ?)");
            $ok = $stmt->execute([$idEmpresa, $moeda, $valor_compra, $valor_venda]);
            echo json_encode(['success' => $ok]);
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // AJAX para buscar histórico
    if ($_POST['action'] === 'historico_cotacao') {
        header('Content-Type: application/json');
        $moeda = $_POST['moeda'] ?? '';
        if (!in_array($moeda, $moedas)) {
            echo json_encode(['success' => false, 'message' => 'Moeda inválida!']); exit;
        }
        
        $stmt = $pdo->prepare("
            SELECT valor_compra, valor_venda, DATE_FORMAT(data_cotacao,'%d/%m/%Y %H:%i') as data_formatada
            FROM cambio_cotacoes 
            WHERE idEmpresa = ? AND moeda = ?
            ORDER BY data_cotacao DESC
            LIMIT 10
        ");
        $stmt->execute([$idEmpresa, $moeda]);
        $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'historico' => $historico]);
        exit;
    }
}

// BUSCA AS COTAÇÕES MAIS RECENTES DE CADA MOEDA
$stmt = $pdo->prepare("
    SELECT c.moeda, c.valor_compra, c.valor_venda, DATE_FORMAT(c.data_cotacao,'%d/%m %H:%i') as atualizado_em
    FROM cambio_cotacoes c
    INNER JOIN (
        SELECT moeda, MAX(id) as max_id
        FROM cambio_cotacoes WHERE idEmpresa = ?
        GROUP BY moeda
    ) ult
    ON c.moeda = ult.moeda AND c.id = ult.max_id
    WHERE c.idEmpresa = ?
    ORDER BY FIELD(c.moeda, 'BRL','ARS','USD','EUR','CLP','UYU'), c.moeda
");
$stmt->execute([$idEmpresa, $idEmpresa]);
$ultimasCotacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Para o modal: pega últimas cotações individuais por moeda
$lastCotacoes = [];
foreach ($ultimasCotacoes as $cot) {
    $lastCotacoes[$cot['moeda']] = $cot;
}

// Flags país para 6 moedas
function getFlag($moeda) {
    switch($moeda) {
        case 'BRL': return 'BR';
        case 'ARS': return 'AR';
        case 'USD': return 'US';
        case 'EUR': return 'EU';
        case 'CLP': return 'CL';
        case 'UYU': return 'UY';
        default: return '';
    }
}
function getMoedaNome($moeda) {
    switch($moeda) {
        case 'BRL': return 'Real Brasileiro';
        case 'ARS': return 'Peso Argentino';
        case 'USD': return 'Dólar Americano';
        case 'EUR': return 'Euro';
        case 'CLP': return 'Peso Chileno';
        case 'UYU': return 'Peso Uruguaio';
        default: return $moeda;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Cotações | CambioPro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        },
                        secondary: {
                            50: '#f5f3ff',
                            100: '#ede9fe',
                            200: '#ddd6fe',
                            300: '#c4b5fd',
                            400: '#a78bfa',
                            500: '#8b5cf6',
                            600: '#7c3aed',
                            700: '#6d28d9',
                            800: '#5b21b6',
                            900: '#4c1d95',
                        },
                        dark: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            200: '#e2e8f0',
                            300: '#cbd5e1',
                            400: '#94a3b8',
                            500: '#64748b',
                            600: '#475569',
                            700: '#334155',
                            800: '#1e293b',
                            900: '#0f172a',
                        }
                    },
                    boxShadow: {
                        'soft': '0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.03)',
                        'card': '0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02)',
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
            transition: background-color 0.3s;
        }
        
        .dark body {
            background-color: #0f172a;
            color: #e2e8f0;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(241, 245, 249, 0.5);
        }
        
        .dark .glass-card {
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(30, 41, 59, 0.5);
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #0ea5e9 0%, #7c3aed 100%);
        }
        
        .card-gradient {
            background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(240,249,255,0.95) 100%);
            transition: all 0.3s ease;
        }
        
        .dark .card-gradient {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.95) 0%, rgba(30, 41, 59, 0.95) 100%);
        }
        
        .sidebar-nav a.active {
            background-color: rgba(14, 165, 233, 0.1);
            color: #0ea5e9;
            border-left: 3px solid #0ea5e9;
        }
        
        .dark .sidebar-nav a.active {
            background-color: rgba(14, 165, 233, 0.15);
        }
        
        .table-striped tbody tr:nth-child(even) {
            background-color: rgba(241, 245, 249, 0.5);
        }
        
        .dark .table-striped tbody tr:nth-child(even) {
            background-color: rgba(30, 41, 59, 0.5);
        }
        
        .fab-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 4rem;
            height: 4rem;
            border-radius: 50%;
            background: linear-gradient(135deg, #0ea5e9 0%, #7c3aed 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 25px -5px rgba(14, 165, 233, 0.4);
            z-index: 50;
            transition: all 0.3s ease;
        }
        
        .fab-btn:hover {
            transform: scale(1.1) translateY(-5px);
            box-shadow: 0 15px 30px -5px rgba(14, 165, 233, 0.5);
        }
        
        .notification-badge {
            position: absolute;
            top: -0.5rem;
            right: -0.5rem;
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 9999px;
            background-color: #ef4444;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        /* Currency specific styles */
        .currency {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .currency-flag {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            background: rgba(14, 165, 233, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            color: #0ea5e9;
        }
        
        .dark .currency-flag {
            background: rgba(14, 165, 233, 0.2);
        }
        
        .last-quote {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 0.25rem;
        }
        
        .dark .last-quote {
            color: #94a3b8;
        }
        
        /* Modal styles */
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
            max-width: 28rem;
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
        
        .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .dark .modal-header {
            border-bottom-color: #334155;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0f172a;
        }
        
        .dark .modal-title {
            color: #f8fafc;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: #64748b;
            font-size: 1.25rem;
            cursor: pointer;
        }
        
        .dark .modal-close {
            color: #94a3b8;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }
        
        .dark .modal-footer {
            border-top-color: #334155;
        }
        
        .historico-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .historico-table th, 
        .historico-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .dark .historico-table th,
        .dark .historico-table td {
            border-bottom-color: #334155;
        }
        
        .historico-table th {
            font-weight: 600;
            color: #64748b;
        }
        
        .dark .historico-table th {
            color: #94a3b8;
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100">
<div class="min-h-screen flex">
    <!-- Incluir Menu Lateral -->
    <?php include 'includes/menu.php'; ?>
    
    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Incluir Topbar -->
        <?php include 'includes/topo.php'; ?>
        
        <!-- Content -->
        <main class="flex-1 overflow-y-auto p-6 bg-slate-50 dark:bg-slate-900">
            <div class="max-w-7xl mx-auto">
                <div class="card-gradient rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm mb-6">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-200">Cotações Atuais</h3>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                <thead class="bg-slate-50 dark:bg-slate-800">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Moeda</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Compra</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Venda</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Última Atualização</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                                    <?php foreach($ultimasCotacoes as $cot): ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="currency">
                                                <div class="currency-flag"><?= htmlspecialchars(getFlag($cot['moeda'])) ?></div>
                                                <div>
                                                    <div class="font-medium text-slate-900 dark:text-slate-100"><?= htmlspecialchars(getMoedaNome($cot['moeda'])) ?></div>
                                                    <div class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($cot['moeda']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-slate-100">
                                            <?= number_format($cot['valor_compra'], 4, ',', '.') ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-slate-100">
                                            <?= number_format($cot['valor_venda'], 4, ',', '.') ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                                            <?= htmlspecialchars($cot['atualizado_em']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex justify-end gap-2">
                                                <button class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300" onclick="abrirModalHistorico('<?= $cot['moeda'] ?>')">
                                                    <i class="fa-solid fa-history"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if(empty($ultimasCotacoes)): ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-4 text-center text-sm text-slate-500 dark:text-slate-400">
                                            Nenhuma cotação cadastrada ainda.
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Botão Flutuante para Adicionar Cotação -->
<button class="fab-btn shadow-xl" id="newQuoteBtn">
    <i class="fa-solid fa-plus text-xl"></i>
</button>

<!-- Modal Adicionar Cotação -->
<div class="modal-overlay" id="quoteModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Adicionar Cotação</h3>
            <button class="modal-close" id="closeModal"><i class="fa-solid fa-times"></i></button>
        </div>
        <form class="modal-body" id="formCotacao" autocomplete="off" method="post">
            <input type="hidden" name="action" value="nova_cotacao">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Moeda</label>
                    <select class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-slate-800" name="moeda" id="currencySelect" required>
                        <?php foreach($moedas as $m): ?>
                            <option value="<?= $m ?>"><?= getMoedaNome($m) ?> (<?= $m ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Valor de Compra</label>
                    <input type="number" step="0.0001" name="valor_compra" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-slate-800" placeholder="0.0000" required>
                    <div class="last-quote" id="lastCompra"></div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Valor de Venda</label>
                    <input type="number" step="0.0001" name="valor_venda" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-slate-800" placeholder="0.0000" required>
                    <div class="last-quote" id="lastVenda"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm text-sm font-medium text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500" id="cancelModalBtn">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    Salvar Cotação
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Histórico de Cotações -->
<div class="modal-overlay" id="historicoModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title" id="historicoModalTitle">Histórico de Cotações</h3>
            <button class="modal-close" id="closeHistoricoModal"><i class="fa-solid fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="overflow-x-auto">
                <table class="historico-table">
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Valor Compra</th>
                            <th>Valor Venda</th>
                        </tr>
                    </thead>
                    <tbody id="historicoTableBody">
                        <!-- Dados serão preenchidos via JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm text-sm font-medium text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500" id="closeHistoricoModalBtn">
                Fechar
            </button>
        </div>
    </div>
</div>

<script>
    // Dark mode toggle
    const themeToggle = document.getElementById('theme-toggle');
    const htmlElement = document.documentElement;
    
    // Verifica se há preferência salva
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        htmlElement.classList.add('dark');
    } else if (savedTheme === 'light') {
        htmlElement.classList.remove('dark');
    } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
        htmlElement.classList.add('dark');
    }
    
    themeToggle.addEventListener('click', () => {
        htmlElement.classList.toggle('dark');
        localStorage.setItem('theme', htmlElement.classList.contains('dark') ? 'dark' : 'light');
    });
    
    // Modal controle - Adicionar Cotação
    const quoteModal = document.getElementById('quoteModal');
    const newQuoteBtn = document.getElementById('newQuoteBtn');
    const closeModal = document.getElementById('closeModal');
    const cancelModalBtn = document.getElementById('cancelModalBtn');
    
    newQuoteBtn.addEventListener('click', () => { quoteModal.classList.add('active'); });
    
    closeModal.addEventListener('click', () => { quoteModal.classList.remove('active'); });
    cancelModalBtn.addEventListener('click', () => { quoteModal.classList.remove('active'); });
    quoteModal.addEventListener('click', (e) => {
        if (e.target === quoteModal) quoteModal.classList.remove('active');
    });
    
    // Modal controle - Histórico
    const historicoModal = document.getElementById('historicoModal');
    const closeHistoricoModal = document.getElementById('closeHistoricoModal');
    const closeHistoricoModalBtn = document.getElementById('closeHistoricoModalBtn');
    
    closeHistoricoModal.addEventListener('click', () => { historicoModal.classList.remove('active'); });
    closeHistoricoModalBtn.addEventListener('click', () => { historicoModal.classList.remove('active'); });
    historicoModal.addEventListener('click', (e) => {
        if (e.target === historicoModal) historicoModal.classList.remove('active');
    });
    
    // Preencher "última cotação" no modal ao mudar a moeda
    const cotacoes = <?= json_encode($lastCotacoes) ?>;
    function updateLastQuotes() {
        const moeda = document.getElementById('currencySelect').value;
        if (cotacoes[moeda]) {
            document.getElementById('lastCompra').textContent = 'Última cotação: ' + Number(cotacoes[moeda]['valor_compra']).toFixed(4);
            document.getElementById('lastVenda').textContent = 'Última cotação: ' + Number(cotacoes[moeda]['valor_venda']).toFixed(4);
        } else {
            document.getElementById('lastCompra').textContent = '';
            document.getElementById('lastVenda').textContent = '';
        }
    }
    document.getElementById('currencySelect').addEventListener('change', updateLastQuotes);
    updateLastQuotes();
    
    // Função para abrir modal de histórico
    function abrirModalHistorico(moeda) {
        const modalTitle = document.getElementById('historicoModalTitle');
        const tableBody = document.getElementById('historicoTableBody');
        
        modalTitle.textContent = `Histórico de Cotações - ${moeda}`;
        tableBody.innerHTML = '<tr><td colspan="3" class="text-center py-4">Carregando...</td></tr>';
        
        // Buscar dados via AJAX
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=historico_cotacao&moeda=${moeda}`
        })
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                if(res.historico.length > 0) {
                    let html = '';
                    res.historico.forEach(item => {
                        html += `
                            <tr>
                                <td>${item.data_formatada}</td>
                                <td>${Number(item.valor_compra).toFixed(4)}</td>
                                <td>${Number(item.valor_venda).toFixed(4)}</td>
                            </tr>
                        `;
                    });
                    tableBody.innerHTML = html;
                } else {
                    tableBody.innerHTML = '<tr><td colspan="3" class="text-center py-4">Nenhum histórico encontrado</td></tr>';
                }
            } else {
                tableBody.innerHTML = '<tr><td colspan="3" class="text-center py-4">Erro ao carregar histórico</td></tr>';
            }
        })
        .catch(() => {
            tableBody.innerHTML = '<tr><td colspan="3" class="text-center py-4">Erro ao carregar histórico</td></tr>';
        });
        
        historicoModal.classList.add('active');
    }
    
    // Submit AJAX da nova cotação
    document.getElementById('formCotacao').onsubmit = function(e){
        e.preventDefault();
        const fd = new FormData(this);
        fetch('', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                window.location.reload();
            } else {
                alert(res.message || "Falha ao salvar.");
            }
        });
    };
</script>
</body>
</html>