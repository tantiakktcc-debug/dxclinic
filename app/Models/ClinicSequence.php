<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ClinicSequence extends Model
{
    use HasFactory;

    protected $table = 'clinic_sequences';

    protected $fillable = [
        'current_value',
        'prefix',
    ];

    /**
     * Concurrency-safe atomic generation of next Clinic ID (e.g. NX0002)
     */
    public static function nextClinicCode(string $prefix = 'NX'): string
    {
        return DB::transaction(function () use ($prefix) {
            $seq = static::where('prefix', $prefix)->lockForUpdate()->first();
            if (!$seq) {
                $seq = static::create([
                    'prefix' => $prefix,
                    'current_value' => 0,
                ]);
            }

            // Find highest existing numeric code in clinics table
            $latest = Clinic::where('clinic_code', 'LIKE', "{$prefix}%")
                ->orderBy('clinic_code', 'desc')
                ->first();

            if ($latest && preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $latest->clinic_code, $matches)) {
                $highestExisting = (int) $matches[1];
                if ($seq->current_value < $highestExisting) {
                    $seq->current_value = $highestExisting;
                }
            }

            $seq->current_value += 1;
            $seq->save();

            return sprintf('%s%04d', $prefix, $seq->current_value);
        });
    }
}
