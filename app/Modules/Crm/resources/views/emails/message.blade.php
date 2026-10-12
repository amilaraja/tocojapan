<!doctype html>
<html><body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6"><tr><td align="center" style="padding:24px 12px">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:6px;overflow:hidden">
<tr><td style="background:#1F2356;padding:16px 24px;color:#ffffff;font-size:20px;font-weight:bold">TOCO <span style="color:#E30613">Japan</span></td></tr>
@if ($tracker)
<tr><td style="padding:20px 24px 4px">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
@foreach ($tracker['labels'] as $n => $label)
@php($state = $tracker['stage'] === null ? 'next' : ($n < $tracker['stage'] ? 'done' : ($n === $tracker['stage'] ? 'now' : 'next')))
<td align="center" valign="top" style="width:20%;padding:0 2px;font-size:11px;line-height:14px;color:{{ $state === 'next' ? '#9ca3af' : '#1f2937' }}">
<div style="margin:0 auto 6px;width:28px;height:28px;line-height:28px;border-radius:14px;font-weight:bold;font-size:12px;color:#ffffff;background:{{ $state === 'done' ? '#16a34a' : ($state === 'now' ? '#E30613' : '#d1d5db') }}">{{ sprintf('%02d', $n) }}</div>
{{ $label }}</td>
@endforeach
</tr></table></td></tr>
@endif
<tr><td style="padding:20px 24px;font-size:15px;line-height:22px">{!! nl2br(e($text)) !!}</td></tr>
@if ($button)
<tr><td style="padding:0 24px 24px"><a href="{{ $button['url'] }}" style="display:inline-block;background:#E30613;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:4px;font-weight:bold;font-size:14px">{{ $button['label'] }}</a></td></tr>
@endif
<tr><td style="padding:14px 24px;background:#f9fafb;font-size:12px;color:#6b7280">TOCO International Co., Ltd · Sano City, Tochigi, Japan · <a href="{{ url('/') }}" style="color:#6b7280">tocojapan.com</a>
@unless ($forCustomer)<br>Internal CRM notification.@endunless</td></tr>
</table></td></tr></table>
</body></html>
