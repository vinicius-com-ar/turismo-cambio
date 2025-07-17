<?php
session_start();
require_once '../recursos/conexao/index.php';

if (!isset($_SESSION['idEmpresa'])) die('Empresa não autenticada.');
$idEmpresa = $_SESSION['idEmpresa'];

$moedas = ['BRL','USD','EUR','CLP','UYU','PEN']; // Removido ARS definitivamente

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

// BUSCA AS COTAÇÕES MAIS RECENTES DE CADA MOEDA (EXCLUINDO ARS)
$stmt = $pdo->prepare("
    SELECT c.moeda, c.valor_compra, c.valor_venda, DATE_FORMAT(c.data_cotacao,'%d/%m') as atualizado_em
    FROM cambio_cotacoes c
    INNER JOIN (
        SELECT moeda, MAX(id) as max_id
        FROM cambio_cotacoes WHERE idEmpresa = ? AND moeda != 'ARS'
        GROUP BY moeda
    ) ult
    ON c.moeda = ult.moeda AND c.id = ult.max_id
    WHERE c.idEmpresa = ? AND c.moeda != 'ARS'
    ORDER BY FIELD(c.moeda, 'BRL','USD','EUR','CLP','UYU','PEN'), c.moeda
");
$stmt->execute([$idEmpresa, $idEmpresa]);
$ultimasCotacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Para o modal: pega últimas cotações individuais por moeda
$lastCotacoes = [];
foreach ($ultimasCotacoes as $cot) {
    $lastCotacoes[$cot['moeda']] = $cot;
}

// Função para obter URL da bandeira do país
function getFlagUrl($moeda) {
    $flags = [
        'BRL' => 'https://flagcdn.com/w40/br.png',
        'USD' => 'https://flagcdn.com/w40/us.png',
        'EUR' => 'https://flagcdn.com/w40/eu.png',
        'CLP' => 'https://flagcdn.com/w40/cl.png',
        'UYU' => 'https://flagcdn.com/w40/uy.png',
        'PEN' => 'https://flagcdn.com/w40/pe.png'
    ];
    return $flags[$moeda] ?? '';
}

// Função para formatar valor monetário (sempre AR$)
function formatCurrency($valor, $moeda) {
    return 'AR$ ' . number_format($valor, 2, ',', '.');
}

function getMoedaNome($moeda) {
    switch($moeda) {
        case 'BRL': return 'Real Brasileiro';
        case 'USD': return 'Dólar Americano';
        case 'EUR': return 'Euro';
        case 'CLP': return 'Peso Chileno';
        case 'UYU': return 'Peso Uruguaio';
        case 'PEN': return 'Sol Peruano';
        default: return $moeda;
    }
}

// Função para cor da moeda
function getCurrencyColor($moeda) {
    $colors = [
        'BRL' => 'from-green-500 to-emerald-600',
        'USD' => 'from-indigo-500 to-purple-600',
        'EUR' => 'from-amber-500 to-orange-600',
        'CLP' => 'from-red-500 to-pink-600',
        'UYU' => 'from-teal-500 to-cyan-600',
        'PEN' => 'from-violet-500 to-purple-600'
    ];
    return $colors[$moeda] ?? 'from-slate-500 to-slate-600';
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
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-out',
                        'slide-up': 'slideUp 0.6s ease-out',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0', transform: 'scale(0.95)' },
                            '100%': { opacity: '1', transform: 'scale(1)' },
                        },
                        slideUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
        
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: #0f172a;
            transition: all 0.3s ease;
        }
        
        .dark body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #f1f5f9;
        }
        
        /* Glass effect melhorado para tema claro */
        .glass {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .dark .glass {
            background: rgba(15, 23, 42, 0.4);
            border: 1px solid rgba(51, 65, 85, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        /* Sidebar styles */
        .sidebar-nav a.active {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.1) 0%, rgba(59, 130, 246, 0.1) 100%);
            color: #0ea5e9;
            border-left: 3px solid #0ea5e9;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .dark .sidebar-nav a.active {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.15) 0%, rgba(59, 130, 246, 0.15) 100%);
        }

        /* FAB Button */
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
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .fab-btn:hover {
            transform: scale(1.05) rotate(90deg);
            box-shadow: 0 20px 40px -10px rgba(14, 165, 233, 0.6);
        }

        /* Cotação Card melhorado */
        .cotacao-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 24px;
            padding: 1.5rem;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            box-shadow: 
                0 4px 16px rgba(0, 0, 0, 0.06),
                0 2px 8px rgba(0, 0, 0, 0.03),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
        }

        .dark .cotacao-card {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(51, 65, 85, 0.4);
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.2),
                0 4px 16px rgba(0, 0, 0, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }

        .cotacao-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: conic-gradient(from 180deg at 50% 50%, transparent 0deg, rgba(59, 130, 246, 0.02) 180deg, transparent 360deg);
            opacity: 0;
            transition: opacity 0.6s ease;
            border-radius: 24px;
        }

        .cotacao-card:hover::before {
            opacity: 1;
        }

        .cotacao-card:hover {
            transform: translateY(-2px) scale(1.01);
            box-shadow: 
                0 12px 40px rgba(0, 0, 0, 0.08),
                0 6px 20px rgba(0, 0, 0, 0.04),
                0 0 0 1px rgba(59, 130, 246, 0.08);
            border-color: rgba(59, 130, 246, 0.2);
        }

        .dark .cotacao-card:hover {
            border-color: rgba(59, 130, 246, 0.4);
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.3),
                0 8px 25px rgba(0, 0, 0, 0.2),
                0 0 0 1px rgba(59, 130, 246, 0.2);
        }

        /* Currency Flag retangular como antes */
        .currency-flag {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: all 0.4s ease;
            width: 3rem;
            height: 2rem;
        }

        .currency-flag::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, transparent 0%, rgba(255, 255, 255, 0.1) 50%, transparent 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 8px;
        }

        .currency-flag:hover::after {
            opacity: 1;
        }

        .currency-flag:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .currency-flag img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Currency Values */
        .currency-value {
            font-family: 'SF Mono', 'Monaco', 'Consolas', 'Liberation Mono', monospace;
            font-weight: 700;
            letter-spacing: -0.025em;
        }

        /* Buy/Sell Sections menores */
        .buy-section {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border: 1px solid #86efac;
            border-radius: 12px;
            padding: 0.875rem;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .dark .buy-section {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.1) 0%, rgba(22, 163, 74, 0.05) 100%);
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .sell-section {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border: 1px solid #93c5fd;
            border-radius: 12px;
            padding: 0.875rem;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .dark .sell-section {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(37, 99, 235, 0.05) 100%);
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        /* Action Button */
        .action-btn {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(226, 232, 240, 0.6);
            border-radius: 10px;
            padding: 0.625rem;
            color: #64748b;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .dark .action-btn {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(51, 65, 85, 0.4);
            color: #94a3b8;
        }

        .action-btn:hover {
            background: rgba(59, 130, 246, 0.1);
            border-color: rgba(59, 130, 246, 0.3);
            color: #3b82f6;
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.15);
        }

        /* Modal Premium */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s ease;
        }
        
        .modal-overlay.active {
            opacity: 1;
            pointer-events: all;
        }
        
        .modal {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(226, 232, 240, 0.6);
            border-radius: 24px;
            width: 100%;
            max-width: 28rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            transform: scale(0.95) translateY(20px);
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        
        .dark .modal {
            background: rgba(30, 41, 59, 0.95);
            border: 1px solid rgba(51, 65, 85, 0.4);
        }
        
        .modal-overlay.active .modal {
            transform: scale(1) translateY(0);
        }
        
        .modal-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid rgba(226, 232, 240, 0.6);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .dark .modal-header {
            border-bottom-color: rgba(51, 65, 85, 0.4);
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
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
            transition: all 0.2s ease;
        }

        .modal-close:hover {
            color: #ef4444;
            transform: scale(1.1);
        }
        
        .dark .modal-close {
            color: #94a3b8;
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .modal-footer {
            padding: 1rem 2rem 1.5rem;
            border-top: 1px solid rgba(226, 232, 240, 0.6);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }
        
        .dark .modal-footer {
            border-top-color: rgba(51, 65, 85, 0.4);
        }

        .last-quote {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 0.25rem;
        }
        
        .dark .last-quote {
            color: #94a3b8;
        }

        /* Responsive Grid */
        .currency-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        @media (min-width: 768px) {
            .currency-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1200px) {
            .currency-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 640px) {
            .currency-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .cotacao-card {
                padding: 1.25rem;
                border-radius: 20px;
            }
        }

        /* Estado vazio */
        .empty-state {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            border: 2px dashed #cbd5e1;
            border-radius: 24px;
            padding: 3rem 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .dark .empty-state {
            background: rgba(15, 23, 42, 0.6);
            border-color: #475569;
        }

        .empty-state:hover {
            border-color: #0ea5e9;
            background: rgba(14, 165, 233, 0.05);
            transform: scale(1.02);
        }

        /* Historico table */
        .historico-table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            overflow: hidden;
        }

        .dark .historico-table {
            background: rgba(30, 41, 59, 0.8);
        }
        
        .historico-table th, 
        .historico-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(226, 232, 240, 0.6);
        }
        
        .dark .historico-table th,
        .dark .historico-table td {
            border-bottom-color: rgba(51, 65, 85, 0.4);
        }
        
        .historico-table th {
            font-weight: 600;
            color: #475569;
            background: rgba(248, 250, 252, 0.8);
        }
        
        .dark .historico-table th {
            color: #94a3b8;
            background: rgba(51, 65, 85, 0.4);
        }

        .historico-table tr:hover {
            background: rgba(59, 130, 246, 0.05);
        }
    </style>
</head>
<body class="min-h-screen">
<div class="min-h-screen flex">
    <!-- Incluir Menu Lateral -->
    <?php include 'includes/menu.php'; ?>
    
    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Incluir Topbar -->
        <?php include 'includes/topo.php'; ?>
        
        <!-- Content -->
        <main class="flex-1 overflow-y-auto p-8">
            <div class="max-w-7xl mx-auto">
                <!-- Stats Cards - apenas 3 -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="glass rounded-2xl p-6 group hover:scale-105 transition-all duration-300">
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-emerald-500 to-green-600 flex items-center justify-center text-white group-hover:rotate-12 transition-transform duration-300">
                                <i class="fa-solid fa-flag text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Países Ativos</p>
                                <p class="text-3xl font-black text-slate-900 dark:text-white"><?= count($ultimasCotacoes) ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="glass rounded-2xl p-6 group hover:scale-105 transition-all duration-300">
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-600 flex items-center justify-center text-white group-hover:rotate-12 transition-transform duration-300">
                                <i class="fa-solid fa-clock text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Última Atualização</p>
                                <p class="text-lg font-bold text-slate-900 dark:text-white">
                                    <?= !empty($ultimasCotacoes) ? $ultimasCotacoes[0]['atualizado_em'] : 'N/A' ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="glass rounded-2xl p-6 group hover:scale-105 transition-all duration-300">
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center text-white group-hover:rotate-12 transition-transform duration-300">
                                <i class="fa-solid fa-wifi text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Status</p>
                                <p class="text-lg font-bold text-green-600 dark:text-green-400">
                                    <i class="fa-solid fa-circle text-green-500 mr-2 animate-pulse"></i>
                                    Online
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Currency Grid -->
                <?php if(!empty($ultimasCotacoes)): ?>
                <div class="currency-grid">
                    <?php foreach($ultimasCotacoes as $index => $cot): ?>
                    <div class="cotacao-card animate-fade-in" style="animation-delay: <?= $index * 0.1 ?>s">
                        <!-- Header Card -->
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="currency-flag">
                                    <img 
                                        src="<?= getFlagUrl($cot['moeda']) ?>" 
                                        alt="<?= $cot['moeda'] ?>" 
                                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'"
                                    >
                                    <!-- Fallback -->
                                    <div class="w-full h-full bg-gradient-to-br <?= getCurrencyColor($cot['moeda']) ?> flex items-center justify-center text-white font-bold text-xs rounded-lg" style="display: none;">
                                        <?= htmlspecialchars($cot['moeda']) ?>
                                    </div>
                                </div>
                                
                                <div>
                                    <h3 class="text-lg font-black text-slate-900 dark:text-white">
                                        <?= htmlspecialchars($cot['moeda']) ?>
                                    </h3>
                                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                                        <?= htmlspecialchars(getMoedaNome($cot['moeda'])) ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Action Button -->
                            <button 
                                class="action-btn"
                                onclick="abrirModalHistorico('<?= $cot['moeda'] ?>')"
                                title="Ver histórico de cotações"
                            >
                                <i class="fa-solid fa-chart-line"></i>
                            </button>
                        </div>

                        <!-- Values Section -->
                        <div class="space-y-3">
                            <!-- Buy Section -->
                            <div class="buy-section">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-lg bg-green-100 dark:bg-green-900/40 flex items-center justify-center">
                                            <i class="fa-solid fa-arrow-trend-down text-green-600 dark:text-green-400 text-sm"></i>
                                        </div>
                                        <div>
                                            <p class="text-xs font-bold text-green-800 dark:text-green-300 uppercase tracking-wide">COMPRA</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="currency-value text-lg font-black text-green-800 dark:text-green-300">
                                            <span class="text-xs">AR$</span> <?= number_format($cot['valor_compra'], 2, ',', '.') ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Sell Section -->
                            <div class="sell-section">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center">
                                            <i class="fa-solid fa-arrow-trend-up text-blue-600 dark:text-blue-400 text-sm"></i>
                                        </div>
                                        <div>
                                            <p class="text-xs font-bold text-blue-800 dark:text-blue-300 uppercase tracking-wide">VENDA</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="currency-value text-lg font-black text-blue-800 dark:text-blue-300">
                                            <span class="text-xs">AR$</span> <?= number_format($cot['valor_venda'], 2, ',', '.') ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Gradient bottom accent -->
                        <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r <?= getCurrencyColor($cot['moeda']) ?> rounded-b-3xl opacity-60"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <div class="w-24 h-24 mx-auto mb-6">
                        <div class="w-full h-full rounded-full bg-gradient-to-br from-slate-200 to-slate-300 dark:from-slate-700 dark:to-slate-800 flex items-center justify-center">
                            <i class="fa-solid fa-chart-line text-4xl text-slate-400"></i>
                        </div>
                    </div>
                    <h3 class="text-2xl font-black text-slate-900 dark:text-white mb-4">Nenhuma cotação ativa</h3>
                    <p class="text-lg text-slate-600 dark:text-slate-400 mb-8 max-w-md mx-auto">
                        Adicione sua primeira cotação para começar a monitorar os mercados financeiros
                    </p>
                    <button 
                        class="px-8 py-4 bg-gradient-to-r from-blue-600 via-purple-600 to-blue-800 text-white rounded-2xl font-bold text-lg hover:shadow-2xl transform hover:scale-105 transition-all duration-300"
                        onclick="document.getElementById('newQuoteBtn').click()"
                    >
                        <i class="fa-solid fa-plus mr-3"></i>
                        Adicionar Primeira Cotação
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<!-- FAB Button -->
<button class="fab-btn" id="newQuoteBtn">
    <i class="fa-solid fa-plus text-xl"></i>
</button>

<!-- Modal Adicionar Cotação -->
<div class="modal-overlay" id="quoteModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fa-solid fa-chart-line mr-3 text-blue-600"></i>
                Adicionar Nova Cotação
            </h3>
            <button class="modal-close" id="closeModal">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <form class="modal-body" id="formCotacao" autocomplete="off" method="post">
            <input type="hidden" name="action" value="nova_cotacao">
            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">
                        <i class="fa-solid fa-globe mr-2"></i>Moeda
                    </label>
                    <select class="w-full px-4 py-3 border-2 border-slate-200 dark:border-slate-600 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-800 font-medium transition-all" name="moeda" id="currencySelect" required>
                        <?php foreach($moedas as $m): ?>
                            <option value="<?= $m ?>"><?= getMoedaNome($m) ?> (<?= $m ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">
                            <i class="fa-solid fa-arrow-down mr-2 text-green-600"></i>Valor de Compra
                        </label>
                        <input type="number" step="0.0001" name="valor_compra" class="w-full px-4 py-3 border-2 border-slate-200 dark:border-slate-600 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 bg-white dark:bg-slate-800 font-mono font-bold transition-all" placeholder="0,0000" required>
                        <div class="last-quote" id="lastCompra"></div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">
                            <i class="fa-solid fa-arrow-up mr-2 text-blue-600"></i>Valor de Venda
                        </label>
                        <input type="number" step="0.0001" name="valor_venda" class="w-full px-4 py-3 border-2 border-slate-200 dark:border-slate-600 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-800 font-mono font-bold transition-all" placeholder="0,0000" required>
                        <div class="last-quote" id="lastVenda"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="px-6 py-3 border-2 border-slate-300 dark:border-slate-600 rounded-xl shadow-sm text-sm font-bold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all duration-200" id="cancelModalBtn">
                    Cancelar
                </button>
                <button type="submit" class="px-6 py-3 border-2 border-transparent rounded-xl shadow-sm text-sm font-bold text-white bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 transition-all duration-200 transform hover:scale-105">
                    <i class="fa-solid fa-save mr-2"></i>Salvar Cotação
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Histórico de Cotações -->
<div class="modal-overlay" id="historicoModal">
    <div class="modal" style="max-width: 700px;">
        <div class="modal-header">
            <h3 class="modal-title" id="historicoModalTitle">
                <i class="fa-solid fa-history mr-3 text-purple-600"></i>
                Histórico de Cotações
            </h3>
            <button class="modal-close" id="closeHistoricoModal">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="overflow-hidden rounded-xl">
                <table class="historico-table">
                    <thead>
                        <tr>
                            <th class="text-left font-bold">
                                <i class="fa-solid fa-calendar mr-2"></i>Data/Hora
                            </th>
                            <th class="text-left font-bold">
                                <i class="fa-solid fa-arrow-down mr-2 text-green-600"></i>Compra
                            </th>
                            <th class="text-left font-bold">
                                <i class="fa-solid fa-arrow-up mr-2 text-blue-600"></i>Venda
                            </th>
                        </tr>
                    </thead>
                    <tbody id="historicoTableBody">
                        <!-- Dados via JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="px-6 py-3 border-2 border-slate-300 dark:border-slate-600 rounded-xl shadow-sm text-sm font-bold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all duration-200" id="closeHistoricoModalBtn">
                <i class="fa-solid fa-times mr-2"></i>Fechar
            </button>
        </div>
    </div>
</div>

<script>
    // Dark mode toggle
    const themeToggle = document.getElementById('theme-toggle');
    const htmlElement = document.documentElement;
    
    // Verifica preferência salva
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        htmlElement.classList.add('dark');
    } else if (savedTheme === 'light') {
        htmlElement.classList.remove('dark');
    } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
        htmlElement.classList.add('dark');
    }
    
    themeToggle?.addEventListener('click', () => {
        htmlElement.classList.toggle('dark');
        localStorage.setItem('theme', htmlElement.classList.contains('dark') ? 'dark' : 'light');
    });
    
    // Modal controle - Adicionar Cotação
    const quoteModal = document.getElementById('quoteModal');
    const newQuoteBtn = document.getElementById('newQuoteBtn');
    const closeModal = document.getElementById('closeModal');
    const cancelModalBtn = document.getElementById('cancelModalBtn');
    
    newQuoteBtn?.addEventListener('click', () => quoteModal.classList.add('active'));
    closeModal?.addEventListener('click', () => quoteModal.classList.remove('active'));
    cancelModalBtn?.addEventListener('click', () => quoteModal.classList.remove('active'));
    quoteModal?.addEventListener('click', (e) => {
        if (e.target === quoteModal) quoteModal.classList.remove('active');
    });
    
    // Modal controle - Histórico
    const historicoModal = document.getElementById('historicoModal');
    const closeHistoricoModal = document.getElementById('closeHistoricoModal');
    const closeHistoricoModalBtn = document.getElementById('closeHistoricoModalBtn');
    
    closeHistoricoModal?.addEventListener('click', () => historicoModal.classList.remove('active'));
    closeHistoricoModalBtn?.addEventListener('click', () => historicoModal.classList.remove('active'));
    historicoModal?.addEventListener('click', (e) => {
        if (e.target === historicoModal) historicoModal.classList.remove('active');
    });
    
    // Última cotação no modal
    const cotacoes = <?= json_encode($lastCotacoes) ?>;
    function updateLastQuotes() {
        const moeda = document.getElementById('currencySelect')?.value;
        if (cotacoes[moeda]) {
            const lastCompra = document.getElementById('lastCompra');
            const lastVenda = document.getElementById('lastVenda');
            if (lastCompra) lastCompra.textContent = 'Última: ' + Number(cotacoes[moeda]['valor_compra']).toFixed(4);
            if (lastVenda) lastVenda.textContent = 'Última: ' + Number(cotacoes[moeda]['valor_venda']).toFixed(4);
        } else {
            const lastCompra = document.getElementById('lastCompra');
            const lastVenda = document.getElementById('lastVenda');
            if (lastCompra) lastCompra.textContent = '';
            if (lastVenda) lastVenda.textContent = '';
        }
    }
    document.getElementById('currencySelect')?.addEventListener('change', updateLastQuotes);
    updateLastQuotes();
    
    // Função para abrir modal de histórico
    function abrirModalHistorico(moeda) {
        const modalTitle = document.getElementById('historicoModalTitle');
        const tableBody = document.getElementById('historicoTableBody');
        
        if (modalTitle) modalTitle.innerHTML = `<i class="fa-solid fa-history mr-3 text-purple-600"></i>Histórico de Cotações - ${moeda}`;
        if (tableBody) tableBody.innerHTML = '<tr><td colspan="3" class="text-center py-8"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Carregando...</td></tr>';
        
        // Buscar dados via AJAX
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=historico_cotacao&moeda=${moeda}`
        })
        .then(r => r.json())
        .then(res => {
            if (tableBody) {
                if (res.success && res.historico.length > 0) {
                    let html = '';
                    res.historico.forEach(item => {
                        html += `
                            <tr class="hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors">
                                <td class="font-medium">${item.data_formatada}</td>
                                <td class="font-mono font-bold text-green-700 dark:text-green-400">AR$ ${Number(item.valor_compra).toFixed(2).replace('.', ',')}</td>
                                <td class="font-mono font-bold text-blue-700 dark:text-blue-400">AR$ ${Number(item.valor_venda).toFixed(2).replace('.', ',')}</td>
                            </tr>
                        `;
                    });
                    tableBody.innerHTML = html;
                } else {
                    tableBody.innerHTML = '<tr><td colspan="3" class="text-center py-8 text-slate-500"><i class="fa-solid fa-inbox mr-2"></i>Nenhum histórico encontrado</td></tr>';
                }
            }
        })
        .catch(() => {
            if (tableBody) tableBody.innerHTML = '<tr><td colspan="3" class="text-center py-8 text-red-500"><i class="fa-solid fa-exclamation-triangle mr-2"></i>Erro ao carregar histórico</td></tr>';
        });
        
        historicoModal.classList.add('active');
    }
    
    // Submit AJAX da nova cotação
    document.getElementById('formCotacao')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        
        // Feedback visual
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Salvando...';
        submitBtn.disabled = true;
        
        fetch('', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                window.location.reload();
            } else {
                alert(res.message || "Falha ao salvar cotação.");
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        })
        .catch(err => {
            alert("Erro ao processar solicitação.");
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });
    
    // Animações de entrada
    window.addEventListener('load', () => {
        const cards = document.querySelectorAll('.cotacao-card');
        cards.forEach((card, index) => {
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    });
</script>
</body>
</html>
