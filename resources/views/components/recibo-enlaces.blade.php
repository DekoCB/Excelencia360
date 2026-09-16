@props(['recibo'])

@if ($recibo)
    @if ($recibo->getFirstMedia('pdf'))
        <a href="{{ $recibo->getFirstMediaUrl('pdf') }}" target="_blank" class="block text-xs font-medium text-accent hover:underline">Recibo (A4)</a>
    @endif
    @if ($recibo->getFirstMedia('pdf_80mm'))
        <a href="{{ $recibo->getFirstMediaUrl('pdf_80mm') }}" target="_blank" class="block text-xs font-medium text-accent hover:underline">Recibo (80mm)</a>
    @endif
@endif
