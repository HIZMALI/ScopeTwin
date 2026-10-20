# BUILD 03.6 — Bağlantı ve açık assistant bağlama raporu

**Sonraki teslim çalışması:**2026-10-20'de ayrıca izin verilen tek canlı deneme yapıldı; açıklama kabul edilmedi, cleanup doğrulandı, flagfalse'a döndü ve retry yapılmadı. Bu rapordaki NOT_TESTED ifadeleri önceki preflight aşamalarına aittir. Güncel sonuç ve medya teslimleri SUBMISSION_DELIVERY_REPORT.md içindedir.

Tarih: 2026-10-18. Taban `fea6281`; yerel dal `feat/build-03-6-backboard-connectivity`. Başlangıçta yalnızca iki izlenmeyen talimat dosyası vardı. Kullanıcı bunları koruyarak yeni dala devam edilmesini açıkça onayladı. Dosyaların içeriği değiştirilmedi. Bu çalışma AI üretimi veya uzak assistant/thread yazımı/silmesi yapmadı. Push, deployment, satın alma, promosyon işlemi, mevcut servis recreation veya özel ortam/ACL değişikliği yapılmadı.

**Güncel durum (2026-10-20):** İlk bölümler tarihsel BUILD 03.6 kaydıdır; 2026-10-18 onaylı assistant/18734 kurulumu tamamlandı. Tekrarlanan istekte aynı assistant ve kapalı 18734 runtime yeniden doğrulandı; ikinci assistant oluşturulmadı. Docker başlangıçta kapalıydı, ayrı kullanıcı onayıyla başlatıldı; 18733/18734 sağlıklı, başlangıçta kapalı olan 18731 kapalı bırakıldı. Güncel ölçümler sondaki **2026-10-20 yeniden doğrulama** bölümündedir. **Canlı generation hâlâ NOT TESTED; BACKBOARD_ENABLED=false.**

## Ölçülen durum

| Alan | Sonuç |
| --- | --- |
| `ENV_MAP` | 18731, 18733 ve 18734 `/ready` 200. Windows CLI kök `.env`: production/SQLite, APP_KEY ve alan şeması MISSING; Backboard anahtarı PRESENT. Bu CLI bağlamı çalışan 18731'in yerine kullanılamaz. 18733/18734 ayrı Docker/PostgreSQL provalarıdır; mevcut APP_KEY'leri PRESENT. |
| `TLS_DIAGNOSIS` | Windows PHP 8.4.24 / OpenSSL 3.0.21 varsayılan trust ile aynı Backboard sunucusunda **cURL 60 / TLS_CHAIN_VERIFICATION / HTTP 0** verdi. INI/default CA dosya/dizinleri MISSING. Sistem curl 8.21.0 / Schannel doğrulaması başarılı, HTTP 200. 18734 PHP/OpenSSL 3.5.9 mevcut CA ile TLS doğruladı, HTTP 200. |
| `CA_ACTION_NEEDED` | 18734 için yeni CA gerekli değil. Windows PHP için mevcut `C:/Program Files/Git/mingw64/etc/ssl/certs/ca-bundle.crt` okunabilir; yalnızca bir public test isteğinde CURLOPT_CAINFO ile **TLS_VERIFIED / cURL 0 / HTTP 200** elde edildi. Kalıcı INI değiştirilmedi. |
| `CATALOG_HTTP_STATUS` | **200 / VALID_JSON**. Windows normal sertifika doğrulamasıyla tam bir authenticated GET; model endpoint'ine tekrar yapılmadı. Katalog `models` / `total`, text/JSON bayrağı ve negatif olmayan fiyat alanları kontrol edildi. |
| `KEY_AUTH_STATUS` | **AUTHENTICATED_READ_OK**. Kök `.env` içindeki mevcut BACKBOARD_API_KEY yalnızca bellekte, authenticated GET başlığında kullanıldı; değeri veya parçası çıkarılmadı. APP_KEY ile karıştırılmadı. |
| `ASSISTANT_INVENTORY_STATUS` | Bir GET: **HTTP 200 / VALID_JSON_UNEXPECTED_SCHEMA**. Envanter şekli/sayısı doğrulanamadı. Bunun provider şeması ile istemcinin JSON dönüş tipi arasındaki nedeni kesinleştirilmedi; tekrar çağrı yapılmadı. UUID, isim, açıklama, prompt, araç veya belge raporlanmadı. Bu sonuç izolasyon kanıtı değildir. |
| `EXPLICIT_ASSISTANT_BINDING` | Kaynakta zorunlu server-only BACKBOARD_ASSISTANT_ID UUID doğrulaması; eksik/bozuksa sıfır upstream çağrı. Payload'a açık bağlama, yanıtta aynı kimlik kontrolü ve assistant kapsamlı cache. Varsayılan/ilk assistant'a geçiş yok. |
| `ACL_STATUS` | `.env`, `storage/.env.rehearsal`, `storage/.env.build035-rehearsal`: **BROAD**. Users ve Authenticated Users üzerinden inherited Allow READ var; ilgili explicit DENY görülmedi. Everyone Allow veya başka ayrı Allow READ kaydı saptanmadı. Mevcut SYSTEM/Administrators erişimi korunmalı. ACL değiştirilmedi. |
| `18734_CONFIG_READY` | **NOT_READY_FOR_LIVE**: çalışan prova BUILD 03.5 image'ını koruyor; yeni kaynak oraya uygulanmadı. Backboard disabled; anahtar, provider/model ve explicit assistant bağlamı MISSING. Advisor tabloları/sabit sentetik fixture mevcut; son preflight'ta advisor kota/cache boştu. |
| `LIVE_GENERATION_STATUS` | **NOT_TESTED**. Model çağrısı, manuel smoke, uzak POST/PUT/DELETE yok. |
| `CREDITS_SPENT` | **NOT_CLAIMED**. Yalnız GET yapıldı; GET metering/bakiye değişimi bağımsız doğrulanmadı. Dashboard bakiye veya kullanım verisi okunmadı. |

İki authenticated istek, yalnızca normal TLS doğrulaması başarılı olduktan sonra, bellekteki başlıkla, kısa timeout ve redirect kapalı yapıldı: `/api/models?model_type=llm&supports_json_output=true&limit=50` ve `/api/assistants?skip=0&limit=20`. Her endpoint bir defa; retry yok. Ham response veya header loglanmadı/kaydedilmedi. Public TLS kontrolleri anahtarsızdı. Önceki preflight'ın ayrıntısı tutulmayan INVALID sonucu yeniden yorumlanmıyor; yukarıdaki hata sınıfı bu çalışmada gerçekten ölçüldü.

## Doğrulanmış katalog adayları

Bu üç kayıt sorgulanan ilk 50 içinden fiyat/capability filtresiyle seçildi; küresel en ucuz model iddiası yok. JSON-output bayrağı true; reasoning/media üretim bayrakları açık olan kayıtlar seçilmedi. Fiyatlar katalog ilanıdır, gerçekleşmiş çağrı maliyeti veya güvenilirlik/kalite testi değildir. Runtime seçimi yapılmadı.

| Provider | Exact model name | JSON output | Girdi USD / 1M token | Çıktı USD / 1M token |
| --- | --- | --- | --- | --- |
| featherless | anthracite-org/magnum-v4-72b | true | 0.0375 | 0.15 |
| featherless | openai/gpt-oss-20b | true | 0.0375 | 0.15 |
| featherless | openai/gpt-oss-120b | true | 0.0375 | 0.15 |

İlk ekonomik inceleme adayı olarak `featherless` / `openai/gpt-oss-20b` düşünülebilir; JSON talimatlarına uyumu, reasoning davranışı, yanıt süresi ve gerçek faturalama üretim yapılmadan kanıtlanmış değildir. Diğer iki kayıt karşılaştırma adayıdır; büyüklük/isimden güvenilirlik sonucu çıkarılmadı. Dedicated assistant ve özel yapılandırma onayı olmadan hiçbir aday etkinleştirilmemeli. Çağrı sayısı kotası bir dolar tavanı değildir.

## Kaynak değişikliği ve otomatik doğrulama

- `config/backboard.php`, `.env.production.example`, `compose.production.yaml`: yalnız sunucuda kullanılan BACKBOARD_ASSISTANT_ID; boş default, tahmini/varsayılan assistant yok.
- `app/Services/ReplayAdvisor.php`: binding validation config aşamasında ve cache okumadan önce; JSON payload'a kimlik; yanıtta yoksa/uyuşmazsa fallback ve mevcut thread temizliği. Cache fingerprint'i assistant ve v2 prompt sürümüyle ayrıldı. Kimlik UI/props/loglara eklenmedi. Eski tenant/role, quota, timeout, cleanup ve deterministik alan davranışı korunur.
- `tests/Feature/ReplayAdvisorTest.php`: eksik/bozuk binding, doğru payload, eksik/uyuşmayan response identity, UI/response/cache/log sızıntısızlığı, kimlik değişiminde cache izolasyonu ve kota, binding kaldırıldığında cache'in de kapatılması. `tests/scripts/advisor-concurrency.php`: ortak **mock** bağlamı worker belleğine taşınır; gerçek assistant kullanılmaz veya yazdırılmaz.
- README, entegrasyon/go-live/Devpost/video notları ve IMPLEMENTATION_LOG güncellendi. **Backboard live call: NOT TESTED** ifadesi korundu. Yeni migration/şema değişikliği gerekmedi.

| Kontrol | Gerçek sonuç |
| --- | --- |
| SQLite `php artisan test --compact` | **178 test / 1367 assertion PASS**, 10.95 saniye; önceki 169/1291 kapsamı korunur |
| PostgreSQL `./scripts/test-postgres.ps1` | **178 test / 1367 assertion PASS**, 38.57 saniye; yalnız `scopetwin_test` |
| Mevcut migration ve yarışlar | Üç migration rollback/reapply disposable test DB'de PASS; eski idempotency/onay/policy yarışları PASS; üç advisor yarışı PASS |
| Chromium `npm run test:browser` | **4 senaryo PASS**, 1.3 dakika; ayrı 18732 / `scopetwin_browser_test`, mevcut uygulamalara dokunulmadı |
| Üretim public tur `npm run test:release -- --grep 'production public tour'` | **3 senaryo PASS**, 16.1 saniye; Chromium/Firefox/WebKit, masaüstü/mobil, CSP/klavye/axe. Mevcut 18734 turuna yönelik stateless kontrollerdir; yeni binding'in o image'a uygulanmış olduğu iddia edilmez. |
| TypeScript / Vite / ESLint / Prettier / Pint | PASS; Vite production bundle 5.14 saniyede üretildi |
| Composer validate/audit / npm audit | Strict config valid; Composer advisory 0; npm vulnerability 0 |
| Yeni image / ağsız paket testi | `docker build -t scopetwin:build036 .` PASS. Ayrı `--rm --network none` konteynerde disabled, eksik ve bozuk binding guard'ları PASS; mevcut servis/özel env enjeksiyonu yok. Image çalışır servislere uygulanmadı. |
| Yeni app image güvenlik taraması | Trivy **0 HIGH / CRITICAL**, 20:09:20Z; ignore/bastırma eklenmedi. DB image değişmedi ve yeniden taranmış sayılmadı. |
| Kaynak ve staged secret/whitespace taraması | **304 kaynak yolu** secret-pattern PASS. Yazılan kaynaklarda whitespace PASS. Korunan kullanıcı connectivity talimatının 3 satırındaki kasıtlı Markdown iki-boşluk satır sonları tam diff check'te uyarı verir; dosya değiştirilmedi, kaynak gate'i yalnız bu girdiyi hariç tutar. Ham anahtar/UUID/response evidence'i Git'e alınmaz. Bağımsız sızma testi değildir. |
| Koruma | Eski hash guard: `.env` MATCH ve **183 orijinal PostgreSQL alan kaydı MATCH**. Üç özel env, PHP INI ve iki talimat dosyası başlangıç hash'leriyle MATCH. 18731/18733/18734 yeniden `/ready` 200; mevcut servisler değiştirilmedi. |

İlk yeni cache testi, fake HTTP'nin önceki assistant cevabını sabit tuttuğu için 502 verdi. Mock, güncel mock kimliği döndürecek şekilde düzeltildi; gerçek eşleşme denetimi gevşetilmedi. Son iki DB koşusu geçti.

Tam üretim yazımlı browser yolculukları bu çalışmada mevcut 18734 DB'sine uygulanmadı. Mevcut servisin recreation/anahtar enjeksiyonu veya yeni runtime kurulumu onaysız yapılmadığından yeni image için runtime browser/başlangıç smoke'u doğrulanmış sayılmaz. Bunun yerine yeni backend iki DB'de faked HTTP ile; tam UI/API akışı disposable Chromium ortamında; mevcut üretim turu üç tarayıcıda doğrulandı. Önceki BUILD 03.5'in altı release PASS sonucu bu build'e kopyalanmadı. Yeni image üretildi, tarandı ve ağsız guard testi geçti; image üretmek deployment değildir.

## Minimal değişiklik planı — uygulanmadı, onay gerekiyor

1. **ACL — yalnız üç dosya:** `C:/Projects/ScopeTwin/.env`, `C:/Projects/ScopeTwin/storage/.env.rehearsal`, `C:/Projects/ScopeTwin/storage/.env.build035-rehearsal`. Yetkili operatör önce mevcut owner ve gerçek Compose/runtime hesabını özel olarak doğrulamalı. Hedef DACL: geçerli kullanıcı/owner, SYSTEM ve Administrators için FullControl; farklı gerekli runtime hesabı varsa yalnız gereken Read. Önce bu explicit erişimler kurulup sahibin Read/Write/WriteDac ve Compose okuma erişimi doğrulanmalı. Ardından yalnız bu üç leaf dosyada geniş inherited Users/Authenticated Users Allow girişlerinin taşınmasını engelleyen incelenmiş korumalı DACL uygulanmalı; owner/deneysel runtime erişimi kaybedilmemeli. Parent klasör, başka proje, blanket `icacls /reset` veya tüm inheritance üzerinde işlem yok. Sonrasında aynı token ile dosya okuma + hash + servis health kontrolü yapılmalı; özel içerik/log basılmamalı. Bu bir uygulama izni değildir.
2. **CA — kalıcı değişiklik şart değil:** 18734 mevcut trust çalışıyor; doğrulanmış Windows TLS yolu katalog okumaya yetti. Windows PHP'nin de varsayılan TLS ile çalışması istenirse ayrı onayla yalnız `C:/php84/php.ini` içindeki `curl.cainfo` ve `openssl.cafile`, `C:/Program Files/Git/mingw64/etc/ssl/certs/ca-bundle.crt` absolute yoluna ayarlanabilir. Mevcut okunabilir Git CA paketiyle doğrulama test edildi; bundle/INI değiştirilmedi. Paket güncellemelerinde yolu/trust durumunu yeniden doğrulamak gerekir. Bağımsız bundle tercih edilirse kaynak yalnız resmi `https://curl.se/ca/cacert.pem` ve resmi SHA-256 olmalı; indirme/hedef yol/onay ayrı incelenmeli. TLS kontrolünü kapatmak kabul edilmez. Bu build yeni CA indirmedi veya kurmadı.
3. **Dedicated assistant — ayrı uzak yazım onayı:** hesap sahibi boş, yalnız ScopeTwin için assistant oluşturmalı veya oluşturulmasına açıkça izin vermeli. Belge/memory/tool bağlamını özel olarak inceleyip UUID'yi sohbet/rapor/screenshot yerine özel sunucu ayarında sağlamalı. Envanter GET'i veya sentaktik UUID bunun izolasyonunu kanıtlamaz. Mevcut/default assistant otomatik seçilmez.
4. **Yalnız 18734 özel runtime — ayrı yerel release onayı:** BUILD 03.6 image'ının sadece 18734'e uygulanması, `storage/.env.build035-rehearsal` içinde BACKBOARD_API_KEY, BACKBOARD_ASSISTANT_ID, BACKBOARD_LLM_PROVIDER, BACKBOARD_MODEL_NAME alanlarının özel olarak sağlanması ve config cache'in kontrollü recreation ile yenilenmesi gerekir. BACKBOARD_ENABLED önce false kalmalı; mevcut APP_KEY, DB_PASSWORD, owner parolası, DB/volume/port kimliği aynen korunmalı. Kök `.env` dosyası topluca kopyalanmamalı; 18731/18733 restart/cache reset yok. Mevcut anahtarın yalnız adı BACKBOARD_API_KEY'dir; değeri hiçbir komut argümanında bulunmamalı. Docker exec'e env eklemek mevcut cached config'i kendiliğinden değiştirmez. Özel env/anahtar enjeksiyonu/recreation/deployment şu an yetkili değildir.
5. **Tek canlı sentetik çağrı — son ve ayrı harcama onayı:** yalnız yukarıdaki engeller giderilince, sabit fixture'ı mevcut `scopetwin_build035_rehearsal` DB'sinde doğrulayan `scope:backboard-smoke --live-backboard-smoke --confirm-charge-credits` komutu, sadece doğrulanmış 18734 CLI bağlamından çalıştırılabilir. Şimdi çalıştırılmadı. Bir generation + cleanup birden fazla HTTP isteğidir; timeout hâlâ ücretlenebilir, retry yoktur. Kullanıcı dashboard kullanım/bakiyesini önce/sonra kendisi incelemeli; auto-recharge veya plan alımı yoktur.

**OPERATOR_APPROVALS_REQUIRED:** hedef ACL planı; istenirse Windows PHP CA ayarı; dedicated assistant oluşturma/izolasyon onayı; yalnız 18734 private config + source image promotion/recreation; en son tek generation için bağımsız kredi harcama onayı. Bu liste, toplu veya önceden verilmiş izin olarak yorumlanamaz. En kısa yol: önce ACL ve dedicated assistant/18734 planını onaylamak; Windows PHP INI değişikliği 18734 için zorunlu değildir.

Galuxium partner promosyonu çözümlenmedi; katalog erişimi veya sıradan kredi, sponsor uygunluğu kanıtı değildir. BUILD 04A başlamadı. Gelecekteki build, assistant bağlamasını düşürmemek için bu feature commit'ini de tabana almalı.

## Resmi sözleşmeler

[Send message](https://docs.backboard.io/api-reference/threads/send-message), [model fields/pricing units](https://docs.backboard.io/concepts/models), [list models](https://docs.backboard.io/api-reference/models/list), [assistant inventory](https://docs.backboard.io/api-reference/assistants/list), [delete thread](https://docs.backboard.io/api-reference/threads/delete), [PHP curl CA](https://www.php.net/manual/en/curl.configuration.php), [curl certificate trust](https://curl.se/docs/sslcerts.html). Belgeler 2026-10-18'de yeniden incelendi. Canlı generation veya cleanup sonucu yerine geçmez.

## Onaylı assistant ve 18734 hazırlığı — 2026-10-18

Başlangıç: doğrulanmış `5186390`, temiz `feat/build-03-6-backboard-connectivity`. Kullanıcı bu mevcut dalda devam edilmesini, en fazla bir dedicated assistant oluşturulmasını ve yalnız 18734 özel ayar/app recreation işlemini açıkça onayladı. Yeni dal açılmadı. Daha önce onayla düzeltilen üç özel dosya ACL'si başlangıçta PRIVATE olarak doğrulandı; bu işlemde ACL değişmedi. Bu bölüm eski onay bekleyen maddelerin hangilerinin gerçekten tamamlandığını kaydeder.

| İstenen sonuç | Gerçek ölçüm |
| --- | --- |
| Assistant oluşturuldu mu? | **EVET.** Önce `GET /api/assistants?name=ScopeTwin%20Replay%20Advisor&skip=0&limit=20`: HTTP 200, geçerli JSON dizi, tam isim eşleşmesi **0**. Ardından **yalnız bir** `POST /api/assistants`: HTTP **200**; retry yapılmadı. Assistant adı **ScopeTwin Replay Advisor**. |
| İzolasyon doğrulandı mı? | **EVET, ölçülen kapsamda.** Dönen UUID özel bellekte doğrulandı; ayrı assistant GET'iyle aynı kimlik/isim/talimatlar ve araçsız yapı kontrol edildi. Assistant kapsamlı documents GET'i **0 belge**, threads GET'i **0 thread**, memory stats GET'i **0 memory** döndürdü. Her kontrol HTTP 200. Başka/default assistant kullanılmadı veya değiştirilmedi. |
| Provider/model | **featherless / openai/gpt-oss-20b**, provider+text+JSON filtreli güncel katalog GET'i HTTP 200; exact model kaydı ve JSON-output true doğrulandı. İlan edilen fiyat: **girdi USD 0.0375 / çıktı USD 0.15 / 1M token**. Bu bir üretim veya gerçekleşmiş maliyet testi değildir. |
| 18734 özel config | **VERIFIED_DISABLED.** Yalnız BACKBOARD_API_KEY, BACKBOARD_ASSISTANT_ID, BACKBOARD_LLM_PROVIDER, BACKBOARD_MODEL_NAME ve gerekli APP_IMAGE alanı güncellendi. BACKBOARD_ENABLED **false** kaldı. Boot config cache'inde key/UUID bağlama eşitliği hash ile, provider/model ve kota değerleri ayrıca doğrulandı. Değerler rapora çıkarılmadı. |
| Image / sağlık | Yalnız `scopetwin-build035-app-1`, **scopetwin:build036** ile app-only recreate edildi. Çalışan image kimliği özel rollback kaydıyla ve BUILD 03.6 kaynağının sekiz kritik paket dosyası hash'iyle eşleşti. Docker **healthy**, `/ready` **200**. DB container/volume kimlikleri aynı. |
| Mevcut sırlar/veri | APP_KEY, runtime DB_PASSWORD, migration-owner password ve diğer ilgisiz ortam değerleri aynı. Kök `.env` ve 18733 özel dosya hash'leri aynı; üç dosyanın ACL/owner bilgisi aynı. 18734'te migration/seed/reset yapılmadı. |
| 18731 / 18733 | Her ikisi `/ready` **200**. 18731 PID ve başlangıç anı aynı; 18733 app/DB container kimliği, başlangıcı, image/env hash'leri ve volume bağları aynı. Restart/config-cache reset uygulanmadı. |
| Canlı generation | **NOT TESTED.** Gerçek `/threads/messages`, thread oluşturma veya remote DELETE/PUT yok. Canlı smoke komutu çalıştırılmadı. Kredi/bakiye değişimi **NOT CLAIMED**. |

### Güvenlik ve API kapsamı

Resmî [create](https://docs.backboard.io/api-reference/assistants/create), [exact-name list](https://docs.backboard.io/api-reference/assistants/list), [get](https://docs.backboard.io/api-reference/assistants/get), [documents](https://docs.backboard.io/api-reference/assistants/list-documents), [threads](https://docs.backboard.io/api-reference/assistants/list-threads), [memory stats](https://docs.backboard.io/api-reference/memories/stats) ve [model catalog](https://docs.backboard.io/api-reference/models/list) sözleşmeleri okundu. Authenticated işlem toplamı **altı GET + tek assistant-create POST**; normal Windows TLS doğrulaması, redirects kapalı ve sınırlı timeout ile yapıldı. Anahtar sadece kök `.env` dosyasından bellekte okundu. Response, header, UUID ve özel prompt içerikleri evidence/Git/loglara alınmadı.

Assistant yalnız aggregate policy replay açıklaması için oluşturuldu; araçlar boş, policy authorization ve gerçek işlem yetkisi yasaklandı. Resmî create şeması assistant genelinde memory/search kapatma bayrağı sunmuyor. Bu nedenle genel ve kalıcı provider kapatması iddia edilmiyor: boş assistant bağlamı doğrulandı; mevcut adapter her mesajda `memory=off`, `web_search=off`, `tools=[]`, streaming/media kapalı gönderiyor. Bu kontroller provider operasyon loglarının saklanmadığını kanıtlamaz. Windows PHP'nin genel CA/INI ayarına dokunulmadı; Windows güvenilir TLS yolu discovery için, 18734'ün mevcut CA paketi runtime için kullanıldı. Yeni container'dan anahtarsız public TLS probu **cURL 0 / HTTP 200 / doğrulama açık** döndü.

Özel rollback kopyası **CurrentUser DPAPI** ile şifreli, yalnız mevcut kullanıcı/SYSTEM/Administrators erişimli LocalAppData klasöründe tutuluyor. Klasör: `C:/Users/merth/AppData/Local/ScopeTwin-Backboard-Setup-20261018-233057-ed63377e`; environment yedeği `rehearsal-env-before.dpapi`, işlem durumu `state.dpapi`. Plaintext env yedeği yok. Yeni ayar mevcut dosyaya ACL korunarak yazıldı; Compose model çıktısı yalnız bellekte denetlendi. Kök env topluca kullanılmadı. Compose sadece açık private `--env-file`, sabit project/file, `up -d --no-deps --force-recreate --no-build --pull never app` kapsamıyla çalıştırıldı. `down -v`, owner DB migration, `migrate:fresh`, `db:wipe` veya reseed yok.

İlk config kontrolü, PowerShell/.NET'in null env atamasını boş süreç değişkeni olarak bırakıp Compose dosya değerini gölgelemesini yakaladı; dosya geri alındı. Süreç env girdilerini gerçekten kaldıran yöntemle düzeltildi. İlk recreation doğrulaması da otomatik geri dönüş yaptı; o ilk kontrolün ayrıntılı nedeni tutulmadı. Sonraki koşu, JSON'un başlangıç zamanını DateTime'a çevirmesi nedeniyle yanlış alarm verdi; PID ve gerçek UTC başlangıç anı aynıydı. Karşılaştırma UTC ticks ile düzeltildi; mount alanları sıralamadan bağımsız denetlendi ve production paket kontrolünde dev-only PHPUnit assertion bağımlılığı kaldırıldı. Son recreation ve tüm final koruma kontrolleri geçti. Bu geçici betik sorunları uygulama arızası veya veri kaybı olarak yorumlanmıyor; önceki image/env güvenli geri dönüşleri korundu. Uzak assistant POST'u hiçbir aşamada tekrarlanmadı.

### Bu görevde yeniden çalıştırılan testler

| Kontrol | Gerçek sonuç / evidence |
| --- | --- |
| SQLite `php artisan test --compact` | **178 PASS / 1367 assertion**, **8.44s**; `storage/backboard-setup-sqlite.txt` |
| PostgreSQL `scripts/test-postgres.ps1` | **178 PASS / 1367 assertion**, **36.21s**, yalnız `scopetwin_test`; `storage/backboard-setup-postgres.txt` |
| Migration / yarışlar | Disposable PG'de üç migration rollback/reapply PASS; idempotency, approval, eşzamanlı policy değişimi ve üç advisor kota/cache yarışı PASS. Provider çağrıları tamamen **mock**; gerçek AI/thread yok. |
| Chromium tam uygulama | **4 PASS**, **1.1 dakika**; ayrı 18732 / `scopetwin_browser_test`. Advisor fallback/mocked UI, CSRF, kayıt/login/API, policy, replay ve approvals. `storage/backboard-setup-browser.txt` |
| Yeni 18734 public tur | **3 PASS**, **14.4s**, Chromium/Firefox/WebKit; masaüstü/mobil, CSP, axe ve klavye. `storage/backboard-setup-release-public.txt`. Windows WebKit gerçek link Tab sınırlaması önceki gibi kayıtlı; programatik focus kontrol edildi. |
| Paket guard | BUILD 03.6 image'ı ayrı `--network none` konteynerde disabled/missing/malformed/valid assistant config durumlarını doğru döndürdü; yalnız mock değerler, DB/HTTP yok. |
| Gerçek 18734 kapalı advisor | Mevcut sentetik fixture readonly transaction'da doğrulandı; `advise` sonucu disabled, HTTP kayıt sayısı sıfır, kota/cache yazımı yok. API/UUID/APP_KEY/DB_PASSWORD cache eşitliği özel hash karşılaştırmasıyla doğrulandı. |
| TypeScript / ESLint / Prettier / Pint | **PASS**, kaynak kodunda düzeltme gerekmedi. |
| Vite build | **PASS, 3.52s**, 2027 modül; ayrı özel output klasöründe production build. Çalışan 18731'in `public/build` dosyaları değiştirilmedi. Sonuç `storage/backboard-setup-build.txt` içinde. |
| Composer / npm / source secrets | Strict Composer valid; Composer advisory **0**, npm vulnerability **0**. **304 kaynak yolu** secret-pattern ve gerçek özel key/assistant/ortam değerleri için ek literal tarama **PASS**; doküman diff whitespace kontrolü **PASS**. Private env dosyalarının Git ignore durumu doğrulandı. Pattern taraması bağımsız güvenlik denetimi değildir. |
| Çalışan app image Trivy | Çalışan 18734 image'ının ayrı export'u tarandı: **0 HIGH / CRITICAL**, **2026-10-18 20:42:35Z**. Ignore/suppression yok. `storage/backboard-setup-scan-app.json`, `storage/backboard-setup-container-scan.txt`. Değişmeyen DB image'ı yeniden taranmış sayılmadı. |
| Public güvenlik / log | Dört stateless public yol 200/cookie yok; dört private yol 404; forged Host 400; public POST 405; CSP PASS. Public HTML ve yeni container logları gerçek private key/UUID/ortam sırlarıyla eşleşmiyor. |
| Veri ve servis koruması | Legacy guard: kök `.env` ve **183 orijinal domain kaydı MATCH**. 18733'ün **21 tablosu MATCH**. Recreation hemen sonrasında 18734'ün **23 tablosu MATCH**; public tur sonrasında **22 tablo MATCH**, yalnız framework `cache` hash'i değişti, satır sayısı **76 → 76**. API 401 testi throttle middleware'den geçtiği için framework cache yazımı beklenir; alan/advisor/oturum verisi değişmedi. Cache elle silinmedi veya geri yazılmadı. |

Özel runtime ayarı ve dokümanlar dışında uygulama kaynak değişikliği veya yeni migration gerekmedi. Mevcut 18734'e kayıt/login gibi yazımlı release testleri uygulanmadı; tam iş akışları disposable ortamda çalıştı. Frontend build özel output'a yönlendirildi. Testler sırasında gerçek Backboard anahtarı test runner'lara verilmedi; `Http::preventStrayRequests` ve faked HTTP sınırları korundu.

### Kalan izin ve sınırlar

**Hazır olan:** dedicated/boş assistant, doğru özel binding, seçilmiş katalog modeli, BUILD 03.6 paketi, Docker CA, geçerli sentetik fixture, boş advisor kota/cache, üç/gün ve bir/dakika sınırları, kapalı güvenli fallback. Teknik hazırlık tamamlandı.

**Hazır olmayan/kanıtlanmayan:** gerçek generation başarısı/yanıt şeması, açıklama kalitesi, output filtresinden geçiş, gecikme, gerçek fatura/bakiye ve canlı thread cleanup. Katalog kaydı bunları kanıtlamaz. Çağrı kotası dolar tavanı değildir; timeout ücretlenebilir. Sponsor kredi/uygunluk konusu hâlâ doğrulanmadı.

**Sonraki tek adım ayrı izin gerektirir:** yalnız 18734'te geçici enable/recreation ile **bir sentetik generation**, API'nin bunun için açacağı **thread** ve adapter'ın **best-effort DELETE cleanup** işlemi birlikte açıkça onaylanmalı; bakiye/kullanım özel olarak gözden geçirilmeli. Mevcut onay bunları kapsamıyor. Bu izin gelene kadar BACKBOARD_ENABLED=false korunur; canlı smoke çalıştırılmaz. GitHub push, uzak deployment, DNS veya hizmet satın alma yapılmadı.

## 2026-10-20 yeniden doğrulama — aynı assistant, yeni uzak yazım yok

Tekrarlanan görev temiz `4f76bbc` üzerinden, aynı `feat/build-03-6-backboard-connectivity` dalında ele alındı. `5186390` uygulama tabanı ve önceki başarılı kurulum belgeleri korundu. Mevcut iş zaten tamamlandığından yeni assistant oluşturmak veya private ayarları yeniden yazmak gerekmedi.

### Güncel sonuçlar

| Soru | 2026-10-20 doğrulaması |
| --- | --- |
| Assistant var mı? | **EVET.** Resmî tam isim filtresi HTTP 200 ile **tek** ScopeTwin Replay Advisor döndürdü. Kimliği önceki şifreli kurulum kaydı ve mevcut özel 18734 ayarıyla eşleşti; UUID sohbet/Git/log/evidence'e yazılmadı. Bugünkü assistant POST sayısı **0**; önceki tek assistant yeniden kullanılıyor. |
| İzolasyon | Ayrı detail/docs/threads/memory-stats GET'leri HTTP 200: beklenen isim/talimatlar, **0 araç / 0 belge / 0 thread / 0 memory**. Hassas/default assistant kullanılmadı; hiçbir uzak kaynak değiştirilmedi/silinmedi. |
| Model | Provider filtreli güncel katalog HTTP 200: **featherless / openai/gpt-oss-20b**, text/JSON desteği true. Girdi **USD 0.0375**, çıktı **USD 0.15 / 1M token**. Gerçek generation erişimi/yanıtı/maliyeti sınanmadı. |
| Özel yapılandırma | Üç private env dosyasının hash, ACL ve owner bilgisi önceki kurulum kaydıyla aynı. Kök anahtar ile 18734 key eşitliği, özel assistant binding, provider/model, BUILD 03.6 image seçimi ve ilgisiz tüm ortam değerleri doğrulandı. **BACKBOARD_ENABLED=false**; hiçbir private dosya yazılmadı. |
| 18734 | Mevcut **scopetwin:build036** container'ı **healthy**, `/ready` **200**. Container oluşturulma zamanı **2026-10-18 20:38:17Z**; bugün recreation/image/env güncellemesi yapılmadı. Boot cache key/UUID/APP_KEY/DB_PASSWORD eşitlikleri özel hash kontrolüyle doğrulandı. |
| 18733 | Başlangıçta durmuştu; onaylanan Docker Desktop başlangıcında mevcut restart politikasıyla otomatik açıldı. **healthy / ready 200**. Container kimliği/image/env hash/mount bilgisi aynı; manuel recreation/config işlemi yapılmadı. |
| 18731 | Görev başlangıcında dinleyici yoktu, `/ready` erişilemiyordu. Dokunmama koşulu ve ek onay kapsamı gereği host uygulaması başlatılmadı; **şu anda kapalı**. Sağlıklı veya today-ready olduğu iddia edilmiyor. |
| Generation/krediler | Altı authenticated **GET**, **0 POST/PUT/DELETE**. Gerçek AI isteği, thread oluşturma, remote silme ve canlı smoke yok. **LIVE_NOT_TESTED / CREDITS_NOT_CLAIMED**. |

Docker Desktop/engine ve üç port ilk ölçümde kapalıydı. Kullanıcıya, Docker başlangıcının 18733/diğer projeleri restart politikalarıyla açabileceği açıklandı; kullanıcı **Docker Desktop'ı başlat** yanıtıyla bunu ayrıca onayladı. İlk CLI başlangıç bildirimi engine hazır olduğu anlamına gelmedi: bounded salt okunur problarda pipe/timeout hataları görüldü. Sonraki başlatma/başlangıç tamamlanmasıyla engine ve mevcut 18733/18734 servisleri hazır hale geldi. WSL salt okunur liste sorgusu başarılıydı; WSL update, factory reset, shutdown, unregister, disk onarımı, container/volume silme veya secret değişikliği yapılmadı. Günlükteki başlangıç uyarıları veri bozulması olarak yorumlanmadı.

### Bugün çalıştırılan testler

| Kontrol | Gerçek sonuç |
| --- | --- |
| SQLite | **178 PASS / 1367 assertion**, **23.92s**; `storage/backboard-recheck-20261020-sqlite.txt` |
| PostgreSQL | **178 PASS / 1367 assertion**, **34.60s**; `storage/backboard-recheck-20261020-postgres.txt` |
| PG migration ve concurrency | Üç migration rollback/reapply, idempotency, approve/reject, stale policy ve üç advisor quota/cache yarışı **PASS**. Yalnız ephemeral test DB; upstream tamamen mock. |
| Chromium tam workflow | **4 PASS / 1.2 dakika**, ayrı 18732 ve disposable browser DB; `storage/backboard-recheck-20261020-browser.txt` |
| Mevcut 18734 public tur | **3 PASS / 15.3s**; Chromium/Firefox/WebKit, masaüstü/mobil, axe/CSP/focus; `storage/backboard-recheck-20261020-release.txt`. Windows WebKit gerçek Tab sınırlaması aynen kayıtlı; bu durumda programatik focus doğrulanır. |
| Kapalı gerçek advisor | Mevcut sentetik aggregate readonly transaction'da **PASS**; advise disabled, **0 HTTP / 0 kota veya açıklama yazımı**. Advisor kota ve açıklama tabloları hâlâ **0 kayıt**. |
| Paket assistant guard | BUILD 03.6 image'ında ağsız ayrı container: disabled/missing/malformed/valid config durumları **PASS**; yalnız mock değerler, DB/HTTP yok. |
| TLS / public güvenlik | 18734 public Backboard TLS probu **cURL 0 / HTTP 200**, doğrulama açık. Dört public yol 200/cookie yok, dört private yol 404, forged Host 400, public POST 405, CSP PASS; public HTML/container loglarında gerçek sır/assistant kimliği literal taraması PASS. |
| Frontend / stil | TypeScript, ESLint, Prettier, Pint **PASS**. İzole özel output'a Vite production build **PASS / 10.70s / 2027 modül**; mevcut `public/build` değiştirilmedi. |
| Bağımlılık denetimleri | Strict Composer valid, Composer advisory **0**, npm vulnerability **0**. |
| Çalışan app image Trivy | Güncel vulnerability DB indirilerek çalışan 18734 image export'u tarandı: **0 HIGH / CRITICAL**, **2026-10-20 17:11:01Z**; suppression yok. `storage/backboard-recheck-20261020-scan-app.json`. DB image'ı yeniden taranmış sayılmadı. |

Orijinal geliştirme PostgreSQL container'ı durmuştu; test için başlatılmadı. PostgreSQL ve browser suite, yerel mevcut PostgreSQL image'ı kullanan **yeni ve geçici bir Compose projesi** üzerinde çalıştı: rastgele boş loopback portu, yeni test-only credential, **tmpfs depolama**, ayrı test DB'leri. Test bitiminde yalnız bu geçici container/network kaldırıldı; mevcut hiçbir volume kullanılmadı/silinmedi. `down -v` kullanılmadı. Test secret'ı yalnız işlem belleği/container env içindeydi, gerçek Backboard credential test runner'a verilmedi.

Veri koruma: 18733'ün **21 tablosu** önceki hash/sayılarla aynı; 18734'ün **framework cache dışındaki 22 tablosu** aynı. Framework cache, önceki ve bugünkü unauthenticated ingress throttle güvenlik testleri nedeniyle değişik; satır sayısı **76 → 76**. Alan, replay, approval, advisor, oturum ve diğer kalıcı tablolar aynı. Cache elle temizlenmedi/geri yazılmadı. Orijinal geliştirme DB'sinin container/image/env/mount kimlikleri korundu, fakat sunucu kapalı bırakıldığı için **183 kayıt SQL guard'ı bugün yeniden çalıştırılmadı**; 2026-10-18 PASS sonucu güncel çalıştırma olarak sunulmadı.

Final source/privacy gate: **304 kaynak yolu** configured secret-pattern ve gerçek private key/assistant/ortam sırları için literal taramadan geçti. Değişen dokümanlarda whitespace kontrolü PASS; üç private dosya Git tarafından ignore ediliyor ve hash/ACL/owner değerleri aynı. Bugün yalnız dört mevcut doküman güncellendi; uygulama kodu/private yapılandırma değiştirilmedi.

**Tek canlı teste hazırlık:** isolated 18734 tarafında teknik hazırlık doğrulandı ve yeni config/recreation gerekmedi. Gerçek response kalitesi/şeması, süre, ücret ve canlı cleanup hâlâ bilinmiyor. Memory/search için assistant genelinde belgelenmiş hard-disable yok; request-level off kontrolleri ve boş bağlam doğrulaması geçerli. Sponsor uygunluğu/bakiye kanıtlanmadı. Geçici enable, bir generation, implicit thread ve best-effort remote DELETE için önceki ayrı izin gereksinimi aynen sürüyor. **Generation yapmadan duruldu.** 18731'in ayrıca başlatılması bu görevde yapılmadı; talep edilirse ayrı yerel çalışma adımıdır.
