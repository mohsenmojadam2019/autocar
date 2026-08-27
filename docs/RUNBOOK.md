# AutoCar Production Runbook

## Deploy
1. Build immutable image from `main`.
2. Back up database and `storage/app/public`.
3. Run `php artisan migrate --force` on one release instance.
4. Run `php artisan optimize` and `php artisan queue:restart`.
5. Switch traffic only after `/up` returns HTTP 200.

## Rollback
- Switch traffic to the previous image first.
- Do not blindly roll back destructive migrations. Restore the pre-deploy backup when schema rollback is unsafe.
- Run `php artisan queue:restart` after application rollback.

## Required monitoring
- HTTP availability and latency.
- Queue failed jobs and queue lag.
- MySQL disk/connection pressure.
- Redis memory.
- Payment verification failures by gateway.
- SMS provider failure rate.
- Low-stock count and checkout exceptions.

## Backup policy
Daily database + public-media backups, 14-day local retention, and an encrypted off-host copy managed by infrastructure. S3/MinIO is intentionally not required by the application.
