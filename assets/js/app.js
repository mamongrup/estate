/**
 * Mamon Estate — Homepage
 * Fetches data from API and renders listings, regions, search.
 */

const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const symbols = { TRY: '₺', EUR: '€', USD: '$', GBP: '£', RUB: '₽', AED: 'د.إ' };

let currentCurrency = localStorage.getItem('currency') || 'TRY';
let currentFilter = '';
let currentStatus = '';

function money(value, data) {
  const converted = Number(value || 0) * (data.rates?.[currentCurrency] || 1);
  return new Intl.NumberFormat('tr-TR', { maximumFractionDigits: 0 }).format(converted) + ' ' + (symbols[currentCurrency] || currentCurrency);
}

function renderListings(data) {
  const grid = document.querySelector('#propertyGrid');
  if (!grid) return;

  let list = (data.listings || []).filter(x =>
    (!currentFilter || x.type === currentFilter) &&
    (!currentStatus || x.status === currentStatus)
  );

  grid.innerHTML = list.map(x => `
    <article class="property-card" data-id="${esc(x.id)}">
      <a class="property-link" href="/ilan/${esc(x.id)}" aria-label="${esc(x.title)} ilan detayını aç">
        <div class="property-image">
          <img src="${esc(x.image || '/assets/images/mamon-estate-icon.png')}" alt="${esc(x.title)}">
          <span class="badge">${esc((x.status || '').toUpperCase())}</span>
        </div>
        <div class="property-body">
          <span class="property-location">${esc((x.region || '').toUpperCase())} · ${esc((x.type || '').toUpperCase())}</span>
          <h3>${esc(x.title)}</h3>
          <div class="features">
            <span>▦ ${esc(x.rooms)}</span>
            <span>♢ ${esc(x.bath)} Banyo</span>
            <span>□ ${esc(x.area)} m²</span>
          </div>
          <div class="property-price">
            <b>${money(x.price, data)}</b>
            <span>İlan no: MV-${esc(x.id)}</span>
          </div>
        </div>
      </a>
      <button class="favorite" type="button" aria-label="Favoriye ekle">♡</button>
    </article>
  `).join('') || '<p>Aramanızla eşleşen ilan bulunamadı.</p>';

  document.querySelectorAll('.favorite').forEach(button => {
    button.onclick = e => {
      e.preventDefault();
      e.stopPropagation();
      button.textContent = button.textContent === '♡' ? '♥' : '♡';
    };
  });
}

function renderRegions(data) {
  const regionGrid = document.querySelector('#regionGrid');
  const searchRegion = document.querySelector('#searchRegion');

  if (regionGrid) {
    regionGrid.innerHTML = (data.regions || []).slice(0, 5).map(r => `
      <article class="region-card">
        <img src="${esc(r.image || '')}" alt="${esc(r.name)}">
        <div class="region-overlay">
          <h3>${esc(r.name)}</h3>
          <span>${r.listingCount || 0} GAYRİMENKUL →</span>
        </div>
      </article>
    `).join('');
  }

  if (searchRegion) {
    searchRegion.innerHTML = '<option value="">Tüm bölgeler</option>'
      + (data.regions || []).map(r => `<option>${esc(r.name)}</option>`).join('');
  }
}

// Initialize after data is loaded
async function initHomepage() {
  const data = await fetchEstateData();

  renderListings(data);
  renderRegions(data);

  // Currency selector
  document.querySelector('#currency').value = currentCurrency;
  document.querySelector('#currency').onchange = e => {
    currentCurrency = e.target.value;
    localStorage.setItem('currency', currentCurrency);
    renderListings(data);
  };

  // Filter chips
  document.querySelectorAll('.filter-chips button').forEach(b => {
    b.onclick = () => {
      document.querySelector('.filter-chips .active').classList.remove('active');
      b.classList.add('active');
      currentFilter = b.dataset.filter;
      renderListings(data);
    };
  });

  // Search tabs
  document.querySelectorAll('.search-tabs button').forEach(b => {
    b.onclick = () => {
      document.querySelector('.search-tabs .active').classList.remove('active');
      b.classList.add('active');
      currentStatus = b.dataset.status;
    };
  });

  // Search form
  document.querySelector('#searchForm').onsubmit = e => {
    e.preventDefault();
    currentFilter = document.querySelector('#searchType').value;
    const region = document.querySelector('#searchRegion').value;

    // If region filter, filter displayed listings
    renderListings(data);
    if (region) {
      document.querySelectorAll('.property-card').forEach(card => {
        const item = data.listings.find(x => String(x.id) === card.dataset.id);
        if (item && item.region !== region) card.remove();
      });
    }
    document.querySelector('#listings').scrollIntoView();
  };

  // Modal close
  document.querySelector('.modal-close').onclick = () => document.querySelector('#propertyModal').close();
  document.querySelector('#propertyModal').onclick = e => { if (e.target.id === 'propertyModal') e.target.close(); };
}

initHomepage();
