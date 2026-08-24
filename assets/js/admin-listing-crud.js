(function () {
  const table = document.querySelector('#listingTable');
  const dialog = document.querySelector('#listingDialog');
  const form = document.querySelector('#listingForm');
  if (!table || !dialog || !form) return;

  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const currencySymbols = { TRY: '₺', EUR: '€', USD: '$', GBP: '£', RUB: '₽', AED: 'د.إ' };
  const money = (amount, currency) => new Intl.NumberFormat('tr-TR', { maximumFractionDigits: 2 }).format(Number(amount || 0)) + ' ' + (currencySymbols[currency] || currency);

  function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
  }

  async function loadTable() {
    const response = await fetch('/admin-api/listing', { credentials: 'same-origin' });
    if (!response.ok) return;
    const data = await response.json();
    table.innerHTML = (data.listings || []).map(item => {
      const contract = item.contract_duration_months
        ? item.contract_duration_months + ' ay'
        : (item.contract_type === 'unlimited' ? 'Süresiz' : (item.contract_end || 'Tarih yok'));
      return `<tr data-type="${escapeHtml(item.type)}">
        <td><div class="table-property"><img src="${escapeHtml(item.image || '/assets/images/mamon-estate-icon.png')}" alt="">
        <div><b>${escapeHtml(item.title)}</b><small>MV-${escapeHtml(item.id)}</small></div></div></td>
        <td>${escapeHtml(item.region)}</td>
        <td>${escapeHtml(item.type)}</td>
        <td><b>${escapeHtml(money(item.price, item.currency))}</b></td>
        <td>${escapeHtml(contract)}</td>
        <td><span class="pill">${escapeHtml(item.sale_status)}</span></td>
        <td><div class="listing-actions">
          <button type="button" class="edit-listing" data-id="${escapeHtml(item.id)}">Düzenle</button>
          <button type="button" class="delete-listing" data-id="${escapeHtml(item.id)}">Sil</button>
        </div></td></tr>`;
    }).join('');
  }

  async function refreshDashboard() {
    const recent = document.querySelector('#recentListings');
    if (recent) {
      const response = await fetch('/htmx/admin/listings', { credentials: 'same-origin' });
      if (response.ok) recent.innerHTML = await response.text();
    }
    const total = document.querySelector('#totalListings');
    if (total) {
      const response = await fetch('/admin-api/listing', { credentials: 'same-origin' });
      if (response.ok) total.textContent = String((await response.json()).listings?.length || 0);
    }
    if (window.loadAdminStats) await window.loadAdminStats();
  }

  function setField(name, value) {
    const fields = form.querySelectorAll(`[name="${CSS.escape(name)}"], [name="${CSS.escape(name)}[]"]`);
    fields.forEach(field => {
      if (field.type === 'checkbox') field.checked = Array.isArray(value) ? value.includes(field.value) : Boolean(value);
      else if (field.type === 'radio') field.checked = field.value === value;
      else if (field.type !== 'file') field.value = value ?? '';
    });
  }

  function newMode() {
    if (form.id === 'listingEditForm') form.id = 'listingForm';
    form.querySelector('[name="id"]')?.remove();
    form.reset();
    dialog.querySelector('.dialog-head h2').textContent = 'Yeni ilan oluştur';
    form.querySelector('.dialog-actions .solid').textContent = 'İlanı kaydet';
  }

  async function editListing(id) {
    const response = await fetch('/admin-api/listing?id=' + encodeURIComponent(id), { credentials: 'same-origin' });
    const data = await response.json();
    if (!response.ok) return window.showToast && showToast(data.error || 'İlan yüklenemedi.');

    newMode();
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'id';
    hidden.value = id;
    form.appendChild(hidden);
    Object.entries(data.listing).forEach(([name, value]) => setField(name, value));
    form.id = 'listingEditForm';
    dialog.querySelector('.dialog-head h2').textContent = 'İlanı düzenle';
    form.querySelector('.dialog-actions .solid').textContent = 'Değişiklikleri kaydet';

    const dates = form.querySelector('.dates');
    if (dates) dates.style.display = data.listing.contractType === 'unlimited' ? 'none' : 'grid';
    dialog.showModal();
  }

  async function deleteListing(id) {
    if (!window.confirm('Bu ilanı kalıcı olarak silmek istediğinize emin misiniz?')) return;

    const response = await fetch('/admin-api/listing?id=' + encodeURIComponent(id), {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: { 'X-CSRF-TOKEN': getCsrfToken() },
    });
    const data = await response.json();
    if (!response.ok) return window.showToast && showToast(data.error || 'İlan silinemedi.');

    await loadTable();
    await refreshDashboard();
    window.showToast && showToast('İlan silindi.');
  }

  table.addEventListener('click', event => {
    const edit = event.target.closest('.edit-listing');
    const remove = event.target.closest('.delete-listing');
    if (edit) editListing(edit.dataset.id);
    if (remove) deleteListing(remove.dataset.id);
  });

  form.addEventListener('submit', async event => {
    if (form.id !== 'listingEditForm') return;
    event.preventDefault();
    event.stopImmediatePropagation();

    const body = new FormData(form);
    body.set('_csrf', getCsrfToken());

    const response = await fetch('/admin-api/listing', {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
    });
    const data = await response.json();
    if (!response.ok) return window.showToast && showToast(data.error || 'İlan güncellenemedi.');

    dialog.close();
    newMode();
    await loadTable();
    await refreshDashboard();
    window.showToast && showToast('İlan başarıyla güncellendi.');
  }, true);

  document.addEventListener('click', event => {
    if (event.target.closest('.add-listing')) newMode();
  }, true);

  document.addEventListener('DOMContentLoaded', loadTable);
  new MutationObserver(() => loadTable()).observe(document.querySelector('#recentListings'), { childList: true });
})();
