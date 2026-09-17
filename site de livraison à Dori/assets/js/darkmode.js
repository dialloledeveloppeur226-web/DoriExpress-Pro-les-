/**
 * =============================================
 * JAVASCRIPT MODE SOMBRE - DoriExpress-Pro
 * =============================================
 * Fichier : assets/js/darkmode.js
 * Rôle : Gestion du mode sombre (toggle, persistance, détection système)
 * Niveau : Uber / Glovo / Deliveroo
 * =============================================
 */

'use strict';

// =============================================
// 1. ATTENDRE LE CHARGEMENT DU DOM
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ DoriExpress-Pro - Dark Mode chargé');
    initDarkMode();
});

// =============================================
// 2. INITIALISATION
// =============================================
let darkModeConfig = {
    enabled: false,
    autoDetect: true,
    persist: true
};

function initDarkMode() {
    // Charger la configuration
    const configEl = document.getElementById('darkmode-config');
    if (configEl) {
        darkModeConfig = JSON.parse(configEl.dataset.config || '{}');
    }

    // Détecter la préférence système
    if (darkModeConfig.autoDetect && window.matchMedia) {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');
        if (prefersDark.matches) {
            enableDarkMode();
        }
    }

    // Charger la préférence sauvegardée
    if (darkModeConfig.persist) {
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            enableDarkMode();
        }
    }

    // Écouter les changements système
    if (darkModeConfig.autoDetect && window.matchMedia) {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');
        prefersDark.addEventListener('change', function(e) {
            // Ne pas écraser la préférence utilisateur
            if (!localStorage.getItem('theme_user_choice')) {
                if (e.matches) {
                    enableDarkMode();
                } else {
                    disableDarkMode();
                }
            }
        });
    }

    // Initialiser les boutons de toggle
    initDarkModeToggle();
    initDarkModeSwitcher();
    initDarkModeSelect();

    // Initialiser les préférences dans le profil
    initDarkModePreferences();
}

// =============================================
// 3. ACTIVER LE MODE SOMBRE
// =============================================
function enableDarkMode() {
    document.documentElement.classList.add('dark-mode');
    darkModeConfig.enabled = true;

    // Mettre à jour les icônes
    updateDarkModeIcons(true);

    // Sauvegarder
    if (darkModeConfig.persist) {
        localStorage.setItem('theme', 'dark');
    }

    // Émettre un événement
    document.dispatchEvent(new CustomEvent('darkmode:enabled'));

    console.log('🌙 Mode sombre activé');
}

// =============================================
// 4. DÉSACTIVER LE MODE SOMBRE
// =============================================
function disableDarkMode() {
    document.documentElement.classList.remove('dark-mode');
    darkModeConfig.enabled = false;

    // Mettre à jour les icônes
    updateDarkModeIcons(false);

    // Sauvegarder
    if (darkModeConfig.persist) {
        localStorage.setItem('theme', 'light');
    }

    // Émettre un événement
    document.dispatchEvent(new CustomEvent('darkmode:disabled'));

    console.log('☀️ Mode clair activé');
}

// =============================================
// 5. TOGGLE MODE SOMBRE
// =============================================
function toggleDarkMode() {
    if (darkModeConfig.enabled) {
        disableDarkMode();
    } else {
        enableDarkMode();
    }

    // Marquer le choix utilisateur
    if (darkModeConfig.persist) {
        localStorage.setItem('theme_user_choice', 'true');
    }
}

// =============================================
// 6. METTRE À JOUR LES ICÔNES
// =============================================
function updateDarkModeIcons(isDark) {
    // Bouton de toggle
    document.querySelectorAll('.darkmode-icon, .theme-toggle-icon').forEach(icon => {
        icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
    });

    // Boutons avec texte
    document.querySelectorAll('.theme-toggle-text').forEach(el => {
        el.textContent = isDark ? 'Mode clair' : 'Mode sombre';
    });

    // Labels
    document.querySelectorAll('.theme-label').forEach(el => {
        el.textContent = isDark ? '🌙 Sombre' : '☀️ Clair';
    });
}

// =============================================
// 7. INITIALISER LE BOUTON DE TOGGLE
// =============================================
function initDarkModeToggle() {
    // Bouton principal (navbar)
    document.querySelectorAll('.btn-darkmode, .theme-toggle-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            toggleDarkMode();
        });
    });

    // Bouton dans le menu mobile
    document.querySelectorAll('.mobile-darkmode-toggle').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            toggleDarkMode();
            // Fermer le menu mobile si ouvert
            const mobileMenu = document.getElementById('mobile-menu');
            if (mobileMenu) {
                mobileMenu.classList.remove('active');
            }
        });
    });
}

// =============================================
// 8. INITIALISER LE SWITCHER
// =============================================
function initDarkModeSwitcher() {
    document.querySelectorAll('.theme-switcher').forEach(switcher => {
        const input = switcher.querySelector('input[type="checkbox"]');
        if (!input) return;

        // Mettre à jour l'état initial
        input.checked = darkModeConfig.enabled;

        input.addEventListener('change', function() {
            toggleDarkMode();
            this.checked = darkModeConfig.enabled;
        });
    });
}

// =============================================
// 9. INITIALISER LE SÉLECTEUR
// =============================================
function initDarkModeSelect() {
    document.querySelectorAll('.theme-select').forEach(select => {
        // Mettre à jour la valeur initiale
        select.value = darkModeConfig.enabled ? 'dark' : 'light';

        select.addEventListener('change', function() {
            if (this.value === 'dark') {
                enableDarkMode();
            } else if (this.value === 'light') {
                disableDarkMode();
            } else {
                // Auto - revenir à la détection système
                localStorage.removeItem('theme_user_choice');
                if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    enableDarkMode();
                } else {
                    disableDarkMode();
                }
            }
        });
    });
}

// =============================================
// 10. PRÉFÉRENCES DANS LE PROFIL
// =============================================
function initDarkModePreferences() {
    const form = document.getElementById('darkmode-preferences-form');
    if (!form) return;

    // Charger les préférences
    const autoDetectCheck = form.querySelector('[name="auto_detect"]');
    const persistCheck = form.querySelector('[name="persist"]');

    if (autoDetectCheck) {
        autoDetectCheck.checked = darkModeConfig.autoDetect;
    }
    if (persistCheck) {
        persistCheck.checked = darkModeConfig.persist;
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        if (autoDetectCheck) {
            darkModeConfig.autoDetect = autoDetectCheck.checked;
        }
        if (persistCheck) {
            darkModeConfig.persist = persistCheck.checked;
        }

        // Sauvegarder les préférences
        fetch('/api/user/preferences.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'darkmode_preferences',
                auto_detect: darkModeConfig.autoDetect ? 1 : 0,
                persist: darkModeConfig.persist ? 1 : 0,
                csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || ''
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('✅ Préférences sauvegardées', 'success');
            } else {
                showNotification('❌ Erreur', 'error');
            }
        })
        .catch(() => {
            showNotification('❌ Erreur réseau', 'error');
        });
    });
}

// =============================================
// 11. DÉTECTION DU MODE SOMBRE SYSTÈME
// =============================================
function detectSystemTheme() {
    if (!window.matchMedia) return 'light';
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

// =============================================
// 12. APPLIQUER LE THÈME
// =============================================
function applyTheme(theme) {
    if (theme === 'dark') {
        enableDarkMode();
    } else if (theme === 'light') {
        disableDarkMode();
    } else {
        // Auto
        const systemTheme = detectSystemTheme();
        if (systemTheme === 'dark') {
            enableDarkMode();
        } else {
            disableDarkMode();
        }
    }
}

// =============================================
// 13. NOTIFICATION
// =============================================
function showNotification(message, type = 'info') {
    const colors = {
        success: '#22c55e',
        error: '#ef4444',
        info: '#3b82f6',
        warning: '#f59e0b'
    };

    const div = document.createElement('div');
    div.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 15px 25px;
        background: ${colors[type] || colors.info};
        color: white;
        border-radius: 12px;
        font-weight: 600;
        z-index: 9999;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        animation: slideInRight 0.5s ease;
        max-width: 400px;
        font-family: inherit;
    `;
    div.textContent = message;
    document.body.appendChild(div);

    setTimeout(() => {
        div.style.animation = 'slideOutRight 0.5s ease';
        setTimeout(() => div.remove(), 500);
    }, 4000);
}

// =============================================
// 14. EXPOSER LES FONCTIONS GLOBALES
// =============================================
window.enableDarkMode = enableDarkMode;
window.disableDarkMode = disableDarkMode;
window.toggleDarkMode = toggleDarkMode;
window.applyTheme = applyTheme;
window.detectSystemTheme = detectSystemTheme;

// =============================================
// FIN DU FICHIER DARKMODE.JS
// =============================================