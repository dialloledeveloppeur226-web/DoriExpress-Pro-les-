<?php
/**
 * =============================================
 * PAGE D'INSCRIPTION PREMIUM - DoriExpress-Pro
 * =============================================
 * Fichier : register.php
 * Rôle : Inscription utilisateur avec sécurité maximale
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
$page_title = 'Inscription - DoriExpress-Pro';
$page_description = 'Créez votre compte DoriExpress-Pro et commencez à commander en quelques minutes.';
$page_keywords = 'inscription, DoriExpress, compte, livraison, Dori';

// Rediriger si déjà connecté
if (est_connecte()) {
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

// Traitement du formulaire
$error = '';
$success = '';
$form_data = [
    'nom' => '',
    'prenom' => '',
    'email' => '',
    'telephone' => '',
    'password' => '',
    'password_confirm' => '',
    'role' => 'client',
    'accept_terms' => false
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifier_token_csrf($csrf_token)) {
        $error = 'Erreur de sécurité. Veuillez rafraîchir la page.';
    } else {
        // Récupérer les données
        $form_data['nom'] = trim($_POST['nom'] ?? '');
        $form_data['prenom'] = trim($_POST['prenom'] ?? '');
        $form_data['email'] = trim($_POST['email'] ?? '');
        $form_data['telephone'] = trim($_POST['telephone'] ?? '');
        $form_data['password'] = $_POST['password'] ?? '';
        $form_data['password_confirm'] = $_POST['password_confirm'] ?? '';
        $form_data['role'] = $_POST['role'] ?? 'client';
        $form_data['accept_terms'] = isset($_POST['accept_terms']);
        
        // Validation
        $errors = [];
        
        // Nom
        if (empty($form_data['nom'])) {
            $errors['nom'] = 'Veuillez saisir votre nom.';
        } elseif (strlen($form_data['nom']) < 2) {
            $errors['nom'] = 'Le nom doit contenir au moins 2 caractères.';
        }
        
        // Prénom
        if (empty($form_data['prenom'])) {
            $errors['prenom'] = 'Veuillez saisir votre prénom.';
        } elseif (strlen($form_data['prenom']) < 2) {
            $errors['prenom'] = 'Le prénom doit contenir au moins 2 caractères.';
        }
        
        // Email
        if (empty($form_data['email'])) {
            $errors['email'] = 'Veuillez saisir votre adresse email.';
        } elseif (!valider_email($form_data['email'])) {
            $errors['email'] = 'Adresse email invalide.';
        } elseif (email_existe($form_data['email'])) {
            $errors['email'] = 'Cette adresse email est déjà utilisée.';
        }
        
        // Téléphone
        if (empty($form_data['telephone'])) {
            $errors['telephone'] = 'Veuillez saisir votre numéro de téléphone.';
        } elseif (!valider_telephone($form_data['telephone'])) {
            $errors['telephone'] = 'Numéro de téléphone invalide (ex: 70XXXXXX).';
        } elseif (telephone_existe($form_data['telephone'])) {
            $errors['telephone'] = 'Ce numéro de téléphone est déjà utilisé.';
        }
        
        // Mot de passe
        if (empty($form_data['password'])) {
            $errors['password'] = 'Veuillez saisir un mot de passe.';
        } else {
            $password_check = check_password_strength($form_data['password']);
            if ($password_check['score'] < 3) {
                $errors['password'] = 'Mot de passe trop faible. ' . implode(' ', $password_check['errors']);
            }
        }
        
        // Confirmation mot de passe
        if ($form_data['password'] !== $form_data['password_confirm']) {
            $errors['password_confirm'] = 'Les mots de passe ne correspondent pas.';
        }
        
        // Acceptation des conditions
        if (!$form_data['accept_terms']) {
            $errors['accept_terms'] = 'Vous devez accepter les conditions d\'utilisation.';
        }
        
        // Si pas d'erreurs, créer le compte
        if (empty($errors)) {
            try {
                $db = Database::getInstance();
                $db->beginTransaction();
                
                // Hacher le mot de passe
                $password_hash = hash_mot_de_passe($form_data['password']);
                
                // Insérer l'utilisateur
                $db->query(
                    "INSERT INTO utilisateurs (
                        nom, prenom, email, telephone, mot_de_passe_hash, 
                        role, statut, verifie, date_creation
                    ) VALUES (?, ?, ?, ?, ?, ?, 'actif', 0, NOW())",
                    [
                        $form_data['nom'],
                        $form_data['prenom'],
                        $form_data['email'],
                        $form_data['telephone'],
                        $password_hash,
                        $form_data['role']
                    ]
                );
                
                $user_id = $db->lastInsertId();
                
                // Créer le wallet
                $db->query(
                    "INSERT INTO wallet (utilisateur_id, solde) VALUES (?, 0)",
                    [$user_id]
                );
                
                // Si c'est un client, créer le profil client
                if ($form_data['role'] === 'client') {
                    $db->query(
                        "INSERT INTO clients (utilisateur_id, points_fidelite, niveau_client) 
                         VALUES (?, 0, 'bronze')",
                        [$user_id]
                    );
                }
                
                // Si c'est un livreur, créer le profil livreur en attente de validation
                if ($form_data['role'] === 'livreur') {
                    $db->query(
                        "INSERT INTO livreurs (utilisateur_id, statut_validation) 
                         VALUES (?, 'en_attente')",
                        [$user_id]
                    );
                }
                
                // Si c'est un partenaire, créer le profil partenaire en attente de validation
                if ($form_data['role'] === 'partenaire') {
                    $db->query(
                        "INSERT INTO partenaires (utilisateur_id, statut_validation) 
                         VALUES (?, 'en_attente')",
                        [$user_id]
                    );
                }
                
                // Journaliser l'inscription
                journaliser($user_id, 'inscription', 'auth', [
                    'email' => $form_data['email'],
                    'role' => $form_data['role']
                ]);
                
                $db->commit();
                
                // Envoyer l'email de confirmation
                // $this->sendConfirmationEmail($form_data['email'], $user_id);
                
                // Message de succès
                $success = 'Votre compte a été créé avec succès ! Vous pouvez maintenant vous connecter.';
                
                // Rediriger vers la page de connexion après 3 secondes
                header('Refresh: 3; URL=' . URL_BASE . 'login.php');
                
            } catch (Exception $e) {
                $db->rollback();
                $error = 'Une erreur est survenue lors de la création du compte.';
                journaliser(null, 'inscription_echouee', 'auth', [
                    'email' => $form_data['email'],
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            $error = 'Veuillez corriger les erreurs ci-dessous.';
        }
    }
}

// Générer un token CSRF
$csrf_token = generer_token_csrf();

// Récupérer les rôles disponibles pour l'inscription
$available_roles = [
    'client' => 'Client',
    'livreur' => 'Livreur',
    'partenaire' => 'Partenaire'
];

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* Styles spécifiques à la page d'inscription */
.register-section {
    min-height: 80vh;
    display: flex;
    align-items: center;
    padding: 60px 0;
    background: linear-gradient(135deg, #f8fafc 0%, #e5e7eb 100%);
}

.register-card {
    background: white;
    border-radius: 24px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.1);
    padding: 50px 45px;
    max-width: 550px;
    margin: 0 auto;
    transition: all 0.3s ease;
}

.register-card:hover {
    box-shadow: 0 30px 80px rgba(0,0,0,0.15);
}

.register-header {
    text-align: center;
    margin-bottom: 35px;
}

.register-header .icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, #00A651, #008a44);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    font-size: 30px;
    color: white;
}

.register-header h1 {
    font-size: 28px;
    font-weight: 800;
    color: #1a1a1a;
    margin-bottom: 8px;
}

.register-header p {
    color: #6b7280;
    font-size: 15px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
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
    font-size: 18px;
}

.form-group .input-wrapper input,
.form-group .input-wrapper select {
    width: 100%;
    padding: 14px 14px 14px 45px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 16px;
    transition: all 0.3s ease;
    background: #f9fafb;
    color: #1a1a1a;
    appearance: none;
}

.form-group .input-wrapper select {
    padding-right: 45px;
    cursor: pointer;
}

.form-group .input-wrapper .select-arrow {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    pointer-events: none;
}

.form-group .input-wrapper input:focus,
.form-group .input-wrapper select:focus {
    border-color: #00A651;
    background: white;
    box-shadow: 0 0 0 4px rgba(0, 166, 81, 0.15);
    outline: none;
}

.form-group .input-wrapper input.error,
.form-group .input-wrapper select.error {
    border-color: #ef4444;
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.15);
}

.form-group .input-wrapper .toggle-password {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #9ca3af;
    cursor: pointer;
    font-size: 18px;
    padding: 5px;
}

.form-group .input-wrapper .toggle-password:hover {
    color: #6b7280;
}

.form-group .error-message {
    color: #ef4444;
    font-size: 13px;
    margin-top: 5px;
    display: block;
}

.form-group .help-text {
    color: #6b7280;
    font-size: 12px;
    margin-top: 4px;
}

/* Password strength */
.password-strength {
    margin-top: 8px;
    display: flex;
    gap: 8px;
    align-items: center;
}

.password-strength .bars {
    display: flex;
    gap: 4px;
    flex: 1;
}

.password-strength .bar {
    height: 4px;
    flex: 1;
    background: #e5e7eb;
    border-radius: 4px;
    transition: all 0.3s ease;
}

.password-strength .bar.active {
    background: #00A651;
}

.password-strength .label {
    font-size: 12px;
    font-weight: 600;
    min-width: 70px;
    text-align: right;
}

.password-strength .label.weak { color: #ef4444; }
.password-strength .label.medium { color: #f59e0b; }
.password-strength .label.strong { color: #22c55e; }

/* Role selection */
.role-selector {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-top: 5px;
}

.role-option {
    position: relative;
}

.role-option input[type="radio"] {
    display: none;
}

.role-option label {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 15px 10px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #f9fafb;
    font-weight: 500;
    font-size: 14px;
    color: #374151;
}

.role-option label i {
    font-size: 24px;
    color: #9ca3af;
    transition: all 0.3s ease;
}

.role-option input[type="radio"]:checked + label {
    border-color: #00A651;
    background: rgba(0, 166, 81, 0.05);
    color: #00A651;
}

.role-option input[type="radio"]:checked + label i {
    color: #00A651;
}

.role-option label:hover {
    border-color: #00A651;
    background: rgba(0, 166, 81, 0.03);
}

/* Terms */
.terms-group {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin: 20px 0;
}

.terms-group input[type="checkbox"] {
    width: 20px;
    height: 20px;
    margin-top: 2px;
    accent-color: #00A651;
    cursor: pointer;
    flex-shrink: 0;
}

.terms-group label {
    font-size: 14px;
    color: #6b7280;
    cursor: pointer;
    line-height: 1.5;
}

.terms-group label a {
    color: #00A651;
    font-weight: 600;
    text-decoration: none;
}

.terms-group label a:hover {
    text-decoration: underline;
}

.terms-group .error-message {
    display: block;
    margin-top: 5px;
}

.btn-register {
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
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.btn-register:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 40px rgba(0, 166, 81, 0.3);
}

.btn-register:active {
    transform: translateY(0);
}

.btn-register i {
    font-size: 20px;
}

.btn-register.loading {
    opacity: 0.7;
    cursor: not-allowed;
}

.btn-register .spinner {
    display: none;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

.btn-register.loading .spinner {
    display: inline-block;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.register-footer {
    text-align: center;
    margin-top: 25px;
    color: #6b7280;
    font-size: 15px;
}

.register-footer a {
    color: #00A651;
    font-weight: 600;
    text-decoration: none;
}

.register-footer a:hover {
    text-decoration: underline;
}

.alert {
    padding: 14px 18px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
}

.alert-danger {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #dc2626;
}

.alert-danger i {
    font-size: 20px;
    color: #dc2626;
}

.alert-success {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #16a34a;
}

.alert-success i {
    font-size: 20px;
    color: #16a34a;
}

.flash-message {
    animation: slideDown 0.5s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Dark mode */
.dark-mode .register-section {
    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
}

.dark-mode .register-card {
    background: #2a2a2a;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
}

.dark-mode .register-header h1 {
    color: white;
}

.dark-mode .register-header p {
    color: #b0b0b0;
}

.dark-mode .form-group label {
    color: #d0d0d0;
}

.dark-mode .form-group .input-wrapper input,
.dark-mode .form-group .input-wrapper select {
    background: #1a1a1a;
    border-color: #444;
    color: white;
}

.dark-mode .form-group .input-wrapper input:focus,
.dark-mode .form-group .input-wrapper select:focus {
    border-color: #00A651;
    background: #2a2a2a;
}

.dark-mode .role-option label {
    background: #1a1a1a;
    border-color: #444;
    color: #b0b0b0;
}

.dark-mode .role-option input[type="radio"]:checked + label {
    border-color: #00A651;
    background: rgba(0, 166, 81, 0.1);
    color: #00A651;
}

.dark-mode .terms-group label {
    color: #b0b0b0;
}

.dark-mode .register-footer {
    color: #b0b0b0;
}

.dark-mode .alert-danger {
    background: #331a1a;
    border-color: #442222;
    color: #ef4444;
}

.dark-mode .alert-success {
    background: #1a3320;
    border-color: #224433;
    color: #22c55e;
}

/* Responsive */
@media (max-width: 768px) {
    .register-card {
        padding: 35px 25px;
        margin: 0 15px;
    }
    
    .register-header h1 {
        font-size: 24px;
    }
    
    .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }
    
    .role-selector {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 480px) {
    .register-card {
        padding: 25px 20px;
    }
    
    .register-header .icon {
        width: 60px;
        height: 60px;
        font-size: 24px;
    }
    
    .btn-register {
        font-size: 16px;
        padding: 14px;
    }
    
    .role-selector {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- ============================================= -->
<!-- SECTION INSCRIPTION -->
<!-- ============================================= -->
<section class="register-section">
    <div class="container">
        <div class="register-card animate-on-scroll">
            <div class="register-header">
                <div class="icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h1>Créer un compte</h1>
                <p>Rejoignez DoriExpress-Pro en quelques clics</p>
            </div>
            
            <!-- Affichage des messages -->
            <?php if ($error): ?>
                <div class="alert alert-danger flash-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success flash-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <!-- Formulaire -->
            <form method="POST" action="" id="register-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <!-- Nom et Prénom -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="nom">Nom <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-user"></i>
                            <input type="text" 
                                   id="nom" 
                                   name="nom" 
                                   value="<?php echo htmlspecialchars($form_data['nom']); ?>" 
                                   placeholder="Votre nom" 
                                   required>
                        </div>
                        <?php if (isset($errors['nom'])): ?>
                            <span class="error-message"><?php echo $errors['nom']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="prenom">Prénom <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-user"></i>
                            <input type="text" 
                                   id="prenom" 
                                   name="prenom" 
                                   value="<?php echo htmlspecialchars($form_data['prenom']); ?>" 
                                   placeholder="Votre prénom" 
                                   required>
                        </div>
                        <?php if (isset($errors['prenom'])): ?>
                            <span class="error-message"><?php echo $errors['prenom']; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Email -->
                <div class="form-group">
                    <label for="email">Adresse email <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               value="<?php echo htmlspecialchars($form_data['email']); ?>" 
                               placeholder="exemple@email.com" 
                               required>
                    </div>
                    <?php if (isset($errors['email'])): ?>
                        <span class="error-message"><?php echo $errors['email']; ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Téléphone -->
                <div class="form-group">
                    <label for="telephone">Numéro de téléphone <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-phone"></i>
                        <input type="tel" 
                               id="telephone" 
                               name="telephone" 
                               value="<?php echo htmlspecialchars($form_data['telephone']); ?>" 
                               placeholder="70XXXXXX" 
                               required>
                    </div>
                    <?php if (isset($errors['telephone'])): ?>
                        <span class="error-message"><?php echo $errors['telephone']; ?></span>
                    <?php endif; ?>
                    <span class="help-text">Format : 70XXXXXX, 71XXXXXX, etc.</span>
                </div>
                
                <!-- Mot de passe -->
                <div class="form-group">
                    <label for="password">Mot de passe <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               placeholder="8 caractères minimum" 
                               required>
                        <button type="button" class="toggle-password" onclick="togglePassword('password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <?php if (isset($errors['password'])): ?>
                        <span class="error-message"><?php echo $errors['password']; ?></span>
                    <?php endif; ?>
                    
                    <!-- Barre de force du mot de passe -->
                    <div class="password-strength" id="password-strength">
                        <div class="bars">
                            <div class="bar" data-index="0"></div>
                            <div class="bar" data-index="1"></div>
                            <div class="bar" data-index="2"></div>
                            <div class="bar" data-index="3"></div>
                            <div class="bar" data-index="4"></div>
                        </div>
                        <span class="label" id="strength-label">-</span>
                    </div>
                </div>
                
                <!-- Confirmation mot de passe -->
                <div class="form-group">
                    <label for="password_confirm">Confirmer le mot de passe <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" 
                               id="password_confirm" 
                               name="password_confirm" 
                               placeholder="Confirmez votre mot de passe" 
                               required>
                        <button type="button" class="toggle-password" onclick="togglePassword('password_confirm')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <?php if (isset($errors['password_confirm'])): ?>
                        <span class="error-message"><?php echo $errors['password_confirm']; ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Rôle -->
                <div class="form-group">
                    <label>Je suis <span class="required">*</span></label>
                    <div class="role-selector">
                        <div class="role-option">
                            <input type="radio" id="role_client" name="role" value="client" 
                                   <?php echo $form_data['role'] === 'client' ? 'checked' : ''; ?>>
                            <label for="role_client">
                                <i class="fas fa-user"></i>
                                <span>Client</span>
                            </label>
                        </div>
                        <div class="role-option">
                            <input type="radio" id="role_livreur" name="role" value="livreur"
                                   <?php echo $form_data['role'] === 'livreur' ? 'checked' : ''; ?>>
                            <label for="role_livreur">
                                <i class="fas fa-truck"></i>
                                <span>Livreur</span>
                            </label>
                        </div>
                        <div class="role-option">
                            <input type="radio" id="role_partenaire" name="role" value="partenaire"
                                   <?php echo $form_data['role'] === 'partenaire' ? 'checked' : ''; ?>>
                            <label for="role_partenaire">
                                <i class="fas fa-store"></i>
                                <span>Partenaire</span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Conditions -->
                <div class="terms-group">
                    <input type="checkbox" id="accept_terms" name="accept_terms" 
                           <?php echo $form_data['accept_terms'] ? 'checked' : ''; ?>>
                    <label for="accept_terms">
                        J'accepte les 
                        <a href="<?php echo URL_BASE; ?>conditions.php" target="_blank">conditions d'utilisation</a> 
                        et la 
                        <a href="<?php echo URL_BASE; ?>politique.php" target="_blank">politique de confidentialité</a>.
                    </label>
                </div>
                <?php if (isset($errors['accept_terms'])): ?>
                    <span class="error-message" style="color: #ef4444; font-size: 13px; display: block; margin-top: -10px; margin-bottom: 15px;">
                        <?php echo $errors['accept_terms']; ?>
                    </span>
                <?php endif; ?>
                
                <!-- Bouton inscription -->
                <button type="submit" class="btn-register" id="register-btn">
                    <span class="spinner"></span>
                    <i class="fas fa-user-plus"></i>
                    Créer mon compte
                </button>
            </form>
            
            <!-- Footer -->
            <div class="register-footer">
                Déjà un compte ? 
                <a href="<?php echo URL_BASE; ?>login.php">Se connecter</a>
            </div>
        </div>
    </div>
</section>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// TOGGLE PASSWORD
// =============================================
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.parentElement.querySelector('.toggle-password i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// =============================================
// PASSWORD STRENGTH
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    const bars = document.querySelectorAll('#password-strength .bar');
    const label = document.getElementById('strength-label');
    
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        const strength = checkPasswordStrength(password);
        
        // Mettre à jour les barres
        bars.forEach((bar, index) => {
            bar.classList.toggle('active', index < strength.score);
        });
        
        // Mettre à jour le label
        label.textContent = strength.label;
        label.className = 'label ' + strength.className;
    });
});

function checkPasswordStrength(password) {
    let score = 0;
    let className = 'weak';
    let label = 'Très faible';
    
    // Longueur
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    
    // Majuscules
    if (/[A-Z]/.test(password)) score++;
    
    // Minuscules
    if (/[a-z]/.test(password)) score++;
    
    // Chiffres
    if (/[0-9]/.test(password)) score++;
    
    // Caractères spéciaux
    if (/[^A-Za-z0-9]/.test(password)) score++;
    
    // Score max = 7
    if (score >= 6) { className = 'strong'; label = 'Très fort'; }
    else if (score >= 4) { className = 'strong'; label = 'Fort'; }
    else if (score >= 3) { className = 'medium'; label = 'Moyen'; }
    else if (score >= 2) { className = 'weak'; label = 'Faible'; }
    else { className = 'weak'; label = 'Très faible'; }
    
    return { score, className, label };
}

// =============================================
// VALIDATION DU FORMULAIRE
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('register-form');
    const btn = document.getElementById('register-btn');
    
    form.addEventListener('submit', function(e) {
        const nom = document.getElementById('nom').value.trim();
        const prenom = document.getElementById('prenom').value.trim();
        const email = document.getElementById('email').value.trim();
        const telephone = document.getElementById('telephone').value.trim();
        const password = document.getElementById('password').value;
        const passwordConfirm = document.getElementById('password_confirm').value;
        const acceptTerms = document.getElementById('accept_terms').checked;
        
        let hasError = false;
        
        // Validation nom
        if (nom.length < 2) {
            showError('nom', 'Le nom doit contenir au moins 2 caractères.');
            hasError = true;
        } else {
            clearError('nom');
        }
        
        // Validation prénom
        if (prenom.length < 2) {
            showError('prenom', 'Le prénom doit contenir au moins 2 caractères.');
            hasError = true;
        } else {
            clearError('prenom');
        }
        
        // Validation email
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showError('email', 'Veuillez saisir une adresse email valide.');
            hasError = true;
        } else {
            clearError('email');
        }
        
        // Validation téléphone
        if (!telephone || !/^[0-9]{8}$/.test(telephone.replace(/[^0-9]/g, ''))) {
            showError('telephone', 'Veuillez saisir un numéro valide (8 chiffres).');
            hasError = true;
        } else {
            clearError('telephone');
        }
        
        // Validation mot de passe
        if (password.length < 8) {
            showError('password', 'Le mot de passe doit contenir au moins 8 caractères.');
            hasError = true;
        } else {
            clearError('password');
        }
        
        // Validation confirmation
        if (password !== passwordConfirm) {
            showError('password_confirm', 'Les mots de passe ne correspondent pas.');
            hasError = true;
        } else {
            clearError('password_confirm');
        }
        
        // Validation conditions
        if (!acceptTerms) {
            document.querySelector('.terms-group').style.border = '1px solid #ef4444';
            document.querySelector('.terms-group').style.borderRadius = '8px';
            document.querySelector('.terms-group').style.padding = '10px';
            hasError = true;
        } else {
            document.querySelector('.terms-group').style.border = 'none';
            document.querySelector('.terms-group').style.padding = '0';
        }
        
        if (hasError) {
            e.preventDefault();
            return;
        }
        
        // Afficher le loader
        btn.classList.add('loading');
        btn.querySelector('i').style.display = 'none';
    });
});

function showError(fieldId, message) {
    const field = document.getElementById(fieldId);
    field.classList.add('error');
    
    let errorDiv = field.parentElement.parentElement.querySelector('.error-message');
    if (!errorDiv) {
        errorDiv = document.createElement('span');
        errorDiv.className = 'error-message';
        field.parentElement.parentElement.appendChild(errorDiv);
    }
    errorDiv.textContent = message;
}

function clearError(fieldId) {
    const field = document.getElementById(fieldId);
    field.classList.remove('error');
    
    const errorDiv = field.parentElement.parentElement.querySelector('.error-message');
    if (errorDiv) {
        errorDiv.remove();
    }
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
    }, { threshold: 0.1 });
    
    elements.forEach(el => observer.observe(el));
});

console.log('✅ DoriExpress-Pro - Page d\'inscription chargée');
</script>

<style>
.animate-on-scroll {
    opacity: 0;
    transform: translateY(30px);
    transition: all 0.8s ease;
}

.animate-on-scroll.animated {
    opacity: 1;
    transform: translateY(0);
}
</style>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';
?>