<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2018 chopin xiao (xiao@toknot.com)
 */

namespace Toknot\Database;

use Toknot\Database\DB;
use Toknot\Database\QueryBuild;

abstract class TableModel {
    public static $tableAlias = '';

    public function __construct($condition = '') {
        $this->one($condition);
    }

    protected function getCasVerColAttr() {
        $pro = self::ATTR_CAS_VER_COL;
        return $this->$pro;
    }

    protected function setCasVerColAttr($feilds) {
        $pro = self::ATTR_CAS_VER_COL;
        $this->$pro = $feilds;
    }

    protected function setRecordValues($key, $value = null) {
        $pro = self::ATTR_RECORD_VALUES;
        if(\is_array($key)) {
            $this->$pro = $key;
            return;
        }
        $this->$pro[$key] = $value;
    }

    protected function getRecordValues($key = null) {
        $pro = self::ATTR_RECORD_VALUES;
        if(!$key) {
            return $this->$pro;
        }
        return $this->$pro[$key];
    }

    protected function setFilterValues($key, $value = null) {
        $pro = self::ATTR_SET_COL_VALUES;
        if(\is_array($key)) {
            $this->$pro = $key;
            return;
        }
        $this->$pro[$key] = $value;
    }

    protected function getFilterValues($key = null) {
        $pro = self::ATTR_SET_COL_VALUES;
        if(!$key) {
            return $this->$pro;
        }
        return $this->$pro[$key];
    }

    public function __set($name, $value = '') {
        if(\in_array(self::TABLE_COLUMN_LIST, $name)) {
            throw new \PDOException("column '$name' not exists in " . self::TABLE_NAME);
        }
        if(\is_scalar($value)) {
            $this->setRecordValues($name, $value);
        }
        $this->setFilterValues($name, $value);
    }

    public function __get($name) {
        if(\in_array(self::TABLE_COLUMN_LIST, $name)) {
            throw new \PDOException("column '$name' not exists in " . self::TABLE_NAME);
        }
        return $this->getRecordValues($name);
    }

    /**
     * 
     * @return \Toknot\Database\DB
     */
    public function db() {
        return DB::connect();
    }
    public function idValue() {
        $pkn = self::TABLE_KEY_NAME;
        return $this->$pkn;
    }

    /**
     * 
     * @return int
     */
    public function lastInsertId() {
        return $this->db()->lastInsertId();
    }

    /**
     * 
     * @return \PDOStatement
     */
    public function executeSelectOrUpdate(QueryBuild $queryBuild) {
        $sql = $queryBuild->getSQL();
        $sth = $this->db()->prepare($sql);
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
        if (!$res) {
            $errInfo = $sth->errorInfo();
            $errData = [$errInfo[1], "SQLSTATE[$errInfo[0]] $errInfo[2]", $sql, $bindParameter];
            throw new \PDOException("SQLSTATE[$errInfo[0]] $errInfo[2]:(SQL: $sql);PARAMS:" . \var_export($bindParameter));
        }
        $queryBuild->cleanBindParameter();
        return $sth;
    }

    

    /**
     * 
     * @param string $name
     * @return int
     */
    public function lastId($name = '') {
        return $this->db()->lastInsertId($name);
    }

    /**
     * 
     * @return \Toknot\Database\QueryBuild
     */
    public function query() {
        return new QueryBuild($this);
    }

    /**
     * 
     * @return QueryBuild
     */
    public function newQuery() {
        $query = new QueryBuild($this);
        $query->cleanBindParameter();
        return $query;
    }

    public function endQuery() {
        QueryBuild::clearAllBindParameter();
    }

    /**
     * 
     * @return string
     */
    public function tableName() {
        return self::TABLE_NAME;
    }

    public function quote($string) {
        $this->db()->quote($string);
    }

    /**
     * 
     * @return string
     */
    public function getAlias() {
        return self::$tableAlias;
    }

    /**
     * 
     * @param string $alias
     */
    public function setAlias($alias = '') {
        self::$tableAlias = $alias ? $alias : self::TABLE_NAME;
    }

    /**
     * find one row
     * 
     * @param mixed $id
     * @return array
     */
    public function one($id) {
        $query = $this->query();
        if (is_array($id)) {
            $and = $query->onAnd();
            foreach ($id as $col => $v) {
                $and->arg($query->col($col)->eq($v));
            }

            $list = $query->where($and)->limit(1)->row();
        } else {
            $list = $query->findOne($id);
        }
        $this->setRecordValues($list);
        return $this;
    }

    public function isNewRecord() {
        return empty($this->getRecordValues());
    }

    public function save() {
        if($this->isNewRecord()) {
            $this->insert($this->getRecordValues());
        } else {
            $filter = $this->getFilterValues();
            $updateData = [];
            $where = [];
            foreach ($filter as $key => $value) {
                if(\is_scalar($value)) {
                    $updateData[$key] = $value;
                } else {
                    $where[$key] = $value;
                }
            }
            $this->updateById($updateData, $this->idValue(), $where);
        }
    }

    /**
     * count all row number
     * 
     * @param mix $where
     * @return int
     */
    public function count($where = null) {
        $query = $this->query();
        $query->where($where);
        return $query->count();
    }

    /**
     * find all row
     * 
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function findAll($limit, $offset = 0) {
        $query = $this->query();
        return $query->range($offset, $limit)->all();
    }

    /**
     * find row by greater than id
     * 
     * @param int $id
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function findGTId($id, $limit, $offset = 0) {
        $query = $this->query();
        $exp = $query->col(self::TABLE_KEY_NAME)->gt($id);
        return $query->where($exp)->range($offset, $limit)->all();
    }

    /**
     * find row by less than id
     * 
     * @param int $id
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function findLTId($id, $limit, $offset = 0) {
        $query = $this->query();
        $exp = $query->col(self::TABLE_KEY_NAME)->lt($id);
        return $query->where($exp)->range($offset, $limit)->all();
    }

    /**
     * update by id when cas version not change
     * 
     * @param array $param
     * @param int $id
     * @param int $casValue
     * @param int $newValue
     * @return int
     */
    public function casUpdateById(array $param, $id, $casValue, $newValue) {
        $query = $this->query();
        $exp1 = $query->col(self::TABLE_KEY_NAME)->eq($id);
        $exp2 = $query->col(self::$casVerCol)->eq($casValue);
        $exp = $query->onAnd($exp1, $exp2);
        $param[self::$casVerCol] = $newValue;
        return $query->where($exp)->range(0, 1)->update($param);
    }

    /**
     * update by id
     * 
     * @param array $param
     * @param int $id
     * @param int $where
     * @return int
     */
    public function updateById(array $param, $id, $where = '') {
        $query = $this->query();
        $exp1 = $query->col(self::TABLE_KEY_NAME)->eq($id);
        $filter = $exp1;
        if ($where) {
            $and = $query->onAnd();
            $and->arg($exp1);
            $and->arg($where);
            $filter = $and;
        }
        return $query->where($filter)->range(0, 1)->update($param);
    }

    /**
     * delete row by id
     * 
     * @param int $id
     * @return int
     */
    public function deleteById($id) {
        $query = $this->query();
        $exp = $query->key()->eq($id);
        return $query->where($exp)->range(0, 1)->delete();
    }

    /**
     * update a feild value by id
     * 
     * @param int $id
     * @param string $setCol
     * @param mixed $set
     */
    public function setById($id, $setCol, $set) {
        $query = $this->query();
        $exp1 = $query->key()->eq($id);
        $exp2 = $query->col($setCol)->eq($set);
        $query->where($exp1)->range(0, 1)->update($exp2);
    }

    /**
     * multitple table query,default is left join query
     * 
     * @param array $table
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function multiAll(array $table, $limit, $offset = 0) {
        $this->setAlias();
        $selfQuery = $this->query();
        if (is_array($table)) {
            foreach ($table as $type => $t) {
                if (is_numeric($type)) {
                    $this->multiJoin($t, $selfQuery);
                } else {
                    $this->multiJoin($t, $selfQuery, $type);
                }
            }
        } else {
            $this->multiJoin($table, $selfQuery);
        }
        return $selfQuery->range($offset, $limit)->all();
    }

    /**
     * insert data
     * 
     * @param array $param          the first row data, if not key with query column name will use table defined feild
     * @param array $otherData      the other row data, must align with first param
     * @return int
     */
    public function insert($param, $otherData = []) {
        return $this->query()->insert($param, $otherData);
    }

    /**
     * save data, if has exists of key will update, otherwise insert data
     * 
     * @param array $param      save data, one value is pairs key/value map column-name/value
     * @param bool $autoUpate   whether auto update, if true will check unique key whether seted and exec update
     *                          if false only check parimary key
     * @param int $casVer       current cas ver
     * @param int $newVer       update new cas
     * @return int
     */
    public function modify(array $param, $autoUpate = true, $casVer = 0, $newVer = 0) {
        $keys = array_keys($param);
        if (isset($param[self::TABLE_KEY_NAME]) && (self::TABLE_AUTO_INCREMENT || $autoUpate)) {
            $idValue = $param[self::TABLE_KEY_NAME];
            if ($newVer && $casVer) {
                $this->casUpdateById($param, $idValue, $casVer, $newVer);
            } else {
                $this->updateById($param, $idValue);
            }
        } elseif ($autoUpate && ($ainter = array_intersect($keys, self::TABLE_UNIQUE))) {
            $query = $this->query();
            $and = $query->onAnd();
            foreach ($ainter as $col => $value) {
                $exp = $query->col($col)->eq($value);
                $and->arg($exp);
                unset($param[$col]);
            }
            if ($casVer && $newVer) {
                $and->arg($this->casExpression($query, $casVer));
                $param[self::$casVerCol] = $newVer;
            }
            return $query->where($and)->range(0, 1)->update($param);
        } else {
            if ($newVer) {
                $param[self::$casVerCol] = $newVer;
            }
            return $this->insert($param);
        }
    }

    /**
     * 
     * @param \Toknot\Database\QueryBuild $query
     * @param int $casVer
     * @return \Toknot\Database\Expression
     */
    public function casExpression(QueryBuild $query, $casVer) {
        return $query->col(self::$casVerCol)->eq($casVer);
    }

    /**
     * 
     * @param \Toknot\Database\TableModel $table
     * @param QueryBuild $selfQuery
     * @param string $type
     */
    protected function multiJoin(TableModel $table, QueryBuild $selfQuery, $type = QueryBuild::L_JOIN) {
        $table->setAlias();
        $exp = $table->query()->key()->eq($selfQuery->key());
        if ($type === QueryBuild::C_JOIN) {
            $selfQuery->join($table, $exp);
        } elseif ($type === QueryBuild::R_JOIN) {
            $selfQuery->rightJoin($table, $exp);
        } elseif ($type === QueryBuild::I_JOIN) {
            $selfQuery->innerJoin($table, $exp);
        } else {
            $selfQuery->leftJoin($table, $type, $exp);
        }
    }

}
