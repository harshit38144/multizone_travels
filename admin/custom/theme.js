/**
 * Admin panel light / dark theme toggle.
 */
(function (window, document) {
    'use strict';

    var STORAGE_KEY = 'mz.admin.theme';

    function normalizeTheme(mode) {
        return mode === 'dark' ? 'dark' : 'light';
    }

    function getTheme() {
        return normalizeTheme(document.documentElement.getAttribute('data-theme'));
    }

    function applyTheme(mode, persistLocal) {
        var theme = normalizeTheme(mode);
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.style.colorScheme = theme;
        if (persistLocal !== false) {
            try {
                window.localStorage.setItem(STORAGE_KEY, theme);
            } catch (err) {}
        }
        updateToggleUi(theme);
        return theme;
    }

    function updateToggleUi(theme) {
        var btn = document.getElementById('mzThemeToggle');
        if (!btn) {
            return;
        }
        var icon = btn.querySelector('i');
        var isDark = theme === 'dark';
        if (icon) {
            icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
        }
        btn.setAttribute('title', isDark ? 'Switch to light mode' : 'Switch to dark mode');
        btn.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
        btn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
    }

    function saveUrl() {
        if (window.MZ_ADMIN && window.MZ_ADMIN.themeSaveUrl) {
            return window.MZ_ADMIN.themeSaveUrl;
        }
        return 'ajax/save_theme_preference.php';
    }

    function persistTheme(theme) {
        if (!window.jQuery) {
            return;
        }
        window.jQuery.ajax({
            url: saveUrl(),
            method: 'POST',
            dataType: 'json',
            data: { theme: theme }
        });
    }

    function toggleTheme() {
        var next = getTheme() === 'dark' ? 'light' : 'dark';
        applyTheme(next, true);
        persistTheme(next);
    }

    window.MZAdminTheme = {
        get: getTheme,
        apply: applyTheme,
        toggle: toggleTheme
    };

    function syncThemeToServer() {
        if (!window.MZ_ADMIN || !window.MZ_ADMIN.loggedIn || !window.jQuery) {
            return;
        }
        var current = getTheme();
        var server = normalizeTheme(window.MZ_ADMIN.serverTheme || 'light');
        if (current !== server) {
            persistTheme(current);
            window.MZ_ADMIN.serverTheme = current;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        updateToggleUi(getTheme());
        syncThemeToServer();
        var btn = document.getElementById('mzThemeToggle');
        if (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                toggleTheme();
                if (window.MZ_ADMIN) {
                    window.MZ_ADMIN.serverTheme = getTheme();
                }
            });
        }
    });
})(window, document);
