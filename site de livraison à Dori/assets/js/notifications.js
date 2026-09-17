/**
 * =============================================
 * JAVASCRIPT NOTIFICATIONS - DoriExpress-Pro
 * =============================================
 * Fichier : assets/js/notifications.js
 * Rôle : Gestion des notifications en temps réel (Web, Push, Alertes)
 * Niveau : Uber / Glovo / Deliveroo
 * =============================================
 */

'use strict';

// =============================================
// 1. ATTENDRE LE CHARGEMENT DU DOM
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ DoriExpress-Pro - Notifications chargé');
    initNotifications();
});

// =============================================
// 2. INITIALISATION
// =============================================
let notifConfig = {
    userId: 0,
    lastNotifId: 0,
    pollingInterval: 10000,
    soundEnabled: true,
    pushEnabled: false,
    toastDuration: 5000
};

function initNotifications() {
    // Récupérer les configurations
    const configEl = document.getElementById('notif-config');
    if (configEl) {
        notifConfig = JSON.parse(configEl.dataset.config || '{}');
    }

    // Initialiser le service worker pour les push notifications
    if ('serviceWorker' in navigator && notifConfig.pushEnabled) {
        initPushNotifications();
    }

    // Démarrer le polling pour les nouvelles notifications
    startPolling();

    // Initialiser le compteur de notifications
    updateBadge();

    // Gérer les clics sur les notifications
    initNotificationClick();

    // Initialiser les préférences de notification
    initPreferences();

    // Demander la permission pour les notifications (si push activé)
    if (notifConfig.pushEnabled && 'Notification' in window) {
        if (Notification.permission === 'default') {
            Notification.requestPermission();
        }
    }

    // Marquer toutes comme lues
    const markAllBtn = document.getElementById('mark-all-read');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function() {
            markAllAsRead();
        });
    }
}

// =============================================
// 3. POLLING DES NOTIFICATIONS
// =============================================
function startPolling() {
    if (notifConfig.userId <= 0) return;

    pollNotifications();
    setInterval(pollNotifications, notifConfig.pollingInterval);
}

function pollNotifications() {
    fetch(`/api/notifications/poll.php?last=${notifConfig.lastNotifId}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.notifications && data.notifications.length > 0) {
            data.notifications.forEach(notif => {
                if (notif.id > notifConfig.lastNotifId) {
                    displayNotification(notif);
                    if (notifConfig.soundEnabled) {
                        playNotificationSound();
                    }
                }
            });

            // Mettre à jour le dernier ID
            if (data.notifications.length > 0) {
                notifConfig.lastNotifId = data.notifications[data.notifications.length - 1].id;
            }

            // Mettre à jour le badge
            if (data.total_non_lus !== undefined) {
                updateBadge(data.total_non_lus);
            }
        }
    })
    .catch(error => {
        // Silencieux
    });
}

// =============================================
// 4. AFFICHER UNE NOTIFICATION
// =============================================
function displayNotification(notif) {
    // 1. Toast / Alert dans l'interface
    showToast(notif);

    // 2. Push notification (si activé)
    if (notifConfig.pushEnabled && 'Notification' in window && Notification.permission === 'granted') {
        const options = {
            body: notif.message,
            icon: '/assets/images/logo-192x192.png',
            badge: '/assets/images/badge-72x72.png',
            vibrate: [200, 100, 200],
            data: {
                url: notif.lien || '/notifications.php'
            }
        };
        const pushNotif = new Notification(notif.titre, options);
        pushNotif.onclick = function() {
            window.focus();
            this.close();
            if (notif.lien) {
                window.location.href = notif.lien;
            }
        };
        setTimeout(() => pushNotif.close(), 10000);
    }

    // 3. Son (si activé)
    if (notifConfig.soundEnabled) {
        playNotificationSound();
    }

    // 4. Ajouter à la liste des notifications
    addToNotificationList(notif);
}

// =============================================
// 5. TOAST NOTIFICATION
// =============================================
function showToast(notif) {
    const container = document.getElementById('toast-container');
    if (!container) {
        // Créer le conteneur s'il n'existe pas
        const newContainer = document.createElement('div');
        newContainer.id = 'toast-container';
        newContainer.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 380px;
            width: 100%;
        `;
        document.body.appendChild(newContainer);
    }

    const toast = document.createElement('div');
    const typeColors = {
        'commande': '#3b82f6',
        'paiement': '#22c55e',
        'livraison': '#f59e0b',
        'promotion': '#ec4899',
        'message': '#8b5cf6',
        'securite': '#ef4444',
        'systeme': '#6b7280'
    };
    const color = typeColors[notif.type] || '#00A651';

    toast.style.cssText = `
        background: white;
        border-radius: 12px;
        padding: 16px 18px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        border-left: 4px solid ${color};
        animation: slideInRight 0.5s ease;
        display: flex;
        gap: 12px;
        align-items: flex-start;
        cursor: pointer;
        transition: all 0.3s ease;
    `;

    toast.innerHTML = `
        <div class="toast-icon" style="flex-shrink:0; width:36px; height:36px; border-radius:50%; background:${color}20; display:flex; align-items:center; justify-content:center; color:${color}; font-size:18px;">
            <i class="fas ${getIconForType(notif.type)}"></i>
        </div>
        <div style="flex:1; min-width:0;">
            <div style="font-weight:700; font-size:14px; color:#1a1a1a;">${escapeHtml(notif.titre)}</div>
            <div style="font-size:13px; color:#6b7280; margin-top:2px;">${escapeHtml(notif.message)}</div>
            <div style="font-size:11px; color:#9ca3af; margin-top:4px;">${timeAgo(notif.date_creation)}</div>
        </div>
        <button class="toast-close" style="background:transparent; border:none; color:#9ca3af; cursor:pointer; padding:4px; font-size:16px;">
            <i class="fas fa-times"></i>
        </button>
    `;

    // Conteneur
    const containerEl = document.getElementById('toast-container') || document.body;
    containerEl.appendChild(toast);

    // Gérer le clic sur la notification
    toast.addEventListener('click', function(e) {
        if (e.target.closest('.toast-close')) return;
        if (notif.lien) {
            window.location.href = notif.lien;
        } else {
            this.remove();
        }
    });

    // Fermer
    toast.querySelector('.toast-close').addEventListener('click', function(e) {
        e.stopPropagation();
        toast.remove();
    });

    // Auto-fermeture
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(50px)';
        setTimeout(() => toast.remove(), 500);
    }, notifConfig.toastDuration);
}

// =============================================
// 6. ICÔNE PAR TYPE
// =============================================
function getIconForType(type) {
    const icons = {
        'commande': 'fa-shopping-cart',
        'paiement': 'fa-credit-card',
        'livraison': 'fa-truck',
        'promotion': 'fa-tag',
        'message': 'fa-envelope',
        'securite': 'fa-shield-alt',
        'systeme': 'fa-cog'
    };
    return icons[type] || 'fa-bell';
}

// =============================================
// 7. SON DE NOTIFICATION
// =============================================
function playNotificationSound() {
    try {
        const audio = new Audio('/assets/sounds/notification.mp3');
        audio.volume = 0.5;
        audio.play().catch(() => {
            // Ignorer si le son ne peut pas être joué
        });
    } catch (e) {
        // Ignorer
    }
}

// =============================================
// 8. BADGE DE NOTIFICATION
// =============================================
function updateBadge(count) {
    const badgeElements = document.querySelectorAll('.notif-badge, .badge-notification');

    if (count !== undefined) {
        notifConfig.lastNotifId = count;
    }

    // Récupérer le nombre de notifications non lues depuis le serveur
    fetch('/api/notifications/count.php', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        const total = data.count || 0;
        badgeElements.forEach(badge => {
            if (total > 0) {
                badge.textContent = total;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        });

        // Mettre à jour le titre de la page
        if (total > 0) {
            document.title = `(${total}) ${document.title.replace(/^\(\d+\)\s*/, '')}`;
        } else {
            document.title = document.title.replace(/^\(\d+\)\s*/, '');
        }
    })
    .catch(() => {
        // Silencieux
    });
}

// =============================================
// 9. AJOUTER À LA LISTE DES NOTIFICATIONS
// =============================================
function addToNotificationList(notif) {
    const list = document.getElementById('notif-list');
    if (!list) return;

    const item = document.createElement('div');
    item.className = `notif-item ${notif.est_lu ? '' : 'unread'}`;
    item.dataset.id = notif.id;

    const typeColors = {
        'commande': '#3b82f6',
        'paiement': '#22c55e',
        'livraison': '#f59e0b',
        'promotion': '#ec4899',
        'message': '#8b5cf6',
        'securite': '#ef4444',
        'systeme': '#6b7280'
    };
    const color = typeColors[notif.type] || '#00A651';

    item.innerHTML = `
        <div class="notif-icon" style="background:${color}20; color:${color};">
            <i class="fas ${getIconForType(notif.type)}"></i>
        </div>
        <div class="notif-content">
            <div class="notif-title">${escapeHtml(notif.titre)}</div>
            <div class="notif-message">${escapeHtml(notif.message)}</div>
            <div class="notif-time">${timeAgo(notif.date_creation)}</div>
        </div>
        <div class="notif-status">
            <span class="badge ${notif.est_lu ? 'bg-secondary' : 'bg-danger'}">
                ${notif.est_lu ? 'Lu' : 'Non lu'}
            </span>
        </div>
    `;

    list.prepend(item);

    // Gérer le clic pour marquer comme lu
    item.addEventListener('click', function() {
        const id = this.dataset.id;
        markAsRead(id);
    });
}

// =============================================
// 10. MARQUER COMME LU
// =============================================
function markAsRead(notifId) {
    fetch('/api/notifications/read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            id: notifId,
            csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || ''
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const item = document.querySelector(`.notif-item[data-id="${notifId}"]`);
            if (item) {
                item.classList.remove('unread');
                const badge = item.querySelector('.badge');
                if (badge) {
                    badge.className = 'badge bg-secondary';
                    badge.textContent = 'Lu';
                }
            }
            updateBadge();
        }
    })
    .catch(() => {});
}

// =============================================
// 11. MARQUER TOUTES COMME LUES
// =============================================
function markAllAsRead() {
    fetch('/api/notifications/read-all.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || ''
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.querySelectorAll('.notif-item.unread').forEach(item => {
                item.classList.remove('unread');
                const badge = item.querySelector('.badge');
                if (badge) {
                    badge.className = 'badge bg-secondary';
                    badge.textContent = 'Lu';
                }
            });
            updateBadge();
            showNotification('✅ Toutes les notifications ont été marquées comme lues', 'success');
        }
    })
    .catch(() => {});
}

// =============================================
// 12. CLIC SUR NOTIFICATION (REDIRECTION)
// =============================================
function initNotificationClick() {
    document.addEventListener('click', function(e) {
        const notifItem = e.target.closest('.notif-item');
        if (notifItem && notifItem.dataset.url) {
            window.location.href = notifItem.dataset.url;
        }
    });
}

// =============================================
// 13. PRÉFÉRENCES DE NOTIFICATION
// =============================================
function initPreferences() {
    // Toggle son
    const soundToggle = document.getElementById('notif-sound-toggle');
    if (soundToggle) {
        soundToggle.addEventListener('change', function() {
            notifConfig.soundEnabled = this.checked;
            localStorage.setItem('notif_sound', this.checked ? '1' : '0');
        });
        // Charger la préférence
        const saved = localStorage.getItem('notif_sound');
        if (saved !== null) {
            soundToggle.checked = saved === '1';
            notifConfig.soundEnabled = soundToggle.checked;
        }
    }

    // Toggle push
    const pushToggle = document.getElementById('notif-push-toggle');
    if (pushToggle) {
        pushToggle.addEventListener('change', function() {
            if (this.checked && 'Notification' in window) {
                Notification.requestPermission().then(permission => {
                    if (permission === 'granted') {
                        notifConfig.pushEnabled = true;
                        localStorage.setItem('notif_push', '1');
                    } else {
                        this.checked = false;
                        showNotification('❌ Permission de notification refusée', 'error');
                    }
                });
            } else {
                notifConfig.pushEnabled = this.checked;
                localStorage.setItem('notif_push', this.checked ? '1' : '0');
            }
        });
        // Charger la préférence
        const savedPush = localStorage.getItem('notif_push');
        if (savedPush !== null) {
            pushToggle.checked = savedPush === '1';
            notifConfig.pushEnabled = pushToggle.checked;
        }
    }
}

// =============================================
// 14. INITIALISER LES PUSH NOTIFICATIONS
// =============================================
function initPushNotifications() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        console.warn('Push notifications non supportées');
        return;
    }

    navigator.serviceWorker.register('/service-worker.js')
        .then(registration => {
            console.log('Service Worker enregistré');
            return registration.pushManager.getSubscription();
        })
        .then(subscription => {
            if (subscription) {
                // Déjà abonné
                console.log('Déjà abonné aux push notifications');
                return subscription;
            }
            // S'abonner aux push
            return subscribeToPush();
        })
        .catch(error => {
            console.error('Erreur Service Worker:', error);
        });
}

function subscribeToPush() {
    const publicKey = document.querySelector('meta[name="vapid-public-key"]')?.content || '';
    if (!publicKey) return;

    navigator.serviceWorker.ready
        .then(registration => {
            return registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(publicKey)
            });
        })
        .then(subscription => {
            // Envoyer la souscription au serveur
            return fetch('/api/notifications/subscribe.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    subscription: subscription,
                    csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || ''
                })
            });
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Abonnement push réussi');
            }
        })
        .catch(error => {
            console.error('Erreur abonnement push:', error);
        });
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

// =============================================
// 15. UTILITAIRES
// =============================================
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function timeAgo(date) {
    const diff = Math.floor((Date.now() - new Date(date).getTime()) / 1000);
    const intervals = {
        an: 31536000,
        mois: 2592000,
        semaine: 604800,
        jour: 86400,
        heure: 3600,
        minute: 60,
        seconde: 1
    };

    for (const [key, value] of Object.entries(intervals)) {
        const count = Math.floor(diff / value);
        if (count > 0) {
            const plural = count > 1 && key !== 'an' ? 's' : '';
            return `Il y a ${count} ${key}${plural}`;
        }
    }
    return 'À l\'instant';
}

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
// 16. EXPOSER LES FONCTIONS GLOBALES
// =============================================
window.markAsRead = markAsRead;
window.markAllAsRead = markAllAsRead;
window.updateBadge = updateBadge;

// =============================================
// FIN DU FICHIER NOTIFICATIONS.JS
// =============================================