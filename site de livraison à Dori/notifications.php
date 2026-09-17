<?php
/**
 * =============================================
 * PAGE DES NOTIFICATIONS - DoriExpress-Pro
 * =============================================
 * Fichier : notifications.php
 * Rôle : Affichage et gestion de toutes les notifications utilisateur
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
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('notifications.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Mes notifications - DoriExpress-Pro';
$page_description = 'Consultez et gérez vos notifications sur DoriExpress-Pro.';
$page_keywords = 'notifications, alertes, DoriExpress';

// Récupérer les notifications
try {
    $db = Database::getInstance();
    $user_id = $_SESSION['user_id'];
    
    // Pagination
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    // Filtrer par type
    $type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';
    $where = "WHERE utilisateur_id = ?";
    $params = [$user_id];
    
    if (!empty($type_filter)) {
        $where .= " AND type = ?";
        $params[] = $type_filter;
    }
    
    // Compter le total
    $countSql = "SELECT COUNT(*) FROM notifications $where";
    $total_notifications = (int) $db->fetchValue($countSql, $params);
    $total_pages = ceil($total_notifications / $limit);
    
    // Récupérer les notifications
    $sql = "SELECT * FROM notifications $where ORDER BY date_creation DESC LIMIT $limit OFFSET $offset";
    $notifications = $db->fetchAll($sql, $params);
    
    // Récupérer les types disponibles pour le filtre
    $types_disponibles = $db->fetchAll(
        "SELECT DISTINCT type FROM notifications WHERE utilisateur_id = ?",
        [$user_id]
    );
    
    // Compter les non lues
    $non_lues = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM notifications WHERE utilisateur_id = ? AND est_lu = 0",
        [$user_id]
    );
    
} catch (Exception $e) {
    $notifications = [];
    $total_notifications = 0;
    $total_pages = 1;
    $page = 1;
    $types_disponibles = [];
    $non_lues = 0;
}

// Définitions des types de notifications
$type_icons = [
    'commande' => ['icon' => 'fa-box', 'color' => '#3b82f6', 'bg' => 'rgba(59,130,246,0.1)'],
    'paiement' => ['icon' => 'fa-credit-card', 'color' => '#22c55e', 'bg' => 'rgba(34,197,94,0.1)'],
    'livraison' => ['icon' => 'fa-truck', 'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.1)'],
    'promotion' => ['icon' => 'fa-tag', 'color' => '#ec4899', 'bg' => 'rgba(236,72,153,0.1)'],
    'message' => ['icon' => 'fa-envelope', 'color' => '#8b5cf6', 'bg' => 'rgba(139,92,246,0.1)'],
    'securite' => ['icon' => 'fa-shield-alt', 'color' => '#ef4444', 'bg' => 'rgba(239,68,68,0.1)'],
    'systeme' => ['icon' => 'fa-cog', 'color' => '#6b7280', 'bg' => 'rgba(107,114,128,0.1)']
];

// Marquer toutes comme lues
if (isset($_GET['mark_all_read']) && $_GET['mark_all_read'] === 'true') {
    try {
        $db->query(
            "UPDATE notifications SET est_lu = 1, date_lecture = NOW() WHERE utilisateur_id = ? AND est_lu = 0",
            [$user_id]
        );
        header('Location: ' . URL_BASE . 'notifications.php?success=all_read');
        exit;
    } catch (Exception $e) {
        // Ignorer
    }
}

// Marquer une notification comme lue (AJAX)
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    try {
        $id = (int)$_GET['read'];
        $db->query(
            "UPDATE notifications SET est_lu = 1, date_lecture = NOW() WHERE id = ? AND utilisateur_id = ?",
            [$id, $user_id]
        );
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
        header('Location: ' . URL_BASE . 'notifications.php?success=read');
        exit;
    } catch (Exception $e) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}

// Supprimer une notification (AJAX)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $id = (int)$_GET['delete'];
        $db->query(
            "DELETE FROM notifications WHERE id = ? AND utilisateur_id = ?",
            [$id, $user_id]
        );
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
        header('Location: ' . URL_BASE . 'notifications.php?success=deleted');
        exit;
    } catch (Exception $e) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE NOTIFICATIONS
 * ============================================= */
.page-notifications {
    background: #f8fafc;
    min-height: 100vh;
    padding: 30px 0 60px;
}

.notifications-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 30px;
}

.notifications-header h1 {
    font-size: 28px;
    font-weight: 800;
    color: #1a1a1a;
    margin: 0;
}

.notifications-header .notif-count {
    font-size: 14px;
    color: #6b7280;
}

.notifications-header .notif-actions {
    display: flex;
    gap: 10px;
}

.notifications-header .notif-actions .btn {
    padding: 8px 18px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
    text-decoration: none;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.notifications-header .notif-actions .btn-primary {
    background: #00A651;
    color: white;
}

.notifications-header .notif-actions .btn-primary:hover {
    background: #008a44;
    transform: translateY(-2px);
}

.notifications-header .notif-actions .btn-outline {
    background: transparent;
    color: #6b7280;
    border: 2px solid #e5e7eb;
}

.notifications-header .notif-actions .btn-outline:hover {
    border-color: #00A651;
    color: #00A651;
}

/* Filters */
.notif-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 25px;
}

.notif-filters .filter-btn {
    padding: 6px 16px;
    border-radius: 50px;
    border: 2px solid #e5e7eb;
    background: white;
    font-weight: 600;
    font-size: 13px;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.notif-filters .filter-btn:hover {
    border-color: #00A651;
    color: #00A651;
}

.notif-filters .filter-btn.active {
    border-color: #00A651;
    background: #00A651;
    color: white;
}

/* Liste des notifications */
.notif-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.notif-item {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    background: white;
    border-radius: 14px;
    padding: 18px 22px;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
    position: relative;
}

.notif-item:hover {
    border-color: #00A651;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}

.notif-item.unread {
    background: #f0fdf4;
    border-left: 4px solid #00A651;
}

.notif-item .notif-icon {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.notif-item .notif-content {
    flex: 1;
}

.notif-item .notif-title {
    font-weight: 600;
    font-size: 15px;
    color: #1a1a1a;
    margin: 0 0 4px 0;
}

.notif-item .notif-message {
    font-size: 14px;
    color: #6b7280;
    line-height: 1.5;
    margin: 0 0 6px 0;
}

.notif-item .notif-message a {
    color: #00A651;
    text-decoration: none;
    font-weight: 600;
}

.notif-item .notif-message a:hover {
    text-decoration: underline;
}

.notif-item .notif-time {
    font-size: 12px;
    color: #9ca3af;
}

.notif-item .notif-actions-item {
    display: flex;
    gap: 8px;
    align-items: center;
}

.notif-item .notif-actions-item .btn-action {
    background: transparent;
    border: none;
    color: #9ca3af;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 6px;
    transition: all 0.3s ease;
    font-size: 14px;
}

.notif-item .notif-actions-item .btn-action:hover {
    background: #f3f4f6;
    color: #00A651;
}

.notif-item .notif-actions-item .btn-action.delete:hover {
    color: #ef4444;
}

.notif-item .notif-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #00A651;
    flex-shrink: 0;
    margin-top: 4px;
}

/* No notifications */
.no-notifications {
    text-align: center;
    padding: 60px 0;
    background: white;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
}

.no-notifications i {
    font-size: 60px;
    color: #d1d5db;
    display: block;
    margin-bottom: 15px;
}

.no-notifications h3 {
    color: #1a1a1a;
    margin-bottom: 10px;
}

.no-notifications p {
    color: #6b7280;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 25px;
}

.pagination .page-link {
    padding: 8px 16px;
    border-radius: 10px;
    border: 1px solid #e5e7eb;
    text-decoration: none;
    color: #6b7280;
    font-weight: 600;
    transition: all 0.3s ease;
    background: white;
}

.pagination .page-link:hover {
    border-color: #00A651;
    color: #00A651;
}

.pagination .page-link.active {
    background: #00A651;
    color: white;
    border-color: #00A651;
}

.pagination .page-link.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Responsive */
@media (max-width: 768px) {
    .notifications-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .notifications-header .notif-actions {
        width: 100%;
    }
    .notifications-header .notif-actions .btn {
        flex: 1;
        text-align: center;
    }
    .notif-item {
        flex-wrap: wrap;
        padding: 15px;
    }
    .notif-item .notif-actions-item {
        width: 100%;
        justify-content: flex-end;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid #f3f4f6;
    }
}

@media (max-width: 480px) {
    .notifications-header h1 {
        font-size: 22px;
    }
    .notif-filters .filter-btn {
        padding: 4px 12px;
        font-size: 12px;
    }
    .notif-item .notif-title {
        font-size: 14px;
    }
    .notif-item .notif-message {
        font-size: 13px;
    }
}

/* Dark Mode */
.dark-mode .page-notifications {
    background: #121212;
}

.dark-mode .notifications-header h1 {
    color: #e5e5e5;
}

.dark-mode .notifications-header .notif-count {
    color: #a0a0a0;
}

.dark-mode .notif-item {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .notif-item.unread {
    background: #0a1a10;
    border-left-color: #00A651;
}

.dark-mode .notif-item .notif-title {
    color: #e5e5e5;
}

.dark-mode .notif-item .notif-message {
    color: #b0b0b0;
}

.dark-mode .notif-item .notif-actions-item .btn-action:hover {
    background: #2a2a2a;
}

.dark-mode .notif-filters .filter-btn {
    background: #1e1e1e;
    border-color: #333;
    color: #b0b0b0;
}

.dark-mode .notif-filters .filter-btn:hover {
    border-color: #00A651;
    color: #00A651;
}

.dark-mode .notif-filters .filter-btn.active {
    background: #00A651;
    color: white;
}

.dark-mode .pagination .page-link {
    background: #1e1e1e;
    border-color: #333;
    color: #b0b0b0;
}

.dark-mode .pagination .page-link:hover {
    border-color: #00A651;
    color: #00A651;
}

.dark-mode .no-notifications {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .no-notifications h3 {
    color: #e5e5e5;
}

.dark-mode .no-notifications p {
    color: #b0b0b0;
}

.dark-mode .no-notifications i {
    color: #333;
}

.dark-mode .notif-item .notif-actions-item {
    border-color: #333;
}
</style>

<!-- ============================================= -->
<!-- PAGE NOTIFICATIONS -->
<!-- ============================================= -->
<div class="page-notifications">
    <div class="container">
        
        <!-- Header -->
        <div class="notifications-header">
            <div>
                <h1>🔔 Mes notifications</h1>
                <span class="notif-count">
                    <?php echo $total_notifications; ?> notification<?php echo $total_notifications > 1 ? 's' : ''; ?>
                    <?php if ($non_lues > 0): ?>
                        • <span style="color:#00A651; font-weight:700;"><?php echo $non_lues; ?> non lue<?php echo $non_lues > 1 ? 's' : ''; ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="notif-actions">
                <?php if ($non_lues > 0): ?>
                    <a href="?mark_all_read=true" class="btn btn-primary">
                        <i class="fas fa-check-double"></i> Tout marquer comme lu
                    </a>
                <?php endif; ?>
                <a href="<?php echo URL_BASE; ?>notifications.php" class="btn btn-outline">
                    <i class="fas fa-sync-alt"></i> Rafraîchir
                </a>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="notif-filters">
            <a href="<?php echo URL_BASE; ?>notifications.php" class="filter-btn <?php echo empty($type_filter) ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> Toutes
            </a>
            <?php foreach ($types_disponibles as $type): ?>
                <a href="?type=<?php echo urlencode($type['type']); ?>" 
                   class="filter-btn <?php echo $type_filter === $type['type'] ? 'active' : ''; ?>">
                    <?php 
                    $icon = $type_icons[$type['type']]['icon'] ?? 'fa-circle';
                    $label = ucfirst($type['type']);
                    ?>
                    <i class="fas <?php echo $icon; ?>"></i> <?php echo $label; ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <!-- Notifications List -->
        <?php if (!empty($notifications)): ?>
            <div class="notif-list">
                <?php foreach ($notifications as $notif): 
                    $type_info = $type_icons[$notif['type']] ?? $type_icons['systeme'];
                ?>
                    <div class="notif-item <?php echo $notif['est_lu'] ? '' : 'unread'; ?>" id="notif-<?php echo $notif['id']; ?>">
                        <div class="notif-icon" style="background: <?php echo $type_info['bg']; ?>; color: <?php echo $type_info['color']; ?>;">
                            <i class="fas <?php echo $type_info['icon']; ?>"></i>
                        </div>
                        <div class="notif-content">
                            <h4 class="notif-title"><?php echo htmlspecialchars($notif['titre']); ?></h4>
                            <p class="notif-message">
                                <?php 
                                $message = $notif['message'];
                                if ($notif['lien']) {
                                    $message .= ' <a href="' . htmlspecialchars($notif['lien']) . '">Voir</a>';
                                }
                                echo $message;
                                ?>
                            </p>
                            <span class="notif-time">
                                <i class="far fa-clock"></i> <?php echo temps_ecoule($notif['date_creation']); ?>
                            </span>
                        </div>
                        <?php if (!$notif['est_lu']): ?>
                            <span class="notif-dot"></span>
                        <?php endif; ?>
                        <div class="notif-actions-item">
                            <?php if (!$notif['est_lu']): ?>
                                <button class="btn-action" onclick="marquerLu(<?php echo $notif['id']; ?>)" title="Marquer comme lu">
                                    <i class="fas fa-check"></i>
                                </button>
                            <?php endif; ?>
                            <button class="btn-action delete" onclick="supprimerNotif(<?php echo $notif['id']; ?>)" title="Supprimer">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?><?php echo !empty($type_filter) ? '&type='.urlencode($type_filter) : ''; ?>" class="page-link">← Précédent</a>
                    <?php else: ?>
                        <span class="page-link disabled">← Précédent</span>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo !empty($type_filter) ? '&type='.urlencode($type_filter) : ''; ?>" 
                           class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?><?php echo !empty($type_filter) ? '&type='.urlencode($type_filter) : ''; ?>" class="page-link">Suivant →</a>
                    <?php else: ?>
                        <span class="page-link disabled">Suivant →</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="no-notifications">
                <i class="fas fa-bell-slash"></i>
                <h3>Aucune notification</h3>
                <p>Vous n'avez pas encore de notifications.</p>
                <a href="<?php echo URL_BASE; ?>index.php" class="btn btn-success mt-3">
                    <i class="fas fa-home"></i> Retour à l'accueil
                </a>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// MARQUER COMME LU (AJAX)
// =============================================
function marquerLu(id) {
    fetch('<?php echo URL_BASE; ?>notifications.php?read=' + id, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const item = document.getElementById('notif-' + id);
            if (item) {
                item.classList.remove('unread');
                const dot = item.querySelector('.notif-dot');
                if (dot) dot.remove();
                const actions = item.querySelector('.notif-actions-item');
                const btn = actions?.querySelector('.btn-action');
                if (btn) btn.remove();
                
                // Mettre à jour le compteur
                const countSpan = document.querySelector('.notif-count span');
                if (countSpan) {
                    const current = parseInt(countSpan.textContent);
                    if (current > 0) {
                        countSpan.textContent = current - 1;
                        if (current - 1 === 0) {
                            countSpan.textContent = '0';
                            countSpan.style.display = 'none';
                        }
                    }
                }
                showNotification('✅ Notification marquée comme lue', 'success');
            }
        } else {
            showNotification('❌ Erreur', 'error');
        }
    })
    .catch(error => {
        showNotification('❌ Erreur', 'error');
    });
}

// =============================================
// SUPPRIMER UNE NOTIFICATION (AJAX)
// =============================================
function supprimerNotif(id) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer cette notification ?')) {
        return;
    }
    
    fetch('<?php echo URL_BASE; ?>notifications.php?delete=' + id, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const item = document.getElementById('notif-' + id);
            if (item) {
                item.style.animation = 'slideOutRight 0.5s ease';
                setTimeout(() => {
                    item.remove();
                    // Recharger la page si plus de notifications
                    const remaining = document.querySelectorAll('.notif-item');
                    if (remaining.length === 0) {
                        location.reload();
                    }
                }, 500);
            }
            showNotification('🗑️ Notification supprimée', 'info');
        } else {
            showNotification('❌ Erreur', 'error');
        }
    })
    .catch(error => {
        showNotification('❌ Erreur', 'error');
    });
}

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

// =============================================
// AUTO-REFRESH DES NOTIFICATIONS
// =============================================
// Vérifier les nouvelles notifications toutes les 30 secondes
setInterval(function() {
    fetch('<?php echo URL_BASE; ?>api/notifications/count.php')
        .then(response => response.json())
        .then(data => {
            if (data.count > 0) {
                // Mettre à jour le compteur dans la navbar
                const badge = document.querySelector('.badge-notification');
                if (badge) {
                    badge.textContent = data.count;
                    badge.style.display = 'flex';
                }
                // Mettre à jour le compteur sur la page
                const countSpan = document.querySelector('.notif-count');
                if (countSpan) {
                    countSpan.innerHTML = data.total + ' notifications • <span style="color:#00A651;font-weight:700;">' + data.count + ' non lue' + (data.count > 1 ? 's' : '') + '</span>';
                }
            }
        })
        .catch(() => {});
}, 30000);

console.log('✅ DoriExpress-Pro - Page notifications chargée');
</script>

<style>
.animate-on-scroll {
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.6s ease;
}

.animate-on-scroll.animated {
    opacity: 1;
    transform: translateY(0);
}

@keyframes slideOutRight {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100px); opacity: 0; }
}

@keyframes slideInRight {
    from { transform: translateX(100px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
</style>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER NOTIFICATIONS.PHP
// =============================================
?>