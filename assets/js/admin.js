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
