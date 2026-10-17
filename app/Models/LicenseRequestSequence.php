<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LicenseRequestSequence extends Model
{
    protected $table = 'license_request_sequences';

    protected $fillable = [
        'current_value',
        'prefix',
    ];

    /**
     * Concurrency-safe atomic generation of next License Request Number
     * e.g. LR-000001, LR-000002, ...
     */
    public static function nextRequestNumber(string $prefix = 'LR'): string
    {
        return DB::transaction(function () use ($prefix) {
            $seq = static::where('prefix', $prefix)->lockForUpdate()->first();
            if (!$seq) {
                $seq = static::create([
                    'prefix' => $prefix,
                    'current_value' => 0,
                ]);
            }

            $seq->current_value += 1;
            $seq->save();

            return sprintf('%s-%06d', $prefix, $seq->current_value);
        });
    }
}
