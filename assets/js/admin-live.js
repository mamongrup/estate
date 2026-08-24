(function () {
  const listings = document.querySelector('#recentListings');
  const regions = document.querySelector('#regionList');

  // CSRF token from meta tag
  function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
  }

  function csrfHeaders() {
    return { 'X-CSRF-TOKEN': getCsrfToken() };
  }

  function csrfBody(params) {
    if (params instanceof URLSearchParams) {
      params.set('_csrf', getCsrfToken());
      return params;
    }
    if (params instanceof FormData) {
      params.set('_csrf', getCsrfToken());
      return params;
    }
    return params;
  }

  // Sync region dropdowns after loading
  function syncRegionOptions() {
    const rows = [...document.querySelectorAll('#regionList [data-region-id]')];
    const parent = document.querySelector('#parentRegion');
    const listing = document.querySelector('#formRegion');
    const options = rows.map(row => ({
      id: row.dataset.regionId,
      name: row.dataset.regionName,
      depth: Number(row.dataset.regionDepth || 0),
    }));
    if (parent) {
      parent.innerHTML = '<option value="">— Ana bölge —</option>'
        + options.map(x => '<option value="' + x.id + '">' + '— '.repeat(x.depth) + x.name + '</option>').join('');
    }
    if (listing) {
      listing.innerHTML = options.map(x => '<option value="' + x.name + '">' + '— '.repeat(x.depth) + x.name + '</option>').join('');
    }
  }

  // Load HTML fragment into target element
  async function load(url, target) {
    const response = await fetch(url, { credentials: 'same-origin' });
    if (response.ok) {
      target.innerHTML = await response.text();
      if (target === regions) syncRegionOptions();
    }
  }

  // Fill a form with data
  function fill(form, data) {
    if (!form || !data) return;
    Object.entries(data).forEach(([key, value]) => {
      const field = form.elements.namedItem(key);
      if (!field) return;
      if (field.type === 'checkbox') field.checked = String(value) === '1';
      else if (field.type !== 'file') field.value = value ?? '';
    });
  }

  // Load settings from API
  async function loadSettings() {
    const response = await fetch('/admin-api/settings', { credentials: 'same-origin' });
    if (!response.ok) return;
    const data = await response.json();
    fill(document.querySelector('#settingsForm'), data.site);
    fill(document.querySelector('#seoForm'), data.seo);
    fill(document.querySelector('#deepseekForm'), data.deepseek);
    const key = document.querySelector('#deepseekForm [name=apiKey]');
    if (key && data.deepseek.apiKeyConfigured) key.placeholder = 'API anahtarı kayıtlı ••••••••';
  }

  async function loadStats() {
    const response = await fetch('/admin-api/stats', { credentials: 'same-origin' });
    if (!response.ok) return;
    const data = await response.json();
    document.querySelector('#totalListings').textContent = data.totalListings ?? 0;
    document.querySelector('#activeListings').textContent = data.activeListings ?? 0;
    document.querySelector('#totalRegions').textContent = data.totalRegions ?? 0;
    document.querySelector('#translationPercent').textContent = (data.translationPercent ?? 0) + '%';

    const distribution = document.querySelector('#distribution');
    if (distribution) {
      const total = Math.max(Number(data.totalListings || 0), 1);
      const known = new Map((data.distribution || []).map(row => [row.type, Number(row.count)]));
      const types = ['Villa', 'Daire', 'Arsa', 'Ticari'];
      distribution.innerHTML = types.map(type => {
        const count = known.get(type) || 0;
        const percent = Math.round(count / total * 100);
        return `<div class="bar-row"><div class="bar-label"><span>${type}</span><b>${count}</b></div><div class="bar"><i style="width:${percent}%"></i></div></div>`;
      }).join('');
    }

    const alerts = document.querySelector('#contractAlerts');
    if (alerts) {
      alerts.innerHTML = (data.contractAlerts || []).map(item =>
        `<div class="alert-item"><b>${item.title}</b><span>${item.contract_end}</span></div>`
      ).join('') || '<div class="alert-item"><b>Yaklaşan sözleşme bitişi yok</b></div>';
    }
  }
  window.loadAdminStats = loadStats;

  async function loadTranslations() {
    const table = document.querySelector('#translationTable');
    if (!table) return;
    const response = await fetch('/admin-api/translations', { credentials: 'same-origin' });
    if (!response.ok) {
      table.innerHTML = '<tr><td colspan="8" class="empty-state">Çeviri durumu yüklenemedi.</td></tr>';
      return;
    }
    const data = await response.json();
    const escape = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character]));
    table.innerHTML = (data.listings || []).map(item => `<tr>
      <td><b>${escape(item.title)}</b></td><td class="trans-ok">✓</td>
      ${['en','de','ru','ar','fr'].map(language => `<td class="${item[language] ? 'trans-ok' : 'trans-wait'}">${item[language] ? '✓' : '—'}</td>`).join('')}
      <td><button type="button" class="more translate-one" data-id="${Number(item.id)}">${['en','de','ru','ar','fr'].every(language => item[language]) ? 'Yenile' : 'Çevir'}</button></td>
    </tr>`).join('') || '<tr><td colspan="8" class="empty-state">Henüz çevrilecek ilan yok.</td></tr>';
  }
  window.loadAdminTranslations = loadTranslations;

  // ── Init ──
  document.addEventListener('DOMContentLoaded', () => {
    loadSettings();
    loadStats();
    loadTranslations();
    if (listings) load('/htmx/admin/listings', listings);
    if (regions) load('/htmx/admin/regions', regions);
  });

  // ── Form submissions ──
  document.addEventListener('submit', async event => {
    const settingTypes = { settingsForm: 'site', seoForm: 'seo', deepseekForm: 'deepseek' };

    // Settings forms
    if (settingTypes[event.target.id]) {
      event.preventDefault();
      event.stopImmediatePropagation();
      const body = new URLSearchParams(new FormData(event.target));
      body.set('type', settingTypes[event.target.id]);
      const response = await fetch('/admin-api/settings', {
        method: 'POST',
        body: csrfBody(body),
        credentials: 'same-origin',
        headers: csrfHeaders(),
      });
      if (window.showToast) showToast(response.ok ? 'Ayarlar başarıyla kaydedildi.' : 'Ayarlar kaydedilemedi.');
      if (response.ok && event.target.id === 'deepseekForm') event.target.elements.apiKey.value = '';
      return;
    }

    // Region or Listing forms
    if (event.target.id !== 'regionForm' && event.target.id !== 'listingForm') return;
    event.preventDefault();
    event.stopImmediatePropagation();

    const isRegion = event.target.id === 'regionForm';
    const url = isRegion ? '/htmx/admin/regions' : '/htmx/admin/listings';
    const target = isRegion ? regions : listings;
    const payload = isRegion
      ? csrfBody(new URLSearchParams(new FormData(event.target)))
      : csrfBody(new FormData(event.target));

    const response = await fetch(url, {
      method: 'POST',
      body: payload,
      credentials: 'same-origin',
      headers: isRegion ? csrfHeaders() : {},
    });

    const regionId = response.headers.get('X-Region-Id');
    const listingId = response.headers.get('X-Listing-Id');
    const html = await response.text();

    if (target) target.innerHTML = html;

    if (response.ok) {
      if (isRegion) {
        syncRegionOptions();
        if (regionId) {
          window.showToast && showToast('Bölge kaydedildi, 5 dil hazırlanıyor…');
          fetch('/admin-api/region-translate', {
            method: 'POST',
            body: csrfBody(new URLSearchParams({ regionId })),
            credentials: 'same-origin',
            headers: csrfHeaders(),
          })
            .then(async r => { const d = await r.json(); if (!r.ok) throw new Error(d.error || 'Çeviri başarısız'); })
            .then(() => window.showToast && showToast('Bölge 5 dile çevrildi.'))
            .catch(error => window.showToast && showToast(error.message));
        }
      }

      event.target.reset();

      if (!isRegion) {
        document.querySelector('#listingDialog')?.close();
        if (listingId) {
          window.showToast && showToast('İlan kaydedildi, 5 dil hazırlanıyor…');
          fetch('/admin-api/listing-translate', {
            method: 'POST',
            body: csrfBody(new URLSearchParams({ listingId })),
            credentials: 'same-origin',
            headers: csrfHeaders(),
          })
            .then(async r => { const d = await r.json(); if (!r.ok) throw new Error(d.error || 'Çeviri başarısız'); })
            .then(() => window.showToast && showToast('İlan 5 dile çevrildi.'))
            .catch(error => window.showToast && showToast(error.message));
        }
      }

      if (window.showToast) showToast(isRegion ? 'Bölge PostgreSQL\'e kaydedildi.' : 'İlan PostgreSQL\'e kaydedildi.');
      loadStats();
      loadTranslations();
    }
  }, true);

  async function translateListing(listingId) {
    const response = await fetch('/admin-api/listing-translate', {
      method: 'POST',
      body: csrfBody(new URLSearchParams({ listingId })),
      credentials: 'same-origin',
      headers: csrfHeaders(),
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'Çeviri oluşturulamadı.');
  }

  document.querySelector('#translationTable')?.addEventListener('click', async event => {
    const button = event.target.closest('.translate-one');
    if (!button) return;
    button.disabled = true;
    button.textContent = 'Çevriliyor…';
    try {
      await translateListing(button.dataset.id);
      await Promise.all([loadTranslations(), loadStats()]);
      window.showToast && showToast('İlan 5 dile çevrildi.');
    } catch (error) {
      window.showToast && showToast(error.message, 'error');
      button.disabled = false;
      button.textContent = 'Tekrar dene';
    }
  });

  document.querySelector('#translateAll')?.addEventListener('click', async event => {
    const ids = [...document.querySelectorAll('#translationTable .translate-one')].map(button => button.dataset.id);
    if (!ids.length) return window.showToast && showToast('Çevrilecek ilan bulunamadı.');
    event.currentTarget.disabled = true;
    event.currentTarget.textContent = 'Çeviriler hazırlanıyor…';
    const results = await Promise.allSettled(ids.map(translateListing));
    await Promise.all([loadTranslations(), loadStats()]);
    const failed = results.filter(result => result.status === 'rejected').length;
    window.showToast && showToast(failed ? `${failed} ilan çevrilemedi.` : 'Tüm ilanlar 5 dile çevrildi.', failed ? 'error' : 'success');
    event.currentTarget.disabled = false;
    event.currentTarget.textContent = 'Eksik çevirileri oluştur';
  });

  // ── AI Buttons ──
  document.querySelector('#generateAttractions')?.addEventListener('click', async event => {
    const form = document.querySelector('#regionForm');
    const name = form.elements.name.value.trim();
    const province = form.elements.province.value.trim();
    if (!name) return window.showToast && showToast('Önce bölge adını yazın.');

    event.currentTarget.disabled = true;
    event.currentTarget.textContent = 'Oluşturuluyor…';
    try {
      const response = await fetch('/admin-api/region-attractions', {
        method: 'POST',
        body: csrfBody(new URLSearchParams({ name, province })),
        credentials: 'same-origin',
        headers: csrfHeaders(),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'İşlem başarısız');
      form.elements.attractions.value = (data.attractions || []).join('\n');
      window.showToast && showToast('Cazibe noktaları oluşturuldu.');
    } catch (error) {
      window.showToast && showToast(error.message);
    } finally {
      event.currentTarget.disabled = false;
      event.currentTarget.textContent = '✦ Yapay zekâ ile oluştur';
    }
  });

  document.querySelector('#generateRegionSeo')?.addEventListener('click', async event => {
    const form = document.querySelector('#regionForm');
    const name = form.elements.name.value.trim();
    const province = form.elements.province.value.trim();
    const description = form.elements.description.value.trim();
    if (!name) return window.showToast && showToast('Önce bölge adını yazın.');

    event.currentTarget.disabled = true;
    event.currentTarget.textContent = 'SEO oluşturuluyor…';
    try {
      const response = await fetch('/admin-api/region-seo', {
        method: 'POST',
        body: csrfBody(new URLSearchParams({ name, province, description })),
        credentials: 'same-origin',
        headers: csrfHeaders(),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'İşlem başarısız');
      form.elements.seoTitle.value = data.seoTitle || '';
      form.elements.seoDescription.value = data.seoDescription || '';
      form.elements.seoKeywords.value = data.keywords || '';
      if (!form.elements.canonicalUrl.value) {
        form.elements.canonicalUrl.value = 'https://mamonestate.com/bolgeler/'
          + name.toLocaleLowerCase('tr-TR').normalize('NFD').replace(/[\u0300-\u036f]/g, '')
          .replace(/ı/g, 'i').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
      }
      window.showToast && showToast('Bölge SEO alanları oluşturuldu.');
    } catch (error) {
      window.showToast && showToast(error.message);
    } finally {
      event.currentTarget.disabled = false;
      event.currentTarget.textContent = '✦ SEO üret';
    }
  });

  document.querySelector('#generateListingSeo')?.addEventListener('click', async event => {
    const form = document.querySelector('#listingForm');
    const title = form.elements.title.value.trim();
    const description = form.elements.description.value.trim();
    const region = form.elements.region.value;
    if (!title) return window.showToast && showToast('Önce ilan başlığını yazın.');

    event.currentTarget.disabled = true;
    event.currentTarget.textContent = 'SEO oluşturuluyor…';
    try {
      const response = await fetch('/admin-api/listing-seo', {
        method: 'POST',
        body: csrfBody(new URLSearchParams({ title, description, region })),
        credentials: 'same-origin',
        headers: csrfHeaders(),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'SEO üretilemedi');
      form.elements.seoTitle.value = data.seoTitle || '';
      form.elements.seoDescription.value = data.seoDescription || '';
      form.elements.seoKeywords.value = data.seoKeywords || '';
      form.elements.slug.value = data.slug || '';
      if (!form.elements.canonicalUrl.value && data.slug) {
        form.elements.canonicalUrl.value = 'https://mamonestate.com/ilan/' + data.slug;
      }
      window.showToast && showToast('İlan SEO alanları oluşturuldu.');
    } catch (error) {
      window.showToast && showToast(error.message);
    } finally {
      event.currentTarget.disabled = false;
      event.currentTarget.textContent = '✦ SEO üret';
    }
  });
})();
