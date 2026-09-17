<?php
/**
 * =============================================
 * PAGE PROFIL UTILISATEUR - DoriExpress-Pro
 * =============================================
 * Fichier : profil.php
 * Rôle : Gestion du profil utilisateur (informations, photo, mot de passe)
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
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('profil.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Mon profil - DoriExpress-Pro';
$page_description = 'Gérez vos informations personnelles sur DoriExpress-Pro.';
$page_keywords = 'profil, compte, DoriExpress, utilisateur';

// Récupérer les informations de l'utilisateur
$user = utilisateur_connecte();
$user_id = $user['id'];

try {
    $db = Database::getInstance();
    
    // Récupérer les informations complètes
    $user_info = $db->fetchOne(
        "SELECT * FROM utilisateurs WHERE id = ?",
        [$user_id]
    );
    
    // Récupérer le profil client si existe
    $client_info = $db->fetchOne(
        "SELECT * FROM clients WHERE utilisateur_id = ?",
        [$user_id]
    );
    
    // Récupérer le profil livreur si existe
    $livreur_info = $db->fetchOne(
        "SELECT * FROM livreurs WHERE utilisateur_id = ?",
        [$user_id]
    );
    
    // Récupérer le profil partenaire si existe
    $partenaire_info = $db->fetchOne(
        "SELECT * FROM partenaires WHERE utilisateur_id = ?",
        [$user_id]
    );
    
    // Récupérer les statistiques
    $total_commandes = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM commandes WHERE client_id = ?",
        [$user_id]
    );
    
    $total_depense = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(prix_total), 0) FROM commandes WHERE client_id = ? AND statut = 'livree'",
        [$user_id]
    );
    
    $solde_wallet = get_solde_wallet($user_id);
    
} catch (Exception $e) {
    $user_info = $user;
    $client_info = null;
    $livreur_info = null;
    $partenaire_info = null;
    $total_commandes = 0;
    $total_depense = 0;
    $solde_wallet = 0;
}

// Traitement des formulaires
$error = '';
$success = '';

// Mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifier_token_csrf($csrf_token)) {
        $error = 'Erreur de sécurité. Veuillez rafraîchir la page.';
    } else {
        
        // Action: Mettre à jour le profil
        if ($_POST['action'] === 'update_profil') {
            $nom = trim($_POST['nom'] ?? '');
            $prenom = trim($_POST['prenom'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $telephone = trim($_POST['telephone'] ?? '');
            
            $errors = [];
            
            if (empty($nom)) $errors[] = 'Veuillez saisir votre nom.';
            if (empty($prenom)) $errors[] = 'Veuillez saisir votre prénom.';
            if (empty($email) || !valider_email($email)) $errors[] = 'Veuillez saisir une adresse email valide.';
            if (empty($telephone) || !valider_telephone($telephone)) $errors[] = 'Veuillez saisir un numéro de téléphone valide.';
            
            if (empty($errors)) {
                try {
                    $db->query(
                        "UPDATE utilisateurs SET nom = ?, prenom = ?, email = ?, telephone = ? WHERE id = ?",
                        [$nom, $prenom, $email, $telephone, $user_id]
                    );
                    
                    // Mettre à jour la session
                    $_SESSION['user_nom'] = $nom . ' ' . $prenom;
                    $_SESSION['user_email'] = $email;
                    
                    $success = '✅ Profil mis à jour avec succès !';
                    
                    // Recharger les données
                    $user_info = $db->fetchOne("SELECT * FROM utilisateurs WHERE id = ?", [$user_id]);
                    
                } catch (Exception $e) {
                    $error = 'Erreur lors de la mise à jour du profil.';
                }
            } else {
                $error = implode('<br>', $errors);
            }
        }
        
        // Action: Changer le mot de passe
        if ($_POST['action'] === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            $errors = [];
            
            if (empty($current_password)) $errors[] = 'Veuillez saisir votre mot de passe actuel.';
            if (empty($new_password)) $errors[] = 'Veuillez saisir un nouveau mot de passe.';
            if ($new_password !== $confirm_password) $errors[] = 'Les mots de passe ne correspondent pas.';
            
            if (empty($errors)) {
                // Vérifier le mot de passe actuel
                if (!password_verify($current_password, $user_info['mot_de_passe_hash'])) {
                    $errors[] = 'Mot de passe actuel incorrect.';
                }
            }
            
            if (empty($errors)) {
                // Vérifier la force du nouveau mot de passe
                $check = check_password_strength($new_password);
                if ($check['score'] < 3) {
                    $errors[] = 'Mot de passe trop faible. ' . implode(' ', $check['errors']);
                }
            }
            
            if (empty($errors)) {
                try {
                    $new_hash = hash_mot_de_passe($new_password);
                    $db->query(
                        "UPDATE utilisateurs SET mot_de_passe_hash = ? WHERE id = ?",
                        [$new_hash, $user_id]
                    );
                    
                    journaliser($user_id, 'changement_mot_de_passe', 'profil');
                    
                    $success = '✅ Mot de passe changé avec succès !';
                    
                } catch (Exception $e) {
                    $error = 'Erreur lors du changement de mot de passe.';
                }
            } else {
                $error = implode('<br>', $errors);
            }
        }
        
        // Action: Upload photo
        if ($_POST['action'] === 'upload_photo' && isset($_FILES['photo'])) {
            $file = $_FILES['photo'];
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'Erreur lors du téléchargement de la photo.';
            } else {
                $result = upload_fichier($file, UPLOAD_PROFILS, UPLOAD_TYPES_IMAGE, UPLOAD_MAX_SIZE_IMAGE);
                
                if ($result['success']) {
                    // Supprimer l'ancienne photo si ce n'est pas la photo par défaut
                    if ($user_info['photo'] && $user_info['photo'] !== 'default.jpg') {
                        $old_path = UPLOAD_PROFILS . $user_info['photo'];
                        if (file_exists($old_path)) {
                            unlink($old_path);
                        }
                    }
                    
                    $db->query(
                        "UPDATE utilisateurs SET photo = ? WHERE id = ?",
                        [$result['nom'], $user_id]
                    );
                    
                    $_SESSION['user_photo'] = $result['nom'];
                    $success = '✅ Photo de profil mise à jour !';
                    
                    // Recharger les données
                    $user_info = $db->fetchOne("SELECT * FROM utilisateurs WHERE id = ?", [$user_id]);
                    
                } else {
                    $error = $result['message'];
                }
            }
        }
    }
}

// Générer le token CSRF
$csrf_token = generer_token_csrf();

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE PROFIL
 * ============================================= */
.page-profil {
    background: #f8fafc;
    min-height: 100vh;
    padding: 30px 0 60px;
}

.profil-header {
    display: flex;
    align-items: center;
    gap: 25px;
    background: white;
    border-radius: 16px;
    padding: 30px;
    border: 1px solid #e5e7eb;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.profil-header .profil-avatar {
    position: relative;
    width: 100px;
    height: 100px;
    flex-shrink: 0;
}

.profil-header .profil-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #00A651;
}

.profil-header .profil-avatar .edit-photo {
    position: absolute;
    bottom: 0;
    right: 0;
    background: #00A651;
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid white;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s ease;
}

.profil-header .profil-avatar .edit-photo:hover {
    transform: scale(1.1);
}

.profil-header .profil-info h1 {
    font-size: 24px;
    font-weight: 800;
    color: #1a1a1a;
    margin: 0;
}

.profil-header .profil-info .profil-role {
    font-size: 14px;
    color: #6b7280;
}

.profil-header .profil-stats {
    display: flex;
    gap: 30px;
    margin-left: auto;
    flex-wrap: wrap;
}

.profil-header .profil-stats .stat {
    text-align: center;
}

.profil-header .profil-stats .stat .number {
    font-size: 22px;
    font-weight: 800;
    color: #00A651;
    display: block;
}

.profil-header .profil-stats .stat .label {
    font-size: 13px;
    color: #6b7280;
}

/* Tabs */
.profil-tabs {
    display: flex;
    gap: 5px;
    background: white;
    border-radius: 12px;
    padding: 5px;
    border: 1px solid #e5e7eb;
    margin-bottom: 25px;
    overflow-x: auto;
}

.profil-tabs .tab-btn {
    padding: 10px 22px;
    border-radius: 10px;
    border: none;
    background: transparent;
    font-weight: 600;
    font-size: 14px;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.profil-tabs .tab-btn:hover {
    background: #f3f4f6;
    color: #1a1a1a;
}

.profil-tabs .tab-btn.active {
    background: #00A651;
    color: white;
}

/* Card */
.profil-card {
    background: white;
    border-radius: 16px;
    padding: 25px 30px;
    border: 1px solid #e5e7eb;
    display: none;
}

.profil-card.active {
    display: block;
}

.profil-card .card-title {
    font-size: 20px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: block;
    font-weight: 600;
    font-size: 14px;
    color: #374151;
    margin-bottom: 6px;
}

.form-group label .required {
    color: #ef4444;
}

.form-group .input-wrapper {
    position: relative;
}

.form-group .input-wrapper i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
}

.form-group .input-wrapper input {
    width: 100%;
    padding: 12px 14px 12px 45px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 15px;
    transition: all 0.3s ease;
    background: #f9fafb;
    color: #1a1a1a;
}

.form-group .input-wrapper input:focus {
    border-color: #00A651;
    background: white;
    box-shadow: 0 0 0 4px rgba(0, 166, 81, 0.1);
    outline: none;
}

.form-group .input-wrapper input.error {
    border-color: #ef4444;
}

.form-group .help-text {
    font-size: 13px;
    color: #6b7280;
    margin-top: 4px;
}

.btn-submit {
    padding: 12px 35px;
    background: #00A651;
    color: white;
    border: none;
    border-radius: 12px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-submit:hover {
    background: #008a44;
    transform: translateY(-2px);
}

.btn-submit-outline {
    padding: 12px 35px;
    background: transparent;
    color: #6b7280;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-submit-outline:hover {
    border-color: #00A651;
    color: #00A651;
}

.btn-danger {
    padding: 12px 35px;
    background: #ef4444;
    color: white;
    border: none;
    border-radius: 12px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-danger:hover {
    background: #dc2626;
    transform: translateY(-2px);
}

/* Photo upload form */
.photo-upload-form {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-top: 5px;
}

.photo-upload-form input[type="file"] {
    padding: 10px;
    border: 2px dashed #e5e7eb;
    border-radius: 12px;
    flex: 1;
}

.photo-upload-form button {
    padding: 10px 25px;
}

/* Responsive */
@media (max-width: 768px) {
    .profil-header {
        flex-direction: column;
        text-align: center;
        padding: 20px;
    }
    .profil-header .profil-stats {
        margin-left: 0;
        justify-content: center;
        width: 100%;
    }
    .profil-card {
        padding: 20px;
    }
    .profil-tabs {
        overflow-x: auto;
        flex-wrap: nowrap;
    }
    .profil-tabs .tab-btn {
        padding: 8px 16px;
        font-size: 13px;
    }
    .photo-upload-form {
        flex-direction: column;
        align-items: stretch;
    }
}

@media (max-width: 480px) {
    .profil-header .profil-avatar {
        width: 80px;
        height: 80px;
    }
    .profil-header .profil-info h1 {
        font-size: 20px;
    }
    .profil-header .profil-stats .stat .number {
        font-size: 18px;
    }
}

/* Dark Mode */
.dark-mode .page-profil {
    background: #121212;
}

.dark-mode .profil-header,
.dark-mode .profil-card,
.dark-mode .profil-tabs {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .profil-header .profil-info h1 {
    color: #e5e5e5;
}

.dark-mode .profil-header .profil-stats .stat .label {
    color: #a0a0a0;
}

.dark-mode .form-group label {
    color: #d0d0d0;
}

.dark-mode .form-group .input-wrapper input {
    background: #1a1a1a;
    border-color: #444;
    color: #e5e5e5;
}

.dark-mode .form-group .input-wrapper input:focus {
    border-color: #00A651;
    background: #2a2a2a;
}

.dark-mode .profil-tabs .tab-btn {
    color: #b0b0b0;
}

.dark-mode .profil-tabs .tab-btn:hover {
    background: #2a2a2a;
    color: #e5e5e5;
}

.dark-mode .btn-submit-outline {
    color: #b0b0b0;
    border-color: #444;
}

.dark-mode .btn-submit-outline:hover {
    border-color: #00A651;
    color: #00A651;
}

.dark-mode .photo-upload-form input[type="file"] {
    border-color: #444;
    color: #b0b0b0;
}
</style>

<!-- ============================================= -->
<!-- PAGE PROFIL -->
<!-- ============================================= -->
<div class="page-profil">
    <div class="container">
        
        <!-- Header -->
        <div class="profil-header">
            <div class="profil-avatar">
                <img src="<?php echo URL_BASE . 'uploads/profils/' . ($user_info['photo'] ?? 'default.jpg'); ?>" 
                     alt="Photo de profil">
                <form method="POST" action="" enctype="multipart/form-data" style="display:inline;">
                    <input type="hidden" name="action" value="upload_photo">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <label class="edit-photo" for="photo-upload" title="Changer la photo">
                        <i class="fas fa-camera"></i>
                    </label>
                    <input type="file" id="photo-upload" name="photo" accept="image/*" style="display:none;" onchange="this.form.submit()">
                </form>
            </div>
            <div class="profil-info">
                <h1><?php echo htmlspecialchars($user_info['nom'] . ' ' . $user_info['prenom']); ?></h1>
                <div class="profil-role">
                    <?php echo ucfirst($user_info['role']); ?>
                    <?php if ($user_info['role'] === 'client'): ?>
                        👤 Client
                    <?php elseif ($user_info['role'] === 'livreur'): ?>
                        🛵 Livreur
                    <?php elseif ($user_info['role'] === 'partenaire'): ?>
                        🏪 Partenaire
                    <?php elseif ($user_info['role'] === 'admin'): ?>
                        🛡️ Administrateur
                    <?php elseif ($user_info['role'] === 'createur'): ?>
                        👑 Créateur
                    <?php endif; ?>
                </div>
            </div>
            <div class="profil-stats">
                <div class="stat">
                    <span class="number"><?php echo $total_commandes; ?></span>
                    <span class="label">Commandes</span>
                </div>
                <div class="stat">
                    <span class="number"><?php echo number_format($total_depense, 0, ',', ' '); ?> FCFA</span>
                    <span class="label">Dépenses</span>
                </div>
                <div class="stat">
                    <span class="number"><?php echo number_format($solde_wallet, 0, ',', ' '); ?> FCFA</span>
                    <span class="label">Wallet</span>
                </div>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="profil-tabs">
            <button class="tab-btn active" data-tab="info">📋 Informations</button>
            <button class="tab-btn" data-tab="password">🔒 Mot de passe</button>
            <button class="tab-btn" data-tab="security">🛡️ Sécurité</button>
            <?php if ($user_info['role'] === 'client'): ?>
                <button class="tab-btn" data-tab="address">📍 Adresses</button>
            <?php endif; ?>
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
        
        <!-- Tab: Informations -->
        <div class="profil-card active" id="tab-info">
            <h2 class="card-title">📋 Informations personnelles</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_profil">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label for="nom">Nom <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-user"></i>
                            <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($user_info['nom']); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="prenom">Prénom <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-user"></i>
                            <input type="text" id="prenom" name="prenom" value="<?php echo htmlspecialchars($user_info['prenom']); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label for="email">Email <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_info['email']); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="telephone">Téléphone <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-phone"></i>
                            <input type="tel" id="telephone" name="telephone" value="<?php echo htmlspecialchars($user_info['telephone']); ?>" required>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Enregistrer les modifications
                </button>
            </form>
        </div>
        
        <!-- Tab: Mot de passe -->
        <div class="profil-card" id="tab-password">
            <h2 class="card-title">🔒 Changer mon mot de passe</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label for="current_password">Mot de passe actuel <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="current_password" name="current_password" placeholder="Votre mot de passe actuel" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="new_password">Nouveau mot de passe <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="new_password" name="new_password" placeholder="8 caractères minimum" required>
                    </div>
                    <div class="help-text">Minimum 8 caractères, une majuscule, une minuscule, un chiffre.</div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirmer le nouveau mot de passe <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirmez votre nouveau mot de passe" required>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-key"></i> Changer le mot de passe
                </button>
            </form>
        </div>
        
        <!-- Tab: Sécurité -->
        <div class="profil-card" id="tab-security">
            <h2 class="card-title">🛡️ Sécurité du compte</h2>
            
            <div style="display:grid; gap:15px;">
                <div style="padding:15px; background:#f8fafc; border-radius:12px; border:1px solid #e5e7eb;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <h4 style="font-weight:600; color:#1a1a1a; margin:0;">🔐 Double authentification</h4>
                            <p style="font-size:14px; color:#6b7280; margin:0;">Sécurisez votre compte avec une double vérification.</p>
                        </div>
                        <span class="badge bg-secondary">Bientôt disponible</span>
                    </div>
                </div>
                
                <div style="padding:15px; background:#f8fafc; border-radius:12px; border:1px solid #e5e7eb;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <h4 style="font-weight:600; color:#1a1a1a; margin:0;">📱 Appareils connectés</h4>
                            <p style="font-size:14px; color:#6b7280; margin:0;">Gérez les appareils connectés à votre compte.</p>
                        </div>
                        <span class="badge bg-secondary">Bientôt disponible</span>
                    </div>
                </div>
                
                <div style="padding:15px; background:#fef2f2; border-radius:12px; border:1px solid #fecaca;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <h4 style="font-weight:600; color:#dc2626; margin:0;">🗑️ Supprimer mon compte</h4>
                            <p style="font-size:14px; color:#6b7280; margin:0;">Cette action est irréversible. Toutes vos données seront supprimées.</p>
                        </div>
                        <button class="btn-danger" style="padding:8px 20px; font-size:14px;" onclick="if(confirm('Êtes-vous sûr de vouloir supprimer votre compte ? Cette action est irréversible.')){alert('Fonctionnalité disponible prochainement.');}">
                            <i class="fas fa-trash"></i> Supprimer
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tab: Adresses (client seulement) -->
        <?php if ($user_info['role'] === 'client'): ?>
            <div class="profil-card" id="tab-address">
                <h2 class="card-title">📍 Mes adresses</h2>
                <p style="color:#6b7280; margin-bottom:15px;">Gérez vos adresses de livraison.</p>
                
                <?php if ($client_info && $client_info['adresse_principale']): ?>
                    <div style="padding:15px; background:#f8fafc; border-radius:12px; border:1px solid #e5e7eb; margin-bottom:15px;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <h4 style="font-weight:600; color:#1a1a1a; margin:0;">🏠 Adresse principale</h4>
                                <p style="font-size:14px; color:#6b7280; margin:0;"><?php echo htmlspecialchars($client_info['adresse_principale']); ?></p>
                                <?php if ($client_info['quartier']): ?>
                                    <p style="font-size:13px; color:#9ca3af; margin:0;">Quartier: <?php echo htmlspecialchars($client_info['quartier']); ?></p>
                                <?php endif; ?>
                            </div>
                            <button class="btn-submit-outline" style="padding:6px 18px; font-size:13px;" onclick="alert('Fonctionnalité disponible prochainement.')">
                                <i class="fas fa-edit"></i> Modifier
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="text-align:center; padding:30px; color:#6b7280;">
                        <i class="fas fa-map-marker-alt" style="font-size:40px; display:block; margin-bottom:10px; color:#d1d5db;"></i>
                        <p>Aucune adresse enregistrée.</p>
                        <button class="btn-submit" style="padding:8px 25px; font-size:14px;" onclick="alert('Fonctionnalité disponible prochainement.')">
                            <i class="fas fa-plus"></i> Ajouter une adresse
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// GESTION DES TABS
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabCards = {
        'info': document.getElementById('tab-info'),
        'password': document.getElementById('tab-password'),
        'security': document.getElementById('tab-security'),
        'address': document.getElementById('tab-address')
    };
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const tab = this.dataset.tab;
            
            // Mettre à jour les boutons
            tabBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Mettre à jour les cartes
            Object.keys(tabCards).forEach(key => {
                if (tabCards[key]) {
                    tabCards[key].classList.toggle('active', key === tab);
                }
            });
        });
    });
});

// =============================================
// VALIDATION DU FORMULAIRE MOT DE PASSE
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const passwordForm = document.querySelector('form[action*="change_password"]');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            const current = document.getElementById('current_password').value;
            const newPass = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (!current || current.length < 1) {
                e.preventDefault();
                alert('Veuillez saisir votre mot de passe actuel.');
                return;
            }
            
            if (newPass.length < 8) {
                e.preventDefault();
                alert('Le nouveau mot de passe doit contenir au moins 8 caractères.');
                return;
            }
            
            if (newPass !== confirm) {
                e.preventDefault();
                alert('Les mots de passe ne correspondent pas.');
                return;
            }
        });
    }
});

// =============================================
// AUTO-SUBMIT PHOTO UPLOAD
// =============================================
document.getElementById('photo-upload')?.addEventListener('change', function() {
    if (this.files.length > 0) {
        this.form.submit();
    }
});

console.log('✅ DoriExpress-Pro - Page profil chargée');
</script>

<style>
.dark-mode .badge.bg-secondary {
    background: #333 !important;
    color: #b0b0b0 !important;
}

.dark-mode .btn-danger {
    background: #dc2626;
    color: white;
}

.dark-mode .btn-danger:hover {
    background: #b91c1c;
}

.dark-mode #tab-security > div > div {
    background: #1a1a1a !important;
    border-color: #333 !important;
}

.dark-mode #tab-security > div > div:last-child {
    background: #2a1414 !important;
    border-color: #442222 !important;
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
// FIN DU FICHIER PROFIL.PHP
// =============================================
?>