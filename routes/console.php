<?php

use App\Domain\Cart\Services\AbandonedCartService;
use App\Domain\Inventory\Services\InventoryReservationService;
use App\Domain\Marketing\Services\CampaignService;
use App\Services\Operations\BackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('autocar:campaigns', function (CampaignService $campaigns): void {
    $this->info('Sent: '.$campaigns->processDue());
})->purpose('Process due SMS campaigns respecting rate, suppression and consent.');

Artisan::command('autocar:release-reservations', function (InventoryReservationService $reservations): void {
    $this->info('Released: '.$reservations->releaseExpired());
})->purpose('Release expired unpaid inventory reservations.');

Artisan::command('autocar:abandoned-carts', function (AbandonedCartService $service): void {
    $this->info('Discovered: '.$service->discover());
    $this->info('Queued: '.$service->sendDue());
})->purpose('Discover inactive carts and queue eligible recovery messages.');

Artisan::command('autocar:backup', function (BackupService $backups): void {
    $this->info('Backup: '.$backups->create());
})->purpose('Create a compressed logical database backup in private storage.');

Artisan::command('autocar:restore {path}', function (string $path, BackupService $backups): void {
    if (app()->environment('production') && ! $this->confirm('This replaces application data from the selected backup. Continue?')) {
        $this->warn('Restore cancelled.');
        return;
    }

    $backups->restore($path);
    $this->info('Restore completed.');
})->purpose('Restore an explicitly selected trusted AutoCar logical backup.');

Schedule::command('autocar:campaigns')->everyMinute()->withoutOverlapping();
Schedule::command('autocar:release-reservations')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('autocar:abandoned-carts')->hourly()->withoutOverlapping();
Schedule::command('autocar:backup')->dailyAt('02:30')->withoutOverlapping();
