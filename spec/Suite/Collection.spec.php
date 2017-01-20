<?php
namespace Lead\Collection\Spec\Suite;

use InvalidArgumentException;
use Lead\Collection\Collection;

use Kahlan\Plugin\Double;

describe("Collection", function() {

    describe("->__construct()", function() {

        it("loads the data variable", function() {

            $collection = new Collection(['foo']);
            expect($collection[0])->toBe('foo');
            expect($collection)->toHaveLength(1);

        });

    });

    describe("->invoke()", function() {

        beforeEach(function() {
            $this->collection = new Collection();
            $class = Double::classname();

            allow($class)->toReceive('hello')->andReturn('world');

            for ($i = 0; $i < 5; $i++) {
                $this->collection[] = new $class();
            }
        });

        it("dispatches a method against all items in the collection", function() {

            foreach ($this->collection as $instance) {
                expect($instance)->toReceive('hello');
            }

            $result = $this->collection->invoke('hello');
            expect($result->values())->toBe(array_fill(0, 5, 'world'));

        });

    });

    describe("->each()", function() {

        it("applies a filter on a collection", function() {

            $collection = new Collection([1, 2, 3, 4, 5]);
            $filter = function($item) { return ++$item; };
            $result = $collection->each($filter);

            expect($result)->toBe($collection);
            expect($result->values())->toBe([2, 3, 4, 5, 6]);

        });

    });

    describe("->filter()", function() {

        it("extracts items from a collection according a filter", function() {

            $collection = new Collection(array_merge(
                array_fill(0, 10, 1),
                array_fill(0, 10, 2)
            ));

            $filter = function($item) { return $item === 1; };

            $result = $collection->filter($filter);
            expect($result)->toBeAnInstanceOf('Lead\Collection\Collection');
            expect($result->values())->toBe(array_fill(0, 10, 1));

        });

    });

    describe("->map()", function() {

        it("applies a Closure to a copy of all data in the collection", function() {

            $collection = new Collection([1, 2, 3, 4, 5]);
            $filter = function($item) { return ++$item; };
            $result = $collection->map($filter);

            expect($result)->not->toBe($collection);
            expect($result->values())->toBe([2, 3, 4, 5, 6]);

        });

    });

    describe("->reduce()", function() {

        it("reduces a collection down to a single value", function() {

            $collection = new Collection([1, 2, 3]);
            $filter = function($memo, $item) { return $memo + $item; };

            expect($collection->reduce($filter, 0))->toBe(6);
            expect($collection->reduce($filter, 1))->toBe(7);

        });

    });

    describe("->slice()", function() {

        it("extracts a slice of items", function() {

            $collection = new Collection([1, 2, 3, 4, 5]);
            $result = $collection->slice(2, 2);

            expect($result)->not->toBe($collection);
            expect($result->values())->toBe([3, 4]);

        });

    });

    describe("->sort()", function() {

        it("sorts a collection", function() {

            $collection = new Collection([5, 3, 4, 1, 2]);
            $result = $collection->sort();
            expect($result->values())->toBe([1, 2, 3, 4, 5]);

        });

        it("sorts a collection using a compare function", function() {

            $collection = new Collection(['Alan', 'Dave', 'betsy', 'carl']);
            $result = $collection->sort('strcasecmp');
            expect($result->values())->toBe(['Alan', 'betsy', 'carl', 'Dave']);

        });

        it("sorts a collection by keys", function() {

            $collection = new Collection([5 => 6, 3 => 7, 4 => 8, 1 => 9, 2 => 10]);
            $result = $collection->sort(null, 'ksort');
            expect($result->keys())->toBe([1, 2, 3, 4, 5]);

        });

        it("throws an exception if the sort function is not callable", function() {

            $closure = function() {
                $collection = new Collection([1, 2, 3, 4, 5]);
                $collection->sort(null, 'mysort');
            };

            expect($closure)->toThrow(new InvalidArgumentException("The passed parameter is not a valid sort function."));

        });

    });

    describe("->offsetExists()", function() {

        it("returns true if a element exist", function() {

            $collection = new Collection();
            $collection[] = 'foo';
            $collection[] = null;

            expect(isset($collection[0]))->toBe(true);
            expect(isset($collection[1]))->toBe(true);

        });

        it("returns false if a element doesn't exist", function() {

            $collection = new Collection();
            expect(isset($collection[0]))->toBe(false);

        });

    });

    describe("->offsetSet()/->offsetGet()", function() {

        it("allows array access", function() {

            $collection = new Collection();
            $collection[] = 'foo';
            expect($collection[0])->toBe('foo');
            expect($collection)->toHaveLength(1);

        });

        it("allows specific key", function() {

            $collection = new Collection();
            $collection['mykey'] = 'foo';
            expect($collection['mykey'])->toBe('foo');
            expect($collection)->toHaveLength(1);

        });

    });

    describe("->offsetUnset()", function() {

        it("unsets items", function() {

            $collection = new Collection([5, 3, 4, 1, 2]);
            unset($collection[1]);
            unset($collection[2]);

            expect($collection)->toHaveLength(3);
            expect($collection->values())->toBe([5, 1, 2]);

        });

        it("unsets items but keeps index", function() {

            $collection = new Collection([5, 3, 4, 1, 2]);
            unset($collection[1]);
            unset($collection[2]);

            expect($collection)->toHaveLength(3);
            expect($collection->values())->toBe([5, 1, 2]);
            expect($collection->keys())->toBe([0, 3, 4]);

        });


        it("unsets all items in a foreach", function() {

            $data = ['Delete me', 'Delete me'];
            $collection = new Collection(compact('data'));

            foreach ($collection as $i => $word) {
                unset($collection[$i]);
            }
            expect($collection->values())->toBe([]);

        });

        it("unsets last items in a foreach", function() {

            $data = ['Hello', 'Hello again!', 'Delete me'];
            $collection = new Collection($data);

            foreach ($collection as $i => $word) {
                if ($word === 'Delete me') {
                    unset($collection[$i]);
                }
            }
            expect($collection->values())->toBe(['Hello', 'Hello again!']);

        });

        it("unsets first items in a foreach", function() {

            $data = ['Delete me', 'Hello', 'Hello again!'];
            $collection = new Collection($data);

            foreach ($collection as $i => $word) {
                if ($word === 'Delete me') {
                    unset($collection[$i]);
                }
            }

            expect($collection->values())->toBe(['Hello', 'Hello again!']);

        });

        it("doesn't skip element in foreach", function() {

            $data = ['Delete me', 'Hello', 'Delete me', 'Hello again!'];
            $collection = new Collection($data);

            $loop = 0;
            foreach ($collection as $i => $word) {
                if ($word === 'Delete me') {
                    unset($collection[$i]);
                }
                $loop++;
            }

            expect($loop)->toBe(4);

        });

        it("resets skip hack on rewind", function() {

            $data = ['Delete me', 'Hello', 'Hello again!'];
            $collection = new Collection($data);
            unset($collection[0]);

            $result = [];
            foreach ($collection as $word) {
                $result[] = $word;
            }

            expect($result)->toBe(['Hello', 'Hello again!']);

        });

    });

    describe("->keys()", function() {

        it("returns the item keys", function() {

            $collection = new Collection([
                'key1' => 'one',
                'key2' => 'two',
                'key3' => 'three'
            ]);
            expect($collection->keys())->toBe(['key1', 'key2', 'key3']);

        });

    });

    describe("->values()", function() {

        it("returns the item values", function() {

            $collection = new Collection([
                'key1' => 'one',
                'key2' => 'two',
                'key3' => 'three'
            ]);
            expect($collection->values())->toBe(['one', 'two', 'three']);

        });

    });

    describe("->plain()", function() {

        it("returns the plain array", function() {

            $data = [
                'key1' => 'one',
                'key2' => 'two',
                'key3' => 'three'
            ];

            $collection = new Collection($data);
            expect($collection->plain())->toBe($data);
        });

    });

    describe("->data()", function() {

        it("delegates to `::toArray`", function() {

            $data = [
                'key1' => 'one',
                'key2' => 'two',
                'key3' => 'three'
            ];
            $collection = new Collection($data);

            expect('Lead\Collection\Collection')->toReceive('::toArray')->with($collection);
            $collection->data();
        });

    });

    describe("->key()", function() {

        it("returns current key", function() {

            $collection = new Collection([1, 2, 3, 4, 5]);
            $value = $collection->key();
            expect($value)->toBe(0);

        });

    });

    describe("->current()", function() {

        it("returns current value", function() {

            $collection = new Collection([1, 2, 3, 4, 5]);
            $value = $collection->current();
            expect($value)->toBe(1);

        });

    });

    describe("->prev()/->next()", function() {

        it("returns prev value", function() {

            $collection = new Collection([1, 2, 3]);
            $collection->rewind();
            expect($collection->next())->toBe(2);
            expect($collection->next())->toBe(3);
            expect($collection->next())->toBe(null);
            $collection->end();
            expect($collection->prev())->toBe(2);
            expect($collection->prev())->toBe(1);
            expect($collection->prev())->toBe(null);

        });

    });

    describe("->first()/->rewind()/->end()", function() {

        it("returns respectively the first and the last item of the collection", function() {

            $collection = new Collection([1, 2, 3, 4, 5]);
            expect($collection->end())->toBe(5);
            expect($collection->rewind())->toBe(1);
            expect($collection->end())->toBe(5);
            expect($collection->first())->toBe(1);

        });

    });

    describe("->valid()", function() {

        it("returns true only when the collection is valid", function() {

            $collection = new Collection();
            expect($collection->valid())->toBe(false);

            $collection = new Collection([1, 5]);
            expect($collection->valid())->toBe(true);

        });

    });

    describe("->count()", function() {

        it("returns 0 on empty", function() {

            $collection = new Collection();
            expect($collection)->toHaveLength(0);

        });

        it("returns the number of items in the collection", function() {

            $collection = new Collection([5 ,null, 4, true, false, 'bob']);
            expect($collection)->toHaveLength(6);

        });

    });

    describe("->merge()", function() {

        it("merges two collection with key preservation", function() {

            $collection = new Collection([1, 2, 3]);
            $collection2 = new Collection([4, 5, 6, 7]);
            $collection->merge($collection2);

            expect($collection->values())->toBe([4, 5, 6, 7]);

        });

    });

    describe("->append()", function() {

        it("appends two collection with no key preservation", function() {

            $collection = new Collection([1, 2, 3]);
            $collection2 = new Collection([4, 5, 6, 7]);
            $collection->append($collection2);

            expect($collection->values())->toBe([1, 2, 3, 4, 5, 6, 7]);

        });

    });

    describe("->formats()", function() {

        it("gets registered formats", function() {

            Collection::formats('json', function() {});
            expect(array_keys(Collection::formats()))->toBe(['array', 'json']);

        });

        it("removes a specific registered formats", function() {

            Collection::formats('json', function() {});
            Collection::formats('json', false);

            expect(array_keys(Collection::formats()))->toBe(['array']);

        });

        it("removes all registered formats", function() {

            Collection::formats('json', function() {});
            Collection::formats(false);

            expect(array_keys(Collection::formats()))->toBe(['array']);

        });
    });

    describe("->clear()", function() {

        it("clears up the collection", function() {

            $collection = new Collection([1, 2, 3]);
            expect($collection->values())->toBe([1, 2, 3]);

            $collection->clear();
            expect($collection->values())->toBe([]);

        });

    });


    describe("->to()", function() {

        it("converts a collection to an array", function() {

            $collection = new Collection([
                1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5
            ]);
            expect($collection->to('array', ['key' => false]))->toBe([1, 2, 3, 4, 5]);

        });

        it("converts a collection to an array by preserving keys", function() {

            $collection = new Collection([
                1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5
            ]);
            $result = $collection->to('array');
            expect($result)->toBe([1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5]);

        });

        it("converts according a registered closure", function() {

            $collection = new Collection([1, 2, 3]);
            Collection::formats('json', function($collection) {
                return json_encode($collection->to('array'));
            });

            expect($collection->to('json'))->toBe("[1,2,3]");

        });

        it("converts according a closure", function() {

            $collection = new Collection(['hello', 'world', '!']);
            $result = $collection->to(function($collection) {
                return join(' ', $collection->to('array'));
            });
            expect($result)->toBe('hello world !');

        });

        it("converts objects which support __toString", function() {

            $stringable = Double::classname();
            allow($stringable)->toReceive('__toString')->andReturn('hello');
            $collection = new Collection([new $stringable()]);

            expect($collection->to('array'))->toBe(['hello']);

        });

        it("converts objects using handlers", function() {

            $handlable = Double::classname();
            $handlers = [$handlable => function($value) { return 'world'; }];
            $collection = new Collection([new $handlable()]);

            expect($collection->to('array', compact('handlers')))->toBe(['world']);

        });

        it("doesn't convert unsupported objects", function() {

            $collection = new Collection([(object) 'an object']);
            expect($collection->to('array'))->toEqual([(object) 'an object']);

        });

        it("converts nested collections", function() {

            $collection = new Collection([1, 2, 3, new Collection([4, 5, 6])]);
            expect($collection->to('array'))->toBe([1, 2, 3, [4, 5, 6]]);

        });

        it("converts mixed nested collections & arrays", function() {

            $collection = new Collection([1, 2, 3, [new Collection([4, 5, 6])]]);
            expect($collection->to('array'))->toBe([1, 2, 3, [[4, 5, 6]]]);

        });

        it("throws an exception with unsupported format", function() {

            $closure = function() {
                $collection = new Collection();
                $collection->to('xml');
            };

            expect($closure)->toThrow(new InvalidArgumentException("Unsupported format `xml`."));

        });

    });

});
