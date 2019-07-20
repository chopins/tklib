<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 chopin xiao (xiao@toknot.com)
 */

namespace Toknot\Database\Exception;

use RuntimeException;

class DBException extends RuntimeException {

    protected $sqlState = 0;
    protected $queryError = '';
    protected $sql =  '';
    protected $queryParams =  [];

    public function __construct($message, $sqlState = 0, $queryError = '', $sql = '', $params = []) {
        if($sqlState) {
            $message .=   ";QueryState[$sqlState]:$queryError, QuerySQL:[ $sql ]" . "Parameter: " . var_export($params, true);
        }
        $this->sqlState = $sqlState;
        $this->queryError = $queryError;
        $this->sql = $sql;
        $this->queryParams = $params;
        parent::__construct($message, $sqlState);
    }

    public function getSqlState() {
        return $this->sqlState;
    }

    public function getQueryError() {
        return $this->queryError;
    }

    public function getQuerySql() {
        return $this->sql;
    }
    public function getQueryParams() {
        return $this->queryParams;
    }
}