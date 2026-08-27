# وضعیت اجرای AutoCar

آخرین به‌روزرسانی: 2026-08-27

## نقطه ادامه

- **تسک جاری:** BE-010 — تکمیل سرویس‌ها و مدیریت دسته‌بندی نامحدود
- **آخرین پیاده‌سازی GitHub:** BE-004 — هسته دامنه کاتالوگ و خودرو
- **شاخه:** main
- **مرجع برنامه:** docs/IMPLEMENTATION_PLAN.md

## تکمیل‌شده

- [x] TASK-00 معماری، README و برنامه شماره‌دار — commits: d769109, 866da26, b5f0412
- [x] BE-001 پیاده‌سازی اسکلت رسمی Laravel 13، PHP 8.4، Bootstrap RTL و Local Storage — commit: d0735eb
- [x] BE-004 ساختار اولیه Domain/Application، Enumها و Money Value Object — commit: ae15c4e
- [x] BE-010 اسکیمای دسته‌بندی نامحدود و ویژگی‌ها — migration موجود در ae15c4e
- [x] BE-011 اسکیمای برند قطعه — migration موجود در ae15c4e
- [x] BE-012 اسکیمای ویژگی و Specification — migration موجود در ae15c4e
- [x] BE-013 اسکیمای محصول، Variant، Media و روابط — migration موجود در ae15c4e
- [x] BE-017 اسکیمای بانک خودرو و گاراژ — migration موجود در ae15c4e
- [x] BE-018 اسکیمای موتور سازگاری محصول/خودرو — migration موجود در ae15c4e

## منتظر اعتبارسنجی لوکال

موارد زیر پیاده‌سازی شده‌اند اما طبق تصمیم کارفرما نصب و اجرای Runtime بعداً در لوکال انجام می‌شود:

- [ ] composer install
- [ ] php artisan migrate:fresh --seed
- [ ] php artisan test
- [ ] npm install && npm run build
- [ ] بررسی Boot روی PHP 8.4

## در حال انجام

- [ ] BE-010/BE-013 تکمیل Actionها، Validation، Policy، Seed و تست‌های کاتالوگ
- [ ] BE-018 تکمیل Fitment Resolver و تست قواعد سازگاری

## قاعده ادامه کار

در شروع هر نوبت ابتدا این فایل و سپس IMPLEMENTATION_PLAN خوانده شود. هر Commit باید شماره تسک داشته باشد. تست‌های Runtime تا زمان نصب لوکال در همین بخش باز می‌مانند و نباید به‌عنوان اجراشده گزارش شوند.
