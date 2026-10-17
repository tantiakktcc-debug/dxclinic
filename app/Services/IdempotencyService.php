<?php

namespace App\Services;

use App\Models\IdempotencyRecord;
use Closure;
use Illuminate\Support\Facades\DB;

class IdempotencyService
{
    /**
     * Execute an action with an idempotency guarantee (Section 78, 88, 100)
     */
    public function execute(string $key, Closure $callback, ?string $endpoint = null)
    {
        return DB::transaction(function () use ($key, $callback, $endpoint) {
            $record = IdempotencyRecord::where('key', $key)->lockForUpdate()->first();

            if ($record) {
                return json_decode($record->response_body, true);
            }

            $result = $callback();

            IdempotencyRecord::create([
                'key' => $key,
                'endpoint' => $endpoint,
                'response_body' => json_encode($result),
                'status_code' => 200,
            ]);

            return $result;
        });
    }
}
