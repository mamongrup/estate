(function () {
  if (!window.MamonI18n) document.write('<script src="/assets/js/i18n.js?v=20260825-4"><\/script>');
  const header = document.querySelector('#siteHeader');
  const footer = document.querySelector('#siteFooter');
  if (header) {
    header.innerHTML = `<div class="topbar"><span>Akdeniz'de yaşam, uzmanından.</span><div><a data-site-phone-link href="tel:+905330577913"><span data-site-phone>0533 057 79 13</span></a><span data-site-email>info@mamonestate.com</span></div></div><header class="header"><a class="brand" href="/"><img class="brand-logo" src="/assets/images/mamon-estate-icon.png" alt="Mamon Estate logosu"><span><b data-site-name>Mamon Estate</b><small>GAYRİMENKUL</small></span></a><nav><a href="/satilik">Satılık</a><a href="/kiralik">Kiralık</a><a href="/bolgeler">Bölgeler</a><a href="/hakkimizda">Hakkımızda</a><a href="/iletisim">İletişim</a></nav><div class="header-actions"><label class="locale-control language-control"><span class="control-icon">◎</span><span class="control-copy"><small>DİL</small><strong id="languageLabel">🇹🇷 Türkçe</strong></span><select id="language" aria-label="Site dili"><option value="tr">🇹🇷 Türkçe</option><option value="en">🇬🇧 English</option><option value="de">🇩🇪 Deutsch</option><option value="ru">🇷🇺 Русский</option><option value="ar">🇸🇦 العربية</option><option value="fr">🇫🇷 Français</option></select><span class="control-arrow">⌄</span></label><label class="locale-control currency-control"><span class="control-icon">¤</span><span class="control-copy"><small>PARA BİRİMİ</small><strong id="currencyLabel">₺ TRY</strong></span><select id="currency" aria-label="Para birimi"><option value="TRY">₺ TRY</option><option value="EUR">€ EUR</option><option value="USD">$ USD</option><option value="GBP">£ GBP</option><option value="RUB">₽ RUB</option><option value="AED">د.إ AED</option></select><span class="control-arrow">⌄</span></label><button class="menu-btn" aria-label="Menü">☰</button></div></header>`;
    const language = header.querySelector('#language');
    const currency = header.querySelector('#currency');
    const languageLabel = header.querySelector('#languageLabel');
    const currencyLabel = header.querySelector('#currencyLabel');
    language.value = localStorage.getItem('siteLanguage') || 'tr';
    currency.value = localStorage.getItem('currency') || 'TRY';
    const sync = (select, label) => { label.textContent = select.options[select.selectedIndex].textContent; };
    sync(language, languageLabel); sync(currency, currencyLabel);
    language.onchange = () => { localStorage.setItem('siteLanguage', language.value); sync(language, languageLabel); location.reload(); };
    currency.onchange = () => { localStorage.setItem('currency', currency.value); sync(currency, currencyLabel); location.reload(); };
  }
  if (footer) footer.innerHTML = `<footer><a class="brand inverse" href="/"><img class="brand-logo" src="/assets/images/mamon-estate-icon.png" alt="Mamon Estate logosu"><span><b data-site-name>Mamon Estate</b><small>GAYRİMENKUL</small></span></a><p>Akdeniz ve Ege'nin seçkin gayrimenkul danışmanı.</p><div><a href="/admin">Yönetim Paneli</a><a href="/kvkk">KVKK</a><a href="/gizlilik">Gizlilik</a></div><small>© 2026 <span data-site-name>Mamon Estate</span>. Tüm hakları saklıdır.</small></footer>`;

  fetch('/api/public/settings', { credentials: 'same-origin' }).then(response => response.ok ? response.json() : null).then(data => {
    if (!data) return;
    const site = data.site || {};
    document.querySelectorAll('[data-site-name]').forEach(element => { element.textContent = site.siteName || 'Mamon Estate'; });
    document.querySelectorAll('[data-site-phone]').forEach(element => { element.textContent = site.phone || ''; });
    document.querySelectorAll('[data-site-email]').forEach(element => { element.textContent = site.email || ''; });
    document.querySelectorAll('[data-site-phone-link]').forEach(element => { element.href = `tel:${String(site.phone || '').replace(/\D/g, '')}`; });
  }).catch(() => {});
})();
