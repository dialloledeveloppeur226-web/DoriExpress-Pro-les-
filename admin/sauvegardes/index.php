<?php
/**
 * =============================================
 * SAUVEGARDES - ADMIN - DoriExpress-Pro
 * =============================================
 * Fichier : admin/sauvegardes/index.php
 * Rôle : Gestion des sauvegardes de la base de données et des fichiers
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
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('admin/sauvegardes/index.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Sauvegardes - DoriExpress-Pro';
$page_description = 'Gérez les sauvegardes de la plateforme.';
$page_keywords = 'sauvegardes, backup, admin, DoriExpress';

// Définir les dossiers
$backup_dir = DOSSIER_RACINE . 'storage/backups/';
$log_dir = DOSSIER_RACINE . 'storage/logs/';

// Créer les dossiers s'ils n'existent pas
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

try {
    $db = Database::getInstance();
    
    // Récupérer les sauvegardes existantes
    $backups = $db->fetchAll(
        "SELECT * FROM backups ORDER BY date_creation DESC"
    );
    
    // Récupérer les fichiers de sauvegarde dans le dossier
    $backup_files = [];
    if (is_dir($backup_dir)) {
        $files = scandir($backup_dir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && !is_dir($backup_dir . $file)) {
                $backup_files[] = [
                    'nom' => $file,
                    'taille' => filesize($backup_dir . $file),
                    'date' => filemtime($backup_dir . $file)
                ];
            }
        }
        // Trier par date décroissante
        usort($backup_files, function($a, $b) {
            return $b['date'] - $a['date'];
        });
    }
    
    // Statistiques
    $stats = [
        'total' => count($backup_files),
        'taille_totale' => array_sum(array_column($backup_files, 'taille')),
        'derniere' => !empty($backup_files) ? date('d/m/Y H:i', $backup_files[0]['date']) : 'Aucune',
        'dernier_sql' => $db->fetchValue(
            "SELECT date_creation FROM backups WHERE type = 'base_donnees' ORDER BY date_creation DESC LIMIT 1"
        )
    ];
    
} catch (Exception $e) {
    $backups = [];
    $backup_files = [];
    $stats = ['total' => 0, 'taille_totale' => 0, 'derniere' => 'Aucune', 'dernier_sql' => null];
}

// Fonction pour formater la taille
function format_size($bytes) {
    if ($bytes == 0) return '0 B';
    $k = 1024;
    $sizes = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

// Traitement des actions
$error = '';
$success = '';

// Action: Créer une sauvegarde
if (isset($_GET['action']) && $_GET['action'] === 'create') {
    $csrf_token = $_GET['csrf_token'] ?? '';
    if (!verifier_token_csrf($csrf_token)) {
        $error = 'Erreur de sécurité.';
    } else {
        $type = isset($_GET['type']) ? $_GET['type'] : 'complet';
        
        try {
            $db->beginTransaction();
            
            $nom_fichier = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $chemin_fichier = $backup_dir . $nom_fichier;
            
            // Exporter la base de données
            $tables = $db->fetchAll("SHOW TABLES");
            $output = "-- =============================================\n";
            $output .= "-- SAUVEGARDE DoriExpress-Pro\n";
            $output .= "-- Date : " . date('Y-m-d H:i:s') . "\n";
            $output .= "-- Base : " . DB_NAME . "\n";
            $output .= "-- =============================================\n\n";
            $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
            
            foreach ($tables as $row) {
                $table = reset($row);
                
                // Structure
                $result = $db->query("SHOW CREATE TABLE " . $table);
                $row_data = $result->fetch(PDO::FETCH_NUM);
                $output .= $row_data[1] . ";\n\n";
                
                // Données
                $rows = $db->fetchAll("SELECT * FROM " . $table);
                if (!empty($rows)) {
                    $output .= "INSERT INTO `" . $table . "` VALUES\n";
                    $values = [];
                    foreach ($rows as $row_values) {
                        $escaped = array_map(function($value) {
                            return $value === null ? 'NULL' : "'" . addslashes($value) . "'";
                        }, $row_values);
                        $values[] = "(" . implode(", ", $escaped) . ")";
                    }
                    $output .= implode(",\n", $values) . ";\n\n";
                }
            }
            
            $output .= "SET FOREIGN_KEY_CHECKS=1;\n";
            
            // Écrire le fichier
            file_put_contents($chemin_fichier, $output);
            
            // Enregistrer dans la base
            $db->query(
                "INSERT INTO backups (nom_fichier, taille, type, statut, date_creation, cree_par) 
                 VALUES (?, ?, ?, 'termine', NOW(), ?)",
                [$nom_fichier, format_size(filesize($chemin_fichier)), $type, $_SESSION['user_id']]
            );
            
            $db->commit();
            $success = '✅ Sauvegarde créée avec succès !';
            
            // Recharger les données
            header('Location: ' . URL_BASE . 'admin/sauvegardes/index.php?success=created');
            exit;
            
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Erreur lors de la création de la sauvegarde.';
        }
    }
}

// Action: Télécharger une sauvegarde
if (isset($_GET['action']) && $_GET['action'] === 'download' && isset($_GET['file'])) {
    $file = $_GET['file'];
    $file_path = $backup_dir . $file;
    
    if (file_exists($file_path)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    } else {
        $error = 'Fichier introuvable.';
    }
}

// Action: Supprimer une sauvegarde
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['file'])) {
    $csrf_token = $_GET['csrf_token'] ?? '';
    if (!verifier_token_csrf($csrf_token)) {
        $error = 'Erreur de sécurité.';
    } else {
        $file = $_GET['file'];
        $file_path = $backup_dir . $file;
        
        if (file_exists($file_path)) {
            unlink($file_path);
            $db->query("DELETE FROM backups WHERE nom_fichier = ?", [$file]);
            $success = '✅ Sauvegarde supprimée avec succès !';
            header('Location: ' . URL_BASE . 'admin/sauvegardes/index.php?success=deleted');
            exit;
        } else {
            $error = 'Fichier introuvable.';
        }
    }
}

// Action: Restaurer une sauvegarde
if (isset($_GET['action']) && $_GET['action'] === 'restore' && isset($_GET['file'])) {
    $csrf_token = $_GET['csrf_token'] ?? '';
    if (!verifier_token_csrf($csrf_token)) {
        $error = 'Erreur de sécurité.';
    } else {
        $file = $_GET['file'];
        $file_path = $backup_dir . $file;
        
        if (file_exists($file_path)) {
            // Lire le fichier SQL
            $sql = file_get_contents($file_path);
            
            try {
                $db->beginTransaction();
                
                // Nettoyer et exécuter
                $sql = preg_replace('/^--.*$/m', '', $sql);
                $sql = preg_replace('/^#.*$/m', '', $sql);
                $sql = preg_replace('/^\/\*.*?\*\//s', '', $sql);
                $queries = preg_split('/;\s*$/m', $sql);
                
                foreach ($queries as $query) {
                    $query = trim($query);
                    if (!empty($query)) {
                        $db->query($query);
                    }
                }
                
                $db->commit();
                $success = '✅ Base de données restaurée avec succès !';
                header('Location: ' . URL_BASE . 'admin/sauvegardes/index.php?success=restored');
                exit;
                
            } catch (Exception $e) {
                $db->rollback();
                $error = 'Erreur lors de la restauration.';
            }
        } else {
            $error = 'Fichier introuvable.';
        }
    }
}

// Inclure le header admin
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE SAUVEGARDES
 * ============================================= */
.page-sauvegardes {
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
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 18px 20px;
    border: 1px solid #e5e7eb;
    text-align: center;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.05);
}

.stat-card .stat-number {
    font-size: 22px;
    font-weight: 800;
    color: #1a1a1a;
    display: block;
}

.stat-card .stat-number.green {
    color: #22c55e;
}

.stat-card .stat-number.blue {
    color: #3b82f6;
}

.stat-card .stat-label {
    font-size: 13px;
    color: #6b7280;
}

.stat-card .stat-icon {
    font-size: 20px;
    display: block;
    margin-bottom: 5px;
}

/* Actions */
.actions-bar {
    background: white;
    border-radius: 12px;
    padding: 15px 20px;
    border: 1px solid #e5e7eb;
    margin-bottom: 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
}

.actions-bar .btn-action {
    padding: 10px 20px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.actions-bar .btn-action.create {
    background: #00A651;
    color: white;
}

.actions-bar .btn-action.create:hover {
    background: #008a44;
    transform: translateY(-2px);
}

.actions-bar .btn-action.create:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.actions-bar .btn-action.secondary {
    background: transparent;
    color: #6b7280;
    border: 2px solid #e5e7eb;
}

.actions-bar .btn-action.secondary:hover {
    border-color: #00A651;
    color: #00A651;
}

/* Table */
.table-responsive {
    background: white;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
}

.table-responsive .table {
    width: 100%;
    border-collapse: collapse;
}

.table-responsive .table th {
    padding: 12px 16px;
    text-align: left;
    font-weight: 700;
    font-size: 13px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: #f8fafc;
    border-bottom: 2px solid #e5e7eb;
}

.table-responsive .table td {
    padding: 12px 16px;
    font-size: 14px;
    color: #1a1a1a;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
}

.table-responsive .table tr:hover td {
    background: #fafbfc;
}

.table-responsive .table .actions {
    display: flex;
    gap: 6px;
}

.table-responsive .table .actions .btn-action {
    padding: 4px 10px;
    border-radius: 6px;
    border: none;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.table-responsive .table .actions .btn-action.download {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.table-responsive .table .actions .btn-action.download:hover {
    background: #3b82f6;
    color: white;
}

.table-responsive .table .actions .btn-action.restore {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.table-responsive .table .actions .btn-action.restore:hover {
    background: #f59e0b;
    color: white;
}

.table-responsive .table .actions .btn-action.delete {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.table-responsive .table .actions .btn-action.delete:hover {
    background: #ef4444;
    color: white;
}

/* No results */
.no-results {
    text-align: center;
    padding: 40px 20px;
    color: #6b7280;
}

.no-results i {
    font-size: 50px;
    color: #d1d5db;
    display: block;
    margin-bottom: 15px;
}

/* Responsive */
@media (max-width: 768px) {
    .admin-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .actions-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .actions-bar .btn-action {
        justify-content: center;
    }
    .table-responsive {
        overflow-x: auto;
    }
    .table-responsive .table {
        font-size: 13px;
    }
    .table-responsive .table th,
    .table-responsive .table td {
        padding: 8px 12px;
    }
}

@media (max-width: 480px) {
    .admin-header h1 {
        font-size: 20px;
    }
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
}

/* Dark Mode */
.dark-mode .page-sauvegardes {
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

.dark-mode .actions-bar {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .actions-bar .btn-action.secondary {
    color: #b0b0b0;
    border-color: #444;
}

.dark-mode .actions-bar .btn-action.secondary:hover {
    border-color: #00A651;
    color: #00A651;
}

.dark-mode .table-responsive {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .table-responsive .table th {
    background: #2a2a2a;
    color: #b0b0b0;
    border-color: #333;
}

.dark-mode .table-responsive .table td {
    color: #e5e5e5;
    border-color: #333;
}

.dark-mode .table-responsive .table tr:hover td {
    background: #2a2a2a;
}

.dark-mode .no-results {
    color: #b0b0b0;
}

.dark-mode .no-results i {
    color: #333;
}
</style>

<!-- ============================================= -->
<!-- PAGE SAUVEGARDES -->
<!-- ============================================= -->
<div class="page-sauvegardes">
    <div class="container">
        
        <!-- Header -->
        <div class="admin-header">
            <h1><i class="fas fa-database" style="color:#00A651;"></i> Sauvegardes</h1>
            <div>
                <span class="badge bg-secondary"><?php echo $stats['total']; ?> sauvegardes</span>
                <a href="<?php echo URL_BASE; ?>admin/createur.php" class="btn btn-outline-secondary btn-sm ms-2">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if ($error): ?>
            <div class="alert alert-danger" style="margin-bottom:20px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success" style="margin-bottom:20px;">
                <i class="fas fa-check-circle"></i> 
                <?php 
                if ($_GET['success'] == 'created') echo 'Sauvegarde créée avec succès !';
                elseif ($_GET['success'] == 'deleted') echo 'Sauvegarde supprimée avec succès !';
                elseif ($_GET['success'] == 'restored') echo 'Base de données restaurée avec succès !';
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">📦</span>
                <span class="stat-number"><?php echo $stats['total']; ?></span>
                <span class="stat-label">Total sauvegardes</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">💾</span>
                <span class="stat-number blue"><?php echo format_size($stats['taille_totale']); ?></span>
                <span class="stat-label">Taille totale</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🕐</span>
                <span class="stat-number green"><?php echo $stats['derniere']; ?></span>
                <span class="stat-label">Dernière sauvegarde</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🗄️</span>
                <span class="stat-number">
                    <?php echo $stats['dernier_sql'] ? formater_date($stats['dernier_sql'], 'd/m/Y H:i') : 'Aucune'; ?>
                </span>
                <span class="stat-label">Dernier SQL</span>
            </div>
        </div>
        
        <!-- Actions -->
        <div class="actions-bar">
            <a href="?action=create&csrf_token=<?php echo generer_token_csrf(); ?>" class="btn-action create">
                <i class="fas fa-plus"></i> Créer une sauvegarde
            </a>
            <a href="?action=create&type=base_donnees&csrf_token=<?php echo generer_token_csrf(); ?>" class="btn-action secondary">
                <i class="fas fa-database"></i> Sauvegarder BDD uniquement
            </a>
            <span style="font-size:13px; color:#6b7280; margin-left:auto;">
                ⚠️ Les sauvegardes sont stockées dans /storage/backups/
            </span>
        </div>
        
        <!-- Table -->
        <div class="table-responsive">
            <?php if (!empty($backup_files)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fichier</th>
                            <th>Taille</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backup_files as $file): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($file['nom']); ?></strong>
                                    <?php if (strpos($file['nom'], '.sql') !== false): ?>
                                        <span class="badge bg-success ms-2">SQL</span>
                                    <?php elseif (strpos($file['nom'], '.zip') !== false): ?>
                                        <span class="badge bg-info ms-2">ZIP</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary ms-2">FILE</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo format_size($file['taille']); ?></td>
                                <td><?php echo date('d/m/Y H:i', $file['date']); ?></td>
                                <td>
                                    <div class="actions">
                                        <a href="?action=download&file=<?php echo urlencode($file['nom']); ?>" class="btn-action download" title="Télécharger">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <?php if (strpos($file['nom'], '.sql') !== false): ?>
                                            <a href="?action=restore&file=<?php echo urlencode($file['nom']); ?>&csrf_token=<?php echo generer_token_csrf(); ?>" 
                                               class="btn-action restore" title="Restaurer" 
                                               onclick="return confirm('⚠️ Restaurer cette sauvegarde ? Toutes les données actuelles seront remplacées.')">
                                                <i class="fas fa-undo"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="?action=delete&file=<?php echo urlencode($file['nom']); ?>&csrf_token=<?php echo generer_token_csrf(); ?>" 
                                           class="btn-action delete" title="Supprimer" 
                                           onclick="return confirm('Supprimer cette sauvegarde ?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-database"></i>
                    <h3>Aucune sauvegarde</h3>
                    <p>Créez votre première sauvegarde en cliquant sur le bouton ci-dessus.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Information -->
        <div style="margin-top:20px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:15px; color:#166534;">
            <h5 style="font-weight:700; margin-bottom:8px;">
                <i class="fas fa-info-circle"></i> Bonnes pratiques
            </h5>
            <ul style="margin:0; padding-left:20px; font-size:14px;">
                <li>Créez une sauvegarde avant toute modification majeure du système.</li>
                <li>Les sauvegardes sont stockées dans <strong>/storage/backups/</strong></li>
                <li>Il est recommandé de télécharger les sauvegardes importantes sur un stockage externe.</li>
                <li>Les sauvegardes automatiques peuvent être configurées via les tâches CRON.</li>
            </ul>
        </div>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
console.log('✅ DoriExpress-Pro - Sauvegardes chargé');
</script>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER ADMIN/SAUVEGARDES/INDEX.PHP
// =============================================
?>