<?php

namespace App\Modules\PartnerApi\Http\Controllers;

use App\Modules\PartnerApi\Support\PartnerAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Partner developer docs (FR-DOC-01): the guide rendered from markdown and
 * the OpenAPI file, both written alongside the code. Public once the API is
 * switched on; before that only partner admins can preview them.
 */
class DocsController
{
    public function guide(Request $request): View
    {
        $this->ensureVisible();

        $markdown = file_get_contents(app_path('Modules/PartnerApi/docs/guide.md'));

        return view('partner-api::docs', [
            // Trusted, repo-authored markdown: inline HTML (error anchors) allowed.
            'html' => Str::markdown($markdown, ['html_input' => 'allow', 'allow_unsafe_links' => false]),
            'baseUrl' => \App\Modules\PartnerApi\Support\PartnerApi::baseUrl(),
        ]);
    }

    public function openapi(): JsonResponse
    {
        $this->ensureVisible();

        $spec = json_decode(file_get_contents(app_path('Modules/PartnerApi/docs/openapi.json')), true, flags: JSON_THROW_ON_ERROR);

        return (new JsonResponse($spec, 200, [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT))
            ->header('Content-Disposition', 'inline; filename="toco-partner-api.json"')
            ->header('X-Robots-Tag', 'noindex');
    }

    private function ensureVisible(): void
    {
        abort_unless(config('partner_api.enabled') || PartnerAccess::canAdmin(), 404);
    }
}
