<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PlatformInvoiceSequence extends Model
{
    use HasFactory;

    protected $table = 'platform_invoice_sequences';

    protected $fillable = [
        'prefix',
        'year',
        'current_value',
    ];

    /**
     * Atomically generate the next globally unique invoice number.
     * Format: INV-YYYY-XXXXX (e.g. INV-2026-00001)
     */
    public static function nextInvoiceNumber(?Carbon $date = null, string $prefix = 'INV'): string
    {
        $year = (int) ($date ? $date->year : date('Y'));

        return DB::transaction(function () use ($year, $prefix) {
            $sequence = self::lockForUpdate()
                ->where('prefix', $prefix)
                ->where('year', $year)
                ->first();

            if (!$sequence) {
                $sequence = self::create([
                    'prefix' => $prefix,
                    'year' => $year,
                    'current_value' => 0,
                ]);
                // Re-lock
                $sequence = self::lockForUpdate()->find($sequence->id);
            }

            $sequence->current_value += 1;
            $sequence->save();

            return sprintf('%s-%04d-%05d', $prefix, $year, $sequence->current_value);
        });
    }
}
