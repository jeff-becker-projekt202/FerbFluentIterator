<?php

declare(strict_types=1);

namespace  Ferb\Iterators;

class FluentIterator implements \Iterator
{
    private $inner;
    private $is_array;
    private $index = 0;
    private static $nil;

    public function __construct($inner)
    {
        if (\is_callable($inner)) { //instanceof Closure
            $inner = $inner();
        }
        while ($inner instanceof FluentIterator) {
            $inner = $inner->inner;
        }
        if (null === $inner) {
            throw new InvalidArgumentException('The $inner iterator is not permitted to be null');
        }
        $this->inner = is_array($inner) ? array_values($inner) : $inner;
        $this->is_array = is_array($inner);
    }


    private function ensure_rewindable()
    {
        if($this->inner instanceof \Generator || $this->inner instanceof \NoRewindIterator){
            $arr = iterator_to_array($this->inner);
            $this->is_array = true;
            $this->inner = $arr;
            $this->index = 0;
        }
    }
    public static function range($base, $count){
        return new self( function() use ($base, $count){
            for ($i = 0; $i < $count; $i = $i+1) {
                yield $i + $base;
            }
        });
    }

    public static function from($inner)
    {
        return self::to_iterable($inner);
    }

    public static function none()
    {
        return new self([]);
    }

    public function current()
    {
        if ($this->is_array) {
            return current($this->inner);
        }

        return $this->inner->current();
    }

    public function key()
    {
        if ($this->is_array) {
            return key($this->inner);
        }

        return  $this->inner->key();
    }

    public function next()
    {
        ++$this->index;
        if ($this->is_array) {
            return next($this->inner);
        }

        $this->inner->next();
    }

    public function rewind()
    {
        $this->index = 0;
        if ($this->is_array) {
            return reset($this->inner);
        }

        $this->inner->rewind();
    }

    public function valid()
    {
        if ($this->is_array) {
            return null !== key($this->inner);
        }

        return  $this->inner->valid();
    }

    public function append(iterable $other)
    {
        return new self(function () use ($other) {
            foreach ($this as $item) {
                yield $item;
            }
            foreach ($other as $item) {
                yield $item;
            }
        });
    }

    public function prepend(iterable $other)
    {
        return new self(function () use ($other) {
            foreach ($other as $item) {
                yield $item;
            }
            foreach ($this as $item) {
                yield $item;
            }
        });
    }

    public function filter(callable $predicate = null)
    {
        return new self(function () use ($predicate) {
            $predicate = $predicate ?? function ($item) { return !empty($item); };

            foreach ($this as $item) {
                if ($predicate($item, $this->index)) {
                    yield $item;
                }
            }
        });
    }

    public function map(callable $projection)
    {
        return new self(function () use ($projection) {
            foreach ($this as $item) {
                yield $projection($item, $this->index);
            }
        });
    }

    public function flat()
    {
        return new self(function () {
            foreach ($this as $inner) {
                foreach ($inner as $item) {
                    yield $item;
                }
            }
        });
    }

    public function flat_map(callable $projection)
    {
        return $this->flat()->map($projection);
    }

    public function reduce(callable $reducer, $inital_value = null)
    {
        $accumulator = $inital_value;
        foreach ($this as $item) {
            $accumulator = $reducer($accumulator, $item);
        }

        return $accumulator;
    }

    public function every(callable $predicate = null)
    {
        $predicate = $predicate ?? function ($item) {
            return !empty($item);
        };
        foreach ($this as $item) {
            if (!$predicate($item)) {
                return false;
            }
        }

        return true;
    }

    public function some(callable $predicate)
    {
        foreach ($this as $item) {
            if ($predicate($item)) {
                return true;
            }
        }

        return false;
    }

    public function includes($value, callable $equality_comparitor = null)
    {
        $comparitor = $comparitor ?? function ($a, $b) {
            return $a == $b;
        };

        return $this->some(function ($x) use ($comparitor, $value) {
            return $comparitor($x, $value);
        });
    }

    public function group_by(callable $key_selector, callable $value_selector)
    {
        return new self(function () use ($key_selector, $value_selector) {
            $value_selector ?? function ($x) {return $x; };
            $groupings = [];
            foreach ($this as $item) {
                $key = $key_selector($item, $this->index);
                $value = $value_selector($item, $this->index);
                if (!isset($groupings[$key])) {
                    $groupings[$key] = [];
                }
                $groupings[$key][] = $item;
            }

            foreach ($groupings as $k => $v) {
                yield (object) [
                    'key' => $k,
                    'values' => $v,
                ];
            }
        });
    }

    private static function to_iterable(iterable $other)
    {
        if (!($other instanceof FluentIterator)) {
            return new self($other);
        }

        return $other;
    }

    public function sequence_equals(iterable $other, callable $comparitor)
    {
        $other = self::to_iterable($other);

        $this->rewind();
        $other->rewind();

        $self = $this;
        $all_valid = function () use ($self, $other) {
            return $self->valid() && $other->valid();
        };
        while ($all_valid()) {
            $cmp = $comparitor($this->current(), $other->current());
            if (0 != $cmp) {
                return false;
            }
            $this->next();
            $other->next();
        }
        //one sequence is longer
        if (($other->valid() && !$this->valid()) || ($this->valid && !$other->valid())) {
            return false;
        }

        return true;
    }

    public function zip(iterable $other, callable $projection = null)
    {
        $projection = $projection ?? function($a,$b){
            return [$a,$b];
        };
        return new self(function () use ($other, $projection) {
            $other = self::to_iterable($other);

            $this->rewind();
            $other->rewind();

            $self = $this;
            $all_valid = function () use ($self, $other) {
                return $self->valid() && $other->valid();
            };
            while ($all_valid()) {
                yield $projection($this->current(), $other->current());
                $this->next();
                $other->next();
            }
        });
    }

    public function skip_while(callable $predicate)
    {
        return new self(function () use ($predicate) {
            $skipping = true;
            foreach ($this as $item) {
                $skipping = $skipping ? $predicate($item, $this->index) : false;
                if (!$skipping) {
                    yield $item;
                }
            }
        });
    }

    public function skip(int $count)
    {
        return new self(function () use ($count) {
            return $this->skip_while(function ($item, $index) use ($count) {
                return $index < $count;
            });
        });
    }

    public function take_while(callable $predicate)
    {
        return new self(function () use ($predicate) {
            foreach($this as $value){
                if(!$predicate($value, $this->index)){
                    goto exit_loop;
                }
                else{
                    yield $value;
                }
               
            } 
            exit_loop:
            $i=0;
   
        });
    }

    public function take(int $count)
    {
        return new self(function () use ($count) {
            return $this->take_while(function ($item, $index) use ($count) {
                return $index < $count;
            });
        });
    }

    public function reverse()
    {
        return new self(array_reverse($this->to_array()));
    }

    public function unique(callable $comparitor = null)
    {
        return new self(function () use ($comparitor) {
            $comparitor = $comparitor ?? function($a,$b){
                return $a- $b;
            };
            $last_value = self::nil();
            $iter = $this->order_by_asc($comparitor);
            foreach ($iter as $item) {
                if($last_value === self::nil()){
                    yield $item;
                    $last_value = $item;
                }
                else{
                    $comp = $comparitor($item, $last_value);
                    if ( 0 !== $comp) {
                        yield $item;
                        $last_value = $item;
                    }
                }

            }
        });
    }

    public function union($other, callable $comparitor = null)
    {
        return $this->append($other)->unique($comparitor);
    }

    public function order_by_asc(callable $comparitor = null)
    {
        $comparitor = $comparitor ?? function ($a, $b) { return $a - $b; };
        $arr = $this->to_array();
        \uasort($arr, $comparitor);

        return new self($arr);
    }

    public function order_by_desc(callable $comparitor = null)
    {
        $comparitor = $comparitor ?? function ($a, $b) { return $a - $b; };
        $comparitor = function ($a, $b) use ($comparitor) {
            return -1 * $comparitor($a, $b);
        };

        return $this->order_by_asc($comparitor);
    }

    public function to_array(iterable $other = null)
    {
        if (null == $other) {
            if ($this->is_array) {
                return array_merge($this->inner);
            }

            return \iterator_to_array($this);
        }
        if ($other instanceof FluentIterator) {
            return $other->to_array();
        }

        return is_array($other) ? $other : \iterator_to_array($other);
    }

    public function intersect($other)
    {
        return new self(function () use ($other) {
            $hash = [];
            foreach ($other as $item) {
                $hash[$item] = $item;
            }
            foreach ($this as $item) {
                if (isset($hash[$item])) {
                    yield $item;
                }
            }
        });
    }

    public function diff($other)
    {
        return new self(function () use ($other) {
            $hash = [];
            foreach ($other as $item) {
                $hash[$item] = $item;
            }
            foreach ($this as $item) {
                if (!isset($hash[$item])) {
                    yield $item;
                }
            }
        });
    }

    public function average(string $by = 'mean')
    {
        if ('median' == $by) {
            $arr = $this->to_array();
            if (0 == count($arr)) {
                return false;
            }
            sort($arr);
            $idx = floor(count($arr) / 2);

            return $arr[$idx];
        }
        if ($by = 'mode') {
            $hist = [];
            foreach ($this as $item) {
                $hist[$item] = ($hist[$item] ?? 0) + 1;
            }
            $last = [0, 0];
            foreach ($hist as $value => $count) {
                if ($last[0] < $count) {
                    $last = [$count, $value];
                }
            }

            return $last[1];
        }
        $this-> ensure_rewindable();
        return $this->sum() / $this->count();
        return $total / $count;
    }

    public function element_at(int $index)
    {

        foreach ($this as $item) {
            if ($this->index === $index) {
                return $item;
            }
        }

        return null;
    }

    public function last()
    {
        foreach ($this as $item) {
        }

        return $item;
    }

    public function first()
    {
        return $this->element_at(0);
    }

    public function min(callable $comparitor = null)
    {
        $comparitor = $comparitor ?? function ($a, $b) {
            return $a - $b;
        };
        $result = self::nil();
        foreach ($this as $item) {
            if ($result === self::nil()) {
                $result = $item;
            } else {
                if ($comparitor($item, $result) < 0) {
                    $result = $item;
                }
            }
        }
        if ($result === self::nil()) {
            return null;
        }

        return $result;
    }

    public function max(callable $comparitor = null)
    {
        $comparitor = $comparitor ?? function ($a, $b) {
            return $a - $b;
        };

        $result = self::nil();
        foreach ($this as $item) {
            if ($result === self::nil()) {
                $result = $item;
            } else {
                if ($comparitor($item, $result) > 0) {
                    $result = $item;
                }
            }
        }
        if ($result === self::nil()) {
            return null;
        }

        return $result;
    }

    public function count()
    {
        return \iterator_count($this);
    }

    public function sum()
    {
        $total = 0;
        foreach ($this as $item) {
            $total += $item;
        }

        return $total;
    }

    public function to_dictionary(callable $key_selector = null, callable $value_selector = null)
    {
        $value_selector = $value_selector ?? function ($value, $index) {
            return $value;
        };
        $result = [];

        foreach ($this as $item) {
            $key = $key_selector($item);
            $result[$key] = $value_selector($item, $this->index);
        }

        return $result;
    }

    private static function nil()
    {
        if (null == self::$nil) {
            self::$nil = new \stdClass();
        }

        return self::$nil;
    }
}
