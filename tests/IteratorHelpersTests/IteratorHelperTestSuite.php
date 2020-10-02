<?php

declare(strict_types=1);

namespace Ferb\Iterators\IteratorHelperTests\Tests;

use Ferb\Iterators\FluentIterator;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class IteratorHelperTestSuite extends TestCase
{
    public static function range($base, $count){
        for($i = 0; $i< $count; $i++ ){
            yield $i+$base;
        }
    }
    public function testAppend()
    {
        $result = FluentIterator::from([1, 2])->append([3, 4]);
        $this->assertSequenceEquals([1, 2, 3, 4], $result);
    }

    public function testPrepend()
    {
        $result = FluentIterator::from([1, 2])->prepend([3, 4]);
        $this->assertSequenceEquals([3, 4, 1, 2], $result);
    }

    public function testFilter()
    {
        $result = FluentIterator::from([1, 2, 3, 4])->filter(function ($x) {
            return 0 == $x % 2;
        });
        $this->assertSequenceEquals([2, 4], $result);
    }

    public function testMap()
    {
        $result = FluentIterator::from([1, 2, 3, 4])->map(function ($x) {
            return $x * 2;
        });
        $this->assertSequenceEquals([2, 4, 6, 8], $result);
    }

    public function testFlatMap()
    {
        $result = FluentIterator::from([[1, 2], [3, 4]])->flat_map(function ($x) {
            return $x * 2;
        });
        $this->assertSequenceEquals([2, 4, 6, 8], $result);
    }

    public function testFlat()
    {
        $result = FluentIterator::from([[1, 2], [3, 4]])->flat();
        $this->assertSequenceEquals([1, 2, 3, 4], $result);
    }

    public function testReduce()
    {
        $result = FluentIterator::from([1, 2, 3, 4])->reduce(function ($a, $x) {
            return $a + $x;
        }, 0);
        $this->assertEquals(10, $result);
    }

    public function testEveryTrue()
    {
        $result = FluentIterator::from([1, 2, 3, 4])->every(function ($x) {
            return $x < 10;
        });
        $this->assertEquals(true, $result);
    }

    public function testEveryFalse()
    {
        $result = FluentIterator::from([1, 2, 3, 4, 15])->every(function ($x) {
            return $x < 10;
        });
        $this->assertEquals(false, $result);
    }

    public function testSomeTrue()
    {
        $result = FluentIterator::from([1, 2, 3, 4])->some(function ($x) {
            return $x < 10;
        });
        $this->assertEquals(true, $result);
    }

    public function testSomeFalse()
    {
        $result = FluentIterator::from([1, 2, 3, 4, 15])->some(function ($x) {
            return $x > 20;
        });
        $this->assertEquals(false, $result);
    }

    public function testIncludesTrue()
    {
        $result = FluentIterator::from([1, 2, 3, 4])->includes(3);
        $this->assertEquals(true, $result);
    }

    public function testIncludesFalse()
    {
        $result = FluentIterator::from([1, 2, 3, 4, 15])->includes(20);
        $this->assertEquals(false, $result);
    }

    public function testGroupBy()
    {
        $result = FluentIterator::from([1, 2, 3, 4, 15])->group_by(function ($x) {
            return $x % 2;
        }, function ($x) {
            return $x * 2;
        })->to_dictionary(function ($x) {
            return $x->key;
        }, function ($x) {
            return $x->values;
        });

        $this->assertEquals([
            0 => [2, 4],
            1 => [1, 3, 15],
        ], $result);
    }

    public function testZip(){
        $result = FluentIterator::from([1,2,3,4,5]) ->zip([2,4,6,8]);
        $this->assertSequenceEquals([
            [1,2],
            [2,4],
            [3,6],
            [4,8]
        ], $result);
    }
    public function testTakeWhile(){
        $result = FluentIterator::range(1,20)
        ->take_while(function($x){
            return $x < 10;
        })->to_array();
        $expected = FluentIterator::range(1,9);
        $this->assertSequenceEquals($expected, $result);
    }

    public function testTake(){
        $result = FluentIterator::range(0,20)
        ->take(10)->to_array();
        $expected = FluentIterator::range(0,10);
        $this->assertSequenceEquals($expected, $result);
    }



    public function testSkip(){
        $result = FluentIterator::range(0,20)
        ->skip(10)->to_array();
        $expected = FluentIterator::range(10,10);
        $this->assertSequenceEquals($expected, $result);
    }


    public function testSkipWhile(){
        $result = FluentIterator::range(0,20)
        ->skip_while(function($x){
            return $x < 10;
        })->to_array();
        $expected = FluentIterator::range(10,10);
        $this->assertSequenceEquals($expected, $result);
    }



    public function testReverse(){
        $result = FluentIterator::range(1,10)
        ->reverse();
        $this->assertSequenceEquals(
            [10,9,8,7,6,5,4,3,2,1],
            $result
        );
    }

    
    public function testUnique(){
        $result = FluentIterator::from([1,2,3,4,5,1,2,3,4,5])
        ->unique()->to_array();
        $this->assertSequenceEquals(
            [1,2,3,4,5],
            $result
        );
    }

    public function testUnion()
    {
        $result = FluentIterator::range(0,10)
        ->union(FluentIterator::range(5,10))
        ->to_array();
        $this->assertSequenceEquals(
            [0,1,2,3,4,5,6,7,8,9,10,11,12,13,14],
            $result
        );
    }

    public function testOrderByAsc()
    {
        $result = FluentIterator::range(0,10)->reverse()
        ->order_by_asc();
        $this->assertSequenceEquals(
            [0,1,2,3,4,5,6,7,8,9],
            $result
        );
    }
    public function testOrderByDesc()
    {
        $result = FluentIterator::range(0,10)
        ->order_by_desc();
        $this->assertSequenceEquals(
            FluentIterator::range(0,10)->reverse(),
            $result
        );
    }


    public function testIntersect()
    {
        $result = FluentIterator::range(0,10)
        ->intersect(FluentIterator::range(5,15));
        $this->assertSequenceEquals(
            FluentIterator::range(5,5),
            $result
        );
    }
    public function testDiff()
    {
        $result = FluentIterator::range(0,10)
        ->diff(FluentIterator::range(5,15));
        $this->assertSequenceEquals(
            FluentIterator::range(0,5),
            $result
        );
    }

    public function testElementAt()
    {
        $range = FluentIterator::range(0,10);
        $result = $range->element_at(3);
        $this->assertEquals(3,$result);
    }

    public function testFirst()
    {
        $result = FluentIterator::range(0,10)->first();
        $this->assertEquals(0,$result);
    }

    public function testLast()
    {
        $result = FluentIterator::range(0,10)->last();
        $this->assertEquals(9,$result);
    }


    public function testMin()
    {
        $result = FluentIterator::range(0,10)->min();
        $this->assertEquals(0,$result);
    }

    public function testMax()
    {
        $result = FluentIterator::range(0,10)->max();
        $this->assertEquals(9,$result);
    }

    public function testCount()
    {
        $result = FluentIterator::range(0,10)->count();
        $this->assertEquals(10,$result);
    }

    public function testSum()
    {
        $result = FluentIterator::range(0,10)->max();
        $this->assertEquals(9,$result);
    }



    public function assertSequenceEquals($expected, $actual, $message = '')
    {
        $expected = is_array($expected) ? $expected : \iterator_to_array($expected);
        $actual = is_array($actual) ? $actual : \iterator_to_array($actual);
        return $this->assertEquals($expected, $actual , $message);
    }

}
