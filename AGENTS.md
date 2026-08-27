# AutoCar repository instructions

- Runtime target: PHP 8.4 and Laravel 13.
- UI stack: Blade, Bootstrap 5 RTL, project CSS and vanilla JavaScript.
- Never add Tailwind CSS, Alpine.js, Livewire, S3 or MinIO.
- Keep domain logic outside controllers and Blade templates.
- Public methods require concise PHPDoc describing purpose, input, output and important failures.
- Financial, payment, order and inventory writes must be transactional and idempotent.
- Do not mark an implementation task complete until its code and tests exist. Runtime execution may be recorded as pending until the local PHP environment is installed.
- Read `docs/IMPLEMENTATION_PLAN.md` and `docs/PROGRESS.md` before continuing work.
