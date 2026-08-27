<?php

use App\Domain\Inventory\Services\InventoryReservationService;
use App\Domain\Marketing\Services\CampaignService;
use App\Domain\Payment\Services\PaymentReconciliationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('autocar:campaigns', function (CampaignService $campaigns): void {
    $this->info('Sent: '.$campaigns->processDue());
})->purpose('Process due SMS campaigns respecting rate and consent.');

Artisan::command('autocar:release-reservations', function (InventoryReservationService $reservations): void {
    $this->info('Released: '.$reservations->releaseExpired());
})->purpose('Release expired unpaid inventory reservations.');

Schedule::command('autocar:campaigns')->everyMinute()->withoutOverlapping();
Schedule::command('autocar:release-reservations')->everyFiveMinutes()->withoutOverlapping();
