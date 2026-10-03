<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>{{ $subject }}</title>
<style>
body{margin:0;padding:0;-webkit-text-size-adjust:100%;}
a{color:#111114;}
@media only screen and (max-width:480px){
.outer{padding:0 !important;}
.wrap{width:100% !important;}
.topbar td{font-size:12px !important;}
.hdr-l,.hdr-r{display:block !important;width:100% !important;text-align:center !important;}
.hdr-l img{margin:0 auto !important;}
.hdr-r{padding-top:12px !important;font-size:12px !important;}
.banner{width:100% !important;height:auto !important;}
.intro{padding:24px 16px 12px 16px !important;}
.grid{padding:8px 16px 24px 16px !important;}
.grid-t{width:100% !important;}
.col{display:block !important;width:100% !important;padding-bottom:16px !important;}
.col.empty,.gut{display:none !important;}
.rowgap{display:none !important;}
.card{width:100% !important;}
.card-img{width:100% !important;height:auto !important;}
.card-title{font-size:15px !important;line-height:20px !important;}
.card-meta{font-size:12px !important;line-height:16px !important;}
.card-ref{font-size:12px !important;}
.card-price{font-size:20px !important;line-height:24px !important;}
.card-pad{padding-left:14px !important;padding-right:14px !important;}
.btn a{padding:14px 0 !important;font-size:13px !important;}
.cta{padding:28px 16px !important;}
.foot{padding-left:16px !important;padding-right:16px !important;}
.small{font-size:12px !important;}
.info{padding:24px 16px 8px 16px !important;}
.info-col{display:block !important;width:100% !important;padding-bottom:16px !important;}
.info-gut{display:none !important;}
.info-img{width:100% !important;height:auto !important;}
}
</style>
</head>
<body style="margin:0;padding:0;background:#ECECEF;">
<!-- SECTION:preheader -->
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#ECECEF;opacity:0;">{{ $previewText }}&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#ECECEF" style="background:#ECECEF;">
<tr><td class="outer" align="center" style="padding:24px 0;">
<table role="presentation" class="wrap" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;background:#FFFFFF;">

<!-- SECTION:topbar -->
<tr><td class="topbar" bgcolor="#0D0D0F" style="background:#0D0D0F;padding:8px 24px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
<td style="font-family:Arial, Helvetica, sans-serif;font-size:11px;line-height:16px;color:#BDBDC4;">{{ $settings['top_bar_text'] }}</td>
<td align="right" style="font-family:Arial, Helvetica, sans-serif;font-size:11px;line-height:16px;"><a href="@{{ mirror }}" target="_blank" style="color:#FFFFFF;text-decoration:underline;">View in browser</a></td>
</tr></table></td></tr>

<!-- SECTION:header -->
<tr><td bgcolor="#FFFFFF" style="background:#FFFFFF;padding:18px 24px;border-bottom:3px solid #E30613;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
<td class="hdr-l" valign="middle" style="width:150px;"><a href="{{ $homeUrl }}" data-utm="logo" target="_blank"><img src="{{ $logoUrl }}" width="150" height="35" alt="TOCO International" style="display:block;width:150px;height:35px;border:0;font-family:Arial, Helvetica, sans-serif;font-size:16px;font-weight:bold;color:#111114;"></a></td>
<td class="hdr-r" align="right" valign="middle" style="font-family:Arial, Helvetica, sans-serif;font-size:11px;line-height:16px;font-weight:bold;letter-spacing:1px;">
@foreach ($navLinks as $i => $link)@if ($i > 0)&nbsp;&nbsp;&nbsp;&nbsp;@endif<a href="{{ $link['url'] }}" data-utm="nav" target="_blank" style="color:{{ $loop->last && $loop->count > 1 ? '#E30613' : '#111114' }};text-decoration:none;">{{ $link['label'] }}</a>@endforeach
</td></tr></table></td></tr>
@if ($banner)

<!-- SECTION:banner -->
<tr><td bgcolor="#0D0D0F" style="background:#0D0D0F;"><a href="{{ $banner['link'] }}" data-utm="banner" target="_blank" style="text-decoration:none;"><img class="banner" src="{{ $banner['url'] }}" width="600" alt="{{ $banner['alt'] }}" style="display:block;width:100%;max-width:600px;height:auto;border:0;font-family:Arial, Helvetica, sans-serif;font-size:18px;line-height:24px;font-weight:bold;color:#FFFFFF;"></a></td></tr>
@endif

<!-- SECTION:stock -->
<tr><td class="stock" align="center" bgcolor="#FFFFFF" style="background:#FFFFFF;padding:24px 24px 0 24px;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;"><tr><td bgcolor="#E30613" style="background:#E30613;"><a href="{{ $stockUrl }}" data-utm="view-all-stock" target="_blank" style="display:block;padding:14px 32px;font-family:Arial, Helvetica, sans-serif;font-size:14px;line-height:16px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#FFFFFF;text-decoration:none;">VIEW ALL STOCK</a></td></tr></table>
</td></tr>

<!-- SECTION:intro -->
<tr><td class="intro" style="padding:32px 24px 16px 24px;font-family:Arial, Helvetica, sans-serif;">
@if (filled($kicker))<div style="font-size:11px;line-height:16px;font-weight:bold;letter-spacing:2px;color:#E30613;">{{ $kicker }}</div>@endif
@if (filled($headline))<div style="padding-top:8px;font-size:22px;line-height:28px;font-weight:bold;color:#111114;">{{ $headline }}</div>@endif
@if (filled($intro))<div style="padding-top:10px;font-size:13px;line-height:20px;color:#55555C;">{!! nl2br(e($intro)) !!}</div>@endif
</td></tr>

<!-- SECTION:vehicles -->
<tr><td class="grid" style="padding:8px 24px 32px 24px;">
<table role="presentation" class="grid-t" width="552" cellpadding="0" cellspacing="0" border="0" style="width:552px;">
@foreach ($rows as $row)
@if (! $loop->first)
<tr><td class="rowgap" colspan="5" height="12" style="height:12px;font-size:0;line-height:0;">&nbsp;</td></tr>
@endif
<tr>@for ($i = 0; $i < 3; $i++)@if ($i > 0)<td class="gut" width="12" style="width:12px;font-size:0;line-height:0;">&nbsp;</td>@endif @if (isset($row[$i]))<td class="col{{ $i === 0 ? ' col-l' : '' }}" width="176" valign="top" style="width:176px;"><x-mailer-email::vehicle-card :card="$row[$i]" /></td>@else<td class="col empty" width="176" style="width:176px;">&nbsp;</td>@endif @endfor</tr>
@endforeach
</table>
</td></tr>

<!-- SECTION:cta -->
<tr><td class="cta" bgcolor="#0D0D0F" align="center" style="background:#0D0D0F;padding:32px 24px;border-top:3px solid #E30613;font-family:Arial, Helvetica, sans-serif;">
<div style="font-size:20px;line-height:26px;font-weight:bold;color:#FFFFFF;">{{ $settings['cta_heading'] }}</div>
@if (filled($settings['cta_text']))<div class="small" style="padding-top:8px;font-size:13px;line-height:20px;color:#C9C9CF;">{{ $settings['cta_text'] }}</div>@endif
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:20px auto 0 auto;"><tr><td bgcolor="#E30613" style="background:#E30613;"><a href="{{ $ctaUrl }}" data-utm="cta" target="_blank" style="display:block;padding:14px 28px;font-family:Arial, Helvetica, sans-serif;font-size:13px;line-height:16px;font-weight:bold;letter-spacing:1px;color:#FFFFFF;text-decoration:none;">{{ $settings['cta_button'] }} &rsaquo;</a></td></tr></table>
</td></tr>

<!-- SECTION:info -->
<tr><td class="info" bgcolor="#FFFFFF" style="background:#FFFFFF;padding:28px 24px 28px 24px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
@foreach ($infoBoxes as $box)
@if (! $loop->first)<td class="info-gut" width="16" style="width:16px;font-size:0;line-height:0;">&nbsp;</td>@endif
<td class="info-col" width="268" valign="top" style="width:268px;"><a href="{{ $box['url'] }}" data-utm="{{ $box['utm'] }}" target="_blank" style="text-decoration:none;"><img class="info-img" src="{{ $box['img'] }}" width="268" height="179" alt="{{ $box['alt'] }}" style="display:block;width:268px;height:179px;border:0;font-family:Arial, Helvetica, sans-serif;font-size:13px;line-height:18px;color:#111114;"></a></td>
@endforeach
</tr></table></td></tr>

<!-- SECTION:footer -->
<tr><td class="foot" bgcolor="#0D0D0F" align="center" style="background:#0D0D0F;padding:28px 24px 32px 24px;font-family:Arial, Helvetica, sans-serif;">
<div style="font-size:13px;line-height:18px;font-weight:bold;letter-spacing:2px;color:#FFFFFF;">{{ $settings['footer_company'] }}</div>
@if (filled($settings['footer_address']))<div class="small" style="padding-top:8px;font-size:12px;line-height:18px;color:#A6A6AE;">{{ $settings['footer_address'] }}</div>@endif
<div class="small" style="padding-top:6px;font-size:12px;line-height:18px;color:#A6A6AE;">@if (filled($settings['footer_phone']))Phone <a href="tel:{{ $phoneHref }}" style="color:#FFFFFF;text-decoration:none;">{{ $settings['footer_phone'] }}</a>@endif @if (filled($settings['footer_phone']) && filled($settings['footer_whatsapp']))&middot; @endif @if (filled($settings['footer_whatsapp']))WhatsApp <a href="https://wa.me/{{ $whatsappHref }}" style="color:#FFFFFF;text-decoration:none;">{{ $settings['footer_whatsapp'] }}</a>@endif @if (filled($settings['footer_email']))<br><a href="mailto:{{ $settings['footer_email'] }}" style="color:#FFFFFF;text-decoration:none;">{{ $settings['footer_email'] }}</a>@endif</div>
<table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0" style="margin:18px auto 0 auto;"><tr><td valign="middle" style="padding:0 10px 0 0;font-family:Arial, Helvetica, sans-serif;font-size:11px;line-height:32px;font-weight:bold;letter-spacing:2px;color:#FFFFFF;">FOLLOW US</td>@foreach ($socialLinks as $social)<td valign="middle" style="padding:0 0 0 {{ $loop->first ? 0 : 8 }}px;"><a href="{{ $social['url'] }}" target="_blank" style="text-decoration:none;"><img src="{{ $social['img'] }}" width="32" height="32" alt="{{ $social['label'] }}" style="display:block;width:32px;height:32px;border:0;"></a></td>@endforeach</tr></table>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:20px 0 0 0;"><div style="border-top:1px solid #2A2A30;font-size:0;line-height:0;">&nbsp;</div></td></tr></table>
<div class="small" style="padding-top:16px;font-size:11px;line-height:17px;color:#A6A6AE;">{{ $settings['footer_reason'] }}</div>
<div class="small" style="padding-top:6px;font-size:11px;line-height:17px;"><a href="@{{ unsubscribe }}" target="_blank" style="color:#FFFFFF;text-decoration:underline;">Unsubscribe</a></div>
</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
