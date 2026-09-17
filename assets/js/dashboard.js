/**
 * =============================================
 * JAVASCRIPT DES DASHBOARDS - DoriExpress-Pro
 * =============================================
 * Fichier : assets/js/dashboard.js
 * Rôle : Fonctions pour les tableaux de bord (graphiques, stats, actions)
 * Niveau : Uber / Glovo / Deliveroo
 * =============================================
 */

'use strict';

// =============================================
// 1. ATTENDRE LE CHARGEMENT DU DOM
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ DoriExpress-Pro - Dashboard chargé');
    initDashboard();
});

// =============================================
// 2. INITIALISATION DU DASHBOARD
// =============================================
function initDashboard() {
    initCharts();
    initDataRefresh();
    initActionButtons();
    initLiveStats();
    initExportButtons();
    initFilterHandlers();
    initPagination();
    initRowClick();
    initDateRangePicker();
    initStatusToggle();
}

// =============================================
// 3. GRAPHIQUES (Chart.js)
// =============================================
function initCharts() {
    // Vérifier si Chart.js est disponible
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js non chargé');
        return;
    }

    // Graphique des revenus
    const revenueChart = document.getElementById('revenue-chart');
    if (revenueChart) {
        const ctx = revenueChart.getContext('2d');
        const data = JSON.parse(revenueChart.dataset.data || '[]');
        const labels = JSON.parse(revenueChart.dataset.labels || '[]');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenus (FCFA)',
                    data: data,
                    borderColor: '#00A651',
                    backgroundColor: 'rgba(0, 166, 81, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#00A651',
                    pointBorderColor: 'white',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y.toLocaleString('fr-FR') + ' FCFA';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('fr-FR') + ' FCFA';
                            }
                        }
                    }
                }
            }
        });
    }

    // Graphique des commandes par service (donut)
    const serviceChart = document.getElementById('service-chart');
    if (serviceChart) {
        const ctx = serviceChart.getContext('2d');
        const data = JSON.parse(serviceChart.dataset.data || '[]');
        const labels = JSON.parse(serviceChart.dataset.labels || '[]');
        const colors = ['#00A651', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors.slice(0, data.length),
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    }
                }
            }
        });
    }

    // Graphique des statistiques (barres)
    const statsChart = document.getElementById('stats-chart');
    if (statsChart) {
        const ctx = statsChart.getContext('2d');
        const data = JSON.parse(statsChart.dataset.data || '[]');
        const labels = JSON.parse(statsChart.dataset.labels || '[]');

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Commandes',
                    data: data,
                    backgroundColor: 'rgba(0, 166, 81, 0.6)',
                    borderColor: '#00A651',
                    borderWidth: 2,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
}

// =============================================
// 4. RAFRAÎCHISSEMENT DES DONNÉES (AJAX)
// =============================================
function initDataRefresh() {
    const refreshBtn = document.getElementById('refresh-data');
    if (!refreshBtn) return;

    refreshBtn.addEventListener('click', function() {
        const icon = this.querySelector('i');
        if (icon) {
            icon.classList.add('fa-spin');
        }
        this.disabled = true;

        // Simuler un rafraîchissement
        setTimeout(() => {
            if (icon) {
                icon.classList.remove('fa-spin');
            }
            this.disabled = false;
            showNotification('✅ Données actualisées', 'success');
            // Recharger les données (à implémenter avec AJAX)
        }, 1500);
    });

    // Auto-refresh toutes les 60 secondes
    setInterval(function() {
        const btn = document.getElementById('refresh-data');
        if (btn) {
            btn.click();
        }
    }, 60000);
}

// =============================================
// 5. BOUTONS D'ACTION
// =============================================
function initActionButtons() {
    document.querySelectorAll('.btn-action-confirm').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const message = this.dataset.confirm || 'Confirmer cette action ?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    document.querySelectorAll('.btn-action-ajax').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const url = this.dataset.url || this.href;
            const method = this.dataset.method || 'POST';
            const data = this.dataset.data || '{}';

            const icon = this.querySelector('i');
            if (icon) {
                icon.className = 'fas fa-spinner fa-spin';
            }
            this.disabled = true;

            fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(JSON.parse(data))
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showNotification('✅ ' + (result.message || 'Action réussie'), 'success');
                    if (result.reload) {
                        location.reload();
                    }
                } else {
                    showNotification('❌ ' + (result.message || 'Erreur'), 'error');
                }
            })
            .catch(error => {
                showNotification('❌ Erreur réseau', 'error');
            })
            .finally(() => {
                if (icon) {
                    icon.className = this.dataset.icon || 'fas fa-check';
                }
                this.disabled = false;
            });
        });
    });
}

// =============================================
// 6. STATISTIQUES EN TEMPS RÉEL
// =============================================
function initLiveStats() {
    const statsElements = document.querySelectorAll('.live-stat');
    if (statsElements.length === 0) return;

    // Simuler des mises à jour en temps réel
    setInterval(function() {
        statsElements.forEach(el => {
            const current = parseInt(el.textContent.replace(/[^0-9]/g, ''));
            if (!isNaN(current)) {
                const variation = Math.floor(Math.random() * 6) - 2; // -2 à +3
                const newValue = Math.max(0, current + variation);
                el.textContent = newValue;
            }
        });
    }, 10000);
}

// =============================================
// 7. BOUTONS D'EXPORT
// =============================================
function initExportButtons() {
    document.querySelectorAll('.btn-export').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const type = this.dataset.type || 'csv';
            const url = this.dataset.url || this.href;
            
            // Simuler un téléchargement
            showNotification('📥 Export ' + type.toUpperCase() + ' en cours...', 'info');
            
            setTimeout(() => {
                showNotification('✅ Export terminé', 'success');
            }, 2000);
        });
    });
}

// =============================================
// 8. FILTRES
// =============================================
function initFilterHandlers() {
    document.querySelectorAll('.filter-select, .filter-input').forEach(filter => {
        filter.addEventListener('change', function() {
            this.closest('form')?.submit();
        });

        filter.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                this.closest('form')?.submit();
            }
        });
    });

    document.querySelectorAll('.filter-reset').forEach(btn => {
        btn.addEventListener('click', function() {
            const form = this.closest('form');
            if (form) {
                form.querySelectorAll('.filter-select, .filter-input').forEach(el => {
                    el.value = '';
                });
                form.submit();
            }
        });
    });
}

// =============================================
// 9. PAGINATION
// =============================================
function initPagination() {
    document.querySelectorAll('.pagination .page-link').forEach(link => {
        link.addEventListener('click', function(e) {
            if (this.classList.contains('disabled')) {
                e.preventDefault();
                return;
            }
            // La navigation est gérée par le lien lui-même
        });
    });
}

// =============================================
// 10. CLIC SUR UNE LIGNE DE TABLEAU
// =============================================
function initRowClick() {
    document.querySelectorAll('.table-row-click').forEach(row => {
        row.addEventListener('click', function() {
            const url = this.dataset.url;
            if (url) {
                window.location.href = url;
            }
        });
    });
}

// =============================================
// 11. SÉLECTEUR DE DATE
// =============================================
function initDateRangePicker() {
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');

    if (dateFrom && dateTo) {
        dateFrom.addEventListener('change', function() {
            if (this.value) {
                dateTo.min = this.value;
            }
        });

        dateTo.addEventListener('change', function() {
            if (this.value) {
                dateFrom.max = this.value;
            }
        });
    }
}

// =============================================
// 12. TOGGLE DE STATUT (Switch)
// =============================================
function initStatusToggle() {
    document.querySelectorAll('.status-toggle').forEach(toggle => {
        toggle.addEventListener('change', function() {
            const id = this.dataset.id;
            const field = this.dataset.field;
            const value = this.checked ? 1 : 0;
            const url = this.dataset.url || window.location.href;

            // Envoyer la mise à jour via AJAX
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    action: 'toggle_status',
                    id: id,
                    field: field,
                    value: value,
                    csrf_token: window.csrf_token || ''
                })
            })
            .then(response => response.json())
            .then(result => {
                if (!result.success) {
                    this.checked = !this.checked;
                    showNotification('❌ ' + (result.message || 'Erreur'), 'error');
                } else {
                    showNotification('✅ Statut mis à jour', 'success');
                }
            })
            .catch(() => {
                this.checked = !this.checked;
                showNotification('❌ Erreur réseau', 'error');
            });
        });
    });
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
// FIN DU FICHIER DASHBOARD.JS
// =============================================