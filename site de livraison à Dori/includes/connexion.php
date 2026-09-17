<?php
/**
 * =============================================
 * CONNEXION À LA BASE DE DONNÉES - DoriExpress-Pro
 * =============================================
 * Fichier : includes/connexion.php
 * Rôle : Gestion de la connexion PDO sécurisée
 * Utilisation : require_once 'includes/connexion.php';
 * =============================================
 */

// Éviter les inclusions multiples
if (!defined('CONNEXION_CHARGEE')) {
    define('CONNEXION_CHARGEE', true);

    /**
     * Classe Database - Gestion de la connexion PDO
     * Pattern Singleton pour une connexion unique
     */
    class Database {
        
        /**
         * @var Database|null Instance unique de la classe
         */
        private static $instance = null;
        
        /**
         * @var PDO|null Instance de la connexion PDO
         */
        private $pdo = null;
        
        /**
         * @var string Dernière requête exécutée (pour le débogage)
         */
        private $lastQuery = '';
        
        /**
         * @var array Statistiques des requêtes
         */
        private $queryStats = [
            'total' => 0,
            'select' => 0,
            'insert' => 0,
            'update' => 0,
            'delete' => 0,
            'errors' => 0
        ];
        
        /**
         * Constructeur privé (Singleton)
         * Établit la connexion à la base de données
         */
        private function __construct() {
            try {
                // Construction du DSN
                $dsn = 'mysql:host=' . DB_HOST . ';';
                $dsn .= 'port=' . DB_PORT . ';';
                $dsn .= 'dbname=' . DB_NAME . ';';
                $dsn .= 'charset=' . DB_CHARSET;
                
                // Options PDO
                $options = DB_OPTIONS;
                
                // Création de la connexion
                $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                
                // Journaliser la connexion réussie
                $this->log('Connexion à la base de données établie avec succès');
                
            } catch (PDOException $e) {
                // Journaliser l'erreur
                $this->log('Erreur de connexion : ' . $e->getMessage(), 'ERROR');
                
                // Message d'erreur selon l'environnement
                if (ENVIRONNEMENT === 'development') {
                    die('Erreur de connexion à la base de données : ' . $e->getMessage());
                } else {
                    die('Erreur de connexion à la base de données. Veuillez contacter l\'administrateur.');
                }
            }
        }
        
        /**
         * Récupère l'instance unique de la classe
         * @return Database Instance unique
         */
        public static function getInstance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        
        /**
         * Récupère la connexion PDO
         * @return PDO Objet PDO
         */
        public function getPDO() {
            return $this->pdo;
        }
        
        /**
         * Prépare une requête SQL
         * @param string $sql Requête SQL
         * @return PDOStatement Statement préparé
         */
        public function prepare($sql) {
            $this->lastQuery = $sql;
            return $this->pdo->prepare($sql);
        }
        
        /**
         * Exécute une requête SQL avec des paramètres
         * @param string $sql Requête SQL
         * @param array $params Paramètres à lier
         * @return PDOStatement|false Résultat de la requête
         */
        public function query($sql, $params = []) {
            $this->lastQuery = $sql;
            $this->queryStats['total']++;
            
            // Déterminer le type de requête pour les statistiques
            $sqlUpper = strtoupper(trim($sql));
            if (strpos($sqlUpper, 'SELECT') === 0) {
                $this->queryStats['select']++;
            } elseif (strpos($sqlUpper, 'INSERT') === 0) {
                $this->queryStats['insert']++;
            } elseif (strpos($sqlUpper, 'UPDATE') === 0) {
                $this->queryStats['update']++;
            } elseif (strpos($sqlUpper, 'DELETE') === 0) {
                $this->queryStats['delete']++;
            }
            
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                return $stmt;
            } catch (PDOException $e) {
                $this->queryStats['errors']++;
                $this->log('Erreur SQL : ' . $e->getMessage() . ' | Requête : ' . $sql, 'ERROR');
                
                if (ENVIRONNEMENT === 'development') {
                    throw new Exception('Erreur SQL : ' . $e->getMessage() . ' (Requête : ' . $sql . ')');
                }
                return false;
            }
        }
        
        /**
         * Exécute une requête et retourne un tableau de résultats
         * @param string $sql Requête SQL
         * @param array $params Paramètres à lier
         * @param int $fetchMode Mode de récupération (PDO::FETCH_ASSOC par défaut)
         * @return array Tableau de résultats
         */
        public function fetchAll($sql, $params = [], $fetchMode = PDO::FETCH_ASSOC) {
            $stmt = $this->query($sql, $params);
            if ($stmt) {
                return $stmt->fetchAll($fetchMode);
            }
            return [];
        }
        
        /**
         * Exécute une requête et retourne la première ligne
         * @param string $sql Requête SQL
         * @param array $params Paramètres à lier
         * @param int $fetchMode Mode de récupération
         * @return array|false Première ligne ou false
         */
        public function fetchOne($sql, $params = [], $fetchMode = PDO::FETCH_ASSOC) {
            $stmt = $this->query($sql, $params);
            if ($stmt) {
                return $stmt->fetch($fetchMode);
            }
            return false;
        }
        
        /**
         * Exécute une requête et retourne une seule valeur
         * @param string $sql Requête SQL
         * @param array $params Paramètres à lier
         * @return mixed Valeur unique
         */
        public function fetchValue($sql, $params = []) {
            $stmt = $this->query($sql, $params);
            if ($stmt) {
                return $stmt->fetchColumn();
            }
            return null;
        }
        
        /**
         * Récupère l'ID de la dernière insertion
         * @return string Dernier ID inséré
         */
        public function lastInsertId() {
            return $this->pdo->lastInsertId();
        }
        
        /**
         * Démarre une transaction
         * @return bool Succès de la transaction
         */
        public function beginTransaction() {
            $this->log('Début de transaction');
            return $this->pdo->beginTransaction();
        }
        
        /**
         * Valide une transaction
         * @return bool Succès de la validation
         */
        public function commit() {
            $this->log('Validation de transaction');
            return $this->pdo->commit();
        }
        
        /**
         * Annule une transaction
         * @return bool Succès de l'annulation
         */
        public function rollback() {
            $this->log('Annulation de transaction');
            return $this->pdo->rollback();
        }
        
        /**
         * Échappe une chaîne pour une utilisation sécurisée
         * @param string $string Chaîne à échapper
         * @return string Chaîne échappée
         */
        public function escape($string) {
            return $this->pdo->quote($string);
        }
        
        /**
         * Récupère les statistiques des requêtes
         * @return array Statistiques
         */
        public function getQueryStats() {
            return $this->queryStats;
        }
        
        /**
         * Récupère la dernière requête exécutée
         * @return string Dernière requête
         */
        public function getLastQuery() {
            return $this->lastQuery;
        }
        
        /**
         * Récupère le nombre de lignes affectées par la dernière requête
         * @return int Nombre de lignes
         */
        public function rowCount() {
            return $this->pdo->rowCount();
        }
        
        /**
         * Journalise un événement
         * @param string $message Message à journaliser
         * @param string $niveau Niveau (INFO, WARNING, ERROR)
         */
        private function log($message, $niveau = 'INFO') {
            // Si les logs sont activés
            if (defined('LOG_ACTIF') && LOG_ACTIF) {
                $logFile = DOSSIER_RACINE . 'storage/logs/database.log';
                $date = date('Y-m-d H:i:s');
                $logMessage = "[$date] [$niveau] $message\n";
                file_put_contents($logFile, $logMessage, FILE_APPEND);
            }
        }
    }
    
    /**
     * Fonctions utilitaires pour un accès rapide à la base de données
     */
    
    /**
     * Récupère l'instance de la base de données
     * @return Database Instance de Database
     */
    function db() {
        return Database::getInstance();
    }
    
    /**
     * Récupère le PDO pour les requêtes directes
     * @return PDO Objet PDO
     */
    function pdo() {
        return Database::getInstance()->getPDO();
    }
    
    /**
     * Exécute une requête SQL
     * @param string $sql Requête SQL
     * @param array $params Paramètres à lier
     * @return PDOStatement Résultat
     */
    function db_query($sql, $params = []) {
        return Database::getInstance()->query($sql, $params);
    }
    
    /**
     * Récupère toutes les lignes d'une requête
     * @param string $sql Requête SQL
     * @param array $params Paramètres à lier
     * @return array Résultats
     */
    function db_fetchAll($sql, $params = []) {
        return Database::getInstance()->fetchAll($sql, $params);
    }
    
    /**
     * Récupère la première ligne d'une requête
     * @param string $sql Requête SQL
     * @param array $params Paramètres à lier
     * @return array|false Résultat
     */
    function db_fetchOne($sql, $params = []) {
        return Database::getInstance()->fetchOne($sql, $params);
    }
    
    /**
     * Récupère une seule valeur
     * @param string $sql Requête SQL
     * @param array $params Paramètres à lier
     * @return mixed Valeur
     */
    function db_fetchValue($sql, $params = []) {
        return Database::getInstance()->fetchValue($sql, $params);
    }
    
    /**
     * Récupère le dernier ID inséré
     * @return string Dernier ID
     */
    function db_lastInsertId() {
        return Database::getInstance()->lastInsertId();
    }
    
    /**
     * Démarre une transaction
     */
    function db_beginTransaction() {
        return Database::getInstance()->beginTransaction();
    }
    
    /**
     * Valide une transaction
     */
    function db_commit() {
        return Database::getInstance()->commit();
    }
    
    /**
     * Annule une transaction
     */
    function db_rollback() {
        return Database::getInstance()->rollback();
    }
    
    /**
     * Vérifie si une table existe
     * @param string $table Nom de la table
     * @return bool Existe ou non
     */
    function db_tableExists($table) {
        $sql = "SHOW TABLES LIKE ?";
        $result = db_fetchOne($sql, [$table]);
        return $result !== false;
    }
    
    /**
     * Récupère la structure d'une table
     * @param string $table Nom de la table
     * @return array Structure de la table
     */
    function db_tableStructure($table) {
        $sql = "DESCRIBE ?";
        return db_fetchAll($sql, [$table]);
    }
    
    /**
     * Exécute plusieurs requêtes en une seule transaction
     * @param array $queries Tableau de requêtes avec leurs paramètres
     * @return bool Succès ou échec
     */
    function db_transaction($queries) {
        $db = Database::getInstance();
        try {
            $db->beginTransaction();
            foreach ($queries as $query) {
                if (is_array($query)) {
                    $db->query($query['sql'], $query['params']);
                } else {
                    $db->query($query);
                }
            }
            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollback();
            return false;
        }
    }
    
    /**
     * Importe un fichier SQL (pour les migrations/sauvegardes)
     * @param string $fichier Chemin du fichier SQL
     * @return bool Succès
     */
    function db_importSQL($fichier) {
        if (!file_exists($fichier)) {
            return false;
        }
        
        $sql = file_get_contents($fichier);
        $db = Database::getInstance();
        
        try {
            // Nettoyer les commentaires
            $sql = preg_replace('/^--.*$/m', '', $sql);
            $sql = preg_replace('/^#.*$/m', '', $sql);
            $sql = preg_replace('/^\/\*.*?\*\//s', '', $sql);
            
            // Séparer les requêtes
            $queries = preg_split('/;\s*$/m', $sql);
            
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    $db->query($query);
                }
            }
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Récupère la taille de la base de données
     * @return array Informations sur la taille
     */
    function db_size() {
        $sql = "SELECT 
                    table_schema AS 'database',
                    SUM(data_length + index_length) / 1024 / 1024 AS 'size_mb'
                FROM information_schema.tables 
                WHERE table_schema = ? 
                GROUP BY table_schema";
        
        $result = db_fetchOne($sql, [DB_NAME]);
        
        if ($result) {
            return [
                'name' => $result['database'],
                'size_mb' => round($result['size_mb'], 2)
            ];
        }
        
        return null;
    }
    
    /**
     * Récupère le nombre de tables dans la base
     * @return int Nombre de tables
     */
    function db_tableCount() {
        $sql = "SELECT COUNT(*) as total FROM information_schema.tables WHERE table_schema = ?";
        return (int) db_fetchValue($sql, [DB_NAME]);
    }
    
    // =============================================
    // INITIALISATION DE LA CONNEXION
    // =============================================
    
    /**
     * Variable globale $db pour un accès facile
     * Utilisation : global $db; puis $db->query(...)
     */
    global $db;
    $db = Database::getInstance();
    
    // Journaliser que la connexion est prête
    if (ENVIRONNEMENT === 'development') {
        $dbSize = db_size();
        if ($dbSize) {
            error_log('✅ Base de données connectée : ' . $dbSize['name'] . 
                     ' (' . $dbSize['size_mb'] . ' Mo, ' . db_tableCount() . ' tables)');
        }
    }
}

// =============================================
// FIN DU FICHIER CONNEXION.PHP
// =============================================
?>