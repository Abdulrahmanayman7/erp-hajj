<?php

namespace App\Core\Shared\Middleware;

use App\Core\Shared\CorrelationId;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates or regenerates X-Correlation-ID and echoes it on the response.
 */
class AssignCorrelationId
{
    private const HEADER = 'X-Correlation-ID';

    private const PATTERN = '/^[A-Za-z0-9-]{8,64}$/';

    public function __construct(private readonly CorrelationId $correlationId) {}

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->headers->get(self::HEADER);
        $id = is_string($incoming) && preg_match(self::PATTERN, $incoming) === 1
            ? $incoming
            : (string) Str::uuid();

        $this->correlationId->set($id);
        $request->headers->set(self::HEADER, $id);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }
}
