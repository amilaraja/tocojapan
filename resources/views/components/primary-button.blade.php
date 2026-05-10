<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-5 py-2.5 bg-toco-red border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-toco-red-deep focus:bg-toco-red-deep active:bg-toco-red-deep focus:outline-none focus:ring-2 focus:ring-toco-red focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
