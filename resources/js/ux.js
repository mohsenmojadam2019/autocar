(() => {
    'use strict';

    const ready = (callback) => document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', callback) : callback();
    ready(() => {
        const body = document.body;
        const live = document.createElement('div');
        live.className = 'visually-hidden';
        live.setAttribute('aria-live', 'polite');
        live.setAttribute('aria-atomic', 'true');
        body.appendChild(live);

        const offline = document.createElement('div');
        offline.className = 'ac-offline-banner';
        offline.setAttribute('role', 'status');
        offline.textContent = 'اتصال اینترنت قطع است؛ اطلاعات فرم را تا اتصال مجدد حفظ کنید.';
        body.prepend(offline);
        const syncOnline = () => { offline.hidden = navigator.onLine; };
        window.addEventListener('online', syncOnline);
        window.addEventListener('offline', syncOnline);
        syncOnline();

        document.querySelectorAll('img:not([data-eager])').forEach((image) => {
            if (!image.hasAttribute('loading')) image.setAttribute('loading', 'lazy');
            if (!image.hasAttribute('decoding')) image.setAttribute('decoding', 'async');
        });

        document.addEventListener('click', (event) => {
            const target = event.target.closest('[data-confirm]');
            if (target && !window.confirm(target.dataset.confirm || 'آیا مطمئن هستید؟')) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }, true);

        const dirtyForms = new Set();
        document.querySelectorAll('.admin-content form:not([data-no-unsaved])').forEach((form) => {
            const markDirty = () => dirtyForms.add(form);
            form.addEventListener('input', markDirty, { once: true });
            form.addEventListener('change', markDirty, { once: true });
        });
        window.addEventListener('beforeunload', (event) => {
            if (dirtyForms.size) {
                event.preventDefault();
                event.returnValue = '';
            }
        });

        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || form.dataset.submitting === '1') return;
            dirtyForms.delete(form);
            form.dataset.submitting = '1';
            form.setAttribute('aria-busy', 'true');
            form.querySelectorAll('button[type="submit"],input[type="submit"]').forEach((button) => {
                button.disabled = true;
                button.dataset.originalText = button.textContent || button.value || '';
                if (button.tagName === 'BUTTON') button.textContent = 'در حال پردازش…';
            });
            live.textContent = 'درخواست در حال پردازش است.';
        }, true);

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            const menu = document.querySelector('[data-mega-menu]');
            const trigger = document.querySelector('[data-mega-trigger]');
            if (menu && !menu.hidden) {
                menu.hidden = true;
                trigger?.setAttribute('aria-expanded', 'false');
                trigger?.focus();
            }
        });

        document.querySelectorAll('.alert').forEach((alert) => {
            alert.setAttribute('role', alert.classList.contains('alert-danger') ? 'alert' : 'status');
        });
    });
})();
