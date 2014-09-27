<?php
namespace collection;

use InvalidArgumentException;

/**
 * The parent class for all collection objects. Contains methods for collection iteration,
 * conversion, and filtering. Implements `ArrayAccess`, `Iterator`, and `Countable`.
 *
 * `Collection` objects can act very much like arrays. This is especially evident in creating new
 * objects, or by converting `Collection` into an actual array:
 *
 * {{{
 * $coll = new Collection();
 * $coll[] = 'foo';
 * // $coll[0] --> 'foo'
 *
 * $coll = new Collection(['data' => ['foo']]);
 * // $coll[0] --> 'foo'
 *
 * $array = iterator_to_array($coll);
 * }}}
 *
 * Apart from array-like data access, `Collection`s enable terse and expressive
 * filtering and iteration:
 *
 * {{{
 * $coll = new Collection([0, 1, 2, 3, 4]);
 *
 * $coll->first();   // 0
 * $coll->current(); // 0
 * $coll->next();    // 1
 * $coll->next();    // 2
 * $coll->next();    // 3
 * $coll->prev();    // 2
 * $coll->rewind();  // 0
 * }}}
 *
 * The primary purpose of the `Collection` class is to enable simple, efficient access to groups
 * of similar objects, and to perform operations against these objects using anonymous functions.
 *
 * The `map()` and `each()` methods allow you to perform operations against the entire set of values
 * in a `Collection`, while `find()` and `first()` allow you to search through values and pick out
 * one or more.
 *
 * The `Collection` class also supports dispatching methods against a set of objects, if the method
 * is supported by all objects. For example:
 *
 * {{{
 * class Task {
 *     public function run($when) {
 *         // Do some work
 *     }
 * }
 *
 * $tasks = new Collection(['data' => [
 *     new Task(['id' => 'task1']),
 *     new Task(['id' => 'task2']),
 *     new Task(['id' => 'task3'])
 * ]]);
 *
 * // $result will contain an array, and each element will be the return
 * // value of a run() method call:
 * $result = $tasks->invoke('run', ['now']);
 * }}}
 *
 */
class Collection implements \ArrayAccess, \Iterator, \Countable
{
    /**
     * The items contained in the collection.
     *
     * @var array
     */
    protected $_data = [];

    /**
     * Workaround to allow consistent `unset()` in `foreach`.
     *
     * Note: the edge effet of this behavior is the following:
     * {{{
     *   $collection = new Collection(['data' => ['1', '2', '3']]);
     *   unset($collection[0]);
     *   $collection->next();   // returns 2 instead of 3 when no `skipNext`
     * }}}
     */
    protected $_skipNext = false;

    /**
     * The constructor
     *
     * @param array $data The data
     */
    public function __construct($config = [])
    {
        if (isset($config['data'])) {
            $this->_data = $config['data'];
        }
    }

    /**
     * Handles dispatching of methods against all items in the collection.
     *
     * @param string $method The name of the method to call on each instance in the collection.
     * @param mixed  $params The parameters to pass on each method call.
     *
     * @return mixed Returns either an array of the return values of the methods, or the return
     *               values wrapped in a `Collection` instance.
     */
    public function invoke($method, $params = [])
    {
        $data = [];
        $isCallable = is_callable($params);

        foreach ($this as $key => $object) {
            $callParams = $isCallable ? $params($object, $key, $this) : $params;
            $data[$key] = call_user_func_array([$object, $method], $callParams);
        }

        return new static(compact('data'));
    }

    /**
     * Checks whether or not an offset exists.
     *
     * @param string  $offset An offset to check for.
     *
     * @return boolean `true` if offset exists, `false` otherwise.
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->_data);
    }

    /**
     * Returns the value at specified offset.
     *
     * @param string $offset The offset to retrieve.
     *
     * @return mixed Value at offset.
     */
    public function offsetGet($offset)
    {
        return $this->_data[$offset];
    }

    /**
     * Assigns a value to the specified offset.
     *
     * @param string $offset The offset to assign the value to.
     * @param mixed  $value The value to set.
     *
     * @return mixed The value which was set.
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            return $this->_data[] = $value;
        }
        return $this->_data[$offset] = $value;
    }

    /**
     * Unsets an offset.
     *
     * @param string $offset The offset to unset.
     */
    public function offsetUnset($offset)
    {
        $this->_skipNext = $offset === key($this->_data);
        unset($this->_data[$offset]);
    }

    /**
     * Merge another collection to this collection.
     *
     * @param mixed   A collection.
     * @param boolean If `true` use the key value as a hash to avoid duplicates.
     *
     * @return Collection Return the merged collection.
     */
    public function merge($collection, $preserveKeys = false) {
        foreach($collection as $key => $value) {
            $preserveKeys ? $this->_data[$key] = $value : $this->_data[] = $value;
        }
        return $this;
    }

    /**
     * Returns the item keys.
     *
     * @return array The keys of the items.
     */
    public function keys()
    {
        return array_keys($this->_data);
    }

    /**
     * Returns the item values.
     *
     * @return array The keys of the items.
     */
    public function values()
    {
        return array_values($this->_data);
    }

    /**
     * Gets the raw array value of the `Collection`.
     *
     * @return array Returns an "unboxed" array of the `Collection`'s value.
     */
    public function raw()
    {
        return $this->_data;
    }

    /**
     * Returns the key of the current item.
     *
     * @return scalar Scalar on success or `null` on failure.
     */
    public function key()
    {
        return key($this->_data);
    }

    /**
     * Returns the current item.
     *
     * @return mixed The current item or `false` on failure.
     */
    public function current()
    {
        return current($this->_data);
    }

    /**
     * Moves backward to the previous item.  If already at the first item,
     * moves to the last one.
     *
     * @return mixed The current item after moving or the last item on failure.
     */
    public function prev()
    {
        $value = prev($this->_data);
        return key($this->_data) !== null ? $value : null;
    }

    /**
     * Move forwards to the next item.
     *
     * @return The current item after moving or `false` on failure.
     */
    public function next()
    {
        $value = $this->_skipNext ? current($this->_data) : next($this->_data);
        $this->_skipNext = false;
        return key($this->_data) !== null ? $value : null;
    }

    /**
     * Alias to `::rewind()`.
     *
     * @return mixed The current item after rewinding.
     */
    public function first()
    {
        return $this->rewind();
    }

    /**
     * Rewinds to the first item.
     *
     * @return mixed The current item after rewinding.
     */
    public function rewind()
    {
        return reset($this->_data);
    }

    /**
     * Moves forward to the last item.
     *
     * @return mixed The current item after moving.
     */
    public function end()
    {
        end($this->_data);
        return current($this->_data);
    }

    /**
     * Checks if current position is valid.
     *
     * @return boolean `true` if valid, `false` otherwise.
     */
    public function valid()
    {
        return key($this->_data) !== null;
    }

    /**
     * Counts the items of the object.
     *
     * @return integer Returns the number of items in the collection.
     */
    public function count() {
        return count($this->_data);
    }

    /**
     * Filters a copy of the items in the collection.
     *
     * @param mixed $filter The callback to use for filtering, or an array of key/value pairs to match.
     *
     * @return mixed Returns a collection of the filtered items.
     */
    public function find($filter)
    {
        $callback = is_array($filter) ? $this->_filterFromArray($filter) : $filter;
        $data = array_filter($this->_data, $callback);
        return new static(compact('data'));
    }

    /**
     * Applies a callback to all items in the collection.
     *
     * @param callback $callback The callback to apply.
     *
     * @return object   This collection instance.
     */
    public function each($callback)
    {
        foreach ($this->_data as $key => $val) {
            $this->_data[$key] = $callback($val, $key, $this);
        }
        return $this;
    }

    /**
     * Applies a callback to a copy of all data in the collection
     * and returns the result.
     *
     * @param callback $callback The callback to apply.
     *
     * @return mixed    Returns the set of filtered values inside a `Collection`.
     */
    public function map($callback)
    {
        $data = array_map($callback, $this->_data);
        return new static(compact('data'));
    }

    /**
     * Reduce, or fold, a collection down to a single value
     *
     * @param callback $filter The filter to apply.
     * @param mixed    $initial Initial value.
     *
     * @return mixed A single reduced value.
     */
    public function reduce($filter, $initial = null)
    {
        return array_reduce($this->_data, $filter, $initial);
    }

    /**
     * Extract a slice of $length items starting at position $offset from the Collection.
     *
     * If $length is null it returns all elements from $offset to the end of the Collection.
     * Keys have to be preserved by this method. Calling this method will only return the
     * selected slice and NOT change the elements contained in the collection slice is called on.
     *
     * @param integer $offset The offset value.
     * @param integer $length The number of element to extract
     *
     * @return array
     */
    public function slice($offset, $length = null, $preserveKeys = true) {
        $data = array_slice($this->_data, $offset, $length, $preserveKeys);
        return new static(compact('data'));
    }

    /**
     * Sorts the objects in the collection.
     *
     * @param callback $callback A compare function like strcmp or a custom closure. The
     *                 comparison function must return an integer less than, equal to, or
     *                 greater than zero if the first argument is considered to be respectively
     *                 less than, equal to, or greater than the second.
     * @param string   $sorter The type of sorting, can be any sort function like `asort`,
     *                 'uksort', or `natsort`.
     *
     * @return Collection Return `$this`.
     */
    public function sort($callback = null, $sorter = null) {
        if (!$sorter) {
            $sorter = $callback === null ? 'sort' : 'usort';
        }
        if (!is_callable($sorter)) {
            throw new InvalidArgumentException("The passed parameter is not a valid sort function.");
        }
        $data = $this->_data;
        $callback === null ? $sorter($data) : $sorter($data, $callback);
        return new static(compact('data'));
    }

    /**
     * Exports a `Collection` instance to an array. Used by `Collection::to()`.
     *
     * @param mixed $data Either a `Collection` instance, or an array representing a
     *              `Collection`'s internal state.
     * @param array $options Options used when converting `$data` to an array:
     *              - `'handlers'` _array_: An array where the keys are fully-namespaced class
     *              names, and the values are closures that take an instance of the class as a
     *              parameter, and return an array or scalar value that the instance represents.
     *
     * @return array Returns the value of `$data` as a pure PHP array, recursively converting all
     *               sub-objects and other values to their closest array or scalar equivalents.
     */
    public static function toArray($data, array $options = [])
    {
        $defaults = ['handlers' => []];
        $options += $defaults;
        $result = [];

        foreach ($data as $key => $item) {
            switch (true) {
                case is_array($item):
                    $result[$key] = static::toArray($item, $options);
                break;
                case (!is_object($item)):
                    $result[$key] = $item;
                break;
                case (isset($options['handlers'][$class = get_class($item)])):
                    $result[$key] = $options['handlers'][$class]($item);
                break;
                case ($item instanceof static):
                    $result[$key] = static::toArray($item, $options);
                break;
                case (method_exists($item, '__toString')):
                    $result[$key] = (string) $item;
                break;
                default:
                    $result[$key] = $item;
                break;
            }
        }
        return $result;
    }
}
