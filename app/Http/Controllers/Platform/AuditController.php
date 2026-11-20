<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class AuditController extends Controller
{
    private function filtered(Request $request): Builder
    {
        $input = $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        return AuditLog::query()->with(['actor:id,name,email', 'tenant:id,name,slug'])
            ->when($input['action'] ?? null, fn ($q, $v) => $q->where('action', 'like', '%'.$v.'%'))
            ->when($input['tenant_id'] ?? null, fn ($q, $v) => $q->where('tenant_id', $v))
            ->when($input['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($input['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
    }

    public function index(Request $request): View
    {
        $logs = $this->filtered($request)->orderByDesc('id')->paginate(25)->withQueryString();
        return view('platform.audit.index', compact('logs'));
    }

    public function export(Request $request): StreamedResponse
    {
        $records = $this->filtered($request)->orderByDesc('id')->limit(5000)->get();

        return response()->streamDownload(function () use ($records): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Timestamp', 'Actor', 'Business', 'Action', 'Subject', 'IP', 'Metadata']);
            foreach ($records as $log) {
                $values = [
                    $log->created_at?->toDateTimeString() ?? '',
                    $log->actor?->email ?? 'System',
                    $log->tenant?->name ?? 'Platform',
                    $log->action,
                    ($log->subject_type ?? '').' #'.($log->subject_id ?? ''),
                    $log->ip_address ?? '',
                    json_encode($log->metadata ?? [], JSON_UNESCAPED_UNICODE),
                ];
                // Avoid spreadsheet formula execution in exported user-entered fields.
                fputcsv($output, array_map(function ($value): string {
                    $str = (string) $value;
                    return preg_match('/^[\s]*[=+\-@]/u', $str) ? "'".$str : $str;
                }, $values));
            }
            fclose($output);
        }, 'pos-platform-audit-'.now()->format('Y-m-d').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
