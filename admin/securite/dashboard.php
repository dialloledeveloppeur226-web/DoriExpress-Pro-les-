<?php
/**
 * =============================================
 * DASHBOARD SÉCURITÉ - ADMIN - DoriExpress-Pro
 * =============================================
 * Fichier : admin/securite/dashboard.php
 * Rôle : Surveillance et gestion de la sécurité de la plateforme
 * Niveau : Uber / Glovo / Deliveroo
 * =============================================
 */

// Définir le chemin racine
define('DOSSIER_RACINE', dirname(__DIR__) . '/');

// Inclure les fichiers nécessaires
require_once DOSSIER_RACINE . 'includes/config.php';
require_once DOSSIER_RACINE . 'includes/connexion.php';
require_once DOSSIER_RACINE . 'includes/auth.php';
require_once DOSSIER_RACINE . 'includes/security.php';
require_once DOSSIER_RACINE . 'includes/functions.php';

// Vérifier que l'utilisateur est connecté et est admin ou créateur
if (!est_connecte() || !est_admin()) {
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('admin/securite/dashboard.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Dashboard sécurité - DoriExpress-Pro';
$page_description = 'Surveillez la sécurité de la plateforme.';
$page_keywords = 'sécurité, logs, surveillance, admin, DoriExpress';

try {
    $db = Database::getInstance();
    
    // === STATISTIQUES DE SÉCURITÉ ===
    
    // Connexions aujourd'hui
    $connexions_aujourdhui = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM logs WHERE action = 'connexion_succes' AND DATE(date_log) = CURDATE()"
    );
    
    // Tentatives échouées aujourd'hui
    $tentatives_echouees = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM logs WHERE action = 'connexion_echoue' AND DATE(date_log) = CURDATE()"
    );
    
    // Tentatives échouées (total)
    $tentatives_echouees_total = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM logs WHERE action = 'connexion_echoue'"
    );
    
    // Alertes de sécurité
    $alertes_securite = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM securite_logs WHERE type IN ('sql_injection_detected', 'xss_detected', 'brute_force_blocked', 'csrf_attack_detected')"
    );
    
    // IP bloquées
    $ip_bloquees = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM securite_logs WHERE type = 'blocage_ip' AND date_expiration > NOW()"
    );
    
    // Sessions actives
    $sessions_actives = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM sessions WHERE date_expiration > NOW() AND est_active = 1"
    );
    
    // === DERNIERS LOGS ===
    $derniers_logs = $db->fetchAll(
        "SELECT * FROM logs ORDER BY date_log DESC LIMIT 20"
    );
    
    // === ALERTES DE SÉCURITÉ RÉCENTES ===
    $alertes_recentes = $db->fetchAll(
        "SELECT * FROM securite_logs ORDER BY date_creation DESC LIMIT 10"
    );
    
    // === IP BLOQUÉES ===
    $ip_bloquees_list = $db->fetchAll(
        "SELECT * FROM securite_logs WHERE type = 'blocage_ip' AND date_expiration > NOW() ORDER BY date_creation DESC LIMIT 10"
    );
    
    // === ACTIVITÉS PAR HEURE (24h) ===
    $activites_par_heure = [];
    for ($i = 0; $i < 24; $i++) {
        $heure = str_pad($i, 2, '0', STR_PAD_LEFT);
        $count = (int) $db->fetchValue(
            "SELECT COUNT(*) FROM logs WHERE HOUR(date_log) = ? AND DATE(date_log) = CURDATE()",
            [$i]
        );
        $activites_par_heure[] = ['heure' => $heure, 'count' => $count];
    }
    
    // === UTILISATEURS CONNECTÉS (dernières 5 minutes) ===
    $utilisateurs_connectes = $db->fetchAll(
        "SELECT DISTINCT u.id, u.nom, u.prenom, u.role, u.photo,
                s.date_creation as connexion_time
         FROM sessions s
         JOIN utilisateurs u ON s.utilisateur_id = u.id
         WHERE s.date_expiration > NOW() AND s.est_active = 1
         ORDER BY s.date_creation DESC LIMIT 10"
    );
    
} catch (Exception $e) {
    $connexions_aujourdhui = 0;
    $tentatives_echouees = 0;
    $tentatives_echouees_total = 0;
    $alertes_securite = 0;
    $ip_bloquees = 0;
    $sessions_actives = 0;
    $derniers_logs = [];
    $alertes_recentes = [];
    $ip_bloquees_list = [];
    $activites_par_heure = [];
    $utilisateurs_connectes = [];
}

// Inclure le header admin
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE SECURITE DASHBOARD
 * ============================================= */
.page-securite-dashboard {
    background: #f8fafc;
    min-height: 100vh;
    padding: 20px 0 60px;
}

.admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}

.admin-header h1 {
    font-size: 24px;
    font-weight: 800;
    color: #1a1a1a;
    margin: 0;
}

/* Stats Cards */
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

.stat-card .stat-number.red {
    color: #ef4444;
}

.stat-card .stat-number.gold {
    color: #f59e0b;
}

.stat-card .stat-number.blue {
    color: #3b82f6;
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

/* Logs */
.logs-container {
    background: white;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid #e5e7eb;
    margin-bottom: 20px;
}

.logs-container .logs-title {
    font-size: 16px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 15px;
}

.log-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 0;
    border-bottom: 1px solid #f3f4f6;
    font-size: 14px;
}

.log-item:last-child {
    border-bottom: none;
}

.log-item .log-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    flex-shrink: 0;
}

.log-item .log-icon.success { background: rgba(34, 197, 94, 0.1); color: #22c55e; }
.log-item .log-icon.danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
.log-item .log-icon.warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.log-item .log-icon.info { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }

.log-item .log-content {
    flex: 1;
}

.log-item .log-content .log-action {
    font-weight: 600;
    color: #1a1a1a;
}

.log-item .log-content .log-detail {
    color: #6b7280;
    font-size: 13px;
}

.log-item .log-time {
    font-size: 12px;
    color: #9ca3af;
    white-space: nowrap;
}

/* Alertes */
.alert-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 10px;
    margin-bottom: 8px;
    border-left: 4px solid;
}

.alert-item.critical { background: #fef2f2; border-color: #ef4444; }
.alert-item.warning { background: #fffbeb; border-color: #f59e0b; }
.alert-item.info { background: #eff6ff; border-color: #3b82f6; }

.alert-item .alert-icon { font-size: 18px; }
.alert-item.critical .alert-icon { color: #ef4444; }
.alert-item.warning .alert-icon { color: #f59e0b; }
.alert-item.info .alert-icon { color: #3b82f6; }

.alert-item .alert-content {
    flex: 1;
    font-size: 14px;
    color: #1a1a1a;
}

.alert-item .alert-time {
    font-size: 12px;
    color: #9ca3af;
}

/* Grid Layouts */
.securite-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

/* Chart */
.chart-container {
    background: white;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid #e5e7eb;
}

.chart-container .chart-title {
    font-size: 16px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 15px;
}

.chart-bars {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    height: 100px;
    gap: 4px;
}

.chart-bar-wrapper {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    height: 100%;
}

.chart-bar {
    width: 100%;
    max-width: 20px;
    border-radius: 4px 4px 0 0;
    background: #3b82f6;
    transition: height 0.8s ease;
    min-height: 4px;
}

.chart-bar.danger {
    background: #ef4444;
}

.chart-label {
    font-size: 9px;
    color: #6b7280;
    margin-top: 4px;
}

.chart-value {
    font-size: 9px;
    font-weight: 600;
    color: #1a1a1a;
    margin-bottom: 2px;
}

/* Responsive */
@media (max-width: 768px) {
    .admin-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    .securite-grid-2 {
        grid-template-columns: 1fr;
    }
    .log-item {
        flex-wrap: wrap;
        gap: 6px;
    }
    .log-item .log-time {
        margin-left: 42px;
    }
}

@media (max-width: 480px) {
    .admin-header h1 {
        font-size: 20px;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .chart-bars {
        height: 80px;
    }
    .alert-item {
        flex-wrap: wrap;
    }
}

/* Dark Mode */
.dark-mode .page-securite-dashboard {
    background: #121212;
}

.dark-mode .admin-header h1 {
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

.dark-mode .logs-container {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .logs-container .logs-title {
    color: #e5e5e5;
}

.dark-mode .log-item {
    border-color: #333;
}

.dark-mode .log-item .log-content .log-action {
    color: #e5e5e5;
}

.dark-mode .log-item .log-content .log-detail {
    color: #b0b0b0;
}

.dark-mode .alert-item.critical { background: #2a1414; }
.dark-mode .alert-item.warning { background: #2a2414; }
.dark-mode .alert-item.info { background: #14202a; }

.dark-mode .alert-item .alert-content {
    color: #e5e5e5;
}

.dark-mode .chart-container {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .chart-container .chart-title {
    color: #e5e5e5;
}

.dark-mode .chart-value {
    color: #e5e5e5;
}

.dark-mode .chart-label {
    color: #b0b0b0;
}
</style>

<!-- ============================================= -->
<!-- PAGE SECURITE DASHBOARD -->
<!-- ============================================= -->
<div class="page-securite-dashboard">
    <div class="container">
        
        <!-- Header -->
        <div class="admin-header">
            <h1><i class="fas fa-shield-alt" style="color:#00A651;"></i> Dashboard sécurité</h1>
            <div>
                <span class="badge bg-<?php echo $alertes_securite > 0 ? 'danger' : 'success'; ?>">
                    <?php echo $alertes_securite > 0 ? '⚠️ ' . $alertes_securite . ' alertes' : '✅ Sécurisé'; ?>
                </span>
                <a href="<?php echo URL_BASE; ?>admin/securite/logs.php" class="btn btn-outline-secondary btn-sm ms-2">
                    <i class="fas fa-list"></i> Tous les logs
                </a>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">✅</span>
                <span class="stat-number green"><?php echo $connexions_aujourdhui; ?></span>
                <span class="stat-label">Connexions réussies</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">❌</span>
                <span class="stat-number red"><?php echo $tentatives_echouees; ?></span>
                <span class="stat-label">Tentatives échouées (auj.)</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🔴</span>
                <span class="stat-number red"><?php echo $tentatives_echouees_total; ?></span>
                <span class="stat-label">Tentatives échouées (total)</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">⚠️</span>
                <span class="stat-number gold"><?php echo $alertes_securite; ?></span>
                <span class="stat-label">Alertes de sécurité</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🚫</span>
                <span class="stat-number red"><?php echo $ip_bloquees; ?></span>
                <span class="stat-label">IP bloquées</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">👤</span>
                <span class="stat-number blue"><?php echo $sessions_actives; ?></span>
                <span class="stat-label">Sessions actives</span>
            </div>
        </div>
        
        <!-- Activité par heure + Alertes -->
        <div class="securite-grid-2">
            
            <!-- Activité par heure -->
            <div class="chart-container">
                <div class="chart-title">📊 Activité par heure (aujourd'hui)</div>
                <div class="chart-bars">
                    <?php 
                    $max_activite = max(array_column($activites_par_heure, 'count'));
                    $max_activite = $max_activite > 0 ? $max_activite : 1;
                    foreach ($activites_par_heure as $data): 
                    ?>
                        <div class="chart-bar-wrapper">
                            <span class="chart-value"><?php echo $data['count']; ?></span>
                            <div class="chart-bar <?php echo $data['count'] > 5 ? 'danger' : ''; ?>" 
                                 style="height: <?php echo max(4, ($data['count'] / $max_activite) * 100); ?>%;"></div>
                            <span class="chart-label"><?php echo $data['heure']; ?>h</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Alertes récentes -->
            <div class="logs-container">
                <div class="logs-title"><i class="fas fa-bell" style="color:#f59e0b;"></i> Alertes récentes</div>
                <?php if (!empty($alertes_recentes)): ?>
                    <?php foreach (array_slice($alertes_recentes, 0, 5) as $alerte): ?>
                        <div class="alert-item <?php echo $alerte['type'] == 'blocage_ip' ? 'warning' : ($alerte['type'] == 'brute_force_blocked' ? 'critical' : 'info'); ?>">
                            <span class="alert-icon">
                                <i class="fas <?php echo $alerte['type'] == 'blocage_ip' ? 'fa-ban' : ($alerte['type'] == 'brute_force_blocked' ? 'fa-exclamation-triangle' : 'fa-info-circle'); ?>"></i>
                            </span>
                            <span class="alert-content">
                                <strong><?php echo ucfirst(str_replace('_', ' ', $alerte['type'])); ?></strong>
                                <?php if ($alerte['adresse_ip']): ?>
                                    • IP: <?php echo $alerte['adresse_ip']; ?>
                                <?php endif; ?>
                            </span>
                            <span class="alert-time"><?php echo temps_ecoule($alerte['date_creation']); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center; padding:20px; color:#6b7280;">
                        <i class="fas fa-check-circle" style="font-size:30px; color:#22c55e; display:block; margin-bottom:10px;"></i>
                        <p>Aucune alerte récente</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Derniers logs -->
        <div class="logs-container">
            <div class="logs-title"><i class="fas fa-history" style="color:#3b82f6;"></i> Derniers logs</div>
            <?php if (!empty($derniers_logs)): ?>
                <?php foreach (array_slice($derniers_logs, 0, 10) as $log): ?>
                    <div class="log-item">
                        <div class="log-icon <?php echo strpos($log['action'], 'succes') !== false ? 'success' : (strpos($log['action'], 'echoue') !== false ? 'danger' : 'info'); ?>">
                            <i class="fas <?php echo strpos($log['action'], 'succes') !== false ? 'fa-check' : (strpos($log['action'], 'echoue') !== false ? 'fa-times' : 'fa-info'); ?>"></i>
                        </div>
                        <div class="log-content">
                            <div class="log-action"><?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?></div>
                            <div class="log-detail">
                                <?php echo $log['module'] ?? 'Système'; ?>
                                <?php if ($log['adresse_ip']): ?>
                                    • IP: <?php echo $log['adresse_ip']; ?>
                                <?php endif; ?>
                                <?php if ($log['utilisateur_id']): ?>
                                    • Utilisateur #<?php echo $log['utilisateur_id']; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="log-time"><?php echo formater_date($log['date_log'], 'H:i'); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align:center; padding:20px; color:#6b7280;">
                    <p>Aucun log disponible</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- IP bloquées -->
        <div class="logs-container">
            <div class="logs-title"><i class="fas fa-ban" style="color:#ef4444;"></i> IP bloquées</div>
            <?php if (!empty($ip_bloquees_list)): ?>
                <div style="display:flex; flex-wrap:wrap; gap:8px;">
                    <?php foreach ($ip_bloquees_list as $ip): ?>
                        <span style="background:#fef2f2; color:#ef4444; padding:4px 14px; border-radius:50px; font-size:14px; font-weight:600; border:1px solid #fecaca;">
                            <?php echo htmlspecialchars($ip['adresse_ip']); ?>
                            <button onclick="debloquerIP('<?php echo $ip['adresse_ip']; ?>')" 
                                    style="background:transparent; border:none; color:#ef4444; cursor:pointer; margin-left:6px;" 
                                    title="Débloquer">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align:center; padding:10px; color:#6b7280;">
                    <p>Aucune IP bloquée</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Utilisateurs connectés -->
        <div class="logs-container">
            <div class="logs-title"><i class="fas fa-users" style="color:#00A651;"></i> Utilisateurs connectés</div>
            <?php if (!empty($utilisateurs_connectes)): ?>
                <div style="display:flex; flex-wrap:wrap; gap:15px;">
                    <?php foreach ($utilisateurs_connectes as $user): ?>
                        <div style="display:flex; align-items:center; gap:8px; background:#f8fafc; padding:6px 14px; border-radius:50px; border:1px solid #e5e7eb;">
                            <img src="<?php echo URL_BASE . 'uploads/profils/' . ($user['photo'] ?? 'default.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($user['nom']); ?>" 
                                 style="width:24px; height:24px; border-radius:50%; object-fit:cover;">
                            <span style="font-weight:600; font-size:13px; color:#1a1a1a;">
                                <?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom']); ?>
                            </span>
                            <span style="font-size:11px; color:#6b7280; background:#e5e7eb; padding:1px 10px; border-radius:50px;">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align:center; padding:10px; color:#6b7280;">
                    <p>Aucun utilisateur connecté</p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// DÉBLOQUER UNE IP
// =============================================
function debloquerIP(ip) {
    if (!confirm('Débloquer l\'IP ' + ip + ' ?')) {
        return;
    }
    
    fetch('<?php echo URL_BASE; ?>api/admin/debloquer_ip.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            ip: ip,
            csrf_token: '<?php echo generer_token_csrf(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ IP débloquée avec succès', 'success');
            location.reload();
        } else {
            showNotification('❌ ' + data.message, 'error');
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

console.log('✅ DoriExpress-Pro - Sécurité dashboard chargé');
</script>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER ADMIN/SECURITE/DASHBOARD.PHP
// =============================================
?>