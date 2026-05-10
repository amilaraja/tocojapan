@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-line focus:border-toco-red focus:ring-toco-red rounded-md shadow-sm']) }}>
