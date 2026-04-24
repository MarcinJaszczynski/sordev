(function () {
    function applyTheme(theme) {
        if (theme === "dark") {
            document.documentElement.setAttribute("data-theme", "dark");
        } else if (theme === "light") {
            document.documentElement.setAttribute("data-theme", "light");
        }
    }

    // On load, prefer stored theme, otherwise system preference
    const stored = localStorage.getItem("sor3-theme");
    if (stored) {
        applyTheme(stored);
    } else {
        const prefersDark =
            window.matchMedia &&
            window.matchMedia("(prefers-color-scheme: dark)").matches;
        applyTheme(prefersDark ? "dark" : "light");
    }

    // Expose simple toggler
    window.sor3Theme = {
        set: function (t) {
            localStorage.setItem("sor3-theme", t);
            applyTheme(t);
        },
        clear: function () {
            localStorage.removeItem("sor3-theme");
            applyTheme("light");
        },
    };

    // Auto-bind to an existing header/theme toggle button if present
    function bindHeaderToggle() {
        const selectors = [
            "[data-theme-toggle]",
            '[aria-label*="theme" i]',
            '[aria-label*="Tryb" i]',
            ".theme-toggle",
            ".toggle-theme",
            ".filament-theme-toggle",
        ];

        let btn = null;
        for (const sel of selectors) {
            const found = document.querySelector(sel);
            if (found) {
                btn = found;
                break;
            }
        }

        if (!btn) return;

        btn.addEventListener("click", function (e) {
            const current = document.documentElement.hasAttribute("data-theme")
                ? "dark"
                : "light";
            const next = current === "dark" ? "light" : "dark";
            window.sor3Theme.set(next);
        });
    }

    // Bind on DOM ready
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", bindHeaderToggle);
    } else {
        bindHeaderToggle();
    }

    // If no header toggle was found, inject a small toggle button into a header container
    function injectHeaderToggle() {
        const headerSelectors = [
            ".filament-header",
            ".filament-topbar",
            ".filament-navbar",
            ".filament-app-header",
            ".filament-main-header",
            "header",
        ];
        let container = null;
        for (const sel of headerSelectors) {
            const el = document.querySelector(sel);
            if (el) {
                container = el;
                break;
            }
        }

        if (!container) return;

        // Avoid injecting twice
        if (container.querySelector("[data-theme-toggle]")) return;

        const btn = document.createElement("button");
        btn.setAttribute("type", "button");
        btn.setAttribute("aria-label", "Przełącz motyw");
        btn.setAttribute("data-theme-toggle", "");
        btn.className = "filament-button filament-button-size-sm";
        btn.style.marginLeft = "0.5rem";
        btn.innerHTML = "🌓";

        btn.addEventListener("click", function () {
            const current = document.documentElement.hasAttribute("data-theme")
                ? "dark"
                : "light";
            const next = current === "dark" ? "light" : "dark";
            window.sor3Theme.set(next);
        });

        // Prefer appending to action area if present
        const actionArea =
            container.querySelector(".filament-header-actions") ||
            container.querySelector(".filament-topbar-actions");
        if (actionArea) actionArea.appendChild(btn);
        else container.appendChild(btn);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", injectHeaderToggle);
    } else {
        injectHeaderToggle();
    }
})();
