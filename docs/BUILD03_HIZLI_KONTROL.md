# ScopeTwin — BUILD 03 Hızlı Kontrol (18 Ekim 2026)

## Hedef

Önce uygulamanın üretim paketini ve jürinin erişebileceği güvenli demoyu geliştireceğiz. GitHub public repo, gerçek HTTPS yayını ve Devpost gönderimini ancak erişim/izin ve bağlantılar netleştikten sonra gerçekleştireceğiz. Codex bu işlemleri kendi başına yapmayacak.

## Şu anda kullanıcı tarafındaki işlemler

1. Devpost'a giriş yapıp **Galuxium Nexus V2** yarışmasında katılım ve takım durumu kontrol edilmeli.
2. Organizatörün **resmî Discord** sunucusuna katılım doğrulanmalı: https://discord.com/invite/J3Tbd3vXdr
3. Organizatörün Backboard kredileriyle ilgili şu duyurusu hesaptan incelenmeli: https://galuxium-nexus-v2-29411.devpost.com/updates/45828-kindly-redeem-the-credits-on-mandatory-basis-to-be-eligible-for-judging . Kredi alınması değerlendirme için zorunlu olarak duyurulmuş olsa da, teknik entegrasyon zorunluluğu açık değildir. Uygunluğu Discord'da teyit edin. Erişim kodu/API anahtarları hiçbir zaman repoya veya sohbete kopyalanmamalı.
4. Hosting için mevcut sunucu veya yeni bir sağlayıcı seçilebilir; Build 03 önce dağıtım paketini hazırlayacak. **Henüz sunucu/domaine erişim vermek gerekmez.**

## Bir sonraki kontroller

- BUILD 03 P0 güvenlik ve üretim paketi tamamlandı mı?
- Ayrı test veritabanında gerçek HTTP smoke test var mı?
- Jüri demosu yalnızca gerçek kayıtları mı gösteriyor ve salt okunur mu?
- 126 eski test ve üç tarayıcı senaryosu bozulmadan korunmuş mu?
- Yerel kullanıcı DB'si, `.env`, APP_KEY ve orijinal kayıtlar değişmeden mi kalmış?
- English Devpost metni ve demo video senaryosu hazırlanmış mı?
- Kaynak kod public GitHub için güvenli mi (gizli veri taraması)?
- Gerçek remote URL, public repo ve video **henüz yoksa** açıkça eksik olarak belirtiliyor mu?

## Kaynaklar

- Resmî yarışma: https://galuxium-nexus-v2-29411.devpost.com/
- Gereklilikler: https://galuxium-nexus-v2-29411.devpost.com/rules
- Yarışma güncellemeleri: https://galuxium-nexus-v2-29411.devpost.com/updates
- Laravel 13 deployment: https://laravel.com/docs/13.x/deployment
