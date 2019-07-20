<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 chopin xiao (xiao@toknot.com)
 */

namespace Toknot\Database;

use PDO;
use Toknot\Database\Exception\DBException;

/**
 * @static
 */
class DB extends PDO {

    private $dbname = '';
    private $tablePrefix = '';
    private $tableModelCacheFile = '';    
    private $modelCacheLoad = false;
    private $sessionQueryAutocommitChanged = false;
    private $serverId = '';
    private $lastSql = '';
    private static $lastServerId = '';
    private static $ins = null;
    public static $forceFlushDatabaseCache = false;
    public $tableLimit = 100;
    const NS = '\\';
    const DB_ATTR_TABLE_PREFIX = 'DB_TABLE_PREFIX';
    const DB_ATTR_SERVER_ID = 'DB_SERVER_ID';

    public function __construct($dsn, $cacheDir, $username = null, $pw = null, $option = []) {
        parent::__construct($dsn, $username, $pw, $option);
        $this->queryDBName();
        $this->setServerKey($option);
        $this->generateCacheFile($cacheDir);
        $this->setTablePrefix($option);
        $this->flushDatabaseCache();
        self::$ins[$this->serverId] = $this;
        self::$lastServerId = $this->serverId;
    }

    protected function setServerKey($option) {
        if(isset($option[self::DB_ATTR_SERVER_ID])) {
            $this->serverId = $option[self::DB_ATTR_SERVER_ID];
        } else {
            $this->serverId = md5($this->getAttribute(self::ATTR_CONNECTION_STATUS));
        }
    }

    protected function generateCacheFile($cacheDir) {
        $serverDir = $cacheDir . DIRECTORY_SEPARATOR . $this->serverId;
        if(!\file_exists($serverDir)) {
            mkdir($serverDir);
        }
        $this->tableModelCacheFile = $serverDir . DIRECTORY_SEPARATOR . $this->dbname . '.php';
    }

    public static function connect($serverId = '') {
        $id = $serverId ? $serverId : self::$lastServerId;
        $instance = self::$ins[$id];
        if(!$instance) {
            throw new DBException('db instance is null');
        }
        return $instance;
    }

    public static function getLastServerId() {
        return self::$lastServerId;
    }

    public function getServerId() {
        return $this->serverId;
    }

    public function getLastSql() {
        return $this->lastSql;
    }

    protected function setTablePrefix($option) {
        if(isset($option[self::DB_ATTR_TABLE_PREFIX])) {
            $this->tablePrefix = $option[self::DB_ATTR_TABLE_PREFIX];
        }
    }

    public function setConnectAutocommit() {
        return $this->setAttribute(self::ATTR_AUTOCOMMIT, 1);
    }

    public function isConnectAutocommit() {
        return $this->getAttribute(self::ATTR_AUTOCOMMIT);
    }

    public function flushDatabaseCache() {
        if (self::$forceFlushDatabaseCache || !file_exists($this->tableModelCacheFile)) {
            $this->generateTableModel();
        }
    }

    public function tablePrefix() {
        return $this->tablePrefix;
    }

    public function getDBName() {
        return $this->dbname;
    }

    public function loadTableCacheFile() {
        if (!$this->modelCacheLoad) {
            $this->modelCacheLoad = true;
            include $this->tableModelCacheFile;
        }
    }

    /**
     * 
     * @param string $table
     * @param mixed $condition
     * @return \Toknot\Lib\Model\Database\TableModel
     */
    public function table($table, $condition = '') {
        $this->loadTableCacheFile();
        if ($this->tablePrefix && strpos($table, $this->tablePrefix) === 0) {
            $table = substr($table, strlen($this->tablePrefix));
        }

        $tableClass = $this->tableModelNamespace() . self::NS . self::symbolConvert($table);
        if (class_exists($tableClass, false)) {
            return new $tableClass($this->serverId, $condition);
        }
        throw new DBException("table '$table' not exists at database '$this->dbname'");
    }

    public function tableNumber() {
        return $this->query("SELECT COUNT(*) AS cnt FROM `information_schema`.`TABLES` "
                        . "WHERE `TABLES`.`TABLE_SCHEMA`='{$this->dbname}'")->fetchColumn();
    }

    protected function getDBTablesInfo($offset = 0) {
        return $this->query("SELECT * FROM `information_schema`.`COLUMNS` "
                                . "WHERE `COLUMNS`.`TABLE_SCHEMA`='{$this->dbname}' LIMIT {$this->tableLimit} OFFSET $offset")
                        ->fetchAll(self::FETCH_ASSOC);
    }

    protected function getTableIndex($table) {
        $indexList = $this->query("SHOW INDEX FROM `{$this->dbname}`.`{$table}`");
        $mulList = ['mulIndex' => [], 'mulUni' => []];
        $keys = ['mulIndex' => [], 'mulUni' => []];
        foreach ($indexList as $index) {
            $keyName = $index['Key_name'];
            $column = $index['Column_name'];
            if ($keyName == 'PRIMARY') {
                continue;
            }
            $vName = $index['Non_unique'] ? 'mulIndex' : 'mulUni';

            if (isset($mulList[$vName][$keyName])) {
                $mulList[$vName][$keyName][] = $column;
                $keys[$vName][$keyName] = $mulList[$vName][$keyName];
            } else {
                $mulList[$vName][$keyName] = [$column];
                if ($vName == 'mulIndex') {
                    $keys[$vName][$keyName] = $mulList[$vName][$keyName];
                }
            }
        }

        return $keys;
    }

    public function tableModelNamespace() {
        return 'Toknot' . self::NS . 'TableModel' . self::NS . self::symbolConvert($this->dbname);
    }

    public static function symbolConvert($name) {
        return str_replace('_', '', ucwords($name, '_'));
    }

    /**
     * 
     * @return \PDOStatement
     */
    public function executeSelectOrUpdate(QueryBuild $queryBuild) {
        $sql = $queryBuild->getSQL();
        $sth = $this->prepare($sql);
        $bindParameter = $queryBuild->getParameterValue();
        foreach ($bindParameter as $i => $bind) {
            if (is_array($bind)) {
                $param = $bind[1];
                $isNum = $bind[0];
            } else {
                $param = $bind;
                $isNum = false;
            }
            if (is_numeric($i)) {
                $sth->bindValue($i + 1, $param, DB::PARAM_STR);
            } else {
                $res = $sth->bindValue($i, $param, $isNum ? DB::PARAM_INT : DB::PARAM_STR);
            }
        }
        $res = $sth->execute();
        $this->lastSql = $sql;
        if (!$res) {
            $errInfo = $sth->errorInfo();
            throw new DBException('Database query error', $errInfo[1], $errInfo[2], $sql, $bindParameter);
        }
        $queryBuild->cleanBindParameter();
        return $sth;
    }

    protected function tableModelString() {
        $offset = 0;
        $tableColumns = [];
        do {
            $tableCols = $this->getDBTablesInfo($offset);
            $tableColumns = array_merge($tableColumns, $tableCols);
            $offset += 100;
        } while ($tableCols);
        $tableList = [];
        foreach ($tableColumns as $col) {
            $tableName = $col['TABLE_NAME'];
            $column = $col['COLUMN_NAME'];
            if (empty($tableList[$tableName])) {
                $tableList[$tableName] = [];
                $tableList[$tableName]['column'] = [];
                $tableList[$tableName]['columnInfo'] = [];
                $tableList[$tableName]['key'] = '';
                $tableList[$tableName]['uni'] = [];
                $tableList[$tableName]['index'] = [];
                $tableList[$tableName]['ai'] = false;
                $tableList[$tableName]['mul'] = false;
            }

            $tableList[$tableName]['column'][] = $column;

            $enumValues = [];
            if ($col['DATA_TYPE'] == 'set' || $col['DATA_TYPE'] == 'enum') {
                $len = strlen($col['DATA_TYPE']);
                $enumValues = eval('return [' . substr($col['COLUMN_TYPE'], $len + 1, -1) . '];');
            } else {
                $enumValues = false;
            }
            if ($col['CHARACTER_MAXIMUM_LENGTH'] !== null) {
                $len = $col['CHARACTER_MAXIMUM_LENGTH'];
            } elseif ($col['NUMERIC_PRECISION'] !== null) {
                $len = $col['NUMERIC_PRECISION'];
            } elseif ($col['DATETIME_PRECISION'] !== null) {
                $len = $col['DATETIME_PRECISION'];
            }
            $defaultValue = false;
            if($col['COLUMN_DEFAULT'] == 'NULL') {
                $defaultValue = NULL;
            } elseif($col['COLUMN_DEFAULT'] == "''") {
                $defaultValue = '';
            }
            $tableList[$tableName]['columnInfo'][$column] = [
                'type' => strtoupper($col['DATA_TYPE']),
                'length' => (int)$len,
                'scale' => (int)$col['NUMERIC_SCALE'],
                'default' => $defaultValue, 
                'enum' => $enumValues,
                'comment' => $col['COLUMN_COMMENT'],
                'null' => $col['IS_NULLABLE'] == 'NO' ? false : true,
            ];

            if ($col['COLUMN_KEY'] == 'PRI') {
                $tableList[$tableName]['key'] = $column;
            } elseif ($col['COLUMN_KEY'] == 'UNI') {
                $tableList[$tableName]['uni'][] = $column;
            } elseif ($col['COLUMN_KEY'] == 'MUL') {
                $tableList[$tableName]['mul'] = true;
            }

            if ($col['EXTRA'] == 'auto_increment') {
                $tableList[$tableName]['ai'] = true;
            }
        }

        $class = '<?php /* Auto generate by toknot at Date:' . date('Y-m-d H:i:s T') . ' */' . PHP_EOL;
        $namespace = $this->tableModelNamespace();
        $class .= 'namespace ' . $namespace . ';';
        $class .= 'use ' . TableModel::class . ';' . PHP_EOL;
        foreach ($tableList as $tableName => $cols) {
            if ($cols['mul']) {
                $keys = $this->getTableIndex($tableName);
            } else {
                $keys = [];
            }
            $tableIdName = $tableName;
            if ($this->tablePrefix) {
                $tableIdClass = substr($tableIdName, strlen($this->tablePrefix));
            }
            $tableClassName = self::symbolConvert($tableIdClass);
            $tableFullClassName = "\\$namespace\\$tableClassName";
            $this->generateTablePropertyComment($class, $cols['columnInfo']);
            $class .= 'class ' . $tableClassName . ' extends TableModel {'  . PHP_EOL;
            $sep = '`, `';
            $class .= $this->generateTableConst('TABLE_SELECT_FEILD', '`' . join($sep, $cols['column']) . '`');
            $class .= $this->generateTableConst('TABLE_COLUMN_LIST', $cols['column']);
            $class .= $this->generateTableConst('TABLE_AUTO_INCREMENT', $cols['ai']);
            $class .= $this->generateTableConst('TABLE_UNIQUE', $cols['uni']);
            $class .= $this->generateTableConst('TABLE_CLASS_NAME', 'self::class', true);
            if ($keys) {
                $class .= $this->generateTableConst('TABLE_INDEX', array_merge($keys['mulIndex']));
                $class .= $this->generateTableConst('TABLE_MUL_UNIQUE', $keys['mulUni']);
            }
            $class .= $this->generateTableConst('TABLE_COLS', $cols['columnInfo']);
            $class .= $this->generateTableConst('TABLE_NAME', $tableName);
            $class .= $this->generateTableConst('TABLE_KEY_NAME', $cols['key']);
            $this->generateTableModelProperty($class, 'cas_ver_col', $cols['column'], '');
            $this->generateTableModelProperty($class, 'set_col_values', $cols['column'], []);
            $this->generateTableModelProperty($class, 'record_values', $cols['column'], []);
            $this->generateTableModelProperty($class, 'filter_values', $cols['column'], []);
            $this->generateTableModelProperty($class, 'alias_name', $cols['column'], '');
            $this->generateTableModelProperty($class, 'server_id', $cols['column'], '');
            $this->generateTableModelProperty($class, 'new_record', $cols['column'], true);
            $class .= '}'. PHP_EOL;
        }
        return $class;
    }

    public function generateTableModel() {
        $class = $this->tableModelString();
        file_put_contents($this->tableModelCacheFile, $class);
    }

    protected function queryDBName() {
        $this->dbname = $this->query('SELECT database()')->fetchColumn();
    }

    protected function generateTableConst($const, $expression, $isCode = false) {
        $expCode = $isCode ? $expression : var_export($expression, true);
        if(\is_array($expression)) {
            $expCode = \str_replace("    ", ' ', $expCode);
            $expCode = \str_replace("(\n", '(', $expCode);
            $expCode = \str_replace(",\n", ',', $expCode);
        }
        return 'CONST ' . strtoupper($const) . '=' . $expCode . ';' . PHP_EOL;
    }
   
    protected function generateTableModelProperty(&$class, $specProp, $cols, $def = '') {
        do {
            $name = '_' . md5(\microtime().\mt_rand(0,100000));
        }while(\in_array($name, $cols));
        
        $specProp = 'ATTR_' . \strtoupper($specProp);
        $class .= $this->generateTableConst($specProp, $name);
        $expCode = \var_export($def, true);
        if(\is_array($def)) {
            $expCode = \str_replace("    ", ' ', $expCode);
            $expCode = \str_replace("(\n", '(', $expCode);
            $expCode = \str_replace(",\n", ',', $expCode);
        }
        $class .= "public \${$name} = ". $expCode . ";".PHP_EOL;
    }
    protected function generateTablePropertyComment(&$class, $cols) {
        $class .= '/*' . PHP_EOL;
        foreach($cols as $n => $info) {
            $type = \strtolower($info['type']);
            $class .= " * @property {$type} \${$n} {$info['comment']}" . PHP_EOL;
        }
        $class .= '*/' . PHP_EOL;
    }

    public function transaction(callable $queryCallabe, callable $afterRollback) {
        $this->beginTransaction();
        try {
            $res = $queryCallabe();
            $this->commit();
            return $res;
        } catch (\Exception $e) {
            $this->rollBack();
            return $afterRollback($e);
        } catch (\Error $e) {
            $this->rollBack();
            return $afterRollback($e);
        }
    }

    public function beginWork() {
        return $this->query('EGIN WORK');
    }

    public function commitWork() {
        return $this->query('COMMIT WORK');
    }

    public function setAutocommit() {
        if (!$this->isConnectAutocommit()) {
            $this->sessionQueryAutocommitChanged = true;
            return $this->query("SET autocommit = 1");
        }
        return true;
    }

    public function unAutocommit() {
        if ($this->isConnectAutocommit()) {
            $this->sessionQueryCommit = true;
            return $this->query("SET autocommit = 0");
        }
        return true;
    }

    public function commit() {
        parent::commit();
        if ($this->isConnectAutocommit()) {
            $status = $this->query('select @@session.autocommit')->fetchColumn(0);
            $newStatus = intval(!$status);
            $this->sessionQueryAutocommitChanged = false;
            if ($newStatus == $this->isConnectAutocommit()) {
                return $this->query("SET autocommit = $newStatus");
            }
        }
    }

}
