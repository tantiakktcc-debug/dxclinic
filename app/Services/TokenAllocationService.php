<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\TokenSequence;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TokenAllocationService
{
    /**
     * Allocate the next date-and-session-scoped numeric token for the clinic (Revision 32)
     * Atomically increments current_token inside a database transaction with row-level locking.
     */
    public function allocateToken(Clinic $clinic, Carbon|string $date, string $sessionType = 'MORNING'): array
    {
        $dateStr = is_string($date) ? Carbon::parse($date)->toDateString() : $date->toDateString();
        $session = strtoupper(trim($sessionType ?: 'MORNING'));
        $padLength = (int) config('dxclinic.token_pad_length', 3);

        return DB::transaction(function () use ($clinic, $dateStr, $session, $padLength) {
            // Find or initialize sequence row for this clinic, date, and session
            try {
                $sequence = TokenSequence::where('clinic_id', $clinic->id)
                    ->whereDate('token_date', $dateStr)
                    ->where('session_type', $session)
                    ->lockForUpdate()
                    ->first();

                if (!$sequence) {
                    $sequence = TokenSequence::create([
                        'clinic_id' => $clinic->id,
                        'token_date' => $dateStr,
                        'session_type' => $session,
                        'current_token' => 1,
                    ]);
                    $tokenNumber = 1;
                } else {
                    $tokenNumber = $sequence->current_token + 1;
                    $sequence->update(['current_token' => $tokenNumber]);
                }
            } catch (\Illuminate\Database\QueryException $e) {
                // In case of initial concurrent insert collision, re-fetch with exclusive lock
                $sequence = TokenSequence::where('clinic_id', $clinic->id)
                    ->whereDate('token_date', $dateStr)
                    ->where('session_type', $session)
                    ->lockForUpdate()
                    ->firstOrFail();

                $tokenNumber = $sequence->current_token + 1;
                $sequence->update(['current_token' => $tokenNumber]);
            }

            $formatted = str_pad((string) $tokenNumber, $padLength, '0', STR_PAD_LEFT);

            return [
                'token_number' => $tokenNumber,
                'formatted_token' => $formatted,
            ];
        });
    }
}
