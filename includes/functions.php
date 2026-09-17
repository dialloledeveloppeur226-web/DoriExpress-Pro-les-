<?php
/**
 * =============================================
 * FONCTIONS RÉUTILISABLES - DoriExpress-Pro
 * =============================================
 * Fichier : includes/functions.php
 * Rôle : Toutes les fonctions utilitaires du site
 * Utilisation : require_once 'includes/functions.php';
 * =============================================
 */

// Empêcher l'accès direct
if (!defined('DOSSIER_RACINE')) {
    die('Accès direct interdit');
}

// =============================================
// 1. FONCTIONS DE NETTOYAGE ET SÉCURITÉ
// =============================================

/**
 * Nettoie une chaîne pour l'affichage (protection XSS)
 */
function nettoyer($chaine) {
    return htmlspecialchars(trim($chaine), ENT_QUOTES, 'UTF-8');
}

/**
 * Nettoie une chaîne pour une utilisation en JavaScript
 */
function nettoyer_js($chaine) {
    return addslashes(htmlspecialchars($chaine, ENT_QUOTES, 'UTF-8'));
}

/**
 * Nettoie une chaîne pour une utilisation en URL
 */
function nettoyer_url($chaine) {
    return urlencode($chaine);
}

/**
 * Génère un slug à partir d'une chaîne
 */
function generer_slug($chaine) {
    $chaine = strtolower(trim($chaine));
    $chaine = preg_replace('/[^a-z0-9-]/', '-', $chaine);
    $chaine = preg_replace('/-+/', '-', $chaine);
    return trim($chaine, '-');
}

/**
 * Valide une adresse email
 */
function valider_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valide un numéro de téléphone burkinabé
 * Formats : 70XXXXXX, 71XXXXXX, 72XXXXXX, 74XXXXXX, 75XXXXXX, 76XXXXXX, 77XXXXXX, 78XXXXXX, 79XXXXXX
 * ou +226XXXXXXXX
 */
function valider_telephone($telephone) {
    $telephone = preg_replace('/[^0-9+]/', '', $telephone);
    
    // Format +226XXXXXXXX
    if (preg_match('/^\+226[0-9]{8}$/', $telephone)) {
        return true;
    }
    
    // Format 70XXXXXX à 79XXXXXX
    if (preg_match('/^[7][0-9]{7}$/', $telephone)) {
        return true;
    }
    
    return false;
}

/**
 * Formate un numéro de téléphone
 */
function formater_telephone($telephone) {
    $telephone = preg_replace('/[^0-9]/', '', $telephone);
    
    if (strlen($telephone) == 8) {
        return $telephone;
    }
    
    if (strlen($telephone) == 11 && substr($telephone, 0, 3) == '226') {
        return substr($telephone, 3);
    }
    
    return $telephone;
}

/**
 * Valide un mot de passe (au moins 8 caractères, 1 majuscule, 1 minuscule, 1 chiffre)
 */
function valider_mot_de_passe($motDePasse) {
    return strlen($motDePasse) >= 8 &&
           preg_match('/[A-Z]/', $motDePasse) &&
           preg_match('/[a-z]/', $motDePasse) &&
           preg_match('/[0-9]/', $motDePasse);
}

/**
 * Génère un token CSRF
 */
function generer_token_csrf() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie un token CSRF
 */
function verifier_token_csrf($token) {
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    $tempsEcoule = time() - $_SESSION['csrf_token_time'];
    if ($tempsEcoule > CSRF_DUREE) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Génère un mot de passe aléatoire
 */
function generer_mot_de_passe($longueur = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $motDePasse = '';
    for ($i = 0; $i < $longueur; $i++) {
        $motDePasse .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $motDePasse;
}

// =============================================
// 2. FONCTIONS DE DATE ET TEMPS
// =============================================

/**
 * Formate une date pour l'affichage
 */
function formater_date($date, $format = 'd/m/Y H:i') {
    if (empty($date)) {
        return '-';
    }
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

/**
 * Formate une date en français
 */
function formater_date_fr($date) {
    if (empty($date)) {
        return '-';
    }
    $timestamp = strtotime($date);
    setlocale(LC_TIME, 'fr_FR.utf8', 'fr_FR', 'fr');
    return strftime('%A %d %B %Y à %H:%M', $timestamp);
}

/**
 * Calcule le temps écoulé depuis une date
 */
function temps_ecoule($date) {
    $timestamp = strtotime($date);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Il y a ' . $diff . ' secondes';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return 'Il y a ' . $minutes . ' minute' . ($minutes > 1 ? 's' : '');
    } elseif ($diff < 86400) {
        $heures = floor($diff / 3600);
        return 'Il y a ' . $heures . ' heure' . ($heures > 1 ? 's' : '');
    } elseif ($diff < 604800) {
        $jours = floor($diff / 86400);
        return 'Il y a ' . $jours . ' jour' . ($jours > 1 ? 's' : '');
    } else {
        return formater_date($date, 'd/m/Y');
    }
}

/**
 * Vérifie si une date est dans le futur
 */
function est_date_future($date) {
    return strtotime($date) > time();
}

/**
 * Vérifie si une date est dans le passé
 */
function est_date_passee($date) {
    return strtotime($date) < time();
}

// =============================================
// 3. FONCTIONS DE FORMATAGE
// =============================================

/**
 * Formate un montant en FCFA
 */
function formater_prix($montant) {
    $devise = get_parametre('devise', 'FCFA');
    return number_format($montant, 0, ',', ' ') . ' ' . $devise;
}

/**
 * Formate un pourcentage
 */
function formater_pourcentage($valeur) {
    return number_format($valeur, 1, ',', ' ') . '%';
}

/**
 * Tronque un texte à une longueur donnée
 */
function tronquer_texte($texte, $longueur = 100, $suffixe = '...') {
    if (strlen($texte) <= $longueur) {
        return $texte;
    }
    return substr($texte, 0, $longueur) . $suffixe;
}

/**
 * Génère un extrait d'un texte (pour les résumés)
 */
function generer_extrait($texte, $longueur = 150) {
    $texte = strip_tags($texte);
    $texte = html_entity_decode($texte);
    return tronquer_texte($texte, $longueur);
}

/**
 * Convertit une durée en minutes en format lisible
 */
function formater_duree($minutes) {
    if ($minutes < 60) {
        return $minutes . ' min';
    }
    $heures = floor($minutes / 60);
    $mins = $minutes % 60;
    return $heures . 'h' . ($mins > 0 ? ' ' . $mins . 'min' : '');
}

// =============================================
// 4. FONCTIONS DE GÉNÉRATION DE CODES
// =============================================

/**
 * Génère un code de commande unique (DE-XXXXXX)
 */
function generer_code_commande() {
    $annee = date('Y');
    $mois = date('m');
    $jour = date('d');
    
    try {
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*) FROM commandes WHERE DATE(date_creation) = CURDATE()";
        $count = (int) $db->fetchValue($sql);
        $numero = str_pad($count + 1, 6, '0', STR_PAD_LEFT);
        return 'DE-' . $annee . $mois . $jour . '-' . $numero;
    } catch (Exception $e) {
        return 'DE-' . date('Ymd-His') . '-' . rand(1000, 9999);
    }
}

/**
 * Génère un code promo aléatoire
 */
function generer_code_promo($longueur = 8) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < $longueur; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

/**
 * Génère un code de parrainage unique
 */
function generer_code_parrainage($longueur = 8) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < $longueur; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return 'PAR-' . $code;
}

/**
 * Génère un OTP (One Time Password)
 */
function generer_otp($longueur = 6) {
    $code = '';
    for ($i = 0; $i < $longueur; $i++) {
        $code .= random_int(0, 9);
    }
    return $code;
}

/**
 * Génère un ID unique pour les transactions
 */
function generer_transaction_id() {
    return 'TXN-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
}

// =============================================
// 5. FONCTIONS DE NOTIFICATIONS
// =============================================

/**
 * Ajoute une notification pour un utilisateur
 */
function ajouter_notification($utilisateurId, $titre, $message, $type = 'systeme', $lien = null) {
    try {
        $db = Database::getInstance();
        $db->query(
            "INSERT INTO notifications (utilisateur_id, titre, message, type, lien, date_creation) 
             VALUES (?, ?, ?, ?, ?, NOW())",
            [$utilisateurId, $titre, $message, $type, $lien]
        );
        return $db->lastInsertId();
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Ajoute une notification au créateur
 */
function ajouter_notification_createur($titre, $message, $type = 'systeme', $lien = null) {
    try {
        $db = Database::getInstance();
        $sql = "SELECT id FROM utilisateurs WHERE role = 'createur' LIMIT 1";
        $createur = $db->fetchOne($sql);
        if ($createur) {
            return ajouter_notification($createur['id'], $titre, $message, $type, $lien);
        }
        return false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Marque une notification comme lue
 */
function marquer_notification_lue($notificationId, $utilisateurId) {
    try {
        $db = Database::getInstance();
        $db->query(
            "UPDATE notifications SET est_lu = 1, date_lecture = NOW() 
             WHERE id = ? AND utilisateur_id = ?",
            [$notificationId, $utilisateurId]
        );
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Marque toutes les notifications comme lues
 */
function marquer_toutes_notifications_lues($utilisateurId) {
    try {
        $db = Database::getInstance();
        $db->query(
            "UPDATE notifications SET est_lu = 1, date_lecture = NOW() 
             WHERE utilisateur_id = ? AND est_lu = 0",
            [$utilisateurId]
        );
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Récupère le nombre de notifications non lues
 */
function compter_notifications_non_lues($utilisateurId) {
    try {
        $db = Database::getInstance();
        return (int) $db->fetchValue(
            "SELECT COUNT(*) FROM notifications WHERE utilisateur_id = ? AND est_lu = 0",
            [$utilisateurId]
        );
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Envoie une notification WhatsApp (via bouton direct)
 */
function envoyer_whatsapp($numero, $message) {
    $numero = formater_telephone($numero);
    $message = urlencode($message);
    return "https://wa.me/226{$numero}?text={$message}";
}

/**
 * Journalise une action
 */
function journaliser($utilisateurId, $action, $module, $details = null) {
    try {
        $db = Database::getInstance();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $detailsJson = $details ? json_encode($details) : null;
        
        $db->query(
            "INSERT INTO logs (utilisateur_id, action, module, adresse_ip, details, date_log) 
             VALUES (?, ?, ?, ?, ?, NOW())",
            [$utilisateurId, $action, $module, $ip, $detailsJson]
        );
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// =============================================
// 6. FONCTIONS DE GESTION DES PARAMÈTRES
// =============================================

/**
 * Récupère un paramètre (fonction déjà définie dans config.php)
 * Cette fonction est un alias pour maintenir la compatibilité
 */
if (!function_exists('get_parametre')) {
    function get_parametre($cle, $defaut = null) {
        global $db;
        
        if (!isset($db) || !$db) {
            $defaults = DEFAUT_PARAMS ?? [];
            return isset($defaults[$cle]) ? $defaults[$cle] : $defaut;
        }
        
        try {
            $stmt = $db->prepare("SELECT valeur FROM parametres WHERE cle = ?");
            $stmt->execute([$cle]);
            $result = $stmt->fetch();
            
            if ($result) {
                return $result['valeur'];
            }
            
            $defaults = DEFAUT_PARAMS ?? [];
            return isset($defaults[$cle]) ? $defaults[$cle] : $defaut;
        } catch (Exception $e) {
            $defaults = DEFAUT_PARAMS ?? [];
            return isset($defaults[$cle]) ? $defaults[$cle] : $defaut;
        }
    }
}

/**
 * Met à jour un paramètre
 */
function mettre_a_jour_parametre($cle, $valeur) {
    try {
        $db = Database::getInstance();
        $db->query(
            "UPDATE parametres SET valeur = ?, date_modification = NOW() WHERE cle = ?",
            [$valeur, $cle]
        );
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Récupère tous les paramètres d'une catégorie
 */
function get_parametres_par_categorie($categorie) {
    try {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT cle, valeur FROM parametres WHERE categorie = ?",
            [$categorie]
        );
    } catch (Exception $e) {
        return [];
    }
}

// =============================================
// 7. FONCTIONS DE GESTION DES UTILISATEURS
// =============================================

/**
 * Vérifie si un email existe déjà
 */
function email_existe($email) {
    try {
        $db = Database::getInstance();
        return (bool) $db->fetchValue(
            "SELECT COUNT(*) FROM utilisateurs WHERE email = ?",
            [$email]
        );
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Vérifie si un téléphone existe déjà
 */
function telephone_existe($telephone) {
    try {
        $db = Database::getInstance();
        return (bool) $db->fetchValue(
            "SELECT COUNT(*) FROM utilisateurs WHERE telephone = ?",
            [$telephone]
        );
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Récupère un utilisateur par son email
 */
function get_utilisateur_par_email($email) {
    try {
        $db = Database::getInstance();
        return $db->fetchOne(
            "SELECT * FROM utilisateurs WHERE email = ?",
            [$email]
        );
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Récupère un utilisateur par son ID
 */
function get_utilisateur_par_id($id) {
    try {
        $db = Database::getInstance();
        return $db->fetchOne(
            "SELECT * FROM utilisateurs WHERE id = ?",
            [$id]
        );
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Récupère le profil client d'un utilisateur
 */
function get_profil_client($utilisateurId) {
    try {
        $db = Database::getInstance();
        return $db->fetchOne(
            "SELECT * FROM clients WHERE utilisateur_id = ?",
            [$utilisateurId]
        );
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Récupère le profil livreur d'un utilisateur
 */
function get_profil_livreur($utilisateurId) {
    try {
        $db = Database::getInstance();
        return $db->fetchOne(
            "SELECT * FROM livreurs WHERE utilisateur_id = ?",
            [$utilisateurId]
        );
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Récupère le profil partenaire d'un utilisateur
 */
function get_profil_partenaire($utilisateurId) {
    try {
        $db = Database::getInstance();
        return $db->fetchOne(
            "SELECT * FROM partenaires WHERE utilisateur_id = ?",
            [$utilisateurId]
        );
    } catch (Exception $e) {
        return null;
    }
}

// =============================================
// 8. FONCTIONS DE GESTION DES COMMANDES
// =============================================

/**
 * Calcule le prix d'une commande
 */
function calculer_prix_commande($distance, $poids = 0, $type = 'colis', $estExpress = false, $codePromo = null) {
    $prixBase = (float) get_parametre('prix_base', 500);
    $prixKm = (float) get_parametre('prix_km', 200);
    $prixKg = (float) get_parametre('prix_kg', 50);
    $fraisExpress = (float) get_parametre('frais_express', 1000);
    $fraisNuit = (float) get_parametre('frais_nuit', 500);
    $fraisPluie = (float) get_parametre('frais_pluie', 300);
    $fraisJoursFeries = (float) get_parametre('frais_jours_feries', 500);
    $fraisMinimum = (float) get_parametre('frais_minimum', 500);
    
    // Calcul de base
    $prix = $prixBase + ($distance * $prixKm) + ($poids * $prixKg);
    
    // Supplément express
    if ($estExpress) {
        $prix += $fraisExpress;
    }
    
    // Supplément nuit (22h - 6h)
    $heure = (int) date('H');
    if ($heure >= 22 || $heure < 6) {
        $prix += $fraisNuit;
    }
    
    // Supplément jours fériés
    $joursFeries = ['01-01', '03-08', '04-08', '05-08', '15-08', '01-11', '11-12', '25-12'];
    $date = date('d-m');
    if (in_array($date, $joursFeries)) {
        $prix += $fraisJoursFeries;
    }
    
    // Application du prix minimum
    if ($prix < $fraisMinimum) {
        $prix = $fraisMinimum;
    }
    
    // Application du code promo
    if ($codePromo) {
        $reduction = calculer_reduction_code_promo($codePromo, $prix);
        $prix -= $reduction;
    }
    
    return max(0, $prix);
}

/**
 * Calcule la réduction d'un code promo
 */
function calculer_reduction_code_promo($code, $montant) {
    try {
        $db = Database::getInstance();
        $sql = "SELECT * FROM codes_promo WHERE code = ? AND statut = 'actif' AND date_expiration > NOW()";
        $codeData = $db->fetchOne($sql, [$code]);
        
        if (!$codeData) {
            return 0;
        }
        
        // Vérifier la limite d'utilisation
        if ($codeData['utilisation_max'] > 0 && $codeData['utilisation_actuelle'] >= $codeData['utilisation_max']) {
            return 0;
        }
        
        // Vérifier le montant minimum
        if ($codeData['commande_minimum'] > 0 && $montant < $codeData['commande_minimum']) {
            return 0;
        }
        
        // Calculer la réduction
        if ($codeData['type_reduction'] == 'pourcentage') {
            $reduction = $montant * ($codeData['valeur'] / 100);
        } else {
            $reduction = $codeData['valeur'];
        }
        
        return min($reduction, $montant);
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Met à jour le statut d'une commande
 */
function changer_statut_commande($commandeId, $nouveauStatut, $commentaire = null) {
    try {
        $db = Database::getInstance();
        
        // Récupérer l'ancien statut
        $ancienStatut = $db->fetchValue(
            "SELECT statut FROM commandes WHERE id = ?",
            [$commandeId]
        );
        
        if (!$ancienStatut) {
            return false;
        }
        
        // Mettre à jour le statut
        $db->query(
            "UPDATE commandes SET statut = ? WHERE id = ?",
            [$nouveauStatut, $commandeId]
        );
        
        // Enregistrer l'historique
        $db->query(
            "INSERT INTO historique_commandes (commande_id, ancien_statut, nouveau_statut, commentaire, date_modification) 
             VALUES (?, ?, ?, ?, NOW())",
            [$commandeId, $ancienStatut, $nouveauStatut, $commentaire]
        );
        
        // Mettre à jour les dates spécifiques
        if ($nouveauStatut == 'acceptee') {
            $db->query("UPDATE commandes SET date_acceptation = NOW() WHERE id = ?", [$commandeId]);
        } elseif ($nouveauStatut == 'preparation') {
            $db->query("UPDATE commandes SET date_preparation = NOW() WHERE id = ?", [$commandeId]);
        } elseif ($nouveauStatut == 'livree') {
            $db->query("UPDATE commandes SET date_livraison = NOW() WHERE id = ?", [$commandeId]);
        } elseif ($nouveauStatut == 'annulee') {
            $db->query("UPDATE commandes SET date_annulation = NOW() WHERE id = ?", [$commandeId]);
        } elseif ($nouveauStatut == 'archivee') {
            $db->query("UPDATE commandes SET date_archivage = NOW() WHERE id = ?", [$commandeId]);
        }
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Récupère le statut d'une commande avec sa couleur
 */
function get_statut_commande_info($statut) {
    $statuts = [
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
    ];
    
    return isset($statuts[$statut]) ? $statuts[$statut] : ['label' => $statut, 'couleur' => 'gray'];
}

// =============================================
// 9. FONCTIONS DE GESTION DU PORTEFEUILLE
// =============================================

/**
 * Récupère le solde du portefeuille d'un utilisateur
 */
function get_solde_wallet($utilisateurId) {
    try {
        $db = Database::getInstance();
        $solde = $db->fetchValue(
            "SELECT solde FROM wallet WHERE utilisateur_id = ?",
            [$utilisateurId]
        );
        return $solde ? (float) $solde : 0;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Crédite le portefeuille d'un utilisateur
 */
function crediter_wallet($utilisateurId, $montant, $description, $reference = null) {
    try {
        $db = Database::getInstance();
        $db->beginTransaction();
        
        // Récupérer le wallet
        $wallet = $db->fetchOne(
            "SELECT id, solde FROM wallet WHERE utilisateur_id = ?",
            [$utilisateurId]
        );
        
        if (!$wallet) {
            // Créer le wallet s'il n'existe pas
            $db->query(
                "INSERT INTO wallet (utilisateur_id, solde) VALUES (?, ?)",
                [$utilisateurId, 0]
            );
            $walletId = $db->lastInsertId();
            $soldeActuel = 0;
        } else {
            $walletId = $wallet['id'];
            $soldeActuel = (float) $wallet['solde'];
        }
        
        // Mettre à jour le solde
        $nouveauSolde = $soldeActuel + $montant;
        $db->query(
            "UPDATE wallet SET solde = ?, date_modification = NOW() WHERE id = ?",
            [$nouveauSolde, $walletId]
        );
        
        // Enregistrer la transaction
        $db->query(
            "INSERT INTO transactions_wallet (wallet_id, type, montant, description, reference, solde_apres, date_transaction) 
             VALUES (?, 'credit', ?, ?, ?, ?, NOW())",
            [$walletId, $montant, $description, $reference, $nouveauSolde]
        );
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollback();
        return false;
    }
}

/**
 * Débite le portefeuille d'un utilisateur
 */
function debiter_wallet($utilisateurId, $montant, $description, $reference = null) {
    try {
        $db = Database::getInstance();
        $db->beginTransaction();
        
        // Récupérer le wallet
        $wallet = $db->fetchOne(
            "SELECT id, solde FROM wallet WHERE utilisateur_id = ?",
            [$utilisateurId]
        );
        
        if (!$wallet) {
            $db->rollback();
            return false;
        }
        
        $walletId = $wallet['id'];
        $soldeActuel = (float) $wallet['solde'];
        
        // Vérifier le solde suffisant
        if ($soldeActuel < $montant) {
            $db->rollback();
            return false;
        }
        
        // Mettre à jour le solde
        $nouveauSolde = $soldeActuel - $montant;
        $db->query(
            "UPDATE wallet SET solde = ?, date_modification = NOW() WHERE id = ?",
            [$nouveauSolde, $walletId]
        );
        
        // Enregistrer la transaction
        $db->query(
            "INSERT INTO transactions_wallet (wallet_id, type, montant, description, reference, solde_apres, date_transaction) 
             VALUES (?, 'debit', ?, ?, ?, ?, NOW())",
            [$walletId, $montant, $description, $reference, $nouveauSolde]
        );
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollback();
        return false;
    }
}

// =============================================
// 10. FONCTIONS DE GESTION DES FICHIERS
// =============================================

/**
 * Télécharge un fichier en toute sécurité
 */
function upload_fichier($fichier, $dossier, $typesAutorises = null, $tailleMax = null) {
    if (!isset($fichier) || $fichier['error'] != UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Erreur lors du téléchargement'];
    }
    
    $tailleMax = $tailleMax ?? UPLOAD_MAX_SIZE;
    $typesAutorises = $typesAutorises ?? UPLOAD_TYPES_IMAGE;
    
    // Vérifier la taille
    if ($fichier['size'] > $tailleMax) {
        return ['success' => false, 'message' => 'Fichier trop volumineux'];
    }
    
    // Vérifier l'extension
    $extension = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $typesAutorises)) {
        return ['success' => false, 'message' => 'Type de fichier non autorisé'];
    }
    
    // Générer un nom unique
    $nomUnique = uniqid() . '.' . $extension;
    $chemin = $dossier . $nomUnique;
    
    // Créer le dossier s'il n'existe pas
    if (!is_dir($dossier)) {
        mkdir($dossier, 0755, true);
    }
    
    // Déplacer le fichier
    if (!move_uploaded_file($fichier['tmp_name'], $chemin)) {
        return ['success' => false, 'message' => 'Erreur lors du déplacement du fichier'];
    }
    
    return [
        'success' => true,
        'nom' => $nomUnique,
        'chemin' => $chemin,
        'taille' => $fichier['size']
    ];
}

/**
 * Supprime un fichier
 */
function supprimer_fichier($chemin) {
    if (file_exists($chemin) && is_file($chemin)) {
        return unlink($chemin);
    }
    return false;
}

/**
 * Récupère la taille d'un fichier en format lisible
 */
function taille_fichier_lisible($taille) {
    $unites = ['o', 'Ko', 'Mo', 'Go'];
    $i = 0;
    while ($taille >= 1024 && $i < count($unites) - 1) {
        $taille /= 1024;
        $i++;
    }
    return round($taille, 2) . ' ' . $unites[$i];
}

// =============================================
// 11. FONCTIONS DE SÉCURITÉ
// =============================================

/**
 * Génère un hash de mot de passe sécurisé
 */
function hash_mot_de_passe($motDePasse) {
    return password_hash($motDePasse, PASSWORD_DEFAULT);
}

/**
 * Vérifie un mot de passe
 */
function verifier_mot_de_passe($motDePasse, $hash) {
    return password_verify($motDePasse, $hash);
}

/**
 * Génère un jeton de session sécurisé
 */
function generer_jeton_session() {
    return bin2hex(random_bytes(32));
}

/**
 * Échappe une chaîne pour une utilisation en HTML
 */
function echapper_html($chaine) {
    return htmlspecialchars($chaine, ENT_QUOTES, 'UTF-8');
}

/**
 * Échappe une chaîne pour une utilisation en JSON
 */
function echapper_json($chaine) {
    return json_encode($chaine, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// =============================================
// 12. FONCTIONS DE DEBUG
// =============================================

/**
 * Debug d'une variable (affichage stylisé)
 */
function debug($variable, $titre = 'Debug') {
    echo '<div style="background:#1a1a1a;color:#00ff00;padding:15px;margin:10px;border-radius:8px;font-family:monospace;font-size:14px;max-height:500px;overflow:auto;">';
    echo '<h3 style="color:#ffff00;margin:0 0 10px 0;">' . $titre . '</h3>';
    echo '<pre>';
    print_r($variable);
    echo '</pre>';
    echo '</div>';
}

/**
 * Debug avec arrêt de l'exécution
 */
function debug_die($variable, $titre = 'Debug - Arrêt') {
    debug($variable, $titre);
    die();
}

/**
 * Journalise un message dans le fichier de log
 */
function log_message($message, $niveau = 'INFO') {
    $logFile = DOSSIER_RACINE . 'storage/logs/debug.log';
    $date = date('Y-m-d H:i:s');
    $logMessage = "[$date] [$niveau] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// =============================================
// 13. FONCTIONS DIVERSES
// =============================================

/**
 * Récupère l'adresse IP du client
 */
function get_ip_client() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // Vérifier les en-têtes proxy
    $headers = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP'];
    foreach ($headers as $header) {
        if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            $ip = trim($ips[0]);
            break;
        }
    }
    
    return $ip;
}

/**
 * Récupère le navigateur de l'utilisateur
 */
function get_navigateur() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Inconnu';
    
    if (strpos($userAgent, 'Chrome') !== false) {
        return 'Chrome';
    } elseif (strpos($userAgent, 'Firefox') !== false) {
        return 'Firefox';
    } elseif (strpos($userAgent, 'Safari') !== false) {
        return 'Safari';
    } elseif (strpos($userAgent, 'Edge') !== false) {
        return 'Edge';
    } elseif (strpos($userAgent, 'Opera') !== false) {
        return 'Opera';
    } else {
        return 'Inconnu';
    }
}

/**
 * Récupère le système d'exploitation
 */
function get_os() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Inconnu';
    
    if (strpos($userAgent, 'Windows') !== false) {
        return 'Windows';
    } elseif (strpos($userAgent, 'Mac') !== false) {
        return 'MacOS';
    } elseif (strpos($userAgent, 'Linux') !== false) {
        return 'Linux';
    } elseif (strpos($userAgent, 'Android') !== false) {
        return 'Android';
    } elseif (strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false) {
        return 'iOS';
    } else {
        return 'Inconnu';
    }
}

/**
 * Récupère la date et l'heure actuelles formatées
 */
function maintenant() {
    return date('Y-m-d H:i:s');
}

/**
 * Récupère la date actuelle formatée
 */
function aujourdhui() {
    return date('d/m/Y');
}

/**
 * Génère un tableau d'options de sélection (select)
 */
function generer_options_select($options, $selected = null) {
    $html = '';
    foreach ($options as $valeur => $label) {
        $selectedAttr = ($selected == $valeur) ? ' selected' : '';
        $html .= '<option value="' . echapper_html($valeur) . '"' . $selectedAttr . '>' . echapper_html($label) . '</option>';
    }
    return $html;
}

/**
 * Vérifie si une requête est AJAX
 */
function est_ajax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Redirige avec un message flash
 */
function redirect_avec_message($url, $message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header('Location: ' . $url);
    exit;
}

/**
 * Affiche un message flash
 */
function afficher_message_flash() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        
        return '<div class="alert alert-' . $type . '">' . $message . '</div>';
    }
    return '';
}

// =============================================
// FIN DU FICHIER FUNCTIONS.PHP
// =============================================
?>