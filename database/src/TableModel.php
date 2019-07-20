<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 chopin xiao (xiao@toknot.com)
 */

namespace Toknot\Database;

use Toknot\Database\DB;
use Toknot\Database\QueryBuild;
use Toknot\Database\Exception\DBException;

abstract class TableModel {

    public function __construct($serverid, $condition = '') {
        $this->setServerId($serverid);
        if($condition) {
            $this->findOne($condition);
        }
        return $this;
    }

    public function getTableConst($name) {
        $className = $this::TABLE_CLASS_NAME;
        return \constant("$className::$name");
    }

    private function setAttributes($name, $key = null, $value = null) {
        $pro = $this->getTableConst($name);
        if(\is_array($key)) {
            $this->$pro = $key;
            return;
        } elseif(!$key) {
            $this->$pro = $value;
            return;
        }
        $this->$pro[$key] = $value;
    }
    private function getAttributes($name, $key = null) {
        $pro = $this->getTableConst($name);
        if($key) {
            return $this->$pro[$key];
        }
        return $this->$pro;
    }

    protected function setServerId($serverid) {
        $this->setAttributes('ATTR_SERVER_ID', null, $serverid);
    }

    protected function getCasVerColAttr() {
        return $this->getAttributes('ATTR_CAS_VER_COL');
    }

    protected function setCasVerColAttr($feilds) {
        $this->setAttributes('ATTR_CAS_VER_COL', null, $feilds);
    }

    protected function setRecordValues($key, $value = null) {
        $this->setAttributes('ATTR_RECORD_VALUES', $key, $value);
    }

    protected function getRecordValues($key = null) {
        return $this->getAttributes('ATTR_RECORD_VALUES', $key);
    }

    protected function setColsValues($key, $value = null) {
        $this->setAttributes('ATTR_SET_COL_VALUES', $key, $value);
    }

    protected function getColsValues($key = null) {
        return $this->getAttributes('ATTR_SET_COL_VALUES', $key);
    }

    protected function setFilterValues($key, $value = null) {
        return $this->setAttributes('ATTR_FILTER_VALUES', $key, $value);
    }

    protected function getFilterValues($key = null) {
        return $this->getAttributes('ATTR_FILTER_VALUES', $key);
    }

    public function getLastSql() {
        return $this->db()->getLastSql();
    }

    public function __set($name, $value = '') {
        if(!\in_array($name, $this::TABLE_COLUMN_LIST)) {
            throw new DBException("column '$name' not exists in table `" . $this::TABLE_NAME.'`');
        }
        if(\is_scalar($value)) {
            $this->setRecordValues($name, $value);
            $this->setColsValues($name, $value);
        } else {
            $this->setFilterValues($name, $value);
        }
    }

    public function __get($name) {
        if(!\in_array($name, $this::TABLE_COLUMN_LIST)) {
            throw new DBException("column '$name' not exists in table `" . $this::TABLE_NAME . '`');
        }
        return $this->getRecordValues($name);
    }

    /**
     * 
     * @return \Toknot\Database\DB
     */
    public function db($serverid = '') {
        $id = $serverid ? $serverid : $this->getAttributes('ATTR_SERVER_ID');
        $this->setServerId($id);
        return DB::connect($id);
    }

    public function executeSelectOrUpdate(QueryBuild $queryBuild) {
        return $this->db()->executeSelectOrUpdate($queryBuild);
    }

    public function idValue() {
        return $this->getAttributes('TABLE_KEY_NAME');
    }

    public function getKeyName() {
        return $this::TABLE_KEY_NAME;
    }

    public function getColumns() {
        return $this::TABLE_COLUMN_LIST;
    }

    public function getCols() {
        return $this::TABLE_COLS;
    }

    public function getUnique() {
        return $this::TABLE_UNIQUE;
    }

    public function getQueryField() {
        return $this::TABLE_SELECT_FEILD;
    }

    public function getIndex() {
        return $this::TABLE_INDEX;
    }

    public function isNewRecord() {
        return $this->getAttributes('ATTR_NEW_RECORD');
    }
    public function setNewRecord($status = true) {
        $this->setAttributes('ATTR_NEW_RECORD', null, $status);
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
        return $this::TABLE_NAME;
    }

    public function quote($string) {
        $this->db()->quote($string);
    }

    /**
     * 
     * @return string
     */
    public function getAlias() {
        $pro = $this::ATTR_ALIAS_NAME;
        return $this->$pro;
    }

    /**
     * 
     * @param string $alias
     */
    public function setAlias($alias = '') {
        $pro = $this::ATTR_ALIAS_NAME;
        $this->$pro = $alias ? $alias : $this::TABLE_NAME;
    }

    /**
     * find one row
     * 
     * @param mixed $id
     * @return $this
     */
    public function findOne($id) {
        $query = $this->query();
        if (is_array($id)) {
            $list = $query->where($id)->limit(1)->row();
        } else {
            $list = $query->findOne($id);
        }
        if($list) {
            $this->setRecordValues($list);
            $this->setNewRecord(false);
            return $this;
        } else {
            return null;
        }
    }

    public function save() {
        $isNewRecord = $this->isNewRecord();
        if($isNewRecord) {
            $res = $this->insert($this->getRecordValues());
            $this->lastInsertId();
            $this->findOne($res);
        } else {
            $updateData = $this->getColsValues();
            if($isNewRecord) {
                throw new DBException('record not exitss');
            } else {
                $filter = $this->getFilterValues();
                $id = $this->idValue();
                $res = $this->updateById($updateData, $id, $filter);
                $this->findOne($id);
            }
        }
        return $res;
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
    public function findAll($where = '', $limit = 10, $offset = 0) {
        $query = $this->query();
        $filter = $this->getFilterValues();
        $filter[] = $where;
        return $query->range($offset, $limit)->where($filter)->all();
    }

    /**
     * find row by greater than id
     * 
     * @param int $id
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function findGTId($id, $limit = 10, $offset = 0) {
        $query = $this->query();
        $exp = $query->col($this::TABLE_KEY_NAME)->gt($id);
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
    public function findLTId($id, $limit = 10, $offset = 0) {
        $query = $this->query();
        $exp = $query->col($this::TABLE_KEY_NAME)->lt($id);
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
        $casVerCol = $this->getCasVerColAttr();
        $exp1 = $query->col($this::TABLE_KEY_NAME)->eq($id);
        $exp2 = $query->col($casVerCol)->eq($casValue);
        $exp = $query->onAnd($exp1, $exp2);
        $param[$casVerCol] = $newValue;
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
    public function updateById(array $data, &$id, $where = '') {
        $query = $this->query();
        $exp1 = $query->col($this::TABLE_KEY_NAME)->eq($id);
        $filter = $exp1;
        if ($where) {
            $and = $query->onAnd();
            $and->arg($exp1);
            if(\is_array($where)) {
                $and->arg($query->hashQuery($where));
            } else {
                $and->arg($where);
            }
            $filter = $and;
        }
        if(isset($data[$this::TABLE_KEY_NAME])) {
            $id = $data[$this::TABLE_KEY_NAME];
        }
        return $query->where($filter)->range(0, 1)->update($data);
    }

    public function update($data, $filter) {
        $query = $this->query();
        return $query->where($filter)->update($data);
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
        if (isset($param[$this::TABLE_KEY_NAME]) && ($this::TABLE_AUTO_INCREMENT || $autoUpate)) {
            $idValue = $param[$this::TABLE_KEY_NAME];
            if ($newVer && $casVer) {
                $this->casUpdateById($param, $idValue, $casVer, $newVer);
            } else {
                $this->updateById($param, $idValue);
            }
        } elseif ($autoUpate && ($ainter = array_intersect($keys, $this::TABLE_UNIQUE))) {
            $query = $this->query();
            $and = $query->onAnd();
            foreach ($ainter as $col => $value) {
                $exp = $query->col($col)->eq($value);
                $and->arg($exp);
                unset($param[$col]);
            }
            if ($casVer && $newVer) {
                $and->arg($this->casExpression($query, $casVer));
                $casVerCol = $this->getCasVerColAttr();
                $param[$casVerCol] = $newVer;
            }
            return $query->where($and)->range(0, 1)->update($param);
        } else {
            if ($newVer) {
                $casVerCol = $this->getCasVerColAttr();
                $param[$casVerCol] = $newVer;
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
        $casVerCol = $this->getCasVerColAttr();
        return $query->col($casVerCol)->eq($casVer);
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
