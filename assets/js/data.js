/**
 * Mamon Estate — Data Layer
 * Fetches data from /api/public/* endpoints with localStorage fallback.
 * Other scripts call getEstateData() which returns the cached data synchronously.
 */

const DEFAULT_DATA = {
  rates: window.MAMON_RATES || { TRY: 1, EUR: 0.027, USD: 0.029, GBP: 0.023, RUB: 2.55, AED: 0.106 },
  regions: [],
  listings: [],
};

let _cached = null;
let _fetching = null;

/**
 * Synchronous getter — returns cached data.
 * If no data has been fetched yet, returns localStorage cache or defaults.
 */
function getEstateData() {
  if (_cached) return _cached;
  try {
    const saved = localStorage.getItem('marevitaData');
    if (saved) _cached = JSON.parse(saved);
  } catch {}
  return _cached || structuredClone(DEFAULT_DATA);
}

/**
 * Save to cache + localStorage.
 */
function saveEstateData(data) {
  _cached = data;
  try { localStorage.setItem('marevitaData', JSON.stringify(data)); } catch {}
}

/**
 * Fetch all data from API and cache it.
 * Returns a promise; subsequent calls reuse the same promise.
 */
async function fetchEstateData() {
  if (_fetching) return _fetching;

  _fetching = (async () => {
    try {
      const [listingsRes, regionsRes, ratesRes] = await Promise.all([
        fetch('/api/public/listings', { credentials: 'same-origin' }),
        fetch('/api/public/regions', { credentials: 'same-origin' }),
        fetch('/api/public/rates', { credentials: 'same-origin' }).catch(() => null),
      ]);

      const data = structuredClone(DEFAULT_DATA);

      if (listingsRes.ok) {
        const body = await listingsRes.json();
        data.listings = body.listings || [];
      } else {
        data.listings = getEstateData().listings || DEFAULT_DATA.listings;
      }

      if (regionsRes.ok) {
        const body = await regionsRes.json();
        data.regions = (body.regions || []).map(r => ({
          id: r.id,
          name: r.name,
          province: r.province,
          image: r.image || 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1000&q=85',
          slug: r.slug,
          listingCount: r.listingCount || 0,
        }));
      } else {
        data.regions = getEstateData().regions || DEFAULT_DATA.regions;
      }

      if (ratesRes && ratesRes.ok) {
        const body = await ratesRes.json();
        if (body.rates) data.rates = { ...data.rates, ...body.rates };
      }

      saveEstateData(data);
      return data;
    } catch (err) {
      console.warn('API fetch failed, using cached data:', err);
      return getEstateData();
    }
  })();

  return _fetching;
}

/**
 * Fetch a single listing from API.
 */
async function fetchListingDetail(id) {
  try {
    const res = await fetch('/api/public/listings?id=' + encodeURIComponent(id), { credentials: 'same-origin' });
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

  try {
    const res = await fetch('/api/public/listings?' + params.toString(), { credentials: 'same-origin' });
    if (!res.ok) return { listings: [], pagination: { total: 0, page: 1, totalPages: 0 } };
    return await res.json();
  } catch {
    return { listings: [], pagination: { total: 0, page: 1, totalPages: 0 } };
  }
}
