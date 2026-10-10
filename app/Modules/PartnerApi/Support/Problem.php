<?php

namespace App\Modules\PartnerApi\Support;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * RFC 9457 problem+json for every partner error (SRS §6 conventions):
 * type, title, status, detail, request_id. Unknown failures never leak
 * internals.
 */
class Problem
{
    public static function fromThrowable(Throwable $e, Request $request): Response
    {
        return match (true) {
            $e instanceof PartnerApiException => self::make($request, $e->status, $e->errorCode, $e->title, $e->detail, $e->extra, $e->headers),
            $e instanceof ValidationException => self::make($request, 422, 'validation_failed', 'The request has invalid values.',
                'See errors for each field.', ['errors' => $e->errors()]),
            $e instanceof AuthenticationException => self::make($request, 401, 'invalid_token', 'Missing or invalid access token.'),
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => self::make($request, 404, 'not_found', 'Not found.'),
            $e instanceof MethodNotAllowedHttpException => self::make($request, 405, 'method_not_allowed', 'Method not allowed.', null, [], $e->getHeaders()),
            $e instanceof HttpExceptionInterface => self::make($request, $e->getStatusCode(), 'http_'.$e->getStatusCode(),
                JsonResponse::$statusTexts[$e->getStatusCode()] ?? 'Error', null, [], $e->getHeaders()),
            default => self::make($request, 500, 'server_error', 'Something went wrong on our side.',
                'Retry later. If it keeps happening, send us the request_id.'),
        };
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, string>  $headers
     */
    public static function make(Request $request, int $status, string $code, string $title, ?string $detail = null,
        array $extra = [], array $headers = []): Response
    {
        $body = array_filter([
            'type' => PartnerApi::docsUrl().'#error-'.str_replace('_', '-', $code),
            'title' => $title,
            'status' => $status,
            'code' => $code,
            'detail' => $detail,
            'request_id' => PartnerApi::requestId($request),
        ], fn ($value) => $value !== null) + $extra;

        return PartnerApi::finalize($request, new JsonResponse($body, $status, $headers + [
            'Content-Type' => 'application/problem+json',
        ]));
    }
}
