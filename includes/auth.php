<?php
/**
 * =============================================
 * AUTHENTIFICATION ET SESSIONS - DoriExpress-Pro
 * =============================================
 * Fichier : includes/auth.php
 * Rôle : Gestion de l'authentification, sessions, rôles
 * Utilisation : require_once 'includes/auth.php';
 * =============================================
 */

// Empêcher l'accès direct
if (!defined('DOSSIER_RACINE')) {
    die('Accès direct interdit');
}

/**
 * Classe Auth - Gestion de l'authentification
 */
class Auth {
    
    /**
     * @var array Données de l'utilisateur connecté
     */
    private static $user = null;
    
    /**
     * @var bool État de la connexion
     */
    private static $isLoggedIn = false;
    
    /**
     * Initialise la session
     */
    public static function init() {
        // Démarrer la session si elle n'est pas déjà active
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NOM);
            session_start();
        }
        
        // Vérifier si l'utilisateur est connecté
        if (isset($_SESSION['user_id'])) {
            self::$isLoggedIn = true;
            self::loadUser($_SESSION['user_id']);
        }
    }
    
    /**
     * Charge les données de l'utilisateur depuis la BDD
     * @param int $userId ID de l'utilisateur
     * @return bool Succès du chargement
     */
    private static function loadUser($userId) {
        try {
            $db = Database::getInstance();
            $sql = "SELECT * FROM utilisateurs WHERE id = ? AND statut = 'actif'";
            $user = $db->fetchOne($sql, [$userId]);
            
            if ($user) {
                self::$user = $user;
                return true;
            } else {
                self::$isLoggedIn = false;
                unset($_SESSION['user_id']);
                return false;
            }
        } catch (Exception $e) {
            self::$isLoggedIn = false;
            return false;
        }
    }
    
    /**
     * Connecte un utilisateur
     * @param string $email Email de l'utilisateur
     * @param string $password Mot de passe
     * @return array Résultat de la connexion
     */
    public static function login($email, $password) {
        try {
            $db = Database::getInstance();
            
            // Vérifier si l'utilisateur existe
            $sql = "SELECT * FROM utilisateurs WHERE email = ? OR telephone = ?";
            $user = $db->fetchOne($sql, [$email, $email]);
            
            if (!$user) {
                return ['success' => false, 'message' => 'Email ou mot de passe incorrect'];
            }
            
            // Vérifier le statut
            if ($user['statut'] === 'suspendu') {
                return ['success' => false, 'message' => 'Votre compte a été suspendu'];
            }
            
            if ($user['statut'] === 'bloque') {
                return ['success' => false, 'message' => 'Votre compte a été bloqué'];
            }
            
            // Vérifier le mot de passe
            if (!password_verify($password, $user['mot_de_passe_hash'])) {
                // Enregistrer la tentative échouée
                self::logConnexion($user['id'], 'echoue', 'Mot de passe incorrect');
                return ['success' => false, 'message' => 'Email ou mot de passe incorrect'];
            }
            
            // Vérifier si le mot de passe doit être ré-hashé
            if (password_needs_rehash($user['mot_de_passe_hash'], PASSWORD_DEFAULT)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $db->query("UPDATE utilisateurs SET mot_de_passe_hash = ? WHERE id = ?", [$newHash, $user['id']]);
            }
            
            // Vérifier si 2FA est activé
            if ($user['deux_facteur_active']) {
                // Générer un code OTP
                $otp = self::genererOTP($user['id']);
                self::envoyerOTP($user['telephone'], $otp);
                
                return [
                    'success' => true, 
                    'require_2fa' => true,
                    'user_id' => $user['id'],
                    'message' => 'Code OTP envoyé sur votre téléphone'
                ];
            }
            
            // Connexion réussie
            return self::completeLogin($user);
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur lors de la connexion'];
        }
    }
    
    /**
     * Finalise la connexion après vérification 2FA
     * @param string $otp Code OTP saisi par l'utilisateur
     * @param int $userId ID de l'utilisateur
     * @return array Résultat
     */
    public static function verify2FA($otp, $userId) {
        try {
            $db = Database::getInstance();
            
            // Vérifier l'OTP
            $sql = "SELECT * FROM otp WHERE utilisateur_id = ? AND code = ? AND date_expiration > NOW()";
            $otpData = $db->fetchOne($sql, [$userId, $otp]);
            
            if (!$otpData) {
                return ['success' => false, 'message' => 'Code OTP invalide ou expiré'];
            }
            
            // Supprimer l'OTP utilisé
            $db->query("DELETE FROM otp WHERE id = ?", [$otpData['id']]);
            
            // Récupérer l'utilisateur
            $sql = "SELECT * FROM utilisateurs WHERE id = ? AND statut = 'actif'";
            $user = $db->fetchOne($sql, [$userId]);
            
            if (!$user) {
                return ['success' => false, 'message' => 'Utilisateur introuvable'];
            }
            
            return self::completeLogin($user);
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur lors de la vérification 2FA'];
        }
    }
    
    /**
     * Finalise la connexion (création de session)
     * @param array $user Données de l'utilisateur
     * @return array Résultat
     */
    private static function completeLogin($user) {
        try {
            $db = Database::getInstance();
            
            // Mettre à jour la dernière connexion
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $db->query(
                "UPDATE utilisateurs SET derniere_connexion = NOW(), adresse_ip = ? WHERE id = ?",
                [$ip, $user['id']]
            );
            
            // Créer la session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_nom'] = $user['nom'] . ' ' . $user['prenom'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_photo'] = $user['photo'];
            $_SESSION['connexion_time'] = time();
            
            // Enregistrer la session en BDD
            $token = bin2hex(random_bytes(32));
            $expiration = date('Y-m-d H:i:s', time() + SESSION_DUREE);
            
            $db->query(
                "INSERT INTO sessions (utilisateur_id, token, appareil, adresse_ip, date_expiration) 
                 VALUES (?, ?, ?, ?, ?)",
                [$user['id'], $token, self::getAppareil(), $ip, $expiration]
            );
            
            // Enregistrer le log de connexion
            self::logConnexion($user['id'], 'succes');
            
            // Charger l'utilisateur
            self::$user = $user;
            self::$isLoggedIn = true;
            
            return [
                'success' => true,
                'message' => 'Connexion réussie',
                'redirect' => self::getRedirection($user['role'])
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur lors de la finalisation de la connexion'];
        }
    }
    
    /**
     * Déconnecte l'utilisateur
     * @param bool $logoutAll Déconnecter de tous les appareils
     * @return bool Succès
     */
    public static function logout($logoutAll = false) {
        try {
            $db = Database::getInstance();
            
            if (self::$isLoggedIn && isset(self::$user['id'])) {
                // Enregistrer le log de déconnexion
                self::logConnexion(self::$user['id'], 'deconnexion');
                
                if ($logoutAll) {
                    // Supprimer toutes les sessions de l'utilisateur
                    $db->query("DELETE FROM sessions WHERE utilisateur_id = ?", [self::$user['id']]);
                } else {
                    // Supprimer la session actuelle
                    $token = $_SESSION['token'] ?? null;
                    if ($token) {
                        $db->query("DELETE FROM sessions WHERE token = ?", [$token]);
                    }
                }
            }
            
            // Détruire la session PHP
            $_SESSION = [];
            session_destroy();
            
            self::$user = null;
            self::$isLoggedIn = false;
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Vérifie si l'utilisateur est connecté
     * @return bool Connecté ou non
     */
    public static function isLoggedIn() {
        return self::$isLoggedIn;
    }
    
    /**
     * Récupère les données de l'utilisateur connecté
     * @return array|null Données de l'utilisateur
     */
    public static function getUser() {
        return self::$user;
    }
    
    /**
     * Récupère l'ID de l'utilisateur connecté
     * @return int|null ID de l'utilisateur
     */
    public static function getUserId() {
        return self::$isLoggedIn ? self::$user['id'] : null;
    }
    
    /**
     * Récupère le rôle de l'utilisateur connecté
     * @return string|null Rôle de l'utilisateur
     */
    public static function getRole() {
        return self::$isLoggedIn ? self::$user['role'] : null;
    }
    
    /**
     * Vérifie si l'utilisateur a un rôle spécifique
     * @param string|array $roles Rôle(s) à vérifier
     * @return bool A le rôle ou non
     */
    public static function hasRole($roles) {
        if (!self::$isLoggedIn) {
            return false;
        }
        
        $userRole = self::$user['role'];
        
        if (is_array($roles)) {
            return in_array($userRole, $roles);
        }
        
        return $userRole === $roles;
    }
    
    /**
     * Vérifie si l'utilisateur est un créateur (Super Admin)
     * @return bool Est le créateur
     */
    public static function isCreateur() {
        return self::hasRole('createur');
    }
    
    /**
     * Vérifie si l'utilisateur est administrateur
     * @return bool Est administrateur
     */
    public static function isAdmin() {
        return self::hasRole(['createur', 'admin']);
    }
    
    /**
     * Vérifie si l'utilisateur est un client
     * @return bool Est client
     */
    public static function isClient() {
        return self::hasRole('client');
    }
    
    /**
     * Vérifie si l'utilisateur est un livreur
     * @return bool Est livreur
     */
    public static function isLivreur() {
        return self::hasRole('livreur');
    }
    
    /**
     * Vérifie si l'utilisateur est un partenaire
     * @return bool Est partenaire
     */
    public static function isPartenaire() {
        return self::hasRole('partenaire');
    }
    
    /**
     * Vérifie si l'utilisateur a une permission spécifique
     * @param string $permission Permission à vérifier
     * @return bool A la permission
     */
    public static function hasPermission($permission) {
        if (!self::$isLoggedIn) {
            return false;
        }
        
        // Le créateur a tous les droits
        if (self::isCreateur()) {
            return true;
        }
        
        try {
            $db = Database::getInstance();
            $sql = "SELECT COUNT(*) as total 
                    FROM permissions p
                    JOIN role_permissions rp ON p.id = rp.permission_id
                    JOIN utilisateur_roles ur ON rp.role_id = ur.role_id
                    WHERE ur.utilisateur_id = ? AND p.nom = ?";
            
            $count = $db->fetchValue($sql, [self::$user['id'], $permission]);
            return $count > 0;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Redirige vers la page appropriée selon le rôle
     * @param string $role Rôle de l'utilisateur
     * @return string URL de redirection
     */
    private static function getRedirection($role) {
        $redirects = [
            'createur' => 'admin/createur.php',
            'admin' => 'admin/dashboard.php',
            'client' => 'client/dashboard.php',
            'livreur' => 'livreur/dashboard.php',
            'partenaire' => 'partenaire/dashboard.php',
            'support' => 'admin/support.php'
        ];
        
        return isset($redirects[$role]) ? $redirects[$role] : 'index.php';
    }
    
    /**
     * Génère un code OTP
     * @param int $userId ID de l'utilisateur
     * @return string Code OTP généré
     */
    private static function genererOTP($userId) {
        $db = Database::getInstance();
        $code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        
        // Supprimer les anciens codes
        $db->query("DELETE FROM otp WHERE utilisateur_id = ?", [$userId]);
        
        // Insérer le nouveau code
        $db->query(
            "INSERT INTO otp (utilisateur_id, code, date_expiration) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE))",
            [$userId, $code]
        );
        
        return $code;
    }
    
    /**
     * Envoie le code OTP (simulation)
     * @param string $telephone Téléphone du destinataire
     * @param string $otp Code OTP
     * @return bool Succès
     */
    private static function envoyerOTP($telephone, $otp) {
        // Dans la vraie vie, envoyer par SMS
        // Pour l'instant, on le stocke pour affichage dans l'admin
        $message = "Votre code OTP DoriExpress-Pro : " . $otp;
        
        // Journaliser pour l'admin
        $logFile = DOSSIER_RACINE . 'storage/logs/otp.log';
        $date = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$date] OTP pour $telephone : $otp\n", FILE_APPEND);
        
        return true;
    }
    
    /**
     * Enregistre un log de connexion
     * @param int $userId ID de l'utilisateur
     * @param string $statut Statut (succes, echoue, deconnexion)
     * @param string $message Message supplémentaire
     */
    private static function logConnexion($userId, $statut, $message = '') {
        try {
            $db = Database::getInstance();
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Inconnu';
            
            $db->query(
                "INSERT INTO logs (utilisateur_id, action, module, adresse_ip, details, date_log) 
                 VALUES (?, ?, 'connexion', ?, ?, NOW())",
                [$userId, 'connexion_' . $statut, $ip, json_encode(['agent' => $userAgent, 'message' => $message])]
            );
        } catch (Exception $e) {
            // Ignorer les erreurs de log
        }
    }
    
    /**
     * Récupère les informations de l'appareil
     * @return string Nom de l'appareil
     */
    private static function getAppareil() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Inconnu';
        
        if (strpos($userAgent, 'Mobile') !== false) {
            return 'Mobile';
        } elseif (strpos($userAgent, 'Tablet') !== false) {
            return 'Tablette';
        } else {
            return 'Ordinateur';
        }
    }
    
    /**
     * Vérifie si l'utilisateur a accès à une page
     * @param string $page Page demandée
     * @return bool Accès autorisé
     */
    public static function hasAccess($page) {
        // Les pages publiques sont accessibles à tous
        if (in_array($page, PAGES_PUBLIQUES)) {
            return true;
        }
        
        // Si non connecté, accès refusé
        if (!self::$isLoggedIn) {
            return false;
        }
        
        // Le créateur a accès à tout
        if (self::isCreateur()) {
            return true;
        }
        
        // Vérifier par rôle
        $role = self::$user['role'];
        $pagesParRole = [
            'admin' => ['admin/'],
            'client' => ['client/'],
            'livreur' => ['livreur/'],
            'partenaire' => ['partenaire/'],
            'support' => ['admin/support', 'admin/tickets']
        ];
        
        if (isset($pagesParRole[$role])) {
            foreach ($pagesParRole[$role] as $prefix) {
                if (strpos($page, $prefix) === 0) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Protège une page (redirige vers login si non autorisé)
     * @param string $page Page à protéger
     */
    public static function protectPage($page = '') {
        if (!self::hasAccess($page)) {
            if (!self::$isLoggedIn) {
                header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode($page));
            } else {
                header('Location: ' . URL_BASE . '403.php');
            }
            exit;
        }
    }
    
    /**
     * Vérifie si un utilisateur est autorisé à effectuer une action
     * @param string $action Action à vérifier
     * @param int $userId ID de l'utilisateur cible (si applicable)
     * @return bool Autorisé
     */
    public static function can($action, $userId = null) {
        // Le créateur peut tout faire
        if (self::isCreateur()) {
            return true;
        }
        
        // Un admin peut tout faire sauf les actions réservées au créateur
        if (self::isAdmin()) {
            $actionsRestreintes = ['gerer_createur', 'supprimer_admin', 'modifier_parametres_critiques'];
            return !in_array($action, $actionsRestreintes);
        }
        
        // Vérifier les permissions spécifiques
        return self::hasPermission($action);
    }
    
    /**
     * Vérifie si l'utilisateur est le propriétaire d'une ressource
     * @param int $ownerId ID du propriétaire
     * @return bool Est le propriétaire
     */
    public static function isOwner($ownerId) {
        return self::$isLoggedIn && self::$user['id'] == $ownerId;
    }
    
    /**
     * Récupère le nombre de sessions actives de l'utilisateur
     * @return int Nombre de sessions
     */
    public static function getActiveSessions() {
        if (!self::$isLoggedIn) {
            return 0;
        }
        
        try {
            $db = Database::getInstance();
            return (int) $db->fetchValue(
                "SELECT COUNT(*) FROM sessions WHERE utilisateur_id = ? AND date_expiration > NOW() AND est_active = 1",
                [self::$user['id']]
            );
        } catch (Exception $e) {
            return 0;
        }
    }
}

// =============================================
// INITIALISATION DE L'AUTHENTIFICATION
// =============================================

// Initialiser l'authentification
Auth::init();

// =============================================
// FONCTIONS D'AIDE
// =============================================

/**
 * Vérifie si l'utilisateur est connecté
 * @return bool
 */
function est_connecte() {
    return Auth::isLoggedIn();
}

/**
 * Récupère l'utilisateur connecté
 * @return array|null
 */
function utilisateur_connecte() {
    return Auth::getUser();
}

/**
 * Récupère l'ID de l'utilisateur connecté
 * @return int|null
 */
function id_utilisateur() {
    return Auth::getUserId();
}

/**
 * Vérifie si l'utilisateur est créateur
 * @return bool
 */
function est_createur() {
    return Auth::isCreateur();
}

/**
 * Vérifie si l'utilisateur est administrateur
 * @return bool
 */
function est_admin() {
    return Auth::isAdmin();
}

/**
 * Vérifie si l'utilisateur est client
 * @return bool
 */
function est_client() {
    return Auth::isClient();
}

/**
 * Vérifie si l'utilisateur est livreur
 * @return bool
 */
function est_livreur() {
    return Auth::isLivreur();
}

/**
 * Vérifie si l'utilisateur est partenaire
 * @return bool
 */
function est_partenaire() {
    return Auth::isPartenaire();
}

/**
 * Protège une page
 * @param string $page Page à protéger
 */
function proteger_page($page = '') {
    Auth::protectPage($page);
}

/**
 * Vérifie une permission
 * @param string $permission Permission à vérifier
 * @return bool
 */
function a_permission($permission) {
    return Auth::hasPermission($permission);
}

// =============================================
// FIN DU FICHIER AUTH.PHP
// =============================================
?>