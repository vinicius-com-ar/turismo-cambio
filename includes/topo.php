<header class="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
    <div class="px-6 py-4 flex items-center justify-between">
        <div class="flex items-center">
            <button class="md:hidden mr-4">
                <i class="fa-solid fa-bars text-slate-600 dark:text-slate-300"></i>
            </button>
            <h1 class="text-xl font-semibold text-slate-900 dark:text-white">Dashboard</h1>
        </div>
        <div class="flex items-center space-x-4">
            <div class="relative">
                <button class="p-2 rounded-full hover:bg-slate-100 dark:hover:bg-slate-700">
                    <i class="fa-solid fa-bell text-slate-600 dark:text-slate-300"></i>
                </button>
                <span class="notification-badge">3</span>
            </div>
            <div class="hidden md:flex items-center space-x-2">
                <div class="relative">
                    <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-slate-700 flex items-center justify-center">
                        <i class="fa-solid fa-user text-primary-600 dark:text-primary-400 text-sm"></i>
                    </div>
                    <div class="absolute bottom-0 right-0 w-2 h-2 bg-green-500 rounded-full border-2 border-white dark:border-slate-800"></div>
                </div>
                <div class="hidden lg:block">
                    <div class="text-sm font-medium text-slate-900 dark:text-white">Amilson Silva</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">Gerente</div>
                </div>
            </div>
            <button id="theme-toggle" class="p-2 rounded-full hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="fa-solid fa-moon dark:hidden text-slate-600"></i>
                <i class="fa-solid fa-sun hidden dark:block text-yellow-400"></i>
            </button>
        </div>
    </div>
</header>
