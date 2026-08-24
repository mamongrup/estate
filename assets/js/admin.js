function showToast(message, type = 'success') {
  const toast = document.querySelector('#toast');
  if (!toast) return;
  toast.textContent = message;
  toast.className = `show ${type}`;
  clearTimeout(showToast.timer);
  showToast.timer = setTimeout(() => { toast.className = ''; }, 3200);
}

function navigate(id) {
  const page = document.getElementById(id) || document.getElementById('dashboard');
  document.querySelectorAll('.page').forEach(item => item.classList.toggle('active', item === page));
  document.querySelectorAll('aside nav button[data-page]').forEach(item => item.classList.toggle('active', item.dataset.page === page.id));
  const labels = {
    dashboard: ['Genel Bakış', 'PostgreSQL üzerindeki portföyünüzün güncel durumu.'],
    listings: ['İlanlar', 'Yayındaki ve taslak portföy kayıtlarını yönetin.'],
    regions: ['Bölgeler', 'İl, ilçe ve mahalle yapısını yönetin.'],
    translations: ['Yapay Zekâ Çeviri', 'DeepSeek ile çok dilli içerikleri yönetin.'],
    deepseek: ['DeepSeek Ayarları', 'Chatbox, SEO ve çeviri modelini yapılandırın.'],
    settings: ['Site Ayarları', 'Önyüzde kullanılan marka ve iletişim bilgileri.'],
    seo: ['SEO Ayarları', 'Önyüz meta verileri ve indeksleme ayarları.'],
  };
  const [title, description] = labels[page.id] || labels.dashboard;
  document.querySelector('#pageTitle').textContent = title;
  document.querySelector('#pageDesc').textContent = description;
  history.replaceState(null, '', `#${page.id}`);
}

window.showToast = showToast;
window.navigateAdmin = navigate;

const adminIcons = {
  dashboard: '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9 21v-7h6v7"/>',
  listings: '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
  regions: '<circle cx="12" cy="10" r="3"/><path d="M12 21s7-6.1 7-11a7 7 0 1 0-14 0c0 4.9 7 11 7 11Z"/>',
  translations: '<path d="m5 8 6 6M4 14l6-7 2-3M2 5h12M7 2h1"/><path d="m14 18 3-7 3 7M15 16h4"/>',
  deepseek: '<path d="m12 3 1.4 4.1L17.5 8.5l-4.1 1.4L12 14l-1.4-4.1-4.1-1.4 4.1-1.4L12 3Z"/><path d="m19 14 .8 2.2L22 17l-2.2.8L19 20l-.8-2.2L16 17l2.2-.8L19 14Z"/>',
  settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/>',
  seo: '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4M8 11h6M11 8v6"/>',
};
document.querySelectorAll('aside nav button[data-page]').forEach(button => {
  const label = button.querySelector('span')?.outerHTML || `<span>${button.textContent.trim()}</span>`;
  button.innerHTML = `<span class="admin-nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true">${adminIcons[button.dataset.page] || ''}</svg></span>${label}`;
});

document.querySelectorAll('aside nav button[data-page]').forEach(button => button.addEventListener('click', () => navigate(button.dataset.page)));
document.querySelectorAll('[data-goto]').forEach(button => button.addEventListener('click', () => navigate(button.dataset.goto)));

const listingDialog = document.querySelector('#listingDialog');
document.querySelectorAll('.add-listing').forEach(button => button.addEventListener('click', () => listingDialog?.showModal()));
document.querySelectorAll('.close-dialog').forEach(button => button.addEventListener('click', () => listingDialog?.close()));
document.querySelectorAll('input[name="contractType"]').forEach(input => input.addEventListener('change', () => {
  const dates = document.querySelector('#listingForm .dates, #listingEditForm .dates');
  if (dates && input.checked) dates.hidden = input.value === 'unlimited';
}));

document.querySelector('#listingSearch')?.addEventListener('input', event => {
  const query = event.target.value.toLocaleLowerCase('tr-TR');
  document.querySelectorAll('#listingTable tr').forEach(row => { row.hidden = !row.textContent.toLocaleLowerCase('tr-TR').includes(query); });
});
document.querySelector('#listingFilter')?.addEventListener('change', event => {
  document.querySelectorAll('#listingTable tr').forEach(row => { row.hidden = Boolean(event.target.value) && row.dataset.type !== event.target.value; });
});
document.querySelector('.mobile-menu')?.addEventListener('click', () => document.body.classList.toggle('admin-menu-open'));
navigate(location.hash.slice(1) || 'dashboard');
