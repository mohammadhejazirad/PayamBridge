# وضعیت پیاده‌سازی اولیه

تاریخ: ۱۴۰۵/۰۸/۱۵، ۲۰۲۶-۱۱-۰۶. شناسه اجرای محلی: `local-20261106-initial`.

این تحویل، شروع اجرایی سند محصول است. پکیج منتشر نشده و آماده production اعلام نمی‌شود. گزارش ماشینی و hash فایل‌های کد/تست در `research/verification.json` ثبت شده است.

## کد تحویل‌شده

- `composer.json` و `src/`: هسته مستقل PHP، قراردادهای قابلیت، شناسه‌های scoped، مدل‌های نتیجه و event، transport مبتنی بر PSR-18/7/17/3، implementation پیش‌فرض Guzzle و خطاهای طبقه‌بندی‌شده.
- `src/Drivers/`: سه driver مستقل BaleBot، RubikaBot و EitaaApp با هفت عملیات واقعی در سطح کد. فایل‌های provider جدا هستند و قراردادهایشان یکسان فرض نشده‌اند.
- `src/Runtime/`: جمع‌آوری یک batch بله، inbox در SQLite، deduplication شناسه update، تراکنش مشترک cursor/event و lease با fencing. این قسمت هنوز dispatcher، outbox یا پردازش تجاری ندارد.
- `packages/laravel/`: پکیج مستقل با auto-discovery، config شامل reference متغیر محیطی، اتصال‌های نام‌دار، binding قابل override، Facade اختیاری، Notification channel و دستور محلی `payam:capabilities`.
- `src/Testing/` و `tests/`: fake مستقل برای هر instance، fixtureهای مصنوعی مبتنی بر منابع، unit/contract/integration/Testbench. هیچ fake به صورت پیش‌فرض success نمی‌سازد.
- `research/`: منابع و hash، inventory عملیات، قابلیت‌ها، اختلاف مستندات، مجوز وابستگی‌ها، پرسش‌های باز و شواهد آزمون.
- `docs/`، `examples/`، README فارسی/انگلیسی، CHANGELOG و CI هدف.

## آزمون‌های واقعاً اجراشده

روی Linux با **PHP 8.5.12**:

- کل suite در **Laravel 13.40.0 / Testbench 11.3.0**: **۹۷ تست و ۲۳۰ assertion**؛ سه اجرای متوالی روی کد یکسان موفق شدند. هر اجرا شامل restart با دیتابیس پایدار، replay، قطع شبیه‌سازی‌شده ارتباط، خرابی نوشتن cursor و expiry/takeover قفل است.
- همان suite در یک کپی مستقل با **Laravel 12.71.0 / Testbench 10**: **۹۷ تست و ۲۳۰ assertion** موفق.
- نصب مصرف‌کننده PHP تمیز با `--no-dev` و کپی پکیج: موفق؛ بدون Illuminate و بدون PHPUnit/Testbench. Autoload و قرارداد getMe با fake بررسی شد.
- نصب مصرف‌کننده Laravel 12 و 13 تمیز با `--no-dev`: موفق؛ package discovery، config cache، route cache، reference credential، resolve اتصال و دستور capabilities بررسی شد. این بررسی‌ها HTTP واقعی پیام‌رسان اجرا نکردند.
- Notification بدون تغییر مدل User، صف sync و صف database با اجرای worker و serialization واقعی: موفق. توکن در payload صف قرار نگرفت.
- `composer validate --strict` برای هر دو manifest، Pint، PHPStan سطح ۶، syntax تعداد ۵۵ فایل PHP و اعتبار JSONها: موفق.
- `composer audit`: برای dependencyهای resolveشده در lock فعلی هیچ advisory امنیتی گزارش نشد؛ این نتیجه تضمین امنیت یا نبود باگ نیست.

گزارش line coverage فعلی برای sourceهای SDK و Laravel، شامل testing helper: **۳۷۹ از ۴۳۳ statement، معادل ۸۷٫۵۳٪**. برای تمام SDK به‌جز helper تست **۸۹٫۵۳٪**، و زیرمجموعه core بدون driver/runtime/helper/Laravel **۸۶٫۱۸٪** است. branch coverage اندازه‌گیری نشده است. هدف پیشنهادی انتشار core یعنی ۹۰٪ line و ۸۰٪ branch هنوز محقق یا تأیید نشده؛ پوشش generated یا mock به‌عنوان پوشش live محاسبه نشده است.

PHP 8.3/8.4، matrix کامل CI، prefer-lowest، Windows/macOS، mutation و soak بیست‌وچهارساعته هنوز اجرا نشده‌اند. فایل workflow آماده است؛ وجود آن معادل pass شدن CI نیست.

## پوشش API و مرز اطمینان

Inventory فعلی شامل **۷۰ عملیات** است: ۵۰ بات بله، ۱۹ متد مستقل روبیکا و یک ارسال برنامه ایتا. فهرست کل محصول، از جمله surfaceهای دیگر، هنوز کامل نیست. وضعیت‌های باقی‌مانده از مخرج حذف نشده‌اند.

- پیاده‌سازی: **۷/۷۰ = ۱۰٪**.
- تست قرارداد با fixture مصنوعی: **۷/۷۰ = ۱۰٪**.
- تأیید موفق زنده با credential معتبر: **۰/۷۰ = ۰٪**.

این درصدها فقط به inventory فعلی مربوط‌اند. schema مدل‌های nested و تمام حالت‌های هر API هنوز کامل نیستند. endpoint، limit و متد مجهول به‌عنوان قابلیت آماده معرفی نشده‌اند. در روبیکا `offset_id` از جدول متد انتخاب شده و cursor رشته‌ای حفظ می‌شود؛ تعارض با `start_id` و شکل getMe همچنان نیازمند تست زنده است.

تنها probe شبکه‌ای API در این مرحله، getMe فقط‌خواندنی روبیکا با توکن عمداً نامعتبر بود که `INVALID_INPUT` برگرداند. این مشاهده، تست موفق عملیات یا حساب واقعی نیست. هیچ پیامی به مقصد واقعی ارسال نشد.

## بازبینی جدا پس از پیاده‌سازی

در بازخوانی مسیرهای دریافت، ارسال، secret و نصب، این موارد اصلاح و با تست مرتبط بررسی شدند:

- تمایز `{}` از `[]` برای جلوگیری از پذیرش object نامعتبر به‌عنوان batch خالی.
- جلوگیری از عقب رفتن cursor با دریافت batch قدیمی یا خارج از ترتیب.
- رد receipt بله با chat نامرتبط، با حفظ امکان resolve شدن alias کانال.
- حفظ وضعیت مبهم ارسال هنگام قطع پاسخ، خرابی body و شکست logger؛ بدون retry خودکار.
- عدم نمایش credential در `var_dump` و `var_export` و جلوگیری از serialize شدن آن.
- حفظ fencing پس از release و رد writer قدیمی حتی اگر دیرتر lease را آزاد کند.
- محدود کردن تشخیص event بله به فیلدهای مستند؛ فیلد ناشناخته همچنان در raw حفظ می‌شود.

این بازبینی، بازبینی امنیتی مستقل یا پذیرش نهایی انتشار نیست. نیاز به بازبین مستقل در gate انتشار باقی است.

## کار بعدی قابل اجرا

ابتدا schema عملیات pending و مدل‌های nested را تکمیل و wrapperهای رسمی باقی‌مانده را با contract مستقل اضافه کنید. سپس مدیریت webhook و ingress پایدار، dispatcher/outbox و runtime قابل توقف را بسازید. پژوهش حساب شخصی و Mini App باید مستقل و طبق مراحل سند ادامه یابد؛ این نیازها حذف نشده‌اند.

برای بستن live acceptance، credentialهای بات/برنامه در secret store یا محیط واقعی پردازش، و مقصدهای آزمایشی مجاز لازم است. برای حساب شخصی نیز حساب تحت اختیار مالک و ورود تعاملی لازم خواهد بود. نبود این موارد مانع unit/integration نیست، ولی تأیید live را متوقف می‌کند. جزئیات همه gapها در `research/open-questions.md` آمده است.

## بازتولید

```sh
composer install
composer check
composer validate --strict packages/laravel/composer.json
composer audit
php tools/consumer-smoke.php php
php tools/consumer-smoke.php 12
php tools/consumer-smoke.php 13
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text
```

اسکریپت consumer پروژه‌های موقت را در مسیر temp سیستم نگه می‌دارد تا قابل بازبینی باشند. به Composer/network برای دریافت dependency نیاز دارد؛ از credential واقعی یا API ارسال استفاده نمی‌کند. آزمون suite کامل Laravel 12 نیازمند resolve کردن `orchestra/testbench:^10.0` در checkout جداست؛ checkout اصلی با Testbench 11 و Laravel 13 قفل شده است.
