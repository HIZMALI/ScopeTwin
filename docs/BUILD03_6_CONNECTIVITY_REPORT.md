# BUILD 03.6 — Bağlantı ve açık assistant bağlama raporu

Tarih: 2026-10-18. Taban `fea6281`; yerel dal `feat/build-03-6-backboard-connectivity`. Başlangıçta yalnızca iki izlenmeyen talimat dosyası vardı. Kullanıcı bunları koruyarak yeni dala devam edilmesini açıkça onayladı. Dosyaların içeriği değiştirilmedi. Bu çalışma AI üretimi veya uzak assistant/thread yazımı/silmesi yapmadı. Push, deployment, satın alma, promosyon işlemi, mevcut servis recreation veya özel ortam/ACL değişikliği yapılmadı.

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
