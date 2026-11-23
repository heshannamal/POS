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


// Plan-discount control: no frontend amount is trusted by the billing server.
document.addEventListener('DOMContentLoaded', () => {
    const wrapper = document.querySelector('[data-plan-discount]');
    if (!wrapper) return;

    const toggle = wrapper.querySelector('#is_discount_available');
    const percentage = wrapper.querySelector('#discount_percentage');
    const pane = wrapper.querySelector('[data-discount-percentage-wrap]');
    const preview = wrapper.querySelector('[data-discount-preview]');
    const monthly = document.querySelector('#monthly_price');
    const yearly = document.querySelector('#yearly_price');

    const update = () => {
        const enabled = toggle.checked;
        pane.hidden = !enabled;
        percentage.required = enabled;
        const discount = enabled ? Number(percentage.value) : 0;
        const valid = !enabled || (discount > 0 && discount < 100);
        const percentText = enabled && valid ? discount.toFixed(2) + '% discount' : enabled ? 'Enter a discount from 0.01% to 99.99%' : 'No discount applied';
        const discounted = (input) => {
            if (!input.value.trim() || !valid) return '—';
            const original = Math.round(Number(input.value) * 100);
            if (!Number.isFinite(original) || original < 0) return '—';
            const basis = Math.round(discount * 100);
            const savings = enabled ? Math.floor((original * basis + 5000) / 10000) : 0;
            return ((original - savings) / 100).toFixed(2);
        };
        preview.textContent = percentText + ' · Monthly: ' + discounted(monthly) +
            ' · Yearly: ' + discounted(yearly);
    };

    [toggle, percentage, monthly, yearly].forEach((control) => control.addEventListener('input', update));
    update();
});


// Clipboard actions never send authenticator keys to third-party services.
document.addEventListener('DOMContentLoaded', () => {
    for (const attribute of ['data-copy-secret','data-copy-recovery']) {
        document.querySelectorAll('[' + attribute + ']').forEach(button => {
            button.addEventListener('click', async () => {
                const value = button.getAttribute(attribute);
                if (!value) return;
                try {
                    await navigator.clipboard.writeText(value);
                    button.textContent = 'Copied';
                } catch (_) {
                    button.textContent = 'Select and copy manually';
                }
            });
        });
    }
});


// Communication editor: markdown formatting and server-rendered safe preview.
document.addEventListener('DOMContentLoaded', () => {
    const editor = document.querySelector('[data-markdown-editor]');
    if (!editor) return;
    const insert = (prefix, suffix = prefix, placeholder = 'text') => {
        const start = editor.selectionStart, end = editor.selectionEnd;
        const picked = editor.value.slice(start, end) || placeholder;
        editor.setRangeText(prefix + picked + suffix, start, end, 'select');
        editor.focus();
    };
    const formats = {
        bold: () => insert('**'),
        italic: () => insert('*'),
        heading: () => insert('## ', '', 'Heading'),
        list: () => insert('- ', '', 'List item'),
        link: () => insert('[', '](https://example.com)', 'Link text'),
    };
    document.querySelectorAll('[data-md-format]').forEach(button => {
        button.addEventListener('click', () => formats[button.dataset.mdFormat]?.());
    });
    document.querySelectorAll('[data-insert-variable]').forEach(button => {
        button.addEventListener('click', () => insert('', '', button.dataset.insertVariable));
    });
    const preview = document.querySelector('[data-template-preview]');
    preview?.addEventListener('click', () => {
        document.querySelector('#preview-subject').value =
            document.querySelector('#template_subject')?.value || '';
        document.querySelector('#preview-body').value = editor.value;
    });
    // The submit button is also keyboard accessible.
    document.querySelector('#template-preview-form')?.addEventListener('submit', () => {
        document.querySelector('#preview-subject').value =
            document.querySelector('#template_subject')?.value || '';
        document.querySelector('#preview-body').value = editor.value;
    });
});
