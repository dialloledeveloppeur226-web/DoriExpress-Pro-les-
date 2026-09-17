/**
 * =============================================
 * JAVASCRIPT CHAT - DoriExpress-Pro
 * =============================================
 * Fichier : assets/js/chat.js
 * Rôle : Gestion du chat en temps réel (WebSocket/POLLING)
 * Niveau : Uber / Glovo / Deliveroo
 * =============================================
 */

'use strict';

// =============================================
// 1. ATTENDRE LE CHARGEMENT DU DOM
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ DoriExpress-Pro - Chat chargé');
    initChat();
});

// =============================================
// 2. INITIALISATION DU CHAT
// =============================================
let chatConfig = {
    userId: 0,
    contactId: 0,
    lastMessageId: 0,
    pollingInterval: 5000,
    isPolling: false
};

function initChat() {
    // Récupérer les configurations depuis les données HTML
    const chatContainer = document.getElementById('chat-container');
    if (!chatContainer) return;

    chatConfig.userId = parseInt(chatContainer.dataset.userId) || 0;
    chatConfig.contactId = parseInt(chatContainer.dataset.contactId) || 0;
    chatConfig.lastMessageId = parseInt(chatContainer.dataset.lastMessageId) || 0;

    initMessageInput();
    initScrollToBottom();
    initFileUpload();
    initEmojiPicker();
    initTypingIndicator();
    startPolling();
    initMessageActions();
    initSearchMessages();
}

// =============================================
// 3. INPUT DE MESSAGE
// =============================================
function initMessageInput() {
    const input = document.getElementById('message-input');
    const sendBtn = document.getElementById('send-btn');

    if (!input || !sendBtn) return;

    // Auto-resize du textarea
    input.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });

    // Envoyer avec Entrée (Shift+Enter pour nouvelle ligne)
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    sendBtn.addEventListener('click', function() {
        sendMessage();
    });
}

// =============================================
// 4. ENVOYER UN MESSAGE
// =============================================
function sendMessage() {
    const input = document.getElementById('message-input');
    const message = input.value.trim();

    if (!message || chatConfig.contactId <= 0) return;

    const sendBtn = document.getElementById('send-btn');
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    // Afficher le message en local (optimiste)
    addMessageToChat({
        id: Date.now(),
        expediteur_id: chatConfig.userId,
        message: message,
        date_envoi: new Date().toISOString(),
        is_sent: true,
        nom: 'Moi',
        prenom: ''
    });

    input.value = '';
    input.style.height = 'auto';

    // Envoyer au serveur
    fetch('/api/chat/send.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            destinataire_id: chatConfig.contactId,
            message: message,
            csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || ''
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mettre à jour l'ID du message
            const lastMessage = document.querySelector('.message-bubble:last-child');
            if (lastMessage) {
                lastMessage.dataset.messageId = data.message_id;
            }
            chatConfig.lastMessageId = data.message_id;
        } else {
            showNotification('❌ ' + (data.error || 'Erreur d\'envoi'), 'error');
            // Retirer le message optimiste
            const lastMessage = document.querySelector('.message-bubble:last-child');
            if (lastMessage) {
                lastMessage.remove();
            }
        }
    })
    .catch(error => {
        showNotification('❌ Erreur réseau', 'error');
        const lastMessage = document.querySelector('.message-bubble:last-child');
        if (lastMessage) {
            lastMessage.remove();
        }
    })
    .finally(() => {
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
        scrollToBottom();
    });
}

// =============================================
// 5. AJOUTER UN MESSAGE AU CHAT
// =============================================
function addMessageToChat(data) {
    const container = document.getElementById('chat-messages');
    if (!container) return;

    const isSent = data.expediteur_id === chatConfig.userId || data.is_sent;

    const bubble = document.createElement('div');
    bubble.className = `message-bubble ${isSent ? 'sent' : 'received'}`;
    bubble.dataset.messageId = data.id || Date.now();

    let avatarHtml = '';
    if (!isSent && data.photo) {
        avatarHtml = `<img src="${data.photo}" alt="Avatar" class="msg-avatar">`;
    }

    let nameHtml = '';
    if (!isSent && data.nom) {
        nameHtml = `<span class="msg-name">${data.nom} ${data.prenom || ''}</span>`;
    }

    const time = new Date(data.date_envoi);
    const timeStr = time.getHours().toString().padStart(2, '0') + ':' + 
                    time.getMinutes().toString().padStart(2, '0');

    bubble.innerHTML = `
        <div class="bubble">
            ${avatarHtml}
            ${nameHtml}
            ${escapeHtml(data.message)}
            <span class="msg-time">${timeStr}</span>
        </div>
    `;

    container.appendChild(bubble);
    scrollToBottom();

    // Mettre à jour le dernier message ID
    if (data.id && data.id > chatConfig.lastMessageId) {
        chatConfig.lastMessageId = data.id;
    }
}

// =============================================
// 6. SCROLL EN BAS
// =============================================
function initScrollToBottom() {
    const container = document.getElementById('chat-messages');
    if (container) {
        scrollToBottom();

        // Observer les nouvelles lignes
        const observer = new MutationObserver(function() {
            scrollToBottom();
        });
        observer.observe(container, { childList: true, subtree: true });
    }
}

function scrollToBottom() {
    const container = document.getElementById('chat-messages');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

// =============================================
// 7. POLLING (RÉCUPÉRATION DES MESSAGES)
// =============================================
function startPolling() {
    if (chatConfig.isPolling) return;
    chatConfig.isPolling = true;

    pollMessages();
    setInterval(pollMessages, chatConfig.pollingInterval);
}

function pollMessages() {
    if (chatConfig.contactId <= 0) return;

    fetch(`/api/chat/poll.php?contact=${chatConfig.contactId}&last=${chatConfig.lastMessageId}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.messages && data.messages.length > 0) {
            data.messages.forEach(msg => {
                if (msg.id > chatConfig.lastMessageId) {
                    addMessageToChat(msg);
                }
            });

            // Mettre à jour le badge des non lus
            if (data.total_non_lus !== undefined) {
                updateUnreadBadge(data.total_non_lus);
            }
        }
    })
    .catch(error => {
        // Silencieux
    });
}

// =============================================
// 8. INDICATEUR DE SAISIE
// =============================================
function initTypingIndicator() {
    const input = document.getElementById('message-input');
    if (!input) return;

    let typingTimeout;

    input.addEventListener('input', function() {
        clearTimeout(typingTimeout);

        // Envoyer l'état de saisie
        fetch('/api/chat/typing.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                contact_id: chatConfig.contactId,
                typing: this.value.length > 0 ? 1 : 0
            })
        });

        // Arrêter le typing après 3 secondes d'inactivité
        typingTimeout = setTimeout(() => {
            fetch('/api/chat/typing.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    contact_id: chatConfig.contactId,
                    typing: 0
                })
            });
        }, 3000);
    });

    // Simuler la réception d'un typing (à implémenter côté serveur)
    // Polling pour les états de typing
    setInterval(() => {
        fetch(`/api/chat/typing-status.php?contact=${chatConfig.contactId}`)
            .then(response => response.json())
            .then(data => {
                const indicator = document.getElementById('typing-indicator');
                if (indicator) {
                    if (data.typing) {
                        indicator.style.display = 'block';
                        indicator.textContent = 'L\'utilisateur est en train d\'écrire...';
                    } else {
                        indicator.style.display = 'none';
                    }
                }
            })
            .catch(() => {});
    }, 3000);
}

// =============================================
// 9. TÉLÉCHARGEMENT DE FICHIERS
// =============================================
function initFileUpload() {
    const fileBtn = document.getElementById('file-upload-btn');
    const fileInput = document.getElementById('file-upload-input');

    if (!fileBtn || !fileInput) return;

    fileBtn.addEventListener('click', function() {
        fileInput.click();
    });

    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            uploadFile(this.files[0]);
            this.value = '';
        }
    });
}

function uploadFile(file) {
    const maxSize = 5 * 1024 * 1024; // 5 Mo

    if (file.size > maxSize) {
        showNotification('❌ Fichier trop volumineux (max 5 Mo)', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('file', file);
    formData.append('contact_id', chatConfig.contactId);
    formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '');

    showNotification('📤 Téléchargement en cours...', 'info');

    fetch('/api/chat/upload.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ Fichier envoyé', 'success');
            addMessageToChat({
                id: data.message_id || Date.now(),
                expediteur_id: chatConfig.userId,
                message: `📎 ${data.filename || file.name}`,
                date_envoi: new Date().toISOString(),
                is_sent: true
            });
        } else {
            showNotification('❌ ' + (data.error || 'Erreur'), 'error');
        }
    })
    .catch(error => {
        showNotification('❌ Erreur réseau', 'error');
    });
}

// =============================================
// 10. ÉMOJIS
// =============================================
function initEmojiPicker() {
    const emojiBtn = document.getElementById('emoji-btn');
    const picker = document.getElementById('emoji-picker');

    if (!emojiBtn || !picker) return;

    emojiBtn.addEventListener('click', function() {
        picker.style.display = picker.style.display === 'block' ? 'none' : 'block';
    });

    // Fermer le picker au clic en dehors
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.emoji-container')) {
            picker.style.display = 'none';
        }
    });

    // Sélection d'un emoji
    picker.querySelectorAll('.emoji-item').forEach(el => {
        el.addEventListener('click', function() {
            const input = document.getElementById('message-input');
            if (input) {
                const emoji = this.textContent;
                const start = input.selectionStart;
                const end = input.selectionEnd;
                input.value = input.value.substring(0, start) + emoji + input.value.substring(end);
                input.focus();
                input.selectionStart = input.selectionEnd = start + emoji.length;
            }
            picker.style.display = 'none';
        });
    });
}

// =============================================
// 11. ACTIONS SUR LES MESSAGES
// =============================================
function initMessageActions() {
    document.querySelectorAll('.message-actions .btn-action').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const action = this.dataset.action;
            const messageId = this.closest('.message-bubble').dataset.messageId;

            if (action === 'delete') {
                deleteMessage(messageId);
            } else if (action === 'reply') {
                replyToMessage(messageId);
            } else if (action === 'copy') {
                copyMessage(messageId);
            }
        });
    });
}

function deleteMessage(messageId) {
    if (!confirm('Supprimer ce message ?')) return;

    fetch('/api/chat/delete.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            message_id: messageId,
            csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || ''
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const el = document.querySelector(`.message-bubble[data-message-id="${messageId}"]`);
            if (el) {
                el.style.animation = 'slideOutRight 0.5s ease';
                setTimeout(() => el.remove(), 500);
            }
            showNotification('✅ Message supprimé', 'success');
        } else {
            showNotification('❌ ' + (data.error || 'Erreur'), 'error');
        }
    })
    .catch(() => {
        showNotification('❌ Erreur réseau', 'error');
    });
}

function replyToMessage(messageId) {
    const el = document.querySelector(`.message-bubble[data-message-id="${messageId}"]`);
    if (el) {
        const text = el.querySelector('.bubble').textContent.trim();
        const input = document.getElementById('message-input');
        if (input) {
            input.value = `> ${text}\n\n`;
            input.focus();
            showNotification('💬 Réponse à: ' + text.substring(0, 30) + '...', 'info');
        }
    }
}

function copyMessage(messageId) {
    const el = document.querySelector(`.message-bubble[data-message-id="${messageId}"]`);
    if (el) {
        const text = el.querySelector('.bubble').textContent.trim();
        navigator.clipboard.writeText(text).then(() => {
            showNotification('📋 Message copié', 'success');
        }).catch(() => {
            // Fallback
            const input = document.createElement('textarea');
            input.value = text;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
            showNotification('📋 Message copié', 'success');
        });
    }
}

// =============================================
// 12. RECHERCHE DE MESSAGES
// =============================================
function initSearchMessages() {
    const searchInput = document.getElementById('message-search');
    if (!searchInput) return;

    searchInput.addEventListener('input', function() {
        const query = this.value.trim().toLowerCase();
        const messages = document.querySelectorAll('.message-bubble');

        messages.forEach(msg => {
            const text = msg.querySelector('.bubble')?.textContent?.toLowerCase() || '';
            if (query && !text.includes(query)) {
                msg.style.display = 'none';
            } else {
                msg.style.display = '';
                if (query) {
                    msg.style.background = 'rgba(0, 166, 81, 0.05)';
                } else {
                    msg.style.background = '';
                }
            }
        });

        if (query) {
            showNotification(`🔍 ${document.querySelectorAll('.message-bubble:not([style*="display: none"])').length} résultat(s)`, 'info');
        }
    });
}

// =============================================
// 13. BADGE DES NON LUS
// =============================================
function updateUnreadBadge(count) {
    const badges = document.querySelectorAll('.unread-badge');
    badges.forEach(badge => {
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'inline';
        } else {
            badge.style.display = 'none';
        }
    });

    // Mettre à jour le titre de la page
    if (count > 0) {
        document.title = `(${count}) ${document.title.replace(/^\(\d+\)\s*/, '')}`;
    } else {
        document.title = document.title.replace(/^\(\d+\)\s*/, '');
    }
}

// =============================================
// 14. UTILITAIRES
// =============================================
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
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
// 15. EXPOSER LES FONCTIONS GLOBALES
// =============================================
window.sendMessage = sendMessage;
window.addMessageToChat = addMessageToChat;
window.scrollToBottom = scrollToBottom;

// =============================================
// FIN DU FICHIER CHAT.JS
// =============================================