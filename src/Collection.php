<?php
namespace collection;

use InvalidArgumentException;

/**
 * `Collection` class.
 *
 * Example of usage:
 * ```php
 * $collection = new Collection();
 * $collection[] = 'foo';
 * // $collection[0] --> 'foo'
 *
 * $collection = new Collection(['foo']);
 * // $collection[0] --> 'foo'
 *
 * $array = iterator_to_array($collection);
 * ```
 *
 * Apart from array-like data access, `Collection`s enable terse and expressive
 * filtering and iteration:
 *
 * ```php
 * $collection = new Collection([0, 1, 2, 3, 4]);
 *
 * $collection->first();   // 0
 * $collection->current(); // 0
 * $collection->next();    // 1
 * $collection->next();    // 2
 * $collection->next();    // 3
 * $collection->prev();    // 2
 * $collection->rewind();  // 0
 * ```
 *
 * The purpose of the `Collection` class is to enable simple, efficient access to groups
 * of similar objects, and to perform operations against these objects using anonymous functions.
 *
 * The `map()` and `each()` methods allow you to perform operations against the entire set of values
 * in a `Collection`, while `find()` and `first()` allow you to search through values and pick out
 * one or more.
 *
 * The `Collection` class also supports dispatching methods against a set of objects, if the method
 * is supported by all objects. For example:
 *
 * ```php
 * class Task {
 *     public function run($when) {
 *         // Do some work
 *     }
 * }
 *
 * $tasks = new Collection([
 *     new Task(['id' => 'task1']),
 *     new Task(['id' => 'task2']),
 *     new Task(['id' => 'task3'])
 * ]);
 *
 * // $result will contain an array, and each element will be the return
 * // value of a run() method call:
 * $result = $tasks->invoke('run', ['now']);
 * ```
 *
 */
class Collection implements \ArrayAccess, \Iterator, \Countable
{
    /**
     * Contains all exportable formats and their handler
     *
     * @var array
     */
    protected static $_formats = [
        'array' => 'collection\Collection::toArray'
    ];

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
     * ```php
     * $collection = new Collection(['1', '2', '3']);
     * unset($collection[0]);
     * $collection->next();   // returns 2 instead of 3
     * ```
     */
    protected $_skipNext = false;

    /**
     * The constructor
     *
     * @param array $data The data
     */
    public function __construct($data = [])
    {
        $this->_data = $data;
    }

    /**
     * Accessor method for adding format handlers to `Collection` instances.
     *
     * The values assigned are used by `Collection::to()` to convert `Collection` instances into
     * different formats, i.e. JSON.
     *
     * This can be accomplished in two ways. First, format handlers may be registered on a
     * case-by-case basis, as in the following:
     *
     * ```php
     * Collection::formats('json', function($collection, $options) {
     *  return json_encode($collection->to('array'));
     * });
     *
     * // You can also implement the above as a static class method, and register it as follows:
     * Collection::formats('json', 'my\custom\Formatter::toJson');
     * ```
     *
     * @see    collection\Collection::to()
     * @param  string $format  A string representing the name of the format that a `Collection`
     *                         can be converted to. If `false`, reset the `$_formats` attribute.
     *                         If `null` return the content of the `$_formats` attribute.
     * @param  mixed  $handler The function that handles the conversion, either an anonymous function,
     *                         a fully namespaced class method or `false` to remove the `$format` handler.
     * @return mixed
     */
    public static function formats($format = null, $handler = null) {
        if ($format === null) {
            return static::$_formats;
        }
        if ($format === false) {
            return static::$_formats = ['array' => 'collection\Collection::toArray'];
        }
        if ($handler === false) {
            unset(static::$_formats[$format]);
            return;
        }
        return static::$_formats[$format] = $handler;
    }

    /**
     * Handles dispatching of methods against all items in the collection.
     *
     * @param  string $method The name of the method to call on each instance in the collection.
     * @param  mixed  $params The parameters to pass on each method call.
     * @return mixed          Returns either an array of the return values of the methods, or the
     *                        return values wrapped in a `Collection` instance.
     */
    public function invoke($method, $params = [])
    {
        $data = [];
        $isCallable = is_callable($params);

        foreach ($this as $key => $object) {
            $callParams = $isCallable ? $params($object, $key, $this) : $params;
            $data[$key] = call_user_func_array([$object, $method], $callParams);
        }

        return new static($data);
    }

    /**
     * Checks whether or not an offset exists.
     *
     * @param  string  $offset An offset to check for.
     * @return boolean         Returns `true` if offset exists, `false` otherwise.
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->_data);
    }

    /**
     * Returns the value at specified offset.
     *
     * @param  string $offset The offset to retrieve.
     * @return mixed          The value at offset.
     */
    public function offsetGet($offset)
    {
        return $this->_data[$offset];
    }

    /**
     * Assigns a value to the specified offset.
     *
     * @param  string $offset The offset to assign the value to.
     * @param  mixed  $value  The value to set.
     * @return mixed          The value which was set.
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
     * @param  mixed   $collection   A collection.
     * @param  boolean $preserveKeys If `true` use the key value as a hash to avoid duplicates.
     * @return object                Return the merged collection.
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
     * Returns the `$_data` attribute of the collection.
     *
     * @return array
     */
    public function plain()
    {
        return $this->_data;
    }

    /**
     * Exports the collection as an array.
     *
     * @return array
     */
    public function data()
    {
        return static::toArray($this);
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
     * Moves backward to the previous item.
     *
     * @return mixed The previous item.
     */
    public function prev()
    {
        $value = prev($this->_data);
        return key($this->_data) !== null ? $value : null;
    }

    /**
     * Moves forward to the next item.
     *
     * @return mixed The next item.
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
     * @return mixed The first item.
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
     * @return mixed The last item.
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
     * @param  mixed $filter The callback to use for filtering, or an array of key/value pairs to match.
     * @return mixed         A collection of the filtered items.
     */
    public function find($filter)
    {
        $callback = is_array($filter) ? $this->_filterFromArray($filter) : $filter;
        $data = array_filter($this->_data, $callback);
        return new static($data);
    }

    /**
     * Applies a callback to all items in the collection.
     *
     * @param  callback $callback The callback to apply.
     * @return object             This collection instance.
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
     * @param  callback $callback The callback to apply.
     * @return mixed              The set of filtered values inside a `Collection`.
     */
    public function map($callback)
    {
        $data = array_map($callback, $this->_data);
        return new static($data);
    }

    /**
     * Reduce, or fold, a collection down to a single value
     *
     * @param  callback $filter  The filter to apply.
     * @param  mixed    $initial Initial value.
     * @return mixed             A single reduced value.
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
     * @param  integer $offset The offset value.
     * @param  integer $length The number of element to extract
     * @return array
     */
    public function slice($offset, $length = null, $preserveKeys = true) {
        $data = array_slice($this->_data, $offset, $length, $preserveKeys);
        return new static($data);
    }

    /**
     * Sorts the objects in the collection.
     *
     * @param  callback $callback A compare function like strcmp or a custom closure. The
     *                            comparison function must return an integer less than, equal to, or
     *                            greater than zero if the first argument is considered to be respectively
     *                            less than, equal to, or greater than the second.
     * @param  string   $sorter   The type of sorting, can be any sort function like `asort`,
     *                            'uksort', or `natsort`.
     * @return object             The collection instance.
     */
    public function sort($callback = null, $sorter = null) {
        if (!$sorter) {
            $sorter = $callback === null ? 'sort' : 'usort';
        }
        if (!is_callable($sorter)) {
            throw new InvalidArgumentException("The passed parameter is not a valid sort function.");
        }
        $callback === null ? $sorter($this->_data) : $sorter($this->_data, $callback);
        return $this;
    }

    /**
     * Exports a `Collection` object to another format.
     *
     * The supported values of `$format` depend on the registered handlers.
     *
     * Once the appropriate handlers are registered, a `Collection` instance can be converted into
     * any handler-supported format, i.e.:
     *
     * ```php
     * $collection->to('json'); // returns a JSON string
     * $collection->to('xml'); // returns an XML string
     * ```
     *
     * @see    collection\Collection::formats()
     * @param  string $format  By default the only supported value is `'array'`. However, additional
     *                         format handlers can be registered using the `formats()` method.
     * @param  array  $options Options for converting the collection.
     * @return mixed           The converted collection.
     */
    public function to($format, $options = []) {
        if (!is_string($format) || !isset(static::$_formats[$format])) {
            if (is_callable($format)) {
                return $format($this, $options);
            }
            throw new InvalidArgumentException("Unsupported format `{$format}`.");
        }
        $handler = static::$_formats[$format];
        return is_string($handler) ? call_user_func($handler, $this, $options) : $handler($this, $options);
    }

    /**
     * Exports a `Collection` instance to an array.
     *
     * @param  mixed $data    Either a `Collection` instance, or an array representing a
     *                        `Collection`'s internal state.
     * @param  array $options Options used when converting `$data` to an array:
     *                        - `'key'`      _boolean_: A boolean indicating if keys must be conserved or not.
     *                        - `'handlers'` _array_  : An array where the keys are fully-namespaced class
     *                        names, and the values are closures that take an instance of the class as a
     *                        parameter, and return an array or scalar value that the instance represents.
     * @return array          The value of `$data` as a pure PHP array, recursively converting all
     *                        sub-objects and other values to their closest array or scalar equivalents.
     */
    public static function toArray($data, $options = [])
    {
        $defaults = ['key' => true, 'handlers' => []];
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
        return $options['key'] ? $result : array_values($result);
    }
}
