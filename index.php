<?php
session_start();
require_once '../recursos/conexao/index.php';
// ...aqui seu PHP de busca de dados...
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Dashboard | CambioPro</title>
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
    <?php require_once "includes/styles.css"; ?>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100">
<div class="min-h-screen flex">
    <?php require_once 'includes/menu.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require_once 'includes/topo.php'; ?>

        <!-- Conteúdo do dashboard -->
        <main class="flex-1 overflow-y-auto p-6 bg-slate-50 dark:bg-slate-900">
            <div class="max-w-7xl mx-auto">
                <?php require_once 'includes/cards.php'; ?>
                <?php require_once 'includes/desempenho-cotacoes.php'; ?>
                <?php require_once 'includes/tabela.php'; ?>
            </div>
        </main>
    </div>
</div>

<!-- Botão Flutuante -->
<button class="fab-btn shadow-xl" data-bs-toggle="modal" data-bs-target="#modalAddOperacao">
    <i class="fa-solid fa-plus text-xl"></i>
</button>

<!-- Aqui seus modais se precisar -->

<!-- Scripts principais -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        // Gráfico de desempenho
        const ctx = document.getElementById('performanceChart').getContext('2d');
        const performanceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'],
                datasets: [
                    {
                        label: 'Operações',
                        data: [18, 22, 15, 24, 30, 28, 32],
                        borderColor: '#0ea5e9',
                        backgroundColor: 'rgba(14, 165, 233, 0.1)',
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Lucro (R$)',
                        data: [1200, 1450, 980, 1650, 2100, 1850, 2400],
                        borderColor: '#7c3aed',
                        backgroundColor: 'rgba(124, 58, 237, 0.1)',
                        tension: 0.3,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#64748b',
                            font: {
                                family: "'Inter', sans-serif"
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(100, 116, 139, 0.1)'
                        },
                        ticks: {
                            color: '#64748b'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(100, 116, 139, 0.1)'
                        },
                        ticks: {
                            color: '#64748b'
                        }
                    }
                }
            }
        });
        
        // Atualizar tema do gráfico quando dark mode mudar
        const observer = new MutationObserver(() => {
            performanceChart.options.scales.x.grid.color = document.documentElement.classList.contains('dark') ? 'rgba(100, 116, 139, 0.1)' : 'rgba(100, 116, 139, 0.1)';
            performanceChart.options.scales.y.grid.color = document.documentElement.classList.contains('dark') ? 'rgba(100, 116, 139, 0.1)' : 'rgba(100, 116, 139, 0.1)';
            performanceChart.options.scales.x.ticks.color = document.documentElement.classList.contains('dark') ? '#94a3b8' : '#64748b';
            performanceChart.options.scales.y.ticks.color = document.documentElement.classList.contains('dark') ? '#94a3b8' : '#64748b';
            performanceChart.options.plugins.legend.labels.color = document.documentElement.classList.contains('dark') ? '#94a3b8' : '#64748b';
            performanceChart.update();
        });
        
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class']
        });
    </script>
</body>
</html>
