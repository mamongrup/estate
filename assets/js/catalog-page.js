/**
 * Mamon Estate — Catalog Page (Satılık / Kiralık)
 * Fetches listings from API with status and region filters.
 */

const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const catalogSymbols = { TRY: '₺', EUR: '€', USD: '$', GBP: '£', RUB: '₽', AED: 'د.إ' };

async function renderCatalog() {
  const grid = document.querySelector('#catalogGrid');
  if (!grid) return;

  const pageStatus = document.body.dataset.catalogStatus || '';
  const requestedRegion = new URLSearchParams(location.search).get('region') || '';
  const currentCurrency = localStorage.getItem('currency') || 'TRY';

  grid.innerHTML = '<p>İlanlar yükleniyor…</p>';

  const result = await searchListings({
    status: pageStatus,
    region: requestedRegion,
    limit: 50,
  });

  const listings = result.listings || [];

  grid.innerHTML = listings.map(x => `
    <article class="property-card">
      <a class="property-link" href="/ilan/${esc(x.id)}">
        <div class="property-image">
          <img src="${esc(x.image || '/assets/images/mamon-estate-icon.png')}" alt="${esc(x.title)}">
          <span class="badge">${esc((x.status || '').toUpperCase())}</span>
        </div>
        <div class="property-body">
          <span class="property-location">${esc((x.region || '').toUpperCase())} · ${esc((x.type || '').toUpperCase())}</span>
          <h3>${esc(x.title)}</h3>
          <div class="features">
            <span>${esc(x.rooms)} Oda</span>
            <span>${esc(x.bath)} Banyo</span>
            <span>${esc(x.area)} m²</span>
          </div>
          <div class="property-price">
            <b>${new Intl.NumberFormat('tr-TR', { maximumFractionDigits: 0 }).format(Number(x.price || 0) * (window.MAMON_RATES?.[currentCurrency] || 1))} ${catalogSymbols[currentCurrency] || currentCurrency}</b>
            <span>MV-${esc(x.id)}</span>
          </div>
        </div>
      </a>
    </article>
  `).join('') || '<p>Bu kategoride henüz aktif ilan bulunmuyor.</p>';
}

fetchEstateData().then(() => renderCatalog());
