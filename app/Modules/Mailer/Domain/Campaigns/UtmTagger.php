<?php

namespace App\Modules\Mailer\Domain\Campaigns;

/**
 * TOC-CMP-005: every link to tocojapan.com gets utm_source=brevo,
 * utm_medium=email, utm_campaign={slug} and utm_content.
 *
 * utm_content comes from the link's data-utm attribute (stock ref, banner,
 * cta, logo, nav), which is removed from the output. Other hosts, mailto:,
 * tel: and Brevo tags are left alone.
 */
class UtmTagger
{
    /** @param  list<string>  $hosts  hosts treated as tocojapan.com */
    public function __construct(protected array $hosts = []) {}

    public function tag(string $html, string $campaignSlug): string
    {
        $hosts = $this->hosts();

        return preg_replace_callback('/<a\b[^>]*>/i', function (array $m) use ($campaignSlug, $hosts): string {
            $tag = $m[0];
            $content = preg_match('/\sdata-utm="([^"]*)"/i', $tag, $c) ? html_entity_decode($c[1]) : 'link';
            $tag = preg_replace('/\sdata-utm="[^"]*"/i', '', $tag);

            return preg_replace_callback('/\shref="([^"]*)"/i', function (array $h) use ($campaignSlug, $content, $hosts): string {
                $url = html_entity_decode($h[1], ENT_QUOTES | ENT_HTML5);
                $host = strtolower((string) parse_url($url, PHP_URL_HOST));

                if (! in_array(preg_replace('/^www\./', '', $host), $hosts, true)) {
                    return $h[0];
                }

                return ' href="'.e($this->withUtm($url, $campaignSlug, $content)).'"';
            }, $tag, 1);
        }, $html) ?? $html;
    }

    public function withUtm(string $url, string $campaignSlug, string $content): string
    {
        $fragment = '';
        if (($pos = strpos($url, '#')) !== false) {
            $fragment = substr($url, $pos);
            $url = substr($url, 0, $pos);
        }

        [$base, $query] = array_pad(explode('?', $url, 2), 2, '');
        parse_str($query, $params);

        $params = array_merge($params, [
            'utm_source' => 'brevo',
            'utm_medium' => 'email',
            'utm_campaign' => $campaignSlug,
            'utm_content' => $content,
        ]);

        return $base.'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986).$fragment;
    }

    /** @return list<string> */
    protected function hosts(): array
    {
        if ($this->hosts !== []) {
            return $this->hosts;
        }

        $app = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return array_values(array_unique(array_filter(['tocojapan.com', preg_replace('/^www\./', '', $app)])));
    }
}
