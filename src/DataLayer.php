<?php

namespace WalissonHms\DataLayer;

use Exception;
use PDO;
use PDOException;
use stdClass;

/**
 * Class DataLayer
 * @package WalissonHms\DataLayer
 */
abstract class DataLayer {
    use CrudTrait;

    /** @var string $entity database table */
    private $entity;

    /** @var string $primary table primary key field */
    private $primary;

    /** @var array $required table required fields */
    private $required;

    /** @var string $timestamps control created and updated at */
    private $timestamps;

    /** @var string */
    protected $statement;

    /** @var string */
    protected $params;

    /** @var string */
    protected $group;

    /** @var string */
    protected $having;

    /** @var string */
    protected $order;

    /** @var int */
    protected $limit;

    /** @var int */
    protected $offset;

    /** @var \PDOException|null */
    protected $fail;

    /** @var object|null */
    protected $data;

    /** @var int */
    protected $limitNumber = 10;

    /**
     * DataLayer constructor.
     * @param string $entity
     * @param array $required
     * @param string $primary
     * @param bool $timestamps
     */
    public function __construct(string $entity, array $required, string $primary = 'id', bool $timestamps = true) {
        $this->entity = $entity;
        $this->primary = $primary;
        $this->required = $required;
        $this->timestamps = $timestamps;
    }

    /**
     * @param $method
     * @param $arguments
     * @return DataLayer|null
     */
    public function __call($method, $arguments): ?DataLayer {
        $snakeCase = fn (string $word) => strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $word));

        if (str_starts_with($method, 'findBy')) {
            return $this->findBy([$snakeCase(substr($method, 6)) => implode(', ', $arguments)]);
        }
    }

    /**
     * @param $name
     * @param $value
     */
    public function __set($name, $value) {
        if (empty($this->data)) {
            $this->data = new stdClass();
        }

        $this->data->$name = $value;
    }

    /**
     * @param $name
     * @return bool
     */
    public function __isset($name) {
        return isset($this->data->$name);
    }

    /**
     * @param $name
     * @return string|null
     */
    public function __get($name) {
        $method = $this->toCamelCase($name);
        if (method_exists($this, $method)) {
            return $this->$method();
        }

        if (method_exists($this, $name)) {
            return $this->$name();
        }

        return ($this->data->$name ?? null);
    }

    /*
    * @return PDO mode
    */
    public function columns($mode = PDO::FETCH_OBJ) {
        $stmt = Connect::getInstance()->prepare("DESCRIBE {$this->entity}");
        $stmt->execute($this->params);
        return $stmt->fetchAll($mode);
    }


    /**
     * @return object|null
     */
    public function data(): ?object {
        return $this->data;
    }

    /**
     * @return PDOException|Exception|null
     */
    public function fail() {
        return $this->fail;
    }

    /**
     * @param string|null $terms
     * @param string|null $params
     * @param string $columns
     * @return DataLayer
     */
    public function find(?string $terms = null, ?string $params = null, string $columns = "*"): DataLayer {
        if ($terms) {
            $this->statement = "SELECT {$columns} FROM {$this->entity} WHERE {$terms}";
            parse_str($params, $this->params);
            return $this;
        }

        $this->statement = "SELECT {$columns} FROM {$this->entity}";
        return $this;
    }

    // /**
    //  * @param int $id
    //  * @param string $columns
    //  * @return DataLayer|null
    //  */
    // public function findById(int $id, string $columns = "*"): ?DataLayer
    // {
    //     return $this->find("{$this->primary} = :id", "id={$id}", $columns)->fetch();
    // }

    /**
     * @param array $criteria
     * @param string|null $columns
     * @return DataLayer|null
     */
    public function findBy(array $criteria, ?string $columns = '*'): ?DataLayer {
        $terms = null;
        foreach ($criteria as $key => $value) {
            $terms[] = $key . ' = :' . $key;
        }

        return $this->find(implode(' AND ', $terms), http_build_query($criteria), $columns)->fetch();
    }

    /**
     * @param string $columns
     * @param null|string $table
     * @return DataLayer
     */
    public function select(string $columns = "*", $table = null): DataLayer {
        $table = $table ?? $this->entity;
        $this->statement = "SELECT {$columns} FROM {$table}";

        return $this;
    }

    /**
     * @param string $column
     * @param string $alias
     * @param null|string $table
     * @return DataLayer
     */
    public function max(string $column, string $alias, string $table = null): DataLayer {
        $table = $table ?? $this->entity;
        $this->statement = "SELECT MAX({$column}) as {$alias} FROM {$table}";

        return $this;
    }

    /**
     * @param null|string $table
     * @return DataLayer
     */
    public function from($table = null): DataLayer {
        $table = $table ?? $this->entity;
        $this->statement = " FROM {$table} ";

        return $this;
    }

    /**
     * @param string $table
     * @param string $first
     * @param string|null $operator
     * @param string|null $second
     * @param string $type
     * @param string|null $where
     * @return DataLayer
     */
    public function join($table, $first, $operator = null, $second = null, $type = 'inner', $where = null): DataLayer {
        $type = strtoupper($type);
        $where = ($where ? ' AND ' . $where : null);
        $this->statement .= " {$type} JOIN {$table} ON {$first} {$operator} {$second} {$where}";
        return $this;
    }

    /**
     * @param string $column
     * @return DataLayer|null
     */
    public function having(string $column): ?DataLayer {
        $this->having = " HAVING {$column}";
        return $this;
    }

    /**
     * @param string $nameColumn
     * @param string $operator
     * @param string|null $valueToCompare
     * @param string $replace = ')'
     * @return DataLayer
     */
    public function where($nameColumn, $operator = '=', $valueToCompare = null, $replace = null): DataLayer {
        if (strpos($this->statement, 'WHERE') !== false) {
            $nameColumn = ($nameColumn ? ' AND ' . $nameColumn : null);
        } else {
            $nameColumn = ($nameColumn ? ' WHERE ' . $nameColumn : null);
        }

        if (is_null($valueToCompare)) {
            $this->statement .= " {$nameColumn} " . " {$operator} {$replace}";
        } else {
            if (is_numeric($valueToCompare) && is_int($valueToCompare)) {
                $this->statement .= " {$nameColumn} " . " {$operator} " . $valueToCompare . " {$replace} ";
            } else {
                if (strpos($valueToCompare, '(') !== false && strpos($valueToCompare, ')') !== false) {
                    $this->statement .= " {$nameColumn} " . " {$operator} " . $valueToCompare . " {$replace} ";
                } else {
                    $this->statement .= " {$nameColumn} " . " {$operator} " . " '{$valueToCompare}' " . " {$replace} ";
                }
            }
        }

        return $this;
    }

    /**
     * @param string $nameColumn
     * @param integer $inValue
     * @return DataLayer
     */
    public function whereIn($nameColumn, $inValue) {
        if (strpos($this->statement, 'WHERE') !== false) {
            $nameColumn = ($nameColumn ? ' AND ' . $nameColumn : null);
        } else {
            $nameColumn = ($nameColumn ? ' WHERE ' . $nameColumn : null);
        }

        $this->statement .= $nameColumn . " IN ( " . $inValue . " )";

        return $this;
    }

    /**
     * @param string $nameColumn
     * @param integer $inValue
     * @return DataLayer
     */
    public function whereNotIn($nameColumn, $inValue) {
        if (strpos($this->statement, 'WHERE') !== false) {
            $nameColumn = ($nameColumn ? ' AND ' . $nameColumn : null);
        } else {
            $nameColumn = ($nameColumn ? ' WHERE ' . $nameColumn : null);
        }

        $this->statement .= $nameColumn . " NOT IN ( " . $inValue . " )";

        return $this;
    }

    /**
     * @param string $nameColumn
     * @param integer $inValue
     * @return DataLayer
     */
    public function whereIsNull($nameColumn, $inValue) {
        if (strpos($this->statement, 'WHERE') !== false) {
            $nameColumn = ($nameColumn ? ' AND ' . $nameColumn : null);
        } else {
            $nameColumn = ($nameColumn ? ' WHERE ' . $nameColumn : null);
        }

        $this->statement .= $nameColumn . " IS NULL ";

        return $this;
    }

    /**
     * @param string $nameColumn
     * @param integer $inValue
     * @return DataLayer
     */
    public function whereIsNotNull($nameColumn, $inValue) {
        if (strpos($this->statement, 'WHERE') !== false) {
            $nameColumn = ($nameColumn ? ' AND ' . $nameColumn : null);
        } else {
            $nameColumn = ($nameColumn ? ' WHERE ' . $nameColumn : null);
        }

        $this->statement .= $nameColumn . " IS NOT NULL ";

        return $this;
    }

    /**
     * @param string $nameColumn
     * @param integer $inValue
     * @return DataLayer
     */
    public function whereLike($nameColumn, $inValue) {
        if (strpos($this->statement, 'WHERE') !== false) {
            $nameColumn = ($nameColumn ? ' AND ' . $nameColumn : null);
        } else {
            $nameColumn = ($nameColumn ? ' WHERE ' . $nameColumn : null);
        }

        $this->statement .= $nameColumn . " LIKE '%{$inValue}%' ";

        return $this;
    }

    /**
     * @param string $nameColumn
     * @param string $operator
     * @param string|null $valueToCompare
     * @param string $replace = ')'
     * @return DataLayer
     */
    public function orWhere($nameColumn, $operator = '=', $valueToCompare = null, $replace = null): DataLayer {
        $nameColumn = ($nameColumn ? ' OR ' . $nameColumn : null);

        if (is_null($valueToCompare)) {
            $this->statement .= " {$nameColumn} " . " {$operator} {$replace}";
        } else {
            if (is_numeric($valueToCompare) && is_int($valueToCompare)) {
                $this->statement .= " {$nameColumn} " . " {$operator} " . $valueToCompare . " {$replace} ";
            } else {
                $this->statement .= " {$nameColumn} " . " {$operator} " . " '{$valueToCompare}' " . " {$replace} ";
            }
        }

        return $this;
    }

    /**
     * @param string $nameColumn
     * @param string $startDate
     * @param string $endDate
     * @return DataLayer
     */
    public function between($nameColumn, $startDate, $endDate): DataLayer {
        $this->statement .= " AND {$nameColumn} BETWEEN '{$startDate}' AND '{$endDate}' ";

        return $this;
    }

    /**
     * @param string $nameColumn
     * @param string $operator
     * @param string|null $valueToCompare
     * @param string $replace = ')'
     * @return DataLayer
     */
    public function andWhere($nameColumn, $operator = '=', $valueToCompare = null, $replace = null): DataLayer {
        $nameColumn = ($nameColumn ? ' AND ' . $nameColumn : null);

        if (is_null($valueToCompare)) {
            $this->statement .= " {$nameColumn} " . " {$operator} ";
        } else {
            if (is_numeric($valueToCompare) && is_int($valueToCompare)) {
                $this->statement .= " {$nameColumn} " . " {$operator} " . $valueToCompare . " {$replace} ";
            } else {
                $this->statement .= " {$nameColumn} " . " {$operator} " . " '{$valueToCompare}' "  . " {$replace} ";
            }
        }

        return $this;
    }

    /**
     * @param string $column
     * @return DataLayer|null
     */
    public function group(string $column): ?DataLayer {
        $this->group = " GROUP BY {$column}";
        return $this;
    }

    /**
     * @param string $columnOrder
     * @param string|null $order
     * @return DataLayer|null
     */
    public function order(string $columnOrder, string $order = null): ?DataLayer {
        $this->order = " ORDER BY {$columnOrder} " . ($order ? $order : null);
        return $this;
    }

    /**
     * @param int $limit
     * @param int|null $offset
     * @return DataLayer|null
     */
    public function limit(int $limit, int $offset = null): ?DataLayer {
        $this->limit = " LIMIT {$limit} " . ($offset ? ", {$offset}" : null);
        return $this;
    }

    /**
     * @param int $offset
     * @return DataLayer|null
     */
    public function offset(int $offset): ?DataLayer {
        $this->offset = " OFFSET {$offset}";
        return $this;
    }

    /**
     * @param bool $all
     * @return array|mixed|null
     */
    public function get() {
        try {
            $stmt = Connect::getInstance()->prepare($this->statement . $this->group . $this->having . $this->order . $this->limit . $this->offset);
            $stmt->execute($this->params);

            if (!$stmt->rowCount()) {
                return null;
            }

            return $stmt->fetchAll();
        } catch (PDOException $exception) {
            $this->fail = $exception;
            return null;
        }
    }

    /**
     * @param bool $all
     * @return array|mixed|null
     */
    public function debug() {
        echo "<pre>";
        print_r($this->statement . $this->group . $this->order . $this->limit . $this->offset);
        die;
    }

    /**
     * @return array|mixed|null
     */
    public function first() {
        try {
            $stmt = Connect::getInstance()->prepare($this->statement . $this->group . $this->having . $this->order . $this->limit . $this->offset);
            $stmt->execute($this->params);

            if (!$stmt->rowCount()) {
                return null;
            }

            return $stmt->fetchObject(static::class)->data;
        } catch (PDOException $exception) {
            $this->fail = $exception;
            return null;
        }
    }

    /**
     * @param bool $all
     * @return array|mixed|null
     */
    public function fetch(bool $all = false) {
        try {
            $stmt = Connect::getInstance()->prepare($this->statement . $this->group . $this->having . $this->order . $this->limit . $this->offset);
            $stmt->execute($this->params);

            if (!$stmt->rowCount()) {
                return null;
            }

            if ($all) {
                return $stmt->fetchAll(PDO::FETCH_CLASS, static::class);
            }

            return $stmt->fetchObject(static::class);
        } catch (PDOException $exception) {
            $this->fail = $exception;
            return null;
        }
    }

    /**
     * @return int
     */
    public function count(): int {
        $stmt = Connect::getInstance()->prepare($this->statement);
        $stmt->execute($this->params);
        return $stmt->rowCount();
    }

    /**
     * @param int $page
     * @param int $limit
     * @param string $columns
     * @return DataLayer
     */
    public function paginator(int $page = 1, int $limit, string $columns = '*') {
        $offset = ($page * $limit) - $limit;
        $this->limitNumber = $limit;
        $this->limit = " LIMIT {$limit} OFFSET {$offset} ";
        $this->columns = $columns;

        return $this;
    }

    /**
     * Paginator for Bootstrap
     */
    private function paginatorBootstrap() {
        $paginator = new stdClass();

        $paginator->pages = ceil($this->count() / $this->limitNumber);
        $paginator->page = $this->page;
        $paginator->limitNumber = $this->limitNumber;
        $paginator->offset = ($this->page * $this->limitNumber) - $this->limitNumber;

        return $paginator;
    }

    public function renderPaginator() {
        $paginator = $this->paginatorBootstrap();
        $link = $this->link;
        $pages = $paginator->pages;
        $page = $paginator->page;

        $first = ($page > 1 ? "<li class='page-item'><a class='page-link' href='{$link}1'>Primeira</a></li>" : null);
        $last = ($page < $pages ? "<li class='page-item'><a class='page-link' href='{$link}{$pages}'>Última</a></li>" : null);

        $links = null;
        for ($i = $page - 2; $i <= $page + 2; $i++) {
            if ($i >= 1 && $i <= $pages) {
                $links .= ($i == $page ? "<li class='page-item active'><a class='page-link' href='{$link}{$i}'>{$i}</a></li>" : "<li class='page-item'><a class='page-link' href='{$link}{$i}'>{$i}</a></li>");
            }
        }

        return "<ul class='pagination'>{$first}{$links}{$last}</ul>";
    }

    /**
     * @return bool
     */
    public function save(): bool {
        $primary = $this->primary;
        $id = null;

        try {
            if (!$this->required()) {
                throw new Exception("Preencha os campos necessários");
            }

            /** Update */
            if (!empty($this->data->$primary)) {
                $id = $this->data->$primary;
                $this->update($this->safe(), "{$this->primary} = :id", "id={$id}");
            }

            /** Create */
            if (empty($this->data->$primary)) {
                $id = $this->create($this->safe());
            }

            if (!$id) {
                return false;
            }

            $this->data = $this->findById($id)->data();
            return true;
        } catch (Exception $exception) {
            $this->fail = $exception;
            return false;
        }
    }

    /**
     * @return bool
     */
    public function destroy(): bool {
        $primary = $this->primary;
        $id = $this->data->$primary;

        if (empty($id)) {
            return false;
        }

        return $this->delete("{$this->primary} = :id", "id={$id}");
    }

    /**
     * @return bool
     */
    protected function required(): bool {
        $data = (array)$this->data();
        foreach ($this->required as $field) {
            if (empty($data[$field])) {
                if (!is_int($data[$field])) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * @return array|null
     */
    protected function safe(): ?array {
        $safe = (array)$this->data;
        unset($safe[$this->primary]);
        return $safe;
    }


    /**
     * @param string $string
     * @return string
     */
    protected function toCamelCase(string $string): string {
        $camelCase = str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
        $camelCase[0] = strtolower($camelCase[0]);
        return $camelCase;
    }
}
