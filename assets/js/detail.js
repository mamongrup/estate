/**
 * Mamon Estate — Listing Detail Page
 * Fetches listing from /api/public/listings?id=N and renders it safely.
 */

const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const symbols = { TRY: '₺', EUR: '€', USD: '$', GBP: '£', RUB: '₽', AED: 'د.إ' };

let detailCurrency = localStorage.getItem('currency') || 'TRY';
let detailRates = window.MAMON_RATES || { TRY: 1 };

function detailMoney(value, currency) {
  const cur = currency || detailCurrency;
  return new Intl.NumberFormat('tr-TR', { maximumFractionDigits: 0 }).format(Number(value || 0) * (detailRates[cur] || 1)) + ' ' + (symbols[cur] || cur);
}

async function renderDetail() {
  const root = document.querySelector('#detailPage');
  if (!root) return;

  const params = new URLSearchParams(location.search);
  const pathId = (location.pathname.match(/\/ilan\/(\d+)/) || [])[1];
  const id = pathId || params.get('id');

  if (!id) {
    document.title = 'İlan bulunamadı | Mamon Estate';
    root.innerHTML = '<section class="detail-empty"><h1>İlan bulunamadı</h1><p>İlan numarası belirtilmemiş.</p><a class="back-link" href="/satilik">← Tüm ilanlara dön</a></section>';
    return;
  }

  root.innerHTML = '<div class="detail-loading">İlan yükleniyor…</div>';

  const property = await fetchListingDetail(id);

  if (!property) {
    document.title = 'İlan bulunamadı | Mamon Estate';
    root.innerHTML = '<section class="detail-empty"><h1>İlan bulunamadı</h1><p>Bu ilan kaldırılmış veya adresi değişmiş olabilir.</p><a class="back-link" href="/satilik">← Tüm ilanlara dön</a></section>';
    return;
  }

  document.title = esc(property.title) + ' | Mamon Estate';

  const metaDesc = document.querySelector('#metaDescription');
  if (metaDesc) metaDesc.content = esc(property.description);

  const canonical = document.querySelector('#canonical');
  if (canonical) canonical.href = 'https://mamonestate.com/ilan/' + (property.slug || property.id);

  const ref = 'MV-' + property.id;
  const gallery = property.gallery || [];
  const details = property.details || {};

  root.innerHTML = `
    <div class="detail-shell">
      <div class="breadcrumbs">
        <a href="/">Ana Sayfa</a><span>›</span>
        <a href="/satilik">İlanlar</a><span>›</span>
        <span>${esc(property.region)}</span>
      </div>

      <div class="detail-head">
        <div>
          <span class="eyebrow dark">${esc(property.region?.toUpperCase())} · ${esc(property.type?.toUpperCase())} · ${esc(property.status?.toUpperCase())}</span>
          <h1>${esc(property.title)}</h1>
        </div>
        <div class="detail-price">
          <b>${detailMoney(property.price, property.currency)}</b>
          <span>İlan no: ${esc(ref)}</span>
        </div>
      </div>

      <section class="gallery">
        <figure>
          <img src="${esc(property.image)}" alt="${esc(property.title)}">
          <span class="gallery-badge">${esc(property.status?.toUpperCase())}</span>
        </figure>
        ${gallery.slice(0, 4).map((g, i) => `<figure><img src="${esc(g)}" alt="${esc(property.title)} görünüm ${i + 2}"></figure>`).join('')}
      </section>

      <div class="detail-grid">
        <div>
          <section class="detail-panel">
            <div class="facts">
              <div class="fact"><small>ODA</small><b>${esc(property.rooms)}</b></div>
              <div class="fact"><small>BANYO</small><b>${esc(property.bath)}</b></div>
              <div class="fact"><small>BRÜT ALAN</small><b>${esc(property.area)} m²</b></div>
              <div class="fact"><small>İLAN TİPİ</small><b>${esc(property.type)}</b></div>
              ${property.netArea ? `<div class="fact"><small>NET ALAN</small><b>${esc(property.netArea)} m²</b></div>` : ''}
              ${details.buildingAge ? `<div class="fact"><small>BİNA YAŞI</small><b>${esc(details.buildingAge)}</b></div>` : ''}
              ${details.heating ? `<div class="fact"><small>ISITMA</small><b>${esc(details.heating)}</b></div>` : ''}
              ${details.parking ? `<div class="fact"><small>OTOPARK</small><b>${esc(details.parking)}</b></div>` : ''}
              ${details.deedStatus ? `<div class="fact"><small>TAPU</small><b>${esc(details.deedStatus)}</b></div>` : ''}
            </div>
          </section>

          <section class="detail-panel">
            <h2>İlan açıklaması</h2>
            <div class="description">${esc(property.description)}<br><br>Mamon Estate uzmanlığıyla sunulan bu portföy hakkında ayrıntılı bilgi için danışmanımızla iletişime geçebilirsiniz.</div>
          </section>

          ${(details.interior?.length || details.exterior?.length || details.surroundings?.length || details.transport?.length || details.views?.length || details.accessibility?.length) ? `
          <section class="detail-panel">
            <h2>Özellikler</h2>
            <div class="amenities">
              ${(details.interior || []).map(f => `<span>✓ ${esc(f)}</span>`).join('')}
              ${(details.exterior || []).map(f => `<span>✓ ${esc(f)}</span>`).join('')}
              ${(details.surroundings || []).map(f => `<span>✓ ${esc(f)}</span>`).join('')}
              ${(details.transport || []).map(f => `<span>✓ ${esc(f)}</span>`).join('')}
              ${(details.views || []).map(f => `<span>✓ ${esc(f)}</span>`).join('')}
              ${(details.housingTypes || []).map(f => `<span>✓ ${esc(f)}</span>`).join('')}
              ${(details.accessibility || []).map(f => `<span>✓ ${esc(f)}</span>`).join('')}
            </div>
          </section>` : ''}

          <section class="detail-panel">
            <h2>Konum</h2>
            <div class="map-placeholder">
              <b>${esc(property.district || property.region)}</b>
              <small>${esc(property.neighborhood) ? esc(property.neighborhood) + ' · ' : ''}${esc(property.province)}</small>
              <small>Detaylı konum danışman tarafından paylaşılır.</small>
            </div>
          </section>

          <a class="back-link" href="/satilik">← Tüm ilanlara dön</a>
        </div>

        <aside>
          <div class="contact-card">
            <span class="eyebrow">BU İLANLA İLGİLENİYORUM</span>
            <h3>Size yardımcı olalım.</h3>
            <p>Portföy danışmanımız detaylı bilgi ve randevu için sizi arasın.</p>
            <div class="advisor">
              <span>ME</span>
              <div>
                <b>Mamon Estate Danışmanı</b>
                <small>Gayrimenkul uzmanı</small>
              </div>
            </div>
            <a class="call-action" href="tel:+905330577913">0533 057 79 13</a>
            <a class="whatsapp-action" target="_blank" rel="noopener"
               href="https://wa.me/905330577913?text=${encodeURIComponent(ref + ' numaralı ' + property.title + ' ilanı hakkında bilgi almak istiyorum.')}">
              WhatsApp ile yazın
            </a>
            <div class="detail-id">İlan referansı: ${esc(ref)}</div>
          </div>
        </aside>
      </div>
    </div>`;
}

// Currency selector
const currencySelect = document.querySelector('#currency');
if (currencySelect) {
  currencySelect.value = detailCurrency;
  currencySelect.addEventListener('change', e => {
    detailCurrency = e.target.value;
    localStorage.setItem('currency', detailCurrency);
    renderDetail();
  });
}

// Language selector
const langSelect = document.querySelector('#language');
if (langSelect) {
  langSelect.value = localStorage.getItem('siteLanguage') || 'tr';
}

// Load data then render
fetchEstateData().then(estateData => {
  detailRates = estateData.rates || detailRates;
  return renderDetail();
});
