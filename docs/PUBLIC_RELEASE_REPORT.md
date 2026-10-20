# ScopeTwin — gerçek public yayın raporu

Tarih: 21 Ekim 2026, Europe/Istanbul. Bu rapor önceki BUILD belgelerinin tarihli yerel sonuçlarından ayrıdır.

## Gerçek bağlantılar

- Public kaynak: https://github.com/HIZMALI/ScopeTwin (varsayılan dal main).
- HTTPS uygulama: https://scopetwin-hizmali.onrender.com
- Hesapsız jüri demosu: https://scopetwin-hizmali.onrender.com/demo
- Üçlü karşılaştırma: https://scopetwin-hizmali.onrender.com/demo/replay
- Sağlık: https://scopetwin-hizmali.onrender.com/ready

Doğrulanmış 0d2d18f tabanı force push olmadan yayımlandı. Kullanıcının özellikle korunmasını istediği eski yerel main zorla değiştirilmedi; mevcut feat/build-03-6-backboard-connectivity dalında çalışıldı. Linux CI ve gerçek yönetilen proxy yolu için gereken düzeltmeler ayrıca normal commit/push ile yayımlandı. Son uygulama düzeltmesi: 03c9ca5; son kaynak/belge commit'i GitHub main ve nihai teslimde belirtilir.

## Yeni ve izole hosting

Render Free Web Service; Docker/PHP8.4/FrankenPHP/Laravel13/React19; Oregon; 512 MB RAM,0.1 CPU;8080;/ready. Yeni Free PostgreSQL18,1 GB,autoscaling kapalı. Veritabanı yalnızca yeni scopetwin_public_demo; yerel veriler taşınmadı. Yeni rastgele APP_KEY ve runtime parolası üretildi; özel değerler Git'e, terminale veya bu rapora yazılmadı. Yerel özel operatör kaydı CurrentUser DPAPI ile, yalnızca mevcut kullanıcı/SYSTEM/Administrators ACL altında saklanır.

Web hesabı scopetwin_runtime: DML ve sequence kullanımı; SUPERUSER=false,CREATEDB=false,CREATEROLE=false,şema CREATE=false. Yönetilen migration sahibinin parolası web ortamında yok. Migration'lar yalnızca yeni DB'ye ileri yönde uygulandı. Gerçek scope:judge-demo komutu sınırlı runtime hesabıyla çalıştı:3 karar,2 changed,2 newly denied,belgelenmiş APPROVED; dış eylem yok. DEMO_ALLOW_SETUP=false.

Operatör DB bağlantısında TLS verify-full ve CA doğrulandı. Uygulama aynı bölgedeki internal DB bağlantısında TLS require kullanır; Render internal sertifikası verify-full desteklemez. Yeni DB'nin geçici operatör /32 dış erişimi kurulumdan sonra kaldırılır; workspace ve başka servis kuralları değiştirilmez.

APP_ENV=production,APP_DEBUG=false,BACKBOARD_ENABLED=false. Uzak ortama Backboard anahtarı kurulmadı, generation yapılmadı. SMTP/parola kurtarma kapalı ve arayüzde açıkça belirtiliyor. Session/cache PostgreSQL'de; oturum Secure,HttpOnly,SameSite=Lax,şifreli.

## Bulunan ve düzeltilen gerçek hatalar

1. İlk GitHub Ubuntu CI'da2 Inertia kontrolü başarısızdı: varsayılan Pages dizini, gerçek küçük harfli pages diziniyle uyuşmuyordu. Dosya kontrolü kapatılmadan doğru TSX yolu tanımlandı; sonraki CI geçti.
2. HSTS dış istekte yoktu. Render health probe özel IPv4 ile, dış trafik ise IPv6 loopback ::1 ile PHP'ye ulaşıyordu. Yalnızca ::1 güvenilir proxy olarak ayarlandı; geçici geniş ağ aralığı kaldırıldı. Host/prefix forwarded başlıkları kabul edilmiyor. Gerçek dış HTTPS yanıtında HSTS max-age=31536000 doğrulandı. Geçici tanılama kapatıldı; tanılama açıkken genel adresler yalnızca şifreli, TLS durumları yalnızca boolean kaydedilir.
3. Gerçek spoof testi ziyaretçi X-Forwarded-For değerlerinin kayıt rate limit kimliğini değiştirebildiğini gösterdi. Yalnızca bu doğrulanmış Render/Cloudflare yolunda, güvenilir peer üzerinden edge'in üzerine yazdığı CF-Connecting-IP tek değeri normalize edilir. Eksik/geçersiz değer ziyaretçinin XFF zincirine geri dönmez. Default/local davranış değişmedi. Uzak test sahte başlıklarla da429 aldığını doğruladı.
4. Manuel izolasyon POST testinde kayıt sonrası CSRF token yenilenmesi hesaba katıldı. Form güvenliği kaldırılmadı; test yeni belge yükleyerek güncel token kullandı. Yanlış HTTP Host testi için doğru TLS SNI korunur; sadece TLS handshake reddiyle yetinilmez.
5. Tanılamayı genişletirken log çağrı sayısı beklentisi bir ara başarısız oldu; filtreli beklenti ve şifre çözme regresyonuyla düzeltildi. Başarısız ara sürüm başarılı sayılmadı.

## Gerçek test kanıtları

| Kontrol | Son doğrulama |
| --- | --- |
| SQLite tam suite |212 PASS,1615 assertion,9.91 s |
| GitHub Ubuntu CI |Gerçek uzakta çalıştı; test/linter başarılı. Son commit sonucu ayrıca kontrol edilir. |
| TypeScript / ESLint / Prettier / Pint |PASS |
| Vite production build |PASS,2027 module |
| Composer strict validate / advisory audit |PASS /0 advisory |
| npm advisory audit |0 vulnerability |
| Gerçek uzak PostgreSQL uygulama akışı |Kayıt,giriş,agent/key,policy yayınlama,ALLOW/REVIEW/DENY,idempotent tekrar/conflict,replay2/2,approval,audit JSON,usage3/1/1 PASS |
| Organizasyon izolasyonu |Yeni ikinci uzak workspace; diğer workspace decision/replay/approval/audit GET404,write404; kendi usage0/0/0 |
| Public UI |1440/390 px,taşma yok; klavye/skip link,axe WCAG2/2.1 AA,CSP ve JS hata kontrolü PASS |
| HTTPS / HTTP yönlendirme |Sertifika doğrulaması açık; başarılı gerçek TLS ve HTTP→HTTPS |
| Güvenlik sınırları |HSTS,CSP,Secure/HttpOnly/Lax cookie;özel .env/.git/storage/artisan/composer/deploy yolları kapalı;yanlış Host reddi;CSRF419;public mutation405;kimliksiz API401 |
| Rate limit |6 geçersiz ve farklı sahte XFF isteği aynı gerçek limiti tüketir;429 doğrulandı;hesap oluşturulmaz |
| Mail / AI |Kurtarma UI'sı unavailable;replay advisor disabled,Explain with AI düğmesi yok |

Son birleşik Chromium browser turu: **3 PASS,1.1 dakika**. Ham key/token/response body veya kullanıcı içeriği test raporlarına eklenmez. Gerçek browser testleri yalnızca uzak yeni sentetik workspaceleri kullanır; localhost fixture kullanılmaz. Audit kayıt kimliğinin bulunması özel, salt okunur operatör helper'ı ile yapılır; HTTP okuması gerçek oturum/yetki kontrolünden geçer. Public test konfigürasyonu yalnızca açıkça onaylanan HTTPS hostunu kabul eder; trace/video/otomatik retry kapalıdır.

## Sınırlar ve sahipte kalan işler

- Ücretsiz web15 dakika trafiksizlikte uyur; sonraki açılış yaklaşık1 dakika sürebilir. Gerçek ölçüm teslim kaydında belirtilir. Keepalive ile ücretsiz plan politikası aşılmaz.
- DB19 Kasım 2026'da sona erer. Managed backup/PITR yok; app filesystem geçici. Bu hackathon preview ticari SLA veya kalıcı hosting taahhüdü değildir.
- Workspace ortak kota:750 instance-hour/ay; bu hesapta5 GB bant ve500 pipeline-minute/ay. Dashboard'da kart yok; hizmet/datastore tutarları$0. Ödeme yöntemi, ücretli plan veya otomatik yükleme eklenmedi.
- Başarılı Backboard LLM üretimi hâlâ yok; opsiyonel Advisor kapalı. Video ve Devpost'a dokunulmadı; yükleme/gönderim kullanıcıda.
- Bağımsız penetrasyon testi, formal erişilebilirlik sertifikası, ticari kapasite/SLA ve gerçek SMTP teslimi iddia edilmez. Önceki yerel Trivy sonuçları bu yeni Render image'ının bağımsız taraması olarak sunulmaz.
-18731/18733/18734 ortamları, mevcut APP_KEY/parolalar, DB/volume'lar yeniden yapılandırılmadı. down -v,migrate:fresh,db:wipe kullanılmadı. İlgisiz Render servisi ve OAuth erişimleri değiştirilmedi.

Resmî referanslar: [Render free](https://render.com/docs/free), [Laravel Docker](https://render.com/docs/deploy-php-laravel-docker), [PostgreSQL](https://render.com/docs/postgresql-creating-connecting), [Cloudflare IP başlıkları](https://developers.cloudflare.com/fundamentals/reference/http-headers/). Bu kaynaklar platform davranışını açıklar; uygulama başarısının kanıtı gerçek testlerdir.

## Son teslim kaydı

Birleşik dış browser turu3/3 PASS. Yeni DB dış erişimi kapalı; gerçek bağlantı reddi SQLSTATE08006 ve internal /ready200,HSTS doğrulandı. Doğal uyku/uyanma ölçümü bekleniyor; henüz başarılı ölçüm iddiası yok.
