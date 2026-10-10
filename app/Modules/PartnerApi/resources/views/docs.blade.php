<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Toco Partner Stock API</title>
<style>
    :root { --ink:#1b1f3b; --soft:#5b6077; --line:#e3e5ee; --bg:#ffffff; --tint:#f6f7fb; --red:#E30613; }
    * { box-sizing: border-box; }
    body { margin:0; background:var(--bg); color:var(--ink); font:16px/1.6 -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
    header { border-bottom:3px solid var(--red); padding:20px 16px; }
    header .wrap, main { max-width: 880px; margin: 0 auto; }
    header p { margin:4px 0 0; color:var(--soft); font-size:14px; }
    header a { color:var(--ink); }
    main { padding: 8px 16px 64px; }
    h1 { font-size: 28px; line-height:1.25; margin: 24px 0 8px; }
    h2 { font-size: 21px; margin: 40px 0 8px; padding-top: 8px; border-top: 1px solid var(--line); }
    code { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 0.88em; background: var(--tint); padding: 1px 5px; border-radius: 4px; }
    pre { background: #12152b; color: #e8eaf6; padding: 14px 16px; border-radius: 6px; overflow-x: auto; font-size: 13.5px; line-height: 1.5; }
    pre code { background: none; padding: 0; color: inherit; }
    table { border-collapse: collapse; width: 100%; font-size: 14.5px; display: block; overflow-x: auto; }
    th, td { text-align: left; vertical-align: top; padding: 8px 10px; border-bottom: 1px solid var(--line); }
    th { background: var(--tint); font-weight: 600; }
    a { color: #1F2356; }
    tr:target, td:target { background: #fff7d6; }
</style>
</head>
<body>
<header><div class="wrap">
    <strong>Toco International · Partner Stock API</strong>
    <p>Base URL <code>{{ $baseUrl }}</code> · <a href="{{ route('partner-docs.openapi') }}">OpenAPI 3.1 specification (JSON)</a></p>
</div></header>
<main>
{!! $html !!}
</main>
</body>
</html>
