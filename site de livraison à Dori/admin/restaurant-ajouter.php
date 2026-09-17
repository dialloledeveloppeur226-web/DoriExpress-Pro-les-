<?php
/**
 * =============================================
 * AJOUT D'UN RESTAURANT - ADMIN - DoriExpress-Pro
 * =============================================
 * Fichier : admin/restaurant-ajouter.php
 * Rôle : Ajouter un nouveau restaurant
 * Niveau : Premium
 * =============================================
 */

define('DOSSIER_RACINE', dirname(__DIR__) . '/');
require_once DOSSIER_RACINE . 'includes/config.php';
require_once DOSSIER_RACINE . 'includes/connexion.php';
require_once DOSSIER_RACINE . 'includes/auth.php';
require_once DOSSIER_RACINE . 'includes/security.php';
require_once DOSSIER_RACINE . 'includes/functions.php';

if (!est_connecte() || !est_admin()) {
    header('Location: ' . URL_BASE . 'login.php');
    exit;
}

$error = '';
$success = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifier_token_csrf($csrf_token)) {
        $error = 'Erreur de sécurité.';
    } else {
        $nom_entreprise = trim($_POST['nom_entreprise'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $horaires_ouverture = trim($_POST['horaires_ouverture'] ?? '07:00');
        $horaires_fermeture = trim($_POST['horaires_fermeture'] ?? '22:00');
        $temps_preparation = (int)($_POST['temps_preparation'] ?? 15);
        $commission_pourcentage = (float)($_POST['commission_pourcentage'] ?? 15);
        
        $errors = [];
        if (empty($nom_entreprise)) $errors[] = 'Le nom est requis';
        if (empty($telephone)) $errors[] = 'Le téléphone est requis';
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide';
        
        if (empty($errors)) {
            try {
                $db = Database::getInstance();
                $db->beginTransaction();
                
                // Vérifier si l'utilisateur existe déjà
                $user = $db->fetchOne("SELECT id FROM utilisateurs WHERE email = ? OR telephone = ?", [$email, $telephone]);
                
                if ($user) {
                    $utilisateur_id = $user['id'];
                } else {
                    // Créer l'utilisateur
                    $password = generer_mot_de_passe(12);
                    $password_hash = hash_mot_de_passe($password);
                    
                    $db->query(
                        "INSERT INTO utilisateurs (nom, prenom, email, telephone, mot_de_passe_hash, role, statut, date_creation) 
                         VALUES (?, ?, ?, ?, ?, 'partenaire', 'actif', NOW())",
                        ['Restaurant', 'Admin', $email, $telephone, $password_hash]
                    );
                    $utilisateur_id = $db->lastInsertId();
                    
                    // Créer le wallet
                    $db->query("INSERT INTO wallet (utilisateur_id, solde) VALUES (?, 0)", [$utilisateur_id]);
                }
                
                // Créer le partenaire (restaurant)
                $db->query(
                    "INSERT INTO partenaires (
                        utilisateur_id, nom_entreprise, type_activite, description, adresse,
                        telephone, email, horaires_ouverture, horaires_fermeture,
                        temps_preparation_moyen, commission_pourcentage,
                        statut_validation, est_public, date_inscription
                    ) VALUES (?, ?, 'restaurant', ?, ?, ?, ?, ?, ?, ?, ?, 'actif', 1, NOW())",
                    [
                        $utilisateur_id, $nom_entreprise, $description, $adresse,
                        $telephone, $email, $horaires_ouverture, $horaires_fermeture,
                        $temps_preparation, $commission_pourcentage
                    ]
                );
                
                $partenaire_id = $db->lastInsertId();
                
                $db->commit();
                $success = '✅ Restaurant ajouté avec succès !';
                
                // Redirection après succès
                header('Location: ' . URL_BASE . 'admin/restaurant-detail.php?id=' . $partenaire_id . '&success=added');
                exit;
                
            } catch (Exception $e) {
                $db->rollback();
                $error = 'Erreur lors de l\'ajout.';
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}

$page_title = 'Ajouter un restaurant - DoriExpress-Pro';
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* Utilise les mêmes styles que restaurant-edit.php */
.page-restaurant-ajout {
    background: #f8fafc;
    min-height: 100vh;
    padding: 20px 0 60px;
}
/* Les styles sont hérités de la page edit */
</style>

<div class="page-restaurant-ajout">
    <div class="container">
        <div class="edit-container">
            <div class="edit-title">
                <i class="fas fa-plus-circle" style="color:#00A651;"></i> Ajouter un nouveau restaurant
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger" style="margin-bottom:20px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success" style="margin-bottom:20px;">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="ajouter">
                <input type="hidden" name="csrf_token" value="<?php echo generer_token_csrf(); ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="nom_entreprise">Nom du restaurant <span class="required">*</span></label>
                        <input type="text" class="form-control" id="nom_entreprise" name="nom_entreprise" required>
                    </div>
                    <div class="form-group">
                        <label for="telephone">Téléphone <span class="required">*</span></label>
                        <input type="tel" class="form-control" id="telephone" name="telephone" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" class="form-control" id="email" name="email">
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="adresse">Adresse</label>
                    <input type="text" class="form-control" id="adresse" name="adresse">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="horaires_ouverture">Ouverture</label>
                        <input type="time" class="form-control" id="horaires_ouverture" name="horaires_ouverture" value="07:00">
                    </div>
                    <div class="form-group">
                        <label for="horaires_fermeture">Fermeture</label>
                        <input type="time" class="form-control" id="horaires_fermeture" name="horaires_fermeture" value="22:00">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="temps_preparation">Temps de préparation (min)</label>
                        <input type="number" class="form-control" id="temps_preparation" name="temps_preparation" value="15" min="1" max="120">
                    </div>
                    <div class="form-group">
                        <label for="commission_pourcentage">Commission (%)</label>
                        <input type="number" class="form-control" id="commission_pourcentage" name="commission_pourcentage" value="15" min="0" max="50" step="0.5">
                    </div>
                </div>
                
                <div style="display:flex; gap:12px; margin-top:20px; flex-wrap:wrap;">
                    <button type="submit" class="btn-save">
                        <i class="fas fa-plus"></i> Ajouter le restaurant
                    </button>
                    <a href="<?php echo URL_BASE; ?>admin/restaurants.php" class="btn-cancel">
                        <i class="fas fa-arrow-left"></i> Retour à la liste
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once DOSSIER_RACINE . 'includes/footer.php';
?>