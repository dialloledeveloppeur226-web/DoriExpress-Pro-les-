<?php
/**
 * =============================================
 * INSTALLATEUR - DoriExpress-Pro
 * =============================================
 * Fichier : install.php
 * Rôle : Installation automatique de la plateforme
 * Niveau : Premium
 * =============================================
 */

// =============================================
// 1. CONFIGURATION DE BASE
// =============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Définir le chemin racine
define('DOSSIER_RACINE', __DIR__ . '/');

// =============================================
// 2. VÉRIFICATION DE L'INSTALLATION
// =============================================
$installed = false;

// Vérifier si le fichier de configuration existe déjà
if (file_exists(DOSSIER_RACINE . 'includes/config.php') && file_exists(DOSSIER_RACINE . 'storage/installed.lock')) {
    $installed = true;
}

// Si déjà installé, rediriger
if ($installed && !isset($_GET['force'])) {
    header('Location: index.php');
    exit;
}

// =============================================
// 3. TRAITEMENT DU FORMULAIRE
// =============================================
$error = '';
$success = '';
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Vérification des extensions PHP
function check_php_extensions() {
    $required = ['pdo', 'pdo_mysql', 'openssl', 'curl', 'json', 'mbstring', 'gd'];
    $missing = [];
    
    foreach ($required as $ext) {
        if (!extension_loaded($ext)) {
            $missing[] = $ext;
        }
    }
    
    return $missing;
}

// Vérification des permissions
function check_permissions() {
    $dirs = ['includes/', 'storage/', 'uploads/', 'storage/logs/', 'storage/backups/'];
    $errors = [];
    
    foreach ($dirs as $dir) {
        $path = DOSSIER_RACINE . $dir;
        if (!is_writable($path)) {
            $errors[] = $dir;
        }
    }
    
    return $errors;
}

// Traitement de l'installation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'install' && $step === 2) {
        // Récupérer les données
        $db_host = trim($_POST['db_host'] ?? 'localhost');
        $db_name = trim($_POST['db_name'] ?? 'dori_express');
        $db_user = trim($_POST['db_user'] ?? 'root');
        $db_pass = $_POST['db_pass'] ?? '';
        $db_port = trim($_POST['db_port'] ?? '3306');
        
        $admin_email = trim($_POST['admin_email'] ?? '');
        $admin_password = $_POST['admin_password'] ?? '';
        $admin_password_confirm = $_POST['admin_password_confirm'] ?? '';
        $admin_nom = trim($_POST['admin_nom'] ?? 'Admin');
        $admin_prenom = trim($_POST['admin_prenom'] ?? 'DoriExpress');
        $admin_telephone = trim($_POST['admin_telephone'] ?? '61874528');
        
        $site_name = trim($_POST['site_name'] ?? 'DoriExpress-Pro');
        $site_url = trim($_POST['site_url'] ?? 'http://localhost/');
        
        $whatsapp = trim($_POST['whatsapp'] ?? '61874528');
        $telephone = trim($_POST['telephone'] ?? '61874528');
        $email = trim($_POST['email'] ?? 'contact@doriexpress.bf');
        $adresse = trim($_POST['adresse'] ?? 'Dori, Burkina Faso');
        
        // Validation
        $errors = [];
        
        if (empty($db_name)) $errors[] = 'Nom de la base de données requis';
        if (empty($db_user)) $errors[] = 'Utilisateur MySQL requis';
        if (empty($admin_email) || !filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email administrateur invalide';
        }
        if (empty($admin_password) || strlen($admin_password) < 8) {
            $errors[] = 'Mot de passe administrateur (8 caractères minimum)';
        }
        if ($admin_password !== $admin_password_confirm) {
            $errors[] = 'Les mots de passe ne correspondent pas';
        }
        if (empty($site_url)) $errors[] = 'URL du site requise';
        
        if (empty($errors)) {
            try {
                // Tester la connexion à la base de données
                $dsn = "mysql:host=$db_host;port=$db_port";
                $pdo = new PDO($dsn, $db_user, $db_pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Créer la base de données si elle n'existe pas
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `$db_name`");
                
                // Importer le fichier SQL
                $sql_file = DOSSIER_RACINE . 'database/dori_express.sql';
                if (!file_exists($sql_file)) {
                    throw new Exception('Fichier SQL introuvable: ' . $sql_file);
                }
                
                $sql = file_get_contents($sql_file);
                
                // Nettoyer les commentaires
                $sql = preg_replace('/^--.*$/m', '', $sql);
                $sql = preg_replace('/^#.*$/m', '', $sql);
                $sql = preg_replace('/^\/\*.*?\*\//s', '', $sql);
                
                // Séparer les requêtes
                $queries = preg_split('/;\s*$/m', $sql);
                
                foreach ($queries as $query) {
                    $query = trim($query);
                    if (!empty($query)) {
                        $pdo->exec($query);
                    }
                }
                
                // Mettre à jour le mot de passe admin
                $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
                $pdo->exec("
                    UPDATE utilisateurs 
                    SET nom = '$admin_nom', 
                        prenom = '$admin_prenom', 
                        email = '$admin_email', 
                        telephone = '$admin_telephone',
                        mot_de_passe_hash = '$password_hash' 
                    WHERE role = 'createur'
                ");
                
                // Mettre à jour les paramètres
                $pdo->exec("
                    UPDATE parametres 
                    SET valeur = '$site_name' 
                    WHERE cle = 'nom_site'
                ");
                
                $pdo->exec("
                    UPDATE parametres 
                    SET valeur = '$site_url' 
                    WHERE cle = 'url_base'
                ");
                
                $pdo->exec("
                    UPDATE parametres 
                    SET valeur = '$whatsapp' 
                    WHERE cle = 'whatsapp'
                ");
                
                $pdo->exec("
                    UPDATE parametres 
                    SET valeur = '$telephone' 
                    WHERE cle = 'telephone'
                ");
                
                $pdo->exec("
                    UPDATE parametres 
                    SET valeur = '$email' 
                    WHERE cle = 'email'
                ");
                
                $pdo->exec("
                    UPDATE parametres 
                    SET valeur = '$adresse' 
                    WHERE cle = 'adresse'
                );
                
                // Créer le fichier de configuration
                $config_content = "<?php\n";
                $config_content .= "/**\n";
                $config_content .= " * =============================================\n";
                $config_content .= " * CONFIGURATION - DoriExpress-Pro\n";
                $config_content .= " * =============================================\n";
                $config_content .= " * Fichier généré automatiquement par l'installateur\n";
                $config_content .= " * Date : " . date('Y-m-d H:i:s') . "\n";
                $config_content .= " * =============================================\n";
                $config_content .= " */\n\n";
                
                $config_content .= "define('ENVIRONNEMENT', 'production');\n\n";
                
                $config_content .= "define('URL_BASE', '$site_url');\n\n";
                
                $config_content .= "define('DOSSIER_RACINE', __DIR__ . '/');\n";
                $config_content .= "define('DOSSIER_INCLUDES', DOSSIER_RACINE . 'includes/');\n";
                $config_content .= "define('DOSSIER_ADMIN', DOSSIER_RACINE . 'admin/');\n";
                $config_content .= "define('DOSSIER_CLIENT', DOSSIER_RACINE . 'client/');\n";
                $config_content .= "define('DOSSIER_LIVREUR', DOSSIER_RACINE . 'livreur/');\n";
                $config_content .= "define('DOSSIER_PARTENAIRE', DOSSIER_RACINE . 'partenaire/');\n";
                $config_content .= "define('DOSSIER_ASSETS', DOSSIER_RACINE . 'assets/');\n";
                $config_content .= "define('DOSSIER_UPLOADS', DOSSIER_RACINE . 'uploads/');\n";
                $config_content .= "define('DOSSIER_MODULES', DOSSIER_RACINE . 'modules/');\n";
                $config_content .= "define('DOSSIER_API', DOSSIER_RACINE . 'api/');\n";
                $config_content .= "define('DOSSIER_PWA', DOSSIER_RACINE . 'pwa/');\n\n";
                
                $config_content .= "define('DB_HOST', '$db_host');\n";
                $config_content .= "define('DB_PORT', '$db_port');\n";
                $config_content .= "define('DB_NAME', '$db_name');\n";
                $config_content .= "define('DB_USER', '$db_user');\n";
                $config_content .= "define('DB_PASS', '$db_pass');\n";
                $config_content .= "define('DB_CHARSET', 'utf8mb4');\n\n";
                
                $config_content .= "define('DB_OPTIONS', [\n";
                $config_content .= "    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n";
                $config_content .= "    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n";
                $config_content .= "    PDO::ATTR_EMULATE_PREPARES => false,\n";
                $config_content .= "    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci'\n";
                $config_content .= "]);\n\n";
                
                $config_content .= "define('SESSION_DUREE', 86400);\n";
                $config_content .= "define('SESSION_NOM', 'DORIEXPRESS_SESSION');\n";
                $config_content .= "define('SESSION_SECURISE', true);\n\n";
                
                $config_content .= "define('SALT_GLOBAL', '" . bin2hex(random_bytes(32)) . "');\n";
                $config_content .= "define('CLE_CHIFFREMENT', '" . bin2hex(random_bytes(32)) . "');\n\n";
                
                $config_content .= "define('CSRF_DUREE', 3600);\n";
                $config_content .= "define('TENTATIVES_CONNEXION_MAX', 5);\n";
                $config_content .= "define('BLOCAGE_DUREE_MINUTES', 15);\n\n";
                
                $config_content .= "define('UPLOAD_MAX_SIZE', 5242880);\n";
                $config_content .= "define('UPLOAD_MAX_SIZE_IMAGE', 5242880);\n";
                $config_content .= "define('UPLOAD_MAX_SIZE_DOCUMENT', 10485760);\n\n";
                
                $config_content .= "define('UPLOAD_TYPES_IMAGE', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);\n";
                $config_content .= "define('UPLOAD_TYPES_DOCUMENT', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt']);\n";
                $config_content .= "define('UPLOAD_TYPES_ALL', array_merge(UPLOAD_TYPES_IMAGE, UPLOAD_TYPES_DOCUMENT));\n\n";
                
                $config_content .= "define('UPLOAD_PROFILS', DOSSIER_UPLOADS . 'profils/');\n";
                $config_content .= "define('UPLOAD_PRODUITS', DOSSIER_UPLOADS . 'produits/');\n";
                $config_content .= "define('UPLOAD_COMMANDES', DOSSIER_UPLOADS . 'commandes/');\n";
                $config_content .= "define('UPLOAD_DOCUMENTS', DOSSIER_UPLOADS . 'documents/');\n";
                $config_content .= "define('UPLOAD_PARTENAIRES', DOSSIER_UPLOADS . 'partenaires/');\n";
                $config_content .= "define('UPLOAD_ARTICLES', DOSSIER_UPLOADS . 'articles/');\n\n";
                
                $config_content .= "define('GOOGLE_MAPS_API_KEY', 'VOTRE_CLE_GOOGLE_MAPS');\n\n";
                
                $config_content .= "define('ORANGE_MONEY_API_URL', 'https://api.orange.com/orange-money-webpay/');\n";
                $config_content .= "define('ORANGE_MONEY_API_KEY', 'VOTRE_CLE_ORANGE_MONEY');\n";
                $config_content .= "define('ORANGE_MONEY_MERCHANT_ID', 'VOTRE_MERCHANT_ID');\n\n";
                
                $config_content .= "define('MOOV_MONEY_API_URL', 'https://api.moov.africa/moov-money/');\n";
                $config_content .= "define('MOOV_MONEY_API_KEY', 'VOTRE_CLE_MOOV_MONEY');\n";
                $config_content .= "define('MOOV_MONEY_MERCHANT_ID', 'VOTRE_MERCHANT_ID_MOOV');\n\n";
                
                $config_content .= "define('WHATSAPP_NUMBER', '$whatsapp');\n";
                $config_content .= "define('WHATSAPP_API_URL', 'https://graph.facebook.com/v17.0/');\n";
                $config_content .= "define('WHATSAPP_API_TOKEN', 'VOTRE_TOKEN_WHATSAPP');\n\n";
                
                $config_content .= "define('SMTP_HOST', 'smtp.gmail.com');\n";
                $config_content .= "define('SMTP_PORT', 587);\n";
                $config_content .= "define('SMTP_USERNAME', '');\n";
                $config_content .= "define('SMTP_PASSWORD', '');\n";
                $config_content .= "define('SMTP_SECURE', 'tls');\n";
                $config_content .= "define('SMTP_FROM_EMAIL', '$email');\n";
                $config_content .= "define('SMTP_FROM_NAME', '$site_name');\n\n";
                
                $config_content .= "define('VERSION_SYSTEME', '1.0.0');\n";
                $config_content .= "define('VERSION_DATE', '" . date('Y-m-d') . "');\n";
                $config_content .= "define('VERSION_NOM', '$site_name');\n\n";
                
                $config_content .= "// =============================================\n";
                $config_content .= "// FIN DU FICHIER CONFIG.PHP\n";
                $config_content .= "// =============================================\n";
                $config_content .= "?>";
                
                // Écrire le fichier de configuration
                file_put_contents(DOSSIER_RACINE . 'includes/config.php', $config_content);
                
                // Créer le fichier de verrouillage
                file_put_contents(DOSSIER_RACINE . 'storage/installed.lock', date('Y-m-d H:i:s'));
                
                // Créer les dossiers d'upload
                $upload_dirs = [
                    'uploads/profils/',
                    'uploads/produits/',
                    'uploads/commandes/',
                    'uploads/documents/',
                    'uploads/partenaires/',
                    'uploads/articles/',
                    'storage/logs/',
                    'storage/backups/'
                ];
                
                foreach ($upload_dirs as $dir) {
                    $path = DOSSIER_RACINE . $dir;
                    if (!is_dir($path)) {
                        mkdir($path, 0755, true);
                    }
                }
                
                $success = '✅ Installation terminée avec succès !';
                $step = 3;
                
            } catch (Exception $e) {
                $error = '❌ Erreur lors de l\'installation : ' . $e->getMessage();
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}

// Vérifications système
$php_version_ok = version_compare(PHP_VERSION, '8.0.0', '>=');
$missing_extensions = check_php_extensions();
$permission_errors = check_permissions();
$mysql_available = extension_loaded('pdo_mysql');

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE D'INSTALLATION
 * ============================================= */
.page-install {
    background: #f8fafc;
    min-height: 100vh;
    padding: 40px 0;
}

.install-container {
    max-width: 800px;
    margin: 0 auto;
    background: white;
    border-radius: 16px;
    padding: 40px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}

.install-header {
    text-align: center;
    margin-bottom: 30px;
}

.install-header .logo {
    font-size: 48px;
    color: #00A651;
    margin-bottom: 10px;
}

.install-header h1 {
    font-size: 28px;
    font-weight: 800;
    color: #1a1a1a;
}

.install-header p {
    color: #6b7280;
    font-size: 16px;
}

/* Steps */
.install-steps {
    display: flex;
    justify-content: space-between;
    margin-bottom: 30px;
    position: relative;
}

.install-steps::before {
    content: '';
    position: absolute;
    top: 20px;
    left: 10%;
    right: 10%;
    height: 2px;
    background: #e5e7eb;
    z-index: 0;
}

.install-steps .step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    z-index: 1;
    flex: 1;
}

.install-steps .step .number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 16px;
    background: #e5e7eb;
    color: #6b7280;
    transition: all 0.3s ease;
}

.install-steps .step.active .number {
    background: #00A651;
    color: white;
}

.install-steps .step.done .number {
    background: #22c55e;
    color: white;
}

.install-steps .step .label {
    font-size: 12px;
    color: #6b7280;
    font-weight: 600;
}

.install-steps .step.active .label {
    color: #00A651;
}

/* Form */
.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: block;
    font-weight: 600;
    font-size: 14px;
    color: #374151;
    margin-bottom: 4px;
}

.form-group label .required {
    color: #ef4444;
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

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.form-hint {
    font-size: 12px;
    color: #6b7280;
    margin-top: 4px;
}

/* Checks */
.check-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid #f3f4f6;
}

.check-item:last-child {
    border-bottom: none;
}

.check-item .status {
    font-size: 18px;
}

.check-item .status.ok { color: #22c55e; }
.check-item .status.error { color: #ef4444; }
.check-item .status.warning { color: #f59e0b; }

.check-item .label {
    flex: 1;
    font-size: 14px;
    color: #1a1a1a;
}

.check-item .value {
    font-size: 13px;
    color: #6b7280;
}

.btn-install {
    width: 100%;
    padding: 14px;
    background: #00A651;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-install:hover {
    background: #008a44;
    transform: translateY(-2px);
}

.btn-install:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-secondary {
    padding: 10px 25px;
    background: transparent;
    color: #6b7280;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-secondary:hover {
    border-color: #00A651;
    color: #00A651;
}

/* Responsive */
@media (max-width: 768px) {
    .install-container {
        padding: 20px;
        margin: 0 10px;
    }
    .form-row {
        grid-template-columns: 1fr;
    }
    .install-steps .step .label {
        font-size: 10px;
    }
    .install-steps .step .number {
        width: 32px;
        height: 32px;
        font-size: 14px;
    }
}

@media (max-width: 480px) {
    .install-header h1 {
        font-size: 22px;
    }
    .install-steps {
        flex-wrap: wrap;
        gap: 10px;
    }
    .install-steps .step {
        flex: 0 0 45%;
    }
}

/* Dark Mode */
.dark-mode .page-install {
    background: #121212;
}

.dark-mode .install-container {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .install-header h1 {
    color: #e5e5e5;
}

.dark-mode .install-header p {
    color: #b0b0b0;
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

.dark-mode .check-item {
    border-color: #333;
}

.dark-mode .check-item .label {
    color: #e5e5e5;
}

.dark-mode .check-item .value {
    color: #b0b0b0;
}

.dark-mode .btn-secondary {
    color: #b0b0b0;
    border-color: #444;
}

.dark-mode .btn-secondary:hover {
    border-color: #00A651;
    color: #00A651;
}
</style>

<!-- ============================================= -->
<!-- PAGE D'INSTALLATION -->
<!-- ============================================= -->
<div class="page-install">
    <div class="container">
        <div class="install-container">
            
            <!-- Header -->
            <div class="install-header">
                <div class="logo">📦</div>
                <h1>DoriExpress-Pro</h1>
                <p>Installation de la plateforme de livraison</p>
            </div>
            
            <!-- Steps -->
            <div class="install-steps">
                <div class="step <?php echo $step >= 1 ? ($step > 1 ? 'done' : 'active') : ''; ?>">
                    <span class="number">1</span>
                    <span class="label">Vérification</span>
                </div>
                <div class="step <?php echo $step >= 2 ? ($step > 2 ? 'done' : 'active') : ''; ?>">
                    <span class="number">2</span>
                    <span class="label">Configuration</span>
                </div>
                <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">
                    <span class="number">3</span>
                    <span class="label">Finalisation</span>
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
            
            <!-- Step 1: Vérification -->
            <?php if ($step === 1): ?>
                
                <h3 style="font-weight:700; color:#1a1a1a; margin-bottom:15px;">
                    <i class="fas fa-check-circle" style="color:#00A651;"></i> Vérification du système
                </h3>
                
                <div style="margin-bottom:20px;">
                    <div class="check-item">
                        <span class="status <?php echo $php_version_ok ? 'ok' : 'error'; ?>">
                            <i class="fas <?php echo $php_version_ok ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                        </span>
                        <span class="label">PHP 8.0+</span>
                        <span class="value"><?php echo PHP_VERSION; ?></span>
                    </div>
                    
                    <div class="check-item">
                        <span class="status <?php echo $mysql_available ? 'ok' : 'error'; ?>">
                            <i class="fas <?php echo $mysql_available ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                        </span>
                        <span class="label">MySQL / PDO</span>
                        <span class="value"><?php echo $mysql_available ? '✅ Disponible' : '❌ Non disponible'; ?></span>
                    </div>
                    
                    <div class="check-item">
                        <span class="status <?php echo empty($missing_extensions) ? 'ok' : 'error'; ?>">
                            <i class="fas <?php echo empty($missing_extensions) ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                        </span>
                        <span class="label">Extensions PHP requises</span>
                        <span class="value">
                            <?php if (empty($missing_extensions)): ?>
                                ✅ Toutes disponibles
                            <?php else: ?>
                                ❌ Manquantes: <?php echo implode(', ', $missing_extensions); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <div class="check-item">
                        <span class="status <?php echo empty($permission_errors) ? 'ok' : 'error'; ?>">
                            <i class="fas <?php echo empty($permission_errors) ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                        </span>
                        <span class="label">Permissions des dossiers</span>
                        <span class="value">
                            <?php if (empty($permission_errors)): ?>
                                ✅ Tous accessibles
                            <?php else: ?>
                                ❌ Dossiers: <?php echo implode(', ', $permission_errors); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <div class="check-item">
                        <span class="status <?php echo !file_exists(DOSSIER_RACINE . 'includes/config.php') ? 'ok' : 'warning'; ?>">
                            <i class="fas <?php echo !file_exists(DOSSIER_RACINE . 'includes/config.php') ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
                        </span>
                        <span class="label">Fichier de configuration</span>
                        <span class="value">
                            <?php if (!file_exists(DOSSIER_RACINE . 'includes/config.php')): ?>
                                ✅ Prêt à être créé
                            <?php else: ?>
                                ⚠️ Existe déjà (sera écrasé)
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($php_version_ok && empty($missing_extensions) && empty($permission_errors)): ?>
                    <form method="GET" action="">
                        <input type="hidden" name="step" value="2">
                        <button type="submit" class="btn-install">
                            <i class="fas fa-arrow-right"></i> Continuer
                        </button>
                    </form>
                <?php else: ?>
                    <div style="text-align:center; padding:20px; background:#fef2f2; border-radius:10px; border:1px solid #fecaca; color:#991b1b;">
                        <i class="fas fa-exclamation-triangle" style="font-size:24px; display:block; margin-bottom:10px;"></i>
                        <p>Veuillez corriger les erreurs ci-dessus avant de continuer.</p>
                    </div>
                <?php endif; ?>
                
            <?php endif; ?>
            
            <!-- Step 2: Configuration -->
            <?php if ($step === 2): ?>
                
                <h3 style="font-weight:700; color:#1a1a1a; margin-bottom:15px;">
                    <i class="fas fa-cog" style="color:#00A651;"></i> Configuration
                </h3>
                
                <form method="POST" action="?step=2">
                    <input type="hidden" name="action" value="install">
                    
                    <h4 style="font-weight:600; color:#1a1a1a; margin:15px 0 10px; font-size:16px; border-bottom:2px solid #00A651; padding-bottom:5px;">
                        🗄️ Base de données
                    </h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="db_host">Hôte <span class="required">*</span></label>
                            <input type="text" class="form-control" id="db_host" name="db_host" value="localhost" required>
                        </div>
                        <div class="form-group">
                            <label for="db_port">Port</label>
                            <input type="text" class="form-control" id="db_port" name="db_port" value="3306">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="db_name">Nom de la base <span class="required">*</span></label>
                            <input type="text" class="form-control" id="db_name" name="db_name" value="dori_express" required>
                        </div>
                        <div class="form-group">
                            <label for="db_user">Utilisateur <span class="required">*</span></label>
                            <input type="text" class="form-control" id="db_user" name="db_user" value="root" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_pass">Mot de passe</label>
                        <input type="password" class="form-control" id="db_pass" name="db_pass">
                    </div>
                    
                    <hr style="border-color:#e5e7eb; margin:20px 0;">
                    
                    <h4 style="font-weight:600; color:#1a1a1a; margin:15px 0 10px; font-size:16px; border-bottom:2px solid #00A651; padding-bottom:5px;">
                        👑 Compte administrateur
                    </h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="admin_nom">Nom <span class="required">*</span></label>
                            <input type="text" class="form-control" id="admin_nom" name="admin_nom" value="Admin" required>
                        </div>
                        <div class="form-group">
                            <label for="admin_prenom">Prénom <span class="required">*</span></label>
                            <input type="text" class="form-control" id="admin_prenom" name="admin_prenom" value="DoriExpress" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_email">Email <span class="required">*</span></label>
                        <input type="email" class="form-control" id="admin_email" name="admin_email" value="admin@doriexpress.bf" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_telephone">Téléphone</label>
                        <input type="tel" class="form-control" id="admin_telephone" name="admin_telephone" value="61874528">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="admin_password">Mot de passe <span class="required">*</span></label>
                            <input type="password" class="form-control" id="admin_password" name="admin_password" required>
                            <div class="form-hint">8 caractères minimum</div>
                        </div>
                        <div class="form-group">
                            <label for="admin_password_confirm">Confirmer <span class="required">*</span></label>
                            <input type="password" class="form-control" id="admin_password_confirm" name="admin_password_confirm" required>
                        </div>
                    </div>
                    
                    <hr style="border-color:#e5e7eb; margin:20px 0;">
                    
                    <h4 style="font-weight:600; color:#1a1a1a; margin:15px 0 10px; font-size:16px; border-bottom:2px solid #00A651; padding-bottom:5px;">
                        🌐 Informations du site
                    </h4>
                    
                    <div class="form-group">
                        <label for="site_name">Nom du site <span class="required">*</span></label>
                        <input type="text" class="form-control" id="site_name" name="site_name" value="DoriExpress-Pro" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="site_url">URL du site <span class="required">*</span></label>
                        <input type="text" class="form-control" id="site_url" name="site_url" value="http://localhost/" required>
                        <div class="form-hint">Ex: https://www.doriexpress.bf/</div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="whatsapp">WhatsApp</label>
                            <input type="tel" class="form-control" id="whatsapp" name="whatsapp" value="61874528">
                        </div>
                        <div class="form-group">
                            <label for="telephone">Téléphone</label>
                            <input type="tel" class="form-control" id="telephone" name="telephone" value="61874528">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email du site</label>
                        <input type="email" class="form-control" id="email" name="email" value="contact@doriexpress.bf">
                    </div>
                    
                    <div class="form-group">
                        <label for="adresse">Adresse</label>
                        <input type="text" class="form-control" id="adresse" name="adresse" value="Dori, Burkina Faso">
                    </div>
                    
                    <button type="submit" class="btn-install" style="margin-top:20px;">
                        <i class="fas fa-rocket"></i> Installer DoriExpress-Pro
                    </button>
                </form>
                
            <?php endif; ?>
            
            <!-- Step 3: Finalisation -->
            <?php if ($step === 3): ?>
                
                <div style="text-align:center; padding:20px 0;">
                    <div style="font-size:80px; color:#22c55e; margin-bottom:20px;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    
                    <h2 style="font-weight:800; color:#1a1a1a; margin-bottom:10px;">
                        Installation réussie ! 🎉
                    </h2>
                    
                    <p style="color:#6b7280; font-size:16px; max-width:500px; margin:0 auto 20px;">
                        DoriExpress-Pro est maintenant installé et prêt à être utilisé.
                    </p>
                    
                    <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:15px; margin-bottom:20px; text-align:left;">
                        <h4 style="font-weight:700; color:#166534; margin-bottom:8px;">
                            <i class="fas fa-key"></i> Identifiants administrateur
                        </h4>
                        <p style="margin:0; color:#166534;">
                            <strong>Email :</strong> <?php echo htmlspecialchars($admin_email ?? 'admin@doriexpress.bf'); ?><br>
                            <strong>Mot de passe :</strong> <?php echo htmlspecialchars($admin_password ?? 'admin123'); ?>
                        </p>
                        <p style="font-size:13px; color:#166534; margin-top:5px;">
                            <i class="fas fa-info-circle"></i> Changez votre mot de passe dès la première connexion.
                        </p>
                    </div>
                    
                    <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
                        <a href="index.php" class="btn-install" style="width:auto; padding:12px 35px; text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
                            <i class="fas fa-home"></i> Accéder au site
                        </a>
                        <a href="admin/createur.php" class="btn-secondary" style="display:inline-flex; align-items:center; gap:8px; text-decoration:none;">
                            <i class="fas fa-crown"></i> Dashboard Créateur
                        </a>
                    </div>
                </div>
                
            <?php endif; ?>
            
        </div>
    </div>
</div>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER INSTALL.PHP
// =============================================
?>