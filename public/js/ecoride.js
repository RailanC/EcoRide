/* ============================================================
   EcoRide — Main JavaScript
   ============================================================ */

(function () {
    "use strict";

    /* ── NAV: scroll effect ──────────────────────────────── */
    const nav = document.getElementById("er-nav");
    if (nav) {
        window.addEventListener(
            "scroll",
            () => {
                nav.classList.toggle("scrolled", window.scrollY > 30);
            },
            { passive: true },
        );
    }

    /* ── NAV: hamburger ─────────────────────────────────── */
    const hamburger = document.getElementById("er-hamburger");
    if (hamburger && nav) {
        hamburger.addEventListener("click", () => {
            nav.classList.toggle("mobile-open");
        });
        // Close on outside click
        document.addEventListener("click", (e) => {
            if (!nav.contains(e.target)) {
                nav.classList.remove("mobile-open");
            }
        });
    }

    /* ── DROPDOWN: avatar menu ──────────────────────────── */
    const avatarBtn = document.getElementById("er-avatar-btn");
    const dropdownMenu = document.getElementById("er-dropdown-menu");
    if (avatarBtn && dropdownMenu) {
        const dropdown = avatarBtn.closest(".er-dropdown");
        avatarBtn.addEventListener("click", (e) => {
            e.stopPropagation();
            dropdown.classList.toggle("open");
        });
        document.addEventListener("click", (e) => {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove("open");
            }
        });
    }

    /* ── TRIP TOGGLE: aller simple / retour ─────────────── */
    window.setTrip = function (btn, isReturn) {
        const group = btn.closest("[data-trip-toggle-group]");
        if (group) {
            const buttons = group.querySelectorAll("[data-trip-toggle-option]");
            buttons.forEach((button) => {
                const isActive = button === btn;
                button.classList.toggle("active", isActive);
                button.classList.toggle("btn-search-custom", isActive);
                button.classList.toggle("text-white", isActive);
                button.classList.toggle("text-white-50", !isActive);
                button.classList.toggle(
                    "home-trip-toggle-btn--inactive",
                    !isActive,
                );
                button.setAttribute("aria-pressed", String(isActive));
            });
        }

        const tripTypeField = document.getElementById("trip_type");
        if (tripTypeField) {
            tripTypeField.value = isReturn ? "return" : "";
        }

        const returnField = document.getElementById("return-field");
        const returnDateInput = document.getElementById("return-date");
        if (returnField) {
            if (isReturn) {
                returnField.classList.remove("d-none", "opacity-50", "pe-none");
                if (returnDateInput) {
                    returnDateInput.disabled = false;
                    returnDateInput.focus();
                }
            } else {
                returnField.classList.add("d-none", "opacity-50", "pe-none");
                if (returnDateInput) {
                    returnDateInput.disabled = true;
                    returnDateInput.value = "";
                }
            }
        }
    };

    /* ── SWAP CITIES ────────────────────────────────────── */
    window.swapCities = function () {
        const fromInput = document.getElementById("from");
        const toInput = document.getElementById("to");
        if (fromInput && toInput) {
            const tmp = fromInput.value;
            fromInput.value = toInput.value;
            toInput.value = tmp;
            // Brief visual feedback
            [fromInput, toInput].forEach((el) => {
                el.style.transition = "opacity 0.15s";
                el.style.opacity = "0.4";
                setTimeout(() => {
                    el.style.opacity = "1";
                }, 150);
            });
        }
    };

    /* ── SEARCH FIELDS: label animation ─────────────────── */
    /* ── SCROLL REVEAL ───────────────────────────────────── */
    const revealObserver = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("visible");
                    revealObserver.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.12 },
    );

    function initReveal() {
        // Add reveal class to major sections' direct children
        const targets = document.querySelectorAll(
            ".er-fleet-copy > *, .er-section__header, .er-bento__card, .er-contact-card",
        );
        targets.forEach((el, i) => {
            el.classList.add("er-reveal");
            el.style.transitionDelay = `${Math.min(i * 0.07, 0.42)}s`;
            revealObserver.observe(el);
        });
    }

    /* ── CONTACT FORM: basic validation feedback ─────────── */
    function initContactForm() {
        const form = document.querySelector(".er-contact-form");
        if (!form) return;

        form.addEventListener("submit", (e) => {
            let hasError = false;
            form.querySelectorAll(".er-form-group__input").forEach((input) => {
                if (!input.value.trim()) {
                    input.style.borderColor = "#ffb4ab";
                    hasError = true;
                    input.addEventListener(
                        "input",
                        () => {
                            input.style.borderColor = "";
                        },
                        { once: true },
                    );
                }
            });
            if (hasError) {
                e.preventDefault();
            }
        });
    }

    function initDiscoverScroll() {
        document
            .querySelectorAll("[data-discover-scroll]")
            .forEach((button) => {
                if (button.dataset.discoverScrollReady === "true") return;

                button.dataset.discoverScrollReady = "true";
                button.addEventListener("click", () => {
                    const targetId = button.dataset.discoverTarget;
                    const target = targetId
                        ? document.getElementById(targetId)
                        : null;
                    if (!target) return;

                    target.scrollIntoView({
                        behavior: "smooth",
                        block: "start",
                    });

                    if (history.replaceState) {
                        history.replaceState(null, "", `#${target.id}`);
                    }
                });
            });
    }

    /* ── NAV: active link highlighting ──────────────────── */
    function highlightActiveLink() {
        const current = window.location.pathname;
        document.querySelectorAll(".er-nav__link").forEach((link) => {
            try {
                const href = new URL(link.href, window.location.origin)
                    .pathname;
                if (href !== "/" && current.startsWith(href)) {
                    link.style.color = "var(--er-primary)";
                }
            } catch (_) {
                /* ignore invalid hrefs */
            }
        });
    }

    function initBootstrapNavSectionState() {
        const sectionLinks = Array.from(
            document.querySelectorAll(".navbar .nav-link[data-nav-section]"),
        );
        if (!sectionLinks.length) return;

        const sections = sectionLinks
            .map((link) => ({
                link,
                section: document.getElementById(link.dataset.navSection),
            }))
            .filter((item) => item.section);

        if (!sections.length) return;

        const setActive = (activeLink) => {
            sectionLinks.forEach((link) => {
                const isActive = link === activeLink;
                link.classList.toggle("active", isActive);
                if (isActive) {
                    link.setAttribute("aria-current", "page");
                } else {
                    link.removeAttribute("aria-current");
                }
            });
        };

        const updateActiveSection = () => {
            const marker = window.innerHeight * 0.4;
            const current =
                sections.reduce((active, item) => {
                    const rect = item.section.getBoundingClientRect();
                    if (rect.top <= marker && rect.bottom > marker) {
                        return item;
                    }

                    if (!active && rect.top > marker) {
                        return item;
                    }

                    return active;
                }, null) || sections[sections.length - 1];

            setActive(current.link);
        };

        sectionLinks.forEach((link) => {
            link.addEventListener("click", () => setActive(link));
        });

        const hashLink = sectionLinks.find((link) => {
            const url = new URL(link.href, window.location.origin);
            return url.hash && url.hash === window.location.hash;
        });
        if (hashLink) {
            setActive(hashLink);
        } else {
            updateActiveSection();
        }

        const observer = new IntersectionObserver(
            (entries) => {
                const visible = entries
                    .filter((entry) => entry.isIntersecting)
                    .sort(
                        (a, b) => b.intersectionRatio - a.intersectionRatio,
                    )[0];

                if (!visible) return;

                const active = sections.find(
                    (item) => item.section === visible.target,
                );
                if (active) setActive(active.link);
            },
            {
                rootMargin: "-35% 0px -50% 0px",
                threshold: [0.1, 0.25, 0.5, 0.75],
            },
        );

        sections.forEach((item) => observer.observe(item.section));
        window.addEventListener("scroll", updateActiveSection, {
            passive: true,
        });
        window.addEventListener("resize", updateActiveSection);
    }

    /* ── INIT ────────────────────────────────────────────── */
    document.addEventListener("DOMContentLoaded", () => {
        initReveal();
        initContactForm();
        initDiscoverScroll();
        highlightActiveLink();
        initBootstrapNavSectionState();
    });

    document.addEventListener("turbo:load", initDiscoverScroll);
})();

const dateInputs = document.querySelectorAll('input[type="date"]');

dateInputs.forEach((input) => {
    input.addEventListener("click", () => {
        if (input.showPicker) {
            input.showPicker();
        }
    });
});
