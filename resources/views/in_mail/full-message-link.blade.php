<div class="mt-2 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">
        Il messaggio completo è troppo lungo per essere mostrato qui.
    </p>
    <a href="{{ $url }}" target="_blank" class="inline-flex items-center gap-1 text-primary-600 hover:text-primary-700 font-medium">
        Leggi messaggio completo
        <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
        <span class="text-xs">({{ $size }} KB)</span>
    </a>
</div>
