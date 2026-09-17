<?php
/**
 * =============================================
 * PAGE RECRUTEMENT - DoriExpress-Pro
 * =============================================
 * Fichier : recrutement.php
 * Rôle : Devenir livreur ou partenaire
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

// Paramètres de la page
$page_title = 'Recrutement - DoriExpress-Pro';
$page_description = 'Devenez livreur ou partenaire DoriExpress-Pro et rejoignez notre équipe.';
$page_keywords = 'recrutement, livreur, partenaire, DoriExpress, emploi';

// Traitement du formulaire
$error = '';
$success = '';
$form_data = [
    'type' => 'livreur',
    'nom' => '',
    'prenom' => '',
    'email' => '',
    'telephone' => '',
    'adresse' => '',
    'experience' => '',
    'vehicule' => 'moto',
    'disponibilite' => 'temps_plein',
    'message' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'candidature') {
    // Vérifier le token CSRF
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifier_token_csrf($csrf_token)) {
        $error = 'Erreur de sécurité. Veuillez rafraîchir la page.';
    } else {
        $form_data['type'] = $_POST['type'] ?? 'livreur';
        $form_data['nom'] = trim($_POST['nom'] ?? '');
        $form_data['prenom'] = trim($_POST['prenom'] ?? '');
        $form_data['email'] = trim($_POST['email'] ?? '');
        $form_data['telephone'] = trim($_POST['telephone'] ?? '');
        $form_data['adresse'] = trim($_POST['adresse'] ?? '');
        $form_data['experience'] = trim($_POST['experience'] ?? '');
        $form_data['vehicule'] = $_POST['vehicule'] ?? 'moto';
        $form_data['disponibilite'] = $_POST['disponibilite'] ?? 'temps_plein';
        $form_data['message'] = trim($_POST['message'] ?? '');
        
        // Validation
        $errors = [];
        
        if (empty($form_data['nom'])) {
            $errors[] = 'Veuillez saisir votre nom.';
        }
        
        if (empty($form_data['prenom'])) {
            $errors[] = 'Veuillez saisir votre prénom.';
        }
        
        if (empty($form_data['email']) || !valider_email($form_data['email'])) {
            $errors[] = 'Veuillez saisir une adresse email valide.';
        }
        
        if (empty($form_data['telephone']) || !valider_telephone($form_data['telephone'])) {
            $errors[] = 'Veuillez saisir un numéro de téléphone valide.';
        }
        
        if (empty($form_data['adresse'])) {
            $errors[] = 'Veuillez saisir votre adresse.';
        }
        
        // Si pas d'erreurs
        if (empty($errors)) {
            try {
                $db = Database::getInstance();
                $db->beginTransaction();
                
                // Vérifier si l'utilisateur existe déjà
                $existing = $db->fetchOne(
                    "SELECT id FROM utilisateurs WHERE email = ? OR telephone = ?",
                    [$form_data['email'], $form_data['telephone']]
                );
                
                if ($existing) {
                    $user_id = $existing['id'];
                } else {
                    // Créer l'utilisateur
                    $password = generer_mot_de_passe(12);
                    $password_hash = hash_mot_de_passe($password);
                    
                    $db->query(
                        "INSERT INTO utilisateurs (nom, prenom, email, telephone, mot_de_passe_hash, role, statut, date_creation) 
                         VALUES (?, ?, ?, ?, ?, ?, 'actif', NOW())",
                        [
                            $form_data['nom'],
                            $form_data['prenom'],
                            $form_data['email'],
                            $form_data['telephone'],
                            $password_hash,
                            $form_data['type'] === 'livreur' ? 'livreur' : 'partenaire'
                        ]
                    );
                    $user_id = $db->lastInsertId();
                    
                    // Créer le wallet
                    $db->query(
                        "INSERT INTO wallet (utilisateur_id, solde) VALUES (?, 0)",
                        [$user_id]
                    );
                }
                
                // Créer le profil livreur ou partenaire
                if ($form_data['type'] === 'livreur') {
                    $db->query(
                        "INSERT INTO livreurs (utilisateur_id, type_vehicule, zone_travail, statut_validation, disponibilite, date_inscription) 
                         VALUES (?, ?, ?, 'en_attente', 'hors_ligne', NOW())",
                        [$user_id, $form_data['vehicule'], $form_data['adresse']]
                    );
                } else {
                    $db->query(
                        "INSERT INTO partenaires (utilisateur_id, type_activite, adresse, statut_validation, date_inscription) 
                         VALUES (?, 'commerce', ?, 'en_attente', NOW())",
                        [$user_id, $form_data['adresse']]
                    );
                }
                
                // Journaliser la candidature
                journaliser($user_id, 'candidature_' . $form_data['type'], 'recrutement', [
                    'type' => $form_data['type'],
                    'vehicule' => $form_data['vehicule'],
                    'disponibilite' => $form_data['disponibilite']
                ]);
                
                // Notifier le créateur
                ajouter_notification_createur(
                    'Nouvelle candidature ' . $form_data['type'],
                    $form_data['nom'] . ' ' . $form_data['prenom'] . ' a postulé comme ' . $form_data['type'],
                    'recrutement',
                    URL_BASE . 'admin/' . ($form_data['type'] === 'livreur' ? 'livreurs.php' : 'partenaires.php')
                );
                
                $db->commit();
                
                $success = '✅ Votre candidature a été envoyée avec succès ! Nous vous contacterons dans les plus brefs délais.';
                
                // Réinitialiser le formulaire
                $form_data = [
                    'type' => $form_data['type'],
                    'nom' => '',
                    'prenom' => '',
                    'email' => '',
                    'telephone' => '',
                    'adresse' => '',
                    'experience' => '',
                    'vehicule' => 'moto',
                    'disponibilite' => 'temps_plein',
                    'message' => ''
                ];
                
            } catch (Exception $e) {
                $db->rollback();
                $error = 'Une erreur est survenue. Veuillez réessayer.';
                journaliser(null, 'candidature_echouee', 'recrutement', ['error' => $e->getMessage()]);
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE RECRUTEMENT
 * ============================================= */
.page-recrutement {
    background: #f8fafc;
    min-height: 100vh;
    padding: 30px 0 60px;
}

.recrutement-header {
    text-align: center;
    margin-bottom: 40px;
}

.recrutement-header h1 {
    font-size: 36px;
    font-weight: 800;
    color: #1a1a1a;
}

.recrutement-header p {
    color: #6b7280;
    font-size: 18px;
    max-width: 600px;
    margin: 10px auto 0;
}

/* Cards info */
.info-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.info-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    text-align: center;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}

.info-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}

.info-card .info-icon {
    font-size: 40px;
    margin-bottom: 12px;
}

.info-card .info-title {
    font-weight: 700;
    font-size: 18px;
    color: #1a1a1a;
}

.info-card .info-desc {
    font-size: 14px;
    color: #6b7280;
    margin-top: 6px;
}

/* Form */
.recrutement-form {
    background: white;
    border-radius: 16px;
    padding: 30px;
    border: 1px solid #e5e7eb;
    max-width: 700px;
    margin: 0 auto;
}

.recrutement-form .form-title {
    font-size: 20px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 20px;
    text-align: center;
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

.form-group .input-wrapper input,
.form-group .input-wrapper select,
.form-group .input-wrapper textarea {
    width: 100%;
    padding: 12px 14px 12px 45px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 15px;
    transition: all 0.3s ease;
    background: #f9fafb;
    color: #1a1a1a;
}

.form-group .input-wrapper input:focus,
.form-group .input-wrapper select:focus,
.form-group .input-wrapper textarea:focus {
    border-color: #00A651;
    background: white;
    box-shadow: 0 0 0 4px rgba(0, 166, 81, 0.1);
    outline: none;
}

.form-group .input-wrapper textarea {
    padding-left: 14px;
    min-height: 100px;
    resize: vertical;
}

.form-group .help-text {
    font-size: 13px;
    color: #6b7280;
    margin-top: 4px;
}

/* Type selector */
.type-selector {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 20px;
}

.type-option {
    position: relative;
}

.type-option input[type="radio"] {
    display: none;
}

.type-option label {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 20px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #f9fafb;
    font-weight: 600;
    font-size: 16px;
    color: #374151;
}

.type-option label i {
    font-size: 30px;
    color: #9ca3af;
}

.type-option input[type="radio"]:checked + label {
    border-color: #00A651;
    background: rgba(0, 166, 81, 0.05);
    color: #00A651;
}

.type-option input[type="radio"]:checked + label i {
    color: #00A651;
}

.type-option label:hover {
    border-color: #00A651;
    background: rgba(0, 166, 81, 0.02);
}

.btn-submit {
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, #00A651, #008a44);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 18px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 40px rgba(0, 166, 81, 0.3);
}

/* Responsive */
@media (max-width: 768px) {
    .recrutement-header h1 {
        font-size: 28px;
    }
    .recrutement-form {
        padding: 20px;
        margin: 0 10px;
    }
    .type-selector {
        grid-template-columns: 1fr;
    }
    .info-cards {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .recrutement-header h1 {
        font-size: 24px;
    }
    .recrutement-form .form-title {
        font-size: 18px;
    }
}

/* Dark Mode */
.dark-mode .page-recrutement {
    background: #121212;
}

.dark-mode .recrutement-header h1 {
    color: #e5e5e5;
}

.dark-mode .info-card {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .info-card .info-title {
    color: #e5e5e5;
}

.dark-mode .info-card .info-desc {
    color: #b0b0b0;
}

.dark-mode .recrutement-form {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .recrutement-form .form-title {
    color: #e5e5e5;
}

.dark-mode .form-group label {
    color: #d0d0d0;
}

.dark-mode .form-group .input-wrapper input,
.dark-mode .form-group .input-wrapper select,
.dark-mode .form-group .input-wrapper textarea {
    background: #1a1a1a;
    border-color: #444;
    color: #e5e5e5;
}

.dark-mode .form-group .input-wrapper input:focus,
.dark-mode .form-group .input-wrapper select:focus,
.dark-mode .form-group .input-wrapper textarea:focus {
    border-color: #00A651;
    background: #2a2a2a;
}

.dark-mode .type-option label {
    background: #1a1a1a;
    border-color: #444;
    color: #b0b0b0;
}

.dark-mode .type-option input[type="radio"]:checked + label {
    border-color: #00A651;
    background: rgba(0, 166, 81, 0.1);
    color: #00A651;
}

.dark-mode .type-option label:hover {
    border-color: #00A651;
}
</style>

<!-- ============================================= -->
<!-- PAGE RECRUTEMENT -->
<!-- ============================================= -->
<div class="page-recrutement">
    <div class="container">
        
        <!-- Header -->
        <div class="recrutement-header">
            <h1>🤝 Rejoignez l'équipe</h1>
            <p>Devenez livreur ou partenaire et faites partie de l'aventure DoriExpress-Pro.</p>
        </div>
        
        <!-- Info Cards -->
        <div class="info-cards">
            <div class="info-card animate-on-scroll">
                <div class="info-icon">🛵</div>
                <div class="info-title">Devenir livreur</div>
                <div class="info-desc">Gagnez de l'argent en livrant des colis et repas. Horaires flexibles.</div>
            </div>
            <div class="info-card animate-on-scroll">
                <div class="info-icon">🏪</div>
                <div class="info-title">Devenir partenaire</div>
                <div class="info-desc">Développez votre activité en rejoignant notre marketplace locale.</div>
            </div>
            <div class="info-card animate-on-scroll">
                <div class="info-icon">📈</div>
                <div class="info-title">Pourquoi nous ?</div>
                <div class="info-desc">Formation, accompagnement, revenus compétitifs et flexibilité.</div>
            </div>
        </div>
        
        <!-- Formulaire -->
        <div class="recrutement-form animate-on-scroll">
            <div class="form-title">
                <?php if ($success): ?>
                    <i class="fas fa-check-circle" style="color:#22c55e;"></i> Candidature envoyée !
                <?php else: ?>
                    📝 Formulaire de candidature
                <?php endif; ?>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
                <div style="text-align:center; margin-top:15px;">
                    <a href="<?php echo URL_BASE; ?>index.php" class="btn btn-success">
                        <i class="fas fa-home"></i> Retour à l'accueil
                    </a>
                </div>
            <?php else: ?>
                <form method="POST" action="" id="recrutement-form">
                    <input type="hidden" name="action" value="candidature">
                    <input type="hidden" name="csrf_token" value="<?php echo generer_token_csrf(); ?>">
                    
                    <!-- Type de candidature -->
                    <div class="type-selector">
                        <div class="type-option">
                            <input type="radio" id="type_livreur" name="type" value="livreur" 
                                   <?php echo $form_data['type'] === 'livreur' ? 'checked' : ''; ?>>
                            <label for="type_livreur">
                                <i class="fas fa-motorcycle"></i>
                                Livreur
                            </label>
                        </div>
                        <div class="type-option">
                            <input type="radio" id="type_partenaire" name="type" value="partenaire"
                                   <?php echo $form_data['type'] === 'partenaire' ? 'checked' : ''; ?>>
                            <label for="type_partenaire">
                                <i class="fas fa-store"></i>
                                Partenaire
                            </label>
                        </div>
                    </div>
                    
                    <!-- Nom et Prénom -->
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                        <div class="form-group">
                            <label for="nom">Nom <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-user"></i>
                                <input type="text" id="nom" name="nom" placeholder="Votre nom" required
                                       value="<?php echo htmlspecialchars($form_data['nom']); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="prenom">Prénom <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-user"></i>
                                <input type="text" id="prenom" name="prenom" placeholder="Votre prénom" required
                                       value="<?php echo htmlspecialchars($form_data['prenom']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Email et Téléphone -->
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                        <div class="form-group">
                            <label for="email">Email <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="email" name="email" placeholder="exemple@email.com" required
                                       value="<?php echo htmlspecialchars($form_data['email']); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="telephone">Téléphone <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-phone"></i>
                                <input type="tel" id="telephone" name="telephone" placeholder="70XXXXXX" required
                                       value="<?php echo htmlspecialchars($form_data['telephone']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Adresse -->
                    <div class="form-group">
                        <label for="adresse">Adresse <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-map-marker-alt"></i>
                            <input type="text" id="adresse" name="adresse" placeholder="Votre adresse complète" required
                                   value="<?php echo htmlspecialchars($form_data['adresse']); ?>">
                        </div>
                    </div>
                    
                    <!-- Type de véhicule (pour livreur) -->
                    <div class="form-group" id="vehicule-group">
                        <label for="vehicule">Type de véhicule</label>
                        <div class="input-wrapper">
                            <i class="fas fa-car"></i>
                            <select id="vehicule" name="vehicule">
                                <option value="moto" <?php echo $form_data['vehicule'] === 'moto' ? 'selected' : ''; ?>>🛵 Moto</option>
                                <option value="velo" <?php echo $form_data['vehicule'] === 'velo' ? 'selected' : ''; ?>>🚲 Vélo</option>
                                <option value="voiture" <?php echo $form_data['vehicule'] === 'voiture' ? 'selected' : ''; ?>>🚗 Voiture</option>
                                <option value="camionnette" <?php echo $form_data['vehicule'] === 'camionnette' ? 'selected' : ''; ?>>🚐 Camionnette</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Disponibilité -->
                    <div class="form-group">
                        <label for="disponibilite">Disponibilité</label>
                        <div class="input-wrapper">
                            <i class="fas fa-clock"></i>
                            <select id="disponibilite" name="disponibilite">
                                <option value="temps_plein" <?php echo $form_data['disponibilite'] === 'temps_plein' ? 'selected' : ''; ?>>Temps plein</option>
                                <option value="temps_partiel" <?php echo $form_data['disponibilite'] === 'temps_partiel' ? 'selected' : ''; ?>>Temps partiel</option>
                                <option value="weekend" <?php echo $form_data['disponibilite'] === 'weekend' ? 'selected' : ''; ?>>Week-ends uniquement</option>
                                <option value="soir" <?php echo $form_data['disponibilite'] === 'soir' ? 'selected' : ''; ?>>Soirées uniquement</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Expérience -->
                    <div class="form-group">
                        <label for="experience">Expérience (optionnel)</label>
                        <div class="input-wrapper">
                            <i class="fas fa-briefcase"></i>
                            <textarea id="experience" name="experience" placeholder="Décrivez votre expérience professionnelle (livraison, transport, etc.)"><?php echo htmlspecialchars($form_data['experience']); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Message -->
                    <div class="form-group">
                        <label for="message">Message (optionnel)</label>
                        <div class="input-wrapper">
                            <i class="fas fa-pen"></i>
                            <textarea id="message" name="message" placeholder="Informations complémentaires..."><?php echo htmlspecialchars($form_data['message']); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Bouton -->
                    <button type="submit" class="btn-submit" id="btn-submit">
                        <i class="fas fa-paper-plane"></i> Envoyer ma candidature
                    </button>
                </form>
            <?php endif; ?>
        </div>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// AFFICHER/MASQUER LE TYPE DE VÉHICULE
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const typeRadios = document.querySelectorAll('input[name="type"]');
    const vehiculeGroup = document.getElementById('vehicule-group');
    
    function toggleVehicule() {
        const selectedType = document.querySelector('input[name="type"]:checked');
        if (selectedType && selectedType.value === 'livreur') {
            vehiculeGroup.style.display = 'block';
        } else {
            vehiculeGroup.style.display = 'none';
        }
    }
    
    typeRadios.forEach(radio => {
        radio.addEventListener('change', toggleVehicule);
    });
    
    toggleVehicule();
});

// =============================================
// VALIDATION DU FORMULAIRE
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('recrutement-form');
    const btn = document.getElementById('btn-submit');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            const nom = document.getElementById('nom').value.trim();
            const prenom = document.getElementById('prenom').value.trim();
            const email = document.getElementById('email').value.trim();
            const telephone = document.getElementById('telephone').value.trim();
            const adresse = document.getElementById('adresse').value.trim();
            
            let hasError = false;
            
            if (!nom) { showFieldError('nom', 'Veuillez saisir votre nom.'); hasError = true; }
            if (!prenom) { showFieldError('prenom', 'Veuillez saisir votre prénom.'); hasError = true; }
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showFieldError('email', 'Veuillez saisir une adresse email valide.');
                hasError = true;
            }
            if (!telephone || !/^[0-9]{8}$/.test(telephone.replace(/[^0-9]/g, ''))) {
                showFieldError('telephone', 'Veuillez saisir un numéro valide (8 chiffres).');
                hasError = true;
            }
            if (!adresse) {
                showFieldError('adresse', 'Veuillez saisir votre adresse.');
                hasError = true;
            }
            
            if (hasError) {
                e.preventDefault();
                return;
            }
            
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Envoi en cours...';
            btn.disabled = true;
        });
    }
});

function showFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    const wrapper = field.closest('.input-wrapper');
    field.style.borderColor = '#ef4444';
    field.style.boxShadow = '0 0 0 4px rgba(239, 68, 68, 0.1)';
    
    let errorDiv = wrapper.parentNode.querySelector('.field-error');
    if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        errorDiv.style.cssText = 'color: #ef4444; font-size: 13px; margin-top: 4px;';
        wrapper.parentNode.appendChild(errorDiv);
    }
    errorDiv.textContent = message;
    
    field.addEventListener('focus', function() {
        this.style.borderColor = '';
        this.style.boxShadow = '';
        const err = this.closest('.input-wrapper').parentNode.querySelector('.field-error');
        if (err) err.remove();
    });
}

// =============================================
// ANIMATION AU SCROLL
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const elements = document.querySelectorAll('.animate-on-scroll');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animated');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.05 });
    
    elements.forEach(el => observer.observe(el));
});

console.log('✅ DoriExpress-Pro - Page recrutement chargée');
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

.dark-mode .btn-success {
    background: #00A651;
    color: white;
}

.dark-mode .btn-success:hover {
    background: #008a44;
}

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
</style>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER RECRUTEMENT.PHP
// =============================================
?>