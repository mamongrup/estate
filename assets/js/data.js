/**
 * Mamon Estate — Data Layer
 * Fetches published content directly from the PostgreSQL-backed public API.
 * Other scripts call getEstateData() which returns the cached data synchronously.
 */

const DEFAULT_DATA = {
  rates: window.MAMON_RATES || { TRY: 1, EUR: 0.027, USD: 0.029, GBP: 0.023, RUB: 2.55, AED: 0.106 },
  regions: [],
  listings: [],
};

// Remove data written by the retired demo/localStorage implementation.
try { localStorage.removeItem('marevitaData'); } catch {}

let _cached = null;
let _fetching = null;
const parseArray = value => {
  if (Array.isArray(value)) return value;
  try { const parsed = JSON.parse(value || '[]'); return Array.isArray(parsed) ? parsed : []; } catch { return []; }
};

/**
 * Synchronous getter — returns cached data.
 * If no data has been fetched yet, returns an empty, typed data set.
 */
function getEstateData() {
  if (_cached) return _cached;
  return _cached || structuredClone(DEFAULT_DATA);
}

/**
 * Save to the in-memory page cache. Portfolio content is never sourced from
 * browser storage, so deleted or unpublished database rows cannot reappear.
 */
function saveEstateData(data) {
  _cached = data;
}

/**
 * Fetch all data from API and cache it.
 * Returns a promise; subsequent calls reuse the same promise.
 */
async function fetchEstateData() {
  if (_fetching) return _fetching;

  _fetching = (async () => {
    try {
      const language = localStorage.getItem('siteLanguage') || 'tr';
      const [listingsRes, regionsRes, ratesRes] = await Promise.all([
        fetch('/api/public/listings?lang=' + encodeURIComponent(language), { credentials: 'same-origin', cache: 'no-store' }),
        fetch('/api/public/regions?lang=' + encodeURIComponent(language), { credentials: 'same-origin', cache: 'no-store' }),
        fetch('/api/public/rates', { credentials: 'same-origin', cache: 'no-store' }).catch(() => null),
      ]);

      const data = structuredClone(DEFAULT_DATA);

      if (listingsRes.ok) {
        const body = await listingsRes.json();
        data.listings = body.listings || [];
      } else throw new Error('İlan verisi alınamadı.');

      if (regionsRes.ok) {
        const body = await regionsRes.json();
        data.regions = (body.regions || []).map(r => ({
          id: r.id,
          name: r.name,
          province: r.province,
          image: r.image || 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1000&q=85',
          slug: r.slug,
          listingCount: r.listingCount || 0,
          contentTitle: r.contentTitle || r.name,
          description: r.description || '',
          attractions: parseArray(r.attractions),
          gallery: parseArray(r.gallery),
          videoUrl: r.videoUrl || '',
          seoTitle: r.seoTitle || '',
          seoDescription: r.seoDescription || '',
        }));
      } else throw new Error('Bölge verisi alınamadı.');

      if (ratesRes && ratesRes.ok) {
        const body = await ratesRes.json();
        if (body.rates) data.rates = { ...data.rates, ...body.rates };
      }

      saveEstateData(data);
      return data;
    } catch (err) {
      console.warn('PostgreSQL public API request failed:', err);
      _cached = structuredClone(DEFAULT_DATA);
      return _cached;
    }
  })();

  return _fetching;
}

/**
 * Fetch a single listing from API.
 */
async function fetchListingDetail(id) {
  try {
    const language = localStorage.getItem('siteLanguage') || 'tr';
    const res = await fetch('/api/public/listings?id=' + encodeURIComponent(id) + '&lang=' + encodeURIComponent(language), { credentials: 'same-origin', cache: 'no-store' });
    if (!res.ok) return null;
    const body = await res.json();
    return body.listing || null;
  } catch {
    return null;
  }
}

/**
 * Search listings via API.
 */
async function searchListings({ search = '', type = '', status = '', region = '', page = 1, limit = 12 } = {}) {
  const params = new URLSearchParams();
  if (search) params.set('search', search);
  if (type) params.set('type', type);
  if (status) params.set('status', status);
  if (region) params.set('region', region);
  params.set('page', page);
  params.set('limit', limit);
  params.set('lang', localStorage.getItem('siteLanguage') || 'tr');

  try {
    const res = await fetch('/api/public/listings?' + params.toString(), { credentials: 'same-origin', cache: 'no-store' });
    if (!res.ok) return { listings: [], pagination: { total: 0, page: 1, totalPages: 0 } };
    return await res.json();
  } catch {
    return { listings: [], pagination: { total: 0, page: 1, totalPages: 0 } };
  }
}
