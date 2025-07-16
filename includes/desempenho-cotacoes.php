<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Chart -->
    <div class="lg:col-span-2 card-gradient rounded-2xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-200">Desempenho Semanal</h3>
            <div class="flex space-x-2">
                <button class="px-3 py-1 text-sm rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">7D</button>
                <button class="px-3 py-1 text-sm rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400">30D</button>
                <button class="px-3 py-1 text-sm rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400">90D</button>
            </div>
        </div>
        <div class="chart-container">
            <canvas id="performanceChart"></canvas>
        </div>
    </div>
    <!-- Cotações Atuais -->
    <div class="card-gradient rounded-2xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm">
        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-200 mb-6">Cotações Atuais</h3>
           <div class="space-y-4">
                                <div class="flex justify-between items-center pb-4 border-b border-slate-100 dark:border-slate-700">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-slate-700 flex items-center justify-center mr-3">
                                            <i class="fa-solid fa-dollar-sign text-blue-600 dark:text-blue-400"></i>
                                        </div>
                                        <div>
                                            <div class="font-medium">Dólar (USD)</div>
                                            <div class="text-sm text-slate-500 dark:text-slate-400">Compra / Venda</div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="font-medium text-right">R$ 5,20</div>
                                        <div class="text-sm text-right text-green-600 dark:text-green-400">+0,8%</div>
                                    </div>
                                </div>
                                
                                <div class="flex justify-between items-center pb-4 border-b border-slate-100 dark:border-slate-700">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-full bg-purple-100 dark:bg-slate-700 flex items-center justify-center mr-3">
                                            <i class="fa-solid fa-euro-sign text-purple-600 dark:text-purple-400"></i>
                                        </div>
                                        <div>
                                            <div class="font-medium">Euro (EUR)</div>
                                            <div class="text-sm text-slate-500 dark:text-slate-400">Compra / Venda</div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="font-medium text-right">R$ 5,65</div>
                                        <div class="text-sm text-right text-red-600 dark:text-red-400">-0,3%</div>
                                    </div>
                                </div>
                                
                                <div class="flex justify-between items-center pb-4 border-b border-slate-100 dark:border-slate-700">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-full bg-yellow-100 dark:bg-slate-700 flex items-center justify-center mr-3">
                                            <i class="fa-solid fa-sterling-sign text-yellow-600 dark:text-yellow-400"></i>
                                        </div>
                                        <div>
                                            <div class="font-medium">Libra (GBP)</div>
                                            <div class="text-sm text-slate-500 dark:text-slate-400">Compra / Venda</div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="font-medium text-right">R$ 6,32</div>
                                        <div class="text-sm text-right text-green-600 dark:text-green-400">+1,2%</div>
                                    </div>
                                </div>
                                
                                <div class="flex justify-between items-center">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-full bg-cyan-100 dark:bg-slate-700 flex items-center justify-center mr-3">
                                            <i class="fa-solid fa-yen-sign text-cyan-600 dark:text-cyan-400"></i>
                                        </div>
                                        <div>
                                            <div class="font-medium">Iene (JPY)</div>
                                            <div class="text-sm text-slate-500 dark:text-slate-400">Compra / Venda</div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="font-medium text-right">R$ 0,034</div>
                                        <div class="text-sm text-right text-red-600 dark:text-red-400">-0,5%</div>
                                    </div>
                                </div>
                            </div>
    </div>
</div>
