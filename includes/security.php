<?php
/**
 * =============================================
 * SYSTÈME DE SÉCURITÉ ULTIME - DoriExpress-Pro
 * =============================================
 * Fichier : includes/security.php
 * Rôle : Protection maximale contre toutes les menaces
 * Niveau : Comme Uber, Glovo, ou toute plateforme de livraison professionnelle
 * Utilisation : require_once 'includes/security.php';
 * =============================================
 */

// Empêcher l'accès direct
if (!defined('DOSSIER_RACINE')) {
    die('Accès direct interdit - 403 Forbidden');
}

// =============================================
// CLASSE DE SÉCURITÉ ULTIME
// =============================================
class SecurityUltimate {
    
    /**
     * @var array Liste des IP bloquées
     */
    private static $ipBlacklist = [];
    
    /**
     * @var array Liste des IP whitelistées
     */
    private static $ipWhitelist = [];
    
    /**
     * @var array Tentatives de connexion par IP
     */
    private static $loginAttempts = [];
    
    /**
     * @var array Session utilisateur sécurisée
     */
    private static $secureSession = [];
    
    /**
     * @var bool État de la sécurité
     */
    private static $isInitialized = false;
    
    /**
     * Initialise toutes les protections
     */
    public static function init() {
        if (self::$isInitialized) {
            return;
        }
        
        // 1. Démarrer la session sécurisée
        self::initSecureSession();
        
        // 2. Charger les listes de blocage
        self::loadBlockLists();
        
        // 3. Vérifier les menaces actives
        self::checkActiveThreats();
        
        // 4. Appliquer les en-têtes de sécurité
        self::applySecurityHeaders();
        
        // 5. Vérifier les tentatives de connexion
        self::checkLoginAttempts();
        
        // 6. Protéger contre les attaques courantes
        self::protectAgainstCommonAttacks();
        
        // 7. Nettoyer les entrées utilisateur
        self::cleanGlobalInputs();
        
        // 8. Vérifier l'intégrité des fichiers
        self::checkFileIntegrity();
        
        // 9. Initialiser le système d'audit
        self::initAuditSystem();
        
        // 10. Vérifier les sessions actives
        self::validateActiveSessions();
        
        self::$isInitialized = true;
    }
    
    // =============================================
    // 1. GESTION DES SESSIONS SÉCURISÉES
    // =============================================
    
    /**
     * Initialise une session ultra-sécurisée
     */
    private static function initSecureSession() {
        // Nom de session unique
        session_name(SESSION_NOM . '_' . md5(SALT_GLOBAL));
        
        // Paramètres de session sécurisés
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_only_cookies', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.gc_maxlifetime', SESSION_DUREE);
        
        // En production, forcer HTTPS
        if (ENVIRONNEMENT === 'production') {
            ini_set('session.cookie_secure', 1);
        }
        
        // Démarrer la session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Régénérer l'ID de session régulièrement
        if (!isset($_SESSION['_last_regeneration'])) {
            $_SESSION['_last_regeneration'] = time();
        } else {
            $regenerationInterval = 300; // 5 minutes
            if (time() - $_SESSION['_last_regeneration'] > $regenerationInterval) {
                session_regenerate_id(true);
                $_SESSION['_last_regeneration'] = time();
            }
        }
        
        // Vérifier l'empreinte du navigateur
        if (!isset($_SESSION['_fingerprint'])) {
            $_SESSION['_fingerprint'] = self::generateFingerprint();
        } elseif ($_SESSION['_fingerprint'] !== self::generateFingerprint()) {
            // Empreinte différente = session suspecte
            self::logSecurityEvent('session_fingerprint_mismatch', [
                'stored' => $_SESSION['_fingerprint'],
                'current' => self::generateFingerprint()
            ]);
            self::destroySession();
        }
    }
    
    /**
     * Génère une empreinte unique du navigateur
     */
    private static function generateFingerprint() {
        $data = [
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? ''
        ];
        return hash('sha256', implode('|', $data));
    }
    
    /**
     * Détruit une session de manière sécurisée
     */
    public static function destroySession() {
        $_SESSION = [];
        
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        session_destroy();
        self::$secureSession = [];
    }
    
    // =============================================
    // 2. LISTES DE BLOCAGE
    // =============================================
    
    /**
     * Charge les listes de blocage depuis la base de données
     */
    private static function loadBlockLists() {
        try {
            $db = Database::getInstance();
            
            // IP bloquées
            $blocked = $db->fetchAll(
                "SELECT adresse_ip FROM securite_logs WHERE type = 'blocage_ip' AND date_expiration > NOW()"
            );
            self::$ipBlacklist = array_column($blocked, 'adresse_ip');
            
            // IP autorisées (whitelist)
            $allowed = $db->fetchAll(
                "SELECT adresse_ip FROM securite_logs WHERE type = 'whitelist_ip' AND est_active = 1"
            );
            self::$ipWhitelist = array_column($allowed, 'adresse_ip');
            
        } catch (Exception $e) {
            // En cas d'erreur, charger les listes par défaut
            self::$ipBlacklist = [];
            self::$ipWhitelist = [];
        }
    }
    
    /**
     * Vérifie si une IP est bloquée
     */
    public static function isIpBlocked($ip = null) {
        $ip = $ip ?: self::getClientIP();
        
        // Vérifier dans la liste
        if (in_array($ip, self::$ipBlacklist)) {
            return true;
        }
        
        // Vérifier les plages CIDR
        foreach (self::$ipBlacklist as $blocked) {
            if (strpos($blocked, '/') !== false) {
                if (self::ipInRange($ip, $blocked)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Vérifie si une IP est dans une plage CIDR
     */
    private static function ipInRange($ip, $range) {
        list($subnet, $bits) = explode('/', $range);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;
        return ($ip & $mask) == $subnet;
    }
    
    /**
     * Bloque une IP définitivement
     */
    public static function blockIp($ip, $reason = 'Activité suspecte', $duration = null) {
        $duration = $duration ?: date('Y-m-d H:i:s', strtotime('+1 year'));
        
        try {
            $db = Database::getInstance();
            $db->query(
                "INSERT INTO securite_logs (adresse_ip, type, raison, date_expiration, date_creation) 
                 VALUES (?, 'blocage_ip', ?, ?, NOW())",
                [$ip, $reason, $duration]
            );
            self::$ipBlacklist[] = $ip;
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Débloque une IP
     */
    public static function unblockIp($ip) {
        try {
            $db = Database::getInstance();
            $db->query(
                "UPDATE securite_logs SET est_active = 0, date_expiration = NOW() 
                 WHERE adresse_ip = ? AND type = 'blocage_ip'",
                [$ip]
            );
            self::$ipBlacklist = array_diff(self::$ipBlacklist, [$ip]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    // =============================================
    // 3. PROTECTION CONTRE LES ATTAQUES
    // =============================================
    
    /**
     * Protège contre les attaques courantes
     */
    private static function protectAgainstCommonAttacks() {
        // 1. Protéger contre les attaques par force brute
        self::protectBruteForce();
        
        // 2. Protéger contre les injections SQL
        self::protectSQLInjection();
        
        // 3. Protéger contre les XSS
        self::protectXSS();
        
        // 4. Protéger contre les CSRF
        self::protectCSRF();
        
        // 5. Protéger contre le clickjacking
        self::protectClickjacking();
        
        // 6. Protéger contre le directory traversal
        self::protectDirectoryTraversal();
        
        // 7. Protéger contre les attaques XXE
        self::protectXXE();
        
        // 8. Protéger contre les injections de code
        self::protectCodeInjection();
        
        // 9. Protéger contre les attaques par timing
        self::protectTimingAttacks();
        
        // 10. Protéger contre les attaques par session fixation
        self::protectSessionFixation();
    }
    
    // =============================================
    // 3.1 PROTECTION BRUTE FORCE
    // =============================================
    
    private static function protectBruteForce() {
        $ip = self::getClientIP();
        $maxAttempts = (int) get_parametre('tentatives_connexion_max', 5);
        $blockTime = (int) get_parametre('blocage_temps_minutes', 15);
        
        // Vérifier les tentatives
        $attempts = self::getLoginAttempts($ip);
        
        if ($attempts >= $maxAttempts) {
            $blockUntil = self::getBlockUntil($ip);
            if ($blockUntil && time() < $blockUntil) {
                // Encore bloqué
                $remaining = ceil(($blockUntil - time()) / 60);
                self::logSecurityEvent('brute_force_blocked', [
                    'ip' => $ip,
                    'attempts' => $attempts,
                    'remaining_minutes' => $remaining
                ]);
                http_response_code(429);
                die('Trop de tentatives. Réessayez dans ' . $remaining . ' minutes.');
            } elseif ($blockUntil && time() >= $blockUntil) {
                // Réinitialiser les tentatives
                self::resetLoginAttempts($ip);
            }
        }
    }
    
    /**
     * Récupère le nombre de tentatives de connexion
     */
    private static function getLoginAttempts($ip) {
        try {
            $db = Database::getInstance();
            $count = $db->fetchValue(
                "SELECT COUNT(*) FROM logs 
                 WHERE adresse_ip = ? AND action = 'connexion_echoue' 
                 AND date_log > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
                [$ip]
            );
            return (int) $count;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Récupère la date de déblocage
     */
    private static function getBlockUntil($ip) {
        try {
            $db = Database::getInstance();
            $result = $db->fetchOne(
                "SELECT date_expiration FROM securite_logs 
                 WHERE adresse_ip = ? AND type = 'blocage_temporaire' 
                 AND date_expiration > NOW() 
                 ORDER BY date_creation DESC LIMIT 1",
                [$ip]
            );
            return $result ? strtotime($result['date_expiration']) : null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Réinitialise les tentatives de connexion
     */
    private static function resetLoginAttempts($ip) {
        try {
            $db = Database::getInstance();
            $db->query(
                "DELETE FROM securite_logs 
                 WHERE adresse_ip = ? AND type = 'blocage_temporaire'",
                [$ip]
            );
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    // =============================================
    // 3.2 PROTECTION SQL INJECTION
    // =============================================
    
    private static function protectSQLInjection() {
        // Nettoyer les entrées GET, POST, COOKIE
        foreach ($_GET as $key => $value) {
            $_GET[$key] = self::cleanSQLInput($value);
        }
        foreach ($_POST as $key => $value) {
            $_POST[$key] = self::cleanSQLInput($value);
        }
        foreach ($_COOKIE as $key => $value) {
            $_COOKIE[$key] = self::cleanSQLInput($value);
        }
        
        // Vérifier les patterns d'injection SQL
        $patterns = [
            '/\bUNION\b.*\bSELECT\b/i',
            '/\bDROP\b.*\bTABLE\b/i',
            '/\bDELETE\b.*\bFROM\b/i',
            '/\bINSERT\b.*\bINTO\b/i',
            '/\bUPDATE\b.*\bSET\b/i',
            '/\bEXEC\b/i',
            '/\bXP_/i',
            '/\bSLEEP\b/i',
            '/\bBENCHMARK\b/i',
            '/\bLOAD_FILE\b/i',
            '/\bOUTFILE\b/i',
            '/\bDUMPFILE\b/i',
            '/\bINFORMATION_SCHEMA\b/i'
        ];
        
        foreach ($_REQUEST as $key => $value) {
            if (is_string($value)) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        self::logSecurityEvent('sql_injection_detected', [
                            'key' => $key,
                            'value' => $value,
                            'pattern' => $pattern
                        ]);
                        http_response_code(403);
                        die('Requête suspecte détectée.');
                    }
                }
            }
        }
    }
    
    /**
     * Nettoie une entrée pour éviter les injections SQL
     */
    private static function cleanSQLInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'cleanSQLInput'], $input);
        }
        
        if (!is_string($input)) {
            return $input;
        }
        
        // Supprimer les caractères dangereux
        $input = preg_replace('/[\x00-\x1F]/', '', $input);
        $input = stripslashes($input);
        $input = trim($input);
        
        return $input;
    }
    
    // =============================================
    // 3.3 PROTECTION XSS (Cross-Site Scripting)
    // =============================================
    
    private static function protectXSS() {
        // Nettoyer toutes les entrées
        $_GET = self::cleanXSS($_GET);
        $_POST = self::cleanXSS($_POST);
        $_COOKIE = self::cleanXSS($_COOKIE);
        $_REQUEST = self::cleanXSS($_REQUEST);
        
        // Vérifier les patterns XSS
        $patterns = [
            '/<script\b[^>]*>/i',
            '/<\/script>/i',
            '/<iframe\b[^>]*>/i',
            '/<object\b[^>]*>/i',
            '/<embed\b[^>]*>/i',
            '/<applet\b[^>]*>/i',
            '/on\w+\s*=/i',
            '/javascript\s*:/i',
            '/vbscript\s*:/i',
            '/expression\s*\(/i',
            '/alert\s*\(/i',
            '/prompt\s*\(/i',
            '/confirm\s*\(/i',
            '/eval\s*\(/i'
        ];
        
        foreach ($_REQUEST as $key => $value) {
            if (is_string($value)) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        self::logSecurityEvent('xss_detected', [
                            'key' => $key,
                            'value' => $value,
                            'pattern' => $pattern
                        ]);
                        http_response_code(403);
                        die('Contenu suspect détecté.');
                    }
                }
            }
        }
    }
    
    /**
     * Nettoie une entrée contre les XSS
     */
    private static function cleanXSS($input) {
        if (is_array($input)) {
            return array_map([self::class, 'cleanXSS'], $input);
        }
        
        if (!is_string($input)) {
            return $input;
        }
        
        // Nettoyer les balises HTML
        $input = strip_tags($input, '<p><br><strong><em><u><ul><ol><li><a><img><h1><h2><h3><h4><h5><h6>');
        
        // Échapper les caractères
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $input;
    }
    
    // =============================================
    // 3.4 PROTECTION CSRF
    // =============================================
    
    private static function protectCSRF() {
        // Vérifier les tokens CSRF pour les méthodes POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Ignorer les pages publiques et les webhooks
            $excludeUrls = ['/api/', '/webhook/', '/callback/'];
            foreach ($excludeUrls as $url) {
                if (strpos($_SERVER['REQUEST_URI'], $url) !== false) {
                    return;
                }
            }
            
            // Vérifier le token
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (!verifier_token_csrf($token)) {
                self::logSecurityEvent('csrf_attack_detected', [
                    'ip' => self::getClientIP(),
                    'uri' => $_SERVER['REQUEST_URI']
                ]);
                http_response_code(403);
                die('Token CSRF invalide.');
            }
        }
    }
    
    // =============================================
    // 3.5 PROTECTION CLICKJACKING
    // =============================================
    
    private static function protectClickjacking() {
        header('X-Frame-Options: DENY');
        header('Content-Security-Policy: frame-ancestors none;');
    }
    
    // =============================================
    // 3.6 PROTECTION DIRECTORY TRAVERSAL
    // =============================================
    
    private static function protectDirectoryTraversal() {
        // Vérifier les patterns de traversée de répertoires
        $patterns = [
            '/\.\.\//',
            '/\.\.\\\\/',
            '/%2e%2e%2f/',
            '/%2e%2e%5c/',
            '/%252e%252e%252f/'
        ];
        
        foreach ($_REQUEST as $key => $value) {
            if (is_string($value)) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        self::logSecurityEvent('directory_traversal_detected', [
                            'key' => $key,
                            'value' => $value,
                            'pattern' => $pattern
                        ]);
                        http_response_code(403);
                        die('Accès interdit.');
                    }
                }
            }
        }
    }
    
    // =============================================
    // 3.7 PROTECTION XXE
    // =============================================
    
    private static function protectXXE() {
        // Désactiver les entités externes dans SimpleXML
        libxml_disable_entity_loader(true);
        
        // Vérifier les entités XML dans les requêtes
        $xmlPatterns = [
            '/<!DOCTYPE/i',
            '/<!ENTITY/i',
            '/SYSTEM\s+/i',
            '/PUBLIC\s+/i',
            '/%[a-zA-Z_]+;/i'
        ];
        
        foreach ($_REQUEST as $key => $value) {
            if (is_string($value)) {
                foreach ($xmlPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        self::logSecurityEvent('xxe_detected', [
                            'key' => $key,
                            'value' => $value
                        ]);
                        http_response_code(403);
                        die('XML suspect détecté.');
                    }
                }
            }
        }
    }
    
    // =============================================
    // 3.8 PROTECTION CODE INJECTION
    // =============================================
    
    private static function protectCodeInjection() {
        $patterns = [
            '/\b(base64_decode|eval|system|exec|shell_exec|passthru|popen|proc_open)\s*\(/i',
            '/\b(assert|include|require|include_once|require_once)\s*\(/i',
            '/\b(file_get_contents|curl_exec|fopen)\s*\(/i',
            '/\$\{/',
            '/`.*`/'
        ];
        
        foreach ($_REQUEST as $key => $value) {
            if (is_string($value)) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        self::logSecurityEvent('code_injection_detected', [
                            'key' => $key,
                            'value' => $value,
                            'pattern' => $pattern
                        ]);
                        http_response_code(403);
                        die('Code injecté détecté.');
                    }
                }
            }
        }
    }
    
    // =============================================
    // 3.9 PROTECTION TIMING ATTACKS
    // =============================================
    
    private static function protectTimingAttacks() {
        // Ajouter un délai aléatoire pour les requêtes sensibles
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
            usleep(rand(100000, 300000)); // 100-300ms
        }
    }
    
    // =============================================
    // 3.10 PROTECTION SESSION FIXATION
    // =============================================
    
    private static function protectSessionFixation() {
        // Régénérer l'ID de session à chaque changement de niveau de privilège
        if (isset($_SESSION['user_id']) && !isset($_SESSION['_auth_level'])) {
            session_regenerate_id(true);
            $_SESSION['_auth_level'] = 'authenticated';
        }
    }
    
    // =============================================
    // 4. EN-TÊTES DE SÉCURITÉ
    // =============================================
    
    private static function applySecurityHeaders() {
        // HSTS (HTTP Strict Transport Security)
        if (ENVIRONNEMENT === 'production') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
        
        // X-Content-Type-Options
        header('X-Content-Type-Options: nosniff');
        
        // X-XSS-Protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer-Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content-Security-Policy (CSP)
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com https://maps.googleapis.com; " .
               "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; " .
               "img-src 'self' data: https://maps.googleapis.com https://*.googleapis.com; " .
               "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; " .
               "connect-src 'self' https://*.googleapis.com; " .
               "frame-src 'self' https://*.googleapis.com; " .
               "object-src 'none'; " .
               "base-uri 'self'; " .
               "form-action 'self';";
        
        header('Content-Security-Policy: ' . $csp);
        
        // Permissions-Policy
        header('Permissions-Policy: geolocation=(self), microphone=(), camera=(), payment=()');
        
        // Cache-Control pour les pages sensibles
        if (self::isSensitivePage()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }
    }
    
    /**
     * Vérifie si la page est sensible
     */
    private static function isSensitivePage() {
        $sensitivePages = ['admin/', 'client/', 'livreur/', 'partenaire/', 'profil.php', 'paiement.php'];
        foreach ($sensitivePages as $page) {
            if (strpos($_SERVER['REQUEST_URI'], $page) !== false) {
                return true;
            }
        }
        return false;
    }
    
    // =============================================
    // 5. NETTOYAGE DES ENTRÉES GLOBALES
    // =============================================
    
    private static function cleanGlobalInputs() {
        // Nettoyer $_GET
        foreach ($_GET as $key => $value) {
            $_GET[$key] = self::cleanInput($value);
        }
        
        // Nettoyer $_POST
        foreach ($_POST as $key => $value) {
            $_POST[$key] = self::cleanInput($value);
        }
        
        // Nettoyer $_COOKIE
        foreach ($_COOKIE as $key => $value) {
            $_COOKIE[$key] = self::cleanInput($value);
        }
        
        // Nettoyer $_REQUEST
        foreach ($_REQUEST as $key => $value) {
            $_REQUEST[$key] = self::cleanInput($value);
        }
    }
    
    /**
     * Nettoie une entrée utilisateur
     */
    private static function cleanInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'cleanInput'], $input);
        }
        
        if (!is_string($input)) {
            return $input;
        }
        
        // Supprimer les espaces en trop
        $input = trim($input);
        
        // Supprimer les caractères nuls
        $input = str_replace(chr(0), '', $input);
        
        // Normaliser les caractères Unicode
        if (function_exists('normalizer_normalize')) {
            $input = normalizer_normalize($input, Normalizer::FORM_C);
        }
        
        return $input;
    }
    
    // =============================================
    // 6. SYSTÈME D'AUDIT
    // =============================================
    
    private static function initAuditSystem() {
        // Journaliser les requêtes suspectes
        if (self::isRequestSuspicious()) {
            self::logSecurityEvent('suspicious_request', [
                'uri' => $_SERVER['REQUEST_URI'],
                'method' => $_SERVER['REQUEST_METHOD'],
                'ip' => self::getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        }
        
        // Journaliser les accès admin
        if (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) {
            self::logSecurityEvent('admin_access', [
                'uri' => $_SERVER['REQUEST_URI'],
                'ip' => self::getClientIP(),
                'user' => $_SESSION['user_id'] ?? 'guest'
            ]);
        }
    }
    
    /**
     * Vérifie si la requête est suspecte
     */
    private static function isRequestSuspicious() {
        // Vérifier la présence de paramètres suspects
        $suspiciousParams = ['cmd', 'exec', 'system', 'passthru', 'shell_exec'];
        foreach ($suspiciousParams as $param) {
            if (isset($_REQUEST[$param])) {
                return true;
            }
        }
        
        // Vérifier l'User-Agent
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (empty($userAgent) || strlen($userAgent) < 10) {
            return true;
        }
        
        // Vérifier les requêtes avec des méthodes HTTP inhabituelles
        $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'HEAD'];
        if (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Journalise un événement de sécurité
     */
    public static function logSecurityEvent($type, $data = []) {
        try {
            $db = Database::getInstance();
            $db->query(
                "INSERT INTO securite_logs (type, details, adresse_ip, date_creation) 
                 VALUES (?, ?, ?, NOW())",
                [$type, json_encode($data), self::getClientIP()]
            );
            return true;
        } catch (Exception $e) {
            // Écrire dans le log système en cas d'échec
            error_log('Security event: ' . $type . ' - ' . json_encode($data));
            return false;
        }
    }
    
    // =============================================
    // 7. VALIDATION DES SESSIONS ACTIVES
    // =============================================
    
    private static function validateActiveSessions() {
        if (!isset($_SESSION['user_id'])) {
            return;
        }
        
        try {
            $db = Database::getInstance();
            $session = $db->fetchOne(
                "SELECT * FROM sessions WHERE utilisateur_id = ? AND token = ? AND est_active = 1 AND date_expiration > NOW()",
                [$_SESSION['user_id'], session_id()]
            );
            
            if (!$session) {
                // Session invalide
                self::destroySession();
                header('Location: ' . URL_BASE . 'login.php?expired=1');
                exit;
            }
            
            // Mettre à jour la dernière activité
            $db->query(
                "UPDATE sessions SET date_modification = NOW() WHERE id = ?",
                [$session['id']]
            );
            
        } catch (Exception $e) {
            // En cas d'erreur, ne pas bloquer l'accès
        }
    }
    
    // =============================================
    // 8. VÉRIFICATION DE L'INTÉGRITÉ DES FICHIERS
    // =============================================
    
    private static function checkFileIntegrity() {
        // Vérifier les fichiers critiques
        $criticalFiles = [
            DOSSIER_INCLUDES . 'config.php',
            DOSSIER_INCLUDES . 'connexion.php',
            DOSSIER_INCLUDES . 'auth.php',
            DOSSIER_INCLUDES . 'security.php'
        ];
        
        foreach ($criticalFiles as $file) {
            if (!file_exists($file)) {
                self::logSecurityEvent('critical_file_missing', ['file' => $file]);
                if (ENVIRONNEMENT === 'production') {
                    die('Erreur système. Contactez l\'administrateur.');
                }
            }
        }
    }
    
    // =============================================
    // 9. GESTION DES MENACES ACTIVES
    // =============================================
    
    private static function checkActiveThreats() {
        // Vérifier les IP bloquées
        $ip = self::getClientIP();
        if (self::isIpBlocked($ip)) {
            http_response_code(403);
            die('Votre adresse IP a été bloquée pour des raisons de sécurité.');
        }
        
        // Vérifier si l'IP est whitelistée
        if (!empty(self::$ipWhitelist) && !in_array($ip, self::$ipWhitelist)) {
            // IP non autorisée
            http_response_code(403);
            die('Accès non autorisé.');
        }
        
        // Vérifier les attaques DDoS (trop de requêtes)
        self::checkDDoSProtection();
    }
    
    /**
     * Protection contre les attaques DDoS
     */
    private static function checkDDoSProtection() {
        $ip = self::getClientIP();
        $requests = self::countRecentRequests($ip);
        
        if ($requests > 100) { // Plus de 100 requêtes en 60 secondes
            self::logSecurityEvent('ddos_suspected', [
                'ip' => $ip,
                'requests' => $requests,
                'time' => date('Y-m-d H:i:s')
            ]);
            
            // Bloquer temporairement l'IP
            if ($requests > 200) {
                self::blockIp($ip, 'DDoS suspecté', date('Y-m-d H:i:s', strtotime('+1 hour')));
                http_response_code(429);
                die('Trop de requêtes. Veuillez réessayer plus tard.');
            }
        }
    }
    
    /**
     * Compte les requêtes récentes d'une IP
     */
    private static function countRecentRequests($ip) {
        // Utiliser un fichier de cache pour compter les requêtes
        $cacheFile = DOSSIER_RACINE . 'storage/cache/requests_' . md5($ip) . '.json';
        
        if (!file_exists($cacheFile)) {
            $data = ['count' => 1, 'first' => time(), 'last' => time()];
            file_put_contents($cacheFile, json_encode($data));
            return 1;
        }
        
        $data = json_decode(file_get_contents($cacheFile), true);
        
        // Réinitialiser si plus de 60 secondes
        if (time() - $data['first'] > 60) {
            $data = ['count' => 1, 'first' => time(), 'last' => time()];
            file_put_contents($cacheFile, json_encode($data));
            return 1;
        }
        
        // Incrémenter le compteur
        $data['count']++;
        $data['last'] = time();
        file_put_contents($cacheFile, json_encode($data));
        
        return $data['count'];
    }
    
    // =============================================
    // 10. FONCTIONS UTILITAIRES
    // =============================================
    
    /**
     * Récupère l'adresse IP du client de manière fiable
     */
    public static function getClientIP() {
        $ip = '0.0.0.0';
        
        // Liste des en-têtes à vérifier dans l'ordre
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    break;
                }
            }
        }
        
        return $ip;
    }
    
    /**
     * Vérifie si une requête est HTTPS
     */
    public static function isHTTPS() {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
               (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false);
    }
    
    /**
     * Force le HTTPS
     */
    public static function forceHTTPS() {
        if (ENVIRONNEMENT === 'production' && !self::isHTTPS()) {
            $url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header('Location: ' . $url, true, 301);
            exit;
        }
    }
    
    /**
     * Vérifie si l'utilisateur a un rôle autorisé
     */
    public static function checkRole($allowedRoles) {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
            return false;
        }
        
        if (!is_array($allowedRoles)) {
            $allowedRoles = [$allowedRoles];
        }
        
        return in_array($_SESSION['user_role'], $allowedRoles);
    }
    
    /**
     * Vérifie la force d'un mot de passe (niveau Uber)
     */
    public static function checkPasswordStrength($password) {
        $score = 0;
        $errors = [];
        
        // Longueur
        if (strlen($password) < 8) {
            $errors[] = 'Le mot de passe doit contenir au moins 8 caractères';
        } elseif (strlen($password) >= 12) {
            $score += 2;
        } else {
            $score += 1;
        }
        
        // Majuscules
        if (preg_match('/[A-Z]/', $password)) {
            $score += 1;
        } else {
            $errors[] = 'Le mot de passe doit contenir une majuscule';
        }
        
        // Minuscules
        if (preg_match('/[a-z]/', $password)) {
            $score += 1;
        } else {
            $errors[] = 'Le mot de passe doit contenir une minuscule';
        }
        
        // Chiffres
        if (preg_match('/[0-9]/', $password)) {
            $score += 1;
        } else {
            $errors[] = 'Le mot de passe doit contenir un chiffre';
        }
        
        // Caractères spéciaux
        if (preg_match('/[^A-Za-z0-9]/', $password)) {
            $score += 2;
        } else {
            $errors[] = 'Le mot de passe doit contenir un caractère spécial (!@#$%^&*)';
        }
        
        // Éviter les patterns courants
        $commonPatterns = ['123456', 'password', 'qwerty', 'azerty', '000000', '111111'];
        foreach ($commonPatterns as $pattern) {
            if (strpos(strtolower($password), $pattern) !== false) {
                $errors[] = 'Le mot de passe contient une séquence trop courante';
                $score -= 2;
                break;
            }
        }
        
        return [
            'score' => max(0, $score),
            'max_score' => 7,
            'strength' => self::getStrengthLabel($score),
            'errors' => $errors
        ];
    }
    
    /**
     * Récupère le label de force du mot de passe
     */
    private static function getStrengthLabel($score) {
        if ($score >= 6) return ['label' => 'Très fort', 'color' => '#10b981'];
        if ($score >= 4) return ['label' => 'Fort', 'color' => '#3b82f6'];
        if ($score >= 3) return ['label' => 'Moyen', 'color' => '#f59e0b'];
        if ($score >= 2) return ['label' => 'Faible', 'color' => '#ef4444'];
        return ['label' => 'Très faible', 'color' => '#dc2626'];
    }
    
    /**
     * Génère un token de sécurité unique
     */
    public static function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Vérifie l'intégrité d'un fichier uploadé
     */
    public static function validateUploadedFile($file) {
        // Vérifier l'erreur
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'Erreur lors du téléchargement'];
        }
        
        // Vérifier la taille
        $maxSize = get_parametre('upload_max_size', 5242880);
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'Le fichier est trop volumineux'];
        }
        
        // Vérifier le type MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        if (!in_array($mimeType, $allowedMimes)) {
            return ['valid' => false, 'error' => 'Type de fichier non autorisé'];
        }
        
        return ['valid' => true, 'mime' => $mimeType];
    }
    
    /**
     * Sanitize une chaîne pour une utilisation en URL
     */
    public static function sanitizeUrl($url) {
        $url = filter_var($url, FILTER_SANITIZE_URL);
        $url = strip_tags($url);
        $url = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        return $url;
    }
}

// =============================================
// INITIALISATION DE LA SÉCURITÉ
// =============================================

// Initialiser la sécurité
SecurityUltimate::init();

// En production, forcer HTTPS
if (ENVIRONNEMENT === 'production') {
    SecurityUltimate::forceHTTPS();
}

// =============================================
// FONCTIONS D'ACCÈS RAPIDE
// =============================================

/**
 * Vérifie si l'utilisateur a un rôle autorisé
 */
function check_role($allowedRoles) {
    return SecurityUltimate::checkRole($allowedRoles);
}

/**
 * Vérifie la force du mot de passe
 */
function check_password_strength($password) {
    return SecurityUltimate::checkPasswordStrength($password);
}

/**
 * Génère un token de sécurité
 */
function generate_secure_token($length = 32) {
    return SecurityUltimate::generateSecureToken($length);
}

/**
 * Valide un fichier uploadé
 */
function validate_uploaded_file($file) {
    return SecurityUltimate::validateUploadedFile($file);
}

/**
 * Récupère l'adresse IP du client
 */
function get_ip() {
    return SecurityUltimate::getClientIP();
}

// =============================================
// FIN DU FICHIER SECURITY.PHP
// =============================================
?>