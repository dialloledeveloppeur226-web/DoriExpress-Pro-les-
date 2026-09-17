<?php
/**
 * =============================================
 * MODIFICATION D'UN RESTAURANT - ADMIN - DoriExpress-Pro
 * =============================================
 * Fichier : admin/restaurant-edit.php
 * Rôle : Modifier les informations d'un restaurant
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

$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

try {
    $db = Database::getInstance();
    
    // Récupérer le restaurant
    $restaurant = $db->fetchOne(
        "SELECT p.*, u.nom, u.prenom, u.email, u.telephone, u.photo
         FROM partenaires p
         JOIN utilisateurs u ON p.utilisateur_id = u.id
         WHERE p.id = ? AND p.type_activite = 'restaurant'",
        [$id]
    );
    
    if (!$restaurant) {
        header('Location: ' . URL_BASE . 'admin/restaurants.php');
        exit;
    }
    
    // Traitement du formulaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (!verifier_token_csrf($csrf_token)) {
            $error = 'Erreur de sécurité.';
        } else {
            $nom_entreprise = trim($_POST['nom_entreprise'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $adresse = trim($_POST['adresse'] ?? '');
            $telephone = trim($_POST['telephone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $horaires_ouverture = trim($_POST['horaires_ouverture'] ?? '');
            $horaires_fermeture = trim($_POST['horaires_fermeture'] ?? '');
            $temps_preparation = (int)($_POST['temps_preparation'] ?? 15);
            $commission_pourcentage = (float)($_POST['commission_pourcentage'] ?? 15);
            $statut_validation = $_POST['statut_validation'] ?? 'en_attente';
            $est_public = isset($_POST['est_public']) ? 1 : 0;
            
            $errors = [];
            if (empty($nom_entreprise)) $errors[] = 'Le nom est requis';
            if (empty($telephone)) $errors[] = 'Le téléphone est requis';
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide';
            
            if (empty($errors)) {
                try {
                    $db->beginTransaction();
                    
                    // Mettre à jour le partenaire
                    $db->query(
                        "UPDATE partenaires SET 
                            nom_entreprise = ?, description = ?, adresse = ?, 
                            telephone = ?, email = ?, 
                            horaires_ouverture = ?, horaires_fermeture = ?,
                            temps_preparation_moyen = ?, commission_pourcentage = ?,
                            statut_validation = ?, est_public = ?,
                            date_modification = NOW()
                         WHERE id = ?",
                        [
                            $nom_entreprise, $description, $adresse,
                            $telephone, $email,
                            $horaires_ouverture, $horaires_fermeture,
                            $temps_preparation, $commission_pourcentage,
                            $statut_validation, $est_public,
                            $id
                        ]
                    );
                    
                    // Mettre à jour l'utilisateur
                    $db->query(
                        "UPDATE utilisateurs SET telephone = ?, email = ? WHERE id = ?",
                        [$telephone, $email, $restaurant['utilisateur_id']]
                    );
                    
                    $db->commit();
                    $success = '✅ Restaurant mis à jour avec succès !';
                    
                    // Recharger les données
                    $restaurant = $db->fetchOne(
                        "SELECT p.*, u.nom, u.prenom, u.email, u.telephone, u.photo
                         FROM partenaires p
                         JOIN utilisateurs u ON p.utilisateur_id = u.id
                         WHERE p.id = ?",
                        [$id]
                    );
                    
                } catch (Exception $e) {
                    $db->rollback();
                    $error = 'Erreur lors de la mise à jour.';
                }
            } else {
                $error = implode('<br>', $errors);
            }
        }
    }
    
} catch (Exception $e) {
    $restaurant = null;
    $error = 'Erreur de chargement.';
}

$page_title = 'Modifier restaurant - DoriExpress-Pro';
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
.page-restaurant-edit {
    background: #f8fafc;
    min-height: 100vh;
    padding: 20px 0 60px;
}

.edit-container {
    max-width: 800px;
    margin: 0 auto;
    background: white;
    border-radius: 16px;
    padding: 30px;
    border: 1px solid #e5e7eb;
}

.edit-container .edit-title {
    font-size: 20px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #00A651;
}

.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: block;
    font-weight: 600;
    font-size: 14px;
    color: #374151;
    margin-bottom: 4px;
}

.form-group .form-control {
    width: 100%;
    padding: 10px 14px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: #f9fafb;
}

.form-group .form-control:focus {
    border-color: #00A651;
    box-shadow: 0 0 0 4px rgba(0, 166, 81, 0.1);
    outline: none;
}

.form-group .form-control[type="number"] {
    max-width: 150px;
}

.form-group .form-hint {
    font-size: 12px;
    color: #6b7280;
    margin-top: 4px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.form-check {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
}

.form-check input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #00A651;
}

.btn-save {
    padding: 12px 35px;
    background: #00A651;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-save:hover {
    background: #008a44;
    transform: translateY(-2px);
}

.btn-cancel {
    padding: 12px 35px;
    background: transparent;
    color: #6b7280;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
}

.btn-cancel:hover {
    border-color: #00A651;
    color: #00A651;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    .edit-container {
        padding: 20px;
        margin: 0 10px;
    }
}

.dark-mode .page-restaurant-edit { background: #121212; }
.dark-mode .edit-container { background: #1e1e1e; border-color: #333; }
.dark-mode .edit-container .edit-title { color: #e5e5e5; border-bottom-color: #00A651; }
.dark-mode .form-group label { color: #d0d0d0; }
.dark-mode .form-group .form-control { background: #1a1a1a; border-color: #444; color: #e5e5e5; }
.dark-mode .form-group .form-control:focus { border-color: #00A651; }
.dark-mode .btn-cancel { color: #b0b0b0; border-color: #444; }
.dark-mode .btn-cancel:hover { border-color: #00A651; color: #00A651; }
</style>

<div class="page-restaurant-edit">
    <div class="container">
        <div class="edit-container">
            <div class="edit-title">
                <i class="fas fa-edit" style="color:#00A651;"></i> 
                Modifier le restaurant : <?php echo htmlspecialchars($restaurant['nom_entreprise'] ?? ''); ?>
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
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="csrf_token" value="<?php echo generer_token_csrf(); ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="nom_entreprise">Nom du restaurant <span class="required">*</span></label>
                        <input type="text" class="form-control" id="nom_entreprise" name="nom_entreprise" 
                               value="<?php echo htmlspecialchars($restaurant['nom_entreprise'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="telephone">Téléphone <span class="required">*</span></label>
                        <input type="tel" class="form-control" id="telephone" name="telephone" 
                               value="<?php echo htmlspecialchars($restaurant['telephone'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo htmlspecialchars($restaurant['email'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($restaurant['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="adresse">Adresse</label>
                    <input type="text" class="form-control" id="adresse" name="adresse" 
                           value="<?php echo htmlspecialchars($restaurant['adresse'] ?? ''); ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="horaires_ouverture">Ouverture</label>
                        <input type="time" class="form-control" id="horaires_ouverture" name="horaires_ouverture" 
                               value="<?php echo htmlspecialchars($restaurant['horaires_ouverture'] ?? '07:00'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="horaires_fermeture">Fermeture</label>
                        <input type="time" class="form-control" id="horaires_fermeture" name="horaires_fermeture" 
                               value="<?php echo htmlspecialchars($restaurant['horaires_fermeture'] ?? '22:00'); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="temps_preparation">Temps de préparation (min)</label>
                        <input type="number" class="form-control" id="temps_preparation" name="temps_preparation" 
                               value="<?php echo $restaurant['temps_preparation_moyen'] ?? 15; ?>" min="1" max="120">
                    </div>
                    <div class="form-group">
                        <label for="commission_pourcentage">Commission (%)</label>
                        <input type="number" class="form-control" id="commission_pourcentage" name="commission_pourcentage" 
                               value="<?php echo $restaurant['commission_pourcentage'] ?? 15; ?>" min="0" max="50" step="0.5">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="statut_validation">Statut</label>
                        <select class="form-control" id="statut_validation" name="statut_validation">
                            <option value="en_attente" <?php echo ($restaurant['statut_validation'] ?? '') == 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                            <option value="verifie" <?php echo ($restaurant['statut_validation'] ?? '') == 'verifie' ? 'selected' : ''; ?>>Vérifié</option>
                            <option value="actif" <?php echo ($restaurant['statut_validation'] ?? '') == 'actif' ? 'selected' : ''; ?>>Actif</option>
                            <option value="suspendu" <?php echo ($restaurant['statut_validation'] ?? '') == 'suspendu' ? 'selected' : ''; ?>>Suspendu</option>
                            <option value="ferme" <?php echo ($restaurant['statut_validation'] ?? '') == 'ferme' ? 'selected' : ''; ?>>Fermé</option>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex; align-items:center; padding-top:20px;">
                        <div class="form-check">
                            <input type="checkbox" id="est_public" name="est_public" <?php echo ($restaurant['est_public'] ?? 1) ? 'checked' : ''; ?>>
                            <label for="est_public">Visible publiquement</label>
                        </div>
                    </div>
                </div>
                
                <div style="display:flex; gap:12px; margin-top:20px; flex-wrap:wrap;">
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                    <a href="<?php echo URL_BASE; ?>admin/restaurant-detail.php?id=<?php echo $id; ?>" class="btn-cancel">
                        <i class="fas fa-times"></i> Annuler
                    </a>
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