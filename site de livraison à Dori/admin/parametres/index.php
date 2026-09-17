<?php
/**
 * =============================================
 * PARAMÈTRES GLOBAUX - ADMIN - DoriExpress-Pro
 * =============================================
 * Fichier : admin/parametres/index.php
 * Rôle : Interface de configuration globale de la plateforme (sans code)
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
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('admin/parametres/index.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Paramètres généraux - DoriExpress-Pro';
$page_description = 'Configurez tous les paramètres de la plateforme sans toucher au code.';
$page_keywords = 'paramètres, configuration, admin, DoriExpress';

// Récupérer toutes les catégories de paramètres
$categories = [
    'entreprise' => '🏢 Entreprise',
    'design' => '🎨 Design',
    'tarifs' => '💰 Tarifs et commissions',
    'livraison' => '🚚 Livraison',
    'paiements' => '💳 Paiements',
    'securite' => '🔒 Sécurité',
    'modules' => '🧩 Modules',
    'ia' => '🤖 Intelligence Artificielle',
    'whatsapp' => '💬 WhatsApp',
    'smtp' => '📧 Email (SMTP)',
    'google_maps' => '🗺️ Google Maps',
    'pwa' => '📱 PWA',
    'maintenance' => '🔧 Maintenance',
    'seo' => '🔍 SEO'
];

// Récupérer la catégorie active
$categorie_active = isset($_GET['categorie']) ? trim($_GET['categorie']) : 'entreprise';

try {
    $db = Database::getInstance();
    
    // Récupérer tous les paramètres
    $parametres = $db->fetchAll("SELECT * FROM parametres ORDER BY categorie, cle");
    
    // Organiser par catégorie
    $parametres_par_categorie = [];
    foreach ($parametres as $p) {
        $categorie = $p['categorie'] ?? 'autre';
        if (!isset($parametres_par_categorie[$categorie])) {
            $parametres_par_categorie[$categorie] = [];
        }
        $parametres_par_categorie[$categorie][] = $p;
    }
    
} catch (Exception $e) {
    $parametres = [];
    $parametres_par_categorie = [];
    $error = 'Erreur lors du chargement des paramètres.';
}

// Traitement de la sauvegarde
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sauvegarder') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifier_token_csrf($csrf_token)) {
        $error = 'Erreur de sécurité. Veuillez rafraîchir la page.';
    } else {
        try {
            $db->beginTransaction();
            
            foreach ($_POST as $cle => $valeur) {
                if (strpos($cle, 'param_') === 0) {
                    $param_cle = substr($cle, 6);
                    $db->query(
                        "UPDATE parametres SET valeur = ?, date_modification = NOW() WHERE cle = ?",
                        [$valeur, $param_cle]
                    );
                }
            }
            
            $db->commit();
            $success = '✅ Paramètres sauvegardés avec succès !';
            
            // Recharger les paramètres
            $parametres = $db->fetchAll("SELECT * FROM parametres ORDER BY categorie, cle");
            $parametres_par_categorie = [];
            foreach ($parametres as $p) {
                $categorie = $p['categorie'] ?? 'autre';
                if (!isset($parametres_par_categorie[$categorie])) {
                    $parametres_par_categorie[$categorie] = [];
                }
                $parametres_par_categorie[$categorie][] = $p;
            }
            
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Erreur lors de la sauvegarde des paramètres.';
        }
    }
}

// Inclure le header admin
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE PARAMÈTRES
 * ============================================= */
.page-parametres {
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

/* Sidebar */
.parametres-sidebar {
    background: white;
    border-radius: 16px;
    padding: 15px;
    border: 1px solid #e5e7eb;
}

.parametres-sidebar .sidebar-title {
    font-size: 14px;
    font-weight: 700;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 8px 12px;
    border-bottom: 1px solid #f3f4f6;
    margin-bottom: 8px;
}

.parametres-sidebar .categorie-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border-radius: 10px;
    color: #4a4a4a;
    text-decoration: none;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.3s ease;
}

.parametres-sidebar .categorie-link:hover {
    background: #f3f4f6;
    color: #00A651;
}

.parametres-sidebar .categorie-link.active {
    background: rgba(0, 166, 81, 0.1);
    color: #00A651;
    font-weight: 600;
}

/* Content */
.parametres-content {
    background: white;
    border-radius: 16px;
    padding: 25px;
    border: 1px solid #e5e7eb;
}

.parametres-content .content-title {
    font-size: 20px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #00A651;
}

.param-group {
    margin-bottom: 20px;
}

.param-group .group-title {
    font-size: 16px;
    font-weight: 600;
    color: #1a1a1a;
    margin-bottom: 12px;
}

.param-group .param-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 12px;
}

.param-group .param-row.full {
    grid-template-columns: 1fr;
}

.param-group label {
    display: block;
    font-weight: 600;
    font-size: 13px;
    color: #374151;
    margin-bottom: 4px;
}

.param-group label .param-cle {
    font-weight: 400;
    color: #9ca3af;
    font-size: 11px;
}

.param-group input[type="text"],
.param-group input[type="number"],
.param-group input[type="email"],
.param-group input[type="tel"],
.param-group input[type="time"],
.param-group input[type="password"],
.param-group select,
.param-group textarea {
    width: 100%;
    padding: 10px 14px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: #f9fafb;
    color: #1a1a1a;
}

.param-group input[type="text"]:focus,
.param-group input[type="number"]:focus,
.param-group input[type="email"]:focus,
.param-group input[type="tel"]:focus,
.param-group input[type="time"]:focus,
.param-group input[type="password"]:focus,
.param-group select:focus,
.param-group textarea:focus {
    border-color: #00A651;
    background: white;
    box-shadow: 0 0 0 4px rgba(0, 166, 81, 0.1);
    outline: none;
}

.param-group input[type="color"] {
    width: 60px;
    height: 40px;
    padding: 2px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    cursor: pointer;
}

.param-group .checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
}

.param-group .checkbox-group input[type="checkbox"] {
    width: 20px;
    height: 20px;
    accent-color: #00A651;
    cursor: pointer;
}

.param-group .checkbox-group label {
    cursor: pointer;
    margin: 0;
}

.btn-save {
    padding: 12px 40px;
    background: #00A651;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-save:hover {
    background: #008a44;
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0, 166, 81, 0.3);
}

.btn-reset {
    padding: 12px 40px;
    background: transparent;
    color: #6b7280;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-reset:hover {
    border-color: #ef4444;
    color: #ef4444;
}

/* Responsive */
@media (max-width: 992px) {
    .param-group .param-row {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .admin-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .parametres-sidebar {
        margin-bottom: 20px;
    }
    .parametres-content {
        padding: 18px;
    }
    .param-group .param-row {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .admin-header h1 {
        font-size: 20px;
    }
    .parametres-content .content-title {
        font-size: 18px;
    }
    .btn-save, .btn-reset {
        width: 100%;
        text-align: center;
    }
}

/* Dark Mode */
.dark-mode .page-parametres {
    background: #121212;
}

.dark-mode .admin-header h1 {
    color: #e5e5e5;
}

.dark-mode .parametres-sidebar {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .parametres-sidebar .sidebar-title {
    color: #b0b0b0;
    border-color: #333;
}

.dark-mode .parametres-sidebar .categorie-link {
    color: #d0d0d0;
}

.dark-mode .parametres-sidebar .categorie-link:hover {
    background: #2a2a2a;
    color: #00A651;
}

.dark-mode .parametres-sidebar .categorie-link.active {
    background: rgba(0, 166, 81, 0.15);
}

.dark-mode .parametres-content {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .parametres-content .content-title {
    color: #e5e5e5;
    border-bottom-color: #00A651;
}

.dark-mode .param-group .group-title {
    color: #e5e5e5;
}

.dark-mode .param-group label {
    color: #d0d0d0;
}

.dark-mode .param-group label .param-cle {
    color: #6b7280;
}

.dark-mode .param-group input[type="text"],
.dark-mode .param-group input[type="number"],
.dark-mode .param-group input[type="email"],
.dark-mode .param-group input[type="tel"],
.dark-mode .param-group input[type="time"],
.dark-mode .param-group input[type="password"],
.dark-mode .param-group select,
.dark-mode .param-group textarea {
    background: #1a1a1a;
    border-color: #444;
    color: #e5e5e5;
}

.dark-mode .param-group input[type="text"]:focus,
.dark-mode .param-group input[type="number"]:focus,
.dark-mode .param-group input[type="email"]:focus,
.dark-mode .param-group input[type="tel"]:focus,
.dark-mode .param-group input[type="time"]:focus,
.dark-mode .param-group input[type="password"]:focus,
.dark-mode .param-group select:focus,
.dark-mode .param-group textarea:focus {
    border-color: #00A651;
    background: #2a2a2a;
}

.dark-mode .btn-reset {
    color: #b0b0b0;
    border-color: #444;
}

.dark-mode .btn-reset:hover {
    border-color: #ef4444;
    color: #ef4444;
}
</style>

<!-- ============================================= -->
<!-- PAGE PARAMÈTRES -->
<!-- ============================================= -->
<div class="page-parametres">
    <div class="container">
        
        <!-- Header -->
        <div class="admin-header">
            <h1><i class="fas fa-cog" style="color:#00A651;"></i> Paramètres généraux</h1>
            <div>
                <span class="badge bg-secondary"><?php echo count($parametres); ?> paramètres</span>
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
        
        <?php if ($success): ?>
            <div class="alert alert-success" style="margin-bottom:20px;">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3">
                <div class="parametres-sidebar">
                    <div class="sidebar-title"><i class="fas fa-list"></i> Catégories</div>
                    <?php foreach ($categories as $key => $label): ?>
                        <?php if (isset($parametres_par_categorie[$key]) && !empty($parametres_par_categorie[$key])): ?>
                            <a href="?categorie=<?php echo $key; ?>" 
                               class="categorie-link <?php echo $categorie_active == $key ? 'active' : ''; ?>">
                                <?php echo $label; ?>
                                <span style="margin-left:auto; font-size:11px; color:#9ca3af; background:#f3f4f6; padding:1px 10px; border-radius:50px;">
                                    <?php echo count($parametres_par_categorie[$key]); ?>
                                </span>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Content -->
            <div class="col-md-9">
                <div class="parametres-content">
                    <div class="content-title">
                        <?php echo $categories[$categorie_active] ?? ucfirst($categorie_active); ?>
                    </div>
                    
                    <form method="POST" action="" id="param-form">
                        <input type="hidden" name="action" value="sauvegarder">
                        <input type="hidden" name="csrf_token" value="<?php echo generer_token_csrf(); ?>">
                        
                        <?php if (isset($parametres_par_categorie[$categorie_active])): ?>
                            <?php foreach ($parametres_par_categorie[$categorie_active] as $param): ?>
                                <div class="param-group">
                                    <?php
                                    $type = $param['type'] ?? 'text';
                                    $valeur = $param['valeur'];
                                    $cle = $param['cle'];
                                    $label = ucfirst(str_replace('_', ' ', $cle));
                                    ?>
                                    
                                    <?php if ($type == 'checkbox'): ?>
                                        <div class="checkbox-group">
                                            <input type="checkbox" id="param_<?php echo $cle; ?>" 
                                                   name="param_<?php echo $cle; ?>" value="1" 
                                                   <?php echo $valeur == '1' ? 'checked' : ''; ?>>
                                            <label for="param_<?php echo $cle; ?>">
                                                <?php echo $label; ?>
                                                <span class="param-cle">(<?php echo $cle; ?>)</span>
                                            </label>
                                        </div>
                                    <?php elseif ($type == 'color'): ?>
                                        <div class="param-row">
                                            <div style="flex:1;">
                                                <label for="param_<?php echo $cle; ?>">
                                                    <?php echo $label; ?>
                                                    <span class="param-cle">(<?php echo $cle; ?>)</span>
                                                </label>
                                                <input type="color" id="param_<?php echo $cle; ?>" 
                                                       name="param_<?php echo $cle; ?>" value="<?php echo htmlspecialchars($valeur); ?>">
                                            </div>
                                        </div>
                                    <?php elseif ($type == 'textarea'): ?>
                                        <div class="param-row full">
                                            <div>
                                                <label for="param_<?php echo $cle; ?>">
                                                    <?php echo $label; ?>
                                                    <span class="param-cle">(<?php echo $cle; ?>)</span>
                                                </label>
                                                <textarea id="param_<?php echo $cle; ?>" 
                                                          name="param_<?php echo $cle; ?>" rows="3"><?php echo htmlspecialchars($valeur); ?></textarea>
                                            </div>
                                        </div>
                                    <?php elseif ($type == 'time'): ?>
                                        <div class="param-row">
                                            <div>
                                                <label for="param_<?php echo $cle; ?>">
                                                    <?php echo $label; ?>
                                                    <span class="param-cle">(<?php echo $cle; ?>)</span>
                                                </label>
                                                <input type="time" id="param_<?php echo $cle; ?>" 
                                                       name="param_<?php echo $cle; ?>" value="<?php echo htmlspecialchars($valeur); ?>">
                                            </div>
                                        </div>
                                    <?php elseif ($type == 'email'): ?>
                                        <div class="param-row">
                                            <div>
                                                <label for="param_<?php echo $cle; ?>">
                                                    <?php echo $label; ?>
                                                    <span class="param-cle">(<?php echo $cle; ?>)</span>
                                                </label>
                                                <input type="email" id="param_<?php echo $cle; ?>" 
                                                       name="param_<?php echo $cle; ?>" value="<?php echo htmlspecialchars($valeur); ?>">
                                            </div>
                                        </div>
                                    <?php elseif ($type == 'password'): ?>
                                        <div class="param-row">
                                            <div>
                                                <label for="param_<?php echo $cle; ?>">
                                                    <?php echo $label; ?>
                                                    <span class="param-cle">(<?php echo $cle; ?>)</span>
                                                </label>
                                                <input type="password" id="param_<?php echo $cle; ?>" 
                                                       name="param_<?php echo $cle; ?>" value="<?php echo htmlspecialchars($valeur); ?>">
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="param-row">
                                            <div>
                                                <label for="param_<?php echo $cle; ?>">
                                                    <?php echo $label; ?>
                                                    <span class="param-cle">(<?php echo $cle; ?>)</span>
                                                </label>
                                                <input type="<?php echo $type == 'number' ? 'number' : 'text'; ?>" 
                                                       id="param_<?php echo $cle; ?>" 
                                                       name="param_<?php echo $cle; ?>" 
                                                       value="<?php echo htmlspecialchars($valeur); ?>"
                                                       <?php echo $type == 'number' ? 'step="0.01"' : ''; ?>>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            
                            <div style="display:flex; gap:12px; margin-top:20px; flex-wrap:wrap;">
                                <button type="submit" class="btn-save">
                                    <i class="fas fa-save"></i> Sauvegarder
                                </button>
                                <button type="reset" class="btn-reset" onclick="return confirm('Réinitialiser les modifications ?')">
                                    <i class="fas fa-undo"></i> Réinitialiser
                                </button>
                            </div>
                        <?php else: ?>
                            <div style="text-align:center; padding:30px; color:#6b7280;">
                                <i class="fas fa-info-circle" style="font-size:30px; display:block; margin-bottom:10px;"></i>
                                <p>Aucun paramètre dans cette catégorie.</p>
                            </div>
                        <?php endif; ?>
                    </form>
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
// CONFIRMATION DE RÉINITIALISATION
// =============================================
document.querySelector('.btn-reset')?.addEventListener('click', function(e) {
    if (!confirm('Réinitialiser les modifications ?')) {
        e.preventDefault();
    }
});

// =============================================
// AUTO-SAVE INDICATOR
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('param-form');
    if (form) {
        // Afficher un indicateur lors de la sauvegarde
        form.addEventListener('submit', function() {
            const btn = this.querySelector('.btn-save');
            if (btn) {
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sauvegarde...';
                btn.disabled = true;
            }
        });
    }
});

console.log('✅ DoriExpress-Pro - Paramètres chargé');
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

.dark-mode .alert-success {
    background: #1a3320;
    border: 1px solid #224433;
    color: #22c55e;
}

.dark-mode .alert-danger {
    background: #331a1a;
    border: 1px solid #442222;
    color: #ef4444;
}
</style>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER ADMIN/PARAMETRES/INDEX.PHP
// =============================================
?>