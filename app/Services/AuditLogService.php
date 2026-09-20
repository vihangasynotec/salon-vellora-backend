<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    public static function log(string $action, string $module, ?int $recordId = null, ?array $oldData = null, ?array $newData = null, ?string $userName = null): void
    {
        try {
            $user = Auth::user();
            AuditLog::create([
                'user_id' => $user ? $user->id : null,
                'user_name' => $userName ?: ($user ? $user->name : 'System'),
                'action' => $action,
                'module' => $module,
                'record_id' => $recordId,
                'old_data' => $oldData,
                'new_data' => $newData,
                'ip_address' => Request::ip(),
                'user_agent' => substr(Request::userAgent() ?? '', 0, 255),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Never allow audit logging failure to crash main operation
            report($e);
        }
    }
}
