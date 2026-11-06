# PayamBridge

این پروژه با استفاده از عامل‌های هوش مصنوعی (AI Agents) توسعه داده شده است.

## فهرست

- [قابلیت‌های فعلی](#قابلیتهای-فعلی)
- [پیش‌نیاز و نصب](#پیشنیاز-و-نصب)
- [ارسال با PHP مستقل](#ارسال-با-php-مستقل)
- [دریافت بروزرسانی‌ها](#دریافت-بروزرسانیها)
- [صندوق ورودی پایدار بله](#صندوق-ورودی-پایدار-بله)
- [پیکربندی Laravel](#پیکربندی-laravel)
- [اعلان‌ها و صف](#اعلانها-و-صف)
- [خطا و امنیت](#خطا-و-امنیت)
- [استقرار و رفع اشکال](#استقرار-و-رفع-اشکال)
- [مشارکت و انتشار](#مشارکت-و-انتشار)

SDK مبتنی بر قابلیت برای اتصال برنامه‌های PHP به پیام‌رسان‌های ایرانی، با bridge مستقل Laravel. هسته به Laravel، Redis، Node یا پایگاه داده نیاز ندارد. [English](README.en.md).

**وضعیت:** نسخه اولیه در حال آماده‌سازی است. انتشار عمومی و آزمون موفق با credential معتبر تا زمانی که در [گزارش پیاده‌سازی](docs/IMPLEMENTATION_STATUS.md) ثبت نشده باشند، تأییدشده محسوب نمی‌شوند. constraint هر دو پکیج PHP **8.3، 8.4، 8.5** است؛ bridge از **Laravel 12 و 13** پشتیبانی می‌کند. نتایج واقعی آزمون ماتریس را در گزارش و CI ببینید.

## قابلیت‌های فعلی

| اتصال | ارسال متن | دریافت بروزرسانی | هویت بات | inbox پایدار |
| --- | --- | --- | --- | --- |
| بله Bot | بله | بله | بله | SQLite |
| روبیکا Bot | بله | بله، cursor رشته‌ای | بله | خیر |
| ایتا App | بله، به کاربر مجاز | خیر | خیر | خیر |

این جدول عملیات **پیاده‌سازی‌شده** را نشان می‌دهد. ایتا App با ایتایار کانال یا حساب شخصی فرق دارد. رسانه، webhook، Mini App، سفیر بله، ایتایار، حساب شخصی، outbox/dispatcher و پاسخ‌گوی AI هنوز ارائه نشده‌اند. جزئیات متدها در [API](docs/api.md) آمده است.

## پیش‌نیاز و نصب

هر دو پکیج به **PHP 8.3، 8.4 یا 8.5**، افزونه‌های `ext-json` و `ext-mbstring` و **Composer 2** نیاز دارند. bridge از **Laravel 12 و 13** پشتیبانی می‌کند. `ext-pdo_sqlite` فقط برای صندوق ورودی اختیاری SQLite لازم است. ارسال پیام پایگاه داده نمی‌خواهد.

پس از انتشار نسخه 0.1.0 **هر دو پکیج** و ثبت‌شدن در Packagist:

~~~sh
composer require payambridge/sdk:^0.1
# در برنامه Laravel به‌جای آن bridge را نصب کنید؛ SDK را وابسته دارد:
composer require payambridge/laravel:^0.1
php artisan vendor:publish --tag=payambridge-config
~~~

پیش از انتشار، در `composer.json` **برنامه مصرف‌کننده** این path repositoryها را با مسیر مطلق checkout خود ثبت کنید:

~~~json
{
  "repositories": [
    {"type": "path", "url": "/absolute/path/PayamBridge", "options": {"versions": {"payambridge/sdk": "0.1.0"}, "symlink": false}},
    {"type": "path", "url": "/absolute/path/PayamBridge/packages/laravel", "options": {"versions": {"payambridge/laravel": "0.1.0"}, "symlink": false}}
  ],
  "require": {"payambridge/laravel": "^0.1"}
}
~~~

سپس `composer require payambridge/laravel:^0.1 --with-all-dependencies` را اجرا کنید. برای PHP مستقل فقط path مربوط به SDK را نگه دارید و `payambridge/sdk:^0.1` را require کنید. فایل lock برنامه مصرف‌کننده را commit کنید. bridge بسته‌ای مستقل است و داخل آرشیو SDK قرار ندارد. جزئیات بسته‌بندی در [راهنمای نصب](docs/installation.md) آمده است.

برای توسعه همین checkout:

~~~sh
composer install
composer check
~~~

این فرمان‌ها Composer validation، Pint، PHPStan، PHPUnit، inventory و بررسی آرشیوهای انتشار را اجرا می‌کنند. اجرای تست‌های توسعه به `pdo_sqlite` و `zip` نیاز دارد؛ برای ارسال پیام در برنامه مصرف‌کننده لازم نیستند.

credential متناسب با نوع اتصال را طبق راهنمای سرویس‌دهنده دریافت کنید: [بات بله](https://docs.bale.ai/)، [بات روبیکا](https://rubika.ir/botapi) و [ارسال برنامه ایتا](https://developer.eitaa.com/docs/Develop/SendMassage/). توکن Bot و App قابل جایگزینی با یکدیگر نیستند؛ SDK حساب نمی‌سازد و مجوز دسترسی به گیرنده ایجاد نمی‌کند.

## ارسال با PHP مستقل

~~~php
<?php

require 'vendor/autoload.php';

use PayamBridge\ConnectionFactory;
use PayamBridge\Contracts\Provider;
use PayamBridge\Contracts\SendsText;
use PayamBridge\Contracts\Surface;
use PayamBridge\Data\ChatRef;
use PayamBridge\Data\ConnectionId;
use PayamBridge\Http\HttpTransport;
use PayamBridge\Security\Credential;

$id = new ConnectionId(Provider::Bale, Surface::Bot, 'support', 'shop-a');
$token = getenv('BALE_BOT_TOKEN');
if (! is_string($token) || $token === '') {
    throw new RuntimeException('BALE_BOT_TOKEN is required.');
}
$connection = (new ConnectionFactory(HttpTransport::createDefault()))
    ->create($id, new Credential($token));
if ($connection instanceof SendsText) {
    $receipt = $connection->sendText(new ChatRef($id, '123456'), 'سلام از پیام‌بریج');
    echo $receipt->message?->id ?? 'sent';
}
~~~

این کد پیام واقعی می‌فرستد؛ credential و مقصد مجاز خودتان را بگذارید. [نمونه قابل اجرا](examples/send.php) متغیرهای محیطی را بررسی می‌کند. ConnectionId شامل provider، نوع هویت، نام اتصال و tenant است. ChatRef با scope اشتباه پیش از HTTP رد می‌شود؛ مجوز دسترسی tenant به اتصال را برنامه میزبان باید اعمال کند. MessageReceipt در ایتا App شناسه پیام ندارد و مقدار message آن null است.

برای درایورهای دیگر از `Provider::Rubika`/`Surface::Bot` یا `Provider::Eitaa`/`Surface::App` استفاده کنید. ایتا App به شناسه چت عدد صحیح مثبت و مجوز گیرنده نیاز دارد. نام اتصال و tenant باید ۱ تا ۱۲۸ نویسه ASCII از حروف، رقم، نقطه، زیرخط یا خط تیره باشند. قراردادهای مشترک `SendsText::sendText(ChatRef, string): MessageReceipt`، `GetsIdentity::getMe(): BotIdentity` و `ReceivesUpdates::fetchUpdates(?string): UpdateBatch` هستند. هر اتصال `id()` و `capabilities()` دارد؛ برای عملیات اختیاری از `capabilities()->supports(Capability::SendsText)` یا `require(...)` استفاده کنید.

## دریافت بروزرسانی‌ها

BaleBot::getUpdates(offset, limit, timeout) و RubikaBot::getUpdates(offsetId, limit) دریافت سطح پایین را فراهم می‌کنند. ReceivesUpdates::fetchUpdates(cursor) برای polling دوره‌ای است. cursor روبیکا را دقیقاً به صورت رشته نگه دارید؛ eventهای آن شناسه پایدار مستندشده ندارند.

در خواندن مستقیم، `UpdateBatch::nextCursor` را فقط پس از رسیدگی ایمن به `UpdateBatch::events` ذخیره کنید. هر `IncomingEvent` نوع رویداد، chat/message/text اختیاری و داده خام سرویس‌دهنده دارد؛ فیلدهای ناشناخته در `raw` باقی می‌مانند:

~~~php
use PayamBridge\Contracts\ReceivesUpdates;

$savedCursor = null; // اگر cursor قبلی ثبت شده، آن را بخوانید.
if ($connection instanceof ReceivesUpdates) {
    $batch = $connection->fetchUpdates($savedCursor); // در اولین بار null
    foreach ($batch->events as $event) {
        // پیش از ذخیره cursor بعدی، رویداد را پردازش یا ذخیره کنید.
    }
    $savedCursor = $batch->nextCursor;
}
~~~

cursor بعدی بله برابر بیشترین update ID به‌علاوه یک است. offset بله بروزرسانی‌های قبلی را در سرویس‌دهنده تأیید می‌کند. برای long polling بله، timeout خواندن و کل HTTP را بیشتر از timeout درخواست‌شده از سرویس‌دهنده بگذارید.

## صندوق ورودی پایدار بله

برای جمع‌آوری پایدار **بله**:

~~~php
use PayamBridge\Runtime\Poller;
use PayamBridge\Runtime\SqliteInboxStore;

$inbox = new SqliteInboxStore(new PDO('sqlite:/private/storage/payambridge.sqlite'));
$inbox->initialize(); // ایجاد صریح schema
$count = (new Poller($inbox))->once($connection);
$rows = $inbox->events($id, 100);
~~~

در این مثال connection باید بله باشد. هر once یک batch و cursor را تحت lease و در یک تراکنش ذخیره می‌کند و شناسه‌های تکراری را حذف می‌کند. Poller برای روبیکا UnsupportedCapabilityException می‌دهد. inbox هیچ handler، ack پردازش تجاری، daemon یا outbox ندارد. SQLite و raw eventها را بیرون web root نگه دارید و retention تعریف کنید. offset پیشرفته بله دریافت‌های قبلی را در سرور تأیید می‌کند؛ ابتدا ذخیره پایدار انجام دهید.

`once()` را با scheduler یا worker تحت نظارت برنامه اجرا کنید. رقابت بر سر lease خطای `LeaseUnavailableException` و انقضا یا جایگزینی آن `LeaseLostException` می‌دهد و cursor را ثبت نمی‌کند. شمارش بازگشتی تعداد رویدادهای تازه‌ثبت‌شده است؛ `events()` سطرهای JSON خام می‌دهد، نه صف پردازش تجاری. پیگیری سطرهای پردازش‌شده، پشتیبان‌گیری و نگهداری داده با برنامه میزبان است. دریافت هم‌زمان با webhook و polling به برنامه گذار مشخص نیاز دارد.

## پیکربندی Laravel

bridge در packages/laravel پکیجی جداگانه با ServiceProvider خودکار است:

~~~sh
php artisan vendor:publish --tag=payambridge-config
php artisan payam:capabilities bale
~~~

فرمان capabilities فقط قابلیت محلی را چاپ می‌کند و درخواست شبکه ندارد، اما برای ساخت اتصال به credential پیکربندی‌شده نیاز دارد. config نام متغیر محیطی مثل PAYAMBRIDGE_BALE_TOKEN را نگه می‌دارد، نه secret را. با config:cache، secret را در محیط واقعی PHP-FPM/worker بگذارید یا CredentialResolver را برای secret store خود bind کنید.

فایل منتشرشده `config/payambridge.php` اتصال پیش‌فرض `bale` و اتصال‌های `bale` Bot، `rubika` Bot و `eitaa-app` App را تعریف می‌کند. هر مورد `provider`، `surface`، `tenant` و `token_env` دارد. نام متغیرهای پیش‌فرض دیگر `PAYAMBRIDGE_RUBIKA_TOKEN` و `PAYAMBRIDGE_EITAA_APP_TOKEN` هستند. timeout اتصال/خواندن/کل به‌ترتیب ۵/۳۰/۳۵ ثانیه است. نمونه tenant مستقل:

~~~php
// در config/payambridge.php برنامه مصرف‌کننده:
'connections' => [
    'shop-a-bale' => [
        'provider' => 'bale',
        'surface' => 'bot',
        'tenant' => 'shop-a',
        'token_env' => 'SHOP_A_BALE_TOKEN',
    ],
],
~~~

~~~php
use PayamBridge\Contracts\SendsText;
use PayamBridge\Data\ChatRef;
use PayamBridge\Laravel\ConnectionManager;

$connection = app(ConnectionManager::class)->connection('bale');
if ($connection instanceof SendsText) {
    $connection->sendText(new ChatRef($connection->id(), '123456'), 'سفارش ثبت شد');
}
~~~

PayamBridgeChannel، TextMessage و NotificationRoute برای Notification موجودند. نمونه کامل صف، route و transaction در [راهنمای Laravel](docs/laravel.md) است. در job اتصال را دوباره resolve کنید؛ Credential قابل serialize نیست. bridge route، migration یا daemon نصب نمی‌کند.

manager در هر فراخوانی اتصال immutable تازه و credential فعلی را resolve می‌کند. مجوز دسترسی tenant به نام اتصال را اعمال نمی‌کند؛ این کار با برنامه میزبان است. bridge فرمان polling یا handler webhook نیز نصب نمی‌کند.

## اعلان‌ها و صف

در Notification متد `toPayamBridge` باید `TextMessage` برگرداند و کانال `PayamBridgeChannel` را به کار ببرد:

~~~php
use Illuminate\Notifications\Notification;
use PayamBridge\Laravel\Notifications\PayamBridgeChannel;
use PayamBridge\Laravel\Notifications\TextMessage;

final class OrderRegistered extends Notification
{
    public function via(object $notifiable): array
    {
        return [PayamBridgeChannel::class];
    }

    public function toPayamBridge(object $notifiable): TextMessage
    {
        return new TextMessage('سفارش شما ثبت شد');
    }
}
~~~

~~~php
use Illuminate\Support\Facades\Notification;
use PayamBridge\Laravel\Notifications\NotificationRoute;

Notification::route('payambridge', new NotificationRoute('bale', '123456'))
    ->notify(new OrderRegistered());
~~~

مدل notifiable عادی هم می‌تواند از `routeNotificationForPayambridge` یک `NotificationRoute` برگرداند یا برای صرف‌نظر از ارسال `null` بدهد. نام اتصال و chat ID در route نباید خالی باشد؛ `TextMessage` باید متن UTF-8 غیرخالی داشته باشد. کانال برای ارسال `MessageReceipt` و برای route خالی `null` برمی‌گرداند. برای اعلان صفی، `ShouldQueue` و trait `Queueable` لاراول را به‌کار ببرید. اتصال را در worker resolve کنید، در صورت وابستگی به تراکنش پایگاه داده پس از commit dispatch کنید، و workerهای بلندمدت را بعد از تغییر secret/config از نو راه‌اندازی کنید. retry خودکار job پس از ارسال نامعلوم می‌تواند پیام تکراری بسازد؛ سیاست خطا و تطبیق را در برنامه تعیین کنید.

نمونه صفی همان اعلان:

~~~php
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use PayamBridge\Laravel\Notifications\NotificationRoute;
use PayamBridge\Laravel\Notifications\PayamBridgeChannel;
use PayamBridge\Laravel\Notifications\TextMessage;

final class QueuedOrderRegistered extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array { return [PayamBridgeChannel::class]; }
    public function toPayamBridge(object $notifiable): TextMessage
    {
        return new TextMessage('سفارش شما ثبت شد');
    }
}

NotificationFacade::route('payambridge', new NotificationRoute('bale', '123456'))
    ->notify((new QueuedOrderRegistered())->afterCommit());
~~~

## خطا و امنیت

SdkException دارای kind، context و در صورت وجود providerCode و retryAfter است. kindهای آن transport، timeout، provider، authorization، permission، validation، rate_limit، protocol_mismatch و delivery_unknown هستند. ورودی نامعتبر محلی معمولاً InvalidArgumentException و قابلیت ناموجود UnsupportedCapabilityException می‌دهد.

قطع شبکه، 5xx یا پاسخ موفق خراب در **ارسال** می‌تواند یعنی پیام ثبت شده اما پاسخ گم شده است. AmbiguousDeliveryException را برای تطبیق یا بررسی دستی نگه دارید؛ SDK retry خودکار ندارد و retry کورکورانه job ممکن است پیام تکراری بسازد. MessageReceipt::raw، IncomingEvent::raw و fake requestها حساس‌اند. SDK credential یا payload را در log و متن exception چاپ نمی‌کند. transport پیش‌فرض TLS را بررسی و redirect را رد می‌کند؛ PSR-18 client سفارشی باید timeout و سیاست امنیتی مناسب داشته باشد.

~~~php
use PayamBridge\Exceptions\AmbiguousDeliveryException;
use PayamBridge\Exceptions\SdkException;

try {
    $receipt = $connection->sendText($chat, $text);
} catch (AmbiguousDeliveryException $e) {
    // ممکن است ارسال موفق بوده باشد؛ پیش از retry تطبیق یا بررسی کنید.
} catch (SdkException $e) {
    // $e->kind، $e->context، $e->providerCode، $e->retryAfter را بررسی کنید.
}
~~~

برای timeout سفارشی یا logger از نوع PSR-3 در PHP مستقل:

~~~php
use PayamBridge\Http\HttpTransport;
use PayamBridge\Http\Timeouts;

$transport = HttpTransport::createDefault(new Timeouts(connect: 5, read: 60, total: 65));
// logger دلخواه را با آرگومان logger: به createDefault بدهید.
~~~

در تست، `new HttpTransport(new PayamBridge\Testing\FakeHttpClient([...]))` را با پاسخ‌های مشخص PSR-7 تزریق کنید. در لاراول transport را پیش از resolve کردن اتصال در container ثبت کنید؛ credential آزمایشی یا `CredentialResolver` آزمایشی هم لازم است. fake درخواست شبکه ندارد و با تمام شدن پاسخ‌ها خطا می‌دهد. نمونه کامل اعلان و صف دیتابیس در `tools/consumer/` وجود دارد.

## استقرار و رفع اشکال

- **credential پیدا نمی‌شود:** متغیر مشخص‌شده در `token_env` را در محیط واقعی فرایند PHP-FPM و worker صف تنظیم کنید. با `config:cache` ممکن است `.env` هنگام اجرا خوانده نشود. در صورت استفاده از vault، `PayamBridge\Laravel\CredentialResolver` را با پیاده‌سازی خود bind کنید. token را در config یا job ثبت نکنید.
- **اتصال ناشناخته یا scope نادرست:** نام منتشرشده، provider/surface، tenant و `ConnectionId` در `ChatRef` را بررسی کنید. مجوز انتخاب نام اتصال را برنامه میزبان باید اعمال کند.
- **قابلیت پشتیبانی نمی‌شود:** `capabilities()` و جدول بالا را بررسی کنید. ایتا App بروزرسانی نمی‌خواند و Poller پایدار برای روبیکا نیست.
- **timeout یا rate limit:** از `kind` و در صورت وجود `retryAfter` برای سیاست retry برنامه استفاده کنید. برای long polling بله timeout خواندن/کل را از timeout سرویس‌دهنده بیشتر بگذارید.
- **احتمال پیام تکراری صف:** پیش از retry نتیجه `delivery_unknown` را بررسی کنید. این نسخه outbox پایدار یا انتزاع idempotency key ندارد.

transport پیش‌فرض پاسخ را به ۴ MiB محدود می‌کند. برای PSR-18 client سفارشی سیاست مناسب TLS، redirect و timeout تعیین کنید. SQLite را در فضای خصوصی پایدار بگذارید و پشتیبان‌گیری و دوره نگهداری مشخص کنید.

## مشارکت و انتشار

برای تغییرات، `composer check` را اجرا کنید. `composer test:repeat` مجموعه آزمون را تکرار می‌کند و `composer test:consumer` مصرف‌کننده PHP و Laravel 12/13 را در صورت دسترسی به وابستگی‌ها بررسی می‌کند. fixture، contract و consumer test جای آزمون زنده با credential معتبر نیستند. نتایج واقعی در [گزارش پیاده‌سازی](docs/IMPLEMENTATION_STATUS.md)، جزئیات در [API](docs/api.md)، [منابع](research/sources.json)، [اختلافات باز](research/doc-conflicts.md)، [تغییرات](CHANGELOG.md) و [فرآیند انتشار](docs/releasing.md) آمده است. لایسنس MIT است.

برای بررسی بسته‌های توزیع، `composer test:archive` را اجرا کنید. این بررسی به `ext-zip` نیاز دارد و مانع ورود vendor، کش تست، فایل محیطی و پکیج لاراول به آرشیو مستقل SDK می‌شود. `composer check` این بررسی را نیز اجرا می‌کند. برای اجرای همه تست‌های توسعه، افزونه‌های `pdo_sqlite` و `zip` باید فعال باشند.

نمونه JSON نصب محلی را با `composer.json` موجود برنامه ادغام کنید؛ فایل کامل پروژه لاراول را با آن جایگزین نکنید. برای PHP مستقل فقط repository هسته را نگه دارید و `composer require payambridge/sdk:^0.1` را اجرا کنید.
