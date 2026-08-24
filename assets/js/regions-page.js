/**
 * Mamon Estate — Regions Page
 * Fetches regions from API and renders them.
 */

const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

async function renderRegionsPage() {
  const grid = document.querySelector('#regionPageGrid');
  if (!grid) return;

  grid.innerHTML = '<p>Bölgeler yükleniyor…</p>';

  const data = getEstateData();
  const regions = data.regions || [];

  if (regions.length === 0) {
    grid.innerHTML = '<p>Henüz bölge eklenmemiş.</p>';
    return;
  }

  grid.innerHTML = regions.map(r => `
    <a class="region-page-card" href="/satilik?region=${encodeURIComponent(r.name)}">
      <img src="${esc(r.image || '')}" alt="${esc(r.name)}">
      <div>
        <h2>${esc(r.name)}</h2>
        <span>${esc(r.province)} · ${r.listingCount || 0} İLAN →</span>
      </div>
    </a>
  `).join('');
}

fetchEstateData().then(() => renderRegionsPage());
