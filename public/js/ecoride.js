/* ============================================================
   EcoRide — Main JavaScript
   ============================================================ */

(function () {
    'use strict';

    /* ── NAV: scroll effect ──────────────────────────────── */
    const nav = document.getElementById('er-nav');
    if (nav) {
        window.addEventListener('scroll', () => {
            nav.classList.toggle('scrolled', window.scrollY > 30);
        }, { passive: true });
    }

    /* ── NAV: hamburger ─────────────────────────────────── */
    const hamburger = document.getElementById('er-hamburger');
    if (hamburger && nav) {
        hamburger.addEventListener('click', () => {
            nav.classList.toggle('mobile-open');
        });
        // Close on outside click
        document.addEventListener('click', (e) => {
            if (!nav.contains(e.target)) {
                nav.classList.remove('mobile-open');
            }
        });
    }

    /* ── DROPDOWN: avatar menu ──────────────────────────── */
    const avatarBtn = document.getElementById('er-avatar-btn');
    const dropdownMenu = document.getElementById('er-dropdown-menu');
    if (avatarBtn && dropdownMenu) {
        const dropdown = avatarBtn.closest('.er-dropdown');
        avatarBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('open');
        });
        document.addEventListener('click', (e) => {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove('open');
            }
        });
    }

    /* ── TRIP TOGGLE: aller simple / retour ─────────────── */
    window.setTrip = function (btn, isReturn) {
        const group = btn.closest('[data-trip-toggle-group]');
        if (group) {
            const buttons = group.querySelectorAll('[data-trip-toggle-option]');
            buttons.forEach((button) => {
                const isActive = button === btn;
                button.classList.toggle('btn-success', isActive);
                button.classList.toggle('btn-dark', !isActive);
                button.setAttribute('aria-pressed', String(isActive));
            });
        }

        const returnField = document.getElementById('return-field');
        const returnDateInput = document.getElementById('return-date');
        if (returnField) {
            if (isReturn) {
                returnField.classList.remove('d-none', 'opacity-50', 'pe-none');
                if (returnDateInput) {
                    returnDateInput.disabled = false;
                    returnDateInput.focus();
                }
            } else {
                returnField.classList.add('d-none', 'opacity-50', 'pe-none');
                if (returnDateInput) {
                    returnDateInput.disabled = true;
                    returnDateInput.value = '';
                }
            }
        }
    };

    /* ── SWAP CITIES ────────────────────────────────────── */
    window.swapCities = function () {
        const fromInput = document.getElementById('from');
        const toInput   = document.getElementById('to');
        if (fromInput && toInput) {
            const tmp      = fromInput.value;
            fromInput.value = toInput.value;
            toInput.value   = tmp;
            // Brief visual feedback
            [fromInput, toInput].forEach(el => {
                el.style.transition = 'opacity 0.15s';
                el.style.opacity = '0.4';
                setTimeout(() => { el.style.opacity = '1'; }, 150);
            });
        }
    };

    /* ── SEARCH FIELDS: label animation ─────────────────── */
    /* ── SCROLL REVEAL ───────────────────────────────────── */
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                revealObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });

    function initReveal () {
        // Add reveal class to major sections' direct children
        const targets = document.querySelectorAll(
            '.er-fleet-copy > *, .er-section__header, .er-bento__card, .er-contact-card'
        );
        targets.forEach((el, i) => {
            el.classList.add('er-reveal');
            el.style.transitionDelay = `${Math.min(i * 0.07, 0.42)}s`;
            revealObserver.observe(el);
        });
    }

    /* ── CONTACT FORM: basic validation feedback ─────────── */
    function initContactForm () {
        const form = document.querySelector('.er-contact-form');
        if (!form) return;

        form.addEventListener('submit', (e) => {
            let hasError = false;
            form.querySelectorAll('.er-form-group__input').forEach(input => {
                if (!input.value.trim()) {
                    input.style.borderColor = '#ffb4ab';
                    hasError = true;
                    input.addEventListener('input', () => {
                        input.style.borderColor = '';
                    }, { once: true });
                }
            });
            if (hasError) {
                e.preventDefault();
            }
        });
    }

    /* ── NAV: active link highlighting ──────────────────── */
    function highlightActiveLink () {
        const current = window.location.pathname;
        document.querySelectorAll('.er-nav__link').forEach(link => {
            try {
                const href = new URL(link.href, window.location.origin).pathname;
                if (href !== '/' && current.startsWith(href)) {
                    link.style.color = 'var(--er-primary)';
                }
            } catch (_) { /* ignore invalid hrefs */ }
        });
    }

    /* ── INIT ────────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', () => {
        initReveal();
        initContactForm();
        highlightActiveLink();
    });

})();
