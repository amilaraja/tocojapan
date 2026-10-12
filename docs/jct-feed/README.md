# JCT stock feed (JapaneseCarTrade.com)

JCT imports a dealer's stock once every 24 hours from a URL the dealer gives them.
This module (`app/Modules/JctFeed`) produces that file in JCT's own format. It is separate
from the Partner Stock API: no partner records, keys, quotas or webhooks. It only reads vehicles.

Specs: `/home/tocojapan.com/docs/jct-stock-xml-explaination.pdf` (XML, 8 Jan 2013) and JCT's blog post
"JCT Automatic Stock Data Import from JSON File Explanation" (JSON variant, same fields).

## URLs

| What | URL |
|---|---|
| Stock, JSON | `https://tocojapan.com/feeds/jct/stock.json?token=<JCT token>` |
| Stock, XML | `https://tocojapan.com/feeds/jct/stock.xml?token=<JCT token>` |
| Photos | `https://tocojapan.com/feeds/jct/images/<id>-<signature>.jpg` (the `image_base_url` + `picture` names) |

The feed answers 404 until `JCT_FEED_TOKEN` is set in `.env` (JCT issues the token; we check it with
`hash_equals`). If JCT names the query parameter something other than `token`, set `JCT_FEED_TOKEN_PARAM`.
After changing `.env`: `php artisan optimize` (as tocoj9771).

Photos need no token (JCT appends the file name to `image_base_url`), but each name carries an HMAC
signature, and only photos of cars currently in the feed are served.

## What goes in

- Toco's own stock, status `published` (JCT "Available") and `reserved` (JCT "Reserved"). Sold, draft and
  supplier-feed stock (OnePrice etc.) are left out; JCT drops cars that leave the file.
- Newest first, capped at `JCT_FEED_MAX_VEHICLES` (default 1500 = JCT Premium; Gold = 500).
- Prices: USD stock in `price_usd`; when discounted by at least US$100, `price_usd` = list price and
  `offer_price_usd` = discounted price (JCT's rule). Smaller discounts go as the plain discounted price.
  JPY stock would use `price` / `offer_price` (min gap ¥10,000). Price on request: no price tag.
- Chassis number masked like the website (`ZVG1*-*******`) unless `JCT_FEED_FULL_CHASSIS=true`.
- Up to 10 photos, JPEG, fitted inside 1024 x 800, quality stepped down until ≤ 512 KB. Rendered from the
  watermarked `gallery` file on first request, cached in `storage/app/private/jct-feed/images/`.
- `location` = Japan, `port` = `JCT_FEED_PORT` (default Yokohama). `other_info` = description as plain
  text, max 500 characters. `accessories` = feature labels, comma separated.
- Not sent (no data at Toco): `grade_ext`, `grade_int`, `loading_capacity`, `repaired`, `accident`,
  `youtube_embed_video_url`, `start_date`, `close_date`.

Field mapping: `App\Modules\JctFeed\Domain\JctStockFeed::row()`.

## Commands

    php artisan jct:feed                      # counts and URLs
    php artisan jct:feed --warm               # render every photo JCT will fetch
    php artisan jct:feed --write=/tmp/f.json  # write the feed to a file to check it (.json or .xml)

The feed is cached for 10 minutes (`jct-feed:json` / `jct-feed:xml`); `jct:feed` clears that cache.

## Shared code touched

| File | Change |
|---|---|
| `bootstrap/providers.php` | registers `JctFeedServiceProvider` |
| `phpunit.xml`, `tests/Pest.php` | `JctFeed` test suite |

Tests: `--testsuite=JctFeed` (`tests/JctFeed/JctFeedTest.php`).

## To confirm with JCT

1. Their security token, and the query parameter name they will call us with.
2. JSON or XML (both are live; JSON is their newer format).
3. In JSON, `picture` is a list (`["a.jpg","b.jpg"]`), and `data` is keyed `"1"`, `"2"`, … as in their sample.
4. Membership level (Premium 1500 / Gold 500 cars).
