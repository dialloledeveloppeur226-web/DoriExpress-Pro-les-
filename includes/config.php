<?php
/**
 * =============================================
 * CONFIGURATION CENTRALE - DoriExpress-Pro
 * =============================================
 * Fichier : includes/config.php
 * Rôle : Configuration globale du site
 * Toutes les constantes et paramètres centraux
 * =============================================
 */

// =============================================
// 1. CONFIGURATION DE L'ENVIRONNEMENT
// =============================================

/**
 * Mode de développement
 * - 'development' : Affichage des erreurs, logs détaillés
 * - 'production'  : Erreurs masquées, logs limités
 * - 'test'        : Mode test
 */
define('ENVIRONNEMENT', 'development');

/**
 * URL de base du site
 * À modifier selon votre hébergement
 */
define('URL_BASE', 'http://localhost/DoriExpress-Pro/');
// define('URL_BASE', 'https://www.doriexpress.bf/'); // En production

/**
 * Chemins des dossiers
 */
define('DOSSIER_RACINE', dirname(__DIR__) . '/');
define('DOSSIER_INCLUDES', DOSSIER_RACINE . 'includes/');
define('DOSSIER_ADMIN', DOSSIER_RACINE . 'admin/');
define('DOSSIER_CLIENT', DOSSIER_RACINE . 'client/');
define('DOSSIER_LIVREUR', DOSSIER_RACINE . 'livreur/');
define('DOSSIER_PARTENAIRE', DOSSIER_RACINE . 'partenaire/');
define('DOSSIER_ASSETS', DOSSIER_RACINE . 'assets/');
define('DOSSIER_UPLOADS', DOSSIER_RACINE . 'uploads/');
define('DOSSIER_MODULES', DOSSIER_RACINE . 'modules/');
define('DOSSIER_API', DOSSIER_RACINE . 'api/');
define('DOSSIER_PWA', DOSSIER_RACINE . 'pwa/');

// =============================================
// 2. CONFIGURATION DE LA BASE DE DONNÉES
// =============================================

/**
 * Informations de connexion MySQL
 * À MODIFIER selon votre hébergement
 */
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'dori_express');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Options PDO pour la connexion sécurisée
 */
define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
]);

// =============================================
// 3. CONFIGURATION DE LA SESSION
// =============================================

/**
 * Durée de vie de la session (en secondes)
 * 24 heures = 86400 secondes
 */
define('SESSION_DUREE', 86400);

/**
 * Nom de la session (pour éviter les conflits)
 */
define('SESSION_NOM', 'DORIEXPRESS_SESSION');

/**
 * Sécurité de la session
 */
define('SESSION_SECURISE', true); // Utiliser httponly et secure

// =============================================
// 4. CONFIGURATION DE LA SÉCURITÉ
// =============================================

/**
 * Salt pour le hachage des mots de passe
 * À MODIFIER pour chaque installation
 */
define('SALT_GLOBAL', 'votre_salt_unique_et_securise_123456789!@#');

/**
 * Clé pour le chiffrement des données sensibles
 * À MODIFIER pour chaque installation
 */
define('CLE_CHIFFREMENT', 'votre_cle_chiffrement_32_caracteres_123456789');

/**
 * Durée de validité des tokens CSRF (en secondes)
 */
define('CSRF_DUREE', 3600);

/**
 * Nombre maximum de tentatives de connexion
 */
define('TENTATIVES_CONNEXION_MAX', 5);

/**
 * Durée de blocage après trop de tentatives (en minutes)
 */
define('BLOCAGE_DUREE_MINUTES', 15);

// =============================================
// 5. CONFIGURATION DES UPLOADS
// =============================================

/**
 * Taille maximale des fichiers (en octets)
 * 5 Mo = 5242880 octets
 * 10 Mo = 10485760 octets
 */
define('UPLOAD_MAX_SIZE', 5242880);
define('UPLOAD_MAX_SIZE_IMAGE', 5242880);
define('UPLOAD_MAX_SIZE_DOCUMENT', 10485760);

/**
 * Types de fichiers autorisés
 */
define('UPLOAD_TYPES_IMAGE', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
define('UPLOAD_TYPES_DOCUMENT', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt']);
define('UPLOAD_TYPES_ALL', array_merge(UPLOAD_TYPES_IMAGE, UPLOAD_TYPES_DOCUMENT));

/**
 * Dossiers d'upload
 */
define('UPLOAD_PROFILS', DOSSIER_UPLOADS . 'profils/');
define('UPLOAD_PRODUITS', DOSSIER_UPLOADS . 'produits/');
define('UPLOAD_COMMANDES', DOSSIER_UPLOADS . 'commandes/');
define('UPLOAD_DOCUMENTS', DOSSIER_UPLOADS . 'documents/');
define('UPLOAD_PARTENAIRES', DOSSIER_UPLOADS . 'partenaires/');
define('UPLOAD_ARTICLES', DOSSIER_UPLOADS . 'articles/');

// =============================================
// 6. CONFIGURATION DES API EXTERNES
// =============================================

/**
 * Google Maps API
 * Remplacez par votre propre clé
 */
define('GOOGLE_MAPS_API_KEY', 'VOTRE_CLE_GOOGLE_MAPS');

/**
 * Orange Money API
 */
define('ORANGE_MONEY_API_URL', 'https://api.orange.com/orange-money-webpay/');
define('ORANGE_MONEY_API_KEY', 'VOTRE_CLE_ORANGE_MONEY');
define('ORANGE_MONEY_MERCHANT_ID', 'VOTRE_MERCHANT_ID');

/**
 * Moov Money API
 */
define('MOOV_MONEY_API_URL', 'https://api.moov.africa/moov-money/');
define('MOOV_MONEY_API_KEY', 'VOTRE_CLE_MOOV_MONEY');
define('MOOV_MONEY_MERCHANT_ID', 'VOTRE_MERCHANT_ID_MOOV');

/**
 * WhatsApp Business API
 */
define('WHATSAPP_NUMBER', '61874528');
define('WHATSAPP_API_URL', 'https://graph.facebook.com/v17.0/');
define('WHATSAPP_API_TOKEN', 'VOTRE_TOKEN_WHATSAPP');

/**
 * Configuration SMTP (Email)
 */
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_SECURE', 'tls');
define('SMTP_FROM_EMAIL', 'contact@doriexpress.bf');
define('SMTP_FROM_NAME', 'DoriExpress-Pro');

// =============================================
// 7. CONFIGURATION DES PARAMÈTRES PAR DÉFAUT
// =============================================

/**
 * Valeurs par défaut (utilisées si les paramètres BDD sont indisponibles)
 */
define('DEFAUT_PARAMS', [
    // Entreprise
    'nom_site' => 'DoriExpress-Pro',
    'slogan' => 'Votre livraison rapide à Dori',
    'telephone' => '61874528',
    'whatsapp' => '61874528',
    'email' => 'contact@doriexpress.bf',
    'adresse' => 'Dori, Burkina Faso',
    
    // Tarifs
    'prix_base' => 500,
    'prix_km' => 200,
    'prix_kg' => 50,
    'frais_express' => 1000,
    'frais_minimum' => 500,
    'seuil_retrait_livreur' => 10000,
    
    // Commissions
    'commission_plateforme' => 15,
    'commission_restaurant' => 15,
    'commission_boutique' => 20,
    'commission_livreur' => 80,
    
    // Livraison
    'rayon_livraison_km' => 15,
    'temps_livraison_moyen' => 45,
    'livraison_express_active' => 1,
    'horaires_ouverture' => '07:00',
    'horaires_fermeture' => '22:00',
    
    // Design
    'couleur_primaire' => '#00A651',
    'couleur_secondaire' => '#1A1A1A',
    'couleur_fond' => '#FFFFFF',
    'mode_sombre' => 0,
    
    // Sécurité
    'tentatives_connexion_max' => 5,
    'blocage_temps_minutes' => 15,
    'sessions_expiration_heures' => 24,
    'logs_conservation_jours' => 30,
    
    // PWA
    'pwa_active' => 1,
    'pwa_nom' => 'DoriExpress-Pro',
    'pwa_couleur_theme' => '#00A651',
    
    // Modules
    'module_chat_active' => 1,
    'module_gps_active' => 1,
    'module_wallet_active' => 1,
    'module_blog_active' => 1,
    'module_promotions_active' => 1,
    'module_parrainage_active' => 1,
    'module_ia_active' => 1,
    'module_marketplace_active' => 1,
    
    // IA
    'ia_estimation_prix_active' => 1,
    'ia_anti_fraude_active' => 1,
    'ia_prevision_active' => 1,
    'ia_analyse_revenus_active' => 1,
]);

// =============================================
// 8. CONFIGURATION DES RÔLES ET PERMISSIONS
// =============================================

/**
 * Liste des rôles disponibles
 */
define('ROLES', [
    'createur' => 'Super Administrateur',
    'admin' => 'Administrateur',
    'client' => 'Client',
    'livreur' => 'Livreur',
    'partenaire' => 'Partenaire',
    'support' => 'Support Client',
]);

/**
 * Permissions par défaut par rôle
 */
define('PERMISSIONS_DEFAUT', [
    'createur' => ['*'], // Tous les droits
    'admin' => [
        'voir_dashboard', 'voir_utilisateurs', 'modifier_utilisateurs',
        'voir_commandes', 'modifier_commandes', 'voir_paiements',
        'modifier_paiements', 'voir_finance', 'voir_parametres',
        'modifier_parametres', 'exporter_donnees', 'gerer_modules'
    ],
    'client' => [
        'voir_dashboard', 'voir_commandes', 'creer_commandes',
        'voir_wallet', 'voir_avis', 'voir_promotions'
    ],
    'livreur' => [
        'voir_dashboard', 'voir_missions', 'accepter_missions',
        'voir_gains', 'voir_wallet', 'demander_retrait'
    ],
    'partenaire' => [
        'voir_dashboard', 'voir_commandes', 'creer_produits',
        'modifier_produits', 'voir_statistiques', 'voir_paiements'
    ],
    'support' => [
        'voir_tickets', 'repondre_tickets', 'voir_utilisateurs'
    ]
]);

// =============================================
// 9. CONFIGURATION DES MODULES
// =============================================

/**
 * Liste des modules disponibles
 */
define('MODULES_DISPONIBLES', [
    'paiement' => [
        'nom' => 'Paiement',
        'version' => '1.0.0',
        'description' => 'Gestion des paiements Orange Money et Moov Money',
        'actif_par_defaut' => true
    ],
    'gps' => [
        'nom' => 'GPS',
        'version' => '1.0.0',
        'description' => 'Suivi GPS et cartographie',
        'actif_par_defaut' => true
    ],
    'chat' => [
        'nom' => 'Chat',
        'version' => '1.0.0',
        'description' => 'Messagerie en temps réel',
        'actif_par_defaut' => true
    ],
    'ia' => [
        'nom' => 'Intelligence Artificielle',
        'version' => '1.0.0',
        'description' => 'Estimation prix, anti-fraude, prévisions',
        'actif_par_defaut' => true
    ],
    'marketplace' => [
        'nom' => 'Marketplace',
        'version' => '1.0.0',
        'description' => 'Gestion des partenaires, restaurants et boutiques',
        'actif_par_defaut' => true
    ],
    'wallet' => [
        'nom' => 'Wallet',
        'version' => '1.0.0',
        'description' => 'Portefeuille interne',
        'actif_par_defaut' => true
    ],
    'blog' => [
        'nom' => 'Blog',
        'version' => '1.0.0',
        'description' => 'Blog et actualités',
        'actif_par_defaut' => true
    ],
    'promotions' => [
        'nom' => 'Promotions',
        'version' => '1.0.0',
        'description' => 'Promotions et codes promo',
        'actif_par_defaut' => true
    ],
    'parrainage' => [
        'nom' => 'Parrainage',
        'version' => '1.0.0',
        'description' => 'Programme de parrainage',
        'actif_par_defaut' => true
    ],
    'fidelite' => [
        'nom' => 'Fidélité',
        'version' => '1.0.0',
        'description' => 'Programme de fidélité',
        'actif_par_defaut' => true
    ],
    'exports' => [
        'nom' => 'Exports',
        'version' => '1.0.0',
        'description' => 'Exports PDF, Excel, CSV',
        'actif_par_defaut' => true
    ]
]);

// =============================================
// 10. CONFIGURATION DES TYPES DE COMMANDES
// =============================================

/**
 * Services de livraison disponibles
 */
define('TYPES_SERVICE', [
    'colis' => [
        'nom' => 'Livraison de colis',
        'icone' => 'fa-box',
        'description' => 'Livraison de colis et paquets',
        'prix_supplement' => 0
    ],
    'repas' => [
        'nom' => 'Livraison de repas',
        'icone' => 'fa-utensils',
        'description' => 'Livraison de plats cuisinés',
        'prix_supplement' => 0
    ],
    'courses' => [
        'nom' => 'Livraison de courses',
        'icone' => 'fa-shopping-bag',
        'description' => 'Livraison de courses',
        'prix_supplement' => 0
    ],
    'express' => [
        'nom' => 'Livraison express',
        'icone' => 'fa-rocket',
        'description' => 'Livraison rapide en priorité',
        'prix_supplement' => 1000
    ],
    'depot' => [
        'nom' => 'Dépôt de colis',
        'icone' => 'fa-warehouse',
        'description' => 'Dépôt et retrait de colis',
        'prix_supplement' => 0
    ],
    'programme' => [
        'nom' => 'Livraison programmée',
        'icone' => 'fa-calendar-alt',
        'description' => 'Livraison à date et heure choisies',
        'prix_supplement' => 500
    ]
]);

// =============================================
// 11. CONFIGURATION DES STATUTS
// =============================================

/**
 * Statuts des commandes
 */
define('STATUTS_COMMANDE', [
    'brouillon' => ['label' => 'Brouillon', 'couleur' => 'gray'],
    'en_attente_paiement' => ['label' => 'En attente de paiement', 'couleur' => 'yellow'],
    'payee' => ['label' => 'Payée', 'couleur' => 'green'],
    'acceptee' => ['label' => 'Acceptée', 'couleur' => 'blue'],
    'preparation' => ['label' => 'En préparation', 'couleur' => 'indigo'],
    'livreur_assigne' => ['label' => 'Livreur assigné', 'couleur' => 'purple'],
    'recuperation' => ['label' => 'Récupération en cours', 'couleur' => 'pink'],
    'en_livraison' => ['label' => 'En livraison', 'couleur' => 'orange'],
    'arrivee' => ['label' => 'Arrivée destination', 'couleur' => 'teal'],
    'livree' => ['label' => 'Livrée', 'couleur' => 'green'],
    'annulee' => ['label' => 'Annulée', 'couleur' => 'red'],
    'remboursee' => ['label' => 'Remboursée', 'couleur' => 'red'],
    'litige' => ['label' => 'Litige ouvert', 'couleur' => 'red'],
    'archivee' => ['label' => 'Archivée', 'couleur' => 'gray']
]);

/**
 * Statuts des paiements
 */
define('STATUTS_PAIEMENT', [
    'en_attente' => ['label' => 'En attente', 'couleur' => 'yellow'],
    'en_cours' => ['label' => 'En cours', 'couleur' => 'blue'],
    'valide' => ['label' => 'Validé', 'couleur' => 'green'],
    'echoue' => ['label' => 'Échoué', 'couleur' => 'red'],
    'rembourse' => ['label' => 'Remboursé', 'couleur' => 'gray']
]);

// =============================================
// 12. FONCTIONS UTILES DE CONFIGURATION
// =============================================

/**
 * Récupère la valeur d'un paramètre depuis la base de données
 * Cette fonction est appelée après la connexion à la BDD
 * Elle sert de pont entre config.php et la table parametres
 */
function get_parametre($cle, $defaut = null) {
    global $db;
    
    // Si la BDD n'est pas encore connectée, retourner la valeur par défaut
    if (!isset($db) || !$db) {
        return isset(DEFAUT_PARAMS[$cle]) ? DEFAUT_PARAMS[$cle] : $defaut;
    }
    
    try {
        $stmt = $db->prepare("SELECT valeur FROM parametres WHERE cle = ?");
        $stmt->execute([$cle]);
        $result = $stmt->fetch();
        
        if ($result) {
            return $result['valeur'];
        }
        
        return isset(DEFAUT_PARAMS[$cle]) ? DEFAUT_PARAMS[$cle] : $defaut;
    } catch (Exception $e) {
        return isset(DEFAUT_PARAMS[$cle]) ? DEFAUT_PARAMS[$cle] : $defaut;
    }
}

/**
 * Récupère tous les paramètres d'une catégorie
 */
function get_parametres_categorie($categorie) {
    global $db;
    
    if (!isset($db) || !$db) {
        return [];
    }
    
    try {
        $stmt = $db->prepare("SELECT cle, valeur FROM parametres WHERE categorie = ?");
        $stmt->execute([$categorie]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Vérifie si un module est actif
 */
function module_actif($module) {
    return get_parametre('module_' . $module . '_active', 1) == 1;
}

/**
 * Vérifie si le mode maintenance est actif
 */
function maintenance_active() {
    return get_parametre('maintenance_active', 0) == 1;
}

/**
 * Récupère le chemin absolu d'un fichier
 */
function chemin_fichier($chemin) {
    return DOSSIER_RACINE . ltrim($chemin, '/');
}

/**
 * Récupère l'URL complète d'un fichier
 */
function url_fichier($chemin) {
    return URL_BASE . ltrim($chemin, '/');
}

// =============================================
// 13. CONFIGURATION DES ERREURS
// =============================================

/**
 * Affichage des erreurs selon l'environnement
 */
if (ENVIRONNEMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else if (ENVIRONNEMENT === 'test') {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
} else {
    // Production
    error_reporting(0);
    ini_set('display_errors', 0);
}

/**
 * Fichier de log des erreurs
 */
ini_set('log_errors', 1);
ini_set('error_log', DOSSIER_RACINE . 'storage/logs/php_errors.log');

// =============================================
// 14. CONFIGURATION DE LA LOCALISATION
// =============================================

/**
 * Fuseau horaire par défaut
 */
date_default_timezone_set('Africa/Ouagadougou');

/**
 * Paramètres de localisation
 */
setlocale(LC_TIME, 'fr_FR.utf8', 'fr_FR', 'fr');

// =============================================
// 15. CONFIGURATION DE LA SÉCURITÉ PHP
// =============================================

/**
 * Désactiver les fonctionnalités dangereuses
 */
ini_set('allow_url_fopen', 0);
ini_set('allow_url_include', 0);

/**
 * Paramètres de session sécurisés
 */
if (ENVIRONNEMENT === 'production') {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
}

/**
 * Taille maximale des données POST
 */
ini_set('post_max_size', '10M');
ini_set('upload_max_filesize', '10M');
ini_set('max_file_uploads', 20);
ini_set('max_execution_time', 300);
ini_set('memory_limit', '256M');

// =============================================
// 16. CONSTANTES DE VERSION
// =============================================

/**
 * Version du système
 */
define('VERSION_SYSTEME', '1.0.0');
define('VERSION_DATE', '2026-08-06');
define('VERSION_NOM', 'DoriExpress-Pro');

// =============================================
// 17. CONFIGURATION DE LA PWA
// =============================================

/**
 * Manifest PWA
 */
define('PWA_MANIFEST', [
    'name' => 'DoriExpress-Pro',
    'short_name' => 'DoriExpress',
    'description' => 'Plateforme de livraison rapide à Dori',
    'start_url' => URL_BASE,
    'display' => 'standalone',
    'background_color' => '#FFFFFF',
    'theme_color' => '#00A651',
    'icons' => [
        ['src' => 'assets/icons/icon-72x72.png', 'sizes' => '72x72', 'type' => 'image/png'],
        ['src' => 'assets/icons/icon-96x96.png', 'sizes' => '96x96', 'type' => 'image/png'],
        ['src' => 'assets/icons/icon-128x128.png', 'sizes' => '128x128', 'type' => 'image/png'],
        ['src' => 'assets/icons/icon-144x144.png', 'sizes' => '144x144', 'type' => 'image/png'],
        ['src' => 'assets/icons/icon-152x152.png', 'sizes' => '152x152', 'type' => 'image/png'],
        ['src' => 'assets/icons/icon-192x192.png', 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => 'assets/icons/icon-384x384.png', 'sizes' => '384x384', 'type' => 'image/png'],
        ['src' => 'assets/icons/icon-512x512.png', 'sizes' => '512x512', 'type' => 'image/png']
    ]
]);

// =============================================
// 18. CONFIGURATION DES PAGES PUBLIQUES
// =============================================

/**
 * Liste des pages publiques (sans authentification)
 */
define('PAGES_PUBLIQUES', [
    'index', 'about', 'services', 'tarifs', 'commande', 'suivi',
    'contact', 'faq', 'blog', 'actualites', 'promotions', 'recrutement',
    'partenaires', 'login', 'register', 'logout', 'politique', 'conditions',
    '404', 'maintenance', 'depot-colis', 'boutique', 'restaurants'
]);

/**
 * Liste des pages accessibles selon le rôle
 */
define('PAGES_PAR_ROLE', [
    'createur' => ['admin/*'],
    'admin' => ['admin/*'],
    'client' => ['client/*'],
    'livreur' => ['livreur/*'],
    'partenaire' => ['partenaire/*']
]);

// =============================================
// FIN DU FICHIER CONFIG.PHP
// =============================================
?>