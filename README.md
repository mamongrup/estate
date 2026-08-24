# Mamon Estate

Altı dil ve altı para birimi destekli emlak platformu. Sunucu ve yönetim katmanı Gleam 1.18.1, veritabanı PostgreSQL 18.6, sunucu-rendered ön yüz HTMX 2.0.10 kullanır. DeepSeek danışmanı ve teknik SEO altyapısı içerir.

## Mimari

- `src/`: Gleam/Mist HTTP uygulaması ve HTMX fragment endpoint'leri
- `migrations/`: PostgreSQL bölgeler, ilanlar, sözleşmeler, çeviriler, kurlar ve ayarlar şeması
- `assets/js/htmx-2.0.10.min.js`: sabitlenmiş yerel HTMX sürümü
- `docker-compose.yml`: uygulama + PostgreSQL geliştirme/dağıtım ortamı
- `public/`: Docker imajı oluşturulurken mevcut HTML ve varlıklar bu dizine kopyalanır

## Yerel geliştirme

```bash
gleam deps download
gleam check
gleam run
```

`DATABASE_URL` tanımlı olmalı ve `migrations/001_initial.sql` veritabanına uygulanmalıdır. Plesk sunucusunda gerçek yapılandırma dosyası `/var/www/vhosts/mamonestate.com/mamonestate-config.env` konumunda tutulur. Docker bulunan ortamda `docker compose up --build` yeterlidir.

## Plesk dağıtımı

1. Plesk Git ekranında **Remote repository** seçin.
2. URL: `https://github.com/mamongrup/estate.git`
3. Branch: `main`; deployment mode: **Automatic**.
4. Sunucuda Erlang/OTP 28 ve Gleam 1.18.1 veya Docker desteği sağlayın. Plesk'in yalnız PHP sunan standart `/httpdocs` modu Gleam uygulamasını tek başına çalıştırmaz.
5. PostgreSQL 18 veritabanını oluşturup `DATABASE_URL` değişkenini tanımlayın.
6. `DEEPSEEK_API_KEY` değişkenini Plesk'te güvenli olarak tanımlayın; anahtarı Git'e eklemeyin.
7. Gleam uygulamasını 8080 portunda servis olarak çalıştırıp Plesk/Nginx reverse proxy'yi bu porta yönlendirin.
8. Let's Encrypt ile `mamonestate.com` ve `www.mamonestate.com` SSL sertifikasını kurup HTTPS yönlendirmesini etkinleştirin.

## Ortam değişkenleri

```env
DEEPSEEK_API_KEY=your_key
DEEPSEEK_MODEL=deepseek-chat
SITE_URL=https://mamonestate.com
```

Yönetim ekranı prototiptir. Canlı kullanım öncesinde sunucu taraflı oturum, yetkilendirme ve veritabanı eklenmelidir.
