# سند پژوهش و اجرای SDK پیام‌رسان‌های ایرانی برای PHP و Laravel

تاریخ بررسی: ۲۰۲۶-۱۱-۰۵ — ۱۴ آبان ۱۴۰۵

نام کاری پیشنهادی: **PayamBridge / پیام‌بریج**. این نام و namespace پیشنهادی است؛ آزاد بودن نام Composer، دامنه یا علامت تجاری بررسی نشده است.

وضعیت این تحویل: پژوهش مستندات عمومی، طراحی معماری و دستورکار اجرایی Agent. این فایل پیاده‌سازی SDK، گزارش تست زنده یا فهرست تضمین‌شده تمام APIهای خصوصی نیست. هیچ توکن، حساب شخصی یا محیط واقعی پیام‌رسان در این بررسی استفاده نشده است.

## ۱. هدف و معنی دقیق محصول

توسعه‌دهنده بتواند از داخل PHP خام و Laravel با بله، ایتا و روبیکا کار کند: ساخت ربات، دریافت و ارسال پیام، خودکارسازی حساب متعلق به خودش، انتشار محصول در کانال، اتصال به عامل هوشمند و توسعه Mini App. هدف، حذف نیاز مصرف‌کننده به نوشتن دستی HTTP، مدیریت پروتکل، session و parsing است.

خود SDK همچنان باید از API رسمی یا پروتکل شبکه‌ای سرویس استفاده کند. «بدون API» به معنای بی‌نیازی از سرور پیام‌رسان، توکن بات، ورود حساب یا مجوزهای مقصد نیست. سرویس واسطه تجاری نباید وابستگی اجباری محصول باشد.

«همه قابلیت‌ها» در این پروژه یعنی:

- تمام عملیاتِ ثبت‌شده در inventory نسخه مشخص مستندات، با wrapper صریح، مدل داده، مثال و تست؛
- برای پروتکل خصوصی، تمام عملیات کشف‌شده در schema نسخه مشخص، با اعلام سطح آزمون هر عملیات؛
- تفکیک قابلیت مستند، پیاده‌سازی‌شده، تست‌شده با شبیه‌ساز و تأییدشده در محیط واقعی؛
- عدم حذف قابلیت‌های خاص هر سرویس برای رسیدن به یک interface ظاهراً یکسان.

هیچ مهندس یا Agent نباید تضمین «بدون هیچ باگ برای همیشه» بدهد. جایگزین عملی، معیار انتشار، تست تکرارشونده، ثبت محدودیت و پایش تغییرات upstream است.

## ۲. یافته‌های مستند و مرز اطمینان

### ۲.۱. وضعیت اتصال‌ها

| مسیر | آنچه در منابع بررسی‌شده تأیید شد | وضعیت توسعه پیشنهادی |
| --- | --- | --- |
| بله Bot | HTTP API رسمی و دریافت update با webhook و polling | مسیر رسمی مستقل |
| بله Mini App | SDK جاوااسکریپت و اعتبارسنجی سمت سرور | backend و frontend جدا |
| بله سفیر | سرویس سازمانی ارسال پیام و بارگذاری فایل | driver با credential مستقل |
| بله حساب شخصی | نمونه‌های غیررسمی مبتنی بر API داخلی وجود دارند | آزمایشی تا آزمون واقعی |
| ایتایار | API انتشار در کانال/گروه در PDF عمومی | driver مستقل از برنامک |
| ایتا App | ارسال پیام به کاربران مجاز برنامه و API اصالت‌سنجی | driver رسمی برنامه |
| ایتا Mini App | SDK، initData و مستندات توسعه مستقل | پشتیبانی با آزمون WebView |
| ایتا حساب شخصی | پروژه‌های غیررسمی با serialization اختصاصی پیدا شد | بررسی پروتکل و session |
| روبیکا Bot | API رسمی، مدل‌ها و دریافت رویداد | driver رسمی مستقل |
| روبیکا Mini App | در صفحات Bot API بررسی‌شده قرارداد جامع آن احراز نشد | unknown؛ نیازمند منبع مشخص |
| روبیکا حساب شخصی | پروژه‌های user-client عمومی وجود دارند | مسیر آزمایشی مستقل |

منابع این جدول در بخش ۲۴ آمده‌اند. نبود منبع در این بررسی مساوی نبود قابلیت در سرویس نیست. وجود README یا package نیز اثبات کارکرد فعلی آن نیست.

### ۲.۲. بله: خلاصه یافته‌ها

Bot API از خانواده‌ای مشابه تلگرام است، اما این شباهت مجوز انتقال همه متدهای تلگرام به بله نیست. دریافت update، رسانه، مدیریت گفتگو، تعامل دکمه و پرداخت در دامنه مستندات قرار دارند. نشانی Bot API با سفیر یکسان نیست. سفیر credential سازمانی دارد و نباید با session حساب شخصی اشتباه شود. منابع: S1، S2، S3.

برای کاهش وابستگی، consumer عادی فقط driver موردنیاز را نصب کند؛ مدیریت پرداخت و ارسال سازمانی به‌صورت اختیاری باشد.

### ۲.۳. ایتا: سه محصول متفاوت

PDF ایتایار API انتشار است؛ سند برنامه ایتا مسیر جداگانه‌ای دارد. endpoint ارسال برنامه `https://eitaayar.ir/api/app/sendMessage` است و ارسال به کاربر به دسترسی اعطاشده وابسته است. این قابلیت، خواندن تمام پیام‌های حساب شخصی را ثابت نمی‌کند. ایتایار در وب‌سایت خودش وابستگی حمایتی به ایتا را اعلام می‌کند؛ نباید آن را خودسرانه یک واسط ناشناس معرفی کرد. منابع: S4، S5، S6، S9.

در بررسی حاضر قرارداد رسمی دریافت عمومی پیام خصوصی با `getUpdates` برای ایتایار/برنامه احراز نشد. افزودن متدی با این نام بدون سند و تست ممنوع است.

### ۲.۴. روبیکا: قرارداد مستقل

Bot API روبیکا در `https://botapi.rubika.ir/v3/{token}/{method}` قرار دارد. دریافت رویداد از polling یا endpoint انجام می‌شود. قالب دکمه، update و انتقال فایل را نباید با بله یکسان فرض کرد. جدول متد polling از `offset_id` و `next_offset_id` استفاده می‌کند؛ صفحه معرفی به `start_id` اشاره دارد. نام ویرایش keypad نیز بین عنوان متد و نمونه‌ها متفاوت است. این اختلاف‌ها باید با تست واقعی حل شوند. منابع: S13، S14، S15، S16.

## ۳. اختلاف‌های مستندات و تصمیم‌های باز

| شناسه | مشاهده | تصمیم اجرایی لازم |
| --- | --- | --- |
| DOC-01 | روبیکا: start_id در معرفی در برابر offset_id در جدول متد | ثبت دو شاهد؛ آزمون ادامه دریافت چند صفحه؛ عدم تبدیل عددی cursor |
| DOC-02 | روبیکا: editMessageKeypad در توضیح و editInlineKeypad در نمونه؛ یک نمونه نیز به editMessageText اشاره می‌کند | هر نام صرفاً candidate است؛ انتخاب با پاسخ و مشاهده تغییر واقعی |
| DOC-03 | PDF ایتایار: sendDocument در فهرست و sendFile در شرح | نام wire و نام wrapper را پس از آزمون قطعی کن |
| DOC-04 | نمونه امضای ایتا در نسخه وب و GitHub از نظر متن user یکسان نیست | fixture معتبر مستقل؛ ورودی امضاشده را بازنویسی نکن |
| DOC-05 | اصالت‌سنجی آنلاین ایتا یک‌بارمصرف و زمان‌دار است | retry عمومی روی این عملیات اعمال نشود؛ نتیجه مبهم جدا باشد |
| DOC-06 | بعضی توصیه‌های نمایش iframe حذف هدرها را پیشنهاد می‌کنند | سیاست محدود برای route برنامک؛ هدرهای کل سایت حذف نشوند |
| DOC-07 | مستند عمومی کامل Mini App روبیکا در بررسی حاضر یافت نشد | دریافت منبع رسمی؛ عدم ادعای پشتیبانی با صرفاً یک URL button |
| DOC-08 | بعضی کتابخانه‌های user-client قدیمی، آرشیوی یا محدود از نظر license هستند | وابستگی فقط پس از بررسی source، نسخه، مجوز و تست |

برای هر اختلاف `source_url`، بخش، تاریخ، نسخه/commit، رفتار مشاهده‌شده، نمونه پاک‌سازی‌شده و تصمیم ADR ثبت شود. رفع اختلاف باید محدود به همان driver باشد.

## ۴. معماری توزیع و نصب

یک monorepo برای توسعه، چند پکیج Composer برای مصرف پیشنهاد می‌شود. نام‌ها زیر namespace فرضی `payambridge` هستند:

| پکیج | مسئولیت |
| --- | --- |
| contracts | قراردادهای کوچک، شناسه‌ها، قابلیت‌ها و خطاها |
| core | transport، serializer، dispatcher و سیاست‌های عمومی |
| bale-bot | API رسمی بات بله |
| bale-safir | ارسال سازمانی بله |
| eitaa-yar | انتشار ایتایار |
| eitaa-app | ارسال پیام برنامه و verification رسمی |
| rubika-bot | API رسمی بات روبیکا |
| miniapp | اعتبارسنجی backend و ساخت داده launch در مسیرهای مستند |
| runtime | polling، session runner، inbox/outbox و CLI |
| laravel | ServiceProvider، config، queue، commands و notifications |
| testing | fakeها، fixtures و assertionهای consumer |
| bale-user / eitaa-user / rubika-user | اتصال مستقل حساب شخصی؛ نصب اختیاری |
| sdk | metapackage اختیاری برای مجموعه مسیرهای رسمی |

در نسخه اول می‌توان modules رسمی را داخل یک پکیج اصلی نگه داشت تا هزینه انتشار زیاد نشود؛ مرز namespace و وابستگی حفظ شود. تصمیم split در ADR ثبت شود. user-clientها و Laravel bridge از core مستقل بمانند.

Core نباید به Eloquent، facade لاراول، Redis، Node.js یا سرویس AI وابسته باشد. برای HTTP از قرارداد PSR-18 و پیام PSR-7، factoryهای PSR-17 و برای logging از PSR-3 استفاده شود؛ implementation پیش‌فرض قابل تعویض باشد. نام و نسخه dependencyها هنگام پیاده‌سازی با مستندات همان پکیج تأیید شود.

برای async، interface جدا از HTTP همگام طراحی شود. وجود Fiber به‌تنهایی async I/O ایجاد نمی‌کند. موتور async یا WebSocket فقط در driver نیازمند آن نصب شود. اگر پروتکل حساب فقط با sidecar قابل نگهداری شد، پیش‌نیاز آن صریح باشد؛ این حالت با «PHP خالص و بدون runtime دیگر» برابر نیست.

## ۵. ماتریس نسخه هدف

انتخاب پیشنهادی برای شروع: PHP 8.3 تا 8.5، Laravel 12 و 13. این‌ها هدف آزمون هستند، نه ادعای پشتیبانی فعلی. حداقل PHP لاراول ۱۳ طبق S20 برابر 8.3 است.

| محیط | PHP هدف | نوع بررسی |
| --- | --- | --- |
| PHP مستقل | 8.3 / 8.4 / 8.5 | unit، contract و CLI |
| Laravel 12 | 8.3 / 8.4 / 8.5 | integration و پروژه مصرف‌کننده تمیز |
| Laravel 13 | 8.3 / 8.4 / 8.5 | integration و پروژه مصرف‌کننده تمیز |
| Linux | نسخه‌های بالا | همه مسیرهای سرور |
| Windows و macOS | حداقل یک PHP سازگار | نصب، فایل، CLI و stop |

برای هر نسخه تست `prefer-lowest` و نسخه‌های جدید dependency، با محدودیت‌های معتبر اجرا شود. نسخه‌های خارج از CI داخل constraint پشتیبانی منتشر نشوند. Testbench متناسب با major لاراول انتخاب شود. پشتیبانی Symfony/Slim از طریق core ممکن است، ولی تا پروژه نمونه واقعی تست نشده، badge سازگاری داده نشود.

Node.js فقط برای توسعه و build بسته JS اختیاری است. مصرف‌کننده Laravel برای استفاده از API بات نباید نیازمند npm باشد.

## ۶. فهرست ماشینی API و پوشش کامل

Agent ابتدا این فایل‌ها را بسازد:

- `research/sources.json`: URL، نوع منبع، تاریخ، نسخه/commit یا hash محتوا، وضعیت دریافت.
- `research/capabilities.json`: قابلیت‌ها به تفکیک provider، نوع identity و transport.
- `research/api-inventory.json`: هر عملیات رسمی/خصوصی در یک رکورد مستقل.
- `research/doc-conflicts.md`: اختلاف‌ها و تصمیم‌ها.
- `research/license-audit.md`: منبع کد و مجوز دقیق.
- `research/open-questions.md`: مجهولات باقی‌مانده و راه بستن هر مورد.

نمونه قالب رکورد؛ این قالب پیشنهادی است، نه قرارداد هیچ پیام‌رسان:

```json
{
  "provider": "provider-name",
  "surface": "bot",
  "operation": "operation-name",
  "source_url": "https://official.example/docs",
  "source_revision": null,
  "wire_method": null,
  "authentication": null,
  "parameters": [],
  "response_model": null,
  "error_cases": [],
  "side_effect": "unknown",
  "retry_policy": "never-until-classified",
  "evidence": "documented",
  "implementation": "pending",
  "contract_test": "not-run",
  "live_test": "not-run",
  "limitations": []
}
```

مقدار null به معنی «نامعلوم/تکمیل‌نشده» است و نباید generator از آن متد آماده انتشار تولید کند. هر پارامتر باید نام wire، type، required، nullable، omission semantics، default واقعی، محدودیت، nested schema و مثال پاک‌سازی‌شده داشته باشد.

شمارش پوشش باید سه نسبت مستقل بدهد: پیاده‌سازی/کل inventory، تست قرارداد/کل inventory، آزمون واقعی/کل inventory. عملیات blocked از مخرج حذف نشود. وجود `call(string $method, array $data)` پوشش typed محسوب نمی‌شود. متد magic و fake success جای implementation نیست.

خروجی generator قابل بازتولید و بدون timestamp تصادفی باشد. فایل generated دستی ویرایش نشود؛ overrideهای اختلاف مستندات در فایل جدا نگه داشته شود. API reference از schema تأییدشده تولید شود، نه از حدس زبانی Agent.

## ۷. مدل داده و interface مشترک

مدل مشترک کوچک باشد: ConnectionId، ChatRef، MessageRef، UserRef، IncomingEvent، MessageReceipt، MediaReference و CapabilitySet. شناسه‌ها در لایه عمومی string و دارای scope باشند؛ تبدیل به نوع wire در adapter با range check انجام شود.

کلید منطقی داده‌ها حداقل شامل provider، connection، tenant و شناسه خارجی باشد. شناسه یک chat بین سرویس‌ها یا حساب‌ها قابل اشتراک فرض نشود. file_id نیز scoped است.

قراردادها قابلیت‌محور باشند؛ برای مثال `SendsText`، `ReceivesUpdates`، `UploadsMedia`، `ManagesMembers`، `AuthenticatesUserSession` و `ValidatesMiniAppData`. یک interface بزرگ که اکثر متدهایش unsupported است طراحی نشود.

هر نتیجه باید مدل typed و دسترسی کنترل‌شده به raw response داشته باشد. فیلد ناشناخته حفظ شود. enum ناشناخته به Unknown همراه مقدار اصلی تبدیل شود؛ نباید event از بین برود. `null`، false، صفر، رشته خالی و حذف‌شدن فیلد معانی جدا دارند؛ `array_filter()` بدون callback برای serialization مناسب نیست.

قابلیت پشتیبانی‌نشده باید قبل از HTTP با `UnsupportedCapabilityException` شکست بخورد. در لایه native تمام امکانات مختص provider باقی بماند. تصمیم ارسال fallback مثل تبدیل عکس به لینک فقط با policy صریح consumer انجام شود.

## ۸. دامنه Bot API و driverهای رسمی

inventory رسمی باید تمام عملیات و مدل‌های منابع هر driver را پوشش دهد. دسته‌های زیر چک‌لیست پوشش‌اند؛ وجود هر مورد در هر provider نیاز به شاهد دارد:

- هویت بات و اطلاعات chat؛ متن، reply، forward و copy؛
- عکس، فایل، ویدئو، صدا، voice، animation، sticker و album؛
- مکان، تماس، نظرسنجی، فرمت متن و entity؛
- keyboard، inline callback، command و داده launch؛
- ویرایش متن، caption، دکمه و حذف پیام؛
- مدیریت اعضا، مجوز ادمین، محدودسازی، ban، دعوت و pin؛
- webhook، polling، وضعیت endpoint و مدل تمام updateها؛
- پرداخت، پیش‌تأیید، receipt و استعلام در provider پشتیبان؛
- ارسال سازمانی/قالبی، OTP و محدودیت‌های حساب تجاری در driver مربوطه.

سقف فایل، طول متن، rate limit، timeout و محدودیت اعضا از منبع نسخه‌دار یا آزمون قابل تکرار بیاید. عدد ثابت جهانی اختراع نشود. متن فارسی، نیم‌فاصله، emoji چندکدی، RTL و offset entityها تست شوند؛ واحد offset ممکن است byte، code point یا UTF-16 باشد و باید برای هر provider ثابت شود.

## ۹. transport، خطا و retry

برای connect، read، total و upload timeout تنظیم مستقل وجود داشته باشد. TLS verification همیشه فعال بماند. proxy صرفاً تنظیم transport است؛ راه‌حل عمومی محدودیت حساب نیست.

خطاها حداقل تفکیک شوند: transport، timeout، provider API، authorization، permission، validation، rate limit، protocol mismatch، unsupported capability، expired session و ambiguous delivery.

موفق بودن HTTP به‌تنهایی موفقیت API نیست. body خطا و success envelope هر driver مستقل parse شود. exception شامل نام عملیات، connection عمومی، correlation ID و کد provider باشد، نه token یا payload محرمانه.

retry بر اساس نوع عملیات باشد:

- خواندن امن: backoff نمایی با jitter، سقف تلاش و deadline.
- محدودیت نرخ: retry-after معتبر و policy اختصاصی provider.
- عملیات نوشتن: فقط وقتی بی‌اثر بودن درخواست قبلی یا idempotency واقعی ثابت است.
- ارسال با timeout بعد از خروج درخواست: ممکن است پیام ارسال شده باشد؛ نتیجه `delivery_unknown`، نه ارسال دوباره کورکورانه.
- عملیات یک‌بارمصرف: retry خودکار پیش‌فرض خاموش.

raw call نیز باید از redaction، timeout و طبقه‌بندی خطا عبور کند. credential فقط برای origin مورداعتماد ارسال شود. redirect به میزبان دیگر نباید توکن را منتقل کند.

## ۱۰. دریافت پیام و polling پایدار

سه حالت جدا تعریف شود: long polling واقعی، polling دوره‌ای و stream اتصال حساب. برای سرویس فاقد timeout مستند، افزودن گزینه timeout و نامیدن آن long polling ممنوع است.

چرخه پیشنهادی:

1. گرفتن lease برای connection و transport؛
2. بازیابی cursor اختصاصی provider؛
3. دریافت batch با cancellation و deadline؛
4. ذخیره پایدار تمام eventها با کلید یکتا در inbox؛
5. ثبت cursor همان batch در تراکنش مشترک یا پروتکل crash-safe؛
6. dispatch مستقل از دریافت شبکه؛
7. renew lease و backoff در حالت idle یا خطا.

cursor فقط پس از ذخیره پایدار eventها جلو برود، نه لزوماً پس از پایان AI. اگر storage تراکنش مشترک ندارد، الگوریتم جبران و replay همراه تست crash نوشته شود. crash بین enqueue و checkpoint نباید پیام را گم کند. مدل پردازش at-least-once با deduplication است؛ تضمین exactly-once جهانی داده نشود.

یک worker فعال به‌ازای connection با lease و fencing token؛ TTL تنها بدون fencing برای جلوگیری از دو writer کافی نیست. پیام تکراری در چند worker فقط یک job منطقی بسازد. نبود شناسه یکتای event باید با identity مخصوص نوع event حل شود؛ hash متن به‌تنهایی پیام‌های واقعاً متفاوت را حذف می‌کند.

SIGTERM باعث توقف دریافت، تکمیل ذخیره batch و آزادسازی امن lease شود. در Windows از سازوکار cancellation قابل پشتیبانی استفاده شود. max-runtime، max-memory و restart قابل تنظیم باشند. CLI دستور one-shot برای تست و run برای سرویس پایدار داشته باشد.

تعویض webhook و polling فرایند مدیریتی صریح با بررسی وضعیت و بازیابی cursor است؛ کتابخانه هنگام construct نباید webhook فعال را حذف کند.

## ۱۱. webhook و inbox

routeها per connection و per event surface تعریف شوند. token بات نباید بخش URL عمومی webhook باشد. handler ابتدا اندازه body، JSON، نوع محتوا و اعتبار ورودی را بررسی کند، سپس event را پایدار ذخیره و پاسخ مناسب دهد. کار AI یا ارسال فایل در request webhook انجام نشود.

روش اعتبارسنجی webhook باید واقعاً توسط provider پشتیبانی شود. نباید header یا امضای مخصوص تلگرام را بدون سند به بله/روبیکا نسبت داد. اگر امضای رمزنگاری موجود نبود، secret مسیر مستقل و سایر کنترل‌ها محدودیت را کاهش می‌دهند، اما اصالت رمزنگاری‌شده ایجاد نمی‌کنند. این ضعف در مستندات capability ثبت شود.

اگر ذخیره پایدار شکست خورد، موفقیت گزارش نشود. queue outage، storage full، JSON مخدوش، replay، event ناشناخته، delivery خارج از ترتیب و flood ورودی آزمایش شود. سیاست poison event و dead-letter مشخص باشد. raw event فقط با retention و redaction تنظیم‌شده نگهداری شود.

## ۱۲. مدیریت حساب شخصی

این بخش نیاز اصلی محصول است و نباید با ارسال پیام از بات جایگزین شود. برای هر پیام‌رسان، feasibility مستقل تهیه شود؛ نصب driver آزمایشی اختیاری باشد.

مراحل لازم:

1. یافتن منبع schema و پیاده‌سازی مرجع؛ بررسی commit، مجوز و وضعیت نگهداری.
2. بررسی مسیر login، OTP، challenge و 2FA در صورت پشتیبانی واقعی.
3. ساخت نمونه مستقل ورود با حساب آزمایشی متعلق به توسعه‌دهنده.
4. دریافت یک پیام و ارسال یک پاسخ در گفتگوی تست مجاز.
5. ذخیره session، restart، reconnect و revoke؛ اثبات عدم نیاز به OTP در هر اجرا وقتی پروتکل اجازه می‌دهد.
6. استخراج inventory عملیات قابل مشاهده؛ عدم ادعای دسترسی به APIهای خصوصی ناشناخته.
7. port به PHP یا انتخاب bridge اختیاری با قرارداد نسخه‌دار و ارزیابی هزینه runtime.

State machine ورود باید وضعیت‌های مستقل requested-code، awaiting-code، awaiting-second-factor، authorized، expired، revoked، rate-limited و unsupported-challenge داشته باشد. درخواست دوباره OTP خودکار و بی‌وقفه ممنوع؛ cooldown provider رعایت شود. نیاز واقعی به تعامل مالک حساب به‌صورت callback یا CLI ورودی مخفی حل شود، نه ذخیره رمز در source.

موارد هدف پس از اثبات پروتکل: dialogs، history، incoming events، send/edit/delete، media، contacts، گروه/کانال، profile و session management. هرکدام permission و scope جدا داشته باشند. امکان برنامه‌نویسی حساب به معنی دورزدن سطح دسترسی آن حساب نیست.

SessionStore باید رمزنگاری authenticated، key rotation، قفل writer، نوشتن اتمیک و schema version داشته باشد. فایل خارج web root و با دسترسی محدود ذخیره شود. secret در log، queue payload، exception، dump یا repository قرار نگیرد. encryption key از session جدا نگهداری شود.

پروتکل باینری باید برای endian، signed integer، framing، طول، optional flags، partial frame، unknown constructor، compression و corruption fixture مستقل داشته باشد. کد رمزنگاری دستی اختراع نشود. heartbeat، reconnect، resume و gap recovery با رفتار واقعی سنجیده شوند. در تغییر schema fail واضح و قابل تشخیص باشد.

اگر اجرای PHP مستقل تا حد لازم اثبات نشد، driver با برچسب research/experimental باقی بماند؛ یک wrapper پیرامون سرویس واسطه نباید به‌عنوان پیاده‌سازی مستقل معرفی شود. خطر تغییر پروتکل و احتمال محدودیت حساب برای این مسیر در راهنمای خود آن driver توضیح داده شود.

## ۱۳. Mini App / WebBotApp

PHP بخش backend را فراهم می‌کند؛ تعامل با WebView به JavaScript SDK نیاز دارد. دو deliverable مجزا لازم است: بسته Composer برای احراز هویت و اتصال backend، و wrapper کوچک JS/TypeScript یا assets آماده برای frontend.

برای ایتا script رسمی باید از نشانی توصیه‌شده خودش بارگذاری شود؛ بسته نباید یک کپی قدیمی SDK را بدون اطلاع جایگزین کند. منبع S7. برای هر provider، property، method، event، حداقل نسخه SDK و محیط قابل اجرا در inventory جدا ثبت شود.

Backend:

- parser سختگیرانه initData با limit طول و تعداد field؛
- verifier مستقل برای provider و token برنامه درست؛
- مقایسه constant-time و کنترل auth_date، skew و انقضا؛
- تبدیل نتیجه معتبر به principal دارای provider و app scope؛
- ایجاد session کوتاه‌عمر برنامه به‌جای استفاده مداوم از initData؛
- replay control متناسب با login exchange؛
- سیاست جدا برای داده غیرقابل اعتماد deep link و start parameter.

validator داده را یک بار decode کند و قبل از امضا JSON داخلی یا Unicode آن را normalize نکند. کلید تکراری، percent encoding مخدوش و array syntax نامعتبر باید reject شوند. parser عمومی PHP ممکن است نام کلیدها را تبدیل یا duplicateها را پنهان کند؛ parser با تست اختصاصی لازم است.

مستندات بله و ایتا اعتبارسنجی مبتنی بر HMAC و initData دارند، اما اشتراک الگوریتم مجوز اشتراک token یا هویت بین دو برنامه نیست. auth fixtureها باید مستقل باشند. منابع S2 و S8.

ایتای برنامه، علاوه بر اعتبارسنجی محلی، endpoint `https://eitaayar.ir/api/app/verify` برای اصالت‌سنجی دارد. مستندات آن پنجره یک‌دقیقه‌ای و مصرف یک‌باره را بیان می‌کند. این عملیات را داخل verifier محلی مخفی نکن؛ policy جدا و single-flight لازم است. timeout آن ممکن است نتیجه مبهم داشته باشد. IP از trusted proxy configuration استخراج شود، نه header دلخواه کاربر. منبع S10.

Frontend:

- detection محیط و نسخه، ready/close و event subscribe/unsubscribe؛
- theme، viewport، safe area و دکمه‌ها فقط در قابلیت‌های موجود؛
- contact، QR، پرداخت، fullscreen و سایر bridgeها فقط با سند همان provider؛
- feature detection در کنار نسخه و نمایش fallback مناسب؛
- TypeScript types، timeout callback و cleanup؛
- نمونه سبک HTML/JS و نمونه Blade بدون اجبار React/Next؛
- آزمون در WebView واقعی، نسخه وب و clientهای قدیمی قابل پشتیبانی.

initDataUnsafe، شماره تماس callback و callback پرداخت به‌تنهایی مجوز یا پرداخت معتبر ایجاد نمی‌کنند. عملیات مالی و اتصال حساب باید در backend با شاهد معتبر نهایی شوند. برای iframe فقط routeهای برنامک policy مخصوص بگیرند؛ تنظیم CSP و frame-ancestors با originهای واقعی و تست مرورگر انجام شود.

پشتیبانی Mini App روبیکا تا پیدا کردن سند و handshake قابل اعتبارسنجی، unknown باقی می‌ماند. بازکردن یک لینک وب، پشتیبانی کامل Mini App محسوب نمی‌شود.

## ۱۴. ادغام Laravel

موارد اجباری Laravel bridge:

- auto-discovery و ثبت bindingهای قابل override؛
- config با connectionهای نام‌دار و secret reference؛
- سازگاری config:cache، route:cache و اجرای queue طولانی؛
- Facade اختیاری و dependency injection کامل؛
- Notification channel بدون تغییر اجباری مدل User؛
- event/listener، job و middleware برای webhook و Mini App؛
- storage و queue adapter با migrationهای opt-in؛
- fake قابل scope و reset؛ جلوگیری از نشت state بین testها و workerها؛
- عدم نگهداری connection mutable کاربر داخل singleton مشترک در Octane؛
- مستندات transaction و after-commit برای پیام‌های وابسته به سفارش.

دستورهای پیشنهادی، پس از پیاده‌سازی واقعی:

```text
php artisan payam:doctor
php artisan payam:capabilities connection-name
php artisan payam:poll connection-name --once
php artisan payam:poll connection-name
php artisan payam:webhook:status connection-name
php artisan payam:webhook:set connection-name
php artisan payam:user:login connection-name
php artisan payam:user:run connection-name
php artisan payam:user:logout connection-name
```

doctor به‌صورت پیش‌فرض read-only، بدون تغییر endpoint، session یا ارسال پیام باشد. URLهای حاوی token در خروجی آن redacted باشند. exit codeها برای health check مستند شوند. هیچ migration یا تغییر env هنگام install به‌صورت پنهان انجام نشود.

## ۱۵. پاسخ‌گویی با Agent و انتشار محصول

کتابخانه به مدل هوش مصنوعی خاص وابسته نباشد. قرارداد `ResponderInterface` پیام استاندارد و context مجاز را بگیرد و یک یا چند action typed برگرداند. adapterهای AI، MCP یا webhook دلخواه افزونه باشند.

جریان پیشنهادی: inbox پایدار، تشخیص policy، صف گفتگو، ساخت context محدود، فراخوانی responder، کنترل خروجی، outbox و ارسال. متن کاربر داده غیرقابل اعتماد است؛ نباید instruction سیستم، tenant یا ابزارهای مدیریتی را تعیین کند.

حافظه مکالمه با provider/tenant/connection/chat scope شود. خودپیام و پیام botهای دیگر طبق policy فیلتر شوند تا حلقه پاسخ خودکار ایجاد نشود. ترتیب پیام‌های یک گفتگو حفظ شود؛ اگر پاسخ دیر شد یا اپراتور گفتگو را تحویل گرفت، جواب قدیمی ارسال نشود.

budget زمان، هزینه و تعداد token، امکان توقف، fallback و handoff انسانی داشته باشد. file attachment پیش از ارسال به AI طبق تنظیمات مصرف‌کننده بررسی شود. provider credentials هرگز وارد prompt نشوند. agent عمومی نباید بدون policy اجازه حذف کانال، تغییر نقش یا انتشار در مقصد دلخواه داشته باشد.

سناریوی انتشار محصول: render مستقل متن و رسانه، مقصد allowlist، schedule، شناسه محصول/نسخه محتوا، outbox، receipt و امکان edit. وضعیت هر مقصد مستقل ثبت شود. قیمت و موجودی از منبع کاربردی گرفته شود؛ SDK منطق مالی فروشگاه را حدس نزند. ارسال زمان‌بندی‌شده محلی از زمان‌بندی سمت provider تفکیک شود. timezone نمایش می‌تواند Asia/Tehran باشد؛ زمان ذخیره‌سازی UTC.

## ۱۶. رسانه و امنیت شبکه

آپلود و دانلود streaming باشند؛ فایل بزرگ کامل داخل حافظه خوانده نشود. cancellation، byte limit و checksum در تست وجود داشته باشد. URL موقت upload/download مانند API endpoint حساس مدیریت شود.

دریافت URL دلخواه از پیام می‌تواند SSRF ایجاد کند. policy شبکه باید loopback، private/link-local، metadata endpoint، DNS rebinding و redirect به مقصد ممنوع را کنترل کند. فایل محلی فقط از مسیرهای مجاز consumer پذیرفته شود. دانلود با نام remote نباید traversal یا overwrite فایل حساس ایجاد کند.

MIME، extension و filename به‌تنهایی مورد اعتماد نیستند. caption و متن تولیدی متناسب با parse mode escape شوند. HTML صفحه debug از raw payload تزریق نگیرد. محدودیت upload_url و expiry با تست مشخص شود؛ توکن Bot API به origin آپلود دیگری فرستاده نشود مگر قرارداد مستند آن را نیاز داشته باشد.

## ۱۷. تست‌ها و معیارهای قابل سنجش

| سطح | هدف | خروجی لازم |
| --- | --- | --- |
| Unit | مدل، serialization، validator و policy | گزارش deterministic بدون شبکه |
| Contract | درخواست/پاسخ هر عملیات inventory | fixtures و تست مستقل wire |
| Integration | storage، lock، queue و crash recovery | تست همزمانی و rollback |
| Framework | نصب در Laravel 12/13 و PHP خام | پروژه مصرف‌کننده تمیز |
| Live | تطبیق با سرور و UI واقعی پیام‌رسان | run ID، نسخه، نتیجه و شواهد پاک‌سازی‌شده |
| WebView | bridge واقعی Mini App | ماتریس client و SDK |
| Fault/Soak | قطع شبکه و اجرای طولانی | رفتار بازیابی و اندازه منابع |

حداقل تست‌های هر عملیات: success، provider error، timeout، invalid payload و missing/unknown fields مرتبط. عملیات نوشتن باید بررسی side effect و نتیجه مبهم داشته باشد. fixture ساخته‌شده توسط همان serializer به‌تنهایی شاهد درستی serializer نیست؛ نمونه مستقل لازم است.

حالت‌های ضروری:

- 401/403، rate limit، خطای موقت و body غیر JSON؛
- false/zero/empty/null، integer بزرگ و رشته فارسی؛
- دو worker، lease منقضی، crash قبل و بعد commit؛
- update تکراری، خارج از ترتیب، ویرایش و event ناشناخته؛
- ارسال موفق و قطع پاسخ؛ عدم ارسال دوباره خودکار؛
- session خراب، انقضا، revoke و rotation key؛
- initData تغییرکرده، bot token اشتباه، تاریخ آینده/گذشته و duplicate key؛
- Mini App verification یک‌بارمصرف و دو request همزمان؛
- SSRF، redirect، filename traversal و redaction token در URL؛
- tenant A نتواند connection یا history tenant B را بخواند؛
- restart worker با همان cursor و session؛
- pause پاسخ‌گوی AI و جلوگیری از پاسخ به پیام خود؛
- install مصرف‌کننده بدون dependencyهای dev مخزن.

هدف پیشنهادی coverage برای منطق دست‌نویس core حداقل ۹۰٪ line و ۸۰٪ branch؛ coverage جای قرارداد واقعی را نمی‌گیرد. parser، verifier، dedupe و retry باید mutation test هدفمند داشته باشند. فایل generated برای افزایش مصنوعی درصد استفاده نشود.

تکرار معنی‌دار: سه اجرای متوالی سناریوهای اصلی روی build یکسان، شامل حداقل یک restart و قطع اتصال کنترل‌شده. soak اولیه پیشنهادی ۲۴ ساعت با بار محدود و مجاز؛ این یک معیار پذیرش طراحی است، نه تستی که اکنون اجرا شده باشد. fault/load حجیم روی شبیه‌ساز انجام شود، نه سرور عمومی پیام‌رسان.

## ۱۸. سناریوهای پذیرش واقعی

| شناسه | سناریو | شرط قبولی |
| --- | --- | --- |
| AC-01 | نصب SDK در PHP تمیز | بدون Laravel و npm، ارسال آزمایشی مجاز |
| AC-02 | نصب bridge در Laravel 13 | config/route cache و queue بدون خطا |
| AC-03 | تکرار در Laravel 12 | نتیجه یکسان در قراردادهای مشترک |
| AC-04 | پیام ورودی بات بله و روبیکا | ثبت inbox و پاسخ به گفتگوی درست |
| AC-05 | polling و restart | cursor درست؛ عدم گم‌شدن پیام در fault تعریف‌شده |
| AC-06 | دریافت webhook تکراری | یک پردازش منطقی و جلوگیری از side effect تکراری قابل کنترل |
| AC-07 | انتشار ایتایار | متن و فایل در کانال آزمایش؛ receipt معتبر |
| AC-08 | برنامه ایتا | ارسال به کاربر مجاز و خطای روشن بدون مجوز |
| AC-09 | Mini App بله و ایتا | داده معتبر پذیرفته و داده جعلی رد شود |
| AC-10 | حساب شخصی هر provider | login، receive، reply، restart و revoke مستقلاً تأیید شوند |
| AC-11 | پاسخ AI | scope درست، نبود loop و امکان توقف |
| AC-12 | محصول در چند کانال | گزارش مستقل موفق/ناموفق و recovery قابل کنترل |
| AC-13 | token منقضی | خطای قابل اقدام؛ بدون افشای secret |
| AC-14 | فایل بزرگ | حافظه محدود و توقف امن |
| AC-15 | نسخه ناشناخته پروتکل | شکست تشخیصی، نه داده خراب یا success جعلی |

بخش unsupported/unknown در جدول capability موجب موفق‌شدن AC مربوطه نیست. باید blocked با دلیل و نیاز مشخص گزارش شود. برای تست live، consumer باید حساب‌ها و کانال‌های آزمایشی مجاز، tokenها در secret store و مقصدهای تست را فراهم کند. نبود آن‌ها مانع ادامه unit/integration نیست ولی تأیید live را متوقف می‌کند.

## ۱۹. CI، انتشار و نگهداری

CI شامل composer validate، بررسی dependencyها، lint/style، static analysis، unit، contract و integration matrix باشد. ابزارهای PHPStan/Psalm و PHPUnit/Pest بر اساس سازگاری نسخه انتخاب شوند. یک ابزار اصلی در هر دسته کافی است؛ افزودن ابزار هم‌پوشان بدون نیاز ممنوع.

تست live در workflow جدا با secretهای محدود اجرا شود؛ pull request نامطمئن به secretهای حساب دسترسی نداشته باشد. log و artifact scrub شوند. skip تست live به معنی pass نیست. نتایج mock و live در badgeها ادغام نشوند.

انتشار Composer با Semantic Versioning و changelog باشد. code به صورت source عادی نصب شود؛ Composer plugin یا install script مخفی برای login/run daemon اضافه نشود. release باید manifest و نسخه protocol schema را ثبت کند. breaking change driver خصوصی حتی اگر core ثابت است باید اعلام شود.

پایش اختلاف مستندات فقط issue یا diff بسازد؛ تغییر خودکار schema نباید بدون review به release برود. release checklist باید API inventory، dependency license، known limitations، compatibility و وضعیت live را همراه داشته باشد.

## ۲۰. مشاهده‌پذیری و عملیات

metrics پیشنهادی: زمان API، تعداد خطا به تفکیک نوع، فاصله آخرین event، طول inbox/outbox، backlog age، reconnect، rate-limit، duplicate، session expiry و حافظه worker. labels شامل متن پیام، شماره تلفن و شناسه chat با cardinality زیاد نباشند.

health از readiness جدا شود. نبود پیام جدید الزاماً قطع اتصال نیست. doctor نباید برای تست اتصال پیام به کاربر واقعی بفرستد. log ساختاریافته با redaction قبل از formatting و مقصد logging لازم است.

راهنمای deployment: PHP-FPM برای HTTP، worker برای queue و polling/session runner زیر systemd یا supervisor. Docker اختیاری. حداقل نصب نمونه PHP خام سبک باشد؛ storage پایدار برای delivery مطمئن لازم است ولی Redis/MySQL اجباری نباشد. adapter SQLite برای تک‌گره و backend مشترک برای چندگره با تست قفل پیشنهاد می‌شود.

## ۲۱. تقسیم کار Agentها و ترتیب وابستگی

| مرحله | مالک | تحویل و gate |
| --- | --- | --- |
| A | پژوهشگر API | inventory، اختلاف‌ها، capability و منابع نسخه‌دار |
| B | معمار/core | قراردادها، transport و error/retry با تست |
| C | سه Agent provider | adapter رسمی مستقل؛ contract هر عملیات |
| D | Agent runtime | inbox/outbox، polling و webhook با fault test |
| E | Agent Laravel | integration و پروژه‌های مصرف‌کننده تمیز |
| F | Agent Mini App | backend verifier، JS wrapper و نمونه WebView |
| G | سه Agent user-client | feasibility و سپس implementation جداگانه |
| H | reviewer مستقل | بررسی امنیت، ادعاهای پوشش و acceptance |
| I | maintainer | release candidate، docs و changelog |

A قبل از implementation وسیع اجرا شود؛ B قرارداد مشترک را تثبیت کند. C و پژوهش G می‌توانند موازی باشند. هر Agent مالک مسیر مشخص باشد. تغییر contract مشترک با ADR و migration هماهنگ شود، نه تغییر ناگهانی interface توسط driver.

هیچ مرحله‌ای مجوز حذف نیاز اصلی حساب شخصی نیست. اگر بخشی blocked شد، کار مستقل ادامه یابد و blocker دقیق ثبت شود. پایان هر مرحله باید diff، دستور تست، نتیجه واقعی، نقص‌های باز و کار بعدی قابل اجرا داشته باشد.

## ۲۲. ساختار مستندات خروجی پروژه

README فارسی و انگلیسی؛ Quick Start PHP و Laravel؛ راهنمای credentials به تفکیک identity؛ API reference؛ جدول قابلیت؛ migration guide؛ miniapp backend/frontend؛ حساب شخصی و session؛ polling/webhook؛ AI responder؛ انتشار محصول؛ security و troubleshooting.

مثال‌ها باید runnable و تست‌شده باشند: echo bot، webhook، polling، Laravel notification، برنامه ایتا، مینی‌اپ ورود، user-session responder و ناشر محصول. مثال قابلیت unknown نباید ظاهری کامل داشته باشد. تمام نام‌های API عمومی پیشنهادی در این سند تا زمان implementation صرفاً طراحی‌اند.

در مستندات هر متد: provider/surface، پارامتر، خروجی، خطا، side effect، retry، permission، version و وضعیت live مشخص شود. آموزش نصب نباید از consumer بخواهد کد transport یا signing را خودش بازنویسی کند.

## ۲۳. پرامپت مادر برای Agent توسعه‌دهنده

متن زیر را همراه همین فایل و repository پروژه به Agent بده:

> نقش تو Lead PHP SDK Engineer، Laravel Package Maintainer و Protocol Integration Engineer است. این سند قرارداد محصول است. هدف پیاده‌سازی واقعی یک SDK Composer برای بله، ایتا و روبیکا با سه حوزه مستقل Bot، User Account و Mini App است.
>
> ابتدا repository و دستورهای محلی را بخوان؛ تغییرات موجود کاربر را حفظ کن. اگر مخزن خالی است skeleton حداقلی ایجاد کن. پیش از نوشتن انبوه wrapper، منابع رسمی معرفی‌شده را تازه بررسی کن و inventory نسخه‌دار عملیات، مدل‌ها، events و قابلیت‌ها بساز. اختلاف مستندات را ثبت کن. هیچ متد، endpoint، محدودیت یا مدل را از شباهت به تلگرام حدس نزن.
>
> core مستقل PHP و Laravel bridge جدا بساز. PHP 8.3 تا 8.5 و Laravel 12/13 اهداف اولیه‌اند؛ constraint نهایی فقط با CI معتبر تثبیت شود. user-clientها اختیاری و مستقل باشند. نصب معمول بات نیازمند Python/Node/Redis نباشد. اگر پروتکل خصوصی bridge لازم دارد، این وابستگی را شفاف مستند کن و pure PHP وانمود نکن.
>
> کار را در برش‌های قابل آزمون پیش ببر: inventory، transport، ارسال، دریافت، storage/recovery، Laravel، Mini App، user sessions و سناریوهای مصرف‌کننده. فقط برنامه نده؛ implementation، تست و مستندات مربوط به هر برش را تکمیل کن. برای تصمیم‌های برگشت‌پذیر معمول متوقف نشو.
>
> تمام APIهای مستندِ snapshot باید در جدول پوشش بمانند؛ missing یا blocked را حذف نکن. raw-call، stub، TODO، mock و returning true پوشش کامل نیستند. پیاده‌سازی، contract-tested و live-verified وضعیت‌های جدا دارند. نبود credential یا امکان WebView واقعی را دقیق گزارش کن و تست mock را زنده معرفی نکن.
>
> امنیت credential/session، عدم نشت tenant، initData validation، retry عملیات نوشتن و crash-safe cursor الزامی‌اند. UI یا callback پرداخت را تأیید تراکنش تلقی نکن. دریافت webhook بدون persist نباید success بدهد. برای timeout ارسال، نتیجه مبهم را حفظ کن و خودکار پیام تکراری نساز.
>
> حساب شخصی هر پیام‌رسان را واقعاً feasibility-test کن؛ به‌جای آن Bot API تحویل نده. از کد repository عمومی بدون بررسی license کپی نکن. login فقط با حساب مجاز و secretهای ارائه‌شده در محیط محلی باشد. OTP/رمز/session در مخزن، گزارش یا چت نوشته نشود. اگر credential لازم نیست، روی کار مستقل ادامه بده.
>
> پس از پیاده‌سازی، پروژه‌های مصرف‌کننده تمیز بساز؛ نصب Composer، config/route cache، queue، polling restart، webhook replay، Mini App tampering و sender timeout را آزمایش کن. سناریوهای اصلی را سه بار از وضعیت معلوم، با حداقل یک restart و fault کنترل‌شده اجرا کن. تست‌ها باید رفتار واقعی را بررسی کنند، نه تکرار implementation.
>
> پیش از پایان، reviewer مستقل یا مرحله بازبینی جدا داشته باش. گزارش نهایی شامل فایل‌های تغییرکرده، آزمون‌های اجراشده، درصدهای پوشش مستقل، محدودیت‌ها، APIهای باز، credentialهای لازم و گام بعدی باشد. بدون شواهد ادعای بدون باگ، همه APIهای خصوصی، همه نسخه‌های فریمورک یا production-ready نکن. انتشار عمومی و ارسال به مقصدهای غیرآزمایشی جزو این دستور نیست.

## ۲۴. منابع و نحوه استفاده

همه منابع زیر در تاریخ بالای سند بررسی یا برای بررسی تکمیلی مشخص شده‌اند. منابع غیررسمی فقط شاهد وجود پیاده‌سازی هستند؛ تأیید رسمی سرویس یا توصیه به dependency نیستند.

| شناسه | منبع | کاربرد |
| --- | --- | --- |
| S1 | https://docs.bale.ai/ | Bot API بله |
| S2 | https://docs.bale.ai/miniapp | Mini App بله |
| S3 | https://docs.bale.ai/safir | سفیر بله |
| S4 | https://eitaayar.ir/ | معرفی و نسبت ایتایار با ایتا |
| S5 | https://eitaayar.ir/assets/download/API_eitaayar.ir.pdf | PDF عمومی API؛ دارای اختلاف نام متد |
| S6 | https://developer.eitaa.com/docs/Home/ | معرفی برنامه/برنامک |
| S7 | https://developer.eitaa.com/docs/Develop/JsSDK/ | JS SDK ایتا |
| S8 | https://developer.eitaa.com/docs/Develop/AuthorizationViaHash/ | اعتبارسنجی محلی initData |
| S9 | https://developer.eitaa.com/docs/Develop/SendMassage/ | ارسال برنامه به کاربر |
| S10 | https://developer.eitaa.com/docs/Develop/ValidateHash/ | اصالت‌سنجی آنلاین یک‌بارمصرف |
| S11 | https://developer.eitaa.com/docs/Develop/SendData/ | داده launch؛ تکمیل inventory از این صفحه |
| S12 | https://developer.eitaa.com/docs/WebNotice/ | iframe و نمایش وب |
| S13 | https://rubika.ir/botapi | معرفی Bot API روبیکا |
| S14 | https://rubika.ir/botapi/methods | متدها و اختلاف نمونه‌ها |
| S15 | https://rubika.ir/botapi/models | مدل‌ها و enumها |
| S16 | https://rubika.ir/botapi/group-channel | گروه و کانال |
| S17 | https://github.com/eitaa/Documentation | منبع مستندات ایتا؛ مقایسه با وب |
| S18 | https://github.com/mtp1376/bale | user-client غیررسمی؛ README محدودیت proprietary را ذکر می‌کند |
| S19 | https://github.com/shayanheidari01/rubika | نمونه Rubpy؛ هنگام بررسی archived و دارای GPL-3.0 |
| S20 | https://laravel.com/docs/13.x/releases | نیازمندی و نسخه‌های Laravel |
| S21 | https://www.php.net/supported-versions.php | چرخه پشتیبانی PHP |
| S22 | https://github.com/aminmadaniofficial/aiobale | user-client غیررسمی بله؛ نیازمند ممیزی کد و license |
| S23 | https://github.com/EhsanAhmadzadeh/eitaa-cli/blob/main/docs/protocol.md | شرح پروتکل غیررسمی ایتا؛ اعتبارسنجی مستقل لازم |
| S24 | https://packagist.org/packages/disintegrations/eitaa-serializer-laravel | نامزد بررسی PHP؛ نصب و source audit انجام نشده |

بررسی حاضر تمامی صفحه‌های فرعی، کد همه کلاینت‌های غیررسمی و همه endpointهای زنده را پوشش نداده است. استخراج field-by-field و schema خصوصی نسخه‌دار، خروجی مرحله A/G توسعه است. منابعی که عنوان all/full دارند بدون شواهد آزمون پذیرفته نشوند.

## ۲۵. تعریف پایان کار

پروژه زمانی برای انتشار محدوده‌ای مشخص آماده است که inventory آن محدوده بسته، wrapperها و تست‌ها کامل، اختلاف‌های مؤثر حل، consumer examples اجرا، secretها محفوظ و گزارش live موجود باشد. بخش‌های unknown و experimental واضح باقی بمانند.

پایان یک انتشار رسمی Bot به معنی پایان کل محصول نیست؛ نیاز حساب شخصی و Mini App همچنان در roadmap و acceptance مستقل باقی می‌ماند. هدف نهایی، بسته‌ای قابل نگهداری با قابلیت‌های اثبات‌شده است که توسعه‌دهنده بتواند به مرزهای آن اعتماد کند.
