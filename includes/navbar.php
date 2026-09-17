<?php
/**
 * =============================================
 * BARRE DE NAVIGATION - DoriExpress-Pro
 * =============================================
 * Fichier : includes/navbar.php
 * Rôle : Barre de navigation complète (version desktop et mobile)
 * Utilisation : require_once 'includes/navbar.php';
 * =============================================
 */

// Empêcher l'accès direct
if (!defined('DOSSIER_RACINE')) {
    die('Accès direct interdit');
}

// Récupérer les paramètres
$nom_site = get_parametre('nom_site', 'DoriExpress-Pro');
$slogan = get_parametre('slogan', 'Votre livraison rapide à Dori');
$logo = get_parametre('logo', 'logo.png');
$telephone = get_parametre('telephone', '61874528');
$whatsapp = get_parametre('whatsapp', '61874528');
$couleur_primaire = get_parametre('couleur_primaire', '#00A651');

// Déterminer la page active
$current_page = basename($_SERVER['PHP_SELF']);
$current_url = $_SERVER['REQUEST_URI'];

// Vérifier si l'utilisateur est connecté
$is_logged_in = est_connecte();
$user = utilisateur_connecte();
$user_role = $is_logged_in ? $user['role'] : null;
$user_nom = $is_logged_in ? $user['nom'] . ' ' . $user['prenom'] : '';
$user_photo = $is_logged_in ? $user['photo'] ?? 'default.jpg' : 'default.jpg';

// Récupérer le nombre de notifications non lues
$nb_notifications = 0;
if ($is_logged_in) {
    try {
        $db = Database::getInstance();
        $nb_notifications = (int) $db->fetchValue(
            "SELECT COUNT(*) FROM notifications WHERE utilisateur_id = ? AND est_lu = 0",
            [$user['id']]
        );
    } catch (Exception $e) {
        $nb_notifications = 0;
    }
}

// Récupérer le nombre de messages non lus
$nb_messages = 0;
if ($is_logged_in) {
    try {
        $db = Database::getInstance();
        $nb_messages = (int) $db->fetchValue(
            "SELECT COUNT(*) FROM messages WHERE destinataire_id = ? AND est_lu = 0",
            [$user['id']]
        );
    } catch (Exception $e) {
        $nb_messages = 0;
    }
}

// Déterminer le lien du dashboard selon le rôle
function get_dashboard_link($role) {
    $links = [
        'createur' => 'admin/createur.php',
        'admin' => 'admin/dashboard.php',
        'client' => 'client/dashboard.php',
        'livreur' => 'livreur/dashboard.php',
        'partenaire' => 'partenaire/dashboard.php',
        'support' => 'admin/support.php'
    ];
    return isset($links[$role]) ? URL_BASE . $links[$role] : URL_BASE . 'profil.php';
}

// Fonction pour vérifier si une page est active
function is_page_active($page) {
    global $current_page;
    return $current_page == $page ? 'active' : '';
}

// Fonction pour vérifier si une section est active
function is_section_active($pages) {
    global $current_page;
    foreach ($pages as $page) {
        if ($current_page == $page) {
            return 'active';
        }
    }
    return '';
}
?>
<!-- ============================================= -->
<!-- NAVIGATION PRINCIPALE -->
<!-- ============================================= -->
<nav class="navbar-main" id="navbar-main" role="navigation" aria-label="Navigation principale">
    <div class="container">
        <div class="navbar-wrapper">
            
            <!-- Logo -->
            <div class="navbar-brand">
                <a href="<?php echo URL_BASE; ?>" class="brand-link" aria-label="<?php echo $nom_site; ?>">
                    <img src="<?php echo URL_BASE . 'assets/images/' . $logo; ?>" 
                         alt="<?php echo $nom_site; ?>" 
                         class="brand-logo"
                         height="45"
                         width="auto">
                    <span class="brand-text">
                        <span class="brand-name"><?php echo $nom_site; ?></span>
                        <span class="brand-slogan"><?php echo $slogan; ?></span>
                    </span>
                </a>
            </div>
            
            <!-- Barre de recherche -->
            <div class="navbar-search">
                <form class="search-form" action="<?php echo URL_BASE . 'recherche.php'; ?>" method="GET" role="search">
                    <div class="search-wrapper">
                        <input type="text" 
                               name="q" 
                               class="search-input" 
                               placeholder="Rechercher un service, un restaurant, une boutique..." 
                               aria-label="Rechercher"
                               autocomplete="off">
                        <button type="submit" class="search-btn" aria-label="Lancer la recherche">
                            <i class="fas fa-search"></i>
                        </button>
                        <div class="search-suggestions" id="search-suggestions"></div>
                    </div>
                </form>
            </div>
            
            <!-- Liens de navigation -->
            <ul class="navbar-nav">
                <!-- Pages principales -->
                <li class="nav-item <?php echo is_page_active('index.php') ? 'active' : ''; ?>">
                    <a href="<?php echo URL_BASE; ?>" class="nav-link">
                        <i class="fas fa-home"></i>
                        <span class="nav-label">Accueil</span>
                    </a>
                </li>
                
                <li class="nav-item <?php echo is_section_active(['services.php', 'tarifs.php']) ? 'active' : ''; ?>">
                    <a href="<?php echo URL_BASE . 'services.php'; ?>" class="nav-link">
                        <i class="fas fa-concierge-bell"></i>
                        <span class="nav-label">Services</span>
                    </a>
                </li>
                
                <li class="nav-item nav-item-commande <?php echo is_page_active('commande.php') ? 'active' : ''; ?>">
                    <a href="<?php echo URL_BASE . 'commande.php'; ?>" class="nav-link nav-link-commande">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="nav-label">Commander</span>
                    </a>
                </li>
                
                <li class="nav-item <?php echo is_page_active('suivi.php') ? 'active' : ''; ?>">
                    <a href="<?php echo URL_BASE . 'suivi.php'; ?>" class="nav-link">
                        <i class="fas fa-map-marker-alt"></i>
                        <span class="nav-label">Suivre</span>
                    </a>
                </li>
                
                <li class="nav-item <?php echo is_section_active(['partenaires.php', 'boutique.php', 'restaurants.php']) ? 'active' : ''; ?>">
                    <a href="<?php echo URL_BASE . 'partenaires.php'; ?>" class="nav-link">
                        <i class="fas fa-store"></i>
                        <span class="nav-label">Partenaires</span>
                    </a>
                </li>
                
                <li class="nav-item <?php echo is_section_active(['blog.php', 'actualites.php']) ? 'active' : ''; ?>">
                    <a href="<?php echo URL_BASE . 'blog.php'; ?>" class="nav-link">
                        <i class="fas fa-newspaper"></i>
                        <span class="nav-label">Blog</span>
                    </a>
                </li>
                
                <li class="nav-item <?php echo is_page_active('contact.php') ? 'active' : ''; ?>">
                    <a href="<?php echo URL_BASE . 'contact.php'; ?>" class="nav-link">
                        <i class="fas fa-envelope"></i>
                        <span class="nav-label">Contact</span>
                    </a>
                </li>
                
                <!-- Section utilisateur -->
                <?php if ($is_logged_in): ?>
                    <li class="nav-item nav-item-user dropdown">
                        <a href="#" class="nav-link user-dropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <span class="user-avatar-wrapper">
                                <img src="<?php echo URL_BASE . 'uploads/profils/' . $user_photo; ?>" 
                                     alt="<?php echo $user_nom; ?>" 
                                     class="user-avatar">
                                <?php if ($nb_notifications > 0): ?>
                                    <span class="badge-notification"><?php echo $nb_notifications; ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="user-name"><?php echo $user_nom; ?></span>
                            <i class="fas fa-chevron-down user-chevron"></i>
                        </a>
                        
                        <ul class="dropdown-menu" role="menu">
                            <!-- Dashboard selon le rôle -->
                            <li role="menuitem">
                                <a href="<?php echo get_dashboard_link($user_role); ?>" class="dropdown-link">
                                    <i class="fas fa-tachometer-alt"></i>
                                    <span>Dashboard</span>
                                </a>
                            </li>
                            
                            <!-- Notifications -->
                            <li role="menuitem">
                                <a href="<?php echo URL_BASE . 'notifications.php'; ?>" class="dropdown-link">
                                    <i class="fas fa-bell"></i>
                                    <span>Notifications</span>
                                    <?php if ($nb_notifications > 0): ?>
                                        <span class="badge dropdown-badge"><?php echo $nb_notifications; ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            
                            <!-- Messages -->
                            <li role="menuitem">
                                <a href="<?php echo URL_BASE . 'messages.php'; ?>" class="dropdown-link">
                                    <i class="fas fa-envelope"></i>
                                    <span>Messages</span>
                                    <?php if ($nb_messages > 0): ?>
                                        <span class="badge dropdown-badge"><?php echo $nb_messages; ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            
                            <!-- Profil -->
                            <li role="menuitem">
                                <a href="<?php echo URL_BASE . 'profil.php'; ?>" class="dropdown-link">
                                    <i class="fas fa-user"></i>
                                    <span>Mon profil</span>
                                </a>
                            </li>
                            
                            <!-- Wallet (si client ou livreur) -->
                            <?php if (in_array($user_role, ['client', 'livreur', 'partenaire'])): ?>
                                <li role="menuitem">
                                    <a href="<?php echo URL_BASE . ($user_role == 'client' ? 'client/wallet.php' : ($user_role == 'livreur' ? 'livreur/wallet.php' : 'partenaire/paiements.php')); ?>" class="dropdown-link">
                                        <i class="fas fa-wallet"></i>
                                        <span>Portefeuille</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <!-- Commandes (client) -->
                            <?php if ($user_role == 'client'): ?>
                                <li role="menuitem">
                                    <a href="<?php echo URL_BASE . 'client/commandes.php'; ?>" class="dropdown-link">
                                        <i class="fas fa-history"></i>
                                        <span>Mes commandes</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <!-- Missions (livreur) -->
                            <?php if ($user_role == 'livreur'): ?>
                                <li role="menuitem">
                                    <a href="<?php echo URL_BASE . 'livreur/missions.php'; ?>" class="dropdown-link">
                                        <i class="fas fa-tasks"></i>
                                        <span>Mes missions</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <!-- Produits (partenaire) -->
                            <?php if ($user_role == 'partenaire'): ?>
                                <li role="menuitem">
                                    <a href="<?php echo URL_BASE . 'partenaire/produits.php'; ?>" class="dropdown-link">
                                        <i class="fas fa-box"></i>
                                        <span>Mes produits</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <!-- Séparateur -->
                            <li role="separator" class="dropdown-divider"></li>
                            
                            <!-- Paramètres -->
                            <li role="menuitem">
                                <a href="<?php echo URL_BASE . 'parametres.php'; ?>" class="dropdown-link">
                                    <i class="fas fa-cog"></i>
                                    <span>Paramètres</span>
                                </a>
                            </li>
                            
                            <!-- Déconnexion -->
                            <li role="menuitem">
                                <a href="<?php echo URL_BASE . 'logout.php'; ?>" class="dropdown-link dropdown-link-danger">
                                    <i class="fas fa-sign-out-alt"></i>
                                    <span>Déconnexion</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                <?php else: ?>
                    <!-- Visiteur non connecté -->
                    <li class="nav-item nav-item-auth">
                        <a href="<?php echo URL_BASE . 'login.php'; ?>" class="nav-link nav-link-login">
                            <i class="fas fa-sign-in-alt"></i>
                            <span class="nav-label">Connexion</span>
                        </a>
                    </li>
                    <li class="nav-item nav-item-auth">
                        <a href="<?php echo URL_BASE . 'register.php'; ?>" class="nav-link nav-link-register">
                            <i class="fas fa-user-plus"></i>
                            <span class="nav-label">Inscription</span>
                        </a>
                    </li>
                <?php endif; ?>
                
                <!-- Mode sombre -->
                <li class="nav-item nav-item-darkmode">
                    <button class="nav-link btn-darkmode" onclick="toggleDarkMode()" aria-label="Mode sombre">
                        <i class="fas fa-moon darkmode-icon"></i>
                    </button>
                </li>
                
                <!-- WhatsApp flottant (visible sur mobile dans la navbar) -->
                <li class="nav-item nav-item-whatsapp-mobile">
                    <a href="https://wa.me/226<?php echo $whatsapp; ?>?text=Bonjour%20DoriExpress-Pro" 
                       class="nav-link nav-link-whatsapp" 
                       target="_blank"
                       aria-label="WhatsApp">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                </li>
            </ul>
            
            <!-- Bouton menu mobile -->
            <button class="navbar-toggle" id="navbar-toggle" aria-label="Menu" aria-expanded="false">
                <span class="toggle-icon">
                    <span></span>
                    <span></span>
                    <span></span>
                </span>
            </button>
        </div>
    </div>
</nav>

<!-- ============================================= -->
<!-- MENU MOBILE -->
<!-- ============================================= -->
<div class="mobile-menu-overlay" id="mobile-menu-overlay"></div>
<div class="mobile-menu" id="mobile-menu" role="dialog" aria-label="Menu mobile">
    <div class="mobile-menu-header">
        <div class="mobile-menu-brand">
            <img src="<?php echo URL_BASE . 'assets/images/' . $logo; ?>" 
                 alt="<?php echo $nom_site; ?>" 
                 class="mobile-menu-logo"
                 height="35">
            <span class="mobile-menu-brand-name"><?php echo $nom_site; ?></span>
        </div>
        <button class="mobile-menu-close" id="mobile-menu-close" aria-label="Fermer le menu">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <!-- Profil utilisateur (mobile) -->
    <?php if ($is_logged_in): ?>
        <div class="mobile-user-profile">
            <img src="<?php echo URL_BASE . 'uploads/profils/' . $user_photo; ?>" 
                 alt="<?php echo $user_nom; ?>" 
                 class="mobile-user-avatar">
            <div class="mobile-user-info">
                <span class="mobile-user-name"><?php echo $user_nom; ?></span>
                <span class="mobile-user-role"><?php echo ucfirst($user_role); ?></span>
            </div>
        </div>
    <?php endif; ?>
    
    <ul class="mobile-menu-nav">
        <!-- Pages principales -->
        <li class="mobile-nav-item <?php echo is_page_active('index.php') ? 'active' : ''; ?>">
            <a href="<?php echo URL_BASE; ?>" class="mobile-nav-link">
                <i class="fas fa-home"></i>
                <span>Accueil</span>
            </a>
        </li>
        
        <li class="mobile-nav-item <?php echo is_section_active(['services.php', 'tarifs.php']) ? 'active' : ''; ?>">
            <a href="<?php echo URL_BASE . 'services.php'; ?>" class="mobile-nav-link">
                <i class="fas fa-concierge-bell"></i>
                <span>Services</span>
            </a>
        </li>
        
        <li class="mobile-nav-item mobile-nav-commande <?php echo is_page_active('commande.php') ? 'active' : ''; ?>">
            <a href="<?php echo URL_BASE . 'commande.php'; ?>" class="mobile-nav-link mobile-nav-link-commande">
                <i class="fas fa-shopping-cart"></i>
                <span>Commander</span>
            </a>
        </li>
        
        <li class="mobile-nav-item <?php echo is_page_active('suivi.php') ? 'active' : ''; ?>">
            <a href="<?php echo URL_BASE . 'suivi.php'; ?>" class="mobile-nav-link">
                <i class="fas fa-map-marker-alt"></i>
                <span>Suivre</span>
            </a>
        </li>
        
        <li class="mobile-nav-item <?php echo is_section_active(['partenaires.php', 'boutique.php', 'restaurants.php']) ? 'active' : ''; ?>">
            <a href="<?php echo URL_BASE . 'partenaires.php'; ?>" class="mobile-nav-link">
                <i class="fas fa-store"></i>
                <span>Partenaires</span>
            </a>
        </li>
        
        <li class="mobile-nav-item <?php echo is_section_active(['blog.php', 'actualites.php']) ? 'active' : ''; ?>">
            <a href="<?php echo URL_BASE . 'blog.php'; ?>" class="mobile-nav-link">
                <i class="fas fa-newspaper"></i>
                <span>Blog</span>
            </a>
        </li>
        
        <li class="mobile-nav-item <?php echo is_page_active('contact.php') ? 'active' : ''; ?>">
            <a href="<?php echo URL_BASE . 'contact.php'; ?>" class="mobile-nav-link">
                <i class="fas fa-envelope"></i>
                <span>Contact</span>
            </a>
        </li>
        
        <li class="mobile-nav-item <?php echo is_page_active('faq.php') ? 'active' : ''; ?>">
            <a href="<?php echo URL_BASE . 'faq.php'; ?>" class="mobile-nav-link">
                <i class="fas fa-question-circle"></i>
                <span>FAQ</span>
            </a>
        </li>
        
        <li class="mobile-nav-item <?php echo is_page_active('recrutement.php') ? 'active' : ''; ?>">
            <a href="<?php echo URL_BASE . 'recrutement.php'; ?>" class="mobile-nav-link">
                <i class="fas fa-users"></i>
                <span>Devenir livreur</span>
            </a>
        </li>
        
        <?php if ($is_logged_in): ?>
            <!-- Séparateur -->
            <li class="mobile-nav-divider"></li>
            
            <!-- Dashboard -->
            <li class="mobile-nav-item">
                <a href="<?php echo get_dashboard_link($user_role); ?>" class="mobile-nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <!-- Notifications -->
            <li class="mobile-nav-item">
                <a href="<?php echo URL_BASE . 'notifications.php'; ?>" class="mobile-nav-link">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                    <?php if ($nb_notifications > 0): ?>
                        <span class="mobile-badge"><?php echo $nb_notifications; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- Messages -->
            <li class="mobile-nav-item">
                <a href="<?php echo URL_BASE . 'messages.php'; ?>" class="mobile-nav-link">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                    <?php if ($nb_messages > 0): ?>
                        <span class="mobile-badge"><?php echo $nb_messages; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- Profil -->
            <li class="mobile-nav-item">
                <a href="<?php echo URL_BASE . 'profil.php'; ?>" class="mobile-nav-link">
                    <i class="fas fa-user"></i>
                    <span>Mon profil</span>
                </a>
            </li>
            
            <!-- Wallet -->
            <?php if (in_array($user_role, ['client', 'livreur', 'partenaire'])): ?>
                <li class="mobile-nav-item">
                    <a href="<?php echo URL_BASE . ($user_role == 'client' ? 'client/wallet.php' : ($user_role == 'livreur' ? 'livreur/wallet.php' : 'partenaire/paiements.php')); ?>" class="mobile-nav-link">
                        <i class="fas fa-wallet"></i>
                        <span>Portefeuille</span>
                    </a>
                </li>
            <?php endif; ?>
            
            <!-- Paramètres -->
            <li class="mobile-nav-item">
                <a href="<?php echo URL_BASE . 'parametres.php'; ?>" class="mobile-nav-link">
                    <i class="fas fa-cog"></i>
                    <span>Paramètres</span>
                </a>
            </li>
            
            <!-- Séparateur -->
            <li class="mobile-nav-divider"></li>
            
            <!-- Déconnexion -->
            <li class="mobile-nav-item">
                <a href="<?php echo URL_BASE . 'logout.php'; ?>" class="mobile-nav-link mobile-nav-link-danger">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </li>
            
        <?php else: ?>
            <li class="mobile-nav-divider"></li>
            
            <!-- Connexion / Inscription -->
            <li class="mobile-nav-item mobile-nav-auth">
                <a href="<?php echo URL_BASE . 'login.php'; ?>" class="mobile-nav-link mobile-nav-link-login">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Connexion</span>
                </a>
            </li>
            <li class="mobile-nav-item mobile-nav-auth">
                <a href="<?php echo URL_BASE . 'register.php'; ?>" class="mobile-nav-link mobile-nav-link-register">
                    <i class="fas fa-user-plus"></i>
                    <span>Inscription</span>
                </a>
            </li>
        <?php endif; ?>
        
        <!-- WhatsApp mobile -->
        <li class="mobile-nav-divider"></li>
        <li class="mobile-nav-item mobile-nav-whatsapp">
            <a href="https://wa.me/226<?php echo $whatsapp; ?>?text=Bonjour%20DoriExpress-Pro" 
               class="mobile-nav-link mobile-nav-link-whatsapp" 
               target="_blank">
                <i class="fab fa-whatsapp"></i>
                <span>WhatsApp</span>
            </a>
        </li>
        
        <!-- Appel -->
        <li class="mobile-nav-item mobile-nav-call">
            <a href="tel:+226<?php echo $telephone; ?>" class="mobile-nav-link mobile-nav-link-call">
                <i class="fas fa-phone"></i>
                <span>Appeler</span>
            </a>
        </li>
    </ul>
</div>

<!-- ============================================= -->
<!-- STYLES CSS DE LA NAVBAR -->
<!-- ============================================= -->
<style>
/* ============================================= */
/* NAVIGATION PRINCIPALE */
/* ============================================= */
.navbar-main {
    background: #ffffff;
    border-bottom: 1px solid #e5e7eb;
    padding: 0;
    position: sticky;
    top: 0;
    z-index: 1000;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}

.navbar-main.scrolled {
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.navbar-wrapper {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 70px;
    gap: 15px;
}

/* ============================================= */
/* LOGO */
/* ============================================= */
.navbar-brand {
    flex-shrink: 0;
}

.brand-link {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    transition: opacity 0.3s ease;
}

.brand-link:hover {
    opacity: 0.9;
}

.brand-logo {
    height: 45px;
    width: auto;
    object-fit: contain;
}

.brand-text {
    display: flex;
    flex-direction: column;
    line-height: 1.2;
}

.brand-name {
    font-size: 20px;
    font-weight: 800;
    color: #1a1a1a;
    letter-spacing: -0.5px;
}

.brand-slogan {
    font-size: 11px;
    color: <?php echo $couleur_primaire; ?>;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* ============================================= */
/* BARRE DE RECHERCHE */
/* ============================================= */
.navbar-search {
    flex: 1;
    max-width: 500px;
    margin: 0 15px;
}

.search-wrapper {
    position: relative;
    width: 100%;
}

.search-input {
    width: 100%;
    padding: 10px 45px 10px 18px;
    border: 2px solid #e5e7eb;
    border-radius: 50px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: #f9fafb;
    color: #1a1a1a;
}

.search-input:focus {
    border-color: <?php echo $couleur_primaire; ?>;
    background: #ffffff;
    box-shadow: 0 0 0 4px <?php echo $couleur_primaire; ?>25;
    outline: none;
}

.search-btn {
    position: absolute;
    right: 5px;
    top: 50%;
    transform: translateY(-50%);
    background: <?php echo $couleur_primaire; ?>;
    color: white;
    border: none;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.search-btn:hover {
    transform: translateY(-50%) scale(1.05);
    box-shadow: 0 4px 15px <?php echo $couleur_primaire; ?>50;
}

.search-suggestions {
    position: absolute;
    top: calc(100% + 5px);
    left: 0;
    right: 0;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    display: none;
    z-index: 1001;
    max-height: 300px;
    overflow-y: auto;
}

.search-suggestions.active {
    display: block;
}

/* ============================================= */
/* LIENS DE NAVIGATION */
/* ============================================= */
.navbar-nav {
    display: flex;
    align-items: center;
    list-style: none;
    margin: 0;
    padding: 0;
    gap: 2px;
}

.nav-item {
    position: relative;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    border-radius: 50px;
    color: #4a4a4a;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    background: transparent;
    cursor: pointer;
    white-space: nowrap;
}

.nav-link i {
    font-size: 16px;
    color: #6b7280;
    transition: color 0.3s ease;
}

.nav-link:hover {
    color: <?php echo $couleur_primaire; ?>;
    background: <?php echo $couleur_primaire; ?>10;
}

.nav-link:hover i {
    color: <?php echo $couleur_primaire; ?>;
}

.nav-item.active .nav-link {
    color: <?php echo $couleur_primaire; ?>;
    background: <?php echo $couleur_primaire; ?>15;
    font-weight: 600;
}

.nav-item.active .nav-link i {
    color: <?php echo $couleur_primaire; ?>;
}

/* Bouton Commander */
.nav-link-commande {
    background: <?php echo $couleur_primaire; ?> !important;
    color: white !important;
    font-weight: 700;
    padding: 8px 20px;
}

.nav-link-commande i {
    color: white !important;
}

.nav-link-commande:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px <?php echo $couleur_primaire; ?>50;
    color: white !important;
    background: <?php echo $couleur_primaire; ?> !important;
}

/* ============================================= */
/* UTILISATEUR CONNECTÉ */
/* ============================================= */
.user-dropdown {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 4px 12px 4px 4px;
    border-radius: 50px;
    border: 2px solid transparent;
    transition: all 0.3s ease;
}

.user-dropdown:hover {
    border-color: <?php echo $couleur_primaire; ?>40;
}

.user-avatar-wrapper {
    position: relative;
    display: flex;
}

.user-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid <?php echo $couleur_primaire; ?>;
}

.badge-notification {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ef4444;
    color: white;
    font-size: 10px;
    font-weight: 700;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid white;
}

.user-name {
    font-weight: 600;
    color: #1a1a1a;
    font-size: 14px;
    max-width: 100px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.user-chevron {
    font-size: 12px;
    color: #6b7280;
    transition: transform 0.3s ease;
}

.user-dropdown:hover .user-chevron {
    transform: rotate(180deg);
}

/* ============================================= */
/* DROPDOWN MENU */
/* ============================================= */
.dropdown-menu {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    min-width: 250px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 10px 50px rgba(0,0,0,0.15);
    padding: 8px 0;
    display: none;
    z-index: 1002;
    animation: dropdownFade 0.2s ease;
    border: 1px solid #f3f4f6;
}

.dropdown-menu.show {
    display: block;
}

@keyframes dropdownFade {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.dropdown-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 20px;
    color: #4a4a4a;
    text-decoration: none;
    font-size: 14px;
    transition: all 0.2s ease;
}

.dropdown-link i {
    width: 20px;
    color: #6b7280;
    font-size: 15px;
}

.dropdown-link:hover {
    background: <?php echo $couleur_primaire; ?>10;
    color: <?php echo $couleur_primaire; ?>;
}

.dropdown-link:hover i {
    color: <?php echo $couleur_primaire; ?>;
}

.dropdown-link-danger:hover {
    background: #fef2f2;
    color: #dc2626;
}

.dropdown-link-danger:hover i {
    color: #dc2626;
}

.dropdown-divider {
    height: 1px;
    background: #f3f4f6;
    margin: 6px 0;
}

.dropdown-badge {
    margin-left: auto;
    background: #ef4444;
    color: white;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 10px;
    border-radius: 50px;
}

/* ============================================= */
/* MODE SOMBRE - BOUTON */
/* ============================================= */
.btn-darkmode {
    background: transparent;
    border: none;
    padding: 8px 12px;
    border-radius: 50px;
    cursor: pointer;
    transition: all 0.3s ease;
    color: #4a4a4a;
    font-size: 18px;
}

.btn-darkmode:hover {
    background: #f3f4f6;
    color: <?php echo $couleur_primaire; ?>;
}

/* ============================================= */
/* MENU MOBILE - BOUTON TOGGLE */
/* ============================================= */
.navbar-toggle {
    display: none;
    background: transparent;
    border: none;
    padding: 10px;
    cursor: pointer;
    border-radius: 8px;
    transition: background 0.3s ease;
}

.navbar-toggle:hover {
    background: #f3f4f6;
}

.toggle-icon {
    display: flex;
    flex-direction: column;
    gap: 5px;
    width: 25px;
}

.toggle-icon span {
    display: block;
    height: 2.5px;
    background: #1a1a1a;
    border-radius: 3px;
    transition: all 0.3s ease;
}

.toggle-icon span:nth-child(2) {
    width: 20px;
}

/* ============================================= */
/* MENU MOBILE */
/* ============================================= */
.mobile-menu-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 2000;
    display: none;
    backdrop-filter: blur(4px);
}

.mobile-menu-overlay.active {
    display: block;
}

.mobile-menu {
    position: fixed;
    top: 0;
    right: -100%;
    width: 85%;
    max-width: 380px;
    height: 100%;
    background: white;
    z-index: 2001;
    transition: right 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: -5px 0 30px rgba(0,0,0,0.15);
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}

.mobile-menu.active {
    right: 0;
}

.mobile-menu-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid #f3f4f6;
    flex-shrink: 0;
}

.mobile-menu-brand {
    display: flex;
    align-items: center;
    gap: 10px;
}

.mobile-menu-logo {
    height: 35px;
    width: auto;
}

.mobile-menu-brand-name {
    font-weight: 700;
    font-size: 18px;
    color: #1a1a1a;
}

.mobile-menu-close {
    background: transparent;
    border: none;
    font-size: 24px;
    color: #6b7280;
    cursor: pointer;
    padding: 5px;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.mobile-menu-close:hover {
    background: #f3f4f6;
    color: #1a1a1a;
}

/* Profil utilisateur mobile */
.mobile-user-profile {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 16px 20px;
    background: <?php echo $couleur_primaire; ?>10;
    border-bottom: 1px solid #f3f4f6;
}

.mobile-user-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid <?php echo $couleur_primaire; ?>;
}

.mobile-user-info {
    display: flex;
    flex-direction: column;
}

.mobile-user-name {
    font-weight: 600;
    color: #1a1a1a;
    font-size: 16px;
}

.mobile-user-role {
    font-size: 13px;
    color: #6b7280;
    text-transform: capitalize;
}

/* Navigation mobile */
.mobile-menu-nav {
    list-style: none;
    padding: 10px 0;
    margin: 0;
    flex: 1;
    overflow-y: auto;
}

.mobile-nav-item {
    border-bottom: 1px solid #f9fafb;
}

.mobile-nav-item:last-child {
    border-bottom: none;
}

.mobile-nav-link {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 20px;
    color: #4a4a4a;
    text-decoration: none;
    font-size: 15px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.mobile-nav-link i {
    width: 22px;
    color: #6b7280;
    font-size: 18px;
    transition: color 0.2s ease;
}

.mobile-nav-link:hover {
    background: <?php echo $couleur_primaire; ?>10;
    color: <?php echo $couleur_primaire; ?>;
}

.mobile-nav-link:hover i {
    color: <?php echo $couleur_primaire; ?>;
}

.mobile-nav-item.active .mobile-nav-link {
    color: <?php echo $couleur_primaire; ?>;
    background: <?php echo $couleur_primaire; ?>15;
    font-weight: 600;
}

.mobile-nav-item.active .mobile-nav-link i {
    color: <?php echo $couleur_primaire; ?>;
}

.mobile-nav-link-commande {
    background: <?php echo $couleur_primaire; ?> !important;
    color: white !important;
    border-radius: 12px;
    margin: 4px 12px;
    padding: 12px 20px;
    justify-content: center;
}

.mobile-nav-link-commande i {
    color: white !important;
}

.mobile-nav-link-commande:hover {
    background: <?php echo $couleur_primaire; ?> !important;
    color: white !important;
    transform: scale(1.02);
}

.mobile-nav-divider {
    height: 8px;
    background: #f9fafb;
}

.mobile-nav-link-danger {
    color: #dc2626 !important;
}

.mobile-nav-link-danger i {
    color: #dc2626 !important;
}

.mobile-nav-link-danger:hover {
    background: #fef2f2 !important;
}

.mobile-nav-link-whatsapp {
    color: #25d366 !important;
}

.mobile-nav-link-whatsapp i {
    color: #25d366 !important;
}

.mobile-nav-link-whatsapp:hover {
    background: #25d36615 !important;
}

.mobile-nav-link-call {
    color: #007AFF !important;
}

.mobile-nav-link-call i {
    color: #007AFF !important;
}

.mobile-nav-link-call:hover {
    background: #007AFF15 !important;
}

.mobile-nav-link-login {
    color: <?php echo $couleur_primaire; ?> !important;
}

.mobile-nav-link-login i {
    color: <?php echo $couleur_primaire; ?> !important;
}

.mobile-nav-link-register {
    background: <?php echo $couleur_primaire; ?> !important;
    color: white !important;
    border-radius: 12px;
    margin: 4px 12px;
    padding: 12px 20px;
    justify-content: center;
}

.mobile-nav-link-register i {
    color: white !important;
}

.mobile-badge {
    margin-left: auto;
    background: #ef4444;
    color: white;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 10px;
    border-radius: 50px;
}

/* ============================================= */
/* WHATSAPP DANS LA NAVBAR (MOBILE UNIQUEMENT) */
/* ============================================= */
.nav-item-whatsapp-mobile {
    display: none;
}

.nav-link-whatsapp {
    color: #25d366 !important;
    font-size: 20px;
    padding: 8px 12px;
}

.nav-link-whatsapp:hover {
    background: #25d36615 !important;
    color: #25d366 !important;
}

/* ============================================= */
/* RESPONSIVE */
/* ============================================= */
@media (max-width: 1024px) {
    .navbar-search {
        max-width: 300px;
    }
}

@media (max-width: 992px) {
    .navbar-search {
        display: none;
    }
    
    .navbar-nav {
        gap: 0;
    }
    
    .nav-label {
        display: none;
    }
    
    .nav-link {
        padding: 8px 12px;
    }
    
    .nav-link i {
        font-size: 18px;
    }
    
    .user-name {
        display: none;
    }
    
    .user-dropdown {
        padding: 4px 8px 4px 4px;
    }
    
    .nav-link-commande {
        padding: 8px 16px;
    }
    
    .nav-link-commande .nav-label {
        display: inline;
    }
    
    .nav-item-whatsapp-mobile {
        display: block;
    }
}

@media (max-width: 768px) {
    .navbar-wrapper {
        height: 60px;
    }
    
    .brand-logo {
        height: 35px;
    }
    
    .brand-name {
        font-size: 16px;
    }
    
    .brand-slogan {
        font-size: 9px;
    }
    
    .navbar-nav .nav-item:not(.nav-item-user):not(.nav-item-commande):not(.nav-item-whatsapp-mobile):not(.nav-item-darkmode) {
        display: none;
    }
    
    .navbar-toggle {
        display: block;
    }
    
    .nav-link-commande .nav-label {
        display: none;
    }
    
    .nav-link-commande {
        padding: 8px 12px;
    }
    
    .user-name {
        display: none;
    }
    
    .user-dropdown {
        padding: 4px 8px 4px 4px;
    }
    
    .user-avatar {
        width: 30px;
        height: 30px;
    }
}

@media (max-width: 480px) {
    .navbar-wrapper {
        height: 55px;
        gap: 8px;
    }
    
    .brand-logo {
        height: 30px;
    }
    
    .brand-text {
        display: none;
    }
    
    .nav-link {
        padding: 6px 10px;
    }
    
    .nav-link i {
        font-size: 16px;
    }
    
    .btn-darkmode {
        font-size: 16px;
        padding: 6px 8px;
    }
    
    .mobile-menu {
        width: 100%;
        max-width: 100%;
    }
}

/* ============================================= */
/* DARK MODE */
/* ============================================= */
.dark-mode .navbar-main {
    background: #1a1a1a;
    border-bottom-color: #333;
}

.dark-mode .brand-name {
    color: #ffffff;
}

.dark-mode .nav-link {
    color: #b0b0b0;
}

.dark-mode .nav-link:hover {
    color: <?php echo $couleur_primaire; ?>;
}

.dark-mode .nav-item.active .nav-link {
    color: <?php echo $couleur_primaire; ?>;
}

.dark-mode .user-name {
    color: #ffffff;
}

.dark-mode .dropdown-menu {
    background: #2a2a2a;
    border-color: #333;
}

.dark-mode .dropdown-link {
    color: #d0d0d0;
}

.dark-mode .dropdown-link:hover {
    background: <?php echo $couleur_primaire; ?>20;
}

.dark-mode .dropdown-divider {
    background: #333;
}

.dark-mode .search-input {
    background: #2a2a2a;
    border-color: #444;
    color: #ffffff;
}

.dark-mode .search-input:focus {
    border-color: <?php echo $couleur_primaire; ?>;
    background: #333;
}

.dark-mode .btn-darkmode:hover {
    background: #2a2a2a;
}

.dark-mode .user-avatar {
    border-color: <?php echo $couleur_primaire; ?>;
}

.dark-mode .navbar-toggle:hover {
    background: #2a2a2a;
}

.dark-mode .toggle-icon span {
    background: #ffffff;
}

.dark-mode .mobile-menu {
    background: #1a1a1a;
}

.dark-mode .mobile-menu-header {
    border-bottom-color: #333;
}

.dark-mode .mobile-menu-brand-name {
    color: #ffffff;
}

.dark-mode .mobile-nav-link {
    color: #d0d0d0;
}

.dark-mode .mobile-nav-link:hover {
    background: <?php echo $couleur_primaire; ?>20;
}

.dark-mode .mobile-nav-item.active .mobile-nav-link {
    background: <?php echo $couleur_primaire; ?>30;
}

.dark-mode .mobile-nav-divider {
    background: #2a2a2a;
}

.dark-mode .mobile-user-profile {
    background: <?php echo $couleur_primaire; ?>20;
}

.dark-mode .search-suggestions {
    background: #2a2a2a;
    border-color: #333;
}

.dark-mode .mobile-menu-close {
    color: #b0b0b0;
}

.dark-mode .mobile-menu-close:hover {
    background: #2a2a2a;
    color: #ffffff;
}

.dark-mode .badge-notification {
    border-color: #1a1a1a;
}

.dark-mode .dropdown-badge {
    border-color: #1a1a1a;
}
</style>

<!-- ============================================= -->
<!-- SCRIPT POUR LA NAVBAR -->
<!-- ============================================= -->
<script>
$(document).ready(function() {
    'use strict';
    
    // =============================================
    // Gestion du menu mobile
    // =============================================
    const navbarToggle = document.getElementById('navbar-toggle');
    const mobileMenu = document.getElementById('mobile-menu');
    const mobileOverlay = document.getElementById('mobile-menu-overlay');
    const mobileClose = document.getElementById('mobile-menu-close');
    
    function openMobileMenu() {
        if (mobileMenu && mobileOverlay) {
            mobileMenu.classList.add('active');
            mobileOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
            if (navbarToggle) {
                navbarToggle.setAttribute('aria-expanded', 'true');
            }
        }
    }
    
    function closeMobileMenu() {
        if (mobileMenu && mobileOverlay) {
            mobileMenu.classList.remove('active');
            mobileOverlay.classList.remove('active');
            document.body.style.overflow = '';
            if (navbarToggle) {
                navbarToggle.setAttribute('aria-expanded', 'false');
            }
        }
    }
    
    if (navbarToggle) {
        navbarToggle.addEventListener('click', openMobileMenu);
    }
    
    if (mobileClose) {
        mobileClose.addEventListener('click', closeMobileMenu);
    }
    
    if (mobileOverlay) {
        mobileOverlay.addEventListener('click', closeMobileMenu);
    }
    
    // Fermer avec la touche Echap
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && mobileMenu && mobileMenu.classList.contains('active')) {
            closeMobileMenu();
        }
    });
    
    // =============================================
    // Gestion du dropdown utilisateur
    // =============================================
    const userDropdown = document.querySelector('.user-dropdown');
    const dropdownMenu = document.querySelector('.dropdown-menu');
    
    if (userDropdown && dropdownMenu) {
        userDropdown.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropdownMenu.classList.toggle('show');
        });
        
        // Fermer le dropdown en cliquant ailleurs
        document.addEventListener('click', function(e) {
            if (!userDropdown.contains(e.target) && !dropdownMenu.contains(e.target)) {
                dropdownMenu.classList.remove('show');
            }
        });
        
        // Fermer avec Echap
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && dropdownMenu.classList.contains('show')) {
                dropdownMenu.classList.remove('show');
            }
        });
    }
    
    // =============================================
    // Effet de scroll sur la navbar
    // =============================================
    const navbar = document.getElementById('navbar-main');
    let lastScroll = 0;
    
    window.addEventListener('scroll', function() {
        const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
        
        if (currentScroll > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
        
        // Masquer/afficher la navbar en scroll (mobile)
        if (window.innerWidth <= 768) {
            if (currentScroll > lastScroll && currentScroll > 200) {
                navbar.style.transform = 'translateY(-100%)';
            } else {
                navbar.style.transform = 'translateY(0)';
            }
        }
        
        lastScroll = currentScroll;
    });
    
    // =============================================
    // Recherche avec suggestions
    // =============================================
    const searchInput = document.querySelector('.search-input');
    const suggestions = document.getElementById('search-suggestions');
    let searchTimeout;
    
    if (searchInput && suggestions) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                suggestions.classList.remove('active');
                return;
            }
            
            searchTimeout = setTimeout(function() {
                // Simuler des suggestions (à remplacer par appel AJAX)
                const mockSuggestions = [
                    'Livraison colis',
                    'Livraison repas',
                    'Restaurants à Dori',
                    'Boutiques Dori',
                    'Suivi colis'
                ];
                
                const filtered = mockSuggestions.filter(item => 
                    item.toLowerCase().includes(query.toLowerCase())
                );
                
                if (filtered.length > 0) {
                    suggestions.innerHTML = filtered.map(item => 
                        '<div class="suggestion-item" data-value="' + item + '">' + item + '</div>'
                    ).join('');
                    suggestions.classList.add('active');
                    
                    // Cliquer sur une suggestion
                    suggestions.querySelectorAll('.suggestion-item').forEach(item => {
                        item.addEventListener('click', function() {
                            searchInput.value = this.dataset.value;
                            suggestions.classList.remove('active');
                            searchInput.closest('form').submit();
                        });
                    });
                } else {
                    suggestions.classList.remove('active');
                }
            }, 300);
        });
        
        // Cacher les suggestions en cliquant ailleurs
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-wrapper')) {
                suggestions.classList.remove('active');
            }
        });
    }
    
    // =============================================
    // Gestion du mode sombre
    // =============================================
    function toggleDarkMode() {
        document.documentElement.classList.toggle('dark-mode');
        const isDark = document.documentElement.classList.contains('dark-mode');
        const icon = document.querySelector('.darkmode-icon');
        if (icon) {
            icon.className = 'fas ' + (isDark ? 'fa-sun' : 'fa-moon');
        }
        
        // Sauvegarder la préférence via AJAX
        $.ajax({
            url: '<?php echo URL_BASE; ?>api/parametres.php?action=darkmode',
            method: 'POST',
            data: { value: isDark ? 1 : 0 },
            success: function() {
                console.log('Mode sombre sauvegardé: ' + (isDark ? 'activé' : 'désactivé'));
            },
            error: function() {
                console.log('Erreur sauvegarde mode sombre');
            }
        });
    }
    
    // Exposer la fonction globalement pour le header
    window.toggleDarkMode = toggleDarkMode;
    
    // =============================================
    // Détection du mode sombre système
    // =============================================
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');
    const darkModeEnabled = <?php echo $mode_sombre ? 'true' : 'false'; ?>;
    
    // Si le mode sombre n'est pas forcé par les paramètres, on suit le système
    if (!darkModeEnabled) {
        // Si le système est en mode sombre, on l'applique
        if (prefersDark.matches) {
            document.documentElement.classList.add('dark-mode');
            const icon = document.querySelector('.darkmode-icon');
            if (icon) {
                icon.className = 'fas fa-sun';
            }
        }
    }
    
    // Changer automatiquement si le mode système change
    prefersDark.addEventListener('change', function(e) {
        // Ne pas écraser la préférence utilisateur si elle a été définie
        const userPref = localStorage.getItem('darkmode_user_pref');
        if (!userPref) {
            if (e.matches) {
                document.documentElement.classList.add('dark-mode');
                const icon = document.querySelector('.darkmode-icon');
                if (icon) {
                    icon.className = 'fas fa-sun';
                }
            } else {
                document.documentElement.classList.remove('dark-mode');
                const icon = document.querySelector('.darkmode-icon');
                if (icon) {
                    icon.className = 'fas fa-moon';
                }
            }
        }
    });
    
    console.log('✅ DoriExpress-Pro - Navbar chargée');
});
</script>

<!-- ============================================= -->
<!-- STYLES DES SUGGESTIONS DE RECHERCHE -->
<!-- ============================================= -->
<style>
.suggestion-item {
    padding: 10px 18px;
    cursor: pointer;
    transition: background 0.2s ease;
    font-size: 14px;
    color: #1a1a1a;
}

.suggestion-item:hover {
    background: <?php echo $couleur_primaire; ?>10;
    color: <?php echo $couleur_primaire; ?>;
}

.dark-mode .suggestion-item {
    color: #d0d0d0;
}

.dark-mode .suggestion-item:hover {
    background: <?php echo $couleur_primaire; ?>30;
}
</style>

<?php
// =============================================
// FIN DU FICHIER NAVBAR.PHP
// =============================================
?>