<?php

namespace App\Modules\PartnerApi\Http\Middleware;

use App\Modules\PartnerApi\Models\PartnerAccessToken;
use App\Modules\PartnerApi\Support\PartnerApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Per-route scope check (§8.2). Usage: ->middleware('partner.scope:stock:read'). */
class RequireScope
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        /** @var PartnerAccessToken $token */
        $token = $request->attributes->get(AuthenticatePartner::TOKEN);

        if (! $token->hasScope($scope)) {
            throw new PartnerApiException(403, 'insufficient_scope', 'This token does not allow that action.',
                "Needs the {$scope} scope.", extra: ['required_scope' => $scope]);
        }

        return $next($request);
    }
}
