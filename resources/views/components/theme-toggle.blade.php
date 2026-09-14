@props(['class' => ''])

<button
    type="button"
    x-data
    @click="
        const isDark = document.documentElement.classList.toggle('dark');
        localStorage.setItem('ceba-theme', isDark ? 'dark' : 'light');
    "
    {{ $attributes->merge(['class' => 'inline-flex items-center justify-center rounded-md p-2 text-ink-faint hover:bg-surface-2 hover:text-ink transition '.$class]) }}
    aria-label="Cambiar tema"
>
    <x-heroicon-o-sun class="hidden dark:block h-5 w-5" />
    <x-heroicon-o-moon class="block dark:hidden h-5 w-5" />
</button>
