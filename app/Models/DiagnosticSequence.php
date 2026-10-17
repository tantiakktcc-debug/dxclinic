<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class DiagnosticSequence extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'type',
        'sequence_date',
        'current_number',
    ];

    protected $casts = [
        'sequence_date' => 'date',
        'current_number' => 'integer',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    /**
     * Concurrency-safe, gapless, strictly monotonic sequence number allocation.
     */
    public static function nextSequence(int $clinicId, string $type, ?string $date = null): int
    {
        $date = $date ? \Carbon\Carbon::parse($date)->toDateString() : now()->toDateString();

        return DB::transaction(function () use ($clinicId, $type, $date) {
            $seq = static::where('clinic_id', $clinicId)
                ->where('type', $type)
                ->whereDate('sequence_date', $date)
                ->lockForUpdate()
                ->first();

            if (!$seq) {
                try {
                    $seq = static::create([
                        'clinic_id' => $clinicId,
                        'type' => $type,
                        'sequence_date' => $date,
                        'current_number' => 1,
                    ]);
                    return 1;
                } catch (\Throwable $e) {
                    $seq = static::where('clinic_id', $clinicId)
                        ->where('type', $type)
                        ->whereDate('sequence_date', $date)
                        ->lockForUpdate()
                        ->first();
                }
            }

            $next = ($seq ? $seq->current_number : 0) + 1;
            $seq?->update(['current_number' => $next]);
            return $next;
        });
    }

    /**
     * Generate Order Number: ORD-D-YYYYMMDD-XXXX
     */
    public static function nextOrderNumber(Clinic $clinic): string
    {
        $dateStr = now()->format('Ymd');
        $dateDb = now()->toDateString();
        $num = static::nextSequence($clinic->id, 'ORDER', $dateDb);
        return sprintf('ORD-D-%s-%04d', $dateStr, $num);
    }

    /**
     * Generate Specimen Identifier: SMP-{clinic_code}-YYYYMMDD-XXXX
     */
    public static function nextSpecimenIdentifier(Clinic $clinic): string
    {
        $clinicCode = $clinic->code ?? 'NX0001';
        $dateStr = now()->format('Ymd');
        $dateDb = now()->toDateString();
        $num = static::nextSequence($clinic->id, 'SPECIMEN', $dateDb);
        return sprintf('SMP-%s-%s-%04d', $clinicCode, $dateStr, $num);
    }

    /**
     * Generate Report Number: REP-D-YYYYMMDD-XXXX
     */
    public static function nextReportNumber(Clinic $clinic): string
    {
        $dateStr = now()->format('Ymd');
        $dateDb = now()->toDateString();
        $num = static::nextSequence($clinic->id, 'REPORT', $dateDb);
        return sprintf('REP-D-%s-%04d', $dateStr, $num);
    }
}
