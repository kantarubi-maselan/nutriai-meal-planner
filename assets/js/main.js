/* ============================================================
   NutriAI — Global JavaScript
   ============================================================ */

// ── Flash auto-dismiss ───────────────────────────────────────
document.querySelectorAll('.flash').forEach(el => {
  setTimeout(() => { el.style.opacity = '0'; el.style.transform = 'translateX(120%)'; }, 3500);
});

// ── Password toggle ──────────────────────────────────────────
document.querySelectorAll('.toggle-eye').forEach(btn => {
  btn.addEventListener('click', () => {
    const input = btn.closest('.password-toggle').querySelector('input');
    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    btn.textContent = isText ? '👁' : '🙈';
  });
});

// ── Auth tab switching ───────────────────────────────────────
document.querySelectorAll('.auth-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.auth-tab, .auth-form').forEach(el => el.classList.remove('active'));
    tab.classList.add('active');
    const target = document.getElementById(tab.dataset.target);
    if (target) target.classList.add('active');
  });
});

// ── Sidebar mobile toggle ────────────────────────────────────
const menuBtn = document.getElementById('menuBtn');
const sidebar  = document.querySelector('.sidebar');
if (menuBtn && sidebar) {
  menuBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
  document.addEventListener('click', e => {
    if (!sidebar.contains(e.target) && !menuBtn.contains(e.target)) {
      sidebar.classList.remove('open');
    }
  });
}

// ── Grocery checkbox persistence ────────────────────────────
document.querySelectorAll('.grocery-check').forEach(cb => {
  const key = 'grocery_' + cb.dataset.id;
  cb.checked = localStorage.getItem(key) === '1';
  cb.addEventListener('change', () => {
    localStorage.setItem(key, cb.checked ? '1' : '0');
    cb.closest('.grocery-item')?.classList.toggle('checked', cb.checked);
  });
});

// ── Animate on scroll ────────────────────────────────────────
const observer = new IntersectionObserver(entries => {
  entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
}, { threshold: 0.1 });
document.querySelectorAll('.feature-card, .card').forEach(el => observer.observe(el));