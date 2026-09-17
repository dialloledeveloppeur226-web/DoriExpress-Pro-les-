<?php
/**
 * =============================================
 * CONNEXION PREMIUM ULTIME - DoriExpress-Pro
 * =============================================
 * Fichier : database/connexion.php
 * Rôle : Connexion PDO de niveau professionnel (version premium)
 * Utilisation : require_once 'database/connexion.php';
 * Niveau : Uber / Glovo / Deliveroo
 * =============================================
 */

// Définir les constantes si elles ne sont pas déjà définies
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_PORT', '3306');
    define('DB_NAME', 'dori_express');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_CHARSET', 'utf8mb4');
}

// Options PDO par défaut
if (!defined('DB_OPTIONS')) {
    define('DB_OPTIONS', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        PDO::MYSQL_ATTR_FOUND_ROWS => true
    ]);
}

// Constante pour le logging
if (!defined('LOG_DATABASE')) {
    define('LOG_DATABASE', true);
}

/**
 * =============================================
 * CLASSE DATABASE PREMIUM ULTIME
 * =============================================
 */
class DatabasePremium {
    
    /**
     * @var DatabasePremium|null Instance unique (Singleton)
     */
    private static $instance = null;
    
    /**
     * @var PDO|null Instance PDO
     */
    private $pdo = null;
    
    /**
     * @var string Dernière requête exécutée
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
        'other' => 0,
        'errors' => 0,
        'slow_queries' => 0,
        'total_time' => 0,
        'average_time' => 0
    ];
    
    /**
     * @var array Profiling des requêtes
     */
    private $queryProfiles = [];
    
    /**
     * @var bool Transaction en cours
     */
    private $inTransaction = false;
    
    /**
     * @var bool Mode debug
     */
    private $debug = false;
    
    /**
     * @var float Temps de début de la dernière requête
     */
    private $queryStartTime = 0;
    
    /**
     * @var string Nom du fichier de log
     */
    private $logFile = '';
    
    /**
     * @var array Cache des requêtes
     */
    private $queryCache = [];
    
    /**
     * @var int Nombre de requêtes en cache
     */
    private $cacheHits = 0;
    
    /**
     * @var int Nombre de requêtes en cache miss
     */
    private $cacheMiss = 0;
    
    /**
     * Constructeur privé (Singleton)
     */
    private function __construct() {
        // Initialiser le fichier de log
        $this->logFile = dirname(__DIR__) . '/storage/logs/database_premium.log';
        $this->ensureLogDirectory();
        
        // Établir la connexion
        $this->connect();
    }
    
    /**
     * Établit la connexion à la base de données
     */
    private function connect() {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';';
            $dsn .= 'port=' . DB_PORT . ';';
            $dsn .= 'dbname=' . DB_NAME . ';';
            $dsn .= 'charset=' . DB_CHARSET;
            
            $startTime = microtime(true);
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, DB_OPTIONS);
            $connectTime = microtime(true) - $startTime;
            
            $this->log('Connexion établie en ' . round($connectTime * 1000, 2) . 'ms');
            
            // Journaliser les informations de la base
            $this->logDatabaseInfo();
            
        } catch (PDOException $e) {
            $this->log('ERREUR DE CONNEXION : ' . $e->getMessage(), 'ERROR');
            
            if (defined('ENVIRONNEMENT') && ENVIRONNEMENT === 'development') {
                throw new Exception('Erreur de connexion : ' . $e->getMessage());
            } else {
                throw new Exception('Erreur de connexion à la base de données');
            }
        }
    }
    
    /**
     * Récupère l'instance unique
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Récupère la connexion PDO
     */
    public function getPDO() {
        return $this->pdo;
    }
    
    /**
     * Active/désactive le mode debug
     */
    public function setDebug($enabled) {
        $this->debug = $enabled;
    }
    
    /**
     * Active/désactive le cache des requêtes
     */
    public function setCache($enabled) {
        $this->cacheEnabled = $enabled;
        if (!$enabled) {
            $this->queryCache = [];
        }
    }
    
    // =============================================
    // EXÉCUTION DES REQUÊTES AVEC PROFILING
    // =============================================
    
    /**
     * Prépare une requête SQL
     */
    public function prepare($sql) {
        $this->lastQuery = $sql;
        $this->queryStartTime = microtime(true);
        return $this->pdo->prepare($sql);
    }
    
    /**
     * Exécute une requête avec profiling et cache
     */
    public function query($sql, $params = [], $useCache = false) {
        $this->lastQuery = $sql;
        $this->queryStats['total']++;
        $this->queryStartTime = microtime(true);
        
        // Déterminer le type de requête
        $sqlUpper = strtoupper(trim($sql));
        $queryType = 'other';
        if (strpos($sqlUpper, 'SELECT') === 0) {
            $queryType = 'select';
            $this->queryStats['select']++;
        } elseif (strpos($sqlUpper, 'INSERT') === 0) {
            $queryType = 'insert';
            $this->queryStats['insert']++;
        } elseif (strpos($sqlUpper, 'UPDATE') === 0) {
            $queryType = 'update';
            $this->queryStats['update']++;
        } elseif (strpos($sqlUpper, 'DELETE') === 0) {
            $queryType = 'delete';
            $this->queryStats['delete']++;
        } else {
            $this->queryStats['other']++;
        }
        
        // Vérifier le cache
        if ($useCache && $queryType === 'select') {
            $cacheKey = $this->getCacheKey($sql, $params);
            if (isset($this->queryCache[$cacheKey])) {
                $this->cacheHits++;
                $this->log('Cache HIT : ' . $sql, 'DEBUG');
                return $this->queryCache[$cacheKey];
            }
            $this->cacheMiss++;
        }
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            // Profiling
            $executionTime = microtime(true) - $this->queryStartTime;
            $this->queryStats['total_time'] += $executionTime;
            $this->queryStats['average_time'] = $this->queryStats['total_time'] / $this->queryStats['total'];
            
            // Détecter les requêtes lentes (> 1s)
            if ($executionTime > 1.0) {
                $this->queryStats['slow_queries']++;
                $this->log('SLOW QUERY (' . round($executionTime * 1000, 2) . 'ms) : ' . $sql, 'WARNING');
            }
            
            // Enregistrer le profil
            $this->queryProfiles[] = [
                'sql' => $sql,
                'params' => $params,
                'time' => $executionTime,
                'type' => $queryType,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            // Garder seulement les 100 dernières requêtes
            if (count($this->queryProfiles) > 100) {
                array_shift($this->queryProfiles);
            }
            
            // Mettre en cache si c'est une SELECT
            if ($useCache && $queryType === 'select') {
                $cacheKey = $this->getCacheKey($sql, $params);
                $this->queryCache[$cacheKey] = $stmt;
            }
            
            $this->log('QUERY : ' . $sql . ' (' . round($executionTime * 1000, 2) . 'ms)', 'DEBUG');
            
            return $stmt;
            
        } catch (PDOException $e) {
            $this->queryStats['errors']++;
            $this->log('ERREUR SQL : ' . $e->getMessage() . ' | Requête : ' . $sql, 'ERROR');
            
            if ($this->debug) {
                throw new Exception('Erreur SQL : ' . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Récupère toutes les lignes
     */
    public function fetchAll($sql, $params = [], $fetchMode = PDO::FETCH_ASSOC, $useCache = false) {
        $stmt = $this->query($sql, $params, $useCache);
        if ($stmt) {
            return $stmt->fetchAll($fetchMode);
        }
        return [];
    }
    
    /**
     * Récupère la première ligne
     */
    public function fetchOne($sql, $params = [], $fetchMode = PDO::FETCH_ASSOC, $useCache = false) {
        $stmt = $this->query($sql, $params, $useCache);
        if ($stmt) {
            return $stmt->fetch($fetchMode);
        }
        return false;
    }
    
    /**
     * Récupère une seule valeur
     */
    public function fetchValue($sql, $params = [], $useCache = false) {
        $stmt = $this->query($sql, $params, $useCache);
        if ($stmt) {
            return $stmt->fetchColumn();
        }
        return null;
    }
    
    /**
     * Récupère toutes les valeurs d'une colonne
     */
    public function fetchColumn($sql, $params = [], $column = 0, $useCache = false) {
        $stmt = $this->query($sql, $params, $useCache);
        if ($stmt) {
            return $stmt->fetchAll(PDO::FETCH_COLUMN, $column);
        }
        return [];
    }
    
    /**
     * Récupère un tableau clé-valeur
     */
    public function fetchPairs($sql, $params = [], $useCache = false) {
        $stmt = $this->query($sql, $params, $useCache);
        if ($stmt) {
            return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        }
        return [];
    }
    
    /**
     * Récupère le dernier ID inséré
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Récupère le nombre de lignes affectées
     */
    public function rowCount() {
        return $this->pdo->rowCount();
    }
    
    // =============================================
    // TRANSACTIONS AVANCÉES
    // =============================================
    
    /**
     * Démarre une transaction
     */
    public function beginTransaction() {
        if (!$this->inTransaction) {
            $this->inTransaction = true;
            $this->log('Début de transaction');
            return $this->pdo->beginTransaction();
        }
        return false;
    }
    
    /**
     * Valide une transaction
     */
    public function commit() {
        if ($this->inTransaction) {
            $this->inTransaction = false;
            $this->log('Validation de transaction');
            return $this->pdo->commit();
        }
        return false;
    }
    
    /**
     * Annule une transaction
     */
    public function rollback() {
        if ($this->inTransaction) {
            $this->inTransaction = false;
            $this->log('Annulation de transaction');
            return $this->pdo->rollback();
        }
        return false;
    }
    
    /**
     * Exécute un callback dans une transaction
     */
    public function transaction($callback) {
        try {
            $this->beginTransaction();
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            $this->log('TRANSACTION ÉCHOUÉE : ' . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    // =============================================
    // CACHE DES REQUÊTES
    // =============================================
    
    private function getCacheKey($sql, $params) {
        return md5($sql . json_encode($params));
    }
    
    public function clearCache() {
        $this->queryCache = [];
        $this->cacheHits = 0;
        $this->cacheMiss = 0;
        $this->log('Cache vidé');
    }
    
    // =============================================
    // UTILITAIRES
    // =============================================
    
    /**
     * Échappe une chaîne
     */
    public function escape($string) {
        return $this->pdo->quote($string);
    }
    
    /**
     * Récupère les statistiques des requêtes
     */
    public function getQueryStats() {
        return $this->queryStats;
    }
    
    /**
     * Récupère les profils des requêtes
     */
    public function getQueryProfiles($limit = 50) {
        return array_slice($this->queryProfiles, -$limit);
    }
    
    /**
     * Récupère la dernière requête
     */
    public function getLastQuery() {
        return $this->lastQuery;
    }
    
    /**
     * Récupère les statistiques du cache
     */
    public function getCacheStats() {
        return [
            'size' => count($this->queryCache),
            'hits' => $this->cacheHits,
            'miss' => $this->cacheMiss,
            'hit_rate' => ($this->cacheHits + $this->cacheMiss) > 0 
                ? round($this->cacheHits / ($this->cacheHits + $this->cacheMiss) * 100, 2) 
                : 0
        ];
    }
    
    // =============================================
    // GESTION DE LA BASE DE DONNÉES
    // =============================================
    
    /**
     * Vérifie si la base existe
     */
    public function databaseExists() {
        try {
            $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT, DB_USER, DB_PASS);
            $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            $this->log('Erreur vérification base : ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Crée la base de données
     */
    public function createDatabase() {
        try {
            $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT, DB_USER, DB_PASS);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET " . DB_CHARSET . " COLLATE " . DB_CHARSET . "_unicode_ci");
            $this->log('Base de données créée : ' . DB_NAME);
            return true;
        } catch (PDOException $e) {
            $this->log('Erreur création base : ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Supprime la base de données (ATTENTION)
     */
    public function dropDatabase() {
        try {
            $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT, DB_USER, DB_PASS);
            $pdo->exec("DROP DATABASE IF EXISTS " . DB_NAME);
            $this->log('Base de données supprimée : ' . DB_NAME, 'WARNING');
            return true;
        } catch (PDOException $e) {
            $this->log('Erreur suppression base : ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Importe un fichier SQL
     */
    public function importSQL($fichier) {
        if (!file_exists($fichier)) {
            $this->log('Fichier SQL introuvable : ' . $fichier, 'ERROR');
            return ['success' => false, 'message' => 'Fichier introuvable'];
        }
        
        $sql = file_get_contents($fichier);
        $pdo = $this->pdo;
        
        try {
            // Nettoyer les commentaires
            $sql = preg_replace('/^--.*$/m', '', $sql);
            $sql = preg_replace('/^#.*$/m', '', $sql);
            $sql = preg_replace('/^\/\*.*?\*\//s', '', $sql);
            $sql = preg_replace('/^\/\/.*$/m', '', $sql);
            
            // Séparer les requêtes
            $queries = preg_split('/;\s*$/m', $sql);
            
            $compteur = 0;
            $errors = [];
            
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    try {
                        $pdo->exec($query);
                        $compteur++;
                    } catch (PDOException $e) {
                        $errors[] = $e->getMessage();
                        $this->log('Erreur import requête : ' . $e->getMessage(), 'ERROR');
                    }
                }
            }
            
            $this->log('Import SQL terminé : ' . $compteur . ' requêtes exécutées');
            
            return [
                'success' => true,
                'count' => $compteur,
                'errors' => $errors
            ];
            
        } catch (PDOException $e) {
            $this->log('Erreur import SQL : ' . $e->getMessage(), 'ERROR');
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Exporte la base de données
     */
    public function exportSQL($fichier = null) {
        $fichier = $fichier ?: dirname(__DIR__) . '/storage/backups/backup_' . date('Y-m-d_H-i-s') . '.sql';
        
        try {
            // Récupérer toutes les tables
            $tables = $this->fetchColumn("SHOW TABLES");
            
            $output = "-- =============================================\n";
            $output .= "-- BACKUP DoriExpress-Pro\n";
            $output .= "-- Date : " . date('Y-m-d H:i:s') . "\n";
            $output .= "-- Base : " . DB_NAME . "\n";
            $output .= "-- =============================================\n\n";
            
            $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
            
            foreach ($tables as $table) {
                // Structure
                $result = $this->query("SHOW CREATE TABLE " . $table);
                $row = $result->fetch(PDO::FETCH_NUM);
                $output .= $row[1] . ";\n\n";
                
                // Données
                $rows = $this->fetchAll("SELECT * FROM " . $table);
                if (!empty($rows)) {
                    $output .= "INSERT INTO `" . $table . "` VALUES\n";
                    $values = [];
                    foreach ($rows as $row) {
                        $escaped = array_map(function($value) {
                            return $value === null ? 'NULL' : "'" . addslashes($value) . "'";
                        }, $row);
                        $values[] = "(" . implode(", ", $escaped) . ")";
                    }
                    $output .= implode(",\n", $values) . ";\n\n";
                }
            }
            
            $output .= "SET FOREIGN_KEY_CHECKS=1;\n";
            
            // Assurer que le dossier existe
            $dir = dirname($fichier);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            file_put_contents($fichier, $output);
            $this->log('Export SQL terminé : ' . $fichier);
            
            return [
                'success' => true,
                'file' => $fichier,
                'size' => filesize($fichier),
                'tables' => count($tables)
            ];
            
        } catch (Exception $e) {
            $this->log('Erreur export SQL : ' . $e->getMessage(), 'ERROR');
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Récupère les tables de la base
     */
    public function getTables() {
        return $this->fetchColumn("SHOW TABLES");
    }
    
    /**
     * Récupère les informations d'une table
     */
    public function getTableInfo($table) {
        return $this->fetchAll("DESCRIBE " . $table);
    }
    
    /**
     * Récupère le nombre de lignes d'une table
     */
    public function getTableCount($table) {
        return (int) $this->fetchValue("SELECT COUNT(*) FROM " . $table);
    }
    
    /**
     * Vide une table
     */
    public function truncateTable($table) {
        $this->query("TRUNCATE TABLE " . $table);
        $this->log('Table vidée : ' . $table);
        return true;
    }
    
    /**
     * Récupère la taille de la base
     */
    public function getDatabaseSize() {
        $result = $this->fetchOne(
            "SELECT 
                table_schema AS 'database',
                SUM(data_length + index_length) / 1024 / 1024 AS 'size_mb',
                COUNT(*) AS 'tables'
            FROM information_schema.tables 
            WHERE table_schema = ? 
            GROUP BY table_schema",
            [DB_NAME]
        );
        return $result ? [
            'name' => $result['database'],
            'size_mb' => round($result['size_mb'], 2),
            'tables' => $result['tables']
        ] : null;
    }
    
    // =============================================
    // LOGGING AVANCÉ
    // =============================================
    
    private function ensureLogDirectory() {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    private function log($message, $niveau = 'INFO') {
        if (!LOG_DATABASE) {
            return;
        }
        
        $date = date('Y-m-d H:i:s');
        $pid = getmypid();
        $logMessage = "[$date] [$niveau] [PID:$pid] $message\n";
        
        // Limiter la taille du fichier de log (10 Mo)
        if (file_exists($this->logFile) && filesize($this->logFile) > 10 * 1024 * 1024) {
            $backup = $this->logFile . '.old';
            rename($this->logFile, $backup);
        }
        
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }
    
    private function logDatabaseInfo() {
        try {
            $version = $this->fetchValue("SELECT VERSION()");
            $this->log('Version MySQL : ' . $version);
        } catch (Exception $e) {
            // Ignorer
        }
    }
    
    // =============================================
    // MÉTHODES DE CONVÉNIENCE
    // =============================================
    
    /**
     * Vérifie si une table existe
     */
    public function tableExists($table) {
        $result = $this->fetchOne("SHOW TABLES LIKE ?", [$table]);
        return $result !== false;
    }
    
    /**
     * Vérifie si une colonne existe
     */
    public function columnExists($table, $column) {
        $result = $this->fetchOne("SHOW COLUMNS FROM " . $table . " WHERE Field = ?", [$column]);
        return $result !== false;
    }
    
    /**
     * Ajoute une colonne si elle n'existe pas
     */
    public function addColumnIfNotExists($table, $column, $definition) {
        if (!$this->columnExists($table, $column)) {
            $this->query("ALTER TABLE " . $table . " ADD COLUMN " . $column . " " . $definition);
            $this->log('Colonne ajoutée : ' . $table . '.' . $column);
            return true;
        }
        return false;
    }
    
    /**
     * Récupère la dernière erreur
     */
    public function getLastError() {
        return $this->pdo->errorInfo();
    }
    
    /**
     * Vérifie la connexion
     */
    public function isConnected() {
        try {
            $this->pdo->query("SELECT 1");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Ferme la connexion
     */
    public function close() {
        $this->pdo = null;
        self::$instance = null;
        $this->log('Connexion fermée');
    }
}

// =============================================
// FONCTIONS D'ACCÈS RAPIDE (VERSION PREMIUM)
// =============================================

/**
 * Récupère l'instance de la base de données
 */
function db_premium() {
    return DatabasePremium::getInstance();
}

/**
 * Exécute une requête SQL
 */
function dbp_query($sql, $params = []) {
    return DatabasePremium::getInstance()->query($sql, $params);
}

/**
 * Récupère toutes les lignes
 */
function dbp_fetchAll($sql, $params = [], $fetchMode = PDO::FETCH_ASSOC, $useCache = false) {
    return DatabasePremium::getInstance()->fetchAll($sql, $params, $fetchMode, $useCache);
}

/**
 * Récupère la première ligne
 */
function dbp_fetchOne($sql, $params = [], $fetchMode = PDO::FETCH_ASSOC, $useCache = false) {
    return DatabasePremium::getInstance()->fetchOne($sql, $params, $fetchMode, $useCache);
}

/**
 * Récupère une seule valeur
 */
function dbp_fetchValue($sql, $params = [], $useCache = false) {
    return DatabasePremium::getInstance()->fetchValue($sql, $params, $useCache);
}

/**
 * Récupère le dernier ID inséré
 */
function dbp_lastInsertId() {
    return DatabasePremium::getInstance()->lastInsertId();
}

/**
 * Démarre une transaction
 */
function dbp_beginTransaction() {
    return DatabasePremium::getInstance()->beginTransaction();
}

/**
 * Valide une transaction
 */
function dbp_commit() {
    return DatabasePremium::getInstance()->commit();
}

/**
 * Annule une transaction
 */
function dbp_rollback() {
    return DatabasePremium::getInstance()->rollback();
}

/**
 * Exécute un callback dans une transaction
 */
function dbp_transaction($callback) {
    return DatabasePremium::getInstance()->transaction($callback);
}

/**
 * Vérifie si la base existe
 */
function dbp_exists() {
    return DatabasePremium::getInstance()->databaseExists();
}

/**
 * Crée la base de données
 */
function dbp_create() {
    return DatabasePremium::getInstance()->createDatabase();
}

/**
 * Importe un fichier SQL
 */
function dbp_import($fichier) {
    return DatabasePremium::getInstance()->importSQL($fichier);
}

/**
 * Exporte la base de données
 */
function dbp_export($fichier = null) {
    return DatabasePremium::getInstance()->exportSQL($fichier);
}

/**
 * Récupère les statistiques des requêtes
 */
function dbp_stats() {
    return DatabasePremium::getInstance()->getQueryStats();
}

/**
 * Récupère les profils des requêtes
 */
function dbp_profiles($limit = 50) {
    return DatabasePremium::getInstance()->getQueryProfiles($limit);
}

// =============================================
// INITIALISATION AUTOMATIQUE
// =============================================

/**
 * Variable globale pour un accès facile
 */
global $dbp;
$dbp = DatabasePremium::getInstance();

// Journaliser le démarrage
if (defined('ENVIRONNEMENT') && ENVIRONNEMENT === 'development') {
    $dbSize = $dbp->getDatabaseSize();
    if ($dbSize) {
        $dbp->log('✅ Base : ' . $dbSize['name'] . ' (' . $dbSize['size_mb'] . ' Mo, ' . $dbSize['tables'] . ' tables)');
    }
}

// =============================================
// FIN DU FICHIER DATABASE/CONNEXION.PHP (PREMIUM ULTIME)
// =============================================
?>