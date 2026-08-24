# Mamon Estate

Altı dil ve altı para birimi destekli emlak sitesi; DeepSeek danışmanı, yönetim prototipi ve teknik SEO altyapısı içerir.

## Plesk dağıtımı

1. Plesk Git ekranında **Remote repository** seçin.
2. URL: `https://github.com/mamongrup/estate.git`
3. Branch: `main`; deployment mode: **Automatic**; path: `/httpdocs`.
4. PHP 8.2+ ile `curl` ve `mbstring` eklentilerini etkinleştirin.
5. `DEEPSEEK_API_KEY` ortam değişkenini Plesk'te güvenli olarak tanımlayın; anahtarı Git'e eklemeyin.
6. Let's Encrypt ile `mamonestate.com` ve `www.mamonestate.com` SSL sertifikasını kurup HTTPS yönlendirmesini etkinleştirin.

## Ortam değişkenleri

```env
DEEPSEEK_API_KEY=your_key
DEEPSEEK_MODEL=deepseek-chat
SITE_URL=https://mamonestate.com
```

Yönetim ekranı prototiptir. Canlı kullanım öncesinde sunucu taraflı oturum, yetkilendirme ve veritabanı eklenmelidir.
