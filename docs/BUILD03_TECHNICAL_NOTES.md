# ScopeTwin BUILD 03 — teknik teslim raporu

Tarih: 18 Ekim 2026. Çalışma dizini: `C:\Projects\ScopeTwin`. Başlangıç: BUILD 02 `e495b78`; yerel dal: `feat/build-03-launch`. Kullanıcının iki yeni BUILD 03 talimat dosyası korunmuştur. **Uzak yayın yoktur: NOT DEPLOYED.**

## Kapsam durumu

| Öncelik | Durum | Gerçekte teslim edilen |
| --- | --- | --- |
| P0-A | DONE — yerel doğrulama | Üretim ayarları/başlangıç korumaları, nonce CSP, güvenlik/cache başlıkları, proxy/host sınırı, kayıt/kurtarma throttling, dürüst mail durumu, readiness ve operasyon kuralları |
| P0-B | DONE — yerel doğrulama | Gerçek çok aşamalı Docker paketi, root olmayan sunucu, ayrı DB rolü/servisi, kalıcı volume, manuel migration, izole üretim HTTP/UI provası |
| P0-C | DONE — yerel doğrulama | İngilizce React landing ve gerçek kalıcı sentetik kanıtı okuyan salt okunur jüri turu; uzak URL hâlâ yayınlanmadı |
| P0-D | DONE — aşağıdaki kanıtlarla | Eski testler korunarak iki DB regresyonu, concurrency, üç tarayıcı, axe, güvenlik/ölçek/yedek-restore ve veri koruma kontrolleri |
| P0-E | DONE — yerel teslim materyalleri | README, mimari/ürün güncellemeleri, deployment/runbook, Devpost metni, 3:15 storyboard/SRT, gerçek ekran görüntüleri ve go-live listesi |
| P1 | DEFERRED | İsteğe bağlı LLM/Backboard adaptörü yapılmadı; sağlayıcı kullanımı/ücretli çağrı yetkisi verilmedi. Deterministik açıklamalar mevcut. |
| P2 | PARTIAL / DEFERRED | Güvenli ekran yakalama betiği var. Yeni CI/ücretli kota/billing sistemi eklenmedi; GitHub CI uzakta çalıştırılmadı. |

DONE ifadesi uygulama ve **yerel** paket doğrulamasını anlatır. Gerçek hosting, HTTPS, DNS, public GitHub, video ve Devpost gönderimi ayrı dış yayın kapılarıdır; bunların yapılmış olduğu iddia edilmez.

## Uygulama ve dosya haritası

- `routes/public.php`, `PublicDemoController`, `resources/views/public.blade.php`, `resources/js/public.tsx`, `resources/css/public.css`: stateless React landing, `/demo`, `/demo/replay`, ayrı `/ready`. Public GET/HEAD dışında yazma handler'ı yok. Session/auth/cache-throttle middleware'i bu sayfalarda çalışmaz; GET cookie veya domain/cache/session kaydı üretmez.
- `config/demo.php`, `SetupJudgeDemo`: kapalı varsayılan demo; sabit rezerve workspace UUID + slug; yalnızca açık `DEMO_ENABLED`, `DEMO_ALLOW_SETUP`, `--confirm-disposable` ve uygun ayrı DB adıyla kurulum. İlk kurulum mevcut kullanıcı/workspace içeren DB'yi reddeder. `scopetwin` reddedilir. Tekrar çalıştırma mevcut demo kayıtlarını değiştirmez.
- Kurulum gerçek `EvaluateAction`, `ReplayService`, `ApprovalService` kullanır. 3 action/decision, 3 frozen replay sonucu, 2 değişiklik/2 yeni DENY, documentary APPROVED kaydı oluşur. Rastgele API anahtarı gösterilmeden atılır ve sonunda iptal edilir. Public projection kullanıcı/anahtar/PII/tenant kimliği/idempotency alanlarını dışarı vermez; URL/query üzerinden tenant seçilemez, sonuçlar 3 ile sınırlıdır.
- `SecurityHeaders`, `ConfiguredProxies`, `config/security.php`, bootstrap/provider/session/auth değişiklikleri: script nonce CSP, frame/content-type/referrer/permissions başlıkları, private/no-store; yalnızca gerçek secure production isteğinde opt-in HSTS; üretimde Secure/HttpOnly/SameSite=Lax session varsayılanı. Stil inline izni mevcut React bileşenleri için korunur; script unsafe-inline açılmaz. Proxy varsayılanı güvenmez; gerçek adresler açıkça tanımlanır. Host APP_URL ile sınırlıdır.
- `RedactSecrets`: üretim exception metni/binding/trace/context yerine tip/seviye/zaman tutar; yerel iç içe hassas alanlar ve bearer/anahtar metinleri redakte edilir. Audit karar kimlikleri ve sınırlı metadata DB'de korunur. Ayrıntı azaltımı operasyon teşhisinde bilinçli bir sınırdır.
- Kayıt ve recovery POST 5/dakika/IP; mevcut login koruması korunur. Array/log mailer ile recovery başarı teslimi taklit edilmez. Gerçek taşıma + açık feature flag gerekir. Logout HTTP redirect sözleşmesi korunur; Inertia istemcisi çalışan login sayfasına yönlenir. Landing'e Inertia geçişi tam sayfa navigasyonuyla yapılır.
- `Dockerfile`, `deploy/Caddyfile`, `deploy/php.ini`, `deploy/entrypoint.sh`, `compose.production.yaml`, `.env.production.example`: digest-pinned Node/Composer/FrankenPHP girdileri, PHP 8.4.26, bundled Vite assets, UID 33, düşürülmüş capabilities, yalnızca public/ web root, dot/internal path engeli. Caddy admin/otomatik internal TLS/access log kapalı; gerçek TLS proxy operatör kapsamındadır. Başlangıç debug/ortam/URL/32-byte key biçimini kontrol eder; otomatik migrate/seed/key üretimi yoktur.
- Resmî builder ile Caddy/FrankenPHP'nin `golang.org/x/net` bağımlılığı v0.60.0'a güvenlik düzeltmesi olarak alınır. `deploy/postgres.Dockerfile` resmî PG 18 motorunu koruyup eski Go içeren kullanıcı değiştirme yardımcısını `su-exec` ile değiştirir. Sıfırdan init ve mevcut disposable volume üzerinde gerçek akışlar test edilir. Kullanıcının mevcut PostgreSQL servisi/imajı değiştirilmez.
- Runtime DB rolü yalnızca DML/sequence izinleri alır; superuser/role/database/schema create yoktur. Migration-owner parolası web container environment'ına verilmez. DB portu publish edilmez. Depolama ve DB volume'ları kalıcıdır.
- `scripts/rehearse-production.ps1`, `scan-containers.ps1`, `test-database-image.ps1`, `security-smoke.mjs`, `load-smoke.mjs`, `restore-smoke.ps1`, `capture-demo.mjs`, `check-secrets.ps1`, genişletilmiş preservation guard; `deploy/verify-runtime.php`, `benchmark.php`, `healthcheck.php`: yeniden üretilebilir paket/kanıt kontrolleri.
- Mevcut dashboard, policy editor, replay, approvals ve usage görünümü korunur; soluk metinler koyulaştırılır, scroll table'lara etiketli keyboard focus eklenir. Build/ortam etiketi gerçek konfigürasyonu gösterir. Yeni PHP test dosyası ve ayrı release Playwright konfigürasyonu eklendi; eski PHP/browser testleri silinmedi veya zayıflatılmadı.
- BUILD 03 için yeni domain migration gerekmedi: tüm demo/evidence verisi mevcut şemayı kullanır. Kullanıcı DB'sine DDL/DML uygulanmadı. İki eski domain migration rollback/reapply kontrolü yalnızca disposable `scopetwin_test` üzerinde çalıştı.

## Gerçek test kanıtı

Saatler UTC'dir; bu makinede çalıştırılmış komutların log/JSON çıktılarından alınmıştır. Yeniden üretim komutları README ve deployment belgesindedir. Ham güvenli yerel loglar ignored `storage/build03-*` altında tutulur; ortam dosyaları/dump/trace yayın paketine girmez.

| Kontrol | Gerçek sonuç | Ortam / zaman |
| --- | --- | --- |
| Değişiklik öncesi `php artisan test --compact` | 126 test / 924 assertion PASS | In-memory SQLite; BUILD 02 tabanı |
| Son SQLite suite | **142 test / 1031 assertion PASS**, 10,18 s | `storage/build03-sqlite.txt`; 17:48:32Z |
| `scripts/test-postgres.ps1` | **142 test / 1031 assertion PASS**, 24,10 s | Yalnızca `scopetwin_test`; 17:49:02Z |
| Disposable migration kontrolü | Eski iki domain migration rollback/reapply PASS | Kullanıcı DB'sinde rollback yok |
| PostgreSQL concurrency | 4 aynı retry → 1 karar; farklı input yarışı → 200+409; REVIEW retry → 1 approval; approve/reject → 200+409/1 audit; policy yarışı → STALE/409 | Gerçek PostgreSQL row lock'ları; orijinal REVIEW korunur |
| Değişmeyen browser regresyonu | **3/3 Chromium PASS**, yaklaşık 1 dakika | 18732 / `scopetwin_browser_test`; 17:41:04Z |
| Üretim release suite | **6/6 PASS**, 58,2 s; Chromium + Firefox + WebKit, 2 senaryo/tarayıcı | 18733, gerçek PostgreSQL, APP_ENV=production, built assets; son log `storage/build03-release-final.txt` |
| UI / axe | 1440 ve 390px; public landing/tour ve dashboard, policy list/editor, replay, approvals, usage; test edilen **48 axe taramasında 0 violation** | Sayfa JS hataları yok; public CSP violation yok; overflow yok |
| Keyboard/dialog | Chromium/Firefox gerçek Tab ile skip link; tüm motorlarda dialog adı, başlangıç focus, 5 Tab focus containment, Escape/cancel/confirm | Windows WebKit link Tab/Alt+Tab ilerlemez: orada programmatic skip focus, gerçek Tab kapsamı iddia edilmez |
| Üretim HTTP akışı | Register + logout/login; agent key bir kez; gerçek ALLOW/REVIEW/DENY; retry aynı 5-field response; conflict 409; replay 2/2; approval APPROVED / execution=false; tekrar resolve 409; usage 3/1/1 | Ayrı sentetik workspace'ler; `build03-http-evidence.json` |
| Production runtime | Debug off, UID root değil, built manifest var/hot yok/.env yok, migration sırrı yok, DB yüksek yetkileri yok | `php deploy/verify-runtime.php` PASS |
| Startup negatifleri | Debug açık ortam ve geçersiz encryption key başlangıçta exit 1 ile reddedildi | Hiç migrate/seed yapılmadan; `build03-startup-rejection.txt` |
| HTTP güvenlik probes | 6 private path →404; forged Host →400; cross-site CSRF POST →419; ready→200; HttpOnly/Lax session; demo cookie=0 | `node scripts/security-smoke.mjs`; loopback HTTP olduğu için Secure/HSTS dış doğrulaması yok |
| Ölçek / query plan | 10.000 sentetik karar: hedef 1.000 + yabancı 9.000; 500-item replay **7 SELECT / 390,99 ms**, changed=0; query organization/time index kullanır | `deploy/benchmark.php --confirm-disposable`; 17:52Z; küçük yerel ölçüm |
| Modest HTTP yük | 40 istek, concurrency 4, **0 hata**, p50 **32,70 ms**, p95 **42,81 ms**, max **63,17 ms** | Yalnızca 18733/demo/replay; 17:52:20Z; kapasite/SLA garantisi değil |
| Backup / restore | Custom-format dump yeni isolated DB'ye restore edildi; **13 domain tablosunda tüm count/hash eşit** | `scripts/restore-smoke.ps1`; 17:52:12Z; production restore/RPO garantisi değil |
| Frontend | TypeScript, Vite build, ESLint, Prettier PASS | Son komutlar `storage/build03-types/build/lint/format.txt` |
| PHP / bağımlılıklar | Pint PASS; `composer validate --strict` valid; Composer advisory yok; npm audit **0 vulnerability** | Gerçek yerel komutlar; bağımsız güvenlik sertifikası değil |
| Container security | **Uygulama: 0 HIGH / 0 CRITICAL; PostgreSQL: 0 HIGH / 0 CRITICAL** | Final Trivy taramaları 18:00:46Z ve 18:01:14Z; CVE suppress/ignore yok; düşük/orta seviye için temiz iddiası yok |
| Secret/diff/routes | Kaynak secret pattern scan, Git whitespace, route list ve Compose syntax son teslim kontrolü | Tam dosya listesi ve son durum commit öncesi doğrulanır |
| Koruma | Eski ve genişletilmiş guard: **.env + 183 özgün domain kaydı aynen**; ana app `/up`→200 | APP_KEY/DB_PASSWORD dosyanın byte hash'iyle korunur; 18731/55439 yerinde |

## Üretim provası sınırları ve güvenlikte bulunan gerçek hatalar

İlk Apache prototipinde bearer başlığı iletimi ve stateless landing'e Inertia logout yönlendirmesi hataları bulundu; davranışlar düzeltilip eski regresyonlar tekrar geçirildi. Axe marka linki adı, soluk metin kontrastı ve scroll table keyboard erişimi sorunlarını buldu; düzeltildi. Test varsayımlarındaki dialog adı ve WebKit keyboard çalışma zamanı farkı da gerçek çıktı üzerinden düzeltildi; eski testlere skip eklenmedi.

Container taraması eski Debian paketlerinde düzeltilmemiş libxml2 ve başka bulgular gösterdi. Bunlar gizlenmedi: final paket FrankenPHP/Alpine'a taşındı. Stok sunucudaki x/net v0.59.0 HIGH bulgusu resmî matching builder ile v0.60.0'a düzeltildi. PostgreSQL'in Go içeren eski helper'ı için C karşılığı ve entrypoint user/exec çağrısı kullanıldı. Bu değişiklikler yalnızca ayrı üretim paketini etkiler; ilk başarısız imaj taramaları yerel loglarda korunur. Docker Scout hesap istediği için çalıştırılamadı; hesap açılmadan Trivy kullanıldı.

Gerçek HTTPS, proxy zinciri, HSTS dış erişimi, sertifika yenileme, SMTP teslimi, bağımsız penetration test, resmî erişilebilirlik/compliance sertifikası ve uzun süreli production yükü **NOT TESTED**. Snapshot/tenant güvenliği uygulama seviyesindedir; DB RLS/immutable trigger yoktur. Ayrıcalıklı DB yöneticisi kayıt değiştirebilir. Replay synchronous/latest-N/500 ve 1 MiB manifest ile sınırlıdır. İlk membership workspace modeli devam eder; SSO/invite/org switching yoktur. Approval sadece belge niteliğindedir; executor, gerçek refund, ödeme, ücretli entitlement veya AI çağrısı yoktur.

## Veri, ortam ve Git teslimi

Kullanıcının `.env` dosyasına hiç yazılmadı; APP_KEY/DB_PASSWORD değiştirilmedi. Kullanıcı DB'sine seed/reset/rollback/migration uygulanmadı. Orijinal agent key hash'leri, sürümler, kararlar ve ilişkili kayıtlar korunur. Sessions/cache temizlenmedi. Eski 18731 ve 55439 loopback portları sürer; yeni servis yalnızca 18733 loopback'tir. 18732 regresyon sunucusunun lifecycle'ı testlere aittir.

`storage/.env.rehearsal` yeni rastgele sırlar kullanır ve ignored'dır. Ayrı `scopetwin-build03` projesi/volume'ları ve disposable restore DB'leri kullanılır; hiçbir mevcut projede `down -v` çalıştırılmadı. Demo/ölçek/browser verileri yalnızca test ortamlarında eklenir. Üretim için bu sırlar taşınmamalı; operatör yeni ve kalıcı production sırları yönetmelidir.

Final değişiklikler bu raporla aynı yerel feature commit'inde tutulur; kesin hash `git log -1` ve son kullanıcı raporunda verilir. `main` ve eski BUILD 02 commit'i korunur. Remote oluşturma/değiştirme, push, gerçek deploy, DNS, satın alma, üçüncü taraf hesap/mesaj veya ücretli API çağrısı yapılmadı.

## Demo ve dış yayın adımları

Yerel demo: **http://127.0.0.1:18733/demo**. Landing → Try read-only demo → Recorded requests → Compare the draft → Human review. Hesap veya sır gerekmez. Ana kullanıcı app'inde demo kurulu olmadığı için 18731/demo dürüst unavailable durumunu gösterir.

İngilizce README/Devpost metni, deployment/runbook, go-live listesi, 3:15 storyboard ve SRT hazırdır. Dört public screenshot `docs/images/` altındadır; private test ekranları ignored storage'da ve anahtar gizlendikten sonra çekilmiştir. Gerçek MP4 üretilmedi, yüklenmedi; FFmpeg PATH üzerinde bulunamadı. Video varmış gibi sunulmaz.

Sonraki açık talimat: onaylanmış hosting hedefi + domain/TLS/DNS planı + public GitHub hedefini belirtip dış yayın iznini verin. Operatör staging/release backup, forward migration, gerçek dış HTTPS/SMTP testleri ve video kaydını tamamlasın. Devpost/team/Discord ve partner kredi uygunluğunu kendi hesabında doğrulasın. [Resmî credit duyurusu](https://galuxium-nexus-v2-29411.devpost.com/updates/45828-kindly-redeem-the-credits-on-mandatory-basis-to-be-eligible-for-judging) ve [rules](https://galuxium-nexus-v2-29411.devpost.com/rules) 2026-10-18 tarihinde okundu; hesap bazında uygunluk doğrulanmış değildir, hiçbir erişim kodu materyale kopyalanmadı. `[LIVE_DEMO_URL]`, `[PUBLIC_GITHUB_URL]`, `[VIDEO_URL]` ancak gerçek bağlantılar kontrol edildiğinde doldurulmalı ve gönderim ayrıca yapılmalıdır.

## Teslimde oluşturulan/değişen dosyaların tam listesi

Toplam 82 yol. BUILD03_CODEX_PROMPT.md ve BUILD03_HIZLI_KONTROL.md kullanıcı girdileridir; içerikleri korunarak ilk kez Git kapsamına alındı.

- `.dockerignore`
- `.env.production.example`
- `.gitignore`
- `Dockerfile`
- `LICENSE`
- `README.md`
- `app/Console/Commands/SetupJudgeDemo.php`
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `app/Http/Controllers/Auth/PasswordResetLinkController.php`
- `app/Http/Controllers/PublicDemoController.php`
- `app/Http/Controllers/ReadinessController.php`
- `app/Http/Middleware/ConfiguredProxies.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Http/Middleware/SecurityHeaders.php`
- `app/Logging/RedactSecrets.php`
- `app/Providers/AppServiceProvider.php`
- `bootstrap/app.php`
- `compose.production.yaml`
- `config/demo.php`
- `config/security.php`
- `config/session.php`
- `deploy/Caddyfile`
- `deploy/benchmark.php`
- `deploy/entrypoint.sh`
- `deploy/healthcheck.php`
- `deploy/php.ini`
- `deploy/postgres.Dockerfile`
- `deploy/verify-runtime.php`
- `docs/ACCEPTANCE_TESTS.md`
- `docs/ARCHITECTURE.md`
- `docs/BUILD03_CODEX_PROMPT.md`
- `docs/BUILD03_HIZLI_KONTROL.md`
- `docs/BUILD03_TECHNICAL_NOTES.md`
- `docs/BUILD03_TEST_EVIDENCE.json`
- `docs/DEMO_CAPTIONS.srt`
- `docs/DEMO_VIDEO_SCRIPT.md`
- `docs/DEMO_WALKTHROUGH.md`
- `docs/DEPLOYMENT.md`
- `docs/DEVPOST_SUBMISSION.md`
- `docs/GO_LIVE_CHECKLIST.md`
- `docs/IMPLEMENTATION_LOG.md`
- `docs/OPERATIONS_RUNBOOK.md`
- `docs/PRODUCT_BRIEF.md`
- `docs/THIRD_PARTY_NOTICES.md`
- `docs/images/demo-desktop.png`
- `docs/images/demo-mobile.png`
- `docs/images/landing-desktop.png`
- `docs/images/landing-mobile.png`
- `package-lock.json`
- `package.json`
- `playwright.release.config.ts`
- `resources/css/build02.css`
- `resources/css/public.css`
- `resources/css/scope.css`
- `resources/js/components/replay.tsx`
- `resources/js/components/scope.tsx`
- `resources/js/pages/auth/forgot-password.tsx`
- `resources/js/pages/scope/agent.tsx`
- `resources/js/pages/scope/agents.tsx`
- `resources/js/pages/scope/approvals.tsx`
- `resources/js/pages/scope/policies.tsx`
- `resources/js/pages/scope/replay.tsx`
- `resources/js/pages/scope/replays.tsx`
- `resources/js/pages/scope/usage.tsx`
- `resources/js/public.tsx`
- `resources/views/app.blade.php`
- `resources/views/public.blade.php`
- `routes/auth.php`
- `routes/public.php`
- `routes/web.php`
- `scripts/capture-demo.mjs`
- `scripts/check-secrets.ps1`
- `scripts/load-smoke.mjs`
- `scripts/rehearse-production.ps1`
- `scripts/restore-smoke.ps1`
- `scripts/scan-containers.ps1`
- `scripts/security-smoke.mjs`
- `scripts/test-database-image.ps1`
- `scripts/verify-preservation.php`
- `tests/Feature/Build03Test.php`
- `tests/release/launch.spec.ts`
- `vite.config.js`
