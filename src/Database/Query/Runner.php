<?php namespace Nano7\Framework\Database\Query;

use MongoDB\Collection;
use MongoDB\BSON\Regex;
use MongoDB\BSON\ObjectID;
use MongoDB\UpdateResult;
use MongoDB\BSON\UTCDateTime;
use Nano7\Framework\Support\Str;

/**
 * Class Runner
 * @method Builder limit($value)
 * @method Builder where($column, $operator = null, $value = null, $boolean = 'and')
 * @property Collection $collection
 * @property array $projections
 * @property array $columns
 * @property string $from
 * @property array $wheres
 * @property array $orders
 * @property array $options
 * @property array $operators
 * @property array $groups
 * @property array $aggregate
 * @property int $limit
 * @property int $offset
 * @property bool $distinct
 */
trait Runner
{
    use RunnerWheres;

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return \Illuminate\Support\Collection
     */
    public function get($columns = ['*'])
    {
        $results = $this->getFresh($columns);

        return collect($results);
    }

    /**
     * Execute the query and get the first result.
     *
     * @param  array  $columns
     * @return object|null
     */
    public function first($columns = ['*'])
    {
        return $this->limit(1)->get($columns)->first();
    }

    /**
     * Execute a query for a single record by ID.
     *
     * @param  int    $id
     * @param  array  $columns
     * @return mixed|static
     */
    public function find($id, $columns = ['*'])
    {
        return $this->where('_id', '=', $id)->first($columns);
    }

    /**
     * Insert documents.
     *
     * @param array $values
     * @return bool
     */
    public function insert(array $values)
    {
        // Since every insert gets treated like a batch insert, we will have to detect
        // if the user is inserting a single document or an array of documents.
        $batch = true;

        foreach ($values as $value) {
            // As soon as we find a value that is not an array we assume the user is
            // inserting a single document.
            if (!is_array($value)) {
                $batch = false;
                break;
            }
        }

        if (!$batch) {
            $values = [$values];
        }

        // Batch insert
        $result = $this->collection->insertMany($values);

        return (1 == (int) $result->isAcknowledged());
    }

    /**
     * Insert documet and return ID.
     *
     * @param array $values
     * @param null $sequence
     * @return mixed|null
     */
    public function insertGetId(array $values, $sequence = null)
    {
        $result = $this->collection->insertOne($values);

        if (1 == (int) $result->isAcknowledged()) {
            if (is_null($sequence)) {
                $sequence = '_id';
            }

            // Return id
            return $sequence == '_id' ? trim($result->getInsertedId()) : $values[$sequence];
        }

        return null;
    }

    /**
     * Update documents.
     *
     * @param array $values
     * @param array $options
     * @return int
     */
    public function update(array $values, array $options = [])
    {
        $result = $this->updateReturn($values, $options);

        if (1 == (int) $result->isAcknowledged()) {
            return $result->getModifiedCount() ? $result->getModifiedCount() : $result->getUpsertedCount();
        }

        return 0;
    }

    /**
     * Update documents.
     *
     * @param array $values
     * @param array $options
     * @return UpdateResult
     */
    public function updateReturn(array $values, array $options = [])
    {
        // Use $set as default operator.
        if (!Str::startsWith(key($values), '$')) {
            $values = ['$set' => $values];
        }

        // Update multiple items by default.
        if (!array_key_exists('multiple', $options)) {
            $options['multiple'] = true;
        }

        $wheres = $this->compileWheres();

        return $this->collection->updateMany($wheres, $values, $options);
    }

    /**
     * Delete documents.
     *
     * @param null $id
     * @return int
     */
    public function delete($id = null)
    {
        // If an ID is passed to the method, we will set the where clause to check
        // the ID to allow developers to simply and quickly remove a single row
        // from their database without manually specifying the where clauses.
        if (!is_null($id)) {
            $this->where('_id', '=', $id);
        }

        $wheres = $this->compileWheres();
        $result = $this->collection->deleteMany($wheres);

        if (1 == (int) $result->isAcknowledged()) {
            return $result->getDeletedCount();
        }

        return 0;
    }

    /**
     * Execute the query as a fresh "select" statement.
     *
     * @param  array $columns
     * @return array|static[]|Collection
     */
    protected function getFresh($columns = [])
    {
        // If no columns have been specified for the select statement, we will set them
        // here to either the passed columns, or the standard default of retrieving
        // all of the columns on the table using the "wildcard" column character.
        if (is_null($this->columns)) {
            $this->columns = $columns;
        }

        // Drop all columns if * is present, MongoDB does not work this way.
        if (in_array('*', $this->columns)) {
            $this->columns = [];
        }

        // Compile wheres
        $wheres = $this->compileWheres();

        // Use MongoDB's aggregation framework when using grouping or aggregation functions.
        if ($this->groups || $this->aggregate) {
            return $this->getFreshGroupAndAggregate($wheres);
        }

        // Return distinct results directly
        if ($this->distinct) {
            return $this->getFreshDistinct($wheres);
        }

        return $this->getFreshNormal($wheres);
    }

    /**
     * Fetch Distinct.
     *
     * @param $wheres
     * @return \mixed[]
     */
    protected function getFreshGroupAndAggregate($wheres)
    {
        $group = [];
        $unwinds = [];

        // Add grouping columns to the $group part of the aggregation pipeline.
        if ($this->groups) {
            foreach ($this->groups as $column) {
                $group['_id'][$column] = '$' . $column;

                // When grouping, also add the $last operator to each grouped field,
                // this mimics MySQL's behaviour a bit.
                $group[$column] = ['$last' => '$' . $column];
            }

            // Do the same for other columns that are selected.
            foreach ($this->columns as $column) {
                $key = str_replace('.', '_', $column);

                $group[$key] = ['$last' => '$' . $column];
            }
        }

        // Add aggregation functions to the $group part of the aggregation pipeline,
        // these may override previous aggregations.
        if ($this->aggregate) {
            $function = $this->aggregate['function'];

            foreach ($this->aggregate['columns'] as $column) {
                // Add unwind if a subdocument array should be aggregated
                // column: subarray.price => {$unwind: '$subarray'}
                if (count($splitColumns = explode('.*.', $column)) == 2) {
                    $unwinds[] = $splitColumns[0];
                    $column = implode('.', $splitColumns);
                }

                // Translate count into sum.
                if ($function == 'count') {
                    $group['aggregate'] = ['$sum' => 1];
                } // Pass other functions directly.
                else {
                    $group['aggregate'] = ['$' . $function => '$' . $column];
                }
            }
        }

        // The _id field is mandatory when using grouping.
        if ($group && empty($group['_id'])) {
            $group['_id'] = null;
        }

        // Build the aggregation pipeline.
        $pipeline = [];
        if ($wheres) {
            $pipeline[] = ['$match' => $wheres];
        }

        // apply unwinds for subdocument array aggregation
        foreach ($unwinds as $unwind) {
            $pipeline[] = ['$unwind' => '$' . $unwind];
        }

        if ($group) {
            $pipeline[] = ['$group' => $group];
        }

        // Apply order and limit
        if ($this->orders) {
            $pipeline[] = ['$sort' => $this->orders];
        }
        if ($this->offset) {
            $pipeline[] = ['$skip' => $this->offset];
        }
        if ($this->limit) {
            $pipeline[] = ['$limit' => $this->limit];
        }
        if ($this->projections) {
            $pipeline[] = ['$project' => $this->projections];
        }

        $options = [
            'typeMap' => ['root' => 'array', 'document' => 'array'],
        ];

        // Add custom query options
        if (count($this->options)) {
            $options = array_merge($options, $this->options);
        }

        // Execute aggregation
        $results = iterator_to_array($this->collection->aggregate($pipeline, $options));

        // Return results
        return $results;
    }

    /**
     * Fetch Distinct.
     *
     * @param $wheres
     * @return \mixed[]
     */
    protected function getFreshDistinct($wheres)
    {
        $column = isset($this->columns[0]) ? $this->columns[0] : '_id';

        // Execute distinct
        if ($wheres) {
            $result = $this->collection->distinct($column, $wheres);
        } else {
            $result = $this->collection->distinct($column);
        }

        return $result;
    }

    /**
     * Fetch Normal.
     *
     * @param $wheres
     * @return \mixed[]
     */
    protected function getFreshNormal($wheres)
    {
        $columns = [];

        // Convert select columns to simple projections.
        foreach ($this->columns as $column) {
            $columns[$column] = true;
        }

        // Add custom projections.
        if ($this->projections) {
            $columns = array_merge($columns, $this->projections);
        }
        $options = [];

        // Apply order, offset, limit and projection
        //if ($this->timeout) {
        //    $options['maxTimeMS'] = $this->timeout;
        //}
        if ($this->orders) {
            $options['sort'] = $this->compileOrders($this->orders);
        }
        if ($this->offset) {
            $options['skip'] = $this->offset;
        }
        if ($this->limit) {
            $options['limit'] = $this->limit;
        }
        if ($columns) {
            $options['projection'] = $columns;
        }
        // if ($this->hint)    $cursor->hint($this->hint);

        // Fix for legacy support, converts the results to arrays instead of objects.
        $options['typeMap'] = ['root' => 'array', 'document' => 'array'];

        // Add custom query options
        if (count($this->options)) {
            $options = array_merge($options, $this->options);
        }

        // Execute query and get MongoCursor
        $cursor = $this->collection->find($wheres, $options);

        // Return results as an array with numeric keys
        $results = iterator_to_array($cursor, false);

        return $results;
    }

    /**
     * @param array $orders
     * @return array
     */
    protected function compileOrders(array $orders)
    {
        $sorts = [];

        foreach ($orders as $ord) {
            list($oField, $oDirection) = array_values($ord);
            $sorts[$oField] = ($oDirection == 'desc') ? -1 : 1;
        }

        return $sorts;
    }

    /**
     * Convert a key to ObjectID if needed.
     *
     * @param  mixed $id
     * @return mixed
     */
    public static function convertKey($id)
    {
        if (is_string($id) && strlen($id) === 24 && ctype_xdigit($id)) {
            return new ObjectID($id);
        }

        return $id;
    }
}