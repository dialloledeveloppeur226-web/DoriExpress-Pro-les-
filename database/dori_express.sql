-- =============================================
-- BASE DE DONNÉES : DoriExpress-Pro
-- =============================================
-- Description : Plateforme de livraison complète
-- Version : 1.0
-- Auteur : DoriExpress-Pro
-- Date : 2026
-- =============================================

CREATE DATABASE IF NOT EXISTS dori_express
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE dori_express;

-- =============================================
-- TABLE : utilisateurs (Comptes principaux)
-- =============================================
CREATE TABLE utilisateurs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    telephone VARCHAR(20) UNIQUE NOT NULL,
    mot_de_passe_hash VARCHAR(255) NOT NULL,
    photo VARCHAR(255) DEFAULT 'default.jpg',
    role ENUM('createur','admin','client','livreur','partenaire','support') DEFAULT 'client',
    statut ENUM('actif','inactif','suspendu','bloque') DEFAULT 'actif',
    verifie BOOLEAN DEFAULT FALSE,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    derniere_connexion DATETIME NULL,
    adresse_ip VARCHAR(45) NULL,
    token_session VARCHAR(255) NULL,
    deux_facteur_active BOOLEAN DEFAULT FALSE,
    deux_facteur_secret VARCHAR(255) NULL,
    INDEX idx_email (email),
    INDEX idx_telephone (telephone),
    INDEX idx_role_statut (role, statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : clients (Informations spécifiques clients)
-- =============================================
CREATE TABLE clients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL,
    adresse_principale VARCHAR(255),
    quartier VARCHAR(100),
    ville VARCHAR(100) DEFAULT 'Dori',
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,
    points_fidelite INT DEFAULT 0,
    niveau_client ENUM('bronze','argent','or','premium') DEFAULT 'bronze',
    total_commandes INT DEFAULT 0,
    total_depense DECIMAL(10,2) DEFAULT 0,
    code_parrainage VARCHAR(20) UNIQUE,
    preferences JSON NULL,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_utilisateur (utilisateur_id),
    INDEX idx_code_parrainage (code_parrainage)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : livreurs
-- =============================================
CREATE TABLE livreurs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL,
    type_vehicule ENUM('moto','velo','voiture','camionnette') DEFAULT 'moto',
    plaque_vehicule VARCHAR(20),
    marque_vehicule VARCHAR(50),
    zone_travail VARCHAR(100),
    disponibilite ENUM('disponible','occupe','en_pause','hors_ligne','suspendu') DEFAULT 'hors_ligne',
    note_moyenne DECIMAL(2,1) DEFAULT 0,
    total_livraisons INT DEFAULT 0,
    solde_disponible DECIMAL(10,2) DEFAULT 0,
    solde_en_attente DECIMAL(10,2) DEFAULT 0,
    statut_validation ENUM('en_attente','verification','valide','refuse','suspendu') DEFAULT 'en_attente',
    date_validation DATETIME NULL,
    date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP,
    latitude_actuelle DECIMAL(10,8) NULL,
    longitude_actuelle DECIMAL(11,8) NULL,
    derniere_position DATETIME NULL,
    score_performance INT DEFAULT 0,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_utilisateur (utilisateur_id),
    INDEX idx_disponibilite (disponibilite),
    INDEX idx_zone (zone_travail)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : documents_livreurs
-- =============================================
CREATE TABLE documents_livreurs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    livreur_id INT NOT NULL,
    type_document ENUM('cni','permis','photo_vehicule','assurance','autre') NOT NULL,
    fichier VARCHAR(255) NOT NULL,
    date_expiration DATE NULL,
    statut_validation ENUM('en_attente','valide','refuse') DEFAULT 'en_attente',
    date_upload DATETIME DEFAULT CURRENT_TIMESTAMP,
    commentaire_admin TEXT NULL,
    FOREIGN KEY (livreur_id) REFERENCES livreurs(id) ON DELETE CASCADE,
    INDEX idx_livreur (livreur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : partenaires (Restaurants, Boutiques, Commerces)
-- =============================================
CREATE TABLE partenaires (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL,
    nom_entreprise VARCHAR(150) NOT NULL,
    type_activite ENUM('restaurant','boutique','supermache','pharmacie','commerce','autre') NOT NULL,
    logo VARCHAR(255),
    couverture VARCHAR(255),
    description TEXT,
    adresse VARCHAR(255),
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,
    telephone VARCHAR(20),
    email VARCHAR(150),
    horaires_ouverture VARCHAR(50),
    horaires_fermeture VARCHAR(50),
    jours_ouverture VARCHAR(100) DEFAULT 'lun,mar,mer,jeu,ven,sam,dim',
    temps_preparation_moyen INT DEFAULT 15, -- en minutes
    commission_pourcentage DECIMAL(5,2) DEFAULT 15.00,
    abonnement_type ENUM('gratuit','standard','premium','business') DEFAULT 'gratuit',
    statut_validation ENUM('en_attente','verifie','actif','suspendu','ferme') DEFAULT 'en_attente',
    date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_validation DATETIME NULL,
    abonnement_date_fin DATETIME NULL,
    est_public BOOLEAN DEFAULT TRUE,
    vue_count INT DEFAULT 0,
    note_moyenne DECIMAL(2,1) DEFAULT 0,
    total_commandes INT DEFAULT 0,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_utilisateur (utilisateur_id),
    INDEX idx_type_activite (type_activite),
    INDEX idx_statut (statut_validation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : categories
-- =============================================
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    description TEXT,
    image VARCHAR(255),
    statut ENUM('actif','inactif') DEFAULT 'actif',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nom (nom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : produits
-- =============================================
CREATE TABLE produits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    partenaire_id INT NOT NULL,
    categorie_id INT NOT NULL,
    nom VARCHAR(200) NOT NULL,
    description TEXT,
    image VARCHAR(255),
    prix DECIMAL(10,2) NOT NULL,
    prix_promotion DECIMAL(10,2) NULL,
    stock INT DEFAULT 0,
    stock_min_alerte INT DEFAULT 5,
    disponibilite ENUM('disponible','indisponible','rupture') DEFAULT 'disponible',
    temps_preparation INT NULL, -- en minutes
    est_promotion BOOLEAN DEFAULT FALSE,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME NULL,
    FOREIGN KEY (partenaire_id) REFERENCES partenaires(id) ON DELETE CASCADE,
    FOREIGN KEY (categorie_id) REFERENCES categories(id),
    INDEX idx_partenaire (partenaire_id),
    INDEX idx_categorie (categorie_id),
    INDEX idx_disponibilite (disponibilite),
    INDEX idx_promotion (est_promotion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : variantes_produits (Tailles, options)
-- =============================================
CREATE TABLE variantes_produits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    produit_id INT NOT NULL,
    nom_variante VARCHAR(100) NOT NULL,
    valeur VARCHAR(100) NOT NULL,
    prix_supplementaire DECIMAL(10,2) DEFAULT 0,
    FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE,
    INDEX idx_produit (produit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : commandes
-- =============================================
CREATE TABLE commandes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code_commande VARCHAR(20) UNIQUE NOT NULL,
    client_id INT NOT NULL,
    livreur_id INT NULL,
    partenaire_id INT NULL,
    type_service ENUM('colis','repas','courses','express','depot','programme') NOT NULL,
    adresse_depart VARCHAR(255) NOT NULL,
    adresse_arrivee VARCHAR(255) NOT NULL,
    latitude_depart DECIMAL(10,8) NULL,
    longitude_depart DECIMAL(11,8) NULL,
    latitude_arrivee DECIMAL(10,8) NULL,
    longitude_arrivee DECIMAL(11,8) NULL,
    distance_km DECIMAL(6,2) DEFAULT 0,
    poids_kg DECIMAL(6,2) DEFAULT 0,
    description_colis TEXT NULL,
    instructions TEXT NULL,
    prix_base DECIMAL(10,2) NOT NULL,
    prix_distance DECIMAL(10,2) NOT NULL,
    prix_supplement DECIMAL(10,2) DEFAULT 0,
    reduction DECIMAL(10,2) DEFAULT 0,
    prix_total DECIMAL(10,2) NOT NULL,
    commission_plateforme DECIMAL(10,2) DEFAULT 0,
    commission_livreur DECIMAL(10,2) DEFAULT 0,
    methode_paiement ENUM('orange_money','moov_money','especes','wallet') NOT NULL,
    statut ENUM('brouillon','en_attente_paiement','payee','acceptee','preparation','livreur_assigne','recuperation','en_livraison','arrivee','livree','annulee','remboursee','litige','archivee') DEFAULT 'brouillon',
    statut_paiement ENUM('en_attente','valide','echoue','rembourse') DEFAULT 'en_attente',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_acceptation DATETIME NULL,
    date_preparation DATETIME NULL,
    date_livraison DATETIME NULL,
    date_annulation DATETIME NULL,
    date_archivage DATETIME NULL,
    heure_prise_en_charge DATETIME NULL,
    commentaire_livreur TEXT NULL,
    signature_client VARCHAR(255) NULL,
    qr_code VARCHAR(255) NULL,
    created_by_admin INT NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (livreur_id) REFERENCES livreurs(id),
    FOREIGN KEY (partenaire_id) REFERENCES partenaires(id),
    INDEX idx_code (code_commande),
    INDEX idx_client (client_id),
    INDEX idx_livreur (livreur_id),
    INDEX idx_statut (statut),
    INDEX idx_date (date_creation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : commande_details (Produits commandés)
-- =============================================
CREATE TABLE commande_details (
    id INT PRIMARY KEY AUTO_INCREMENT,
    commande_id INT NOT NULL,
    produit_id INT NOT NULL,
    quantite INT NOT NULL,
    prix_unitaire DECIMAL(10,2) NOT NULL,
    sous_total DECIMAL(10,2) NOT NULL,
    variante_json JSON NULL,
    FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id),
    INDEX idx_commande (commande_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : historique_commandes (Suivi des statuts)
-- =============================================
CREATE TABLE historique_commandes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    commande_id INT NOT NULL,
    ancien_statut VARCHAR(50),
    nouveau_statut VARCHAR(50) NOT NULL,
    commentaire TEXT,
    utilisateur_id INT NULL,
    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    INDEX idx_commande (commande_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : paiements
-- =============================================
CREATE TABLE paiements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    commande_id INT NOT NULL,
    utilisateur_id INT NOT NULL,
    methode ENUM('orange_money','moov_money','especes','wallet') NOT NULL,
    operateur VARCHAR(50) NULL,
    montant DECIMAL(10,2) NOT NULL,
    reference_transaction VARCHAR(100) UNIQUE,
    telephone_payeur VARCHAR(20),
    statut ENUM('en_attente','en_cours','valide','echoue','rembourse') DEFAULT 'en_attente',
    reponse_api JSON NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_validation DATETIME NULL,
    date_remboursement DATETIME NULL,
    valide_par INT NULL,
    FOREIGN KEY (commande_id) REFERENCES commandes(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (valide_par) REFERENCES utilisateurs(id),
    INDEX idx_commande (commande_id),
    INDEX idx_reference (reference_transaction),
    INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : wallet (Portefeuille utilisateurs)
-- =============================================
CREATE TABLE wallet (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL UNIQUE,
    solde DECIMAL(10,2) DEFAULT 0,
    devise VARCHAR(10) DEFAULT 'FCFA',
    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_utilisateur (utilisateur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : transactions_wallet
-- =============================================
CREATE TABLE transactions_wallet (
    id INT PRIMARY KEY AUTO_INCREMENT,
    wallet_id INT NOT NULL,
    type ENUM('credit','debit','bonus','commission','retrait','remboursement') NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    description TEXT,
    reference VARCHAR(100),
    solde_apres DECIMAL(10,2) NOT NULL,
    date_transaction DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (wallet_id) REFERENCES wallet(id) ON DELETE CASCADE,
    INDEX idx_wallet (wallet_id),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : commissions
-- =============================================
CREATE TABLE commissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    commande_id INT NOT NULL,
    pourcentage_plateforme DECIMAL(5,2) NOT NULL,
    montant_plateforme DECIMAL(10,2) NOT NULL,
    pourcentage_partenaire DECIMAL(5,2) DEFAULT 0,
    montant_partenaire DECIMAL(10,2) DEFAULT 0,
    pourcentage_livreur DECIMAL(5,2) DEFAULT 0,
    montant_livreur DECIMAL(10,2) DEFAULT 0,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (commande_id) REFERENCES commandes(id),
    INDEX idx_commande (commande_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : retraits_livreurs
-- =============================================
CREATE TABLE retraits_livreurs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    livreur_id INT NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    moyen_paiement ENUM('orange_money','moov_money','especes') NOT NULL,
    telephone_destinataire VARCHAR(20),
    statut ENUM('en_attente','valide','paye','refuse') DEFAULT 'en_attente',
    date_demande DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_traitement DATETIME NULL,
    traite_par INT NULL,
    commentaire_admin TEXT NULL,
    FOREIGN KEY (livreur_id) REFERENCES livreurs(id),
    FOREIGN KEY (traite_par) REFERENCES utilisateurs(id),
    INDEX idx_livreur (livreur_id),
    INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : promotions
-- =============================================
CREATE TABLE promotions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    titre VARCHAR(200) NOT NULL,
    description TEXT,
    image VARCHAR(255),
    type_reduction ENUM('pourcentage','fixe') NOT NULL,
    valeur_reduction DECIMAL(10,2) NOT NULL,
    date_debut DATETIME NOT NULL,
    date_fin DATETIME NOT NULL,
    statut ENUM('actif','inactif','termine') DEFAULT 'actif',
    visibilite ENUM('public','prive') DEFAULT 'public',
    services_concernes JSON NULL,
    partenaires_concernes JSON NULL,
    zones_concernes JSON NULL,
    utilisation_max INT DEFAULT 0,
    utilisation_actuelle INT DEFAULT 0,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    cree_par INT NOT NULL,
    FOREIGN KEY (cree_par) REFERENCES utilisateurs(id),
    INDEX idx_statut (statut),
    INDEX idx_dates (date_debut, date_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : codes_promo
-- =============================================
CREATE TABLE codes_promo (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    promotion_id INT NOT NULL,
    type_reduction ENUM('pourcentage','fixe','livraison_offerte') NOT NULL,
    valeur DECIMAL(10,2) NOT NULL,
    utilisation_max INT DEFAULT 0,
    utilisation_actuelle INT DEFAULT 0,
    date_expiration DATETIME NOT NULL,
    statut ENUM('actif','inactif','expire') DEFAULT 'actif',
    visibilite ENUM('public','prive') DEFAULT 'public',
    utilisateur_autorise_id INT NULL,
    commande_minimum DECIMAL(10,2) DEFAULT 0,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    cree_par INT NOT NULL,
    FOREIGN KEY (promotion_id) REFERENCES promotions(id),
    FOREIGN KEY (utilisateur_autorise_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (cree_par) REFERENCES utilisateurs(id),
    INDEX idx_code (code),
    INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : parrainages
-- =============================================
CREATE TABLE parrainages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    parrain_id INT NOT NULL,
    filleul_id INT NOT NULL,
    code_parrainage VARCHAR(20) NOT NULL,
    recompense_parrain DECIMAL(10,2) DEFAULT 0,
    recompense_filleul DECIMAL(10,2) DEFAULT 0,
    statut ENUM('en_attente','valide','termine') DEFAULT 'en_attente',
    date_parrainage DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_validation DATETIME NULL,
    FOREIGN KEY (parrain_id) REFERENCES clients(id),
    FOREIGN KEY (filleul_id) REFERENCES clients(id),
    INDEX idx_parrain (parrain_id),
    INDEX idx_filleul (filleul_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : fidelite
-- =============================================
CREATE TABLE fidelite (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NOT NULL,
    points INT DEFAULT 0,
    niveau ENUM('bronze','argent','or','premium') DEFAULT 'bronze',
    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    INDEX idx_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : avis
-- =============================================
CREATE TABLE avis (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NOT NULL,
    commande_id INT NOT NULL,
    livreur_id INT NULL,
    partenaire_id INT NULL,
    note INT CHECK (note BETWEEN 1 AND 5),
    commentaire TEXT,
    reponse_commentaire TEXT NULL,
    photo VARCHAR(255) NULL,
    est_visible BOOLEAN DEFAULT TRUE,
    signale BOOLEAN DEFAULT FALSE,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_reponse DATETIME NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (commande_id) REFERENCES commandes(id),
    FOREIGN KEY (livreur_id) REFERENCES livreurs(id),
    FOREIGN KEY (partenaire_id) REFERENCES partenaires(id),
    INDEX idx_client (client_id),
    INDEX idx_commande (commande_id),
    INDEX idx_partenaire (partenaire_id),
    INDEX idx_livreur (livreur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : messages (Chat)
-- =============================================
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    expediteur_id INT NOT NULL,
    destinataire_id INT NOT NULL,
    commande_id INT NULL,
    message TEXT NOT NULL,
    fichier VARCHAR(255) NULL,
    est_lu BOOLEAN DEFAULT FALSE,
    date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP,
    est_supprime_exp BOOLEAN DEFAULT FALSE,
    est_supprime_dest BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (expediteur_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (destinataire_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (commande_id) REFERENCES commandes(id),
    INDEX idx_expediteur (expediteur_id),
    INDEX idx_destinataire (destinataire_id),
    INDEX idx_commande (commande_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : notifications
-- =============================================
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL,
    titre VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('commande','paiement','livraison','promotion','message','securite','systeme') NOT NULL,
    lien VARCHAR(255) NULL,
    est_lu BOOLEAN DEFAULT FALSE,
    est_envoye_email BOOLEAN DEFAULT FALSE,
    est_envoye_whatsapp BOOLEAN DEFAULT FALSE,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_lecture DATETIME NULL,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_utilisateur (utilisateur_id),
    INDEX idx_lu (est_lu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : tickets_support
-- =============================================
CREATE TABLE tickets_support (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL,
    categorie ENUM('commande','paiement','livreur','partenaire','compte','remboursement','autre') NOT NULL,
    sujet VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    fichier VARCHAR(255) NULL,
    priorite ENUM('basse','moyenne','haute','urgent') DEFAULT 'moyenne',
    statut ENUM('nouveau','en_traitement','resolu','ferme') DEFAULT 'nouveau',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_resolution DATETIME NULL,
    resolu_par INT NULL,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (resolu_par) REFERENCES utilisateurs(id),
    INDEX idx_utilisateur (utilisateur_id),
    INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : reponses_tickets
-- =============================================
CREATE TABLE reponses_tickets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ticket_id INT NOT NULL,
    utilisateur_id INT NOT NULL,
    message TEXT NOT NULL,
    fichier VARCHAR(255) NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets_support(id) ON DELETE CASCADE,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    INDEX idx_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : litiges
-- =============================================
CREATE TABLE litiges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    commande_id INT NOT NULL,
    client_id INT NOT NULL,
    livreur_id INT NULL,
    partenaire_id INT NULL,
    motif VARCHAR(200) NOT NULL,
    description TEXT,
    preuve VARCHAR(255) NULL,
    statut ENUM('ouvert','en_analyse','accepte','refuse','ferme') DEFAULT 'ouvert',
    decision_admin TEXT NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_cloture DATETIME NULL,
    traite_par INT NULL,
    FOREIGN KEY (commande_id) REFERENCES commandes(id),
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (livreur_id) REFERENCES livreurs(id),
    FOREIGN KEY (partenaire_id) REFERENCES partenaires(id),
    FOREIGN KEY (traite_par) REFERENCES utilisateurs(id),
    INDEX idx_commande (commande_id),
    INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : positions_gps
-- =============================================
CREATE TABLE positions_gps (
    id INT PRIMARY KEY AUTO_INCREMENT,
    livreur_id INT NOT NULL,
    commande_id INT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    vitesse DECIMAL(5,2) NULL,
    precision_m DECIMAL(5,2) NULL,
    date_enregistrement DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (livreur_id) REFERENCES livreurs(id),
    FOREIGN KEY (commande_id) REFERENCES commandes(id),
    INDEX idx_livreur (livreur_id),
    INDEX idx_date (date_enregistrement)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : photos_livraison
-- =============================================
CREATE TABLE photos_livraison (
    id INT PRIMARY KEY AUTO_INCREMENT,
    commande_id INT NOT NULL,
    livreur_id INT NOT NULL,
    fichier VARCHAR(255) NOT NULL,
    commentaire TEXT,
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,
    date_upload DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (commande_id) REFERENCES commandes(id),
    FOREIGN KEY (livreur_id) REFERENCES livreurs(id),
    INDEX idx_commande (commande_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : planning_livreurs
-- =============================================
CREATE TABLE planning_livreurs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    livreur_id INT NOT NULL,
    jour VARCHAR(10) NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,
    statut ENUM('actif','inactif','conges','absence') DEFAULT 'actif',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (livreur_id) REFERENCES livreurs(id),
    INDEX idx_livreur (livreur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : abonnements
-- =============================================
CREATE TABLE abonnements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    type ENUM('client','partenaire','restaurant','boutique') NOT NULL,
    description TEXT,
    prix DECIMAL(10,2) NOT NULL,
    duree_mois INT NOT NULL,
    avantages JSON,
    limites JSON,
    statut ENUM('actif','inactif') DEFAULT 'actif',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : souscriptions
-- =============================================
CREATE TABLE souscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL,
    abonnement_id INT NOT NULL,
    date_debut DATETIME NOT NULL,
    date_fin DATETIME NOT NULL,
    statut ENUM('actif','expire','annule') DEFAULT 'actif',
    renouvellement_auto BOOLEAN DEFAULT FALSE,
    paiement_id INT NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (abonnement_id) REFERENCES abonnements(id),
    FOREIGN KEY (paiement_id) REFERENCES paiements(id),
    INDEX idx_utilisateur (utilisateur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : parametres (Configuration sans code)
-- =============================================
CREATE TABLE parametres (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cle VARCHAR(100) UNIQUE NOT NULL,
    valeur TEXT,
    categorie VARCHAR(50) NOT NULL,
    type VARCHAR(20) DEFAULT 'text',
    modifiable BOOLEAN DEFAULT TRUE,
    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cle (cle),
    INDEX idx_categorie (categorie)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- INSERTION DES PARAMÈTRES PAR DÉFAUT
-- =============================================
INSERT INTO parametres (cle, valeur, categorie, type) VALUES
-- Entreprise
('nom_site', 'DoriExpress-Pro', 'entreprise', 'text'),
('slogan', 'Votre livraison rapide à Dori', 'entreprise', 'text'),
('description', 'Plateforme de livraison professionnelle à Dori, Burkina Faso', 'entreprise', 'textarea'),
('adresse', 'Dori, Burkina Faso', 'entreprise', 'text'),
('telephone', '61874528', 'entreprise', 'text'),
('email', 'contact@doriexpress.bf', 'entreprise', 'email'),
('whatsapp', '61874528', 'entreprise', 'text'),
('logo', 'logo.png', 'entreprise', 'file'),
('favicon', 'favicon.ico', 'entreprise', 'file'),

-- Tarifs
('prix_base', '500', 'tarifs', 'number'),
('prix_km', '200', 'tarifs', 'number'),
('prix_kg', '50', 'tarifs', 'number'),
('frais_express', '1000', 'tarifs', 'number'),
('frais_minimum', '500', 'tarifs', 'number'),
('seuil_retrait_livreur', '10000', 'tarifs', 'number'),

-- Commissions
('commission_plateforme', '15', 'finances', 'number'),
('commission_restaurant', '15', 'finances', 'number'),
('commission_boutique', '20', 'finances', 'number'),
('commission_livreur', '80', 'finances', 'number'),

-- Livraison
('rayon_livraison_km', '15', 'livraison', 'number'),
('temps_livraison_moyen', '45', 'livraison', 'number'),
('livraison_express_active', '1', 'livraison', 'checkbox'),
('livraison_nuit_active', '0', 'livraison', 'checkbox'),
('livraison_dimanche_active', '1', 'livraison', 'checkbox'),
('horaires_ouverture', '07:00', 'livraison', 'time'),
('horaires_fermeture', '22:00', 'livraison', 'time'),

-- Design
('couleur_primaire', '#00A651', 'design', 'color'),
('couleur_secondaire', '#1A1A1A', 'design', 'color'),
('couleur_fond', '#FFFFFF', 'design', 'color'),
('mode_sombre', '0', 'design', 'checkbox'),
('glassmorphisme', '1', 'design', 'checkbox'),
('animations', '1', 'design', 'checkbox'),

-- Sécurité
('tentatives_connexion_max', '5', 'securite', 'number'),
('blocage_temps_minutes', '15', 'securite', 'number'),
('mot_de_passe_complexe', '1', 'securite', 'checkbox'),
('2fa_obligatoire_admin', '0', 'securite', 'checkbox'),
('sessions_expiration_heures', '24', 'securite', 'number'),
('logs_conservation_jours', '30', 'securite', 'number'),

-- Maintenance
('maintenance_active', '0', 'maintenance', 'checkbox'),
('maintenance_message', 'Site en maintenance, revenez bientôt !', 'maintenance', 'textarea'),

-- PWA
('pwa_active', '1', 'pwa', 'checkbox'),
('pwa_nom', 'DoriExpress-Pro', 'pwa', 'text'),
('pwa_couleur_theme', '#00A651', 'pwa', 'color'),
('pwa_couleur_fond', '#FFFFFF', 'pwa', 'color'),

-- WhatsApp
('whatsapp_message_commande', '🔔 Nouvelle commande DoriExpress-Pro reçue !', 'whatsapp', 'textarea'),
('whatsapp_message_paiement', '✅ Votre paiement a été reçu avec succès !', 'whatsapp', 'textarea'),
('whatsapp_message_livraison', '📦 Votre commande est arrivée !', 'whatsapp', 'textarea'),

-- Google Maps
('google_maps_api_key', '', 'google_maps', 'text'),
('google_maps_zoom_default', '15', 'google_maps', 'number'),
('google_maps_latitude_default', '14.0330', 'google_maps', 'text'),
('google_maps_longitude_default', '-0.0330', 'google_maps', 'text'),

-- Orange Money
('orange_money_active', '1', 'orange_money', 'checkbox'),
('orange_money_api_key', '', 'orange_money', 'text'),
('orange_money_merchant_id', '', 'orange_money', 'text'),

-- Moov Money
('moov_money_active', '1', 'moov_money', 'checkbox'),
('moov_money_api_key', '', 'moov_money', 'text'),

-- Email (SMTP)
('smtp_active', '0', 'smtp', 'checkbox'),
('smtp_host', 'smtp.gmail.com', 'smtp', 'text'),
('smtp_port', '587', 'smtp', 'number'),
('smtp_username', '', 'smtp', 'text'),
('smtp_password', '', 'smtp', 'password'),
('smtp_secure', 'tls', 'smtp', 'text'),
('email_from', 'contact@doriexpress.bf', 'smtp', 'email'),
('email_from_name', 'DoriExpress-Pro', 'smtp', 'text'),

-- Modules
('module_chat_active', '1', 'modules', 'checkbox'),
('module_gps_active', '1', 'modules', 'checkbox'),
('module_wallet_active', '1', 'modules', 'checkbox'),
('module_blog_active', '1', 'modules', 'checkbox'),
('module_promotions_active', '1', 'modules', 'checkbox'),
('module_parrainage_active', '1', 'modules', 'checkbox'),
('module_ia_active', '1', 'modules', 'checkbox'),
('module_marketplace_active', '1', 'modules', 'checkbox'),

-- IA - Paramètres
('ia_estimation_prix_active', '1', 'ia', 'checkbox'),
('ia_anti_fraude_active', '1', 'ia', 'checkbox'),
('ia_prevision_active', '1', 'ia', 'checkbox'),
('ia_analyse_revenus_active', '1', 'ia', 'checkbox'),
('ia_seuil_alerte_commandes', '50', 'ia', 'number');

-- =============================================
-- TABLE : logs
-- =============================================
CREATE TABLE logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NULL,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(50) NOT NULL,
    adresse_ip VARCHAR(45),
    details JSON,
    date_log DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    INDEX idx_utilisateur (utilisateur_id),
    INDEX idx_action (action),
    INDEX idx_date (date_log)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : sessions
-- =============================================
CREATE TABLE sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    appareil VARCHAR(100),
    adresse_ip VARCHAR(45),
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_expiration DATETIME NOT NULL,
    est_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_utilisateur (utilisateur_id),
    INDEX idx_expiration (date_expiration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : articles_blog
-- =============================================
CREATE TABLE articles_blog (
    id INT PRIMARY KEY AUTO_INCREMENT,
    titre VARCHAR(200) NOT NULL,
    slug VARCHAR(200) UNIQUE NOT NULL,
    contenu LONGTEXT,
    image VARCHAR(255),
    categorie VARCHAR(100),
    auteur_id INT NOT NULL,
    statut ENUM('brouillon','publie','archive') DEFAULT 'brouillon',
    vue_count INT DEFAULT 0,
    date_publication DATETIME,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME NULL,
    meta_titre VARCHAR(200),
    meta_description VARCHAR(300),
    meta_keywords VARCHAR(200),
    FOREIGN KEY (auteur_id) REFERENCES utilisateurs(id),
    INDEX idx_slug (slug),
    INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : commentaires_blog
-- =============================================
CREATE TABLE commentaires_blog (
    id INT PRIMARY KEY AUTO_INCREMENT,
    article_id INT NOT NULL,
    utilisateur_id INT NOT NULL,
    commentaire TEXT NOT NULL,
    statut ENUM('en_attente','approuve','supprime') DEFAULT 'en_attente',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (article_id) REFERENCES articles_blog(id) ON DELETE CASCADE,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    INDEX idx_article (article_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : newsletter
-- =============================================
CREATE TABLE newsletter (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(150) UNIQUE NOT NULL,
    nom VARCHAR(100),
    statut ENUM('actif','desabonne') DEFAULT 'actif',
    date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : seo_pages
-- =============================================
CREATE TABLE seo_pages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    page VARCHAR(100) UNIQUE NOT NULL,
    titre VARCHAR(200),
    description VARCHAR(300),
    keywords VARCHAR(200),
    image_social VARCHAR(255),
    canonical VARCHAR(255),
    robots VARCHAR(50) DEFAULT 'index,follow',
    INDEX idx_page (page)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : zones_livraison
-- =============================================
CREATE TABLE zones_livraison (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    description TEXT,
    latitude_centre DECIMAL(10,8),
    longitude_centre DECIMAL(11,8),
    rayon_km DECIMAL(6,2),
    prix_supplementaire DECIMAL(10,2) DEFAULT 0,
    est_active BOOLEAN DEFAULT TRUE,
    temps_moyen INT NULL,
    INDEX idx_nom (nom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : publicites
-- =============================================
CREATE TABLE publicites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    titre VARCHAR(200) NOT NULL,
    image VARCHAR(255),
    lien VARCHAR(255),
    position ENUM('accueil','sidebar','footer','popup') NOT NULL,
    date_debut DATETIME NOT NULL,
    date_fin DATETIME NOT NULL,
    statut ENUM('actif','inactif','termine') DEFAULT 'actif',
    clics INT DEFAULT 0,
    vues INT DEFAULT 0,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_statut (statut),
    INDEX idx_dates (date_debut, date_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : backups
-- =============================================
CREATE TABLE backups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom_fichier VARCHAR(255) NOT NULL,
    taille VARCHAR(50),
    type ENUM('base_donnees','fichiers','complet') NOT NULL,
    statut ENUM('en_cours','termine','echoue') DEFAULT 'termine',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    cree_par INT NOT NULL,
    FOREIGN KEY (cree_par) REFERENCES utilisateurs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : modules
-- =============================================
CREATE TABLE modules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) UNIQUE NOT NULL,
    version VARCHAR(20),
    description TEXT,
    statut ENUM('installe','actif','inactif','desinstalle') DEFAULT 'actif',
    configuration JSON,
    date_installation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : statistiques
-- =============================================
CREATE TABLE statistiques (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(50) NOT NULL,
    valeur DECIMAL(15,2) NOT NULL,
    periode VARCHAR(20) NOT NULL,
    date_stat DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_periode (periode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : analyses_ia
-- =============================================
CREATE TABLE analyses_ia (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type_analyse VARCHAR(50) NOT NULL,
    donnees JSON,
    resultat JSON,
    confiance DECIMAL(3,2),
    date_analyse DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (type_analyse)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : anti_fraude
-- =============================================
CREATE TABLE anti_fraude (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL,
    type_alerte VARCHAR(100) NOT NULL,
    niveau_risque ENUM('faible','moyen','eleve','critique') NOT NULL,
    description TEXT,
    decision ENUM('alerte','verification','blocage','ignore') DEFAULT 'alerte',
    date_detection DATETIME DEFAULT CURRENT_TIMESTAMP,
    traite_par INT NULL,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (traite_par) REFERENCES utilisateurs(id),
    INDEX idx_utilisateur (utilisateur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : api_configurations
-- =============================================
CREATE TABLE api_configurations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    service VARCHAR(100) NOT NULL,
    cle_api TEXT,
    secret_api TEXT,
    statut ENUM('actif','inactif','erreur') DEFAULT 'inactif',
    derniere_connexion DATETIME NULL,
    derniere_erreur TEXT,
    configuration JSON,
    INDEX idx_service (service)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : notifications_push
-- =============================================
CREATE TABLE notifications_push (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL,
    endpoint TEXT NOT NULL,
    auth_key VARCHAR(255) NOT NULL,
    p256dh_key VARCHAR(255) NOT NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    est_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    INDEX idx_utilisateur (utilisateur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : depenses
-- =============================================
CREATE TABLE depenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    categorie VARCHAR(100) NOT NULL,
    description TEXT,
    montant DECIMAL(10,2) NOT NULL,
    date_depense DATE NOT NULL,
    justificatif VARCHAR(255) NULL,
    cree_par INT NOT NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cree_par) REFERENCES utilisateurs(id),
    INDEX idx_date (date_depense),
    INDEX idx_categorie (categorie)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : modes_paiement (Personnalisation)
-- =============================================
CREATE TABLE modes_paiement (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    type ENUM('orange_money','moov_money','especes','wallet') NOT NULL,
    est_actif BOOLEAN DEFAULT TRUE,
    ordre_affichage INT DEFAULT 0,
    logo VARCHAR(255),
    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : roles
-- =============================================
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : permissions
-- =============================================
CREATE TABLE permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) UNIQUE NOT NULL,
    module VARCHAR(50) NOT NULL,
    description TEXT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : role_permissions
-- =============================================
CREATE TABLE role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLE : utilisateur_roles
-- =============================================
CREATE TABLE utilisateur_roles (
    utilisateur_id INT NOT NULL,
    role_id INT NOT NULL,
    PRIMARY KEY (utilisateur_id, role_id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- DONNÉES INITIALES
-- =============================================

-- Création du compte créateur (Super Admin)
INSERT INTO utilisateurs (nom, prenom, email, telephone, mot_de_passe_hash, role, statut, verifie, date_creation) 
VALUES ('Admin', 'DoriExpress', 'admin@doriexpress.bf', '61874528', 
        '$2y$10$VotreHashIci', -- mot de passe: admin123
        'createur', 'actif', 1, NOW());

-- Ajout du rôle créateur
INSERT INTO roles (nom, description) VALUES 
('createur', 'Super administrateur - tous les droits'),
('admin', 'Administrateur'),
('client', 'Client'),
('livreur', 'Livreur'),
('partenaire', 'Partenaire (restaurant/boutique)'),
('support', 'Support client');

-- Ajout des permissions de base
INSERT INTO permissions (nom, module, description) VALUES
('voir_dashboard', 'general', 'Voir le tableau de bord'),
('voir_utilisateurs', 'utilisateurs', 'Voir la liste des utilisateurs'),
('modifier_utilisateurs', 'utilisateurs', 'Modifier les utilisateurs'),
('voir_commandes', 'commandes', 'Voir les commandes'),
('modifier_commandes', 'commandes', 'Modifier les commandes'),
('voir_paiements', 'paiements', 'Voir les paiements'),
('modifier_paiements', 'paiements', 'Modifier les paiements'),
('voir_parametres', 'parametres', 'Voir les paramètres'),
('modifier_parametres', 'parametres', 'Modifier les paramètres'),
('voir_finance', 'finance', 'Voir les finances'),
('exporter_donnees', 'exports', 'Exporter les données'),
('gerer_modules', 'modules', 'Gérer les modules');

-- Wallet pour le créateur
INSERT INTO wallet (utilisateur_id, solde) VALUES (1, 0);

-- Catégories par défaut
INSERT INTO categories (nom, description) VALUES
('Livraison colis', 'Livraison de colis et paquets'),
('Livraison repas', 'Livraison de plats cuisinés'),
('Livraison courses', 'Livraison de courses'),
('Livraison express', 'Livraison rapide'),
('Dépôt colis', 'Dépôt et retrait de colis'),
('Services', 'Autres services');

-- Modes de paiement par défaut
INSERT INTO modes_paiement (nom, type, est_actif, ordre_affichage) VALUES
('Orange Money', 'orange_money', 1, 1),
('Moov Money', 'moov_money', 1, 2),
('Paiement espèces', 'especes', 1, 3),
('Portefeuille interne', 'wallet', 1, 4);

-- Modules par défaut
INSERT INTO modules (nom, version, description, statut) VALUES
('Paiement', '1.0', 'Gestion des paiements Orange Money et Moov Money', 'actif'),
('GPS', '1.0', 'Suivi GPS et cartographie', 'actif'),
('Chat', '1.0', 'Messagerie en temps réel', 'actif'),
('IA', '1.0', 'Intelligence artificielle (estimation, fraude, prévision)', 'actif'),
('Marketplace', '1.0', 'Gestion des partenaires, restaurants et boutiques', 'actif'),
('Wallet', '1.0', 'Portefeuille interne', 'actif'),
('Blog', '1.0', 'Blog et actualités', 'actif'),
('Promotions', '1.0', 'Gestion des promotions et codes promo', 'actif');

-- SEO par défaut
INSERT INTO seo_pages (page, titre, description, keywords) VALUES
('index', 'DoriExpress-Pro - Livraison rapide à Dori', 'Livraison Dori, livraison colis, livraison repas, courses à domicile', 'DoriExpress, livraison, Dori, Burkina Faso'),
('services', 'Nos services de livraison - DoriExpress-Pro', 'Livraison colis, repas, courses, express à Dori', 'services livraison, Dori, Burkina Faso'),
('commande', 'Commander une livraison - DoriExpress-Pro', 'Commander une livraison rapide à Dori', 'commande, livraison, Dori'),
('suivi', 'Suivi de livraison - DoriExpress-Pro', 'Suivez votre colis en temps réel', 'suivi livraison, colis, Dori');

-- =============================================
-- FIN DU FICHIER SQL
-- =============================================
-- Total : 30+ tables, relations complètes, données initiales
-- Le projet est prêt pour l'installation
-- =============================================