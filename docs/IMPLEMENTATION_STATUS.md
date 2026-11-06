# آخرین بررسی: انتخاب SQLite یا فایل TXT

تاریخ: ۲۰۲۶-۱۱-۰۶. شناسه اجرا: `local-20261106-file-inbox`. [شواهد ماشینی جاری](../research/verification-file-inbox.json).

`FileInboxStore` به‌عنوان پیاده‌سازی جدید `InboxStore` اضافه شد. کاربر PHP مستقل می‌تواند آن را به‌جای `SqliteInboxStore` به `Poller` بدهد. در لاراول، `PAYAMBRIDGE_INBOX_DRIVER=file` یا `sqlite` ذخیره‌ساز را تعیین می‌کند؛ مقدار پیش‌فرض `sqlite` است. bindingهای `InboxStore` و `Poller` هنگام resolve شدن، فقط ذخیره‌ساز انتخاب‌شده را آماده می‌کنند. هیچ فایل inbox هنگام boot معمول برنامه ایجاد نمی‌شود.

فایل `.txt` وضعیت JSON با UTF-8 را نگه می‌دارد. فایل قفل مجزای `.lock`، تغییر نام اتمی فایل موقت، fsync محتوا و روی Unix پوشه والد، تراکنش منطقی رویداد/cursor، deduplication و fencing پیاده‌سازی شده‌اند. فایل خراب با خطا رد می‌شود؛ داده‌ها خودکار پاک یا به backend دیگر منتقل نمی‌شوند. این backend برای حجم کم و فایل‌سیستم محلی قابل‌اعتماد است و به PDO یا SQLite نیاز ندارد. محدودیت قابلیت دریافت پایدار همچنان فقط بله است.

آزمون‌های این تغییر:

- مجموعه کامل فعلی روی میزبان PHP `8.5.12`: **۱۲۳ تست، ۳۹۴ assertion**، بدون skip؛ `composer check` و سه اجرای متوالی `composer test:repeat` موفق.
- ۱۱ ترکیب دیگر روی PHP `8.3.36` و `8.4.27` در Docker و PHP `8.5.0` در Herd Lite، با Laravel 12/13 و dependencyهای latest/lowest: platform، Pint، PHPStan، PHPUnit و سه اجرای تکراری موفق. در مجموع این بررسی **۴۸ اجرای suite در ۱۲ ترکیب** دارد. نسخه `8.5.12` میزبان از lock توسعه قابل‌نصب روی PHP 8.3 استفاده می‌کند؛ جزئیات دقیق هر graph در JSON ثبت شده است.
- تست عدم امکان نوشتن در پوشه readonly در کانتینرهای با کاربر root به‌صورت مشخص skip شد؛ همان تست روی میزبان بدون دسترسی root اجرا و موفق شد. اجراهای root شامل ۱۲۳ تست، ۳۹۰ assertion و یک skip هستند. کد runtime/config/test در تمام checkoutها با hash یکسان تأیید شد.
- اجرای جداگانه **۱۳ تست FileInboxStore و ۱۱۴ assertion** با `php -n` و فقط افزونه‌های موردنیاز PHPUnit، بدون PDO و SQLite: موفق.
- ۸ تست ویژه Laravel با ۳۷ assertion: انتخاب driver، binding قابل جایگزینی، ایجاد تنبل، مجوزهای خصوصی، cache تنظیمات، اعتبارسنجی و restart: موفق.
- برنامه واقعی Laravel `12.71.0` و `13.40.0`: هر دو backend با config cache، ذخیره batch و سپس replay در پردازش جدا تست شدند؛ cursor حفظ و duplicate حذف شد و backend انتخاب‌نشده تغییر نکرد. بررسی‌های notification و queue worker قبلی نیز موفق ماندند. HTTP سرویس‌دهنده fake بود.
- آرشیو SDK شامل ۶۲ فایل و Laravel شامل ۱۴ فایل است؛ کنترل جداسازی و نبود vendor/cache/secret موفق بود. syntax، JSON و لینک‌های مستندات بررسی شدند.
- line coverage فعلی شامل SDK و Laravel: **۵۵۷ از ۶۳۴ statement، معادل ۸۷٫۸۵٪**؛ branch coverage اندازه‌گیری نشده است.

راهنمای استفاده و محدودیت‌های انتخاب فایل در [README فارسی](../README.md)، [README انگلیسی](../README.en.md) و [تنظیمات Laravel](laravel.md#durable-bale-inbox) آمده است. این تغییر، تأیید زنده سرویس‌دهنده‌ها، مهاجرت خودکار داده، dispatcher/outbox یا پشتیبانی تازه برای سایر پیام‌رسان‌ها اضافه نمی‌کند. گزارش انتشار اولیه زیر تاریخی است؛ معیار کد فعلی گزارش JSON پیوندشده بالاست.

# گزارش پیشین آماده‌سازی انتشار اولیه

تاریخ: ۲۰۲۶-۱۱-۰۶، ۱۴۰۵/۰۸/۱۵. شناسه اجرا: `local-20261106-release-readiness`.

این بخش تاریخی مربوط به کد پیش از افزودن ذخیره‌ساز TXT است؛ نتایج جاری در بخش بالای این سند آمده‌اند. نسخه عمومی جدیدی منتشر نشده است. [گزارش ماشینی](../research/verification.json) نسخه وابستگی‌ها، hash کد و تست و مسیر شواهد موقت را ثبت می‌کند. نتایج اولیه قبلی با این گزارش جایگزین شده‌اند.

## آنچه تکمیل و اصلاح شد

- README فارسی و انگلیسی با پوشش متناظر: پیش‌نیازها، نصب قبل و بعد از انتشار، API فعلی، مثال PHP، تنظیمات لاراول، چند اتصال و tenant، اعلان هم‌زمان و صفی، polling، SQLite، خطاها، تست، استقرار و رفع اشکال.
- جلوگیری از افشای نشانی حاوی توکن در خطای ساخت درخواست؛ خطا پیش از ارسال به‌صورت validation پاک‌سازی می‌شود.
- حفظ وضعیت ارسال نامعلوم هنگام خرابی دسترسی به status/body پاسخ؛ خطای بستن stream دیگر پاسخ معتبر یا خطای اصلی HTTP را نمی‌پوشاند.
- جلوگیری از پاک شدن cursor پایدار در batch بدون cursor جدید؛ تست replay، restart، تراکنش و lease همچنان برقرار است.
- رفع ناسازگاری تحلیل استاتیک با حداقل PHPStan پشتیبانی‌شده، بدون تغییر قرارداد عمومی درایورها.
- اصلاح آرشیوهای Composer برای حذف vendor، کش، فایل‌های محیطی و پوشه‌های توسعه؛ جداسازی توزیع SDK و Laravel به‌صورت خودکار بررسی می‌شود.
- lock توسعه با PHP 8.3 سازگار شده است. CI تنظیم platform محلی را حذف می‌کند تا وابستگی‌ها برای PHP واقعی هر job resolve شوند.
- GitHub Actions برای بررسی matrix و انتشار با تگ، subtree مستقل Laravel، پیش‌بررسی وابستگی پایدار، درخواست به‌روزرسانی Packagist و release notes؛ به‌علاوه راهنمای مشارکت، امنیت و قالب issue/PR.

## ماتریس واقعاً اجراشده

روی Linux، **هر ۱۲ ترکیب** PHP و Laravel و انتخاب وابستگی بررسی شد:

- **PHP 8.3.36** در Docker: Laravel 12 و 13، هرکدام با latest و prefer-lowest.
- **PHP 8.4.27** در Docker: Laravel 12 و 13، هرکدام با latest و prefer-lowest.
- **PHP 8.5.12** روی میزبان: Laravel 12 و 13، هرکدام با latest و prefer-lowest.

در هر ترکیب یک اجرای مجموعه کامل و سپس **سه اجرای تکراری متوالی** موفق بود: در مجموع **۴۸ اجرای کامل**، هرکدام **۱۰۲ تست و ۲۴۳ assertion**. hash فایل‌های runtime و تست در همه checkoutهای مستقل با کد فعلی یکسان است. تست‌های fake به‌عنوان تست زنده حساب نشده‌اند.

نسخه‌های resolveشده:

- latest لاراول ۱۲: Framework `12.71.0` و Testbench `10.12.0`.
- latest لاراول ۱۳: Framework `13.40.0` و Testbench `11.3.0`.
- prefer-lowest لاراول ۱۲: Framework `12.69.0` و Testbench `10.2.0`.
- prefer-lowest لاراول ۱۳: Framework `13.30.0` و Testbench `11.0.0`.
- PHPUnit در latest برابر `12.5.43` و در lowest برابر `12.5.8`؛ PHPStan به‌ترتیب `2.3.5` و `2.1.0`؛ Pint به‌ترتیب `1.32.1` و `1.20.0`.

محدودیت‌ها و سیاست امنیتی Composer باعث می‌شوند prefer-lowest الزاماً اولین نسخه تاریخی هر major را انتخاب نکند. latest نیز نسبت به metadata موجود در cache این اجراست؛ این گزارش ادعای دریافت تمام تغییرات رجیستری در لحظه ندارد.

بررسی platform روی runtime واقعی، Pint و PHPStan سطح ۶ برای ترکیب‌ها موفق بود. آرشیوها روی میزبان دارای `ext-zip` بررسی شدند؛ تصویرهای PHP آزمایش محلی افزونه zip نداشتند، بنابراین در چهار اجرای lowest روی Docker اجزای validation/lint/analyse/test جداگانه اجرا شدند. workflow این افزونه را نصب و `composer check` کامل را اجرا می‌کند. GitHub-hosted CI پس از push این تغییرات باید جداگانه اجرا شود.

## آزمایش پروژه واقعی Laravel

ابزار `tools/consumer-smoke.php` برنامه واقعی `laravel/laravel` نسخه ۱۲ و ۱۳ را در پوشه موقت ساخته و هر دو پکیج را با نسخه محلی `0.1.0` به‌صورت mirror و `--no-dev` نصب کرد. در Laravel `12.71.0` و `13.40.0` روی PHP `8.5.12` این موارد موفق بود:

- package discovery، انتشار config، config cache و route cache؛ resolve کردن facade و اتصال نام‌دار.
- migration روی SQLite و ارسال اعلان هم‌زمان از مسیر واقعی Laravel Notifications.
- ثبت اعلان دوم در صف واقعی database، بررسی نبود توکن در payload، و عدم ارسال پیش از worker.
- اجرای `artisan queue:work --once --tries=1` در **پردازش جدا**، با توکن آزمایشی متفاوت پس از enqueue؛ worker توکن جدید محیط خودش را خواند.
- بررسی payload دقیق HTTP، دو ارسال مورد انتظار، خالی شدن jobs و نبود failed job.
- نصب PHP مستقل بدون Illuminate، PHPUnit و Testbench و بررسی autoload و قرارداد SDK.

این آزمایش‌ها در این نوبت چند بار روی برنامه‌های تازه تکرار شدند. HTTP پیام‌رسان با transport اسکریپت‌شده جایگزین شد؛ framework، container، cache، migration، serialization و worker واقعی بودند. هیچ پیام واقعی به کاربر پیام‌رسان ارسال نشد. پوشه آخرین پروژه‌ها و logها در گزارش JSON ثبت شده‌اند؛ این مسیرها موقت‌اند و جزو وابستگی پکیج نیستند.

## بررسی انتشار و بسته‌بندی

- `composer validate --strict` برای هر دو manifest، syntax فایل‌های PHP، اعتبار JSON و لینک‌های محلی مستندات موفق بود.
- `composer test:archive` آرشیو SDK با ۶۰ فایل و Laravel با ۱۳ فایل را بررسی کرد؛ vendor، cache، فایل محیطی و bridge لاراول در آرشیو مستقل SDK وجود ندارند.
- Actionlint برای `ci.yml` و `release.yml` موفق بود.
- `php tools/release-preflight.php v0.1.0` از ابتدا با موفقیت هر دو پکیج را در نسخه `0.1.0` نصب و سازگاری وابستگی پایدار را تأیید کرد.
- در مخزن‌های موقت محلی، subtree اولیه Laravel، push اتمیک branch/tag، اجرای دوباره همان انتشار و fast-forward نسخه بعدی موفق بود.
- مخزن اصلی GitHub عمومی است. تغییرات این بررسی push نشده‌اند و workflow انتشار روی GitHub اجرا نشده است. برای ثبت اولیه Packagist و تنظیم repository/variable/secretها، [راهنمای انتشار](releasing.md) ترتیب دقیق bootstrap و تنظیمات را توضیح می‌دهد.

دسترسی این محیط به endpointهای Packagist timeout شد. resolve وابستگی‌ها با metadata موجود در cache و دریافت archiveهای لازم از GitHub انجام شد. بنابراین **نتیجه تازه و آنلاین `composer audit` در این اجرا به دست نیامد**؛ پیام نبود advisory در update آفلاین، جایگزین audit تازه نیست. CI آنلاین audit را پیش از انتشار الزام می‌کند. API واقعی Packagist بدون credential انتشار فراخوانی نشده است.

## پوشش و مرز قابلیت‌ها

line coverage با Xdebug برای sourceهای SDK و Laravel، شامل helper تست: **۳۸۸ از ۴۴۲ statement = ۸۷٫۷۸٪**. branch coverage اندازه‌گیری نشده است. این عدد اثبات نبود باگ یا تحقق هدف جداگانه پوشش core نیست.

inventory فعلی، فهرست جزئی ۷۰ عملیات است: **۷ عملیات پیاده‌سازی و با fixture مصنوعی تست شده، صفر عملیات تأییدشده با توکن معتبر زنده**. دامنه آماده فعلی عبارت است از هویت/متن/آپدیت بات بله و روبیکا، ارسال متن برنامه ایتا و جمع‌آوری پایدار batch بله. APIهای بیشتر، رسانه، webhook، dispatcher/outbox، حساب شخصی، Mini App، سفیر و ایتایار هنوز قابلیت آماده این نسخه نیستند؛ جزئیات در [پرسش‌ها و کارهای باز](../research/open-questions.md) است.

آزمون زنده سرویس‌دهنده به credential و مقصد آزمایشی مجاز نیاز دارد. Windows/macOS، branch coverage، mutation و soak بیست‌وچهارساعته هنوز اجرا نشده‌اند. انتشار اولیه نباید به‌عنوان تکمیل کل نقشه راه یا تأیید production تمام سرویس‌دهنده‌ها معرفی شود.

## بازتولید

برای checkout معمولی، PHP 8.3–8.5، Composer 2 و افزونه‌های توسعه `mbstring`، `pdo_sqlite` و `zip` لازم‌اند:

```sh
composer install
composer check
composer test:repeat
composer test:consumer
composer validate --strict packages/laravel/composer.json
composer audit
php tools/release-preflight.php v0.1.0
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text
```

برای matrix در checkout مستقل، ابتدا `composer config --unset platform.php` را اجرا کنید؛ برای Laravel 12 از Testbench `^10.0` و برای Laravel 13 از `^11.0` استفاده کنید و سپس `composer update` یا `composer update --prefer-lowest --prefer-stable` را روی PHP هدف اجرا کنید. workflow همین انتخاب را انجام می‌دهد. در محیط بدون دسترسی به رجیستری، `COMPOSER_DISABLE_NETWORK=1` فقط با cache کامل قابل استفاده است و audit تازه فراهم نمی‌کند.
