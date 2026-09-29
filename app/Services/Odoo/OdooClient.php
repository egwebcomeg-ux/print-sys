<?php

namespace App\Services\Odoo;

use App\Services\Odoo\Exceptions\OdooRejectedException;
use App\Services\Odoo\Exceptions\OdooTransientException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Odoo 18 external API over JSON-RPC (`/jsonrpc`), authenticated with a
 * user login + API key.
 */
class OdooClient implements OdooClientInterface
{
    private ?int $uid = null;

    public function __construct(
        private readonly string $url,
        private readonly string $db,
        private readonly string $username,
        private readonly string $apiKey,
        private readonly int $timeout = 15,
    ) {}

    public function executeKw(string $model, string $method, array $args, array $kwargs = []): mixed
    {
        return $this->call('object', 'execute_kw', [
            $this->db, $this->uid(), $this->apiKey, $model, $method, $args, (object) $kwargs,
        ]);
    }

    private function uid(): int
    {
        if ($this->uid !== null) {
            return $this->uid;
        }

        $uid = $this->call('common', 'authenticate', [$this->db, $this->username, $this->apiKey, (object) []]);

        if (! is_int($uid) || $uid <= 0) {
            throw new OdooRejectedException('Odoo authentication failed — check ODOO_DB / ODOO_USERNAME / ODOO_API_KEY.');
        }

        return $this->uid = $uid;
    }

    /** @param list<mixed> $args */
    private function call(string $service, string $method, array $args): mixed
    {
        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->post(rtrim($this->url, '/').'/jsonrpc', [
                    'jsonrpc' => '2.0',
                    'method' => 'call',
                    'params' => ['service' => $service, 'method' => $method, 'args' => $args],
                    'id' => random_int(1, PHP_INT_MAX),
                ]);
        } catch (ConnectionException $e) {
            throw new OdooTransientException('Odoo unreachable: '.$e->getMessage(), previous: $e);
        }

        if ($response->serverError() || $response->status() === 429) {
            throw new OdooTransientException("Odoo HTTP {$response->status()}");
        }

        if ($response->failed()) {
            throw new OdooRejectedException("Odoo HTTP {$response->status()}: ".mb_substr($response->body(), 0, 500));
        }

        $error = $response->json('error');
        if ($error !== null) {
            $message = $error['data']['message'] ?? $error['message'] ?? 'Unknown Odoo error';
            throw new OdooRejectedException((string) $message);
        }

        return $response->json('result');
    }
}
