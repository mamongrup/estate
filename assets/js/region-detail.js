const regionEscape = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character]));
const regionSymbols = { TRY: '₺', EUR: '€', USD: '$', GBP: '£', RUB: '₽', AED: 'د.إ' };

async function renderRegionDetail() {
  const root = document.querySelector('#regionDetail');
  const slug = (location.pathname.match(/\/bolge\/([a-z0-9-]+)/) || [])[1] || new URLSearchParams(location.search).get('slug');
  const estate = await fetchEstateData();
  const region = (estate.regions || []).find(item => item.slug === slug);
  if (!region) {
    root.innerHTML = '<section class="region-detail-empty"><h1>Bölge bulunamadı</h1><p>Bu bölge kaldırılmış veya adresi değişmiş olabilir.</p><a href="/bolgeler">Tüm bölgelere dön</a></section>';
    return;
  }

  document.title = `${region.seoTitle || region.contentTitle || region.name} | Mamon Estate`;
  document.querySelector('#metaDescription').content = region.seoDescription || region.description || '';
  document.querySelector('#canonical').href = `https://mamonestate.com/bolge/${region.slug}`;
  const result = await searchListings({ region: region.name, limit: 12 });
  const currency = localStorage.getItem('currency') || 'TRY';
  const listings = result.listings || [];
  const gallery = Array.isArray(region.gallery) ? region.gallery : [];
  const attractions = Array.isArray(region.attractions) ? region.attractions : [];

  root.innerHTML = `<section class="region-detail-hero" style="background-image:url('${regionEscape(region.image || '')}')"><div><span class="eyebrow">${regionEscape(region.province)} BÖLGE REHBERİ</span><h1>${regionEscape(region.contentTitle || region.name)}</h1><p>${regionEscape(region.description || '')}</p></div></section>
  <div class="region-detail-content"><div class="region-detail-grid"><div>
    <section class="region-detail-panel"><h2>${regionEscape(region.name)} hakkında</h2><div class="region-copy">${regionEscape(region.description || 'Bu bölge için açıklama henüz eklenmedi.')}</div></section>
    ${gallery.length ? `<section class="region-detail-panel"><h2>Fotoğraf galerisi</h2><div class="region-gallery">${gallery.map(image => `<img src="${regionEscape(image)}" alt="${regionEscape(region.name)}">`).join('')}</div></section>` : ''}
  </div><aside>
    <section class="region-detail-panel"><h2>Cazibe noktaları</h2>${attractions.length ? `<ul class="attraction-list">${attractions.map(item => `<li>${regionEscape(typeof item === 'string' ? item : item.name || '')}</li>`).join('')}</ul>` : '<p class="region-empty">Cazibe noktaları henüz eklenmedi.</p>'}${region.videoUrl ? `<a class="region-video" href="${regionEscape(region.videoUrl)}" target="_blank" rel="noopener">Bölge videosunu izle →</a>` : ''}</section>
  </aside></div>
  <section><div class="page-intro"><h2>${regionEscape(region.name)} bölgesindeki ilanlar</h2><p>Yalnızca veritabanında yayınlanan güncel portföyler gösterilir.</p></div><div class="region-listing-grid">${listings.map(item => `<a class="region-listing-card" href="/ilan/${Number(item.id)}"><img src="${regionEscape(item.image || '/assets/images/mamon-estate-icon.png')}" alt="${regionEscape(item.title)}"><div><span>${regionEscape(item.type)} · ${regionEscape(item.status)}</span><h3>${regionEscape(item.title)}</h3><small>${regionEscape(item.rooms)} oda · ${regionEscape(item.area)} m²</small><b>${new Intl.NumberFormat('tr-TR',{maximumFractionDigits:0}).format(Number(item.price || 0) * (estate.rates?.[currency] || 1))} ${regionSymbols[currency] || currency}</b></div></a>`).join('') || '<p class="region-empty">Bu bölgede yayında ilan bulunmuyor.</p>'}</div></section></div>`;
}

renderRegionDetail();
