/**
 * =============================================
 * JAVASCRIPT PRINCIPAL - DoriExpress-Pro
 * =============================================
 * Fichier : assets/js/app.js
 * Rôle : Fonctions globales, interactions utilisateur, utilitaires
 * Niveau : Uber / Glovo / Deliveroo
 * =============================================
 */

// =============================================
// 1. STRICT MODE
// =============================================
'use strict';

// =============================================
// 2. ATTENDRE LE CHARGEMENT DU DOM
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ DoriExpress-Pro - Application chargée');
    initApp();
});

// =============================================
// 3. INITIALISATION DE L'APPLICATION
// =============================================
function initApp() {
    initMobileMenu();
    initDropdowns();
    initScrollToTop();
    initCounters();
    initFormValidation();
    initTooltips();
    initSmoothScroll();
    initThemeToggle();
    initNotificationSystem();
    initSearchAutocomplete();
    initPasswordToggle();
    initModalHandlers();
    initFlashMessages();
    initBackToTop();
    initLazyLoading();
}

// =============================================
// 4. MENU MOBILE
// =============================================
function initMobileMenu() {
    const toggle = document.getElementById('navbar-toggle');
    const menu = document.getElementById('mobile-menu');
    const overlay = document.getElementById('mobile-overlay');
    const close = document.getElementById('mobile-menu-close');

    if (!toggle || !menu || !overlay) return;

    function openMenu() {
        menu.classList.add('active');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        toggle.setAttribute('aria-expanded', 'true');
    }

    function closeMenu() {
        menu.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
        toggle.setAttribute('aria-expanded', 'false');
    }

    toggle.addEventListener('click', openMenu);
    if (close) close.addEventListener('click', closeMenu);
    overlay.addEventListener('click', closeMenu);

    // Fermer avec Echap
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && menu.classList.contains('active')) {
            closeMenu();
        }
    });

    // Fermer en redimensionnant
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768 && menu.classList.contains('active')) {
            closeMenu();
        }
    });
}

// =============================================
// 5. DROPDOWNS
// =============================================
function initDropdowns() {
    document.querySelectorAll('.dropdown').forEach(dropdown => {
        const trigger = dropdown.querySelector('.dropdown-toggle');
        const menu = dropdown.querySelector('.dropdown-menu');

        if (!trigger || !menu) return;

        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            menu.classList.toggle('show');
        });

        // Fermer au clic en dehors
        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target)) {
                menu.classList.remove('show');
            }
        });

        // Fermer avec Echap
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && menu.classList.contains('show')) {
                menu.classList.remove('show');
            }
        });
    });
}

// =============================================
// 6. SCROLL TO TOP
// =============================================
function initScrollToTop() {
    const btn = document.getElementById('scroll-top');
    if (!btn) return;

    window.addEventListener('scroll', function() {
        if (window.scrollY > 300) {
            btn.style.display = 'flex';
        } else {
            btn.style.display = 'none';
        }
    });

    btn.addEventListener('click', function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

// =============================================
// 7. COMPTEURS ANIMÉS
// =============================================
function initCounters() {
    const counters = document.querySelectorAll('.counter');
    if (counters.length === 0) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const target = parseInt(entry.target.getAttribute('data-target'));
                const duration = 2000;
                const step = target / (duration / 16);
                let current = 0;

                const timer = setInterval(() => {
                    current += step;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    entry.target.textContent = Math.floor(current).toLocaleString('fr-FR');
                }, 16);

                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });

    counters.forEach(counter => observer.observe(counter));
}

// =============================================
// 8. VALIDATION DE FORMULAIRES
// =============================================
function initFormValidation() {
    document.querySelectorAll('form[data-validate]').forEach(form => {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            const requiredFields = this.querySelectorAll('[required]');

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                    showFieldError(field, 'Ce champ est obligatoire');
                } else {
                    field.classList.remove('error');
                    removeFieldError(field);
                }

                // Validation spécifique email
                if (field.type === 'email' && field.value.trim()) {
                    if (!isValidEmail(field.value.trim())) {
                        isValid = false;
                        field.classList.add('error');
                        showFieldError(field, 'Adresse email invalide');
                    }
                }

                // Validation spécifique téléphone
                if (field.type === 'tel' && field.value.trim()) {
                    if (!isValidPhone(field.value.trim())) {
                        isValid = false;
                        field.classList.add('error');
                        showFieldError(field, 'Numéro de téléphone invalide');
                    }
                }
            });

            if (!isValid) {
                e.preventDefault();
                const firstError = this.querySelector('.error');
                if (firstError) {
                    firstError.focus();
                }
            }
        });

        // Nettoyer les erreurs au focus
        form.querySelectorAll('input, select, textarea').forEach(field => {
            field.addEventListener('focus', function() {
                this.classList.remove('error');
                removeFieldError(this);
            });
        });
    });
}

function showFieldError(field, message) {
    let errorDiv = field.parentElement.querySelector('.field-error');
    if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        errorDiv.style.cssText = 'color: #ef4444; font-size: 13px; margin-top: 4px;';
        field.parentElement.appendChild(errorDiv);
    }
    errorDiv.textContent = message;
}

function removeFieldError(field) {
    const errorDiv = field.parentElement.querySelector('.field-error');
    if (errorDiv) {
        errorDiv.remove();
    }
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function isValidPhone(phone) {
    return /^[0-9]{8}$/.test(phone.replace(/[^0-9]/g, ''));
}

// =============================================
// 9. TOOLTIPS
// =============================================
function initTooltips() {
    document.querySelectorAll('[data-tooltip]').forEach(el => {
        el.addEventListener('mouseenter', function(e) {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip-custom';
            tooltip.textContent = this.getAttribute('data-tooltip');
            tooltip.style.cssText = `
                position: absolute;
                background: #1a1a1a;
                color: white;
                padding: 6px 12px;
                border-radius: 6px;
                font-size: 12px;
                font-weight: 500;
                z-index: 1000;
                white-space: nowrap;
                pointer-events: none;
                transform: translateX(-50%);
            `;
            const rect = this.getBoundingClientRect();
            tooltip.style.left = rect.left + rect.width / 2 + 'px';
            tooltip.style.top = rect.top - 35 + 'px';
            document.body.appendChild(tooltip);
            this._tooltip = tooltip;
        });

        el.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.remove();
                this._tooltip = null;
            }
        });
    });
}

// =============================================
// 10. SMOOTH SCROLL
// =============================================
function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            const target = document.querySelector(targetId);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });
}

// =============================================
// 11. THEME TOGGLE (MODE SOMBRE)
// =============================================
function initThemeToggle() {
    const darkModeBtn = document.querySelector('.btn-darkmode');
    if (!darkModeBtn) return;

    darkModeBtn.addEventListener('click', function() {
        document.documentElement.classList.toggle('dark-mode');
        const isDark = document.documentElement.classList.contains('dark-mode');
        const icon = this.querySelector('i');
        if (icon) {
            icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
        }

        // Sauvegarder la préférence
        try {
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        } catch (e) {
            // Ignorer
        }
    });

    // Charger la préférence
    try {
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.documentElement.classList.add('dark-mode');
            const icon = darkModeBtn.querySelector('i');
            if (icon) {
                icon.className = 'fas fa-sun';
            }
        }
    } catch (e) {
        // Ignorer
    }
}

// =============================================
// 12. SYSTÈME DE NOTIFICATIONS
// =============================================
function initNotificationSystem() {
    // Les notifications sont gérées par la fonction globale showNotification
    // définie dans les pages individuelles
    window.showNotification = function(message, type = 'info') {
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
    };
}

// =============================================
// 13. RECHERCHE AUTCOMPLETE
// =============================================
function initSearchAutocomplete() {
    const searchInput = document.querySelector('.search-input');
    const suggestions = document.getElementById('search-suggestions');

    if (!searchInput || !suggestions) return;

    let debounceTimer;

    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const query = this.value.trim();

        if (query.length < 2) {
            suggestions.classList.remove('active');
            return;
        }

        debounceTimer = setTimeout(() => {
            // Simuler des suggestions (à remplacer par appel AJAX)
            const mockSuggestions = [
                'Livraison colis',
                'Livraison repas',
                'Restaurants à Dori',
                'Boutiques Dori',
                'Suivi colis',
                'Tarifs livraison',
                'Devenir livreur'
            ];

            const filtered = mockSuggestions.filter(item =>
                item.toLowerCase().includes(query.toLowerCase())
            );

            if (filtered.length > 0) {
                suggestions.innerHTML = filtered.map(item =>
                    `<div class="suggestion-item" data-value="${item}">${item}</div>`
                ).join('');
                suggestions.classList.add('active');

                suggestions.querySelectorAll('.suggestion-item').forEach(item => {
                    item.addEventListener('click', function() {
                        searchInput.value = this.dataset.value;
                        suggestions.classList.remove('active');
                        searchInput.closest('form').submit();
                    });
                });
            } else {
                suggestions.classList.remove('active');
            }
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.search-wrapper')) {
            suggestions.classList.remove('active');
        }
    });
}

// =============================================
// 14. TOGGLE PASSWORD
// =============================================
function initPasswordToggle() {
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input');
            const icon = this.querySelector('i');
            if (input && icon) {
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            }
        });
    });
}

// =============================================
// 15. MODALS
// =============================================
function initModalHandlers() {
    document.querySelectorAll('[data-modal]').forEach(trigger => {
        trigger.addEventListener('click', function() {
            const modalId = this.dataset.modal;
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        });
    });

    document.querySelectorAll('.modal-close, .modal-overlay').forEach(el => {
        el.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.active').forEach(modal => {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            });
        }
    });
}

// =============================================
// 16. FLASH MESSAGES
// =============================================
function initFlashMessages() {
    const flashMessages = document.querySelectorAll('.flash-message');
    flashMessages.forEach(msg => {
        setTimeout(() => {
            msg.style.opacity = '0';
            msg.style.transform = 'translateY(-20px)';
            setTimeout(() => msg.remove(), 500);
        }, 5000);

        // Bouton de fermeture
        const closeBtn = msg.querySelector('.flash-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                msg.remove();
            });
        }
    });
}

// =============================================
// 17. BACK TO TOP (SCROLL)
// =============================================
function initBackToTop() {
    const btn = document.getElementById('back-to-top');
    if (!btn) return;

    window.addEventListener('scroll', function() {
        if (window.scrollY > 500) {
            btn.classList.add('visible');
        } else {
            btn.classList.remove('visible');
        }
    });

    btn.addEventListener('click', function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

// =============================================
// 18. LAZY LOADING DES IMAGES
// =============================================
function initLazyLoading() {
    if ('IntersectionObserver' in window) {
        const images = document.querySelectorAll('img[data-src]');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    observer.unobserve(img);
                }
            });
        });

        images.forEach(img => observer.observe(img));
    }
}

// =============================================
// 19. EXPOSER LES FONCTIONS GLOBALES
// =============================================
window.toggleDarkMode = function() {
    const btn = document.querySelector('.btn-darkmode');
    if (btn) btn.click();
};

window.showNotification = function(message, type = 'info') {
    // Surchargée par initNotificationSystem
};

// =============================================
// 20. GESTION DES COOKIES (Consentement)
// =============================================
function initCookieConsent() {
    const consent = document.getElementById('cookies-consent');
    if (!consent) return;

    if (localStorage.getItem('cookies_consent')) {
        consent.style.display = 'none';
        return;
    }

    consent.style.display = 'block';

    document.getElementById('cookies-accept')?.addEventListener('click', function() {
        localStorage.setItem('cookies_consent', 'accepted');
        consent.style.display = 'none';
    });

    document.getElementById('cookies-refuse')?.addEventListener('click', function() {
        localStorage.setItem('cookies_consent', 'refused');
        consent.style.display = 'none';
    });
}

// Appeler au chargement
document.addEventListener('DOMContentLoaded', initCookieConsent);

// =============================================
// 21. GESTION DU RÉSEAU (Connexion)
// =============================================
function initNetworkStatus() {
    window.addEventListener('online', function() {
        showNotification('✅ Connexion internet rétablie', 'success');
    });

    window.addEventListener('offline', function() {
        showNotification('⚠️ Connexion internet perdue', 'warning');
    });
}

document.addEventListener('DOMContentLoaded', initNetworkStatus);

// =============================================
// FIN DU FICHIER APP.JS
// =============================================