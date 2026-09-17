<?php
/**
 * =============================================
 * CRON - NOTIFICATIONS AUTOMATIQUES - DoriExpress-Pro
 * =============================================
 * Fichier : cron/notifications.php
 * Rôle : Envoi des notifications programmées
 * Fréquence : Toutes les 5 minutes
 * =============================================
 */

// Définir le chemin racine
define('DOSSIER_RACINE', dirname(__DIR__) . '/');

// Inclure la configuration
require_once DOSSIER_RACINE . 'includes/config.php';
require_once DOSSIER_RACINE . 'includes/connexion.php';
require_once DOSSIER_RACINE . 'includes/auth.php';
require_once DOSSIER_RACINE . 'includes/functions.php';

// =============================================
// 1. ENVOYER LES NOTIFICATIONS EN ATTENTE
// =============================================
try {
    $db = Database::getInstance();
    
    // Récupérer les notifications en attente d'envoi
    $notifications = $db->fetchAll(
        "SELECT * FROM notifications 
         WHERE est_envoye_email = 0 
         AND date_creation > DATE_SUB(NOW(), INTERVAL 1 HOUR)
         LIMIT 50"
    );
    
    foreach ($notifications as $notif) {
        // Récupérer l'utilisateur
        $user = $db->fetchOne(
            "SELECT email, telephone, nom, prenom FROM utilisateurs WHERE id = ?",
            [$notif['utilisateur_id']]
        );
        
        if (!$user) continue;
        
        // Envoyer par email
        if (!empty($user['email'])) {
            $subject = $notif['titre'] . ' - DoriExpress-Pro';
            $message = "
                <html>
                <body style='font-family: Arial, sans-serif;'>
                    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <h2 style='color: #00A651;'>DoriExpress-Pro</h2>
                        <h3>" . $notif['titre'] . "</h3>
                        <p>" . $notif['message'] . "</p>
                        <hr>
                        <p style='color: #666; font-size: 12px;'>
                            Cet email a été envoyé automatiquement.
                            Pour ne plus recevoir de notifications, 
                            <a href='" . URL_BASE . "parametres.php'>modifiez vos préférences</a>.
                        </p>
                    </div>
                </body>
                </html>
            ";
            
            // Envoyer l'email via la fonction mail()
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: DoriExpress-Pro <" . get_parametre('email', 'contact@doriexpress.bf') . ">\r\n";
            
            mail($user['email'], $subject, $message, $headers);
        }
        
        // Envoyer par WhatsApp
        if (!empty($user['telephone'])) {
            $whatsapp_active = get_parametre('whatsapp_active', 1);
            if ($whatsapp_active) {
                $message = "📩 " . $notif['titre'] . "\n" . $notif['message'];
                envoyer_whatsapp($user['telephone'], $message);
            }
        }
        
        // Marquer comme envoyé
        $db->query(
            "UPDATE notifications SET est_envoye_email = 1 WHERE id = ?",
            [$notif['id']]
        );
    }
    
    echo "✅ " . count($notifications) . " notifications envoyées\n";
    
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}

// =============================================
// FIN DU FICHIER CRON/NOTIFICATIONS.PHP
// =============================================
?>