# PayamBridge

SDK در حال توسعه برای اتصال پروژه‌های PHP و Laravel به پیام‌رسان‌های ایرانی، بر اساس [سند محصول](.docs/IRAN_MESSENGER_PHP_AGENT_SPEC_FA.md).

**وضعیت: برش اولیه توسعه، پیش از انتشار.** نام‌های Composer هنوز منتشر نشده‌اند. هفت عملیات پیاده‌سازی شده‌اند: `getMe`، `sendMessage` و `getUpdates` برای بات بله و روبیکا، و `sendMessage` برای برنامه ایتا. تأیید زنده با credential معتبر هنوز انجام نشده است. این نسخه همه قابلیت‌های محصول یا تضمین بدون باگ ندارد.

هسته مستقل از لاراول است. bridge لاراول در `packages/laravel` پکیج جداگانه دارد. هدف نسخه‌ها PHP 8.3–8.5 و Laravel 12/13 است؛ وضعیت آزمون واقعی و باقی کارها را در [گزارش پیاده‌سازی](docs/IMPLEMENTATION_STATUS.md) ببینید. [English documentation](README.en.md).

## شروع توسعه

```sh
composer install
composer check
```

مخزن شامل PHPUnit، PHPStan، Pint، تست قرارداد، آزمون SQLite و Testbench است. برای اجرای inbox اختیاری، `pdo_sqlite` لازم است. مصرف‌کننده معمول بات نیاز به Laravel، Node، Redis یا دیتابیس ندارد.

## استفاده در PHP

در این checkout، پس از `composer install`:

```php
use PayamBridge\Contracts\Provider;
use PayamBridge\Contracts\Surface;
use PayamBridge\Data\ChatRef;
use PayamBridge\Data\ConnectionId;
use PayamBridge\Drivers\Bale\BaleBot;
use PayamBridge\Http\HttpTransport;
use PayamBridge\Security\Credential;

require 'vendor/autoload.php';

$id = new ConnectionId(Provider::Bale, Surface::Bot, 'support', 'shop-a');
$bot = new BaleBot($id, new Credential(getenv('BALE_BOT_TOKEN')), HttpTransport::createDefault());
$receipt = $bot->sendText(new ChatRef($id, '123'), 'سلام از پیام‌بریج');
echo $receipt->message->id;
```

این مثال پیام واقعی ارسال می‌کند؛ فقط مقصد آزمایشی مجاز خودتان را تنظیم کنید. نمونه قابل اجرا با کنترل متغیرهای محیطی در [examples/send.php](examples/send.php) است. برای نصب نسخه محلی از پروژه دیگر، راهنمای [نصب مصرف‌کننده](docs/installation.md) را بخوانید.

شناسه‌ها در API عمومی رشته هستند و با provider، نوع هویت، connection و tenant scope می‌شوند. ارسال با `ChatRef` متعلق به scope دیگر پیش از HTTP رد می‌شود. این کنترل جای مجوزدهی tenant در برنامه میزبان را نمی‌گیرد.

## دریافت پایدار اولیه

```php
use PayamBridge\Runtime\Poller;
use PayamBridge\Runtime\SqliteInboxStore;

$inbox = new SqliteInboxStore(new PDO('sqlite:/private/storage/payambridge.sqlite'));
$inbox->initialize(); // صریح؛ هنگام نصب پکیج خودکار اجرا نمی‌شود.
$newEvents = (new Poller($inbox))->once($bot);
```

این کد یک batch را جمع‌آوری می‌کند. eventها و cursor در یک تراکنش ذخیره می‌شوند؛ پردازش تجاری، ارسال پاسخ، dispatcher و outbox هنوز پیاده‌سازی نشده‌اند. دیتابیس را خارج از web root با دسترسی محدود نگه دارید؛ raw event حاوی اطلاعات حساس است و سیاست retention بر عهده مصرف‌کننده است.

Polling پایدار فعلاً برای بله فعال است. روبیکا `getUpdates` سطح پایین دارد و cursor را دقیقاً به صورت رشته حفظ می‌کند، اما Poller پایدار آن تا اثبات هویت یکتای event فعال نیست. متد جعلی `getUpdates` برای ایتا ارائه نشده است.

## Laravel

پس از نصب دو پکیج محلی، ServiceProvider و Facade با auto-discovery ثبت می‌شوند:

```sh
php artisan vendor:publish --tag=payambridge-config
php artisan payam:capabilities bale
```

```php
use PayamBridge\Contracts\SendsText;
use PayamBridge\Data\ChatRef;
use PayamBridge\Laravel\ConnectionManager;

$connection = app(ConnectionManager::class)->connection('bale');
if ($connection instanceof SendsText) {
    $connection->sendText(new ChatRef($connection->id(), '123'), 'سفارش شما ثبت شد');
}
```

Config شامل **نام** متغیرهای محیطی مانند `PAYAMBRIDGE_BALE_TOKEN` است. با `config:cache`، secret را در محیط واقعی PHP-FPM/worker تنظیم کنید؛ فایل `.env` در این حالت لزوماً خوانده نمی‌شود. برای secret store، قرارداد `CredentialResolver` را override کنید. راهنمای [Laravel و صف](docs/laravel.md) شامل Notification، cache و رفتار worker است.

## خطا و ارسال مبهم

هر درخواست فعلاً فقط یک بار اجرا می‌شود. خطای شبکه، پاسخ خراب یا خطای 5xx پس از ارسال ممکن است به معنی ارسال موفق پیام و از دست رفتن پاسخ باشد. در این حالت `AmbiguousDeliveryException` با وضعیت `delivery_unknown` رخ می‌دهد. ارسال دوباره خودکار انجام نمی‌شود. در job لاراول هم این خطا را کورکورانه retry نکنید؛ ابتدا نتیجه را تطبیق دهید یا برای بررسی نگه دارید.

credential و payload در logهای SDK و متن exception قرار نمی‌گیرند؛ raw response و fake request حساس‌اند. `Credential` قابل serialize نیست و باید در worker دوباره resolve شود. برای custom PSR-18 client، TLS، timeout و عدم دنبال‌کردن redirect را رعایت کنید.

## دامنه باقی‌مانده

ایتایار کانال، سفیر بله، رسانه، webhook، Mini App، حساب شخصی هر سه پیام‌رسان، session رمزنگاری‌شده، پاسخ‌گوی AI و ناشر محصول همچنان در دامنه اصلی هستند و هنوز پیاده‌سازی نشده‌اند. نسخه فعلی، ارسال برنامه ایتا را جایگزین ایتایار یا حساب شخصی معرفی نمی‌کند.

[API اولیه](docs/api.md)، [منابع نسخه‌دار](research/sources.json)، [inventory](research/api-inventory.json)، [اختلاف مستندات](research/doc-conflicts.md) و [کارهای باز](research/open-questions.md) مرجع وضعیت دقیق هستند. `composer inventory` نسبت پیاده‌سازی، تست قرارداد و تست زنده را جداگانه برای inventory فعلی محاسبه می‌کند؛ inventory کل محصول هنوز کامل نیست.
