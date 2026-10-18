# BUILD 03.5 — Backboard teknik teslim raporu

Tarih: 2026-10-18. Başlangıç commit'i `643497d`; yerel dal `feat/build-03-5-backboard-advisor`. Kullanıcının BUILD 03.5 talimatı tamamen okundu; yeni dal açık talimatla oluşturuldu. Push, uzak deployment, satın alma, DNS veya üçüncü taraf hesap değişikliği yapılmadı. BUILD 04A başlatılmadı.

**Yerel uygulama ve sahte HTTP ile doğrulama tamamlandı. Canlı sağlayıcı: LIVE_NOT_TESTED. Model: MODEL_NOT_SELECTED.** Gerçek Backboard anahtarı/model kullanılmadı ve kredi harcanmadı. Partner promosyonunun hesapta pasif göründüğü sorunu çözülmedi; sıradan başlangıç kredisi uygunluk veya partner kodu kullanımı kanıtı değildir.

## Uygulanan davranış

- Replay raporunda isteğe bağlı AI Replay Advisor, açık kullanıcı tıklamasıyla Explain with AI, yükleniyor/hata/kota/kapalı durumları, deterministik açıklama ve zorunlu AI atfı. Navigasyon otomatik üretim yapmaz; sayılar mevcut deterministik motor/veriden gelir. Çıktı React metin kaçışından geçer; HTML çalıştırılmaz.
- Kimlikten çalışma alanı çözen, OWNER/ADMIN ile sınırlı, session/CSRF korumalı POST. Başka çalışma alanı/bilinmeyen replay 404; VIEWER 403. İstemci prompt/ayar/organization alanları reddedilir. Public turda sağlayıcı çağrısı veya GET yazımı yoktur.
- Sabit HTTPS uç noktasına Laravel HTTP adaptörü; sunucu dışına çıkmayan anahtar; varsayılan kapalı ayrı config. Gönderilen içerik yalnızca doğrulanmış, boyutu sınırlı simülasyon toplu sayılarıdır. Kimlikler, tutar/para birimi, kişisel veri, örnek girdiler ve politika tanımları gönderilmez.
- Memory/search/tools/stream/image/video kapalı; JSON istenir. İki–üç İngilizce cümle ve en fazla iki insan inceleme sorusu, toplam en fazla 900 ASCII görünür karakter. Sayı/markup/URL ve belirli işlem/garanti ifadeleri reddedilir. LLM hiçbir yetki veya karar vermez.
- Kullanıcı ardından organizasyon kilidiyle kısa DB rezervasyonu; HTTP kilit dışında. En fazla üç yeni üretim denemesi/organizasyon/UTC günü, bir/kullanıcı/kayan 60 saniye. Ayarlar yalnızca azaltabilir. Başarısız denemeler de sayılır. Frozen içerik/model/prompt sürümü ve organizasyon kapsamlı kısa önbellek; aynı anda aynı içerik için tek üretim. Timeout otomatik tekrar edilmez.
- Geçerli thread kimliği döndüğünde `finally` temizleme DELETE'i; başarısız/kanıtlanamayan temizlik açıkça belirtilir. Kayıtlar yalnızca sınırlı durum sınıfı ve süre içerir. İki yeni additive advisor tablosu mevcut alan kayıtlarını değiştirmez.
- İki açık bayrak gerektiren, yalnızca sabit sentetik demo veritabanında çalışabilen manuel canlı smoke komutu. Ücret onaylı komut bu çalışmada çalıştırılmadı.
- Ayrı BUILD 03.5 image/Compose projesi/volume/18734 portuyla üretim provası; 18733 BUILD 03 korunur. Kurulum, gizlilik, sağlayıcı saklama, kota ve Devpost doğruluk belgeleri güncellendi.

## Değişen dosyalar

| Alan | Dosyalar |
| --- | --- |
| Yeni backend | `app/Services/ReplayAdvisor.php`, `app/Http/Controllers/ReplayAdviceController.php`, `app/Console/Commands/BackboardSmoke.php`, `config/backboard.php` |
| Migration | `database/migrations/2026_10_18_000003_create_replay_advisor_tables.php` |
| Entegrasyon / kayıt | `routes/web.php`, `app/Http/Controllers/ReplayController.php`, `app/Logging/RedactSecrets.php` |
| React | `resources/js/components/replay-advisor.tsx`, `resources/js/pages/scope/replay.tsx` |
| Yeni testler | `tests/Feature/ReplayAdvisorTest.php`, `tests/scripts/advisor-concurrency.php`, `tests/browser/advisor.spec.ts` |
| Test altyapısı | `tests/TestCase.php`, `playwright.config.ts`, `playwright.release.config.ts`, `tests/release/launch.spec.ts`, `scripts/test-postgres.ps1` |
| Üretim / koruma | `.env.production.example`, `compose.production.yaml`, `scripts/rehearse-production.ps1`, `scripts/scan-containers.ps1`, `scripts/test-database-image.ps1`, `scripts/restore-smoke.ps1`, `scripts/security-smoke.mjs`, `scripts/load-smoke.mjs`, `scripts/verify-preservation.php` |
| Belgeler | `README.md`, `docs/ARCHITECTURE.md`, `docs/DEVPOST_SUBMISSION.md`, `docs/GO_LIVE_CHECKLIST.md`, `docs/BACKBOARD_INTEGRATION.md`, bu rapor, `docs/IMPLEMENTATION_LOG.md` |
| Korunan kullanıcı girdisi | `docs/BUILD03_5_BACKBOARD_CODEX_PROMPT.md` değiştirilmeden teslim kapsamına alındı |

## Gerçek test kanıtları

Tüm PHP testlerinde beklenmeyen dış HTTP engellenir. Sağlayıcı testleri sahte yanıtlarla yürütülür; browser başarı/HTML-kaçış fixture'ı gerçek Backboard sonucu olarak sunulmaz. Eski alan testleri ve üç browser senaryosu korunur; yalnızca test ortamının anahtar/URL bağımlılığı ve release hedefinin seçilebilirliği düzeltildi.

| Komut / kontrol | Gözlenen sonuç |
| --- | --- |
| `php artisan test --compact` / SQLite bellek DB | **169 test / 1291 assertion PASS**, 8.51 saniye |
| `./scripts/test-postgres.ps1` / `scopetwin_test` | **169 test / 1291 assertion PASS**, 30.95 saniye |
| Migration rollback/reapply | Üç alan migration'ı yalnızca disposable test DB'de geri/ileri PASS |
| Eski PostgreSQL yarışları | Aynı/farklı idempotency, REVIEW retry, approve/reject ve policy-change yarışı PASS |
| Yeni PostgreSQL yarışları | Altı aynı istek → tek mocked üretim; altı yönetici/farklı içerik → üç üretim + üç kota reddi; tek kullanıcı/altı içerik → bir üretim + beş ret PASS |
| `npm run test:browser` | Dört Chromium senaryosu PASS, 1.4 dakika; eski üç yol + advisor kapalı/fallback, gerçek CSRF, mocked yüklenme/başarı/kaçış/kota, değişmeyen replay ve mobil taşma kontrolü |
| `RELEASE_TEST_URL` 18734 ile `npm run test:release` | Altı senaryo PASS, son koşu 1.0 dakika; Chromium/Firefox/WebKit, gerçek production HTTP/API, masaüstü/mobil, CSP/klavye/dialog ve axe |
| `npm run types:check`, `npm run build` | PASS; gerçek Vite production bundle üretildi |
| `npm run lint:check`, `npm run format:check`, `vendor/bin/pint --test` | PASS |
| `composer validate --strict`, `composer audit`, `npm audit` | Geçerli Composer; Composer advisory 0; npm vulnerability 0 |
| `./scripts/rehearse-production.ps1 -Build035` | Ayrı üretim paketi 18734 `/ready` 200; root olmayan UID 33; debug false; owner sırrı runtime'da yok; DB superuser/createDB/createRole false |
| `node scripts/security-smoke.mjs` / 18734 | Özel dosya yolları 404, sahte Host 400, CSRF 419, HttpOnly/SameSite=Lax, demo cookie 0 |
| `node scripts/load-smoke.mjs` / 18734 | 40 istek, concurrency 4, hata 0; p50 27.88 ms / p95 38.16 ms / max 61.01 ms; 19:35:19Z yerel ölçüm, SLA değildir |
| `./scripts/scan-containers.ps1 -Build build035` | Uygulama 19:34:54Z ve DB 19:35:06Z image taramaları HIGH/CRITICAL 0; Trivy, bastırma/ignore eklenmedi |
| `./scripts/test-database-image.ps1 -Build build035` | Yeni izole PostgreSQL başlatma ve SELECT 1 PASS; port yayınlanmadı |
| `./scripts/restore-smoke.ps1 -Build035` | Yeni disposable hedefte **15 tablo** sayısı/hash'i eşit PASS |
| `./scripts/check-secrets.ps1`, `git diff --check` | 301 kaynak yolu secret-pattern ve whitespace kontrolleri PASS; bağımsız sızma testi değildir |
| Veri koruması | `.env` hash'i aynı; mevcut PostgreSQL `scopetwin` içindeki **183 orijinal alan kaydı** aynı; mevcut SQLite'ta alan kaydı yok; 18733 `/up` 200 |
| Gerçek Backboard | **LIVE_NOT_TESTED**, gerçek çağrı/harcama/başarı iddiası yok |

Özel yerel çıktılar Git dışında `storage/build035-{sqlite,postgres,browser,release,production,container-scan}.txt`, güvenlik/yük/restore/scan JSON'ları ve `storage/screenshots/build035-advisor-mocked-ui.png` içindedir. Mobil görüntü açılıp incelendi; görüntüde kota fallback'i ve değişmeyen deterministik sayılar vardır. Anahtar gösterilen test aşamalarında screenshot/trace alınmaz. Console/page error ve tarayıcıdan doğrudan sağlayıcı isteği kontrolleri de yapılır. Bu kontroller her olası sırrın yokluğunun matematiksel kanıtı değildir.

Teslim kapsamı 35 dosyadır. `.env`, veritabanları, özel ortam dosyaları, loglar, image arşivleri, screenshot'lar, anahtarlar ve üretilmiş bundle Git'e alınmaz. Yerel feature commit'inin ebeveyni `643497d` olarak korunur; kesin commit kimliği Git geçmişinde ve kullanıcıya teslim mesajındadır.

## Çalışma sırasında bulunan ve düzeltilen hatalar

1. İlk SQLite regresyonu 72 hata verdi: mevcut `.env` uygulama anahtarı içermiyordu. Kullanıcı dosyasını değiştirmeden test başına rastgele geçici anahtar ve sabit URL verildi. URL generator kökü de test URL'sine sabitlendi; production host kontrolleri zayıflatılmadı.
2. İlk PostgreSQL koşusunda yeni migration sırası yanlıştı: advisor tabloları organization'dan önce geliyordu. Yeni migration `000003` olarak düzeltildi. SQLite'ın kaçırdığı gerçek FK sırası sorunu PostgreSQL'de doğrulandı.
3. Bir test çağrısındaki dış APP_ENV=local ayarı test CSRF muafiyetini kaldırdı. Son PostgreSQL koşuları yalnızca test sürecinde APP_ENV=testing ile çalıştırıldı. Gerçek CSRF davranışı ayrı production-mode PHP/browser kontrollerinde sınandı.
4. ESLint yeni Node scriptinde eksik `node:process` import'unu buldu; düzeltildi. Kota rolling 60 saniyeye sıkılaştırılıp dakika sınırı testi eklendi. Sonuçlar son kodla tekrar alındı.
5. Son incelemede input reddinin yetkilendirme öncesinde olması düzeltildi: yabancı replay veya VIEWER hatalı gövde gönderdiğinde de önce 404/403 döner; yalnızca yetkili istek 422 input doğrulamasına ulaşır. İki negatif assertion eklendi; son iki DB koşusu 1291 assertion ile geçti.

## Kalan sınırlamalar ve operatör adımları

- **Kullanıcı ortamı başlangıçta eksikti:** mevcut `.env` production/SQLite seçiyor, APP_KEY ve ScopeTwin alan şeması bulunmuyordu. Bu dosya, anahtar veya parola yeniden üretilmedi. PostgreSQL geliştirme verisi okunarak 183 kayıt korundu; test bağlantı bilgileri yalnızca süreç belleğinde kullanıldı. Kullanıcı DB'sine DDL/DML uygulanmadı. Özelliği kendi kurulumunda açmadan önce doğru mevcut bağlantıyla normal ileri migration prosedürünü uygulamalı; mevcut anahtar/şifreyi korumalıdır. Çalışan UI/API ayrı disposable ortamlarda doğrulandı; 18731'de eksik yerel ayarlarla çalışır uygulama iddiası yoktur.
- Canlı doğrulama için anahtar özel olarak oluşturulmalı, belgelenen authenticated katalog/dashboard üzerinden gerçekten kullanılabilir JSON text modeli ve güncel ücretler doğrulanmalı, hesap sahibi **bir çağrı için açık harcama izni** vermelidir. Varsayılan model seçilmedi. Ücretli smoke bu izin olmadan çalıştırılmaz.
- Özel değişken adları: `BACKBOARD_ENABLED`, `BACKBOARD_API_KEY`, `BACKBOARD_LLM_PROVIDER`, `BACKBOARD_MODEL_NAME`, `BACKBOARD_TIMEOUT_SECONDS`, `BACKBOARD_DAILY_CALLS`, `BACKBOARD_USER_CALLS_PER_MINUTE`, `BACKBOARD_CACHE_SECONDS`. Tam özel kurulum ve komut prosedürü BACKBOARD_INTEGRATION.md içindedir. Anahtarı sohbet/CLI argümanı/Git'e koymayın.
- Provider ilk assistant bağlamını kullanabilir; temiz özel hesap/proje ve varsayılan assistant kontrolü gerekir. Thread silme provider logları, yedekleri veya assistant'ın silinmesi garantisi değildir. Timeout/bozuk yanıt/eksik ID ile orphan thread kalabilir; dashboard'dan özel inceleme gerekir.
- Kota bir parasal limit değildir; başarısız/timeout çağrı ücretlenebilir. Cache/lease sona erdikten sonraki açık yeni tıklama yeniden çağırabilir; mutlak remote exactly-once garantisi yoktur. Otomatik retry yoktur.
- Yeni tabloların TTL'i otomatik veri silme değildir; retention scheduler eklenmedi. SQLite sıra dışı yazma yarışlarında busy hatası verebilir; üretim hedefi PostgreSQL'dir. Sıkı çıktı doğrulama yararlı yanıtları reddedebilir ve anlam güvenliği garantisi değildir.
- Loopback HTTP provası gerçek TLS/secure-cookie/proxy dağıtımını doğrulamaz. Windows WebKit link Tab dolaşımı önceki ortam sınırlamasını korur; programatik skip focus orada, gerçek Tab Chromium/Firefox'ta kontrol edilir. Bağımsız güvenlik/erişilebilirlik sertifikası veya gerçek kapasite garantisi yoktur.
- Galuxium partner promosyonu **çözülmedi**. Hesap sahibi organizatörle doğrulamalıdır; başlangıç kredisi yeterlilik kanıtı sayılmaz. Devpost/video için gerçek AI başarısı varmış gibi materyal kullanılmamalıdır.

Önerilen sıra: mevcut özel yerel yapılandırmayı sahibiyle doğrulama; istenirse yetkili tek sentetik canlı smoke; asıl 2–5 dakikalık demo videosu; açık izinle public GitHub ve HTTPS deployment. Bu teslim bunları gerçekleştirmez ve BUILD 04A'yı otomatik başlatmaz.
