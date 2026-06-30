<?php

namespace Vcoder7\Ltools\Services\Audit;

use Illuminate\Support\Str;

/**
 * Resolves the request context attached to every audit row (IP, user-agent, URL,
 * HTTP method, correlation request id). Registered as a singleton so the generated
 * request id is stable for the lifetime of a single request, letting multiple audit
 * rows from one request be grouped together.
 */
class AuditContext
{
    private ?string $requestId = null;

    public function capture(): array
    {
        if (! config('ltools.audit.capture_context', true)) {
            return $this->emptyContext();
        }

        $request = request();

        if (app()->runningInConsole() || $request === null) {
            return $this->emptyContext();
        }

        return [
            'ip' => $request->ip(),
            'user_agent' => $this->truncate((string) $request->userAgent()),
            'url' => $request->fullUrl(),
            'http_method' => $request->method(),
            'request_id' => $this->requestId($request),
        ];
    }

    private function requestId($request): string
    {
        if ($this->requestId !== null) {
            return $this->requestId;
        }

        $header = $request->header('X-Request-Id');

        return $this->requestId = $header ?: (string) Str::uuid();
    }

    private function emptyContext(): array
    {
        return [
            'ip' => null,
            'user_agent' => null,
            'url' => null,
            'http_method' => app()->runningInConsole() ? 'console' : null,
            'request_id' => null,
        ];
    }

    private function truncate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return Str::limit($value, 1024, '');
    }
}
