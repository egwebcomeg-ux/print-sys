<?php

namespace App\Services\Odoo;

use Throwable;

/**
 * In-memory stand-in for Odoo (ODOO_FAKE=true, and in tests). Keeps enough
 * state to behave like the real thing for the calls OdooInvoiceService makes.
 */
class FakeOdooClient implements OdooClientInterface
{
    /** @var list<array{model: string, method: string, args: list<mixed>, kwargs: array<string, mixed>}> */
    public array $calls = [];

    /** @var array<int, array<string, mixed>> */
    private array $partners = [];

    /** @var array<int, array<string, mixed>> */
    private array $moves = [];

    /** When set, the next call throws this instead of answering. */
    public ?Throwable $failNextWith = null;

    public function executeKw(string $model, string $method, array $args, array $kwargs = []): mixed
    {
        $this->calls[] = compact('model', 'method', 'args', 'kwargs');

        if ($this->failNextWith) {
            $e = $this->failNextWith;
            $this->failNextWith = null;
            throw $e;
        }

        return match ("{$model}.{$method}") {
            'res.partner.search_read' => $this->searchPartner($args[0] ?? []),
            'res.partner.create' => $this->create($this->partners, 1000, $args[0]),
            'account.move.search_read' => $this->searchMove($args[0] ?? []),
            'account.move.create' => $this->create($this->moves, 5000, $args[0] + ['name' => '/', 'state' => 'draft']),
            'account.move.action_post' => $this->post($args[0]),
            'account.move.read' => array_map(fn ($id) => ['id' => $id] + $this->moves[$id], $args[0]),
            default => null,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $store
     * @param  array<string, mixed>  $values
     */
    private function create(array &$store, int $base, array $values): int
    {
        $id = $base + count($store) + 1;
        $store[$id] = $values;

        return $id;
    }

    /**
     * @param  list<array{0: string, 1: string, 2: mixed}>  $domain
     * @return list<array<string, mixed>>
     */
    private function searchPartner(array $domain): array
    {
        $name = $domain[0][2] ?? null;

        foreach ($this->partners as $id => $partner) {
            if ($partner['name'] === $name) {
                return [['id' => $id]];
            }
        }

        return [];
    }

    /**
     * @param  list<array{0: string, 1: string, 2: mixed}>  $domain
     * @return list<array<string, mixed>>
     */
    private function searchMove(array $domain): array
    {
        $ref = null;
        foreach ($domain as $condition) {
            if ($condition[0] === 'ref') {
                $ref = $condition[2];
            }
        }

        foreach ($this->moves as $id => $move) {
            if (($move['ref'] ?? null) === $ref) {
                return [['id' => $id, 'name' => $move['name'], 'state' => $move['state']]];
            }
        }

        return [];
    }

    /** @param list<int> $ids */
    private function post(array $ids): bool
    {
        foreach ($ids as $id) {
            $this->moves[$id]['state'] = 'posted';
            $this->moves[$id]['name'] = sprintf('INV/%s/%05d', now()->year, $id);
        }

        return true;
    }
}
