<?php
/**
 * =============================================
 * HEADER COMMUN - DoriExpress-Pro
 * =============================================
 * Fichier : includes/header.php
 * Rôle : En-tête HTML commun à toutes les pages
 * Utilisation : require_once 'includes/header.php';
 * =============================================
 */

// Empêcher l'accès direct
if (!defined('DOSSIER_RACINE')) {
    die('Accès direct interdit');
}

// Récupérer les paramètres du site
$nom_site = get_parametre('nom_site', 'DoriExpress-Pro');
$slogan = get_parametre('slogan', 'Votre livraison rapide à Dori');
$description = get_parametre('description', 'Plateforme de livraison professionnelle à Dori');
$couleur_primaire = get_parametre('couleur_primaire', '#00A651');
$couleur_secondaire = get_parametre('couleur_secondaire', '#1A1A1A');
$mode_sombre = get_parametre('mode_sombre', 0);
$logo = get_parametre('logo', 'logo.png');
$favicon = get_parametre('favicon', 'favicon.ico');
$telephone = get_parametre('telephone', '61874528');
$whatsapp = get_parametre('whatsapp', '61874528');

// Récupérer le titre de la page
$page_title = isset($page_title) ? $page_title : 'Accueil';
$page_description = isset($page_description) ? $page_description : $description;
$page_keywords = isset($page_keywords) ? $page_keywords : 'DoriExpress, livraison, Dori, Burkina Faso';

// Vérifier si l'utilisateur est connecté
$is_logged_in = est_connecte();
$user = utilisateur_connecte();
$user_role = $is_logged_in ? $user['role'] : null;
$user_nom = $is_logged_in ? $user['nom'] . ' ' . $user['prenom'] : '';

// Déterminer la page active
$current_page = basename($_SERVER['PHP_SELF']);
$page_active = isset($_GET['page']) ? $_GET['page'] : $current_page;

// Mode maintenance
$maintenance_active = get_parametre('maintenance_active', 0);

// Si maintenance active et pas admin
if ($maintenance_active && !$is_logged_in) {
    header('Location: ' . URL_BASE . 'maintenance.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr" class="<?php echo $mode_sombre ? 'dark-mode' : ''; ?>">
<head>
    <!-- ========================================= -->
    <!-- MÉTADONNÉES DE BASE -->
    <!-- ========================================= -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="theme-color" content="<?php echo $couleur_primaire; ?>">
    <meta name="msapplication-TileColor" content="<?php echo $couleur_primaire; ?>">
    
    <!-- ========================================= -->
    <!-- SEO - MÉTADONNÉES -->
    <!-- ========================================= -->
    <title><?php echo $page_title . ' | ' . $nom_site; ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($page_keywords); ?>">
    <meta name="author" content="<?php echo $nom_site; ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?php echo URL_BASE . $current_page; ?>">
    
    <!-- ========================================= -->
    <!-- OPEN GRAPH (Facebook, LinkedIn) -->
    <!-- ========================================= -->
    <meta property="og:title" content="<?php echo $page_title . ' | ' . $nom_site; ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta property="og:url" content="<?php echo URL_BASE . $current_page; ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?php echo $nom_site; ?>">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:image" content="<?php echo URL_BASE . 'assets/images/og-image.png'; ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    
    <!-- ========================================= -->
    <!-- TWITTER CARDS -->
    <!-- ========================================= -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo $page_title . ' | ' . $nom_site; ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta name="twitter:image" content="<?php echo URL_BASE . 'assets/images/og-image.png'; ?>">
    
    <!-- ========================================= -->
    <!-- ICÔNES ET FAVICON -->
    <!-- ========================================= -->
    <link rel="icon" type="image/x-icon" href="<?php echo URL_BASE . 'assets/images/' . $favicon; ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo URL_BASE . 'assets/images/apple-touch-icon.png'; ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo URL_BASE . 'assets/images/favicon-32x32.png'; ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo URL_BASE . 'assets/images/favicon-16x16.png'; ?>">
    <link rel="manifest" href="<?php echo URL_BASE . 'pwa/manifest.json'; ?>">
    
    <!-- ========================================= -->
    <!-- FONTS (Google Fonts) -->
    <!-- ========================================= -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- ========================================= -->
    <!-- FONT AWESOME (Icônes) -->
    <!-- ========================================= -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- ========================================= -->
    <!-- ANIMATE.CSS (Animations) -->
    <!-- ========================================= -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <!-- ========================================= -->
    <!-- SWEETALERT2 (Alertes modernes) -->
    <!-- ========================================= -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- ========================================= -->
    <!-- CSS PERSONNALISÉ -->
    <!-- ========================================= -->
    <link rel="stylesheet" href="<?php echo URL_BASE . 'assets/css/style.css'; ?>">
    <link rel="stylesheet" href="<?php echo URL_BASE . 'assets/css/responsive.css'; ?>">
    <link rel="stylesheet" href="<?php echo URL_BASE . 'assets/css/animations.css'; ?>">
    <?php if ($mode_sombre): ?>
    <link rel="stylesheet" href="<?php echo URL_BASE . 'assets/css/dark-mode.css'; ?>">
    <?php endif; ?>
    
    <!-- ========================================= -->
    <!-- STYLES DYNAMIQUES -->
    <!-- ========================================= -->
    <style>
        :root {
            --couleur-primaire: <?php echo $couleur_primaire; ?>;
            --couleur-secondaire: <?php echo $couleur_secondaire; ?>;
            --couleur-primaire-light: <?php echo $couleur_primaire; ?>33;
            --couleur-primaire-dark: <?php echo $couleur_primaire; ?>cc;
        }
        
        /* Personnalisation du scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        ::-webkit-scrollbar-thumb {
            background: var(--couleur-primaire);
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--couleur-primaire-dark);
        }
        
        /* Loader global */
        .loader-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s ease;
        }
        .loader-wrapper.loaded {
            opacity: 0;
            pointer-events: none;
        }
        .loader {
            width: 60px;
            height: 60px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--couleur-primaire);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Bouton WhatsApp flottant */
        .whatsapp-float {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #25D366;
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.4);
            z-index: 1000;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .whatsapp-float:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 25px rgba(37, 211, 102, 0.6);
            color: white;
        }
        
        /* Bouton appel flottant */
        .call-float {
            position: fixed;
            bottom: 100px;
            right: 30px;
            background: #007AFF;
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            box-shadow: 0 4px 15px rgba(0, 122, 255, 0.4);
            z-index: 1000;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .call-float:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 25px rgba(0, 122, 255, 0.6);
            color: white;
        }
        
        /* Mode sombre */
        .dark-mode .loader-wrapper {
            background: #1a1a1a;
        }
        .dark-mode ::-webkit-scrollbar-track {
            background: #2a2a2a;
        }
        .dark-mode ::-webkit-scrollbar-thumb {
            background: var(--couleur-primaire);
        }
    </style>
    
    <!-- ========================================= -->
    <!-- SCRIPT POUR LE LOADER -->
    <!-- ========================================= -->
    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                document.querySelector('.loader-wrapper')?.classList.add('loaded');
            }, 500);
        });
    </script>
</head>
<body>
    <!-- ========================================= -->
    <!-- LOADER -->
    <!-- ========================================= -->
    <div class="loader-wrapper">
        <div class="loader"></div>
    </div>
    
    <!-- ========================================= -->
    <!-- WHATSAPP & APPEL FLOATING BUTTONS -->
    <!-- ========================================= -->
    <a href="https://wa.me/226<?php echo $whatsapp; ?>?text=Bonjour%20DoriExpress-Pro%2C%20j%27ai%20besoin%20d%27aide%20pour%20ma%20commande" 
       class="whatsapp-float" 
       target="_blank" 
       aria-label="WhatsApp"
       title="Contactez-nous sur WhatsApp">
        <i class="fab fa-whatsapp"></i>
    </a>
    
    <a href="tel:+226<?php echo $telephone; ?>" 
       class="call-float" 
       aria-label="Appeler"
       title="Appelez-nous">
        <i class="fas fa-phone"></i>
    </a>
    
    <!-- ========================================= -->
    <!-- HEADER / NAVIGATION -->
    <!-- ========================================= -->
    <header id="main-header" class="main-header">
        <div class="container">
            <nav class="navbar" role="navigation" aria-label="Navigation principale">
                <!-- Logo -->
                <div class="navbar-brand">
                    <a href="<?php echo URL_BASE; ?>" class="brand-link">
                        <img src="<?php echo URL_BASE . 'assets/images/' . $logo; ?>" 
                             alt="<?php echo $nom_site; ?>" 
                             class="brand-logo"
                             height="50">
                        <span class="brand-text">
                            <span class="brand-name"><?php echo $nom_site; ?></span>
                            <span class="brand-slogan"><?php echo $slogan; ?></span>
                        </span>
                    </a>
                </div>
                
                <!-- Navigation principale -->
                <ul class="navbar-nav" id="main-nav">
                    <li class="nav-item <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                        <a href="<?php echo URL_BASE; ?>" class="nav-link">
                            <i class="fas fa-home"></i> Accueil
                        </a>
                    </li>
                    <li class="nav-item <?php echo $current_page == 'services.php' ? 'active' : ''; ?>">
                        <a href="<?php echo URL_BASE . 'services.php'; ?>" class="nav-link">
                            <i class="fas fa-concierge-bell"></i> Services
                        </a>
                    </li>
                    <li class="nav-item <?php echo $current_page == 'commande.php' ? 'active' : ''; ?>">
                        <a href="<?php echo URL_BASE . 'commande.php'; ?>" class="nav-link btn-commander">
                            <i class="fas fa-shopping-cart"></i> Commander
                        </a>
                    </li>
                    <li class="nav-item <?php echo $current_page == 'suivi.php' ? 'active' : ''; ?>">
                        <a href="<?php echo URL_BASE . 'suivi.php'; ?>" class="nav-link">
                            <i class="fas fa-map-marker-alt"></i> Suivre
                        </a>
                    </li>
                    <li class="nav-item <?php echo $current_page == 'partenaires.php' ? 'active' : ''; ?>">
                        <a href="<?php echo URL_BASE . 'partenaires.php'; ?>" class="nav-link">
                            <i class="fas fa-store"></i> Partenaires
                        </a>
                    </li>
                    <li class="nav-item <?php echo $current_page == 'blog.php' ? 'active' : ''; ?>">
                        <a href="<?php echo URL_BASE . 'blog.php'; ?>" class="nav-link">
                            <i class="fas fa-newspaper"></i> Blog
                        </a>
                    </li>
                    <li class="nav-item <?php echo $current_page == 'contact.php' ? 'active' : ''; ?>">
                        <a href="<?php echo URL_BASE . 'contact.php'; ?>" class="nav-link">
                            <i class="fas fa-envelope"></i> Contact
                        </a>
                    </li>
                    
                    <!-- Menu utilisateur -->
                    <?php if ($is_logged_in): ?>
                        <li class="nav-item dropdown">
                            <a href="#" class="nav-link user-dropdown" data-toggle="dropdown">
                                <img src="<?php echo URL_BASE . 'uploads/profils/' . ($user['photo'] ?? 'default.jpg'); ?>" 
                                     alt="<?php echo $user_nom; ?>" 
                                     class="user-avatar">
                                <span class="user-name"><?php echo $user_nom; ?></span>
                                <i class="fas fa-chevron-down"></i>
                            </a>
                            <ul class="dropdown-menu">
                                <?php if ($user_role == 'createur'): ?>
                                    <li><a href="<?php echo URL_BASE . 'admin/createur.php'; ?>">
                                        <i class="fas fa-crown"></i> Dashboard Créateur
                                    </a></li>
                                <?php elseif ($user_role == 'admin'): ?>
                                    <li><a href="<?php echo URL_BASE . 'admin/dashboard.php'; ?>">
                                        <i class="fas fa-tachometer-alt"></i> Dashboard Admin
                                    </a></li>
                                <?php elseif ($user_role == 'client'): ?>
                                    <li><a href="<?php echo URL_BASE . 'client/dashboard.php'; ?>">
                                        <i class="fas fa-tachometer-alt"></i> Mon espace
                                    </a></li>
                                <?php elseif ($user_role == 'livreur'): ?>
                                    <li><a href="<?php echo URL_BASE . 'livreur/dashboard.php'; ?>">
                                        <i class="fas fa-tachometer-alt"></i> Dashboard Livreur
                                    </a></li>
                                <?php elseif ($user_role == 'partenaire'): ?>
                                    <li><a href="<?php echo URL_BASE . 'partenaire/dashboard.php'; ?>">
                                        <i class="fas fa-tachometer-alt"></i> Dashboard Partenaire
                                    </a></li>
                                <?php endif; ?>
                                <li><a href="<?php echo URL_BASE . 'profil.php'; ?>">
                                    <i class="fas fa-user"></i> Mon profil
                                </a></li>
                                <li><a href="<?php echo URL_BASE . 'notifications.php'; ?>">
                                    <i class="fas fa-bell"></i> Notifications
                                </a></li>
                                <li><a href="<?php echo URL_BASE . 'logout.php'; ?>">
                                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                                </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a href="<?php echo URL_BASE . 'login.php'; ?>" class="nav-link btn-connexion">
                                <i class="fas fa-sign-in-alt"></i> Connexion
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo URL_BASE . 'register.php'; ?>" class="nav-link btn-inscription">
                                <i class="fas fa-user-plus"></i> S'inscrire
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Bouton mode sombre -->
                    <li class="nav-item">
                        <button class="btn-darkmode" onclick="toggleDarkMode()" aria-label="Mode sombre">
                            <i class="fas <?php echo $mode_sombre ? 'fa-sun' : 'fa-moon'; ?>"></i>
                        </button>
                    </li>
                </ul>
                
                <!-- Bouton menu mobile -->
                <button class="navbar-toggle" id="navbar-toggle" aria-label="Menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </nav>
        </div>
    </header>
    
    <!-- ========================================= -->
    <!-- MOBILE MENU -->
    <!-- ========================================= -->
    <div class="mobile-menu" id="mobile-menu">
        <div class="mobile-menu-header">
            <span class="mobile-menu-title">Menu</span>
            <button class="mobile-menu-close" id="mobile-menu-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <ul class="mobile-menu-nav">
            <li><a href="<?php echo URL_BASE; ?>"><i class="fas fa-home"></i> Accueil</a></li>
            <li><a href="<?php echo URL_BASE . 'services.php'; ?>"><i class="fas fa-concierge-bell"></i> Services</a></li>
            <li><a href="<?php echo URL_BASE . 'commande.php'; ?>" class="mobile-btn-commander"><i class="fas fa-shopping-cart"></i> Commander</a></li>
            <li><a href="<?php echo URL_BASE . 'suivi.php'; ?>"><i class="fas fa-map-marker-alt"></i> Suivre</a></li>
            <li><a href="<?php echo URL_BASE . 'partenaires.php'; ?>"><i class="fas fa-store"></i> Partenaires</a></li>
            <li><a href="<?php echo URL_BASE . 'blog.php'; ?>"><i class="fas fa-newspaper"></i> Blog</a></li>
            <li><a href="<?php echo URL_BASE . 'contact.php'; ?>"><i class="fas fa-envelope"></i> Contact</a></li>
            
            <?php if ($is_logged_in): ?>
                <li><hr></li>
                <li><a href="<?php echo URL_BASE . 'profil.php'; ?>"><i class="fas fa-user"></i> Mon profil</a></li>
                <li><a href="<?php echo URL_BASE . 'notifications.php'; ?>"><i class="fas fa-bell"></i> Notifications</a></li>
                <li><a href="<?php echo URL_BASE . 'logout.php'; ?>"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
            <?php else: ?>
                <li><hr></li>
                <li><a href="<?php echo URL_BASE . 'login.php'; ?>" class="mobile-btn-connexion"><i class="fas fa-sign-in-alt"></i> Connexion</a></li>
                <li><a href="<?php echo URL_BASE . 'register.php'; ?>" class="mobile-btn-inscription"><i class="fas fa-user-plus"></i> S'inscrire</a></li>
            <?php endif; ?>
        </ul>
    </div>
    <div class="mobile-overlay" id="mobile-overlay"></div>
    
    <!-- ========================================= -->
    <!-- BARRE D'INFORMATION SUPÉRIEURE -->
    <!-- ========================================= -->
    <?php if (isset($show_info_bar) && $show_info_bar): ?>
    <div class="info-bar">
        <div class="container">
            <div class="info-bar-content">
                <span><i class="fas fa-phone"></i> <?php echo $telephone; ?></span>
                <span><i class="fab fa-whatsapp"></i> <?php echo $whatsapp; ?></span>
                <span><i class="fas fa-clock"></i> Livraison 7j/7 de 07h à 22h</span>
                <span><i class="fas fa-map-marker-alt"></i> Dori, Burkina Faso</span>
                <?php if ($is_logged_in): ?>
                    <span class="info-bar-user"><i class="fas fa-user"></i> <?php echo $user_nom; ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- ========================================= -->
    <!-- SCRIPT POUR LE MENU MOBILE -->
    <!-- ========================================= -->
    <script>
        // Toggle menu mobile
        const navbarToggle = document.getElementById('navbar-toggle');
        const mobileMenu = document.getElementById('mobile-menu');
        const mobileOverlay = document.getElementById('mobile-overlay');
        const mobileClose = document.getElementById('mobile-menu-close');
        
        function openMobileMenu() {
            mobileMenu.classList.add('active');
            mobileOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeMobileMenu() {
            mobileMenu.classList.remove('active');
            mobileOverlay.classList.remove('active');
            document.body.style.overflow = '';
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
        
        // Dropdown utilisateur
        document.querySelectorAll('.dropdown').forEach(dropdown => {
            const trigger = dropdown.querySelector('.dropdown-toggle');
            const menu = dropdown.querySelector('.dropdown-menu');
            
            if (trigger && menu) {
                trigger.addEventListener('click', function(e) {
                    e.preventDefault();
                    menu.classList.toggle('show');
                });
                
                // Fermer au clic en dehors
                document.addEventListener('click', function(e) {
                    if (!dropdown.contains(e.target)) {
                        menu.classList.remove('show');
                    }
                });
            }
        });
        
        // Toggle dark mode
        function toggleDarkMode() {
            document.documentElement.classList.toggle('dark-mode');
            const isDark = document.documentElement.classList.contains('dark-mode');
            const icon = document.querySelector('.btn-darkmode i');
            if (icon) {
                icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
            }
            
            // Sauvegarder la préférence
            fetch('<?php echo URL_BASE; ?>api/parametres.php?action=darkmode&value=' + (isDark ? 1 : 0))
                .catch(err => console.log('Erreur sauvegarde mode sombre'));
        }
        
        // Détection automatique du mode sombre système
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            // Appliquer automatiquement si l'utilisateur n'a pas de préférence
            // La préférence utilisateur est sauvegardée dans les paramètres
        }
        
        console.log('✅ DoriExpress-Pro - Header chargé');
    </script>

<!-- ========================================= -->
<!-- CONTENU PRINCIPAL -->
<!-- ========================================= -->
<main id="main-content" class="main-content">