<?php
// Verificar variáveis de sessão
if (!isset($idEmpresa)) {
    $idEmpresa = $_SESSION['idEmpresa'] ?? null;
}

if (!$idEmpresa) {
    // Dados padrão se não há empresa
    $dadosGrafico = array_fill(0, 7, 0);
    $dadosLucro = array_fill(0, 7, 0);
    $cotacoesAtuais = [];
} else {
    try {
        // 1. DADOS PARA O GRÁFICO - Últimos 7 dias
        $dadosGrafico = [];
        $dadosLucro = [];
        $labels = [];
        
        for ($i = 6; $i >= 0; $i--) {
            // Data do dia
            $data = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('D', strtotime("-{$i} days")); // Seg, Ter, Qua...
            
            // Contar operações do dia
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM cambio_operacoes 
                WHERE idEmpresa = ? AND DATE(data_operacao) = ?
            ");
            $stmt->execute([$idEmpresa, $data]);
            $operacoesDia = $stmt->fetchColumn() ?: 0;
            $dadosGrafico[] = $operacoesDia;
            
            // Somar lucro do dia
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(lucro), 0) as total_lucro 
                FROM cambio_operacoes 
                WHERE idEmpresa = ? AND DATE(data_operacao) = ?
            ");
            $stmt->execute([$idEmpresa, $data]);
            $lucroDia = $stmt->fetchColumn() ?: 0;
            $dadosLucro[] = round($lucroDia, 2);
        }

        // 2. COTAÇÕES ATUAIS - Últimas de cada moeda (exceto ARS)
        $stmt = $pdo->prepare("
            SELECT c.moeda, c.valor_compra, c.valor_venda, 
                   DATE_FORMAT(c.data_cotacao,'%d/%m %H:%i') as atualizado_em,
                   CASE 
                       WHEN c.valor_venda > COALESCE(ant.valor_venda, c.valor_venda) THEN 'up'
                       WHEN c.valor_venda < COALESCE(ant.valor_venda, c.valor_venda) THEN 'down'
                       ELSE 'stable'
                   END as tendencia
            FROM cambio_cotacoes c
            INNER JOIN (
                SELECT moeda, MAX(id) as max_id
                FROM cambio_cotacoes 
                WHERE idEmpresa = ? AND moeda != 'ARS'
                GROUP BY moeda
            ) ult ON c.moeda = ult.moeda AND c.id = ult.max_id
            LEFT JOIN cambio_cotacoes ant ON ant.moeda = c.moeda 
                AND ant.idEmpresa = c.idEmpresa 
                AND ant.id = (
                    SELECT MAX(id) 
                    FROM cambio_cotacoes 
                    WHERE moeda = c.moeda AND idEmpresa = c.idEmpresa AND id < c.id
                )
            WHERE c.idEmpresa = ?
            ORDER BY FIELD(c.moeda, 'USD','EUR','BRL','CLP','UYU','PEN'), c.moeda
            LIMIT 6
        ");
        $stmt->execute([$idEmpresa, $idEmpresa]);
        $cotacoesAtuais = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Erro ao buscar dados de desempenho: " . $e->getMessage());
        $dadosGrafico = array_fill(0, 7, 0);
        $dadosLucro = array_fill(0, 7, 0);
        $cotacoesAtuais = [];
        $labels = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
    }
}

// Funções auxiliares
function getFlagUrl($moeda) {
    $flags = [
        'BRL' => 'https://flagcdn.com/w20/br.png',
        'USD' => 'https://flagcdn.com/w20/us.png',
        'EUR' => 'https://flagcdn.com/w20/eu.png',
        'CLP' => 'https://flagcdn.com/w20/cl.png',
        'UYU' => 'https://flagcdn.com/w20/uy.png',
        'PEN' => 'https://flagcdn.com/w20/pe.png'
    ];
    return $flags[$moeda] ?? '';
}

function getMoedaIcon($moeda) {
    $icons = [
        'USD' => 'fa-dollar-sign',
        'EUR' => 'fa-euro-sign',
        'BRL' => 'fa-brazilian-real-sign',
        'CLP' => 'fa-peso-sign',
        'UYU' => 'fa-peso-sign',
        'PEN' => 'fa-sol'
    ];
    return $icons[$moeda] ?? 'fa-coins';
}

function getTendenciaClass($tendencia) {
    switch($tendencia) {
        case 'up': return 'text-green-600 dark:text-green-400';
        case 'down': return 'text-red-600 dark:text-red-400';
        default: return 'text-slate-600 dark:text-slate-400';
    }
}

function getTendenciaIcon($tendencia) {
    switch($tendencia) {
        case 'up': return 'fa-arrow-up';
        case 'down': return 'fa-arrow-down';
        default: return 'fa-circle'; // ou fa-equals para estável
    }
}

function formatarMoedaPequena($valor) {
    return '<span class="text-xs opacity-75">AR$</span> ' . number_format($valor, 2, ',', '.');
}
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Gráfico de Desempenho (2/3 da largura) -->
    <div class="lg:col-span-2 card-gradient rounded-2xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-xl font-bold text-slate-800 dark:text-slate-200 mb-1">Desempenho Semanal</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">Operações e lucro dos últimos 7 dias</p>
            </div>
            <div class="flex space-x-2">
                <button class="period-btn active" data-period="7" onclick="changeChartPeriod(7)">7D</button>
                <button class="period-btn" data-period="30" onclick="changeChartPeriod(30)">30D</button>
                <button class="period-btn" data-period="90" onclick="changeChartPeriod(90)">90D</button>
            </div>
        </div>
        
        <!-- Container do Gráfico -->
        <div class="chart-container relative">
            <canvas id="performanceChart" class="w-full" style="max-height: 350px;"></canvas>
            
            <!-- Loading State -->
            <div id="chartLoading" class="absolute inset-0 flex items-center justify-center bg-white/80 dark:bg-slate-800/80 rounded-xl" style="display: none;">
                <div class="text-center">
                    <i class="fa-solid fa-spinner fa-spin text-2xl text-blue-600 mb-2"></i>
                    <p class="text-sm text-slate-600 dark:text-slate-400">Carregando dados...</p>
                </div>
            </div>
        </div>

        <!-- Resumo do Gráfico -->
        <div class="grid grid-cols-2 gap-4 mt-6 pt-4 border-t border-slate-200 dark:border-slate-700">
            <div class="text-center">
                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                    <?= array_sum($dadosGrafico) ?>
                </div>
                <div class="text-sm text-slate-500 dark:text-slate-400">Total Operações</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                    <?= formatarMoedaPequena(array_sum($dadosLucro)) ?>
                </div>
                <div class="text-sm text-slate-500 dark:text-slate-400">Total Lucro</div>
            </div>
        </div>
    </div>

    <!-- Cotações Atuais (1/3 da largura) -->
    <div class="card-gradient rounded-2xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-xl font-bold text-slate-800 dark:text-slate-200 mb-1">Cotações Live</h3>
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Atualizações em tempo real</p>
                </div>
            </div>
            <button class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" onclick="refreshCotacoes()" title="Atualizar cotações">
                <i class="fa-solid fa-arrows-rotate text-slate-600 dark:text-slate-300"></i>
            </button>
        </div>

        <div class="space-y-4 max-h-80 overflow-y-auto">
            <?php if (empty($cotacoesAtuais)): ?>
                <!-- Estado Vazio -->
                <div class="text-center py-8">
                    <div class="w-16 h-16 mx-auto bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center mb-4">
                        <i class="fa-solid fa-chart-line text-2xl text-slate-400"></i>
                    </div>
                    <h4 class="font-semibold text-slate-600 dark:text-slate-300 mb-2">Nenhuma cotação disponível</h4>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Adicione cotações para acompanhar o mercado</p>
                    <a href="cotacoes.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                        <i class="fa-solid fa-plus mr-2"></i>Adicionar Cotação
                    </a>
                </div>
            <?php else: ?>
                <?php foreach($cotacoesAtuais as $cot): ?>
                <div class="flex items-center justify-between p-4 bg-white/50 dark:bg-slate-800/50 rounded-xl border border-slate-200/50 dark:border-slate-700/50 hover:bg-white/80 dark:hover:bg-slate-700/50 transition-all duration-200 group">
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <img 
                                src="<?= getFlagUrl($cot['moeda']) ?>" 
                                alt="<?= $cot['moeda'] ?>" 
                                class="w-8 h-6 rounded object-cover"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'"
                            >
                            <!-- Fallback -->
                            <div class="w-8 h-6 rounded bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-xs font-bold" style="display: none;">
                                <?= htmlspecialchars($cot['moeda']) ?>
                            </div>
                        </div>
                        <div>
                            <div class="font-bold text-slate-900 dark:text-white text-sm">
                                <?= htmlspecialchars($cot['moeda']) ?>
                            </div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">
                                <?= htmlspecialchars($cot['atualizado_em']) ?>
                            </div>
                        </div>
                    </div>

                    <div class="text-right">
                        <div class="font-bold text-slate-900 dark:text-white text-sm">
                            <?= formatarMoedaPequena($cot['valor_venda']) ?>
                        </div>
                        <div class="flex items-center justify-end gap-1 <?= getTendenciaClass($cot['tendencia']) ?>">
                            <i class="<?= getTendenciaIcon($cot['tendencia']) ?> text-xs"></i>
                            <span class="text-xs font-medium">
                                <?= number_format((($cot['valor_venda'] - $cot['valor_compra']) / $cot['valor_compra']) * 100, 2, ',', '.') ?>%
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Link para ver todas -->
                <div class="pt-4 border-t border-slate-200 dark:border-slate-700">
                    <a href="cotacoes.php" class="block w-full text-center py-3 text-blue-600 dark:text-blue-400 font-medium hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors">
                        <i class="fa-solid fa-arrow-right mr-2"></i>
                        Ver Todas as Cotações
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.period-btn {
    @apply px-3 py-1 text-sm rounded-lg font-medium transition-all duration-200;
    @apply text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700;
}

.period-btn.active {
    @apply bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400;
    @apply ring-1 ring-blue-200 dark:ring-blue-800;
}

.chart-container {
    position: relative;
    height: 350px;
}

/* Scrollbar customizada para cotações */
.space-y-4::-webkit-scrollbar {
    width: 4px;
}

.space-y-4::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.05);
    border-radius: 2px;
}

.space-y-4::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 2px;
}

.dark .space-y-4::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
}

.dark .space-y-4::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
}
</style>

<script>
// Dados do PHP para JavaScript
const chartData = {
    labels: <?= json_encode($labels ?? ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom']) ?>,
    operacoes: <?= json_encode($dadosGrafico) ?>,
    lucro: <?= json_encode($dadosLucro) ?>
};

let performanceChart;

// Inicializar o gráfico
function initChart() {
    const ctx = document.getElementById('performanceChart').getContext('2d');
    
    // Destruir gráfico existente se houver
    if (performanceChart) {
        performanceChart.destroy();
    }
    
    performanceChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData.labels,
            datasets: [
                {
                    label: 'Operações',
                    data: chartData.operacoes,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#3b82f6',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    yAxisID: 'y'
                },
                {
                    label: 'Lucro (AR$)',
                    data: chartData.lucro,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#10b981',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            family: "'Inter', sans-serif",
                            size: 12,
                            weight: '500'
                        },
                        color: document.documentElement.classList.contains('dark') ? '#94a3b8' : '#64748b'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: '#374151',
                    borderWidth: 1,
                    cornerRadius: 8,
                    padding: 12,
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            if (context.datasetIndex === 1) {
                                return `${context.dataset.label}: AR$ ${context.parsed.y.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
                            }
                            return `${context.dataset.label}: ${context.parsed.y}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        color: document.documentElement.classList.contains('dark') ? 'rgba(148, 163, 184, 0.1)' : 'rgba(148, 163, 184, 0.2)',
                        drawBorder: false
                    },
                    ticks: {
                        color: document.documentElement.classList.contains('dark') ? '#94a3b8' : '#64748b',
                        font: {
                            family: "'Inter', sans-serif",
                            size: 11
                        }
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    beginAtZero: true,
                    grid: {
                        color: document.documentElement.classList.contains('dark') ? 'rgba(148, 163, 184, 0.1)' : 'rgba(148, 163, 184, 0.2)',
                        drawBorder: false
                    },
                    ticks: {
                        color: document.documentElement.classList.contains('dark') ? '#94a3b8' : '#64748b',
                        font: {
                            family: "'Inter', sans-serif",
                            size: 11
                        },
                        callback: function(value) {
                            return Number.isInteger(value) ? value : '';
                        }
                    },
                    title: {
                        display: true,
                        text: 'Operações',
                        color: '#3b82f6',
                        font: {
                            family: "'Inter', sans-serif",
                            size: 12,
                            weight: 'bold'
                        }
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    grid: {
                        drawOnChartArea: false,
                        drawBorder: false
                    },
                    ticks: {
                        color: document.documentElement.classList.contains('dark') ? '#94a3b8' : '#64748b',
                        font: {
                            family: "'Inter', sans-serif",
                            size: 11
                        },
                        callback: function(value) {
                            return 'AR$ ' + value.toLocaleString('pt-BR', {minimumFractionDigits: 0});
                        }
                    },
                    title: {
                        display: true,
                        text: 'Lucro (AR$)',
                        color: '#10b981',
                        font: {
                            family: "'Inter', sans-serif",
                            size: 12,
                            weight: 'bold'
                        }
                    }
                }
            },
            elements: {
                line: {
                    borderWidth: 3
                }
            }
        }
    });
}

// Mudar período do gráfico
async function changeChartPeriod(days) {
    // Atualizar botões
    document.querySelectorAll('.period-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-period="${days}"]`).classList.add('active');
    
    // Mostrar loading
    const loading = document.getElementById('chartLoading');
    loading.style.display = 'flex';
    
    try {
        // Buscar novos dados via AJAX
        const response = await fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=chart_data&period=${days}`
        });
        
        if (response.ok) {
            const data = await response.json();
            
            // Atualizar dados do gráfico
            performanceChart.data.labels = data.labels;
            performanceChart.data.datasets[0].data = data.operacoes;
            performanceChart.data.datasets[1].data = data.lucro;
            performanceChart.update('active');
            
            // Atualizar resumo
            document.querySelector('.grid.grid-cols-2 .text-2xl').textContent = data.operacoes.reduce((a, b) => a + b, 0);
            document.querySelector('.grid.grid-cols-2 .text-green-600').innerHTML = `<span class="text-xs opacity-75">AR$</span> ${data.lucro.reduce((a, b) => a + b, 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
        }
    } catch (error) {
        console.error('Erro ao carregar dados do gráfico:', error);
    } finally {
        loading.style.display = 'none';
    }
}

// Atualizar cotações
async function refreshCotacoes() {
    const refreshBtn = document.querySelector('[onclick="refreshCotacoes()"] i');
    refreshBtn.classList.add('fa-spin');
    
    try {
        // Simular requisição (você pode implementar AJAX aqui)
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        // Recarregar página ou atualizar via AJAX
        window.location.reload();
    } catch (error) {
        console.error('Erro ao atualizar cotações:', error);
    } finally {
        refreshBtn.classList.remove('fa-spin');
    }
}

// Inicializar quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    initChart();
    
    // Atualizar tema do gráfico quando mudar dark mode
    const observer = new MutationObserver(() => {
        if (performanceChart) {
            const isDark = document.documentElement.classList.contains('dark');
            
            // Atualizar cores do gráfico
            performanceChart.options.plugins.legend.labels.color = isDark ? '#94a3b8' : '#64748b';
            performanceChart.options.scales.x.grid.color = isDark ? 'rgba(148, 163, 184, 0.1)' : 'rgba(148, 163, 184, 0.2)';
            performanceChart.options.scales.y.grid.color = isDark ? 'rgba(148, 163, 184, 0.1)' : 'rgba(148, 163, 184, 0.2)';
            performanceChart.options.scales.x.ticks.color = isDark ? '#94a3b8' : '#64748b';
            performanceChart.options.scales.y.ticks.color = isDark ? '#94a3b8' : '#64748b';
            performanceChart.options.scales.y1.ticks.color = isDark ? '#94a3b8' : '#64748b';
            
            performanceChart.update();
        }
    });
    
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class']
    });
});
</script>
