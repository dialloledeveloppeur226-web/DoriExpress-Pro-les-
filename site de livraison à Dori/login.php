<?php
/**
 * =============================================
 * PAGE DE CONNEXION - DoriExpress-Pro
 * =============================================
 * Fichier : login.php
 * Rôle : Connexion utilisateur avec sécurité premium
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
$page_title = 'Connexion - DoriExpress-Pro';
$page_description = 'Connectez-vous à votre compte DoriExpress-Pro pour gérer vos commandes et livraisons.';
$page_keywords = 'connexion, DoriExpress, compte, livraison, Dori';

// Rediriger si déjà connecté
if (est_connecte()) {
    $user = utilisateur_connecte();
    $role = $user['role'] ?? 'client';
    $redirects = [
        'createur' => 'admin/createur.php',
        'admin' => 'admin/dashboard.php',
        'client' => 'client/dashboard.php',
        'livreur' => 'livreur/dashboard.php',
        'partenaire' => 'partenaire/dashboard.php'
    ];
    $redirect = isset($redirects[$role]) ? $redirects[$role] : 'index.php';
    header('Location: ' . URL_BASE . $redirect);
    exit;
}

// Traitement du formulaire
$error = '';
$success = '';
$email = '';
$remember = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifier_token_csrf($csrf_token)) {
        $error = 'Erreur de sécurité. Veuillez rafraîchir la page.';
    } else {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        if (empty($email) || empty($password)) {
            $error = 'Veuillez remplir tous les champs.';
        } else {
            // Tentative de connexion
            $result = Auth::login($email, $password);
            
            if ($result['success']) {
                if (isset($result['require_2fa']) && $result['require_2fa']) {
                    // Rediriger vers la vérification 2FA
                    $_SESSION['2fa_user_id'] = $result['user_id'];
                    header('Location: ' . URL_BASE . 'verify-2fa.php');
                    exit;
                }
                
                // Connexion réussie
                $user = utilisateur_connecte();
                $role = $user['role'] ?? 'client';
                $redirects = [
                    'createur' => 'admin/createur.php',
                    'admin' => 'admin/dashboard.php',
                    'client' => 'client/dashboard.php',
                    'livreur' => 'livreur/dashboard.php',
                    'partenaire' => 'partenaire/dashboard.php'
                ];
                $redirect = isset($redirects[$role]) ? $redirects[$role] : 'index.php';
                
                // Redirection vers la page demandée ou le dashboard
                $redirect_to = $_GET['redirect'] ?? $redirect;
                header('Location: ' . URL_BASE . $redirect_to);
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Générer un token CSRF
$csrf_token = generer_token_csrf();

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* Styles spécifiques à la page de connexion */
.login-section {
    min-height: 80vh;
    display: flex;
    align-items: center;
    padding: 60px 0;
    background: linear-gradient(135deg, #f8fafc 0%, #e5e7eb 100%);
}

.login-card {
    background: white;
    border-radius: 24px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.1);
    padding: 50px 45px;
    max-width: 450px;
    margin: 0 auto;
    transition: all 0.3s ease;
}

.login-card:hover {
    box-shadow: 0 30px 80px rgba(0,0,0,0.15);
}

.login-header {
    text-align: center;
    margin-bottom: 35px;
}

.login-header .icon {
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

.login-header h1 {
    font-size: 28px;
    font-weight: 800;
    color: #1a1a1a;
    margin-bottom: 8px;
}

.login-header p {
    color: #6b7280;
    font-size: 15px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-weight: 600;
    font-size: 14px;
    color: #374151;
    margin-bottom: 6px;
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

.form-group .input-wrapper input {
    width: 100%;
    padding: 14px 14px 14px 45px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 16px;
    transition: all 0.3s ease;
    background: #f9fafb;
    color: #1a1a1a;
}

.form-group .input-wrapper input:focus {
    border-color: #00A651;
    background: white;
    box-shadow: 0 0 0 4px rgba(0, 166, 81, 0.15);
    outline: none;
}

.form-group .input-wrapper input.error {
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

.form-options {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.form-options .remember-me {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #6b7280;
    cursor: pointer;
}

.form-options .remember-me input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #00A651;
    cursor: pointer;
}

.form-options .forgot-link {
    font-size: 14px;
    color: #00A651;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.form-options .forgot-link:hover {
    color: #008a44;
    text-decoration: underline;
}

.btn-login {
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

.btn-login:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 40px rgba(0, 166, 81, 0.3);
}

.btn-login:active {
    transform: translateY(0);
}

.btn-login i {
    font-size: 20px;
}

.btn-login.loading {
    opacity: 0.7;
    cursor: not-allowed;
}

.btn-login .spinner {
    display: none;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

.btn-login.loading .spinner {
    display: inline-block;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.login-divider {
    text-align: center;
    margin: 25px 0;
    position: relative;
}

.login-divider::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background: #e5e7eb;
}

.login-divider span {
    background: white;
    padding: 0 15px;
    position: relative;
    color: #9ca3af;
    font-size: 14px;
}

.btn-social {
    width: 100%;
    padding: 14px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    background: white;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    color: #374151;
}

.btn-social:hover {
    border-color: #00A651;
    background: #f8fafc;
}

.btn-social.google {
    color: #ea4335;
}
.btn-social.google:hover {
    border-color: #ea4335;
    background: #fef2f2;
}

.btn-social.facebook {
    color: #1877f2;
}
.btn-social.facebook:hover {
    border-color: #1877f2;
    background: #f0f4ff;
}

.login-footer {
    text-align: center;
    margin-top: 25px;
    color: #6b7280;
    font-size: 15px;
}

.login-footer a {
    color: #00A651;
    font-weight: 600;
    text-decoration: none;
}

.login-footer a:hover {
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

/* Messages flash */
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
.dark-mode .login-section {
    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
}

.dark-mode .login-card {
    background: #2a2a2a;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
}

.dark-mode .login-header h1 {
    color: white;
}

.dark-mode .login-header p {
    color: #b0b0b0;
}

.dark-mode .form-group label {
    color: #d0d0d0;
}

.dark-mode .form-group .input-wrapper input {
    background: #1a1a1a;
    border-color: #444;
    color: white;
}

.dark-mode .form-group .input-wrapper input:focus {
    border-color: #00A651;
    background: #2a2a2a;
}

.dark-mode .form-options .remember-me {
    color: #b0b0b0;
}

.dark-mode .login-divider span {
    background: #2a2a2a;
    color: #6b7280;
}

.dark-mode .btn-social {
    background: #1a1a1a;
    border-color: #444;
    color: #d0d0d0;
}

.dark-mode .btn-social:hover {
    border-color: #00A651;
    background: #2a2a2a;
}

.dark-mode .login-footer {
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
    .login-card {
        padding: 35px 25px;
        margin: 0 15px;
    }
    
    .login-header h1 {
        font-size: 24px;
    }
    
    .form-options {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
}

@media (max-width: 480px) {
    .login-card {
        padding: 25px 20px;
    }
    
    .login-header .icon {
        width: 60px;
        height: 60px;
        font-size: 24px;
    }
    
    .btn-login {
        font-size: 16px;
        padding: 14px;
    }
}
</style>

<!-- ============================================= -->
<!-- SECTION CONNEXION -->
<!-- ============================================= -->
<section class="login-section">
    <div class="container">
        <div class="login-card animate-on-scroll">
            <div class="login-header">
                <div class="icon">
                    <i class="fas fa-lock"></i>
                </div>
                <h1>Connexion</h1>
                <p>Connectez-vous à votre compte DoriExpress-Pro</p>
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
            <form method="POST" action="" id="login-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <!-- Email -->
                <div class="form-group">
                    <label for="email">Adresse email ou téléphone</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="text" 
                               id="email" 
                               name="email" 
                               value="<?php echo htmlspecialchars($email); ?>" 
                               placeholder="exemple@email.com ou 70XXXXXX" 
                               required
                               autofocus>
                    </div>
                </div>
                
                <!-- Mot de passe -->
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               placeholder="Votre mot de passe" 
                               required>
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Options -->
                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" <?php echo $remember ? 'checked' : ''; ?>>
                        Se souvenir de moi
                    </label>
                    <a href="<?php echo URL_BASE; ?>mot-de-passe-oublie.php" class="forgot-link">
                        Mot de passe oublié ?
                    </a>
                </div>
                
                <!-- Bouton connexion -->
                <button type="submit" class="btn-login" id="login-btn">
                    <span class="spinner"></span>
                    <i class="fas fa-sign-in-alt"></i>
                    Se connecter
                </button>
            </form>
            
            <!-- Séparateur -->
            <div class="login-divider">
                <span>ou</span>
            </div>
            
            <!-- Connexion sociale (préparée pour future intégration) -->
            <!--
            <button class="btn-social google">
                <i class="fab fa-google"></i> Continuer avec Google
            </button>
            <button class="btn-social facebook" style="margin-top: 10px;">
                <i class="fab fa-facebook"></i> Continuer avec Facebook
            </button>
            -->
            
            <!-- Footer -->
            <div class="login-footer">
                Pas encore de compte ? 
                <a href="<?php echo URL_BASE; ?>register.php">S'inscrire</a>
                <br>
                <small style="color: #9ca3af; font-size: 13px;">
                    <i class="fas fa-shield-alt"></i> Connexion sécurisée
                </small>
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
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const icon = document.querySelector('.toggle-password i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// =============================================
// VALIDATION DU FORMULAIRE
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('login-form');
    const btn = document.getElementById('login-btn');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    
    form.addEventListener('submit', function(e) {
        // Validation email/telephone
        const email = emailInput.value.trim();
        const password = passwordInput.value.trim();
        
        let hasError = false;
        
        if (!email) {
            showError(emailInput, 'Veuillez saisir votre email ou téléphone');
            hasError = true;
        } else {
            clearError(emailInput);
        }
        
        if (!password) {
            showError(passwordInput, 'Veuillez saisir votre mot de passe');
            hasError = true;
        } else {
            clearError(passwordInput);
        }
        
        if (hasError) {
            e.preventDefault();
            return;
        }
        
        // Afficher le loader
        btn.classList.add('loading');
        btn.querySelector('i').style.display = 'none';
    });
    
    // Nettoyer les erreurs au focus
    emailInput.addEventListener('focus', function() { clearError(this); });
    passwordInput.addEventListener('focus', function() { clearError(this); });
});

// =============================================
// FONCTIONS D'ERREUR
// =============================================
function showError(input, message) {
    input.classList.add('error');
    const wrapper = input.closest('.input-wrapper');
    let errorDiv = wrapper.querySelector('.error-message');
    
    if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.style.cssText = 'color: #ef4444; font-size: 13px; margin-top: 5px;';
        wrapper.parentNode.appendChild(errorDiv);
    }
    
    errorDiv.textContent = message;
}

function clearError(input) {
    input.classList.remove('error');
    const wrapper = input.closest('.input-wrapper');
    const errorDiv = wrapper.parentNode.querySelector('.error-message');
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

console.log('✅ DoriExpress-Pro - Page de connexion chargée');
</script>

<style>
.error-message {
    animation: slideDown 0.3s ease;
}

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