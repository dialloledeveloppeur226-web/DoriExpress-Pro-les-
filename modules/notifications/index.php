<?php
/**
 * =============================================
 * MODULE NOTIFICATIONS - DoriExpress-Pro
 * =============================================
 * Fichier : modules/notifications/index.php
 * Rôle : Gestion centralisée des notifications (Email, WhatsApp, Push, Interne)
 * Niveau : Uber / Glovo / Deliveroo
 * =============================================
 */

// Définir le chemin racine
define('DOSSIER_RACINE', dirname(__DIR__, 2) . '/');

// Inclure les fichiers nécessaires
require_once DOSSIER_RACINE . 'includes/config.php';
require_once DOSSIER_RACINE . 'includes/connexion.php';
require_once DOSSIER_RACINE . 'includes/auth.php';
require_once DOSSIER_RACINE . 'includes/security.php';
require_once DOSSIER_RACINE . 'includes/functions.php';

// Vérifier que l'utilisateur est connecté et est admin ou créateur
if (!est_connecte() || !est_admin()) {
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('modules/notifications/index.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Notifications - DoriExpress-Pro';
$page_description = 'Gérez les notifications de la plateforme.';
$page_keywords = 'notifications, alertes, admin, DoriExpress';

$user_id = $_SESSION['user_id'];

// Vérifier si le module notifications est actif
$notif_active = get_parametre('module_notifications_active', 1);
if (!$notif_active) {
    echo '<div class="container" style="padding:60px 0; text-align:center;">
            <h2>🔔 Les notifications sont actuellement désactivées</h2>
            <p style="color:#6b7280;">Veuillez réessayer plus tard.</p>
          </div>';
    require_once DOSSIER_RACINE . 'includes/footer.php';
    exit;
}

try {
    $db = Database::getInstance();
    
    // Récupérer les paramètres des notifications
    $whatsapp_active = get_parametre('module_whatsapp_active', 1);
    $email_active = get_parametre('module_email_active', 1);
    $push_active = get_parametre('module_push_active', 1);
    
    // Récupérer les types de notifications
    $types = [
        'commande' => ['label' => '📦 Commandes', 'icon' => 'fa-shopping-cart'],
        'paiement' => ['label' => '💰 Paiements', 'icon' => 'fa-credit-card'],
        'livraison' => ['label' => '🚚 Livraisons', 'icon' => 'fa-truck'],
        'promotion' => ['label' => '🏷️ Promotions', 'icon' => 'fa-tag'],
        'message' => ['label' => '💬 Messages', 'icon' => 'fa-envelope'],
        'securite' => ['label' => '🔒 Sécurité', 'icon' => 'fa-shield-alt'],
        'systeme' => ['label' => '⚙️ Système', 'icon' => 'fa-cog']
    ];
    
    // Récupérer les notifications récentes
    $notifications_recentes = $db->fetchAll(
        "SELECT * FROM notifications ORDER BY date_creation DESC LIMIT 20"
    );
    
    // Récupérer les utilisateurs pour l'envoi
    $utilisateurs = $db->fetchAll(
        "SELECT id, nom, prenom, email, telephone, role FROM utilisateurs WHERE statut = 'actif' ORDER BY nom"
    );
    
    // Récupérer les statistiques des notifications
    $stats = [
        'total' => (int) $db->fetchValue("SELECT COUNT(*) FROM notifications"),
        'non_lues' => (int) $db->fetchValue("SELECT COUNT(*) FROM notifications WHERE est_lu = 0"),
        'aujourdhui' => (int) $db->fetchValue("SELECT COUNT(*) FROM notifications WHERE DATE(date_creation) = CURDATE()"),
        'par_type' => $db->fetchAll(
            "SELECT type, COUNT(*) as count FROM notifications GROUP BY type"
        )
    ];
    
} catch (Exception $e) {
    $notifications_recentes = [];
    $utilisateurs = [];
    $stats = ['total' => 0, 'non_lues' => 0, 'aujourdhui' => 0, 'par_type' => []];
    $whatsapp_active = 1;
    $email_active = 1;
    $push_active = 1;
}

// Traitement de l'envoi de notification
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'envoyer_notification') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifier_token_csrf($csrf_token)) {
        $error = 'Erreur de sécurité. Veuillez rafraîchir la page.';
    } else {
        $titre = trim($_POST['titre'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $type = $_POST['type'] ?? 'systeme';
        $destinataires = $_POST['destinataires'] ?? 'tous';
        $canaux = isset($_POST['canaux']) ? $_POST['canaux'] : [];
        $utilisateur_id = isset($_POST['utilisateur_id']) ? (int)$_POST['utilisateur_id'] : 0;
        $lien = trim($_POST['lien'] ?? '');
        
        if (empty($titre) || empty($message)) {
            $error = 'Veuillez remplir tous les champs obligatoires.';
        } else {
            try {
                $db->beginTransaction();
                $compteur = 0;
                
                // Déterminer les destinataires
                $target_users = [];
                if ($destinataires === 'tous') {
                    $target_users = $utilisateurs;
                } elseif ($destinataires === 'admins') {
                    $target_users = array_filter($utilisateurs, function($u) {
                        return in_array($u['role'], ['admin', 'createur', 'support']);
                    });
                } elseif ($destinataires === 'clients') {
                    $target_users = array_filter($utilisateurs, function($u) {
                        return $u['role'] === 'client';
                    });
                } elseif ($destinataires === 'livreurs') {
                    $target_users = array_filter($utilisateurs, function($u) {
                        return $u['role'] === 'livreur';
                    });
                } elseif ($destinataires === 'partenaires') {
                    $target_users = array_filter($utilisateurs, function($u) {
                        return $u['role'] === 'partenaire';
                    });
                } elseif ($destinataires === 'specifique' && $utilisateur_id > 0) {
                    $user = $db->fetchOne("SELECT * FROM utilisateurs WHERE id = ?", [$utilisateur_id]);
                    if ($user) {
                        $target_users = [$user];
                    }
                }
                
                foreach ($target_users as $user) {
                    // Notification interne
                    $db->query(
                        "INSERT INTO notifications (utilisateur_id, titre, message, type, lien, date_creation) 
                         VALUES (?, ?, ?, ?, ?, NOW())",
                        [$user['id'], $titre, $message, $type, $lien]
                    );
                    $compteur++;
                    
                    // Email
                    if (in_array('email', $canaux) && $email_active) {
                        // $this->sendEmail($user['email'], $titre, $message);
                    }
                    
                    // WhatsApp
                    if (in_array('whatsapp', $canaux) && $whatsapp_active) {
                        // $this->sendWhatsApp($user['telephone'], $message);
                    }
                }
                
                $db->commit();
                $success = "✅ Notification envoyée à $compteur utilisateur(s) !";
                
            } catch (Exception $e) {
                $db->rollback();
                $error = 'Erreur lors de l\'envoi de la notification.';
            }
        }
    }
}

// Inclure le header admin
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DU MODULE NOTIFICATIONS
 * ============================================= */
.page-notifications-module {
    background: #f8fafc;
    min-height: 100vh;
    padding: 20px 0 60px;
}

.notif-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}

.notif-header h1 {
    font-size: 24px;
    font-weight: 800;
    color: #1a1a1a;
    margin: 0;
}

/* Stats */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 15px;
    border: 1px solid #e5e7eb;
    text-align: center;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.05);
}

.stat-card .stat-number {
    font-size: 20px;
    font-weight: 800;
    color: #1a1a1a;
    display: block;
}

.stat-card .stat-number.green {
    color: #22c55e;
}

.stat-card .stat-number.gold {
    color: #f59e0b;
}

.stat-card .stat-label {
    font-size: 12px;
    color: #6b7280;
}

.stat-card .stat-icon {
    font-size: 18px;
    display: block;
    margin-bottom: 4px;
}

/* Grid */
.notif-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

/* Cards */
.card-notif {
    background: white;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid #e5e7eb;
}

.card-notif .card-title {
    font-size: 16px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 15px;
}

.card-notif .card-title i {
    color: #00A651;
    margin-right: 8px;
}

/* Form */
.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    font-weight: 600;
    font-size: 14px;
    color: #374151;
    margin-bottom: 6px;
}

.form-group .form-control {
    width: 100%;
    padding: 10px 14px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: #f9fafb;
}

.form-group .form-control:focus {
    border-color: #00A651;
    box-shadow: 0 0 0 4px rgba(0, 166, 81, 0.1);
    outline: none;
}

.form-group textarea.form-control {
    min-height: 100px;
    resize: vertical;
}

.form-group .checkbox-group {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.form-group .checkbox-group label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 400;
    cursor: pointer;
}

.form-group .checkbox-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #00A651;
}

.btn-envoyer {
    padding: 12px 35px;
    background: #00A651;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-envoyer:hover {
    background: #008a44;
    transform: translateY(-2px);
}

.btn-envoyer:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Notifications list */
.notif-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid #f3f4f6;
}

.notif-item:last-child {
    border-bottom: none;
}

.notif-item .notif-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    flex-shrink: 0;
}

.notif-item .notif-content {
    flex: 1;
}

.notif-item .notif-content .notif-titre {
    font-weight: 600;
    font-size: 14px;
    color: #1a1a1a;
}

.notif-item .notif-content .notif-message {
    font-size: 13px;
    color: #6b7280;
}

.notif-item .notif-time {
    font-size: 12px;
    color: #9ca3af;
    white-space: nowrap;
}

.notif-item .notif-status .badge {
    padding: 2px 10px;
    border-radius: 50px;
    font-size: 11px;
}

/* Responsive */
@media (max-width: 992px) {
    .notif-grid-2 {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .notif-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .form-group .checkbox-group {
        flex-direction: column;
        gap: 6px;
    }
}

@media (max-width: 480px) {
    .notif-header h1 {
        font-size: 20px;
    }
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    .notif-item {
        flex-wrap: wrap;
        gap: 6px;
    }
    .notif-item .notif-time {
        margin-left: 44px;
    }
}

/* Dark Mode */
.dark-mode .page-notifications-module {
    background: #121212;
}

.dark-mode .notif-header h1 {
    color: #e5e5e5;
}

.dark-mode .stat-card {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .stat-card .stat-number {
    color: #e5e5e5;
}

.dark-mode .stat-card .stat-label {
    color: #a0a0a0;
}

.dark-mode .card-notif {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .card-notif .card-title {
    color: #e5e5e5;
}

.dark-mode .form-group label {
    color: #d0d0d0;
}

.dark-mode .form-group .form-control {
    background: #1a1a1a;
    border-color: #444;
    color: #e5e5e5;
}

.dark-mode .form-group .form-control:focus {
    border-color: #00A651;
}

.dark-mode .notif-item {
    border-color: #333;
}

.dark-mode .notif-item .notif-content .notif-titre {
    color: #e5e5e5;
}

.dark-mode .notif-item .notif-content .notif-message {
    color: #b0b0b0;
}
</style>

<!-- ============================================= -->
<!-- MODULE NOTIFICATIONS -->
<!-- ============================================= -->
<div class="page-notifications-module">
    <div class="container">
        
        <!-- Header -->
        <div class="notif-header">
            <h1><i class="fas fa-bell" style="color:#00A651;"></i> Notifications</h1>
            <div>
                <span class="badge bg-secondary"><?php echo $stats['total']; ?> notifications</span>
                <span class="badge bg-danger ms-2"><?php echo $stats['non_lues']; ?> non lues</span>
                <a href="<?php echo URL_BASE; ?>admin/createur.php" class="btn btn-outline-secondary btn-sm ms-2">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">📊</span>
                <span class="stat-number"><?php echo $stats['total']; ?></span>
                <span class="stat-label">Total</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">📅</span>
                <span class="stat-number green"><?php echo $stats['aujourdhui']; ?></span>
                <span class="stat-label">Aujourd'hui</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🔴</span>
                <span class="stat-number gold"><?php echo $stats['non_lues']; ?></span>
                <span class="stat-label">Non lues</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">📈</span>
                <span class="stat-number">
                    <?php 
                    $taux_lecture = $stats['total'] > 0 ? round(($stats['total'] - $stats['non_lues']) / $stats['total'] * 100, 1) : 0;
                    echo $taux_lecture . '%';
                    ?>
                </span>
                <span class="stat-label">Taux de lecture</span>
            </div>
        </div>
        
        <!-- Content -->
        <div class="notif-grid-2">
            
            <!-- Formulaire d'envoi -->
            <div class="card-notif">
                <div class="card-title"><i class="fas fa-paper-plane"></i> Envoyer une notification</div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger" style="margin-bottom:15px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success" style="margin-bottom:15px;">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="envoyer_notification">
                    <input type="hidden" name="csrf_token" value="<?php echo generer_token_csrf(); ?>">
                    
                    <div class="form-group">
                        <label for="titre">Titre <span class="required">*</span></label>
                        <input type="text" class="form-control" id="titre" name="titre" placeholder="Titre de la notification" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message <span class="required">*</span></label>
                        <textarea class="form-control" id="message" name="message" placeholder="Contenu du message..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="type">Type</label>
                        <select class="form-control" id="type" name="type">
                            <?php foreach ($types as $key => $type): ?>
                                <option value="<?php echo $key; ?>"><?php echo $type['label']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="destinataires">Destinataires <span class="required">*</span></label>
                        <select class="form-control" id="destinataires" name="destinataires" onchange="toggleUtilisateur(this.value)">
                            <option value="tous">👥 Tous les utilisateurs</option>
                            <option value="admins">🛡️ Admins & Créateur</option>
                            <option value="clients">👤 Clients</option>
                            <option value="livreurs">🛵 Livreurs</option>
                            <option value="partenaires">🏪 Partenaires</option>
                            <option value="specifique">🎯 Utilisateur spécifique</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="utilisateur-group" style="display:none;">
                        <label for="utilisateur_id">Choisir un utilisateur</label>
                        <select class="form-control" id="utilisateur_id" name="utilisateur_id">
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($utilisateurs as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom']); ?>
                                    (<?php echo ucfirst($user['role']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="lien">Lien (optionnel)</label>
                        <input type="text" class="form-control" id="lien" name="lien" placeholder="https://...">
                    </div>
                    
                    <div class="form-group">
                        <label>Canaux d'envoi</label>
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" name="canaux[]" value="interne" checked disabled>
                                🔔 Interne (obligatoire)
                            </label>
                            <label>
                                <input type="checkbox" name="canaux[]" value="email" <?php echo $email_active ? 'checked' : ''; ?> <?php echo $email_active ? '' : 'disabled'; ?>>
                                📧 Email <?php echo $email_active ? '' : '(désactivé)'; ?>
                            </label>
                            <label>
                                <input type="checkbox" name="canaux[]" value="whatsapp" <?php echo $whatsapp_active ? 'checked' : ''; ?> <?php echo $whatsapp_active ? '' : 'disabled'; ?>>
                                💬 WhatsApp <?php echo $whatsapp_active ? '' : '(désactivé)'; ?>
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-envoyer">
                        <i class="fas fa-paper-plane"></i> Envoyer
                    </button>
                </form>
            </div>
            
            <!-- Notifications récentes -->
            <div class="card-notif">
                <div class="card-title"><i class="fas fa-history"></i> Dernières notifications</div>
                
                <?php if (!empty($notifications_recentes)): ?>
                    <?php foreach (array_slice($notifications_recentes, 0, 10) as $notif): 
                        $type_info = $types[$notif['type']] ?? ['label' => ucfirst($notif['type']), 'icon' => 'fa-circle'];
                        $color = $notif['type'] == 'commande' ? '#3b82f6' : 
                                ($notif['type'] == 'paiement' ? '#22c55e' : 
                                ($notif['type'] == 'promotion' ? '#ec4899' : 
                                ($notif['type'] == 'securite' ? '#ef4444' : '#6b7280')));
                    ?>
                        <div class="notif-item">
                            <div class="notif-icon" style="background: <?php echo $color; ?>20; color: <?php echo $color; ?>;">
                                <i class="fas <?php echo $type_info['icon']; ?>"></i>
                            </div>
                            <div class="notif-content">
                                <div class="notif-titre"><?php echo htmlspecialchars($notif['titre']); ?></div>
                                <div class="notif-message"><?php echo htmlspecialchars(substr($notif['message'], 0, 60)) . (strlen($notif['message']) > 60 ? '...' : ''); ?></div>
                            </div>
                            <div class="notif-status">
                                <span class="badge bg-<?php echo $notif['est_lu'] ? 'secondary' : 'danger'; ?>">
                                    <?php echo $notif['est_lu'] ? 'Lu' : 'Non lu'; ?>
                                </span>
                            </div>
                            <div class="notif-time"><?php echo temps_ecoule($notif['date_creation']); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center; padding:30px; color:#6b7280;">
                        <i class="fas fa-bell-slash" style="font-size:30px; display:block; margin-bottom:10px; color:#d1d5db;"></i>
                        <p>Aucune notification</p>
                    </div>
                <?php endif; ?>
                
                <div style="margin-top:10px; text-align:center;">
                    <a href="<?php echo URL_BASE; ?>notifications.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-right"></i> Voir toutes les notifications
                    </a>
                </div>
            </div>
            
        </div>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// TOGGLE UTILISATEUR SPECIFIQUE
// =============================================
function toggleUtilisateur(value) {
    const group = document.getElementById('utilisateur-group');
    if (value === 'specifique') {
        group.style.display = 'block';
    } else {
        group.style.display = 'none';
    }
}

// =============================================
// VALIDATION DU FORMULAIRE
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[action*="envoyer_notification"]');
    if (form) {
        form.addEventListener('submit', function(e) {
            const titre = document.getElementById('titre').value.trim();
            const message = document.getElementById('message').value.trim();
            
            if (!titre || !message) {
                e.preventDefault();
                showNotification('Veuillez remplir tous les champs obligatoires.', 'error');
            }
        });
    }
});

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

console.log('✅ DoriExpress-Pro - Module notifications chargé');
</script>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER MODULES/NOTIFICATIONS/INDEX.PHP
// =============================================
?>