<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait HasAuditLog
{
    public static function bootHasAuditLog()
    {
        static::created(function ($model) {
            $model->logAuditAction('created', null, $model->getAttributes());
        });

        static::updated(function ($model) {
            $old = array_intersect_key($model->getOriginal(), $model->getDirty());
            $new = $model->getDirty();
            $model->logAuditAction('updated', $old, $new);
        });

        static::deleted(function ($model) {
            $model->logAuditAction('deleted', $model->getAttributes(), null);
        });
    }

    public function logAuditAction(string $action, ?array $oldValues = null, ?array $newValues = null, array $metadata = [])
    {
        // Don't log if running from console and not testing
        if (app()->runningInConsole() && !app()->runningUnitTests()) {
            return;
        }

        $userId = Auth::id();
        $clinicId = method_exists($this, 'getClinicId') ? $this->getClinicId() : ($this->clinic_id ?? null);
        
        // This will insert the audit log directly without an Eloquent event loop issue.
        // Assuming AuditLog is defined later.
        \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
            'clinic_id' => $clinicId,
            'user_id' => $userId,
            'action' => $action,
            'auditable_type' => get_class($this),
            'auditable_id' => $this->getKey(),
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'metadata' => empty($metadata) ? null : json_encode($metadata),
            'created_at' => now(),
        ]);
    }
}
