<?php

namespace App\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function log(
        Authenticatable $user,
        string $action,
        string $module,
        ?string $recordType = null,
        int|string|null $recordId = null,
        array $details = [],
    ): void {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        DB::table('audit_logs')->insert([
            'user_id' => $user->getAuthIdentifier(),
            'action' => $action,
            'module' => $module,
            'record_type' => $recordType,
            'record_id' => $recordId,
            'ip_address' => request()->ip(),
            'details' => $details === [] ? null : json_encode($details),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
