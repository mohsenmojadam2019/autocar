<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Operations\BackupService;
use App\Services\Operations\ProviderHealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class OperationsHealthController extends Controller
{
    /** Shows provider, backup and queue operational state. */
    public function index(): View
    {
        return view('admin.operations.health', [
            'health' => DB::table('provider_health_checks')->latest('checked_at')->limit(100)->get(),
            'backups' => DB::table('backup_runs')->latest('id')->limit(30)->get(),
            'queuedJobs' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0,
            'failedJobs' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
        ]);
    }

    /** Creates a credential-free logical database backup. */
    public function backup(BackupService $backups): RedirectResponse
    {
        $backups->create();

        return back()->with('success', 'Backup منطقی ایجاد و Checksum ثبت شد.');
    }

    /** Runs safe provider checks. */
    public function health(ProviderHealthService $providers): RedirectResponse
    {
        $providers->checkAll();

        return back()->with('success', 'سلامت Providerها بررسی شد.');
    }
}
