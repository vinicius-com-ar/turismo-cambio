<?php
// Verificar se as variáveis de sessão estão disponíveis
if (!isset($idEmpresa)) {
    $idEmpresa = $_SESSION['idEmpresa'] ?? null;
}

if (!$idEmpresa) {
    // Se não há empresa, mostrar dados zerados
    $operacoesHoje = 0;
    $lucroSemana = 0;
    $lucroHoje = 0;
    $totalOperacoes = 0;
    $moedaTop = 'N/A';
    $operacoesMoedaTop = 0;
    $crescimentoOperacoes = 0;
    $crescimentoLucro = 0;
} else {
    try {
        // 1. OPERAÇÕES HOJE
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM cambio_operacoes 
            WHERE idEmpresa = ? AND DATE(data_operacao) = CURDATE()
        ");
        $stmt->execute([$idEmpresa]);
        $operacoesHoje = $stmt->fetchColumn() ?: 0;

        // 2. LUCRO HOJE
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(lucro), 0) as total_lucro 
            FROM cambio_operacoes 
            WHERE idEmpresa = ? AND DATE(data_operacao) = CURDATE()
        ");
        $stmt->execute([$idEmpresa]);
        $lucroHoje = $stmt->fetchColumn() ?: 0;

        // 3. OPERAÇÕES ONTEM (para calcular crescimento)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM cambio_operacoes 
            WHERE idEmpresa = ? AND DATE(data_operacao) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
        ");
        $stmt->execute([$idEmpresa]);
        $operacoesOntem = $stmt->fetchColumn() ?: 0;

        // Calcular crescimento de operações
        if ($operacoesOntem > 0) {
            $crescimentoOperacoes = (($operacoesHoje - $operacoesOntem) / $operacoesOntem) * 100;
        } else {
            $crescimentoOperacoes = $operacoesHoje > 0 ? 100 : 0;
        }

        // 4. LUCRO DOS ÚLTIMOS 7 DIAS
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(lucro), 0) as total_lucro 
            FROM cambio_operacoes 
            WHERE idEmpresa = ? AND data_operacao >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ");
        $stmt->execute([$idEmpresa]);
        $lucroSemana = $stmt->fetchColumn() ?: 0;

        // 5. LUCRO DA SEMANA ANTERIOR (para calcular crescimento)
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(lucro), 0) as total_lucro 
            FROM cambio_operacoes 
            WHERE idEmpresa = ? 
            AND data_operacao >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
            AND data_operacao < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ");
        $stmt->execute([$idEmpresa]);
        $lucroSemanaAnterior = $stmt->fetchColumn() ?: 0;

        // Calcular crescimento de lucro
        if ($lucroSemanaAnterior > 0) {
            $crescimentoLucro = (($lucroSemana - $lucroSemanaAnterior) / $lucroSemanaAnterior) * 100;
        } else {
            $crescimentoLucro = $lucroSemana > 0 ? 100 : 0;
        }

        // 6. MOEDA MAIS OPERADA (últimos 30 dias)
        $stmt = $pdo->prepare("
            SELECT moeda_origem, COUNT(*) as total_operacoes
            FROM cambio_operacoes 
            WHERE idEmpresa = ? AND data_operacao >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY moeda_origem 
            ORDER BY total_operacoes DESC 
            LIMIT 1
        ");
        $stmt->execute([$idEmpresa]);
        $moedaTopResult = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($moedaTopResult) {
            $moedaTop = $moedaTopResult['moeda_origem'];
            $operacoesMoedaTop = $moedaTopResult['total_operacoes'];
        } else {
            $moedaTop = 'N/A';
            $operacoesMoedaTop = 0;
        }

        // 7. TOTAL DE OPERAÇÕES (histórico)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM cambio_operacoes 
            WHERE idEmpresa = ?
        ");
        $stmt->execute([$idEmpresa]);
        $totalOperacoes = $stmt->fetchColumn() ?: 0;

        // 8. OPERAÇÕES DESTA SEMANA (para o "+X esta semana")
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM cambio_operacoes 
            WHERE idEmpresa = ? AND YEARWEEK(data_operacao, 1) = YEARWEEK(CURDATE(), 1)
        ");
        $stmt->execute([$idEmpresa]);
        $operacoesSemana = $stmt->fetchColumn() ?: 0;

    } catch (PDOException $e) {
        // Em caso de erro, usar valores padrão
        error_log("Erro ao buscar dados dos cards: " . $e->getMessage());
        $operacoesHoje = 0;
        $lucroSemana = 0;
        $lucroHoje = 0;
        $totalOperacoes = 0;
        $moedaTop = 'N/A';
        $operacoesMoedaTop = 0;
        $crescimentoOperacoes = 0;
        $crescimentoLucro = 0;
        $operacoesSemana = 0;
    }
}

// Função para formatar valores monetários com AR$ pequeno
function formatarMoeda($valor) {
    return '<span class="text-sm opacity-80">AR$</span> ' . number_format($valor, 2, ',', '.');
}

// Função para formatar percentual
function formatarPercentual($valor) {
    $sinal = $valor >= 0 ? '+' : '';
    return $sinal . number_format($valor, 1, ',', '.') . '%';
}

// Função para classe CSS baseada no valor
function getClasseVariacao($valor) {
    if ($valor > 0) {
        return 'text-green-600 dark:text-green-400';
    } elseif ($valor < 0) {
        return 'text-red-600 dark:text-red-400';
    } else {
        return 'text-slate-600 dark:text-slate-400';
    }
}

// Função para ícone baseado no valor
function getIconeVariacao($valor) {
    if ($valor > 0) {
        return 'fa-arrow-up';
    } elseif ($valor < 0) {
        return 'fa-arrow-down';
    } else {
        return 'fa-minus';
    }
}

// Gerar resumo dinâmico
function gerarResumo($operacoesHoje, $lucroHoje) {
    if ($operacoesHoje == 0) {
        return "Nenhuma operação realizada hoje. Que tal adicionar a primeira?";
    } elseif ($operacoesHoje == 1) {
        return "Você tem 1 nova operação hoje e seu lucro até o momento está em " . formatarMoeda($lucroHoje) . ".";
    } else {
        return "Você tem {$operacoesHoje} novas operações hoje e seu lucro até o momento está em " . formatarMoeda($lucroHoje) . ".";
    }
}
?>

<!-- Hero Section com Botão Fechar -->
<div class="gradient-bg rounded-2xl p-6 mb-6 text-white shadow-lg relative" id="heroSection">
    <button class="absolute top-4 right-4 w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition-all duration-200" onclick="fecharHero()" title="Fechar">
        <i class="fa-solid fa-times text-sm"></i>
    </button>
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center pr-12">
        <div>
            <h2 class="text-2xl font-bold mb-2">
                Bem-vindo de volta<?= isset($_SESSION['nome']) ? ', ' . htmlspecialchars($_SESSION['nome']) : '' ?>!
            </h2>
            <p class="opacity-90 max-w-2xl">
                <?= gerarResumo($operacoesHoje, $lucroHoje) ?>
            </p>
        </div>
        <button class="mt-4 md:mt-0 px-5 py-2 bg-white text-primary-600 rounded-lg font-semibold flex items-center gap-2 hover:bg-opacity-90 transition">
            <i class="fa-solid fa-download"></i> Exportar Relatório
        </button>
    </div>
</div>

<!-- Cards de Métricas -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <!-- Card 1: Operações Hoje -->
    <div class="card-gradient rounded-2xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm animate-fadeIn">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 rounded-lg bg-blue-100 dark:bg-slate-700 flex items-center justify-center text-blue-600 dark:text-blue-400">
                <i class="fas fa-exchange-alt"></i>
            </div>
            <h3 class="text-lg font-semibold text-slate-700 dark:text-slate-300">Operações Hoje</h3>
        </div>
        
        <!-- Valor Principal -->
        <div class="mb-3">
            <div class="text-3xl font-bold text-primary-600 dark:text-primary-400">
                <?= number_format($operacoesHoje, 0, ',', '.') ?>
            </div>
        </div>
        
        <!-- Indicadores na parte inferior -->
        <div class="flex items-center justify-between">
            <div class="px-3 py-1 rounded-full text-sm flex items-center 
                <?= $crescimentoOperacoes >= 0 ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-400' : 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-400' ?>">
                <i class="<?= getIconeVariacao($crescimentoOperacoes) ?> mr-1 text-xs"></i>
                <?= formatarPercentual(abs($crescimentoOperacoes)) ?>
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400">
                vs. ontem (<?= $operacoesOntem ?>)
            </div>
        </div>
    </div>
    
    <!-- Card 2: Lucro (7 dias) -->
    <div class="card-gradient rounded-2xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm animate-fadeIn animate-delay-100">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 rounded-lg bg-green-100 dark:bg-slate-700 flex items-center justify-center text-green-600 dark:text-green-400">
                <i class="fas fa-coins"></i>
            </div>
            <h3 class="text-lg font-semibold text-slate-700 dark:text-slate-300">Lucro (7 dias)</h3>
        </div>
        
        <!-- Valor Principal -->
        <div class="mb-3">
            <div class="text-3xl font-bold text-primary-600 dark:text-primary-400">
                <?= formatarMoeda($lucroSemana) ?>
            </div>
        </div>
        
        <!-- Indicadores na parte inferior -->
        <div class="flex items-center justify-between">
            <div class="px-3 py-1 rounded-full text-sm flex items-center
                <?= $crescimentoLucro >= 0 ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-400' : 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-400' ?>">
                <i class="<?= getIconeVariacao($crescimentoLucro) ?> mr-1 text-xs"></i>
                <?= formatarPercentual(abs($crescimentoLucro)) ?>
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400">
                vs. semana anterior
            </div>
        </div>
    </div>
    
    <!-- Card 3: Moeda Top -->
    <div class="card-gradient rounded-2xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm animate-fadeIn animate-delay-200">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 rounded-lg bg-purple-100 dark:bg-slate-700 flex items-center justify-center text-purple-600 dark:text-purple-400">
                <i class="fas fa-trophy"></i>
            </div>
            <h3 class="text-lg font-semibold text-slate-700 dark:text-slate-300">Moeda Top</h3>
        </div>
        
        <!-- Valor Principal -->
        <div class="mb-3">
            <div class="text-3xl font-bold text-primary-600 dark:text-primary-400">
                <?= htmlspecialchars($moedaTop) ?>
            </div>
        </div>
        
        <!-- Indicadores na parte inferior -->
        <div class="flex items-center justify-between">
            <div class="text-sm text-slate-600 dark:text-slate-300 font-medium">
                <?= $operacoesMoedaTop ?> operações
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400">
                últimos 30 dias
            </div>
        </div>
    </div>
    
    <!-- Card 4: Total Operações -->
    <div class="card-gradient rounded-2xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm animate-fadeIn animate-delay-300">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 rounded-lg bg-amber-100 dark:bg-slate-700 flex items-center justify-center text-amber-600 dark:text-amber-400">
                <i class="fas fa-chart-line"></i>
            </div>
            <h3 class="text-lg font-semibold text-slate-700 dark:text-slate-300">Total Operações</h3>
        </div>
        
        <!-- Valor Principal -->
        <div class="mb-3">
            <div class="text-3xl font-bold text-primary-600 dark:text-primary-400">
                <?= number_format($totalOperacoes, 0, ',', '.') ?>
            </div>
        </div>
        
        <!-- Indicadores na parte inferior -->
        <div class="flex items-center justify-between">
            <div class="text-sm text-slate-600 dark:text-slate-300 font-medium">
                +<?= $operacoesSemana ?> esta semana
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400">
                histórico completo
            </div>
        </div>
    </div>
</div>

<script>
function fecharHero() {
    const heroSection = document.getElementById('heroSection');
    heroSection.style.transform = 'translateY(-100%)';
    heroSection.style.opacity = '0';
    
    setTimeout(() => {
        heroSection.style.display = 'none';
        // Salvar preferência no localStorage
        localStorage.setItem('heroSectionClosed', 'false');
    }, 300);
}

// Verificar se o usuário já fechou o hero section
document.addEventListener('DOMContentLoaded', function() {
    if (localStorage.getItem('heroSectionClosed') === 'true') {
        const heroSection = document.getElementById('heroSection');
        heroSection.style.display = 'none';
    }
});
</script>
