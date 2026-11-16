import './bootstrap';

function setTheme(theme) {
    const value = theme === 'dark' ? 'dark' : 'light';
    document.documentElement.dataset.theme = value;
    try { localStorage.setItem('pos-theme', value); } catch (_) {}
    document.querySelectorAll('.theme-symbol').forEach((el) => {
        el.textContent = value === 'dark' ? '☀' : '☾';
    });
}

document.addEventListener('DOMContentLoaded', () => {
    setTheme(document.documentElement.dataset.theme);

    document.querySelectorAll('.js-theme-toggle').forEach((button) => {
        button.addEventListener('click', () => {
            setTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark');
        });
    });

    document.querySelectorAll('[data-menu-toggle]').forEach((button) => {
        button.addEventListener('click', () => document.body.classList.toggle('nav-open'));
    });

    document.addEventListener('click', (event) => {
        if (document.body.classList.contains('nav-open') &&
            !event.target.closest('.sidebar, [data-menu-toggle]')) {
            document.body.classList.remove('nav-open');
        }
    });

    document.querySelectorAll('form [data-confirm]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();

            const overlay = document.createElement('div');
            overlay.className = 'modal-overlay';
            overlay.innerHTML = '<div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="confirmation-title"><h2 id="confirmation-title">Confirm action</h2><p class="muted"></p><div class="modal-actions"><button class="btn btn-outline" type="button" data-cancel>Cancel</button><button class="btn btn-primary" type="button" data-approve>Confirm</button></div></div>';
            overlay.querySelector('p').textContent = button.dataset.confirm;
            const close = () => overlay.remove();

            overlay.querySelector('[data-cancel]').addEventListener('click', close);
            overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
            overlay.querySelector('[data-approve]').addEventListener('click', () => {
                close();
                button.closest('form').requestSubmit();
            }, { once: true });
            document.body.append(overlay);
            overlay.querySelector('[data-cancel]').focus();
        });
    });
});
