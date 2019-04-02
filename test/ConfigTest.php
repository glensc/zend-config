<?php
/**
 * @see       https://github.com/zendframework/zend-config for the canonical source repository
 * @copyright Copyright (c) 2005-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-config/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Config;

use PHPUnit\Framework\TestCase;
use Zend\Config\Config;
use Zend\Config\Exception;

/**
 * @group      Zend_Config
 */
class ConfigTest extends TestCase
{
    protected $iniFileConfig;
    protected $iniFileNested;
    /** @var array */
    private $all;
    /** @var array */
    private $numericData;
    /** @var array */
    private $menuData1;
    /** @var array */
    private $toCombineA;
    /** @var array */
    private $toCombineB;
    /** @var array */
    private $leadingdot;
    /** @var array */
    private $invalidkey;

    public function setUp()
    {
        // Arrays representing common config configurations
        $this->all = [
            'hostname' => 'all',
            'name' => 'thisname',
            'db' => [
                'host' => '127.0.0.1',
                'user' => 'username',
                'pass' => 'password',
                'name' => 'live'
                ],
            'one' => [
                'two' => [
                    'three' => 'multi'
                    ]
                ]
            ];

        $this->numericData = [
             0 => 34,
             1 => 'test',
            ];

        $this->menuData1 = [
            'button' => [
                'b0' => [
                    'L1' => 'button0-1',
                    'L2' => 'button0-2',
                    'L3' => 'button0-3'
                ],
                'b1' => [
                    'L1' => 'button1-1',
                    'L2' => 'button1-2'
                ],
                'b2' => [
                    'L1' => 'button2-1'
                    ]
                ]
            ];

        $this->toCombineA = [
            'foo' => 1,
            'bar' => 2,
            'text' => 'foo',
            'numerical' => [
                'first',
                'second',
                [
                    'third'
                ]
            ],
            'misaligned' => [
                2 => 'foo',
                3 => 'bar'
            ],
            'mixed' => [
                'foo' => 'bar'
            ],
            'replaceAssoc' => [
                'foo' => 'bar'
            ],
            'replaceNumerical' => [
                'foo'
            ]
        ];

        $this->toCombineB = [
            'foo' => 3,
            'text' => 'bar',
            'numerical' => [
                'fourth',
                'fifth',
                [
                    'sixth'
                ]
            ],
            'misaligned' => [
                3 => 'baz'
            ],
            'mixed' => [
                false
            ],
            'replaceAssoc' => null,
            'replaceNumerical' => true
        ];

        $this->leadingdot = ['.test' => 'dot-test'];
        $this->invalidkey = [' ' => 'test', '' => 'test2'];
    }

    public function testLoadSingleSection()
    {
        $config = new Config($this->all, false);

        $this->assertEquals('all', $config->hostname);
        $this->assertEquals('live', $config->db->name);
        $this->assertEquals('multi', $config->one->two->three);
        $this->assertNull($config->nonexistent); // property doesn't exist
    }

    public function testIsset()
    {
        $config = new Config($this->all, false);

        $this->assertFalse(isset($config->notarealkey));
        $this->assertTrue(isset($config->hostname)); // top level
        $this->assertTrue(isset($config->db->name)); // one level down
    }

    public function testModification()
    {
        $config = new Config($this->all, true);

        // overwrite an existing key
        $this->assertEquals('thisname', $config->name);
        $config->name = 'anothername';
        $this->assertEquals('anothername', $config->name);

        // overwrite an existing multi-level key
        $this->assertEquals('multi', $config->one->two->three);
        $config->one->two->three = 'anothername';
        $this->assertEquals('anothername', $config->one->two->three);

        // create a new multi-level key
        $config->does = ['not' => ['exist' => 'yet']];
        $this->assertEquals('yet', $config->does->not->exist);
    }

    public function testNoModifications()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage('Config is read only');
        $config = new Config($this->all);
        $config->hostname = 'test';
    }

    public function testNoNestedModifications()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage('Config is read only');
        $config = new Config($this->all);
        $config->db->host = 'test';
    }

    public function testNumericKeys()
    {
        $data = new Config($this->numericData);
        $this->assertEquals('test', $data->{1});
        $this->assertEquals(34, $data->{0});
    }

    public function testCount()
    {
        $data = new Config($this->menuData1);
        $this->assertCount(3, $data->button);
    }

    public function testCountAfterMerge()
    {
        $data = new Config($this->toCombineB);
        $data->merge(
            new Config($this->toCombineA)
        );
        $this->assertEquals(count($data->toArray()), $data->count());
    }

    public function testCountWithDoubleKeys()
    {
        $config = new Config([], true);

        $config->foo = 1;
        $config->foo = 2;
        $this->assertSame(2, $config->foo);
        $this->assertCount(1, $config->toArray());
        $this->assertCount(1, $config);
    }

    public function testIterator()
    {
        // top level
        $config = new Config($this->all);
        $var = '';
        foreach ($config as $key => $value) {
            if (is_string($value)) {
                $var .= "\nkey = $key, value = $value";
            }
        }
        $this->assertContains('key = name, value = thisname', $var);

        // 1 nest
        $var = '';
        foreach ($config->db as $key => $value) {
            $var .= "\nkey = $key, value = $value";
        }
        $this->assertContains('key = host, value = 127.0.0.1', $var);

        // 2 nests
        $config = new Config($this->menuData1);
        $var = '';
        foreach ($config->button->b1 as $key => $value) {
            $var .= "\nkey = $key, value = $value";
        }
        $this->assertContains('key = L1, value = button1-1', $var);
    }

    public function testArray()
    {
        $config = new Config($this->all);

        ob_start();
        print_r($config->toArray());
        $contents = ob_get_contents();
        ob_end_clean();

        $this->assertContains('Array', $contents);
        $this->assertContains('[hostname] => all', $contents);
        $this->assertContains('[user] => username', $contents);
    }

    public function testErrorWriteToReadOnly()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage('Config is read only');
        $config = new Config($this->all);
        $config->test = '32';
    }

    public function testZF343()
    {
        $config_array = [
            'controls' => [
                'visible' => [
                    'name' => 'visible',
                    'type' => 'checkbox',
                    'attribs' => [], // empty array
                ],
            ],
        ];
        $form_config = new Config($config_array, true);
        $this->assertSame([], $form_config->controls->visible->attribs->toArray());
    }

    public function testZF402()
    {
        $configArray = [
            'data1'  => 'someValue',
            'data2'  => 'someValue',
            'false1' => false,
            'data3'  => 'someValue'
            ];
        $config = new Config($configArray);
        $this->assertEquals(count($configArray), count($config));
        foreach ($config as $key => $value) {
            $this->assertEquals($configArray[$key], $value);
        }
    }

    public function testZf1019HandlingInvalidKeyNames()
    {
        $config = new Config($this->leadingdot);
        $array = $config->toArray();
        $this->assertContains('dot-test', $array['.test']);
    }

    public function testZF1019EmptyKeys()
    {
        $config = new Config($this->invalidkey);
        $array = $config->toArray();
        $this->assertContains('test', $array[' ']);
        $this->assertContains('test', $array['']);
    }

    public function testZF1417DefaultValues()
    {
        $config = new Config($this->all);
        $value = $config->get('notthere', 'default');
        $this->assertEquals('default', $value);
        $this->assertNull($config->notThere);
    }

    public function testUnsetException()
    {
        // allow modifications is off - expect an exception
        $config = new Config($this->all, false);

        $this->assertTrue(isset($config->hostname)); // top level

        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('is read only');
        unset($config->hostname);
    }

    public function testUnset()
    {
        // allow modifications is on
        $config = new Config($this->all, true);

        $this->assertTrue(isset($config->hostname));
        $this->assertTrue(isset($config->db->name));

        unset($config->hostname);
        unset($config->db->name);

        $this->assertFalse(isset($config->hostname));
        $this->assertFalse(isset($config->db->name));
    }

    public function testMerge()
    {
        $configA = new Config($this->toCombineA);
        $configB = new Config($this->toCombineB);
        $configA->merge($configB);

        // config->
        $this->assertEquals(3, $configA->foo);
        $this->assertEquals(2, $configA->bar);
        $this->assertEquals('bar', $configA->text);

        // config->numerical-> ...
        $this->assertInstanceOf('\Zend\Config\Config', $configA->numerical);
        $this->assertEquals('first', $configA->numerical->{0});
        $this->assertEquals('second', $configA->numerical->{1});

        // config->numerical->{2}-> ...
        $this->assertInstanceOf('\Zend\Config\Config', $configA->numerical->{2});
        $this->assertEquals('third', $configA->numerical->{2}->{0});
        $this->assertEquals(null, $configA->numerical->{2}->{1});

        // config->numerical->  ...
        $this->assertEquals('fourth', $configA->numerical->{3});
        $this->assertEquals('fifth', $configA->numerical->{4});

        // config->numerical->{5}
        $this->assertInstanceOf('\Zend\Config\Config', $configA->numerical->{5});
        $this->assertEquals('sixth', $configA->numerical->{5}->{0});
        $this->assertEquals(null, $configA->numerical->{5}->{1});

        // config->misaligned
        $this->assertInstanceOf('\Zend\Config\Config', $configA->misaligned);
        $this->assertEquals('foo', $configA->misaligned->{2});
        $this->assertEquals('bar', $configA->misaligned->{3});
        $this->assertEquals('baz', $configA->misaligned->{4});
        $this->assertEquals(null, $configA->misaligned->{0});

        // config->mixed
        $this->assertInstanceOf('\Zend\Config\Config', $configA->mixed);
        $this->assertEquals('bar', $configA->mixed->foo);
        $this->assertFalse($configA->mixed->{0});
        $this->assertNull($configA->mixed->{1});

        // config->replaceAssoc
        $this->assertNull($configA->replaceAssoc);

        // config->replaceNumerical
        $this->assertTrue($configA->replaceNumerical);
    }

    public function testArrayAccess()
    {
        $config = new Config($this->all, true);

        $this->assertEquals('thisname', $config['name']);
        $config['name'] = 'anothername';
        $this->assertEquals('anothername', $config['name']);
        $this->assertEquals('multi', $config['one']['two']['three']);

        $this->assertTrue(isset($config['hostname']));
        $this->assertTrue(isset($config['db']['name']));

        unset($config['hostname']);
        unset($config['db']['name']);

        $this->assertFalse(isset($config['hostname']));
        $this->assertFalse(isset($config['db']['name']));
    }

    public function testArrayAccessModification()
    {
        $config = new Config($this->numericData, true);

        // Define some values we'll be using
        $poem = [
            'poem' => [
                'line 1' => 'Roses are red, bacon is also red,',
                'line 2' => 'Poems are hard,',
                'line 3' => 'Bacon.',
            ],
        ];

        $bacon = 'Bacon';

        // Add a value
        $config[] = $bacon;

        // Check if bacon now has a key that equals to 2
        $this->assertEquals($bacon, $config[2]);

        // Now let's try setting an array with no key supplied
        $config[] = $poem;

        // This should now be set with key 3
        $this->assertEquals($poem, $config[3]->toArray());
    }

    /**
     * Ensures that toArray() supports objects of types other than Zend_Config
     *
     * @return void
     */
    public function testToArraySupportsObjects()
    {
        $configData = [
            'a' => new \stdClass(),
            'b' => [
                'c' => new \stdClass(),
                'd' => new \stdClass()
                ]
            ];
        $config = new Config($configData);
        $this->assertEquals($config->toArray(), $configData);
        $this->assertInstanceOf('stdClass', $config->a);
        $this->assertInstanceOf('stdClass', $config->b->c);
        $this->assertInstanceOf('stdClass', $config->b->d);
    }

    /**
     * ensure that modification is not allowed after calling setReadOnly()
     *
     */
    public function testSetReadOnly()
    {
        $configData = [
            'a' => 'a'
            ];
        $config = new Config($configData, true);
        $config->b = 'b';

        $config->setReadOnly();
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage('Config is read only');
        $config->c = 'c';
    }

    public function testZF3408countNotDecreasingOnUnset()
    {
        $configData = [
            'a' => 'a',
            'b' => 'b',
            'c' => 'c',
            ];
        $config = new Config($configData, true);
        $this->assertCount(3, $config);
        unset($config->b);
        $this->assertCount(2, $config);
    }

    public function testZF4107ensureCloneDoesNotKeepNestedReferences()
    {
        $parent = new Config(['key' => ['nested' => 'parent']], true);
        $newConfig = clone $parent;
        $newConfig->merge(new Config(['key' => ['nested' => 'override']], true));

        $this->assertEquals('override', $newConfig->key->nested, '$newConfig is not overridden');
        $this->assertEquals('parent', $parent->key->nested, '$parent has been overridden');
    }

    /**
     * @group ZF-3575
     *
     */
    public function testMergeHonoursAllowModificationsFlagAtAllLevels()
    {
        $config = new Config(['key' => ['nested' => 'yes'], 'key2' => 'yes'], false);
        $config2 = new Config([], true);

        $config2->merge($config);

        $config2->key2 = 'no';

        $this->assertEquals('no', $config2->key2);

        $config2->key->nested = 'no';

        $this->assertEquals('no', $config2->key->nested);
    }

    /**
     * @group ZF-5771a
     *
     */
    public function testUnsettingFirstElementDuringForeachDoesNotSkipAnElement()
    {
        $config = new Config([
            'first'  => [1],
            'second' => [2],
            'third'  => [3]
        ], true);

        $keyList = [];
        foreach ($config as $key => $value) {
            $keyList[] = $key;
            if ($key == 'first') {
                unset($config->$key); // uses magic Zend\Config\Config::__unset() method
            }
        }

        $this->assertEquals('first', $keyList[0]);
        $this->assertEquals('second', $keyList[1]);
        $this->assertEquals('third', $keyList[2]);
    }

    /**
     * @group ZF-5771
     *
     */
    public function testUnsettingAMiddleElementDuringForeachDoesNotSkipAnElement()
    {
        $config = new Config([
            'first'  => [1],
            'second' => [2],
            'third'  => [3]
        ], true);

        $keyList = [];
        foreach ($config as $key => $value) {
            $keyList[] = $key;
            if ($key == 'second') {
                unset($config->$key); // uses magic Zend\Config\Config::__unset() method
            }
        }

        $this->assertEquals('first', $keyList[0]);
        $this->assertEquals('second', $keyList[1]);
        $this->assertEquals('third', $keyList[2]);
    }

    /**
     * @group ZF-5771
     *
     */
    public function testUnsettingLastElementDuringForeachDoesNotSkipAnElement()
    {
        $config = new Config([
            'first'  => [1],
            'second' => [2],
            'third'  => [3]
        ], true);

        $keyList = [];
        foreach ($config as $key => $value) {
            $keyList[] = $key;
            if ($key == 'third') {
                unset($config->$key); // uses magic Zend\Config\Config::__unset() method
            }
        }

        $this->assertEquals('first', $keyList[0]);
        $this->assertEquals('second', $keyList[1]);
        $this->assertEquals('third', $keyList[2]);
    }

    /**
     * @group ZF-4728
     *
     */
    public function testSetReadOnlyAppliesToChildren()
    {
        $config = new Config($this->all, true);

        $config->setReadOnly();
        $this->assertTrue($config->isReadOnly());
        $this->assertTrue($config->one->isReadOnly(), 'First level children are writable');
        $this->assertTrue($config->one->two->isReadOnly(), 'Second level children are writable');
    }

    public function testZF6995toArrayDoesNotDisturbInternalIterator()
    {
        $config = new Config(range(1, 10));
        $config->rewind();
        $this->assertEquals(1, $config->current());

        $config->toArray();
        $this->assertEquals(1, $config->current());
    }

    /**
     * @depends testMerge
     * @link http://framework.zend.com/issues/browse/ZF2-186
     */
    public function testZF2186mergeReplacingUnnamedConfigSettings()
    {
        $arrayA = [
            'flag' => true,
            'text' => 'foo',
            'list' => [ 'a', 'b', 'c' ],
            'aSpecific' => 12
        ];

        $arrayB = [
            'flag' => false,
            'text' => 'bar',
            'list' => [ 'd', 'e' ],
            'bSpecific' => 100
        ];

        $mergeResult = [
            'flag' => false,
            'text' => 'bar',
            'list' => [ 'a', 'b', 'c', 'd', 'e' ],
            'aSpecific' => 12,
            'bSpecific' => 100
        ];

        $configA = new Config($arrayA);
        $configB = new Config($arrayB);

        $configA->merge($configB); // merge B onto A
        $this->assertEquals($mergeResult, $configA->toArray());
    }

    public function testExtendedConfigHasSubnodesTheSameType()
    {
        $config = new TestAssets\ExtendedConfig([
            'node' => [
                'key' => 'value',
            ],
        ]);

        $this->assertInstanceOf(TestAssets\ExtendedConfig::class, $config->node);
    }

    /**
     * @see https://github.com/zendframework/zend-config/pull/59
     */
    public function testAllowModificationByReference()
    {
        $config = new Config($this->all, true);

        $closure = function (&$property, $value) {
            if ($property === null) {
                return;
            }

            $property = $value;
        };

        $closure($config['db']['pass'], 'secret');
        $closure($config['db']['pass2'], 'secret');

        $this->assertEquals('secret', $config['db']['pass']);
        $this->assertNull($config['db']['pass2']);

        // this tests that 'pass2' key is not created
        $this->assertEquals('not-set', $config['db']->get('pass2', 'not-set'));
    }
}
