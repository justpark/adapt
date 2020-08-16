<?php

namespace CodeDistortion\Adapt\Tests\Unit\DTO;

use App;
use CodeDistortion\Adapt\DTO\LaravelPropBagDTO;
use CodeDistortion\Adapt\Exceptions\AdaptPropBagDTOException;
use CodeDistortion\Adapt\Tests\LaravelTestCase;
use Throwable;

/**
 * Test the PropBag class
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class LaravelPropBagDTOTest extends LaravelTestCase
{
    /**
     * Provide data for the prop_bag_dto_can_set_and_get_values test.
     *
     * @return mixed[][]
     */
    public function propBagDTODataProvider(): array
    {
        return [
            'set and get' => [
                'set' => [
                    'defaultConnection' => 'mysql',
                ],
                'check' => [
                    'method' => 'prop',
                    'params' => ['defaultConnection'],
                    'expected' => 'mysql',
                    'exception' => null,
                ],
            ],
            'set and get something else' => [
                'set' => [
                    'defaultConnection' => 'mysql',
                ],
                'check' => [
                    'method' => 'prop',
                    'params' => ['somethingElse'],
                    'expected' => '',
                    'exception' => AdaptPropBagDTOException::class,
                ],
            ],
            'set and get with default' => [
                'set' => [
                    'defaultConnection' => 'mysql',
                ],
                'check' => [
                    'method' => 'prop',
                    'params' => ['defaultConnection', 'default'],
                    'expected' => 'mysql',
                    'exception' => null,
                ],
            ],
            'set and get something else with default' => [
                'set' => [
                    'defaultConnection' => 'mysql',
                ],
                'check' => [
                    'method' => 'prop',
                    'params' => ['somethingElse', 'default'],
                    'expected' => 'default',
                    'exception' => null,
                ],
            ],
            'set and hasProp when it does exist' => [
                'set' => [
                    'defaultConnection' => 'mysql',
                ],
                'check' => [
                    'method' => 'hasProp',
                    'params' => ['defaultConnection'],
                    'expected' => true,
                    'exception' => null,
                ],
            ],
            'set and hasProp when it doesn\'t exist' => [
                'set' => [
                    'defaultConnection' => 'mysql',
                ],
                'check' => [
                    'method' => 'hasProp',
                    'params' => ['somethingElse'],
                    'expected' => false,
                    'exception' => null,
                ],
            ],
        ];
    }

    /**
     * Test that the PropBagDTO object can set and get values properly.
     *
     * @test
     * @dataProvider propBagDTODataProvider
     * @param string[] $set   The values to set.
     * @param mixed[]  $check Attempts to get values back out and check the result.
     * @return void
     */
    public function test_that_prop_bag_dto_can_set_and_get_values(array $set, array $check)
    {
        // add some values to the bag
        $propBag = new LaravelPropBagDTO;
        foreach ($set as $name => $value) {
            $this->assertSame(
                $propBag,
                $propBag->addProp($name, $value)
            );
        }

        // retrieve some values and see what happens
        $callable = [$propBag, $check['method']];
        if (is_callable($callable)) {

            if (is_string($check['exception'])) {

                try {
                    call_user_func_array($callable, $check['params']);
                } catch (Throwable $e) {
                    if ($e instanceof $check['exception']) {
                        $this->assertTrue(true);
                    } else {
                        throw $e;
                    }
                }

            } else {
                $value = call_user_func_array($callable, $check['params']);
                $this->assertSame($check['expected'], $value);
            }
        }
    }

    /**
     * Test the LaravelPropBagDTO->config(..) method.
     *
     * @test
     * @return void
     */
    public function test_the_config_getter()
    {
        config(['code-distortion.adapt.existing-value' => 'config value']);
        $propBag = (new LaravelPropBagDTO)->addProp('existingValue', 'prop value');
        $this->assertSame(null, $propBag->config('missing-value', 'missingValue'));
        $this->assertSame('prop value', $propBag->config('missing-value', 'existingValue'));
        $this->assertSame('config value', $propBag->config('existing-value', 'missingValue'));
        $this->assertSame('prop value', $propBag->config('existing-value', 'existingValue'));
    }
}
