<?php namespace Nano7\Framework\Database;

use Nano7\Framework\Database\Query\Builder;

interface ConnectionInterface
{
    /**
     * Check if collection exist.
     *
     * @param string $name
     * @return bool
     */
    public function hasCollection($name);

    /**
     * Get collections list.
     *
     * @param array $options
     * @return array
     */
    public function getCollections($options = []);

    /**
     * Get collection by name.
     *
     * @param $name
     * @return Builder
     */
    public function collection($name);

    /**
     * Create new collection.
     *
     * @param $name
     * @param array $options
     */
    public function createCollection($name, $options = []);

    /**
     * Drop a collection.
     *
     * @param $name
     * @param array $options
     */
    public function dropCollection($name, $options = []);

    /**
     * Check if index exist in collection.
     *
     * @param string $collection
     * @param string $name
     * @return bool
     */
    public function hasIndex($collection, $name);

    /**
     * Get index list in collection.
     *
     * @param string $collection
     * @param array $options
     * @return array
     */
    public function getIndexs($collection, $options = []);

    /**
     * Create new index.
     *
     * @param $collection
     * @param $columns
     * @param array $options
     * @return string
     */
    public function createIndex($collection, $columns, array $options = []);

    /**
     * Drop a index.
     *
     * @param $collection
     * @param $indexName
     * @param array $options
     * @return array|object
     */
    public function dropIndex($collection, $indexName, array $options = []);

    /**
     * Run an insert statement against the database.
     *
     * @param  string  $collection
     * @param  array   $bindings
     * @return bool
     */
    public function insert($collection, $bindings = []);

    /**
     * Start transaction. >= mongodb 4.0
     * @return bool
     */
    public function beginTransaction();

    /**
     * Commit. >= mongodb 4.0
     * @return bool
     */
    public function commit();

    /**
     * Abor (abort). >= mongodb 4.0
     * @return bool
     */
    public function abort();

    /**
     * Abor (abort). >= mongodb 4.0
     * @return mixed
     */
    public function transaction(\Closure $callback);
}