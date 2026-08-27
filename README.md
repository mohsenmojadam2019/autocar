# AutoCar — فروشگاه تخصصی قطعات و لوازم خودرو

AutoCar یک فروشگاه حرفه‌ای RTL برای فروش قطعات یدکی، لوازم مصرفی، بدنه، برق، تجهیزات و کلیه اقلام مرتبط با خودرو است. هسته محصول بر «انتخاب خودرو و نمایش فقط قطعات سازگار» بنا شده است.

> وضعیت فعلی: معماری و برنامه اجرا تأیید شده؛ توسعه مرحله‌ای از صفر روی شاخه `main`.

## پشته فنی قطعی

- Laravel 13 / PHP 8.4
- Blade Components
- Bootstrap 5 RTL
- CSS اختصاصی با Design Tokens
- JavaScript خالص با ES Modules
- MySQL 8
- Redis برای Cache، Session، Queue و Rate Limit
- Laravel Horizon و Scheduler
- Meilisearch (اختیاری و قابل خاموش‌کردن)
- Laravel Filesystem روی Local/Public
- Vite
- Pest/PHPUnit
- Docker، Nginx و Supervisor

## موارد عمداً استفاده‌نشده

- Tailwind CSS
- Alpine.js
- Livewire
- S3 و MinIO

هیچ وابستگی UI یا ذخیره‌سازی نباید این چهار مورد را به پروژه اضافه کند.

## اصول معماری

پروژه به شکل Modular Monolith پیاده‌سازی می‌شود. دامنه‌ها مرز روشن دارند، ولی برای جلوگیری از پیچیدگی غیرضروری، سرویس جداگانه و Microservice ساخته نمی‌شود.

```text
app/
├── Domain/
│   ├── Catalog
│   ├── Vehicle
│   ├── Inventory
│   ├── Cart
│   ├── Order
│   ├── Payment
│   ├── Shipping
│   ├── Customer
│   ├── Promotion
│   ├── Notification
│   ├── Content
│   ├── Accounting
│   └── Support
├── Application/
│   ├── Actions
│   ├── DTOs
│   ├── Queries
│   └── Services
├── Infrastructure/
│   ├── Payment
│   ├── SMS
│   ├── Shipping
│   ├── Search
│   └── Storage
└── Http/
    ├── Storefront
    ├── Customer
    └── Admin
```

### قواعد کدنویسی

1. Controller فقط ورودی را اعتبارسنجی و Action مناسب را اجرا می‌کند.
2. منطق تجاری داخل Action/Service دامنه قرار می‌گیرد.
3. Queryهای پیچیده داخل Query Object یا Scope نام‌دار نوشته می‌شوند.
4. برای وضعیت‌ها از PHP Enum استفاده می‌شود.
5. عملیات سفارش، پرداخت و موجودی Transactional و Idempotent است.
6. تمام تغییرات حساس در Audit Log ثبت می‌شوند.
7. هر متد عمومی باید DocBlock کوتاه درباره هدف، ورودی، خروجی و خطاهای مهم داشته باشد.
8. نام متد باید رفتار آن را توضیح دهد؛ از متدهای طولانی و کلاس‌های چندمنظوره جلوگیری می‌شود.
9. تیک هر تسک فقط بعد از Migration، تست و بررسی دستی ثبت می‌شود.
10. مسیرهای Storefront، Customer و Admin کاملاً جدا و دارای Middleware مستقل هستند.

## جریان اصلی خرید

1. کاربر خودرو را با برند، مدل، سال، تیپ و موتور انتخاب می‌کند.
2. خودرو در «گاراژ من» ذخیره می‌شود.
3. جست‌وجو و صفحات دسته فقط محصولات سازگار را اولویت می‌دهند.
4. صفحه محصول وضعیت سازگاری را شفاف نمایش می‌دهد.
5. موجودی هنگام Checkout برای مدت محدود رزرو می‌شود.
6. تراکنش درگاه ایجاد و پس از Verify معتبر می‌شود.
7. سفارش، فاکتور، انبار، پیامک و ارسال با Event/Listener هماهنگ می‌شوند.

## وضعیت سفارش

```text
draft → awaiting_payment → paid → reviewing → sourcing
→ ready_to_ship → shipped → delivered
→ cancelled / returned / refunded
```

## قرارداد درگاه‌ها

تمام درگاه‌ها از یک Contract مشترک استفاده می‌کنند:

- Zarinpal
- IDPay
- NextPay
- Zibal
- Pay.ir
- Behpardakht Mellat
- Saman Kish
- Parsian
- Pasargad
- Card to Card
- Cash on Delivery

متدهای الزامی Driver: `request`، `callback`، `verify`، `inquiry` و در صورت پشتیبانی `refund`.

## قرارداد پیامک

Providerهای هدف: Kavenegar، SMS.ir، IPPanel، Ghasedak، FarazSMS، Melipayamak و Driver سفارشی.

ارسال OTP و پیام تراکنشی از صف جداگانه و کمپین تبلیغاتی با رضایت کاربر، لیست سیاه، محدودیت نرخ، توقف کمپین و گزارش Delivery اجرا می‌شود.

## طراحی رابط

مرجع ظاهری، دو Mockup تأییدشده AutoCar است:

- فروشگاه سفید و روشن، قرمز/گرافیتی، مگامنوی چندستونه و انتخابگر خودرو
- پنل ادمین روشن با Sidebar سمت راست، KPI، نمودار، سفارش‌ها، موجودی کم و وضعیت درگاه‌ها

Design Tokens اصلی:

```css
--ac-primary: #ef2028;
--ac-primary-dark: #c9141b;
--ac-text: #202124;
--ac-muted: #697077;
--ac-bg: #f7f8fa;
--ac-surface: #ffffff;
--ac-border: #e7e9ee;
--ac-radius: 12px;
```

## مستندات مرجع

- [برنامه کامل پیاده‌سازی](docs/IMPLEMENTATION_PLAN.md)
- [وضعیت و نقطه ادامه](docs/PROGRESS.md)

## سیاست Git و تحویل

- شاخه اصلی: `main`
- هر تسک یک Commit مشخص و قابل بازگشت دارد.
- پیام Commit شامل شماره تسک است.
- فایل `docs/PROGRESS.md` پس از اتمام هر تسک به‌روزرسانی می‌شود.
- Secrets و اطلاعات واقعی درگاه‌ها هرگز Commit نمی‌شوند.
