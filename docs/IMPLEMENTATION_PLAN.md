# برنامه کامل پیاده‌سازی AutoCar

قاعده: `[x]` فقط زمانی ثبت می‌شود که کد، Migration، تست و بررسی دستی همان تسک کامل شده باشد. شماره‌ها پایدارند و تغییر نمی‌کنند.

## A — بک‌اند و زیرساخت

- [ ] **BE-001 — ایجاد Laravel 13:** ساخت پروژه، PHP 8.4، env نمونه، timezone، locale فارسی و صحت Boot.
- [ ] **BE-002 — استاندارد پروژه:** Pint، Pest، Larastan، EditorConfig، GitHub Actions و قواعد Commit.
- [ ] **BE-003 — محیط اجرا:** Docker، MySQL، Redis، Nginx، Queue Worker، Scheduler و Health Check.
- [ ] **BE-004 — معماری دامنه:** Domain/Application/Infrastructure، Base Action، DTO و Result استاندارد.
- [ ] **BE-005 — کاربران و احراز هویت:** ثبت‌نام، ورود رمز/OTP، بازیابی، Session، دستگاه‌ها و 2FA ادمین.
- [ ] **BE-006 — نقش و دسترسی:** نقش‌های ریزدانه، Policy، Middleware و دسترسی ستون‌ها/عملیات حساس.
- [ ] **BE-007 — Audit و امنیت:** Activity Log، تغییرات حساس، Rate Limit، CSRF، Security Headers و IP Restriction.
- [ ] **BE-008 — تنظیمات مرکزی:** تنظیمات گروه‌بندی‌شده، Cache، رمزنگاری Secretها و Feature Flag.
- [ ] **BE-009 — رسانه محلی:** Upload امن روی local/public، Variant تصویر، WebP، اعتبارسنجی و Cleanup.
- [ ] **BE-010 — دسته‌بندی نامحدود:** درخت نامحدود، مسیر، Slug، ترتیب، SEO، فعال/غیرفعال و Drag-order API.
- [ ] **BE-011 — برندها و سازندگان:** برند قطعه، کشور، لوگو، نمایندگی و SEO.
- [ ] **BE-012 — ویژگی و Specification:** گروه ویژگی، نوع داده، واحد، Filterable و Template دسته.
- [ ] **BE-013 — محصولات:** SKU، OEM، کد فنی، نوع اصالت، گارانتی، رسانه، وضعیت و SEO.
- [ ] **BE-014 — تنوع محصول:** Variant، Attribute Combination، بارکد، قیمت و موجودی مستقل.
- [ ] **BE-015 — کپی و عملیات گروهی محصول:** Clone عمیق، Import/Export و Bulk Update با گزارش خطا.
- [ ] **BE-016 — روابط محصول:** مرتبط، مکمل، جایگزین، Upsell و Bundle.
- [ ] **BE-017 — بانک خودرو:** سازنده، برند، مدل، نسل، سال، تیپ، موتور، گیربکس و بازار.
- [ ] **BE-018 — موتور سازگاری:** اتصال محصول/Variant به خودرو، شرط سال/تیپ/موتور و نتیجه compatible/conditional/incompatible.
- [ ] **BE-019 — گاراژ مشتری:** چند خودروی ذخیره‌شده، خودروی فعال و اتصال مهمان بعد از Login.
- [ ] **BE-020 — جست‌وجو:** جست‌وجوی فارسی نام/SKU/OEM، Suggestion، مترادف، فیلتر و Fallback دیتابیس.
- [ ] **BE-021 — تأمین‌کنندگان:** مشخصات، قرارداد، لیست قیمت، Lead Time و وضعیت.
- [ ] **BE-022 — چند انبار:** قفسه، Stock Ledger، موجود/رزرو/خراب، انتقال، اصلاح و شمارش.
- [ ] **BE-023 — خرید و تأمین:** Purchase Order، رسید انبار، بهای خرید و تأمین کسری.
- [ ] **BE-024 — قیمت‌گذاری:** خرید/فروش/همکار، تاریخچه قیمت، قیمت زمان‌دار و قواعد گردکردن.
- [ ] **BE-025 — سبد خرید:** مهمان/عضو، Merge، اعتبارسنجی سازگاری، قیمت و ذخیره سبد رهاشده.
- [ ] **BE-026 — تخفیف و کوپن:** ثابت/درصدی، شرط‌ها، سقف، مصرف، ترکیب‌پذیری، BOGO و ارسال رایگان.
- [ ] **BE-027 — Checkout:** آدرس، ارسال، مالیات، تخفیف، رزرو موجودی و Snapshot تغییرناپذیر اقلام.
- [ ] **BE-028 — سفارش:** ماشین وضعیت، سفارش تلفنی، تاریخچه، یادداشت داخلی و عملیات گروهی.
- [ ] **BE-029 — پرداخت:** Contract درگاه، تراکنش Idempotent، Callback، Verify، Reconcile و Refund.
- [ ] **BE-030 — زرین‌پال:** Driver کامل Sandbox/Production، خطاها، لاگ امن و تست.
- [ ] **BE-031 — سایر درگاه‌ها:** IDPay، Zibal، NextPay، Pay.ir و Adapter درگاه‌های بانکی.
- [ ] **BE-032 — کیف پول:** Ledger تغییرناپذیر، اعتبار/بدهکار، Cashback و پرداخت ترکیبی.
- [ ] **BE-033 — ارسال:** روش/منطقه/وزن، پست/تیپاکس/پیک/تحویل حضوری، Tracking و Label.
- [ ] **BE-034 — فاکتور:** رسمی/غیررسمی، شماره یکتا، PDF، چاپ A4/حرارتی، پیش‌فاکتور و Packing Slip.
- [ ] **BE-035 — مرجوعی و بازپرداخت:** RMA جزئی/کامل، بررسی، بازگشت موجودی و Refund.
- [ ] **BE-036 — پیامک:** Contract Provider، کاوه‌نگار و سایر Driverها، Template، Queue و Delivery Log.
- [ ] **BE-037 — کمپین تبلیغاتی:** Segment، Consent، Blacklist، زمان‌بندی، توقف، Rate Limit و گزارش.
- [ ] **BE-038 — اعلان‌ها:** ایمیل/پیامک/اعلان پنل، Preference و Eventهای سفارش.
- [ ] **BE-039 — مشتری و CRM:** پروفایل 360، گروه، برچسب، یادداشت، ارزش مشتری و خروجی.
- [ ] **BE-040 — محتوا:** صفحات، بلاگ، FAQ، بنر، اسلایدر و Revision.
- [ ] **BE-041 — مگامنو:** ساختار دیتابیس، ستون، تصویر، لینک، زمان‌بندی و Cache.
- [ ] **BE-042 — نظر و پرسش:** امتیاز، خرید تأییدشده، Moderation، پاسخ و گزارش تخلف.
- [ ] **BE-043 — پشتیبانی:** تیکت، دپارتمان، اولویت، فایل، SLA و درخواست یافتن قطعه.
- [ ] **BE-044 — فروش عمده:** قیمت همکار، حداقل تعداد، استعلام و پیش‌فاکتور.
- [ ] **BE-045 — گزارش‌ها:** فروش، سود، سفارش، محصول، خودرو، انبار، کمپین، مالیات و Export.
- [ ] **BE-046 — داشبورد ادمین:** KPI، سری زمانی، وضعیت سفارش، موجودی کم و سلامت Providerها.
- [ ] **BE-047 — SEO:** Metadata، Canonical، Schema، Sitemap، Robots و Redirect.
- [ ] **BE-048 — API داخلی:** Endpointهای انتخاب خودرو، جست‌وجو، سبد و پنل با Resource استاندارد.
- [ ] **BE-049 — تست جامع:** Unit، Feature، Integration درگاه/SMS، Race Condition و Permission Matrix.
- [ ] **BE-050 — عملیات و استقرار:** Backup، Monitoring، Queue Dashboard، Zero-downtime و Runbook.

## B — فروشگاه و پنل مشتری

- [ ] **FE-001 — Design System:** Bootstrap 5 RTL، Tokens، فونت فارسی، Grid، Icon و Componentهای Blade.
- [ ] **FE-002 — Layout فروشگاه:** نوار اعتماد، Header، جست‌وجو، حساب، علاقه‌مندی و سبد.
- [ ] **FE-003 — مگامنو دسکتاپ/موبایل:** چندستونه، Keyboard، Touch، Focus و Lazy Image.
- [ ] **FE-004 — صفحه اصلی:** Hero، انتخاب خودرو، دسته‌ها، پیشنهاد ویژه، محصولات و برندها مطابق Mockup.
- [ ] **FE-005 — انتخاب خودرو و گاراژ:** Wizard برند/مدل/سال/تیپ، خودرو فعال و حذف/ویرایش.
- [ ] **FE-006 — جست‌وجو:** Suggestion، تاریخچه، SKU/OEM، حالت بدون نتیجه و درخواست قطعه.
- [ ] **FE-007 — صفحه لیست:** Breadcrumb، فیلتر، Sort، Pagination، View mode و سازگاری.
- [ ] **FE-008 — صفحه محصول:** Gallery، قیمت، موجودی، اصالت، سازگاری، مشخصات، نظر و CTA.
- [ ] **FE-009 — کارت محصول:** Badge، تخفیف، Rating، Wishlist، Compare و Quick Add.
- [ ] **FE-010 — مقایسه و علاقه‌مندی:** تفاوت ویژگی‌ها، انتخاب خودرو و اشتراک لینک.
- [ ] **FE-011 — سبد خرید:** تغییر تعداد، کوپن، Cross-sell، ذخیره و هشدار ناسازگاری.
- [ ] **FE-012 — Checkout:** ورود، آدرس، ارسال، فاکتور، پرداخت و مرور نهایی.
- [ ] **FE-013 — نتیجه پرداخت و پیگیری:** موفق/ناموفق، Retry، فاکتور و Tracking بدون ورود.
- [ ] **FE-014 — احراز هویت:** موبایل/رمز/OTP، بازیابی و مدیریت Session.
- [ ] **FE-015 — پنل مشتری:** داشبورد، سفارش، فاکتور، کیف پول، خودرو، آدرس و تنظیم اعلان.
- [ ] **FE-016 — مرجوعی و تیکت:** فرم مرحله‌ای، فایل، Timeline و پاسخ.
- [ ] **FE-017 — محتوا:** بلاگ، مقاله، FAQ، درباره، تماس، قوانین و Landing خودرو.
- [ ] **FE-018 — Responsive/Accessibility:** موبایل، Tablet، RTL، WCAG، Keyboard و Screen Reader.
- [ ] **FE-019 — Performance:** Vite Split، Critical CSS، Lazy Load، Skeleton و Web Vitals.
- [ ] **FE-020 — SEO UI:** Heading، Breadcrumb، Alt، Pagination و Structured Data view.

## C — پنل مدیریت

- [ ] **AD-001 — Shell پنل:** Sidebar راست، Topbar، Breadcrumb، اعلان و Profile مطابق Mockup.
- [ ] **AD-002 — داشبورد:** KPI، نمودار ۳۰ روز، Donut سفارش، آخرین سفارش و موجودی کم.
- [ ] **AD-003 — Data Table مشترک:** Server-side، فیلتر، Sort، ستون، Bulk Action و Export.
- [ ] **AD-004 — مدیریت محصول:** Wizard، Media، Variant، Specification، SEO، Clone و Preview.
- [ ] **AD-005 — دسته/برند/مگامنو:** درخت، ترتیب، آیکون، بنر و پیش‌نمایش.
- [ ] **AD-006 — خودرو و سازگاری:** بانک خودرو، ماتریس Fitment، Bulk Assign و Import.
- [ ] **AD-007 — سفارش:** Kanban/List، جزئیات، Timeline، وضعیت، چاپ و سفارش تلفنی.
- [ ] **AD-008 — انبار و تأمین:** موجودی، Ledger، انتقال، شمارش، PO و هشدار.
- [ ] **AD-009 — پرداخت و مالی:** تراکنش، Reconcile، Refund، کیف پول و وضعیت Gateway.
- [ ] **AD-010 — مشتری و CRM:** پروفایل، سفارش‌ها، خودروها، Segment و یادداشت.
- [ ] **AD-011 — تخفیف و کمپین:** Builder قواعد، Preview مخاطب، زمان‌بندی و گزارش.
- [ ] **AD-012 — پیامک:** Provider، Template، ارسال تست، کمپین و Delivery Report.
- [ ] **AD-013 — ارسال و مرجوعی:** نرخ‌ها، Shipment، Tracking، RMA و Label.
- [ ] **AD-014 — محتوا و SEO:** Page/Blog editor، Banner، Redirect و Sitemap.
- [ ] **AD-015 — تنظیمات:** General، فروش، مالیات، درگاه، SMS، ارسال، فاکتور و Maintenance.
- [ ] **AD-016 — کاربران و امنیت:** Role matrix، 2FA، Session، IP و Audit viewer.
- [ ] **AD-017 — گزارش‌ها:** Chart/Table، مقایسه بازه، Drill-down، Excel و PDF.
- [ ] **AD-018 — حالات UI:** Loading، Empty، Error، Offline، Toast، Confirm و Unsaved changes.
- [ ] **AD-019 — Responsive/Accessibility:** Desktop-first، Tablet، Keyboard و Contrast.
- [ ] **AD-020 — تست بصری:** تطبیق Pixel-level با Mockupهای تأییدشده و Regression Screenshot.

## معیار پایان پروژه

- تمام موارد بالا `[x]` شده باشند.
- تست خودکار، Migration تازه، Seed و Build بدون خطا اجرا شوند.
- فرآیند واقعی انتخاب خودرو تا تحویل سفارش تست شود.
- پرداخت و Callback تکراری باعث سفارش یا برداشت موجودی تکراری نشود.
- Permission Matrix، Backup/Restore و Runbook استقرار تأیید شوند.
