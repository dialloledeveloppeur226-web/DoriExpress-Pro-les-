<?php
/**
 * =============================================
 * PAGE DES MESSAGES - DoriExpress-Pro
 * =============================================
 * Fichier : messages.php
 * Rôle : Messagerie interne (chat) entre utilisateurs
 * Niveau : Uber / Glovo / Deliveroo
 * =============================================
 */

// Définir le chemin racine
define('DOSSIER_RACINE', __DIR__ . '/');

// Inclure les fichiers nécessaires
require_once DOSSIER_RACINE . 'includes/config.php';
require_once DOSSIER_RACINE . 'includes/connexion.php';
require_once DOSSIER_RACINE . 'includes/auth.php';
require_once DOSSIER_RACINE . 'includes/security.php';
require_once DOSSIER_RACINE . 'includes/functions.php';

// Vérifier que l'utilisateur est connecté
if (!est_connecte()) {
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('messages.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Messagerie - DoriExpress-Pro';
$page_description = 'Gérez vos conversations et messages.';
$page_keywords = 'messages, chat, DoriExpress';
$page_script = 'chat.js';

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Récupérer les conversations
try {
    $db = Database::getInstance();
    
    // Récupérer l'ID du contact si spécifié
    $contact_id = isset($_GET['contact']) ? (int)$_GET['contact'] : 0;
    
    // Récupérer la liste des conversations
    $conversations = $db->fetchAll(
        "SELECT DISTINCT 
            CASE 
                WHEN m.expediteur_id = ? THEN m.destinataire_id 
                ELSE m.expediteur_id 
            END as contact_id,
            u.nom, u.prenom, u.photo, u.role,
            (SELECT message FROM messages 
             WHERE (expediteur_id = ? AND destinataire_id = u.id) 
                OR (expediteur_id = u.id AND destinataire_id = ?) 
             ORDER BY date_envoi DESC LIMIT 1) as dernier_message,
            (SELECT date_envoi FROM messages 
             WHERE (expediteur_id = ? AND destinataire_id = u.id) 
                OR (expediteur_id = u.id AND destinataire_id = ?) 
             ORDER BY date_envoi DESC LIMIT 1) as dernier_date,
            (SELECT COUNT(*) FROM messages 
             WHERE expediteur_id = u.id AND destinataire_id = ? AND est_lu = 0) as non_lus
         FROM messages m
         JOIN utilisateurs u ON (
            (m.expediteur_id = ? AND m.destinataire_id = u.id) OR 
            (m.destinataire_id = ? AND m.expediteur_id = u.id)
         )
         WHERE m.expediteur_id = ? OR m.destinataire_id = ?
         GROUP BY contact_id
         ORDER BY dernier_date DESC",
        [$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]
    );
    
    // Récupérer les messages d'une conversation spécifique
    $messages = [];
    $contact_info = null;
    
    if ($contact_id > 0) {
        // Vérifier que le contact existe
        $contact_info = $db->fetchOne(
            "SELECT id, nom, prenom, photo, role FROM utilisateurs WHERE id = ?",
            [$contact_id]
        );
        
        if ($contact_info) {
            // Marquer les messages comme lus
            $db->query(
                "UPDATE messages SET est_lu = 1 
                 WHERE expediteur_id = ? AND destinataire_id = ? AND est_lu = 0",
                [$contact_id, $user_id]
            );
            
            // Récupérer les messages
            $messages = $db->fetchAll(
                "SELECT m.*, u.nom, u.prenom, u.photo 
                 FROM messages m
                 JOIN utilisateurs u ON m.expediteur_id = u.id
                 WHERE (m.expediteur_id = ? AND m.destinataire_id = ?) 
                    OR (m.expediteur_id = ? AND m.destinataire_id = ?)
                 ORDER BY m.date_envoi ASC",
                [$user_id, $contact_id, $contact_id, $user_id]
            );
        }
    }
    
    // Récupérer le nombre total de messages non lus
    $total_non_lus = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM messages WHERE destinataire_id = ? AND est_lu = 0",
        [$user_id]
    );
    
} catch (Exception $e) {
    $conversations = [];
    $messages = [];
    $contact_info = null;
    $total_non_lus = 0;
}

// Traitement de l'envoi de message (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    header('Content-Type: application/json');
    
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifier_token_csrf($csrf_token)) {
        echo json_encode(['success' => false, 'error' => 'Erreur de sécurité']);
        exit;
    }
    
    $destinataire_id = (int)$_POST['destinataire_id'] ?? 0;
    $message_text = trim($_POST['message'] ?? '');
    $commande_id = isset($_POST['commande_id']) ? (int)$_POST['commande_id'] : null;
    
    if ($destinataire_id <= 0 || empty($message_text)) {
        echo json_encode(['success' => false, 'error' => 'Message invalide']);
        exit;
    }
    
    try {
        $db = Database::getInstance();
        
        // Vérifier que le destinataire existe
        $dest = $db->fetchOne("SELECT id FROM utilisateurs WHERE id = ?", [$destinataire_id]);
        if (!$dest) {
            echo json_encode(['success' => false, 'error' => 'Destinataire introuvable']);
            exit;
        }
        
        // Insérer le message
        $db->query(
            "INSERT INTO messages (expediteur_id, destinataire_id, commande_id, message, date_envoi) 
             VALUES (?, ?, ?, ?, NOW())",
            [$user_id, $destinataire_id, $commande_id, $message_text]
        );
        
        $message_id = $db->lastInsertId();
        
        // Notifier le destinataire
        ajouter_notification(
            $destinataire_id,
            'Nouveau message',
            $_SESSION['user_nom'] . ' vous a envoyé un message',
            'message',
            URL_BASE . 'messages.php?contact=' . $user_id
        );
        
        // Récupérer le message inséré avec les infos de l'expéditeur
        $msg = $db->fetchOne(
            "SELECT m.*, u.nom, u.prenom, u.photo 
             FROM messages m
             JOIN utilisateurs u ON m.expediteur_id = u.id
             WHERE m.id = ?",
            [$message_id]
        );
        
        echo json_encode([
            'success' => true,
            'message' => $msg
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE MESSAGES
 * ============================================= */
.page-messages {
    background: #f8fafc;
    min-height: 100vh;
    padding: 30px 0 60px;
}

.messages-container {
    display: flex;
    gap: 25px;
    background: white;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
    min-height: 500px;
}

/* Sidebar des conversations */
.messages-sidebar {
    width: 320px;
    border-right: 1px solid #e5e7eb;
    flex-shrink: 0;
}

.messages-sidebar .sidebar-header {
    padding: 18px 20px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.messages-sidebar .sidebar-header h3 {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
    margin: 0;
}

.messages-sidebar .sidebar-header .badge {
    background: #00A651;
    color: white;
    padding: 2px 12px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 600;
}

.conversations-list {
    overflow-y: auto;
    max-height: 500px;
}

.conversation-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 20px;
    border-bottom: 1px solid #f3f4f6;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    color: #1a1a1a;
}

.conversation-item:hover {
    background: #f8fafc;
}

.conversation-item.active {
    background: #f0fdf4;
    border-left: 4px solid #00A651;
}

.conversation-item .conv-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    border: 2px solid #e5e7eb;
}

.conversation-item .conv-info {
    flex: 1;
    min-width: 0;
}

.conversation-item .conv-name {
    font-weight: 600;
    font-size: 14px;
    color: #1a1a1a;
}

.conversation-item .conv-name .role-badge {
    font-size: 10px;
    padding: 1px 8px;
    border-radius: 50px;
    font-weight: 600;
    margin-left: 6px;
}

.conversation-item .conv-last {
    font-size: 13px;
    color: #6b7280;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.conversation-item .conv-time {
    font-size: 11px;
    color: #9ca3af;
    flex-shrink: 0;
}

.conversation-item .conv-badge {
    background: #00A651;
    color: white;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 10px;
    border-radius: 50px;
    flex-shrink: 0;
}

/* Zone de chat */
.messages-chat {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.messages-chat .chat-header {
    padding: 15px 22px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    gap: 12px;
}

.messages-chat .chat-header .chat-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #00A651;
}

.messages-chat .chat-header .chat-name {
    font-weight: 700;
    font-size: 16px;
    color: #1a1a1a;
}

.messages-chat .chat-header .chat-role {
    font-size: 13px;
    color: #6b7280;
}

.messages-chat .chat-messages {
    flex: 1;
    padding: 20px 22px;
    overflow-y: auto;
    max-height: 400px;
    background: #fafbfc;
}

.message-bubble {
    display: flex;
    margin-bottom: 12px;
    animation: fadeIn 0.3s ease;
}

.message-bubble.sent {
    justify-content: flex-end;
}

.message-bubble.received {
    justify-content: flex-start;
}

.message-bubble .bubble {
    max-width: 70%;
    padding: 10px 16px;
    border-radius: 14px;
    font-size: 14px;
    line-height: 1.5;
    word-wrap: break-word;
}

.message-bubble.sent .bubble {
    background: #00A651;
    color: white;
    border-bottom-right-radius: 4px;
}

.message-bubble.received .bubble {
    background: white;
    color: #1a1a1a;
    border: 1px solid #e5e7eb;
    border-bottom-left-radius: 4px;
}

.message-bubble .bubble .msg-time {
    font-size: 11px;
    opacity: 0.7;
    display: block;
    margin-top: 4px;
    text-align: right;
}

.message-bubble.sent .bubble .msg-time {
    color: rgba(255,255,255,0.7);
}

.message-bubble.received .bubble .msg-time {
    color: #9ca3af;
}

.message-bubble .bubble .msg-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 8px;
    float: left;
}

.message-bubble .bubble .msg-name {
    font-weight: 600;
    font-size: 13px;
    display: block;
    margin-bottom: 4px;
}

/* Input */
.chat-input {
    padding: 15px 22px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    gap: 10px;
}

.chat-input input {
    flex: 1;
    padding: 12px 18px;
    border: 2px solid #e5e7eb;
    border-radius: 50px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: #f9fafb;
}

.chat-input input:focus {
    border-color: #00A651;
    box-shadow: 0 0 0 4px rgba(0, 166, 81, 0.1);
    outline: none;
}

.chat-input .btn-send {
    padding: 12px 24px;
    background: #00A651;
    color: white;
    border: none;
    border-radius: 50px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
}

.chat-input .btn-send:hover {
    background: #008a44;
    transform: translateY(-2px);
}

.chat-input .btn-send:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Empty states */
.empty-chat {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    flex: 1;
    color: #6b7280;
    padding: 40px;
    text-align: center;
}

.empty-chat i {
    font-size: 60px;
    color: #d1d5db;
    margin-bottom: 15px;
}

.empty-chat h3 {
    color: #1a1a1a;
}

/* Responsive */
@media (max-width: 768px) {
    .messages-container {
        flex-direction: column;
        border-radius: 12px;
    }
    .messages-sidebar {
        width: 100%;
        border-right: none;
        border-bottom: 1px solid #e5e7eb;
    }
    .conversations-list {
        max-height: 200px;
    }
    .messages-chat .chat-messages {
        max-height: 300px;
    }
    .message-bubble .bubble {
        max-width: 85%;
    }
}

@media (max-width: 480px) {
    .messages-sidebar .sidebar-header h3 {
        font-size: 16px;
    }
    .conversation-item {
        padding: 10px 14px;
    }
    .chat-input {
        padding: 12px 14px;
        flex-wrap: wrap;
    }
    .chat-input input {
        flex: 1 1 100%;
    }
    .chat-input .btn-send {
        flex: 1;
    }
}

/* Dark Mode */
.dark-mode .page-messages {
    background: #121212;
}

.dark-mode .messages-container {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .messages-sidebar {
    border-color: #333;
}

.dark-mode .messages-sidebar .sidebar-header {
    border-color: #333;
}

.dark-mode .messages-sidebar .sidebar-header h3 {
    color: #e5e5e5;
}

.dark-mode .conversation-item {
    border-color: #333;
    color: #e5e5e5;
}

.dark-mode .conversation-item:hover {
    background: #2a2a2a;
}

.dark-mode .conversation-item.active {
    background: #0a1a10;
    border-left-color: #00A651;
}

.dark-mode .conversation-item .conv-name {
    color: #e5e5e5;
}

.dark-mode .conversation-item .conv-last {
    color: #a0a0a0;
}

.dark-mode .messages-chat .chat-header {
    border-color: #333;
}

.dark-mode .messages-chat .chat-header .chat-name {
    color: #e5e5e5;
}

.dark-mode .messages-chat .chat-header .chat-role {
    color: #a0a0a0;
}

.dark-mode .messages-chat .chat-messages {
    background: #1a1a1a;
}

.dark-mode .message-bubble.received .bubble {
    background: #2a2a2a;
    color: #e5e5e5;
    border-color: #444;
}

.dark-mode .chat-input {
    border-color: #333;
}

.dark-mode .chat-input input {
    background: #1a1a1a;
    border-color: #444;
    color: #e5e5e5;
}

.dark-mode .chat-input input:focus {
    border-color: #00A651;
}

.dark-mode .empty-chat h3 {
    color: #e5e5e5;
}

.dark-mode .empty-chat i {
    color: #333;
}

.dark-mode .empty-chat {
    color: #a0a0a0;
}
</style>

<!-- ============================================= -->
<!-- PAGE MESSAGES -->
<!-- ============================================= -->
<div class="page-messages">
    <div class="container">
        
        <div class="messages-container">
            
            <!-- Sidebar -->
            <div class="messages-sidebar">
                <div class="sidebar-header">
                    <h3><i class="fas fa-comments" style="color:#00A651;"></i> Messages</h3>
                    <?php if ($total_non_lus > 0): ?>
                        <span class="badge"><?php echo $total_non_lus; ?></span>
                    <?php endif; ?>
                </div>
                <div class="conversations-list">
                    <?php if (!empty($conversations)): ?>
                        <?php foreach ($conversations as $conv): ?>
                            <a href="?contact=<?php echo $conv['contact_id']; ?>" 
                               class="conversation-item <?php echo $contact_id == $conv['contact_id'] ? 'active' : ''; ?>">
                                <img src="<?php echo URL_BASE . 'uploads/profils/' . ($conv['photo'] ?? 'default.jpg'); ?>" 
                                     alt="<?php echo htmlspecialchars($conv['nom']); ?>" 
                                     class="conv-avatar">
                                <div class="conv-info">
                                    <div class="conv-name">
                                        <?php echo htmlspecialchars($conv['nom'] . ' ' . $conv['prenom']); ?>
                                        <span class="role-badge" style="background:rgba(0,166,81,0.1); color:#00A651;">
                                            <?php echo ucfirst($conv['role']); ?>
                                        </span>
                                    </div>
                                    <div class="conv-last"><?php echo htmlspecialchars($conv['dernier_message'] ?? 'Aucun message'); ?></div>
                                </div>
                                <div style="display:flex; flex-direction:column; align-items:flex-end; gap:4px;">
                                    <?php if ($conv['dernier_date']): ?>
                                        <span class="conv-time"><?php echo temps_ecoule($conv['dernier_date']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($conv['non_lus'] > 0): ?>
                                        <span class="conv-badge"><?php echo $conv['non_lus']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding:30px 20px; text-align:center; color:#6b7280;">
                            <i class="fas fa-inbox" style="font-size:30px; display:block; margin-bottom:10px; color:#d1d5db;"></i>
                            <p>Aucune conversation</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Chat -->
            <div class="messages-chat">
                <?php if ($contact_id > 0 && $contact_info): ?>
                    
                    <!-- Header -->
                    <div class="chat-header">
                        <img src="<?php echo URL_BASE . 'uploads/profils/' . ($contact_info['photo'] ?? 'default.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($contact_info['nom']); ?>" 
                             class="chat-avatar">
                        <div>
                            <div class="chat-name"><?php echo htmlspecialchars($contact_info['nom'] . ' ' . $contact_info['prenom']); ?></div>
                            <div class="chat-role">
                                <?php echo ucfirst($contact_info['role']); ?>
                                <?php if ($contact_info['role'] === 'client'): ?>
                                    👤 Client
                                <?php elseif ($contact_info['role'] === 'livreur'): ?>
                                    🛵 Livreur
                                <?php elseif ($contact_info['role'] === 'partenaire'): ?>
                                    🏪 Partenaire
                                <?php elseif ($contact_info['role'] === 'admin'): ?>
                                    🛡️ Admin
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Messages -->
                    <div class="chat-messages" id="chat-messages">
                        <?php if (!empty($messages)): ?>
                            <?php foreach ($messages as $msg): ?>
                                <div class="message-bubble <?php echo $msg['expediteur_id'] == $user_id ? 'sent' : 'received'; ?>">
                                    <div class="bubble">
                                        <?php if ($msg['expediteur_id'] != $user_id): ?>
                                            <span class="msg-name">
                                                <img src="<?php echo URL_BASE . 'uploads/profils/' . ($msg['photo'] ?? 'default.jpg'); ?>" 
                                                     class="msg-avatar">
                                                <?php echo htmlspecialchars($msg['nom'] . ' ' . $msg['prenom']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                        <span class="msg-time"><?php echo formater_date($msg['date_envoi'], 'H:i'); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-chat" style="min-height:300px;">
                                <i class="fas fa-comment-dots"></i>
                                <h3>Démarrez la conversation</h3>
                                <p>Envoyez votre premier message à <?php echo htmlspecialchars($contact_info['nom']); ?>.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Input -->
                    <div class="chat-input">
                        <input type="text" id="message-input" placeholder="Écrivez votre message..." 
                               onkeypress="if(event.key==='Enter'){envoyerMessage();}">
                        <button class="btn-send" id="send-btn" onclick="envoyerMessage()">
                            <i class="fas fa-paper-plane"></i> Envoyer
                        </button>
                    </div>
                    
                <?php else: ?>
                    <!-- Pas de conversation sélectionnée -->
                    <div class="empty-chat" style="flex:1; min-height:400px;">
                        <i class="fas fa-comments"></i>
                        <h3>Votre messagerie</h3>
                        <p>Sélectionnez une conversation pour commencer à discuter.</p>
                        <?php if (empty($conversations)): ?>
                            <p style="font-size:14px; color:#9ca3af;">
                                Vous n'avez pas encore de messages. 
                                <a href="<?php echo URL_BASE; ?>contact.php" style="color:#00A651;">Contactez-nous</a>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
        </div>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// VARIABLES
// =============================================
const contactId = <?php echo $contact_id; ?>;
const userId = <?php echo $user_id; ?>;
const messageInput = document.getElementById('message-input');
const sendBtn = document.getElementById('send-btn');
const chatMessages = document.getElementById('chat-messages');

// =============================================
// ENVOYER UN MESSAGE (AJAX)
// =============================================
function envoyerMessage() {
    const message = messageInput.value.trim();
    if (!message || contactId <= 0) return;
    
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    
    fetch('<?php echo URL_BASE; ?>messages.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            action: 'send_message',
            destinataire_id: contactId,
            message: message,
            csrf_token: '<?php echo generer_token_csrf(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Ajouter le message au chat
            const msg = data.message;
            const bubble = document.createElement('div');
            bubble.className = 'message-bubble sent';
            bubble.innerHTML = `
                <div class="bubble">
                    ${nl2br(escapeHtml(msg.message))}
                    <span class="msg-time">${formatTime(msg.date_envoi)}</span>
                </div>
            `;
            chatMessages.appendChild(bubble);
            messageInput.value = '';
            scrollToBottom();
            
            // Mettre à jour la dernière conversation
            updateConversationList(msg.message);
        } else {
            showNotification('❌ ' + data.error, 'error');
        }
    })
    .catch(error => {
        showNotification('❌ Erreur lors de l\'envoi', 'error');
    })
    .finally(() => {
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Envoyer';
    });
}

// =============================================
// FONCTIONS UTILITAIRES
// =============================================
function nl2br(text) {
    return text.replace(/\n/g, '<br>');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatTime(date) {
    const d = new Date(date);
    return d.getHours().toString().padStart(2, '0') + ':' + 
           d.getMinutes().toString().padStart(2, '0');
}

function scrollToBottom() {
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
}

function updateConversationList(lastMessage) {
    // Mettre à jour la conversation dans la sidebar
    const activeItem = document.querySelector('.conversation-item.active');
    if (activeItem) {
        const lastEl = activeItem.querySelector('.conv-last');
        if (lastEl) {
            lastEl.textContent = lastMessage.length > 50 ? lastMessage.substring(0, 50) + '...' : lastMessage;
        }
        // Déplacer l'élément en haut
        const list = document.querySelector('.conversations-list');
        if (list) {
            list.prepend(activeItem);
        }
    }
}

// =============================================
// AUTO-SCROLL AU CHARGEMENT
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    scrollToBottom();
});

// =============================================
// POLLING POUR LES NOUVEAUX MESSAGES
// =============================================
let lastMessageId = 0;
<?php if (!empty($messages)): ?>
    lastMessageId = <?php echo end($messages)['id'] ?? 0; ?>;
<?php endif; ?>

setInterval(function() {
    if (contactId <= 0) return;
    
    fetch('<?php echo URL_BASE; ?>api/messages/poll.php?contact=' + contactId + '&last=' + lastMessageId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.messages && data.messages.length > 0) {
                data.messages.forEach(msg => {
                    if (msg.id > lastMessageId) {
                        lastMessageId = msg.id;
                        const bubble = document.createElement('div');
                        bubble.className = 'message-bubble received';
                        bubble.innerHTML = `
                            <div class="bubble">
                                <span class="msg-name">
                                    <img src="<?php echo URL_BASE; ?>uploads/profils/${msg.photo || 'default.jpg'}" class="msg-avatar">
                                    ${escapeHtml(msg.nom + ' ' + msg.prenom)}
                                </span>
                                ${nl2br(escapeHtml(msg.message))}
                                <span class="msg-time">${formatTime(msg.date_envoi)}</span>
                            </div>
                        `;
                        chatMessages.appendChild(bubble);
                        scrollToBottom();
                    }
                });
                
                // Mettre à jour le badge des non lus
                if (data.total_non_lus !== undefined) {
                    const badge = document.querySelector('.sidebar-header .badge');
                    if (badge) {
                        if (data.total_non_lus > 0) {
                            badge.textContent = data.total_non_lus;
                            badge.style.display = 'inline';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                }
            }
        })
        .catch(() => {});
}, 5000); // Toutes les 5 secondes

// =============================================
// NOTIFICATION
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
    `;
    div.textContent = message;
    document.body.appendChild(div);
    
    setTimeout(() => {
        div.style.animation = 'slideOutRight 0.5s ease';
        setTimeout(() => div.remove(), 500);
    }, 4000);
}

console.log('✅ DoriExpress-Pro - Page messages chargée');
</script>

<style>
.spinner-border {
    display: inline-block;
    width: 1rem;
    height: 1rem;
    vertical-align: text-bottom;
    border: 0.2em solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: spinner-border .75s linear infinite;
}

@keyframes spinner-border {
    to { transform: rotate(360deg); }
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes slideInRight {
    from { transform: translateX(100px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes slideOutRight {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100px); opacity: 0; }
}

.dark-mode .conversation-item .conv-name .role-badge {
    background: rgba(0,166,81,0.2) !important;
    color: #00A651 !important;
}
</style>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER MESSAGES.PHP
// =============================================
?>