<div x-data="{ width: 600 }">
    <div role="group" aria-label="Preview width" style="display:flex;gap:.5rem;justify-content:center;margin-bottom:1rem;">
        <x-filament::button size="sm" x-on:click="width = 600" color="gray" icon="heroicon-o-computer-desktop">Desktop (600px)</x-filament::button>
        <x-filament::button size="sm" x-on:click="width = 375" color="gray" icon="heroicon-o-device-phone-mobile">Mobile (375px)</x-filament::button>
    </div>
    <p style="text-align:center;font-size:.875rem;opacity:.75;margin:0 0 .75rem 0;">This is exactly the email that will be pushed to Brevo. "View in browser" and "Unsubscribe" work after Brevo sends it.</p>
    <div style="background:#ECECEF;padding:1rem 0;display:flex;justify-content:center;">
        <iframe title="Email preview" srcdoc="{{ $html }}" x-bind:style="'width:' + (width + 40) + 'px;height:1100px;border:0;background:#fff;'" style="width:640px;height:1100px;border:0;background:#fff;"></iframe>
    </div>
    <p style="text-align:center;font-size:.8rem;opacity:.6;margin:.5rem 0 0 0;">{{ number_format(strlen($html) / 1024, 1) }} KB</p>
</div>
