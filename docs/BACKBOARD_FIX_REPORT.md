# Backboard hata düzeltmesi — 2026-10-20

**Durum: uygulama düzeltmeleri ve AI sahnesi olmayan video doğrulandı; gerçek AI üretimi başarılı değildir.** Kullanıcı son olarak ücretli bakiye yüklemeden, AI sahnesi içermeyen mevcut videoyla sunmayı seçti. Bu rapor çalışan bir canlı AI entegrasyonu iddiası taşımaz. Yeni BUILD açılmadı.

## Gerçek hata kanıtı

İlk, 1640 ms süren isteğin kesin ret nedeni geri getirilemiyor. Ham yanıt/HTTP status saklanmamış, CLI çıktısı DPAPI ledger'a yazılmamış ve container yeniden oluşturulmuştu. Ayrıca production redactor mesajı `Application event` yaparken eski collector `Replay advisor` arıyordu. Dolayısıyla eski `FAILED_OR_UNAVAILABLE` sonucunu validator'a veya belirli modele kesin olarak atfetmek yanlış olur.

Bu görevde yeni bir deneme için kullanıcıdan açıkça izin alındı. Gerçek authenticated Replay Lab ekranındaki **Explain with AI** düğmesi **bir kez** kullanıldı. Sonuç artık ayrıştırılmıştır:

| Aşama | Ölçülen sonuç |
| --- | --- |
| App HTTP | 502 / unavailable, cache kullanılmadı |
| Backboard HTTP | **200**; TLS, bağlantı ve API kimlik doğrulama isteği geçti |
| Envelope / assistant | JSON çözüldü, geçerli thread ve doğru assistant eşleşmesi doğrulandı |
| Upstream yürütme | **response.status=FAILED**, failure_stage=provider, reason=provider_failed |
| İçerik validator'ı | Çağrılmadı; bu yeni başarısızlığın sebebi output parsing değildir |
| Süre | Adapter generation + cleanup toplam **1294 ms**; yalnız model gecikmesi değildir |
| Cleanup | DELETE **200**, ardından assistant-scoped thread listesi **0** |
| Veri / konfigürasyon | Deterministik domain verileri aynı; flag yeniden **false**, config cache/binding doğrulandı |
| Deneme sayısı | Bu görevde 1; önceki görevdeki 1'den ayrı açık izin. Otomatik retry yok |

Sonraki hesap tanılamasında kullanıcı paneldeki ücretsiz kredinin **memory & RAG only** olduğunu bildirdi. Salt okunur resmi `/billing/balance` API'si yalnız ücretsiz kredi, sıfır paid/subscription/Nash kredi ve kapalı auto-reload gösterdi. Bu hesapta LLM için kullanılabilir paid kredi bulunmaması doğrulanmış bir engeldir. API'nin FAILED yanıtı daha ayrıntılı sağlayıcı hata metni vermediğinden, bu durumun o yanıtın tek nedeni olduğu veya modelin arızalı olduğu iddia edilmez. Recent-usage hâlâ 0 olay döndürür; gerçek charge için kesin `$0` iddiası yoktur. Hesap bakiyesi ayrıntıları sadece ignored private evidence'da kalır.

`featherless/openai/gpt-oss-20b` güncel resmi katalogda JSON destekli bulunur. Aynı sorguda `featherless/anthracite-org/magnum-v4-72b` ve `featherless/openai/gpt-oss-120b` de listelenir. Bunlar katalog adaylarıdır; çalışan üretim kanıtı değildir. Belirli model sorunu kanıtlanmadığı için tahmini model değişikliği yapılmadı. Kullanıcı ücretli kredi yüklemek yerine AI sahnesi olmadan sunmayı seçti; yeni generation izni yoktur.

## Yapılan kod düzeltmeleri

- `ReplayAdviceOutput`: eski ASCII/numeral yasağı ve iki/üç cümle regex'i kaldırıldı. Gerçek `content` JSON string ve ikinci kez JSON-encoded string ayrıştırılır; normal Unicode, noktalama, tek paragraf ve doğru aggregate sayıları kabul edilir. Beklenen alanlar seçilir, zararsız ek alanlar depolanmaz.
- Sınırlar korunur: typed schema, en fazla iki kısa soru, metin/transport boyut limitleri, anahtar/assistant kimliği, PII/URL/HTML filtreleri, yetki/aksiyon önerileri ve uydurma ticari/risk iddialarını ret. Sayısal tokenlar ve desteklenen sayı-iddiası biçimleri deterministik aggregate ile karşılaştırılır. Bu bir semantic safety garantisi değildir; AI hiçbir yetki/karar/aksiyon yoluna bağlı değildir. UI sayılarını deterministik replay üretmeye devam eder.
- `ReplayAdvisor`: HTTP, envelope, binding, provider, output, TLS/timeout aşamaları ayrı ve sonlu nedenlerle sınıflanır. Provider FAILED hiçbir zaman başarılı içerik gibi kabul edilmez. Response model metadata ve beklenmeyen retrieval context kontrol edilir. Cleanup hata durumlarında da best-effort kalır. v3 cache identity eski başarısız cevabı yeni parser ile karıştırmaz; quota ve in-flight lease korunur.
- `AdvisorDiagnostics` / production redactor: yalnız whitelist edilmiş event/status/stage/reason, HTTP status, upstream status, cleanup status ve elapsed bilgileri kalır. Anahtar, UUID, ham yanıt, SQL veya kullanıcı içeriği loglanmaz. Controller bu operator diagnostic alanlarını browser yanıtından çıkarır.
- `BackboardSmoke`: temizlenmiş diagnostics ve deterministik fingerprint kontrolü eklenir; başarı için gerçek girişim, kabul edilmiş çıktı, cleanup ve değişmeyen domain verileri gerekir. Cache hit yeni canlı kanıt sayılmaz.
- `capture-live-advisor.mjs`: sadece 18734 ve mevcut dedicated synthetic fixture, memory-only signed Laravel session, gerçek düğme, kalıcı tek deneme kilidi, ikinci HTTP isteğini engelleme, gerçek screenshot/video ve sanitized evidence. Yeni web auth bypass, kalıcı parola değişikliği veya mock cevap yoktur.

Resmi sözleşme: [Send Message](https://docs.backboard.io/api-reference/threads/send-message), [Message request/response](https://docs.backboard.io/concepts/messages), [read-only billing API](https://docs.backboard.io/concepts/billing). X-API-Key, assistant_id, llm_provider/model_name, json_output=true, memory=off, web_search=off, tools=[], stream=false ve implicit thread/DELETE yaşam döngüsü korundu.

## Gerçek test sonuçları

- SQLite: **209 PASS / 1604 assertion**, 9.83 s. Smoke success/failure dalı, safe diagnostics ve domain fingerprint testleri de dahildir.
- Disposable PostgreSQL: **209 PASS / 1604 assertion**, 43.94 s. Üç migration rollback/reapply, evaluation/idempotency, approval/policy locking ve üç advisor concurrency kontrolü PASS. Başka uygulama DB'si başlatılmadı/değiştirilmedi; yalnız yeni tmpfs test container/network kaldırıldı.
- Playwright Chromium: **4 PASS**, 1.1 dakika; advisor/CSRF/escaping, original API journey, replay ve approval regresyonları. Mock UI testinin canlı kanıt olmadığı açıkça korunur.
- Yeni 18734 image üzerinde production public-tour: Chromium/Firefox/WebKit **3 PASS**, 14.3 s; mevcut Windows WebKit Tab sınırlaması devam eder.
- TypeScript, ESLint, Prettier, Pint, Composer strict validate: PASS. Composer/npm advisory **0**. Node syntax, Git whitespace ve configured secret/current-private-literal/history audit PASS; bağımsız güvenlik sertifikası değildir.
- Production Vite build: **2027 module**, 5.86 s; host public/build korunarak ayrı protected output. Docker image build PASS; yalnız 18734 app `scopetwin:backboard-fix-20261020` olarak yeniden oluşturuldu.
- HTTP güvenliği: altı özel yol404, forged Host400, forged CSRF419, ready200, HttpOnly/Lax, public demo cookie0.
- Canlı upstream kabul kriteri **FAIL**: gerçek HTTP200/FAILED; doğru unavailable UI ekranı kaydedildi. Başarılı AI explanation veya UI sonucu kanıtı yoktur.

Kanıt: `storage/backboard-fix-*.txt`, `artifacts/backboard-fix-20261020/{preflight,postflight,live-ui-evidence}.json`, `advisor-live.png` ve private başarısız browser kaydı. Hesap bakiyesi ayrıntıları ve rollback DPAPI bilgileri public/Git materyaline eklenmez.

## Güncel MP4 ve koruma

Tam yol: `C:\Projects\ScopeTwin\artifacts\submission-20261020\ScopeTwin-demo-1080p.mp4`.

- **229.981042 s**, 1920×1080, **30 FPS H.264 / AAC48kHz stereo**, 9,728,713 byte.
- İngilizce offline Microsoft Zira narration +30 senkron İngilizce cue; harici ücretli ses API'si yok.
- Önceki 03:24–03:32 Backboard başarısızlık anlatımı kaldırıldı; yerine gerçek kullanım sayıları/replay davranışı anlatılır. Başarısız AI ekranı final videoya eklenmedi; fake/mock/elle yazılmış AI cevabı yoktur. Gerçek başarılı yanıt oluşmadığından AI sahnesi yoktur.
- FFprobe şartları, tam audio/video error-strict decode ve kurulu Chrome'da baştan sona 4× sesli oynatma PASS (`ended=true`, error=null, peak_audio_rms=0.2220243). İlk videonun gerçek footage'ı ve sahne süreleri korunur; yeni ses/captions ölçülüp encode edilir.
- SHA256: `ABDABF8159BBBCE9C42D261DB213B9AA61FFF1E385A879A6F6D065D8748C706B`.
- Önceki MP4/evidence private artifact altında korunmuştur. Güncel narration/captions, metadata/playback/hash kanıtları ve submission-materials güncellenmiştir.

18731 başlangıçtaki gibi kapalı; değiştirilmedi. 18733 ve onun DB/container metadata'sı korunur. 18734 DB/volume sıfırlanmadı; tüm mevcut domain table counts/hash'leri aynı. Üç özel env ACL/owner'ı, APP_KEY ve bütün mevcut parola/anahtar değerleri aynıdır; yalnız izinli 18734 APP_IMAGE ve geçici enable/disable değişti. Flag false kalır. Push, uzak deployment, DNS, video upload, Devpost gönderimi veya satın alma yapılmadı. Kaynak düzeltmeleri yerel commit'e alınır; canlı AI entegrasyonu engeli açık bırakılır.
