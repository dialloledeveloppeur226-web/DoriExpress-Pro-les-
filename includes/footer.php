<?php
/**
 * =============================================
 * FOOTER COMMUN - DoriExpress-Pro
 * =============================================
 * Fichier : includes/footer.php
 * Rôle : Pied de page commun à toutes les pages
 * Utilisation : require_once 'includes/footer.php';
 * =============================================
 */

// Empêcher l'accès direct
if (!defined('DOSSIER_RACINE')) {
    die('Accès direct interdit');
}

// Récupérer les paramètres
$nom_site = get_parametre('nom_site', 'DoriExpress-Pro');
$slogan = get_parametre('slogan', 'Votre livraison rapide à Dori');
$description = get_parametre('description', 'Plateforme de livraison professionnelle à Dori');
$adresse = get_parametre('adresse', 'Dori, Burkina Faso');
$telephone = get_parametre('telephone', '61874528');
$whatsapp = get_parametre('whatsapp', '61874528');
$email = get_parametre('email', 'contact@doriexpress.bf');
$logo = get_parametre('logo', 'logo.png');
$couleur_primaire = get_parametre('couleur_primaire', '#00A651');

// Réseaux sociaux (paramètres configurables)
$facebook = get_parametre('facebook', '#');
$instagram = get_parametre('instagram', '#');
$tiktok = get_parametre('tiktok', '#');
$youtube = get_parametre('youtube', '#');

// Année en cours
$annee = date('Y');
?>
</main> <!-- Fermeture du main ouvert dans header.php -->

<!-- ============================================= -->
<!-- SECTION NEWSLETTER -->
<!-- ============================================= -->
<section class="newsletter-section" style="background: linear-gradient(135deg, <?php echo $couleur_primaire; ?>, <?php echo $couleur_primaire; ?>cc);">
    <div class="container">
        <div class="newsletter-wrapper">
            <div class="newsletter-content">
                <h2 class="newsletter-title">
                    <i class="fas fa-envelope-open-text"></i>
                    Abonnez-vous à notre newsletter
                </h2>
                <p class="newsletter-description">
                    Recevez nos promotions, actualités et nouveautés directement dans votre boîte mail.
                </p>
                <form class="newsletter-form" id="newsletter-form" method="POST" action="<?php echo URL_BASE; ?>api/newsletter/subscribe.php">
                    <div class="newsletter-input-group">
                        <input type="email" 
                               name="email" 
                               class="newsletter-input" 
                               placeholder="Votre adresse email" 
                               required 
                               aria-label="Adresse email pour la newsletter">
                        <button type="submit" class="newsletter-btn">
                            <i class="fas fa-paper-plane"></i> S'abonner
                        </button>
                    </div>
                    <div class="newsletter-consent">
                        <label>
                            <input type="checkbox" name="consent" required>
                            J'accepte de recevoir les communications de <?php echo $nom_site; ?>
                        </label>
                    </div>
                    <div id="newsletter-message" class="newsletter-message"></div>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- ============================================= -->
<!-- FOOTER PRINCIPAL -->
<!-- ============================================= -->
<footer class="main-footer">
    <div class="container">
        <div class="footer-grid">
            <!-- Colonne 1 : Informations -->
            <div class="footer-col">
                <div class="footer-brand">
                    <img src="<?php echo URL_BASE . 'assets/images/' . $logo; ?>" 
                         alt="<?php echo $nom_site; ?>" 
                         class="footer-logo">
                    <h3 class="footer-brand-name"><?php echo $nom_site; ?></h3>
                    <p class="footer-brand-slogan"><?php echo $slogan; ?></p>
                </div>
                <p class="footer-description">
                    <?php echo $description; ?>
                </p>
                <div class="footer-contact">
                    <p><i class="fas fa-map-marker-alt"></i> <?php echo $adresse; ?></p>
                    <p><i class="fas fa-phone"></i> <a href="tel:+226<?php echo $telephone; ?>"><?php echo $telephone; ?></a></p>
                    <p><i class="fab fa-whatsapp"></i> <a href="https://wa.me/226<?php echo $whatsapp; ?>" target="_blank"><?php echo $whatsapp; ?></a></p>
                    <p><i class="fas fa-envelope"></i> <a href="mailto:<?php echo $email; ?>"><?php echo $email; ?></a></p>
                </div>
            </div>
            
            <!-- Colonne 2 : Liens rapides -->
            <div class="footer-col">
                <h4 class="footer-title">Liens rapides</h4>
                <ul class="footer-links">
                    <li><a href="<?php echo URL_BASE; ?>"><i class="fas fa-chevron-right"></i> Accueil</a></li>
                    <li><a href="<?php echo URL_BASE . 'services.php'; ?>"><i class="fas fa-chevron-right"></i> Services</a></li>
                    <li><a href="<?php echo URL_BASE . 'commande.php'; ?>"><i class="fas fa-chevron-right"></i> Commander</a></li>
                    <li><a href="<?php echo URL_BASE . 'suivi.php'; ?>"><i class="fas fa-chevron-right"></i> Suivre un colis</a></li>
                    <li><a href="<?php echo URL_BASE . 'partenaires.php'; ?>"><i class="fas fa-chevron-right"></i> Partenaires</a></li>
                    <li><a href="<?php echo URL_BASE . 'blog.php'; ?>"><i class="fas fa-chevron-right"></i> Blog</a></li>
                    <li><a href="<?php echo URL_BASE . 'recrutement.php'; ?>"><i class="fas fa-chevron-right"></i> Devenir livreur</a></li>
                    <li><a href="<?php echo URL_BASE . 'devenir-partenaire.php'; ?>"><i class="fas fa-chevron-right"></i> Devenir partenaire</a></li>
                </ul>
            </div>
            
            <!-- Colonne 3 : Support -->
            <div class="footer-col">
                <h4 class="footer-title">Support</h4>
                <ul class="footer-links">
                    <li><a href="<?php echo URL_BASE . 'faq.php'; ?>"><i class="fas fa-chevron-right"></i> FAQ</a></li>
                    <li><a href="<?php echo URL_BASE . 'contact.php'; ?>"><i class="fas fa-chevron-right"></i> Contactez-nous</a></li>
                    <li><a href="<?php echo URL_BASE . 'conditions.php'; ?>"><i class="fas fa-chevron-right"></i> Conditions générales</a></li>
                    <li><a href="<?php echo URL_BASE . 'politique.php'; ?>"><i class="fas fa-chevron-right"></i> Politique de confidentialité</a></li>
                    <li><a href="<?php echo URL_BASE . 'mentions-legales.php'; ?>"><i class="fas fa-chevron-right"></i> Mentions légales</a></li>
                    <li><a href="<?php echo URL_BASE . 'livraison.php'; ?>"><i class="fas fa-chevron-right"></i> Livraison et retours</a></li>
                </ul>
            </div>
            
            <!-- Colonne 4 : Horaires et réseaux sociaux -->
            <div class="footer-col">
                <h4 class="footer-title">Horaires d'ouverture</h4>
                <ul class="footer-hours">
                    <li><span>Lundi - Vendredi</span> <span>07:00 - 22:00</span></li>
                    <li><span>Samedi</span> <span>07:00 - 22:00</span></li>
                    <li><span>Dimanche</span> <span>08:00 - 20:00</span></li>
                    <li><span>Jours fériés</span> <span>Sur demande</span></li>
                </ul>
                
                <div class="footer-social">
                    <h4 class="footer-title">Suivez-nous</h4>
                    <div class="social-links">
                        <a href="<?php echo $facebook; ?>" target="_blank" class="social-link facebook" aria-label="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="<?php echo $instagram; ?>" target="_blank" class="social-link instagram" aria-label="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="<?php echo $tiktok; ?>" target="_blank" class="social-link tiktok" aria-label="TikTok">
                            <i class="fab fa-tiktok"></i>
                        </a>
                        <a href="https://wa.me/226<?php echo $whatsapp; ?>" target="_blank" class="social-link whatsapp" aria-label="WhatsApp">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                        <a href="<?php echo $youtube; ?>" target="_blank" class="social-link youtube" aria-label="YouTube">
                            <i class="fab fa-youtube"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Badges de paiement -->
                <div class="footer-payment">
                    <h4 class="footer-title">Moyens de paiement</h4>
                    <div class="payment-icons">
                        <span class="payment-badge" title="Orange Money">
                            <img src="<?php echo URL_BASE . 'assets/images/payments/orange-money.png'; ?>" alt="Orange Money">
                        </span>
                        <span class="payment-badge" title="Moov Money">
                            <img src="<?php echo URL_BASE . 'assets/images/payments/moov-money.png'; ?>" alt="Moov Money">
                        </span>
                        <span class="payment-badge" title="Espèces">
                            <i class="fas fa-money-bill-wave"></i>
                        </span>
                        <span class="payment-badge" title="Portefeuille interne">
                            <i class="fas fa-wallet"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer bottom -->
        <div class="footer-bottom">
            <div class="footer-bottom-content">
                <p class="footer-copyright">
                    &copy; <?php echo $annee; ?> <strong><?php echo $nom_site; ?></strong> - Tous droits réservés.
                </p>
                <p class="footer-version">
                    Version <?php echo VERSION_SYSTEME; ?> | 
                    <span class="footer-dev">Développé avec <i class="fas fa-heart" style="color: #e74c3c;"></i> à Dori</span>
                </p>
                <div class="footer-bottom-links">
                    <a href="<?php echo URL_BASE . 'politique.php'; ?>">Politique</a>
                    <span class="separator">|</span>
                    <a href="<?php echo URL_BASE . 'conditions.php'; ?>">Conditions</a>
                    <span class="separator">|</span>
                    <a href="<?php echo URL_BASE . 'mentions-legales.php'; ?>">Mentions légales</a>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- ============================================= -->
<!-- SCROLL TO TOP -->
<!-- ============================================= -->
<button id="scroll-top" class="scroll-top" aria-label="Retour en haut">
    <i class="fas fa-chevron-up"></i>
</button>

<!-- ============================================= -->
<!-- COOKIES CONSENT -->
<!-- ============================================= -->
<div id="cookies-consent" class="cookies-consent" style="display: none;">
    <div class="cookies-consent-content">
        <div class="cookies-text">
            <i class="fas fa-cookie-bite"></i>
            <p>Nous utilisons des cookies pour améliorer votre expérience sur <?php echo $nom_site; ?>. 
            En poursuivant votre navigation, vous acceptez leur utilisation.</p>
        </div>
        <div class="cookies-actions">
            <button id="cookies-accept" class="cookies-btn-accept">
                <i class="fas fa-check"></i> Accepter
            </button>
            <button id="cookies-refuse" class="cookies-btn-refuse">
                Refuser
            </button>
            <a href="<?php echo URL_BASE . 'politique.php'; ?>" class="cookies-btn-more">
                En savoir plus
            </a>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- JAVASCRIPT - SCRIPTS -->
<!-- ============================================= -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Scripts personnalisés -->
<script src="<?php echo URL_BASE . 'assets/js/app.js'; ?>"></script>
<script src="<?php echo URL_BASE . 'assets/js/notifications.js'; ?>"></script>
<script src="<?php echo URL_BASE . 'assets/js/darkmode.js'; ?>"></script>
<script src="<?php echo URL_BASE . 'assets/js/search.js'; ?>"></script>

<!-- Script de la page spécifique si défini -->
<?php if (isset($page_script) && !empty($page_script)): ?>
    <script src="<?php echo URL_BASE . 'assets/js/' . $page_script; ?>"></script>
<?php endif; ?>

<!-- ============================================= -->
<!-- SCRIPT POUR LE FOOTER -->
<!-- ============================================= -->
<script>
$(document).ready(function() {
    'use strict';
    
    // =========================================
    // Newsletter
    // =========================================
    $('#newsletter-form').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const email = form.find('input[name="email"]').val();
        const consent = form.find('input[name="consent"]').is(':checked');
        const messageDiv = $('#newsletter-message');
        
        if (!consent) {
            Swal.fire({
                icon: 'warning',
                title: 'Consentement requis',
                text: 'Veuillez accepter de recevoir nos communications.',
                confirmButtonColor: '<?php echo $couleur_primaire; ?>'
            });
            return;
        }
        
        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: { email: email, consent: consent },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Inscription réussie !',
                        text: 'Merci de vous être abonné à notre newsletter.',
                        confirmButtonColor: '<?php echo $couleur_primaire; ?>'
                    });
                    form.find('input[name="email"]').val('');
                    form.find('input[name="consent"]').prop('checked', false);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: response.message || 'Une erreur est survenue.',
                        confirmButtonColor: '<?php echo $couleur_primaire; ?>'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Impossible de contacter le serveur.',
                    confirmButtonColor: '<?php echo $couleur_primaire; ?>'
                });
            }
        });
    });
    
    // =========================================
    // Scroll to top
    // =========================================
    const scrollBtn = $('#scroll-top');
    
    $(window).on('scroll', function() {
        if ($(this).scrollTop() > 300) {
            scrollBtn.fadeIn(300);
        } else {
            scrollBtn.fadeOut(300);
        }
    });
    
    scrollBtn.on('click', function() {
        $('html, body').animate({
            scrollTop: 0
        }, 500);
    });
    
    // =========================================
    // Cookies Consent
    // =========================================
    if (!localStorage.getItem('cookies_consent')) {
        $('#cookies-consent').fadeIn(500);
    }
    
    $('#cookies-accept').on('click', function() {
        localStorage.setItem('cookies_consent', 'accepted');
        $('#cookies-consent').fadeOut(500);
    });
    
    $('#cookies-refuse').on('click', function() {
        localStorage.setItem('cookies_consent', 'refused');
        $('#cookies-consent').fadeOut(500);
    });
    
    // =========================================
    // Animation au scroll (AOS-like)
    // =========================================
    const animateOnScroll = function() {
        const elements = $('.animate-on-scroll');
        const windowHeight = $(window).height();
        const scrollY = $(window).scrollTop();
        
        elements.each(function() {
            const element = $(this);
            const elementTop = element.offset().top;
            const elementVisible = 150;
            
            if (elementTop < windowHeight + scrollY - elementVisible) {
                element.addClass('animated');
            }
        });
    };
    
    $(window).on('scroll', animateOnScroll);
    animateOnScroll();
    
    // =========================================
    // Compteurs animés
    // =========================================
    const animateCounter = function() {
        $('.counter').each(function() {
            const element = $(this);
            const target = parseInt(element.attr('data-target'));
            const duration = 2000;
            const step = target / (duration / 16);
            let current = 0;
            
            if (element.data('animated')) return;
            element.data('animated', true);
            
            const timer = setInterval(function() {
                current += step;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                element.text(Math.round(current));
            }, 16);
        });
    };
    
    // Observer pour les compteurs
    const counterObserver = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                animateCounter();
                counterObserver.unobserve(entry.target);
            }
        });
    });
    
    $('.counter').each(function() {
        counterObserver.observe(this);
    });
    
    console.log('✅ DoriExpress-Pro - Footer chargé');
});
</script>

<!-- ============================================= -->
<!-- STYLES CSS ADDITIONNELS POUR LE FOOTER -->
<!-- ============================================= -->
<style>
/* Newsletter */
.newsletter-section {
    padding: 60px 0;
    color: white;
}
.newsletter-wrapper {
    max-width: 600px;
    margin: 0 auto;
    text-align: center;
}
.newsletter-title {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 15px;
}
.newsletter-description {
    font-size: 16px;
    opacity: 0.9;
    margin-bottom: 25px;
}
.newsletter-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
}
.newsletter-input-group {
    display: flex;
    gap: 10px;
}
.newsletter-input {
    flex: 1;
    padding: 15px 20px;
    border: none;
    border-radius: 50px;
    font-size: 16px;
    outline: none;
}
.newsletter-btn {
    padding: 15px 30px;
    border: none;
    border-radius: 50px;
    background: #1a1a1a;
    color: white;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}
.newsletter-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.3);
}
.newsletter-consent label {
    font-size: 14px;
    opacity: 0.8;
    cursor: pointer;
}
.newsletter-consent input[type="checkbox"] {
    margin-right: 8px;
}
.newsletter-message {
    margin-top: 10px;
    font-size: 14px;
}

/* Footer principal */
.main-footer {
    background: #1a1a1a;
    color: #ccc;
    padding: 60px 0 0;
}
.footer-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1.5fr;
    gap: 40px;
    padding-bottom: 40px;
    border-bottom: 1px solid #333;
}
.footer-col {
    display: flex;
    flex-direction: column;
    gap: 15px;
}
.footer-brand {
    display: flex;
    align-items: center;
    gap: 15px;
}
.footer-logo {
    height: 50px;
    width: auto;
}
.footer-brand-name {
    color: white;
    font-size: 22px;
    font-weight: 700;
    margin: 0;
}
.footer-brand-slogan {
    color: <?php echo $couleur_primaire; ?>;
    font-size: 14px;
}
.footer-description {
    font-size: 14px;
    line-height: 1.6;
    margin: 0;
}
.footer-contact p {
    margin: 8px 0;
    font-size: 14px;
}
.footer-contact a {
    color: #ccc;
    text-decoration: none;
    transition: color 0.3s ease;
}
.footer-contact a:hover {
    color: <?php echo $couleur_primaire; ?>;
}
.footer-contact i {
    width: 25px;
    color: <?php echo $couleur_primaire; ?>;
}
.footer-title {
    color: white;
    font-size: 18px;
    font-weight: 600;
    margin: 0 0 10px 0;
    position: relative;
    padding-bottom: 10px;
}
.footer-title::after {
    content: '';
    position: absolute;
    left: 0;
    bottom: 0;
    width: 30px;
    height: 3px;
    background: <?php echo $couleur_primaire; ?>;
    border-radius: 3px;
}
.footer-links {
    list-style: none;
    padding: 0;
    margin: 0;
}
.footer-links li {
    margin: 8px 0;
}
.footer-links a {
    color: #ccc;
    text-decoration: none;
    font-size: 14px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}
.footer-links a i {
    font-size: 10px;
    color: <?php echo $couleur_primaire; ?>;
}
.footer-links a:hover {
    color: white;
    padding-left: 5px;
}
.footer-hours li {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    font-size: 14px;
    border-bottom: 1px solid #333;
}
.footer-hours li:last-child {
    border-bottom: none;
}
.footer-hours li span:last-child {
    color: white;
}

/* Social links */
.social-links {
    display: flex;
    gap: 12px;
    margin-top: 10px;
}
.social-link {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    color: white;
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 18px;
}
.social-link:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}
.social-link.facebook { background: #1877f2; }
.social-link.instagram { background: #e4405f; }
.social-link.tiktok { background: #000; }
.social-link.whatsapp { background: #25d366; }
.social-link.youtube { background: #ff0000; }

/* Payment badges */
.payment-icons {
    display: flex;
    gap: 15px;
    margin-top: 10px;
    flex-wrap: wrap;
}
.payment-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px 15px;
    background: #2a2a2a;
    border-radius: 8px;
    font-size: 14px;
    color: #ccc;
}
.payment-badge img {
    height: 25px;
    width: auto;
}
.payment-badge i {
    font-size: 24px;
    color: <?php echo $couleur_primaire; ?>;
}

/* Footer bottom */
.footer-bottom {
    padding: 20px 0;
    margin-top: 20px;
    background: #111;
}
.footer-bottom-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}
.footer-copyright {
    font-size: 14px;
    margin: 0;
}
.footer-copyright strong {
    color: white;
}
.footer-version {
    font-size: 13px;
    opacity: 0.7;
    margin: 0;
}
.footer-dev i {
    color: #e74c3c;
}
.footer-bottom-links {
    display: flex;
    gap: 10px;
    font-size: 13px;
}
.footer-bottom-links a {
    color: #999;
    text-decoration: none;
    transition: color 0.3s ease;
}
.footer-bottom-links a:hover {
    color: white;
}
.footer-bottom-links .separator {
    color: #555;
}

/* Scroll to top */
.scroll-top {
    position: fixed;
    bottom: 100px;
    right: 30px;
    width: 45px;
    height: 45px;
    border: none;
    border-radius: 50%;
    background: <?php echo $couleur_primaire; ?>;
    color: white;
    font-size: 20px;
    cursor: pointer;
    display: none;
    transition: all 0.3s ease;
    z-index: 1000;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}
.scroll-top:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 25px rgba(0,0,0,0.3);
}

/* Cookies consent */
.cookies-consent {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: #1a1a1a;
    color: white;
    padding: 20px;
    z-index: 9999;
    box-shadow: 0 -4px 20px rgba(0,0,0,0.3);
}
.cookies-consent-content {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}
.cookies-text {
    display: flex;
    align-items: center;
    gap: 15px;
    flex: 1;
}
.cookies-text i {
    font-size: 30px;
    color: <?php echo $couleur_primaire; ?>;
}
.cookies-text p {
    margin: 0;
    font-size: 14px;
    line-height: 1.5;
}
.cookies-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.cookies-btn-accept {
    padding: 10px 25px;
    border: none;
    border-radius: 50px;
    background: <?php echo $couleur_primaire; ?>;
    color: white;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}
.cookies-btn-accept:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,166,81,0.4);
}
.cookies-btn-refuse {
    padding: 10px 25px;
    border: 1px solid #555;
    border-radius: 50px;
    background: transparent;
    color: #ccc;
    cursor: pointer;
    transition: all 0.3s ease;
}
.cookies-btn-refuse:hover {
    border-color: white;
    color: white;
}
.cookies-btn-more {
    padding: 10px 25px;
    color: <?php echo $couleur_primaire; ?>;
    text-decoration: none;
    font-size: 14px;
    display: flex;
    align-items: center;
}
.cookies-btn-more:hover {
    text-decoration: underline;
}

/* Responsive footer */
@media (max-width: 992px) {
    .footer-grid {
        grid-template-columns: 1fr 1fr;
        gap: 30px;
    }
}
@media (max-width: 768px) {
    .footer-grid {
        grid-template-columns: 1fr;
        gap: 25px;
    }
    .footer-bottom-content {
        flex-direction: column;
        text-align: center;
    }
    .footer-bottom-links {
        justify-content: center;
    }
    .cookies-consent-content {
        flex-direction: column;
        text-align: center;
    }
    .cookies-text {
        flex-direction: column;
    }
    .newsletter-input-group {
        flex-direction: column;
    }
    .newsletter-btn {
        width: 100%;
    }
    .social-links {
        justify-content: center;
    }
    .payment-icons {
        justify-content: center;
    }
}
@media (max-width: 480px) {
    .footer-logo {
        height: 40px;
    }
    .footer-brand-name {
        font-size: 18px;
    }
    .footer-col {
        text-align: center;
    }
    .footer-title::after {
        left: 50%;
        transform: translateX(-50%);
    }
    .footer-hours li {
        flex-direction: column;
        align-items: center;
        gap: 3px;
    }
    .cookies-actions {
        flex-direction: column;
        width: 100%;
    }
    .cookies-actions button,
    .cookies-actions a {
        width: 100%;
        text-align: center;
        justify-content: center;
    }
}
</style>

<!-- ============================================= -->
<!-- FERMETURE DU FOOTER -->
<!-- ============================================= -->
</body>
</html>

<?php
// =============================================
// FIN DU FICHIER FOOTER.PHP
// =============================================
?>