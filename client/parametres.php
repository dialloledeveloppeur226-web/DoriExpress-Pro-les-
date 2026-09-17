<?php
/**
 * =============================================
 * PARAMÈTRES CLIENT - DoriExpress-Pro
 * =============================================
 * Fichier : client/parametres.php
 * Rôle : Gestion des préférences et paramètres du client
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

// Vérifier que l'utilisateur est connecté et est client
if (!est_connecte() || !est_client()) {
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('client/parametres.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Mes paramètres - DoriExpress-Pro';
$page_description = 'Gérez vos préférences et paramètres.';
$page_keywords = 'paramètres, préférences, client, DoriExpress';

$user_id = $_SESSION['user_id'];

try {
    $db = Database::getInstance();
    
    // Récupérer les informations de l'utilisateur
    $user = $db->fetchOne("SELECT * FROM utilisateurs WHERE id = ?", [$user_id]);
    
    // Récupérer le profil client
    $client = $db->fetchOne("SELECT * FROM clients WHERE utilisateur_id = ?", [$user_id]);
    
} catch (Exception $e) {
    $user = null;
    $client = null;
}

// Traitement des formulaires
$error = '';
$success = '';

// Mise à jour des préférences
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifier_token_csrf($csrf_token)) {
        $error = 'Erreur de sécurité. Veuillez rafraîchir la page.';
    } else {
        
        // Action: Préférences
        if ($_POST['action'] === 'preferences') {
            $notifications_email = isset($_POST['notifications_email']) ? 1 : 0;
            $notifications_whatsapp = isset($_POST['notifications_whatsapp']) ? 1 : 0;
            $notifications_push = isset($_POST['notifications_push']) ? 1 : 0;
            $langue = trim($_POST['langue'] ?? 'fr');
            $mode_sombre = isset($_POST['mode_sombre']) ? 1 : 0;
            
            try {
                // Mettre à jour les préférences dans la table clients
                $preferences = json_encode([
                    'notifications_email' => $notifications_email,
                    'notifications_whatsapp' => $notifications_whatsapp,
                    'notifications_push' => $notifications_push,
                    'langue' => $langue,
                    'mode_sombre' => $mode_sombre
                ]);
                
                $db->query(
                    "UPDATE clients SET preferences = ? WHERE utilisateur_id = ?",
                    [$preferences, $user_id]
                );
                
                $success = '✅ Préférences mises à jour avec succès !';
                
                // Recharger les données
                $client = $db->fetchOne("SELECT * FROM clients WHERE utilisateur_id = ?", [$user_id]);
                
            } catch (Exception $e) {
                $error = 'Erreur lors de la mise à jour des préférences.';
            }
        }
        
        // Action: Adresse principale
        if ($_POST['action'] === 'adresse') {
            $adresse_principale = trim($_POST['adresse_principale'] ?? '');
            $quartier = trim($_POST['quartier'] ?? '');
            $ville = trim($_POST['ville'] ?? 'Dori');
            
            if (empty($adresse_principale)) {
                $error = 'Veuillez saisir une adresse.';
            } else {
                try {
                    $db->query(
                        "UPDATE clients SET 
                            adresse_principale = ?, 
                            quartier = ?, 
                            ville = ? 
                         WHERE utilisateur_id = ?",
                        [$adresse_principale, $quartier, $ville, $user_id]
                    );
                    
                    $success = '✅ Adresse mise à jour avec succès !';
                    
                    // Recharger les données
                    $client = $db->fetchOne("SELECT * FROM clients WHERE utilisateur_id = ?", [$user_id]);
                    
                } catch (Exception $e) {
                    $error = 'Erreur lors de la mise à jour de l\'adresse.';
                }
            }
        }
    }
}

// Décoder les préférences
$preferences = [];
if ($client && isset($client['preferences'])) {
    $preferences = json_decode($client['preferences'], true);
}
$preferences = $preferences ?: [
    'notifications_email' => 1,
    'notifications_whatsapp' => 1,
    'notifications_push' => 1,
    'langue' => 'fr',
    'mode_sombre' => 0
];

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE CLIENT PARAMÈTRES
 * ============================================= */
.page-client-parametres {
    background: #f8fafc;
    min-height: 100vh;
    padding: 20px 0 60px;
}

.client-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}

.client-header h1 {
    font-size: 24px;
    font-weight: 800;
    color: #1a1a1a;
    margin: 0;
}

/* Paramètres Grid */
.parametres-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
}

.param-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}

.param-card:hover {
    border-color: #00A651;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}

.param-card .card-title {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #00A651;
}

.param-card .form-group {
    margin-bottom: 15px;
}

.param-card .form-group label {
    display: block;
    font-weight: 600;
    font-size: 14px;
    color: #374151;
    margin-bottom: 6px;
}

.param-card .form-group .input-wrapper {
    position: relative;
}

.param-card .form-group .input-wrapper i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
}

.param-card .form-group .input-wrapper input,
.param-card .form-group .input-wrapper select {
    width: 100%;
    padding: 12px 14px 12px 45px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 15px;
    transition: all 0.3s ease;
    background: #f9fafb;
    color: #1a1a1a;
}

.param-card .form-group .input-wrapper input:focus,
.param-card .form-group .input-wrapper select:focus {
    border-color: #00A651;
    background: white;
    box-shadow: 0 0 0 4px rgba(0, 166, 81, 0.1);
    outline: none;
}

.param-card .form-group .input-wrapper textarea {
    width: 100%;
    padding: 12px 14px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 15px;
    min-height: 80px;
    resize: vertical;
    transition: all 0.3s ease;
    background: #f9fafb;
    color: #1a1a1a;
}

.param-card .form-group .input-wrapper textarea:focus {
    border-color: #00A651;
    background: white;
    box-shadow: 0 0 0 4px rgba(0, 166, 81, 0.1);
    outline: none;
}

/* Toggle Switch */
.switch-group {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f3f4f6;
}

.switch-group:last-child {
    border-bottom: none;
}

.switch-group .switch-label {
    font-size: 14px;
    color: #1a1a1a;
}

.switch-group .switch-label small {
    display: block;
    font-size: 12px;
    color: #6b7280;
    font-weight: 400;
}

.switch {
    position: relative;
    width: 48px;
    height: 26px;
    background: #d1d5db;
    border-radius: 50px;
    cursor: pointer;
    transition: background 0.3s ease;
    flex-shrink: 0;
}

.switch.active {
    background: #00A651;
}

.switch .slider {
    position: absolute;
    top: 3px;
    left: 3px;
    width: 20px;
    height: 20px;
    background: white;
    border-radius: 50%;
    transition: transform 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.switch.active .slider {
    transform: translateX(22px);
}

.btn-submit {
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

.btn-submit:hover {
    background: #008a44;
    transform: translateY(-2px);
}

.btn-submit-outline {
    padding: 12px 35px;
    background: transparent;
    color: #6b7280;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-submit-outline:hover {
    border-color: #00A651;
    color: #00A651;
}

/* Responsive */
@media (max-width: 992px) {
    .parametres-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .client-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .param-card {
        padding: 20px;
    }
    .switch-group {
        flex-wrap: wrap;
        gap: 10px;
    }
}

@media (max-width: 480px) {
    .client-header h1 {
        font-size: 20px;
    }
    .param-card .card-title {
        font-size: 16px;
    }
}

/* Dark Mode */
.dark-mode .page-client-parametres {
    background: #121212;
}

.dark-mode .client-header h1 {
    color: #e5e5e5;
}

.dark-mode .param-card {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .param-card .card-title {
    color: #e5e5e5;
    border-bottom-color: #00A651;
}

.dark-mode .param-card .form-group label {
    color: #d0d0d0;
}

.dark-mode .param-card .form-group .input-wrapper input,
.dark-mode .param-card .form-group .input-wrapper select,
.dark-mode .param-card .form-group .input-wrapper textarea {
    background: #1a1a1a;
    border-color: #444;
    color: #e5e5e5;
}

.dark-mode .param-card .form-group .input-wrapper input:focus,
.dark-mode .param-card .form-group .input-wrapper select:focus,
.dark-mode .param-card .form-group .input-wrapper textarea:focus {
    border-color: #00A651;
    background: #2a2a2a;
}

.dark-mode .switch-group {
    border-color: #333;
}

.dark-mode .switch-group .switch-label {
    color: #e5e5e5;
}

.dark-mode .switch-group .switch-label small {
    color: #b0b0b0;
}

.dark-mode .btn-submit-outline {
    color: #b0b0b0;
    border-color: #444;
}

.dark-mode .btn-submit-outline:hover {
    border-color: #00A651;
    color: #00A651;
}
</style>

<!-- ============================================= -->
<!-- PAGE CLIENT PARAMÈTRES -->
<!-- ============================================= -->
<div class="page-client-parametres">
    <div class="container">
        
        <!-- Header -->
        <div class="client-header">
            <h1><i class="fas fa-cog" style="color:#00A651;"></i> Mes paramètres</h1>
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
        
        <!-- Paramètres Grid -->
        <div class="parametres-grid">
            
            <!-- Préférences -->
            <div class="param-card">
                <div class="card-title"><i class="fas fa-bell" style="color:#00A651;"></i> Préférences</div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="preferences">
                    <input type="hidden" name="csrf_token" value="<?php echo generer_token_csrf(); ?>">
                    
                    <!-- Notifications -->
                    <div class="switch-group">
                        <div class="switch-label">
                            📧 Notifications par email
                            <small>Recevoir des emails pour les commandes et promotions</small>
                        </div>
                        <div class="switch <?php echo ($preferences['notifications_email'] ?? 1) ? 'active' : ''; ?>" onclick="toggleSwitch(this, 'notifications_email')">
                            <span class="slider"></span>
                        </div>
                        <input type="hidden" name="notifications_email" value="<?php echo ($preferences['notifications_email'] ?? 1); ?>">
                    </div>
                    
                    <div class="switch-group">
                        <div class="switch-label">
                            💬 Notifications WhatsApp
                            <small>Recevoir des notifications via WhatsApp</small>
                        </div>
                        <div class="switch <?php echo ($preferences['notifications_whatsapp'] ?? 1) ? 'active' : ''; ?>" onclick="toggleSwitch(this, 'notifications_whatsapp')">
                            <span class="slider"></span>
                        </div>
                        <input type="hidden" name="notifications_whatsapp" value="<?php echo ($preferences['notifications_whatsapp'] ?? 1); ?>">
                    </div>
                    
                    <div class="switch-group">
                        <div class="switch-label">
                            📱 Notifications push
                            <small>Recevoir des notifications sur votre téléphone</small>
                        </div>
                        <div class="switch <?php echo ($preferences['notifications_push'] ?? 1) ? 'active' : ''; ?>" onclick="toggleSwitch(this, 'notifications_push')">
                            <span class="slider"></span>
                        </div>
                        <input type="hidden" name="notifications_push" value="<?php echo ($preferences['notifications_push'] ?? 1); ?>">
                    </div>
                    
                    <!-- Langue -->
                    <div class="form-group" style="margin-top:15px;">
                        <label for="langue">Langue</label>
                        <div class="input-wrapper">
                            <i class="fas fa-globe"></i>
                            <select id="langue" name="langue">
                                <option value="fr" <?php echo ($preferences['langue'] ?? 'fr') == 'fr' ? 'selected' : ''; ?>>Français</option>
                                <option value="en" <?php echo ($preferences['langue'] ?? 'fr') == 'en' ? 'selected' : ''; ?>>English</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Mode sombre -->
                    <div class="switch-group">
                        <div class="switch-label">
                            🌙 Mode sombre
                            <small>Activer le mode sombre sur l'application</small>
                        </div>
                        <div class="switch <?php echo ($preferences['mode_sombre'] ?? 0) ? 'active' : ''; ?>" onclick="toggleSwitch(this, 'mode_sombre')">
                            <span class="slider"></span>
                        </div>
                        <input type="hidden" name="mode_sombre" value="<?php echo ($preferences['mode_sombre'] ?? 0); ?>">
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Enregistrer les préférences
                    </button>
                </form>
            </div>
            
            <!-- Adresse -->
            <div class="param-card">
                <div class="card-title"><i class="fas fa-map-marker-alt" style="color:#00A651;"></i> Adresse de livraison</div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="adresse">
                    <input type="hidden" name="csrf_token" value="<?php echo generer_token_csrf(); ?>">
                    
                    <div class="form-group">
                        <label for="adresse_principale">Adresse principale <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-home"></i>
                            <input type="text" id="adresse_principale" name="adresse_principale" 
                                   placeholder="Votre adresse complète" 
                                   value="<?php echo htmlspecialchars($client['adresse_principale'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="quartier">Quartier</label>
                        <div class="input-wrapper">
                            <i class="fas fa-map-pin"></i>
                            <input type="text" id="quartier" name="quartier" 
                                   placeholder="Votre quartier" 
                                   value="<?php echo htmlspecialchars($client['quartier'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="ville">Ville</label>
                        <div class="input-wrapper">
                            <i class="fas fa-city"></i>
                            <input type="text" id="ville" name="ville" 
                                   placeholder="Votre ville" 
                                   value="<?php echo htmlspecialchars($client['ville'] ?? 'Dori'); ?>">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Enregistrer l'adresse
                    </button>
                </form>
            </div>
            
            <!-- Compte -->
            <div class="param-card">
                <div class="card-title"><i class="fas fa-user-shield" style="color:#00A651;"></i> Gestion du compte</div>
                
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <a href="<?php echo URL_BASE; ?>profil.php" class="btn-submit-outline" style="text-align:center; text-decoration:none;">
                        <i class="fas fa-user-edit"></i> Modifier mes informations
                    </a>
                    <a href="<?php echo URL_BASE; ?>client/commandes.php" class="btn-submit-outline" style="text-align:center; text-decoration:none;">
                        <i class="fas fa-history"></i> Voir mes commandes
                    </a>
                    <a href="<?php echo URL_BASE; ?>client/wallet.php" class="btn-submit-outline" style="text-align:center; text-decoration:none;">
                        <i class="fas fa-wallet"></i> Gérer mon portefeuille
                    </a>
                    <a href="<?php echo URL_BASE; ?>client/favoris.php" class="btn-submit-outline" style="text-align:center; text-decoration:none;">
                        <i class="fas fa-heart"></i> Gérer mes favoris
                    </a>
                </div>
            </div>
            
            <!-- Aide -->
            <div class="param-card">
                <div class="card-title"><i class="fas fa-life-ring" style="color:#00A651;"></i> Aide & Support</div>
                
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <a href="<?php echo URL_BASE; ?>faq.php" class="btn-submit-outline" style="text-align:center; text-decoration:none;">
                        <i class="fas fa-question-circle"></i> FAQ
                    </a>
                    <a href="<?php echo URL_BASE; ?>contact.php" class="btn-submit-outline" style="text-align:center; text-decoration:none;">
                        <i class="fas fa-envelope"></i> Nous contacter
                    </a>
                    <a href="https://wa.me/226<?php echo get_parametre('whatsapp', '61874528'); ?>" target="_blank" class="btn-submit-outline" style="text-align:center; text-decoration:none; border-color:#25D366; color:#25D366;">
                        <i class="fab fa-whatsapp"></i> WhatsApp
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
// TOGGLE SWITCH
// =============================================
function toggleSwitch(element, fieldName) {
    const isActive = element.classList.contains('active');
    element.classList.toggle('active');
    
    const hiddenInput = element.parentElement.querySelector('input[type="hidden"]');
    if (hiddenInput) {
        hiddenInput.value = isActive ? 0 : 1;
    }
}

// =============================================
// VALIDATION DU FORMULAIRE ADRESSE
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const addressForm = document.querySelector('form[action*="adresse"]');
    if (addressForm) {
        addressForm.addEventListener('submit', function(e) {
            const adresse = document.getElementById('adresse_principale').value.trim();
            if (!adresse) {
                e.preventDefault();
                showNotification('Veuillez saisir une adresse.', 'error');
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

console.log('✅ DoriExpress-Pro - Client paramètres chargé');
</script>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER CLIENT/PARAMETRES.PHP
// =============================================
?>