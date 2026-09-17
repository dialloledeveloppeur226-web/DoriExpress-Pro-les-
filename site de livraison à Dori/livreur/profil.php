<?php
/**
 * =============================================
 * PROFIL LIVREUR - DoriExpress-Pro
 * =============================================
 * Fichier : livreur/profil.php
 * Rôle : Gestion du profil du livreur (informations, documents, disponibilité)
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

// Vérifier que l'utilisateur est connecté et est livreur
if (!est_connecte() || !est_livreur()) {
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('livreur/profil.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Mon profil - DoriExpress-Pro';
$page_description = 'Gérez votre profil de livreur.';
$page_keywords = 'profil, livreur, DoriExpress';

$user_id = $_SESSION['user_id'];

try {
    $db = Database::getInstance();
    
    // Récupérer les informations de l'utilisateur
    $user = $db->fetchOne("SELECT * FROM utilisateurs WHERE id = ?", [$user_id]);
    
    // Récupérer les informations du livreur
    $livreur = $db->fetchOne(
        "SELECT * FROM livreurs WHERE utilisateur_id = ?",
        [$user_id]
    );
    
    if (!$livreur) {
        $db->query(
            "INSERT INTO livreurs (utilisateur_id, disponibilite, statut_validation) 
             VALUES (?, 'hors_ligne', 'en_attente')",
            [$user_id]
        );
        $livreur = $db->fetchOne(
            "SELECT * FROM livreurs WHERE utilisateur_id = ?",
            [$user_id]
        );
    }
    
    // Récupérer les documents du livreur
    $documents = $db->fetchAll(
        "SELECT * FROM documents_livreurs WHERE livreur_id = ?",
        [$livreur['id']]
    );
    
} catch (Exception $e) {
    $user = null;
    $livreur = null;
    $documents = [];
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
            $type_vehicule = $_POST['type_vehicule'] ?? 'moto';
            $plaque = trim($_POST['plaque'] ?? '');
            $zone_travail = trim($_POST['zone_travail'] ?? '');
            
            $errors = [];
            
            if (empty($nom)) $errors[] = 'Veuillez saisir votre nom.';
            if (empty($prenom)) $errors[] = 'Veuillez saisir votre prénom.';
            if (empty($email) || !valider_email($email)) $errors[] = 'Veuillez saisir une adresse email valide.';
            if (empty($telephone) || !valider_telephone($telephone)) $errors[] = 'Veuillez saisir un numéro de téléphone valide.';
            if (empty($zone_travail)) $errors[] = 'Veuillez saisir votre zone de travail.';
            
            if (empty($errors)) {
                try {
                    $db->beginTransaction();
                    
                    // Mettre à jour l'utilisateur
                    $db->query(
                        "UPDATE utilisateurs SET nom = ?, prenom = ?, email = ?, telephone = ? WHERE id = ?",
                        [$nom, $prenom, $email, $telephone, $user_id]
                    );
                    
                    // Mettre à jour le livreur
                    $db->query(
                        "UPDATE livreurs SET 
                            type_vehicule = ?, 
                            plaque_vehicule = ?, 
                            zone_travail = ? 
                         WHERE utilisateur_id = ?",
                        [$type_vehicule, $plaque, $zone_travail, $user_id]
                    );
                    
                    // Mettre à jour la session
                    $_SESSION['user_nom'] = $nom . ' ' . $prenom;
                    $_SESSION['user_email'] = $email;
                    
                    $db->commit();
                    $success = '✅ Profil mis à jour avec succès !';
                    
                    // Recharger les données
                    $user = $db->fetchOne("SELECT * FROM utilisateurs WHERE id = ?", [$user_id]);
                    $livreur = $db->fetchOne("SELECT * FROM livreurs WHERE utilisateur_id = ?", [$user_id]);
                    
                } catch (Exception $e) {
                    $db->rollback();
                    $error = 'Erreur lors de la mise à jour du profil.';
                }
            } else {
                $error = implode('<br>', $errors);
            }
        }
        
        // Action: Upload document
        if ($_POST['action'] === 'upload_document' && isset($_FILES['document'])) {
            $type_document = $_POST['type_document'] ?? 'autre';
            $file = $_FILES['document'];
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'Erreur lors du téléchargement du document.';
            } else {
                $result = upload_fichier($file, UPLOAD_DOCUMENTS, UPLOAD_TYPES_DOCUMENT, UPLOAD_MAX_SIZE_DOCUMENT);
                
                if ($result['success']) {
                    try {
                        $db->query(
                            "INSERT INTO documents_livreurs (
                                livreur_id, type_document, fichier, date_upload
                            ) VALUES (?, ?, ?, NOW())",
                            [$livreur['id'], $type_document, $result['nom']]
                        );
                        
                        $success = '✅ Document téléchargé avec succès ! En attente de validation.';
                        
                        // Recharger les documents
                        $documents = $db->fetchAll(
                            "SELECT * FROM documents_livreurs WHERE livreur_id = ?",
                            [$livreur['id']]
                        );
                        
                    } catch (Exception $e) {
                        $error = 'Erreur lors de l\'enregistrement du document.';
                    }
                } else {
                    $error = $result['message'];
                }
            }
        }
    }
}

// Définitions
$vehicule_types = [
    'moto' => '🛵 Moto',
    'velo' => '🚲 Vélo',
    'voiture' => '🚗 Voiture',
    'camionnette' => '🚐 Camionnette'
];

$document_types = [
    'cni' => 'Carte d\'identité',
    'permis' => 'Permis de conduire',
    'photo_vehicule' => 'Photo du véhicule',
    'assurance' => 'Assurance',
    'autre' => 'Autre document'
];

$status_labels = [
    'en_attente' => ['label' => '⏳ En attente', 'color' => 'warning'],
    'verification' => ['label' => '🔍 En vérification', 'color' => 'info'],
    'valide' => ['label' => '✅ Validé', 'color' => 'success'],
    'refuse' => ['label' => '❌ Refusé', 'color' => 'danger'],
    'suspendu' => ['label' => '🚫 Suspendu', 'color' => 'danger']
];

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE LIVREUR PROFIL
 * ============================================= */
.page-livreur-profil {
    background: #f8fafc;
    min-height: 100vh;
    padding: 20px 0 60px;
}

.livreur-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}

.livreur-header h1 {
    font-size: 24px;
    font-weight: 800;
    color: #1a1a1a;
    margin: 0;
}

/* Profile Header */
.profile-header {
    background: white;
    border-radius: 16px;
    padding: 25px;
    border: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    gap: 25px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

.profile-header .profile-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #00A651;
}

.profile-header .profile-info h2 {
    font-size: 22px;
    font-weight: 800;
    color: #1a1a1a;
    margin: 0;
}

.profile-header .profile-info .profile-role {
    font-size: 14px;
    color: #6b7280;
}

.profile-header .profile-stats {
    display: flex;
    gap: 20px;
    margin-left: auto;
}

.profile-header .profile-stats .stat {
    text-align: center;
}

.profile-header .profile-stats .stat .number {
    font-size: 20px;
    font-weight: 800;
    color: #00A651;
    display: block;
}

.profile-header .profile-stats .stat .label {
    font-size: 13px;
    color: #6b7280;
}

/* Profile Grid */
.profile-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.profile-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid #e5e7eb;
}

.profile-card .card-title {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #00A651;
}

.profile-card .form-group {
    margin-bottom: 15px;
}

.profile-card .form-group label {
    display: block;
    font-weight: 600;
    font-size: 14px;
    color: #374151;
    margin-bottom: 6px;
}

.profile-card .form-group .input-wrapper {
    position: relative;
}

.profile-card .form-group .input-wrapper i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
}

.profile-card .form-group .input-wrapper input,
.profile-card .form-group .input-wrapper select {
    width: 100%;
    padding: 12px 14px 12px 45px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 15px;
    transition: all 0.3s ease;
    background: #f9fafb;
    color: #1a1a1a;
}

.profile-card .form-group .input-wrapper input:focus,
.profile-card .form-group .input-wrapper select:focus {
    border-color: #00A651;
    background: white;
    box-shadow: 0 0 0 4px rgba(0, 166, 81, 0.1);
    outline: none;
}

.btn-submit {
    padding: 10px 30px;
    background: #00A651;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-submit:hover {
    background: #008a44;
    transform: translateY(-2px);
}

/* Documents */
.document-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f3f4f6;
}

.document-item:last-child {
    border-bottom: none;
}

.document-item .doc-info .doc-name {
    font-weight: 600;
    color: #1a1a1a;
}

.document-item .doc-info .doc-date {
    font-size: 13px;
    color: #6b7280;
}

.document-item .doc-status .badge {
    padding: 4px 14px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 600;
}

/* Document upload */
.doc-upload-form {
    margin-top: 15px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.doc-upload-form select,
.doc-upload-form input[type="file"] {
    padding: 10px 14px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 14px;
    background: #f9fafb;
    flex: 1;
    min-width: 150px;
}

.doc-upload-form button {
    padding: 10px 25px;
}

/* Responsive */
@media (max-width: 992px) {
    .profile-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .livreur-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .profile-header {
        flex-direction: column;
        text-align: center;
        padding: 20px;
    }
    .profile-header .profile-stats {
        margin-left: 0;
        justify-content: center;
        width: 100%;
    }
    .doc-upload-form {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .livreur-header h1 {
        font-size: 20px;
    }
    .profile-header .profile-info h2 {
        font-size: 18px;
    }
    .profile-card {
        padding: 15px;
    }
}

/* Dark Mode */
.dark-mode .page-livreur-profil {
    background: #121212;
}

.dark-mode .profile-header,
.dark-mode .profile-card {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .profile-header .profile-info h2 {
    color: #e5e5e5;
}

.dark-mode .profile-header .profile-stats .stat .label {
    color: #a0a0a0;
}

.dark-mode .profile-card .card-title {
    color: #e5e5e5;
    border-bottom-color: #00A651;
}

.dark-mode .profile-card .form-group label {
    color: #d0d0d0;
}

.dark-mode .profile-card .form-group .input-wrapper input,
.dark-mode .profile-card .form-group .input-wrapper select {
    background: #1a1a1a;
    border-color: #444;
    color: #e5e5e5;
}

.dark-mode .profile-card .form-group .input-wrapper input:focus,
.dark-mode .profile-card .form-group .input-wrapper select:focus {
    border-color: #00A651;
    background: #2a2a2a;
}

.dark-mode .document-item {
    border-color: #333;
}

.dark-mode .document-item .doc-info .doc-name {
    color: #e5e5e5;
}

.dark-mode .document-item .doc-info .doc-date {
    color: #b0b0b0;
}

.dark-mode .doc-upload-form select,
.dark-mode .doc-upload-form input[type="file"] {
    background: #1a1a1a;
    border-color: #444;
    color: #e5e5e5;
}
</style>

<!-- ============================================= -->
<!-- PAGE LIVREUR PROFIL -->
<!-- ============================================= -->
<div class="page-livreur-profil">
    <div class="container">
        
        <!-- Header -->
        <div class="livreur-header">
            <h1><i class="fas fa-id-card" style="color:#00A651;"></i> Mon profil</h1>
            <div>
                <span class="badge bg-<?php echo $livreur['statut_validation'] == 'valide' ? 'success' : ($livreur['statut_validation'] == 'en_attente' ? 'warning' : 'danger'); ?>">
                    <?php echo $status_labels[$livreur['statut_validation']]['label'] ?? 'En attente'; ?>
                </span>
                <a href="<?php echo URL_BASE; ?>livreur/dashboard.php" class="btn btn-outline-secondary btn-sm ms-2">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>
        
        <!-- Profile Header -->
        <div class="profile-header">
            <img src="<?php echo URL_BASE . 'uploads/profils/' . ($user['photo'] ?? 'default.jpg'); ?>" 
                 alt="<?php echo htmlspecialchars($user['nom']); ?>" 
                 class="profile-avatar">
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom']); ?></h2>
                <div class="profile-role">
                    🛵 Livreur • 
                    <?php echo $vehicule_types[$livreur['type_vehicule']] ?? 'Non défini'; ?>
                    <?php if ($livreur['plaque_vehicule']): ?>
                        • 🚗 <?php echo htmlspecialchars($livreur['plaque_vehicule']); ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="profile-stats">
                <div class="stat">
                    <span class="number"><?php echo $livreur['total_livraisons'] ?? 0; ?></span>
                    <span class="label">Livraisons</span>
                </div>
                <div class="stat">
                    <span class="number">⭐ <?php echo number_format($livreur['note_moyenne'] ?? 0, 1); ?></span>
                    <span class="label">Note</span>
                </div>
                <div class="stat">
                    <span class="number"><?php echo $livreur['solde_disponible'] ?? 0; ?> FCFA</span>
                    <span class="label">Solde</span>
                </div>
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
        
        <!-- Profile Grid -->
        <div class="profile-grid">
            
            <!-- Informations -->
            <div class="profile-card">
                <div class="card-title"><i class="fas fa-user" style="color:#00A651;"></i> Informations personnelles</div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profil">
                    <input type="hidden" name="csrf_token" value="<?php echo generer_token_csrf(); ?>">
                    
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="form-group">
                            <label for="nom">Nom <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-user"></i>
                                <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($user['nom']); ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="prenom">Prénom <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-user"></i>
                                <input type="text" id="prenom" name="prenom" value="<?php echo htmlspecialchars($user['prenom']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="form-group">
                            <label for="email">Email <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="telephone">Téléphone <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-phone"></i>
                                <input type="tel" id="telephone" name="telephone" value="<?php echo htmlspecialchars($user['telephone']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="form-group">
                            <label for="type_vehicule">Type de véhicule</label>
                            <div class="input-wrapper">
                                <i class="fas fa-car"></i>
                                <select id="type_vehicule" name="type_vehicule">
                                    <?php foreach ($vehicule_types as $key => $label): ?>
                                        <option value="<?php echo $key; ?>" <?php echo ($livreur['type_vehicule'] ?? 'moto') == $key ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="plaque">Plaque d'immatriculation</label>
                            <div class="input-wrapper">
                                <i class="fas fa-id-card"></i>
                                <input type="text" id="plaque" name="plaque" value="<?php echo htmlspecialchars($livreur['plaque_vehicule'] ?? ''); ?>" placeholder="Ex: AB-123-CD">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="zone_travail">Zone de travail <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-map-marker-alt"></i>
                            <input type="text" id="zone_travail" name="zone_travail" value="<?php echo htmlspecialchars($livreur['zone_travail'] ?? ''); ?>" placeholder="Ex: Dori Centre" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Mettre à jour
                    </button>
                </form>
            </div>
            
            <!-- Documents -->
            <div class="profile-card">
                <div class="card-title"><i class="fas fa-file" style="color:#00A651;"></i> Documents</div>
                
                <div style="margin-bottom:15px;">
                    <?php if (!empty($documents)): ?>
                        <?php foreach ($documents as $doc): ?>
                            <div class="document-item">
                                <div class="doc-info">
                                    <div class="doc-name"><?php echo $document_types[$doc['type_document']] ?? ucfirst($doc['type_document']); ?></div>
                                    <div class="doc-date">
                                        <i class="far fa-calendar-alt"></i> <?php echo formater_date($doc['date_upload'], 'd/m/Y'); ?>
                                    </div>
                                </div>
                                <div class="doc-status">
                                    <span class="badge bg-<?php echo $doc['statut_validation'] == 'valide' ? 'success' : ($doc['statut_validation'] == 'en_attente' ? 'warning' : 'danger'); ?>">
                                        <?php echo ucfirst($doc['statut_validation']); ?>
                                    </span>
                                    <?php if ($doc['statut_validation'] == 'valide'): ?>
                                        <a href="<?php echo URL_BASE . 'uploads/documents/' . $doc['fichier']; ?>" target="_blank" class="btn btn-sm btn-outline-primary ms-2">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center; padding:20px; color:#6b7280;">
                            <i class="fas fa-file" style="font-size:30px; display:block; margin-bottom:10px; color:#d1d5db;"></i>
                            <p>Aucun document téléchargé</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Upload document -->
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_document">
                    <input type="hidden" name="csrf_token" value="<?php echo generer_token_csrf(); ?>">
                    
                    <div class="doc-upload-form">
                        <select name="type_document" required>
                            <option value="">Type de document</option>
                            <?php foreach ($document_types as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                        <button type="submit" class="btn-submit" style="padding:10px 20px;">
                            <i class="fas fa-upload"></i>
                        </button>
                    </div>
                    <div style="font-size:12px; color:#6b7280; margin-top:5px;">
                        Formats acceptés: PDF, JPG, PNG, DOC (max 10 Mo)
                    </div>
                </form>
            </div>
            
        </div>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// VALIDATION DU FORMULAIRE
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[action*="update_profil"]');
    if (form) {
        form.addEventListener('submit', function(e) {
            const nom = document.getElementById('nom').value.trim();
            const prenom = document.getElementById('prenom').value.trim();
            const email = document.getElementById('email').value.trim();
            const telephone = document.getElementById('telephone').value.trim();
            const zone = document.getElementById('zone_travail').value.trim();
            
            if (!nom || !prenom || !email || !telephone || !zone) {
                e.preventDefault();
                showNotification('Veuillez remplir tous les champs obligatoires.', 'error');
                return;
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

console.log('✅ DoriExpress-Pro - Livreur profil chargé');
</script>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER LIVREUR/PROFIL.PHP
// =============================================
?>