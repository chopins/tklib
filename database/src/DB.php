<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2018 chopin xiao (xiao@toknot.com)
 */

namespace Toknot\Database;

use PDO;

/**
 * @static
 */
class DB extends PDO {

    private static $ins = null;
    private static $last = '';
    private $dbname = '';
    private $tablePrefix = '';
    private $tableModelCacheFile = '';    
    private $modelCacheLoad = false;
    private $sessionQueryAutocommitChanged = false;
    public $tableLimit = 100;
    public static $forceFlushDatabaseCache = false;

    const NS = '\\';

    public function __construct($dsn, $modelpath, $username = null, $pw = null, $option = []) {
        parent::__construct($dsn, $username, $pw, $option);
        $this->tableModelCacheFile = $modelpath;
        $this->queryDBName();
        $this->flushDatabaseCache();
        self::$ins = $this;
    }

    public static function connect() {
        return self::$ins;
    }

    public function setTablePrefix($prefix) {
        $this->tablePrefix = $prefix;
    }

    public function setConnectAutocommit() {
        return $this->setAttribute(self::ATTR_AUTOCOMMIT, 1);
    }

    public function isConnectAutocommit() {
        return $this->getAttribute(self::ATTR_AUTOCOMMIT);
    }

    public function flushDatabaseCache() {
        if (self::$forceFlushDatabaseCache || !file_exists(self::$tableModelCacheFile)) {
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
     * @return \Toknot\Lib\Model\Database\TableModel
     */
    public function table($table) {
        $this->loadTableCacheFile();
        if ($this->tablePrefix && strpos($table, $this->tablePrefix) === 0) {
            $table = substr($table, strlen($this->tablePrefix));
        }

        $tableClass = $this->tableModelNamespace() . self::NS . $this->symbolConvert($table);
        if (class_exists($tableClass, false)) {
            return new $tableClass();
        }
        trigger_error("RuntimeException: table '$table' not exists at database '$this->dbname'", E_USER_ERROR);
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
        return 'Toknot' . self::NS . 'TableModel' . self::NS . $this->symbolConvert($this->dbname);
    }

    public function symbolConvert($name) {
        return str_replace('_', '', ucwords($name, '_'));
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

            $values = [];
            if ($col['DATA_TYPE'] == 'set' || $col['DATA_TYPE'] == 'enum') {
                $len = strlen($col['DATA_TYPE']);
                $values = explode(',', str_replace(self::NS, ' ', substr($col['COLUMN_TYPE'], $len + 1, -1)));
            }
            if ($col['CHARACTER_MAXIMUM_LENGTH'] !== null) {
                $len = $col['CHARACTER_MAXIMUM_LENGTH'];
            } elseif ($col['NUMERIC_PRECISION'] !== null) {
                $len = $col['NUMERIC_PRECISION'];
            } elseif ($col['DATETIME_PRECISION'] !== null) {
                $len = $col['DATETIME_PRECISION'];
            }
            $tableList[$tableName]['columnInfo'][$column] = [
                strtoupper($col['DATA_TYPE']),
                $len,
                $col['NUMERIC_SCALE'],
                $col['COLUMN_DEFAULT'], 
                $values,
                $col['COLUMN_COMMENT'],
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

        $class = '<?php ' . '/* Auto generate by toknot at Date:' . date('Y-m-d H:i:s') . ' */' . PHP_EOL;
       
        $class .= 'namespace ' . $this->tableModelNamespace() . ';';
        $class .= 'use ' . TableModel::class . ';' . PHP_EOL;
        foreach ($tableList as $tableName => $cols) {
            if ($cols['mul']) {
                $keys = $this->getTableIndex($tableName);
            } else {
                $keys = [];
            }

            if ($this->tablePrefix) {
                $tableName = substr($tableName, strlen($this->tablePrefix));
            }
            $this->generateTablePropertyComment($class, $cols['columnInfo']);
            $class .= 'class ' . $this->symbolConvert($tableName) . ' extends TableModel {'  . PHP_EOL;
            $sep = '`, `';
            $class .= $this->generateTableConst('SELECT_FEILD', '`' . join($sep, $cols['column']) . '`');
            $class .= $this->generateTableConst('COLUMN_LIST', $cols['column'], true);
            $class .= $this->generateTableConst('AUTO_INCREMENT', $cols['ai']);
            $class .= $this->generateTableConst('UNIQUE', $cols['uni'], true);
            if ($keys) {
                $class .= $this->generateTableConst('INDEX', array_merge($keys['mulIndex']), true);
                $class .= $this->generateTableConst('MUL_UNIQUE', $keys['mulUni'], true);
            }
            $class .= $this->generateTableConst('COLS', $cols['columnInfo'], true);
            $class .= $this->generateTableConst('NAME', $tableName);
            $class .= $this->generateTableConst('KEY_NAME', $cols['key']);
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

    protected function generateTableConst($const, $expression) {
        return 'CONST ' . strtoupper($const) . '=' . var_export($expression, true) . ';' . PHP_EOL;
    }

    protected function generateTablePropertyComment(&$class, $cols) {
        $class .= '/*' . PHP_EOL;
        foreach($cols as $n => $info) {
            $type = \strtolower($info[0]);
            $class .= " * @property {$type} \${$n} {$info[5]}" . PHP_EOL;
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
