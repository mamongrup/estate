(function () {
  const icon = '/assets/images/mamon-estate-icon.png';
  const adminBrand = document.querySelector('.admin-brand');
  if (adminBrand) adminBrand.innerHTML = `<img src="${icon}" alt="Mamon Estate logosu"><b>Mamon Estate<small>YÖNETİM PANELİ</small></b>`;
  const chatAvatar = document.querySelector('.chat-avatar');
  if (chatAvatar) chatAvatar.innerHTML = `<img src="${icon}" alt="">`;

  function setText(selectors, value) {
    if (!value) return;
    selectors.forEach(selector => document.querySelectorAll(selector).forEach(element => { element.textContent = value; }));
  }

  async function applyDatabaseSettings() {
    try {
      const response = await fetch('/api/public/settings', { credentials: 'same-origin' });
      if (!response.ok) return;
      const { site = {}, seo = {} } = await response.json();
      setText(['.brand b', '[data-site-name]'], site.siteName || 'Mamon Estate');
      setText(['[data-site-phone]'], site.phone);
      setText(['[data-site-email]'], site.email);
      const chatName = document.querySelector('.chatbox header b');
      if (chatName) chatName.textContent = `${site.siteName || 'Mamon Estate'} AI`;

      const values = { siteName: site.siteName, phone: site.phone, email: site.email, whatsapp: site.whatsapp, address: site.address };
      Object.entries(values).forEach(([name, value]) => {
        const input = document.querySelector(`#settingsForm [name="${name}"]`);
        if (input && value && !input.matches(':focus')) input.value = value;
      });

      if (!document.body.matches('.admin-page') && seo.title) document.title = seo.title;
      const description = document.querySelector('meta[name="description"]');
      if (description && seo.description) description.content = seo.description;
      const canonical = document.querySelector('link[rel="canonical"]');
      if (canonical && seo.canonical) canonical.href = seo.canonical;
    } catch (error) {
      console.warn('Site ayarları PostgreSQL API üzerinden alınamadı:', error);
    }
  }

  applyDatabaseSettings();
})();
