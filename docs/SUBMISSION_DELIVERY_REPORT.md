# ScopeTwin — doğrulanmış başvuru teslimi, 2026-10-20

Yerel teslim tamamlandı. Video gerçek çalışan uygulamadan üretildi ve baştan sona oynatıldı. Backboard için izin verilen tek canlı deneme kabul edilen bir açıklama üretmedi. Başarılı AI entegrasyonu, sıfır maliyet, public HTTPS veya tamamlanmış başvuru iddiası yoktur.

## 1. Backboard canlı test ve cleanup

- Başlangıç: `d8c2c01`, mevcut `feat/build-03-6-backboard-connectivity` dalı. Çalışan uygulama paketi doğrulanmış BUILD 03.6 image'ı `scopetwin:build036` olarak kaldı.
- Kök anahtar ile yalnızca 18734 özel binding'i bellekte karşılaştırıldı. Dedicated assistant ve `featherless` / `openai/gpt-oss-20b` konfigürasyonu doğrulandı; kimlikler ve sırlar çıktıya yazılmadı.
- Sadece 18734 özel dosyasında `BACKBOARD_ENABLED` geçici olarak açıldı, yalnızca app container yeniden oluşturuldu ve gerçek config cache doğrulandı.
- Tam olarak bir kez, 18734 container bağlamında `php artisan scope:backboard-smoke --live-backboard-smoke --confirm-charge-credits` çalıştırıldı. Otomatik veya manuel ikinci generation denemesi yapılmadı.
- Exit code **1**, sonuç **FAILED_OR_UNAVAILABLE**; advisor kaydı **FAILED**, kabul edilmiş sonuç yok. Komutun toplam duvar süresi **1640 ms**; bu sayı yalnızca sağlayıcının model gecikmesi değildir. Kesin upstream hata sınıfı saklanan güvenli kanıtta bulunmadığından transport/schema ayrımı yapılmıyor.
- Mevcut adapter'ın best-effort DELETE işlemi cleanup'ı doğruladı; son scoped thread listesi **0** döndürdü. Yeni assistant oluşturulmadı. Thread silinmesi sağlayıcının operasyonel loglarını sildiğini kanıtlamaz.
- Sonunda özel env özgün byte/hash/ACL durumuna geri getirildi; sadece app yeniden oluşturuldu. Gerçek cache içinde **BACKBOARD_ENABLED=false** doğrulandı. APP_KEY, DB parolaları ve diğer sırlar korunmuştur.
- Önce/sonra billing recent-activity sorguları 0 kayıt döndürdü. **Gerçek ücret/ücretsiz kredi tüketimi doğrulanamadı; $0 denmiyor.** İkinci canlı deneme için yeni açık izin gerekir.
- Güvenli kanıt: `artifacts/submission-20261020/backboard-live-evidence.json`. Sır içeren rollback bilgisi yalnızca korumalı LocalAppData içinde CurrentUser DPAPI ile tutulur; Git'e veya teslim paketine girmez.

## 2. Gerçek MP4

**Tam yol:** `C:\Projects\ScopeTwin\artifacts\submission-20261020\ScopeTwin-demo-1080p.mp4`

| Özellik | Ölçülen değer |
| --- | --- |
| Süre | 229.981042 saniye — yaklaşık 3:50 |
| Görüntü | H.264/AVC High, 1920×1080, 30/1 FPS, yuv420p |
| Ses | AAC-LC, 48 kHz, stereo |
| Dosya | 9,782,113 byte |
| Anlatım | İngilizce; yerel Microsoft Zira Desktop, ücretli ses API'si yok |
| Altyazı | Görüntüye işlenmiş 30 İngilizce cue; ayrıca `ScopeTwin-demo.en.srt` |
| SHA256 | `749B785DA905C29923CFF663ABC0AB4549DFD1CB87A3F370B2055FE56526C877` |

Kayıtta landing, gerçek yayınlanmış kurallar, gerçek HTTP ALLOW/REVIEW/DENY, kayıtlı istekler, draft düzenleme/preview, Original/Baseline/Candidate karşılaştırması, **2 changed / 2 newly denied**, iptal edip onaylanan insan incelemesi, özgün REVIEW'ın korunması, gerçek scoped audit JSON endpoint'i ve Usage & Plans gösterilir. Ayrı bir audit dashboard varmış gibi gösterilmez. Hiçbir refund veya harici iş aksiyonu yürütülmez. Başarılı Backboard çıktısı bulunmadığından AI başarı sahnesi yoktur; anlatım denemenin başarısız olduğunu açıklar.

Kayıt yalnızca 18734 üzerinde yeni sentetik workspace kullanır. Anahtar oluşturma ve oturum hazırlığı kayıt başlamadan yapılır; değerler bellekte kalır. Son kayıtta **0 mock response, 0 AI request, 0 browser error** vardır. Altyazılar gerçek WAV sürelerinden hesaplanır; en büyük sahne başlama sapması **15 ms** ölçülmüştür.

## 3. Fiilen çalıştırılan doğrulamalar

| Kontrol | Bu teslimdeki gerçek sonuç |
| --- | --- |
| SQLite, `php artisan test --compact`, bellek DB, provider kapalı | **178 PASS / 1367 assertion**, 16.30 saniye |
| Gerçek PostgreSQL üzerindeki kayıtlı UI/API akışı | 3 beklenen karar, replay 3/2/2, APPROVED_NO_EXECUTION, audit ve kullanım sayıları doğrulandı |
| TypeScript / ESLint / Prettier / Pint | PASS; son ESLint turunda bulunan kullanılmayan global bildirimi kaldırılıp tekrar doğrulandı |
| Yeni script syntax / Git whitespace | PASS |
| Kaynaklar + erişilebilir Git history sır taraması | Mevcut özel değerler ve tanımlı credential desenleri için **0 bulgu**; bağımsız güvenlik denetimi değildir |
| FFmpeg tam görüntü ve ses decode | PASS, `-v error -xerror ... -f null -` |
| ffprobe | Süre, codec, 1080p, 30 FPS ve ses kanalları PASS |
| Gerçek Chrome oynatma | Baştan sona `ended=true`, medya hatası yok, ses RMS > 0; tam oynatma 4× hızda doğrulandı |
| Ses analizi | Ortalama -20.0 dB, tepe -4.3 dB; sessiz dosya veya clipping saptanmadı |
| Görsel inceleme | Encode edilmiş replay, onay ve audit kareleri; okunur UI ve İngilizce altyazılar |
| Veri koruma | Önceden mevcut 13 domain tablosundaki kayıt sayısı/içerik hash'leri eşleşiyor; video verisi yalnızca eklemeli |
| Son servis kontrolü | 18733 `/ready`=200, 18734 `/ready`=200; 18731 başlangıçtaki gibi kapalı |

Tam PostgreSQL/concurrency, dört Chromium regresyonu, üç production public-tour tarayıcısı, paket binding guard, production Vite build, Composer/npm advisory ve Trivy kontrolleri aynı gün **d8c2c01 öncesindeki doğrulamada** geçti; bu teslimde tekrar çalıştırılmış gibi sunulmuyor. O günkü sonuç: PostgreSQL 178/1367, Chromium 4 PASS, public-tour 3 PASS, image HIGH/CRITICAL 0. Bu görevde backend, schema ve uygulama image'ı değiştirilmedi. Güncel testler ve kayıt, teslim scriptleri/belgeleri ve gerçek 18734 akışını doğrular; formal sertifikasyon değildir.

Kanıt dosyaları aynı artifact klasöründeki `recording-evidence.json`, `media-properties.json`, `playback-validation.json`, `video-sha256.json`, `audio-validation.txt`, `data-preservation.json`, `publication-secret-audit.json` ve `timeline.json` dosyalarıdır. SQLite çıktısı `storage/submission-sqlite.txt` içindedir. Raw kayıt ve geçici medya Git-ignored kalır.

## 4. Dosyalar, üretim ve düzeltilen hatalar

- `scripts/record-submission.mjs`: gerçek Playwright UI/API kaydı; ayrı kayıtsız kimlik hazırlığı, sadece 18734, AI isteğini bloke eden koruma.
- `scripts/synthesize-demo.ps1`, `assemble-demo-audio.py`, `render-submission.ps1`, `verify-video-playback.mjs`: offline anlatım, gerçek süreli altyazı, H.264/AAC render, tek render kilidi ve doğrulanmış dosya değiştirme, Chrome/ses doğrulaması.
- `scripts/audit-publication.py`: özel değerleri yayımlamayan kaynak/tüm erişilebilir Git blob taraması.
- `docs/VIDEO_SCENES.json`, `DEMO_CAPTIONS.srt`, `DEMO_VIDEO_SCRIPT.md`: gerçek çekime ait anlatım ve zaman çizelgesi.
- README, mimari, Backboard entegrasyon/BUILD03.6 raporu, Devpost, teslim checklist'leri ve implementation log güncellendi. `/artifacts/` Git-ignored eklendi. Yeni migration, uygulama davranış değişikliği veya production image rebuild yoktur.
- İlk kayıt, Inertia'nın ilk HTML props'unun güncel sanılması nedeniyle durdu; gerçek Inertia GET ile düzeltildi ve final çekim geçti. İlk render denemelerinin çakışması/relative log yolu finali bozdu; exclusive lock, unique candidate ve decode sonrası doğrulanmış dosyayı teslim sırası ile düzeltildi. Eski bozuk dosya teslim edilmiyor. Bundled Chromium medya oynatımı yerine kurulu Chrome ile tam H.264/AAC kontrolü yapıldı.
- İstenen BUILD04A adlı belge depoda bulunmadı; mevcut demo script, captions, walkthrough ve deployment belgeleri doğrudan uygulandı. Yeni hazırlık sprinti açılmadı.
- FFmpeg'in resmi download sayfasının yönlendirdiği Windows build özel tooling klasörüne alındı; yayıncı SHA256 doğrulandı. Global PATH/kurulum değiştirilmedi; binary teslim/source Git paketinde değildir.

## 5. GitHub, HTTPS ve Devpost

MIT lisans ve framework/third-party provenance belgeleri korundu. Kaynaklar yerel commit için hazırlandı, sır taraması temiz. Public GitHub remote/hedefi verilmedi; push veya public repo oluşturulmadı. Mevcut Docker üretim paketi ve DEPLOYMENT.md korundu; hosting/domain/erişim hedefi verilmediğinden uzak deployment, DNS veya HTTPS sertifikası işlemi yapılmadı. **Public HTTPS doğrulanmış değildir.**

Son kullanıcı talimatıyla **video yüklemesi ve Devpost gönderimi kullanıcıya aittir**. İngilizce başvuru metni, checklist, rapor, lisans, README ve deployment belgesi `artifacts/submission-20261020/submission-materials/` içinde toplandı. `[VIDEO_URL]`, `[PUBLIC_GITHUB_URL]`, `[LIVE_DEMO_URL]` gerçek bağlantılar sağlanana kadar placeholder kalır. Ekip/kayıt/Discord/partner-kredi uygunluğu ve son submission kabulü hesap sahibi tarafından doğrulanmalıdır. Yeni canlı AI denemesi yapılmadan durulmuştur.

18731/18733 uygulama veya veritabanları değiştirilmedi. 18731 zaten kapalıydı ve başlatılmadı. Sadece 18734 app geçici enable/restore için yeniden oluşturuldu; database volume'ları korundu. Destructive reset, uzak assistant silme/oluşturma, ek ücretli API çağrısı, push, video upload veya Devpost submission yapılmadı.
