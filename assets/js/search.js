/**
 * =============================================
 * JAVASCRIPT RECHERCHE - DoriExpress-Pro
 * =============================================
 * Fichier : assets/js/search.js
 * Rôle : Moteur de recherche global avec suggestions et filtres
 * Niveau : Uber / Glovo / Deliveroo
 * =============================================
 */

'use strict';

// =============================================
// 1. ATTENDRE LE CHARGEMENT DU DOM
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ DoriExpress-Pro - Search chargé');
    initSearch();
});

// =============================================
// 2. INITIALISATION
// =============================================
let searchConfig = {
    minChars: 2,
    maxSuggestions: 10,
    debounceDelay: 300,
    cacheEnabled: true,
    cacheDuration: 60000 // 1 minute
};

let searchCache = {};
let searchTimeout = null;

function initSearch() {
    // Charger la configuration
    const configEl = document.getElementById('search-config');
    if (configEl) {
        searchConfig = JSON.parse(configEl.dataset.config || '{}');
    }

    initSearchInput();
    initSearchFilters();
    initSearchHistory();
    initVoiceSearch();
    initSearchShortcuts();
    initSearchReset();
}

// =============================================
// 3. INPUT DE RECHERCHE
// =============================================
function initSearchInput() {
    const searchInput = document.getElementById('search-input');
    const suggestions = document.getElementById('search-suggestions');

    if (!searchInput) return;

    // Recherche en temps réel
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();

        clearTimeout(searchTimeout);

        if (query.length < searchConfig.minChars) {
            suggestions?.classList.remove('active');
            return;
        }

        searchTimeout = setTimeout(() => {
            performSearch(query);
        }, searchConfig.debounceDelay);
    });

    // Recherche avec Entrée
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const query = this.value.trim();
            if (query.length >= searchConfig.minChars) {
                performSearch(query, true);
            }
        }

        // Navigation dans les suggestions
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            const items = suggestions?.querySelectorAll('.suggestion-item');
            if (!items || items.length === 0) return;

            let currentIndex = -1;
            items.forEach((item, index) => {
                if (item.classList.contains('active')) {
                    currentIndex = index;
                    item.classList.remove('active');
                }
            });

            let newIndex = e.key === 'ArrowDown' 
                ? Math.min(currentIndex + 1, items.length - 1)
                : Math.max(currentIndex - 1, 0);

            items[newIndex].classList.add('active');
            items[newIndex].scrollIntoView({ block: 'nearest' });
        }

        if (e.key === 'Escape') {
            suggestions?.classList.remove('active');
        }
    });

    // Fermer les suggestions au clic en dehors
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.search-container')) {
            suggestions?.classList.remove('active');
        }
    });

    // Focus sur le champ avec Ctrl+K ou Cmd+K
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            searchInput.focus();
            searchInput.select();
        }
    });
}

// =============================================
// 4. EFFECTUER UNE RECHERCHE
// =============================================
function performSearch(query, forceSubmit = false) {
    const suggestions = document.getElementById('search-suggestions');

    // Vérifier le cache
    if (searchConfig.cacheEnabled && searchCache[query]) {
        const cached = searchCache[query];
        if (Date.now() - cached.timestamp < searchConfig.cacheDuration) {
            displaySuggestions(cached.results);
            return;
        }
    }

    // Appel AJAX
    fetch(`/api/search.php?q=${encodeURIComponent(query)}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mettre en cache
            if (searchConfig.cacheEnabled) {
                searchCache[query] = {
                    results: data.data,
                    timestamp: Date.now()
                };
            }

            if (forceSubmit && data.data.length > 0) {
                // Rediriger vers la page de résultats
                window.location.href = `/recherche.php?q=${encodeURIComponent(query)}`;
                return;
            }

            displaySuggestions(data.data);
        }
    })
    .catch(error => {
        console.error('Erreur recherche:', error);
    });
}

// =============================================
// 5. AFFICHER LES SUGGESTIONS
// =============================================
function displaySuggestions(results) {
    const container = document.getElementById('search-suggestions');
    if (!container) return;

    if (!results || results.length === 0) {
        container.innerHTML = `
            <div class="suggestion-empty">
                <i class="fas fa-search"></i>
                <span>Aucun résultat trouvé</span>
            </div>
        `;
        container.classList.add('active');
        return;
    }

    const maxItems = searchConfig.maxSuggestions;
    const items = results.slice(0, maxItems);

    let html = '';
    let hasCategories = false;

    // Grouper par catégorie
    const grouped = {};
    items.forEach(item => {
        const category = item.category || 'general';
        if (!grouped[category]) {
            grouped[category] = [];
        }
        grouped[category].push(item);
    });

    // Construire l'HTML
    Object.keys(grouped).forEach(category => {
        if (grouped[category].length > 0) {
            hasCategories = true;
            const label = getCategoryLabel(category);
            html += `<div class="suggestion-category">${label}</div>`;
            grouped[category].forEach(item => {
                html += `
                    <div class="suggestion-item" data-url="${item.url || '#'}" data-id="${item.id || ''}">
                        ${item.icon ? `<i class="fas ${item.icon}"></i>` : ''}
                        <span>${highlightText(item.label, document.getElementById('search-input')?.value || '')}</span>
                        ${item.subtitle ? `<small>${item.subtitle}</small>` : ''}
                    </div>
                `;
            });
        }
    });

    if (!hasCategories) {
        items.forEach(item => {
            html += `
                <div class="suggestion-item" data-url="${item.url || '#'}" data-id="${item.id || ''}">
                    ${item.icon ? `<i class="fas ${item.icon}"></i>` : ''}
                    <span>${highlightText(item.label, document.getElementById('search-input')?.value || '')}</span>
                    ${item.subtitle ? `<small>${item.subtitle}</small>` : ''}
                </div>
            `;
        });
    }

    // Ajouter "Voir tous les résultats"
    const query = document.getElementById('search-input')?.value || '';
    html += `
        <div class="suggestion-see-all" data-query="${query}">
            <i class="fas fa-arrow-right"></i>
            Voir tous les résultats pour "${query}"
        </div>
    `;

    container.innerHTML = html;
    container.classList.add('active');

    // Gérer les clics sur les suggestions
    container.querySelectorAll('.suggestion-item').forEach(item => {
        item.addEventListener('click', function() {
            const url = this.dataset.url;
            if (url) {
                window.location.href = url;
            }
        });
    });

    // Gérer le clic sur "Voir tous"
    const seeAll = container.querySelector('.suggestion-see-all');
    if (seeAll) {
        seeAll.addEventListener('click', function() {
            const query = this.dataset.query;
            if (query) {
                window.location.href = `/recherche.php?q=${encodeURIComponent(query)}`;
            }
        });
    }
}

// =============================================
// 6. GET CATEGORY LABEL
// =============================================
function getCategoryLabel(category) {
    const labels = {
        'commandes': '📦 Commandes',
        'produits': '🛍️ Produits',
        'partenaires': '🏪 Partenaires',
        'articles': '📝 Articles',
        'livreurs': '🛵 Livreurs',
        'clients': '👤 Clients',
        'general': '📌 Résultats'
    };
    return labels[category] || category;
}

// =============================================
// 7. HIGHLIGHT TEXT
// =============================================
function highlightText(text, query) {
    if (!query || !text) return text;
    const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    return text.replace(regex, '<mark>$1</mark>');
}

// =============================================
// 8. FILTRES DE RECHERCHE
// =============================================
function initSearchFilters() {
    document.querySelectorAll('.search-filter').forEach(filter => {
        filter.addEventListener('change', function() {
            const form = this.closest('form');
            if (form) {
                form.submit();
            }
        });
    });

    // Filtres avancés (toggle)
    document.querySelectorAll('.filter-toggle').forEach(toggle => {
        toggle.addEventListener('click', function() {
            const target = document.querySelector(this.dataset.target);
            if (target) {
                target.classList.toggle('visible');
                this.classList.toggle('active');
            }
        });
    });
}

// =============================================
// 9. HISTORIQUE DE RECHERCHE
// =============================================
function initSearchHistory() {
    const container = document.getElementById('search-history');
    if (!container) return;

    // Charger l'historique
    let history = JSON.parse(localStorage.getItem('search_history') || '[]');

    // Limiter à 20 entrées
    history = history.slice(0, 20);

    if (history.length === 0) {
        container.style.display = 'none';
        return;
    }

    container.style.display = 'block';
    container.innerHTML = `
        <div class="search-history-header">
            <span>🕐 Historique</span>
            <button class="clear-history">Effacer</button>
        </div>
        ${history.map(item => `
            <div class="history-item" data-query="${item}">
                <i class="fas fa-clock"></i>
                <span>${item}</span>
                <button class="remove-history" data-query="${item}">×</button>
            </div>
        `).join('')}
    `;

    // Gérer les clics sur l'historique
    container.querySelectorAll('.history-item').forEach(item => {
        item.addEventListener('click', function(e) {
            if (e.target.closest('.remove-history')) return;
            const query = this.dataset.query;
            const input = document.getElementById('search-input');
            if (input) {
                input.value = query;
                performSearch(query, true);
            }
        });
    });

    // Supprimer un élément de l'historique
    container.querySelectorAll('.remove-history').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const query = this.dataset.query;
            let history = JSON.parse(localStorage.getItem('search_history') || '[]');
            history = history.filter(item => item !== query);
            localStorage.setItem('search_history', JSON.stringify(history));
            this.closest('.history-item').remove();
            if (history.length === 0) {
                container.style.display = 'none';
            }
        });
    });

    // Effacer tout l'historique
    container.querySelector('.clear-history')?.addEventListener('click', function() {
        localStorage.removeItem('search_history');
        container.style.display = 'none';
    });

    // Ajouter une recherche à l'historique
    window.addToSearchHistory = function(query) {
        let history = JSON.parse(localStorage.getItem('search_history') || '[]');
        history = history.filter(item => item !== query);
        history.unshift(query);
        if (history.length > 20) {
            history = history.slice(0, 20);
        }
        localStorage.setItem('search_history', JSON.stringify(history));
    };
}

// =============================================
// 10. RECHERCHE VOCALE
// =============================================
function initVoiceSearch() {
    const voiceBtn = document.getElementById('voice-search-btn');
    if (!voiceBtn) return;

    if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
        voiceBtn.style.display = 'none';
        return;
    }

    voiceBtn.addEventListener('click', function() {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        const recognition = new SpeechRecognition();

        recognition.lang = 'fr-FR';
        recognition.continuous = false;
        recognition.interimResults = false;

        this.classList.add('listening');
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        recognition.onresult = function(event) {
            const transcript = event.results[0][0].transcript;
            const input = document.getElementById('search-input');
            if (input) {
                input.value = transcript;
                performSearch(transcript, true);
            }
            voiceBtn.classList.remove('listening');
            voiceBtn.innerHTML = '<i class="fas fa-microphone"></i>';
        };

        recognition.onerror = function() {
            voiceBtn.classList.remove('listening');
            voiceBtn.innerHTML = '<i class="fas fa-microphone"></i>';
            showNotification('❌ Erreur de reconnaissance vocale', 'error');
        };

        recognition.start();
    });
}

// =============================================
// 11. RACCOURCIS CLAVIER
// =============================================
function initSearchShortcuts() {
    // Ctrl+Shift+F pour focus recherche
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.shiftKey && e.key === 'F') {
            e.preventDefault();
            const input = document.getElementById('search-input');
            if (input) {
                input.focus();
                input.select();
            }
        }
    });
}

// =============================================
// 12. RÉINITIALISER LA RECHERCHE
// =============================================
function initSearchReset() {
    const resetBtn = document.getElementById('search-reset');
    if (!resetBtn) return;

    resetBtn.addEventListener('click', function() {
        const input = document.getElementById('search-input');
        if (input) {
            input.value = '';
            input.focus();
            const suggestions = document.getElementById('search-suggestions');
            if (suggestions) {
                suggestions.classList.remove('active');
            }
        }
    });

    // Afficher le bouton reset quand il y a du texte
    const input = document.getElementById('search-input');
    if (input) {
        input.addEventListener('input', function() {
            if (resetBtn) {
                resetBtn.style.display = this.value.length > 0 ? 'flex' : 'none';
            }
        });
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
window.performSearch = performSearch;
window.addToSearchHistory = addToSearchHistory;
window.highlightText = highlightText;

// =============================================
// FIN DU FICHIER SEARCH.JS
// =============================================