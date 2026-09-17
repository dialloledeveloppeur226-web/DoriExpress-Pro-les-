<?php
/**
 * =============================================
 * PAGE DE CONTACT - DoriExpress-Pro
 * =============================================
 * Fichier : contact.php
 * Rôle : Formulaire de contact avec support multi-canal
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
$page_title = 'Contact - DoriExpress-Pro';
$page_description = 'Contactez DoriExpress-Pro : support, réclamations, demandes d\'information.';
$page_keywords = 'contact, support, DoriExpress, assistance, Dori';

// Récupérer les paramètres
$telephone = get_parametre('telephone', '61874528');
$whatsapp = get_parametre('whatsapp', '61874528');
$email = get_parametre('email', 'contact@doriexpress.bf');
$adresse = get_parametre('adresse', 'Dori, Burkina Faso');
$nom_site = get_parametre('nom_site', 'DoriExpress-Pro');

// Traitement du formulaire
$error = '';
$success = '';
$form_data = [
    'nom' => '',
    'email' => '',
    'telephone' => '',
    'sujet' => '',
    'message' => '',
    'type' => 'general'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'envoyer_message') {
    // Vérifier le token CSRF
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifier_token_csrf($csrf_token)) {
        $error = 'Erreur de sécurité. Veuillez rafraîchir la page.';
    } else {
        // Récupérer les données
        $form_data['nom'] = trim($_POST['nom'] ?? '');
        $form_data['email'] = trim($_POST['email'] ?? '');
        $form_data['telephone'] = trim($_POST['telephone'] ?? '');
        $form_data['sujet'] = trim($_POST['sujet'] ?? '');
        $form_data['message'] = trim($_POST['message'] ?? '');
        $form_data['type'] = $_POST['type'] ?? 'general';
        
        // Validation
        $errors = [];
        
        if (empty($form_data['nom'])) {
            $errors[] = 'Veuillez saisir votre nom.';
        }
        
        if (empty($form_data['email']) || !valider_email($form_data['email'])) {
            $errors[] = 'Veuillez saisir une adresse email valide.';
        }
        
        if (empty($form_data['sujet'])) {
            $errors[] = 'Veuillez saisir un sujet.';
        }
        
        if (empty($form_data['message'])) {
            $errors[] = 'Veuillez saisir votre message.';
        }
        
        // Si pas d'erreurs
        if (empty($errors)) {
            try {
                $db = Database::getInstance();
                
                // Créer un ticket de support
                $db->query(
                    "INSERT INTO tickets_support (
                        utilisateur_id, categorie, sujet, description, statut, date_creation
                    ) VALUES (?, ?, ?, ?, 'nouveau', NOW())",
                    [
                        est_connecte() ? $_SESSION['user_id'] : null,
                        $form_data['type'],
                        $form_data['sujet'],
                        $form_data['message']
                    ]
                );
                
                $ticket_id = $db->lastInsertId();
                
                // Journaliser
                journaliser(
                    est_connecte() ? $_SESSION['user_id'] : null,
                    'contact_message',
                    'support',
                    [
                        'ticket_id' => $ticket_id,
                        'email' => $form_data['email'],
                        'type' => $form_data['type']
                    ]
                );
                
                // Notifier le créateur
                ajouter_notification_createur(
                    'Nouveau message de contact',
                    "{$form_data['nom']} - {$form_data['sujet']}",
                    'support',
                    URL_BASE . 'admin/support.php?ticket=' . $ticket_id
                );
                
                // Envoyer un accusé de réception par email
                // $this->sendConfirmationEmail($form_data['email'], $ticket_id);
                
                $success = '✅ Votre message a été envoyé avec succès ! Nous vous répondrons dans les plus brefs délais.';
                
                // Réinitialiser le formulaire
                $form_data = [
                    'nom' => '',
                    'email' => '',
                    'telephone' => '',
                    'sujet' => '',
                    'message' => '',
                    'type' => 'general'
                ];
                
            } catch (Exception $e) {
                $error = 'Une erreur est survenue. Veuillez réessayer.';
                journaliser(null, 'contact_error', 'support', ['error' => $e->getMessage()]);
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}

// Récupérer les types de demande
$types_demande = [
    'general' => '💬 Demande générale',
    'commande' => '📦 Problème de commande',
    'paiement' => '💰 Problème de paiement',
    'livreur' => '🚚 Problème avec un livreur',
    'partenaire' => '🏪 Devenir partenaire',
    'reclamation' => '📝 Réclamation',
    'autre' => '📌 Autre'
];

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE CONTACT
 * ============================================= */
.page-contact {
    background: #f8fafc;
    min-height: 100vh;
    padding: 30px 0 60px;
}

.contact-header {
    text-align: center;
    margin-bottom: 30px;
}

.contact-header h1 {
    font-size: 32px;
    font-weight: 800;
    color: #1a1a1a;
}

.contact-header p {
    color: #6b7280;
    font-size: 16px;
}

/* Contact Cards */
.contact-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.contact-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    text-align: center;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}

.contact-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}

.contact-card .contact-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    margin: 0 auto 15px;
}

.contact-card .contact-icon.green { background: rgba(0, 166, 81, 0.1); color: #00A651; }
.contact-card .contact-icon.blue { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
.contact-card .contact-icon.whatsapp { background: rgba(37, 211, 102, 0.1); color: #25D366; }
.contact-card .contact-icon.orange { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }

.contact-card .contact-title {
    font-weight: 700;
    font-size: 16px;
    color: #1a1a1a;
}

.contact-card .contact-value {
    font-size: 14px;
    color: #6b7280;
    margin-top: 4px;
}

.contact-card .contact-link {
    color: #00A651;
    font-weight: 600;
    text-decoration: none;
}

.contact-card .contact-link:hover {
    text-decoration: underline;
}

/* Form */
.contact-form {
    background: white;
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border: 1px solid #e5e7eb;
    max-width: 700px;
    margin: 0 auto;
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
    min-height: 120px;
    resize: vertical;
}

.form-group .help-text {
    font-size: 13px;
    color: #6b7280;
    margin-top: 4px;
}

.btn-envoyer {
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

.btn-envoyer:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 40px rgba(0, 166, 81, 0.3);
}

.btn-envoyer:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

/* Map */
.map-container {
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    margin-top: 30px;
}

.map-container iframe {
    width: 100%;
    height: 300px;
    border: none;
}

/* Responsive */
@media (max-width: 768px) {
    .contact-form {
        padding: 20px;
        margin: 0 10px;
    }
    .contact-header h1 {
        font-size: 24px;
    }
    .contact-cards {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 480px) {
    .contact-cards {
        grid-template-columns: 1fr;
    }
}

/* Dark Mode */
.dark-mode .page-contact {
    background: #121212;
}

.dark-mode .contact-form,
.dark-mode .contact-card {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .contact-header h1 {
    color: #e5e5e5;
}

.dark-mode .contact-card .contact-title {
    color: #e5e5e5;
}

.dark-mode .contact-card .contact-value {
    color: #a0a0a0;
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

.dark-mode .map-container {
    border-color: #333;
}
</style>

<!-- ============================================= -->
<!-- PAGE CONTACT -->
<!-- ============================================= -->
<div class="page-contact">
    <div class="container">
        
        <!-- Header -->
        <div class="contact-header">
            <h1>📞 Contactez-nous</h1>
            <p>Une question ? Un problème ? Nous sommes là pour vous aider.</p>
        </div>
        
        <!-- Contact Cards -->
        <div class="contact-cards">
            <div class="contact-card animate-on-scroll">
                <div class="contact-icon green"><i class="fas fa-phone"></i></div>
                <div class="contact-title">Téléphone</div>
                <div class="contact-value">
                    <a href="tel:+226<?php echo $telephone; ?>" class="contact-link"><?php echo $telephone; ?></a>
                </div>
                <div style="font-size:12px; color:#9ca3af; margin-top:4px;">Lun-Sam 7h-22h</div>
            </div>
            
            <div class="contact-card animate-on-scroll">
                <div class="contact-icon whatsapp"><i class="fab fa-whatsapp"></i></div>
                <div class="contact-title">WhatsApp</div>
                <div class="contact-value">
                    <a href="https://wa.me/226<?php echo $whatsapp; ?>" target="_blank" class="contact-link"><?php echo $whatsapp; ?></a>
                </div>
                <div style="font-size:12px; color:#9ca3af; margin-top:4px;">Réponse rapide</div>
            </div>
            
            <div class="contact-card animate-on-scroll">
                <div class="contact-icon blue"><i class="fas fa-envelope"></i></div>
                <div class="contact-title">Email</div>
                <div class="contact-value">
                    <a href="mailto:<?php echo $email; ?>" class="contact-link"><?php echo $email; ?></a>
                </div>
                <div style="font-size:12px; color:#9ca3af; margin-top:4px;">Réponse sous 24h</div>
            </div>
            
            <div class="contact-card animate-on-scroll">
                <div class="contact-icon orange"><i class="fas fa-map-marker-alt"></i></div>
                <div class="contact-title">Adresse</div>
                <div class="contact-value"><?php echo $adresse; ?></div>
                <div style="font-size:12px; color:#9ca3af; margin-top:4px;">Burkina Faso</div>
            </div>
        </div>
        
        <!-- Formulaire -->
        <div class="contact-form animate-on-scroll">
            <h3 style="font-size:20px; font-weight:700; color:#1a1a1a; margin-bottom:20px;">
                <i class="fas fa-paper-plane" style="color:#00A651;"></i> Envoyez-nous un message
            </h3>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="contact-form">
                <input type="hidden" name="action" value="envoyer_message">
                <input type="hidden" name="csrf_token" value="<?php echo generer_token_csrf(); ?>">
                
                <!-- Nom -->
                <div class="form-group">
                    <label for="nom">Nom complet <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" id="nom" name="nom" placeholder="Votre nom" required
                               value="<?php echo htmlspecialchars($form_data['nom'] ?? ($is_logged_in ? $user['nom'] . ' ' . $user['prenom'] : '')); ?>">
                    </div>
                </div>
                
                <!-- Email et Téléphone -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label for="email">Email <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" placeholder="exemple@email.com" required
                                   value="<?php echo htmlspecialchars($form_data['email'] ?? ($is_logged_in ? $user['email'] : '')); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="telephone">Téléphone</label>
                        <div class="input-wrapper">
                            <i class="fas fa-phone"></i>
                            <input type="tel" id="telephone" name="telephone" placeholder="70XXXXXX"
                                   value="<?php echo htmlspecialchars($form_data['telephone'] ?? ($is_logged_in ? $user['telephone'] : '')); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Type et Sujet -->
                <div style="display:grid; grid-template-columns:1fr 2fr; gap:15px;">
                    <div class="form-group">
                        <label for="type">Type de demande <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-tag"></i>
                            <select id="type" name="type" required>
                                <?php foreach ($types_demande as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo isset($form_data['type']) && $form_data['type'] == $key ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="sujet">Sujet <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-heading"></i>
                            <input type="text" id="sujet" name="sujet" placeholder="Sujet de votre message" required
                                   value="<?php echo htmlspecialchars($form_data['sujet'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Message -->
                <div class="form-group">
                    <label for="message">Message <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-pen"></i>
                        <textarea id="message" name="message" placeholder="Décrivez votre demande en détail..." required><?php echo htmlspecialchars($form_data['message'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <!-- Bouton -->
                <button type="submit" class="btn-envoyer" id="btn-envoyer">
                    <i class="fas fa-paper-plane"></i> Envoyer le message
                </button>
            </form>
        </div>
        
        <!-- Carte -->
        <div class="map-container animate-on-scroll">
            <iframe 
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3924.123456789!2d-0.0330!3d14.0330!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMTTCsDAxJzU4LjgiTiAwwrAwMCcwMC4wIlc!5e0!3m2!1sfr!2sbf!4v1234567890" 
                allowfullscreen="" 
                loading="lazy" 
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// VALIDATION FORMULAIRE
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('contact-form');
    const btn = document.getElementById('btn-envoyer');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            const nom = document.getElementById('nom').value.trim();
            const email = document.getElementById('email').value.trim();
            const sujet = document.getElementById('sujet').value.trim();
            const message = document.getElementById('message').value.trim();
            
            let hasError = false;
            
            if (!nom) {
                showFieldError('nom', 'Veuillez saisir votre nom.');
                hasError = true;
            }
            
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showFieldError('email', 'Veuillez saisir une adresse email valide.');
                hasError = true;
            }
            
            if (!sujet) {
                showFieldError('sujet', 'Veuillez saisir un sujet.');
                hasError = true;
            }
            
            if (!message) {
                showFieldError('message', 'Veuillez saisir votre message.');
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
    
    // Nettoyer l'erreur au focus
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

console.log('✅ DoriExpress-Pro - Page contact chargée');
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

.dark-mode .contact-form h3 {
    color: #e5e5e5 !important;
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
// FIN DU FICHIER CONTACT.PHP
// =============================================
?>