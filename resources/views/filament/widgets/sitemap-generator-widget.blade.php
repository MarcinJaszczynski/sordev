<div class="p-6 bg-white rounded-lg border border-gray-200 dark:bg-gray-900 dark:border-gray-700">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Sitemapa XML') }}</h3>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        {{ __('Zarządzaj generowaniem sitemapy dla wyszukiwarek. Kliknij przycisk poniżej, aby wygenerować nową sitemapę.') }}
    </p>
    <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">
        {{ __('Sitemapa jest automatycznie generowana codziennie o 3:00 UTC.') }}
    </p>
    
    <div class="mt-6">
        <a href="javascript:void(0)" onclick="document.getElementById('sitemap-form').submit(); return false;" class="inline-flex items-center px-6 py-3 bg-orange-600 text-white border border-orange-700 rounded-lg hover:bg-orange-700 active:bg-orange-800 transition-colors duration-200 gap-2 font-semibold shadow-md hover:shadow-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            {{ __('Generuj Sitemapę') }}
        </a>
        <form id="sitemap-form" action="/admin/sitemap/generate" method="POST" style="display:none;">
            @csrf
        </form>
    </div>
</div>
