<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace tests\olvlvl\EventDispatcher\Symfony;

use Exception;
use olvlvl\EventDispatcher\Symfony\ListenerProviderPass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\ListenerProviderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use tests\olvlvl\EventDispatcher\SampleEventA;
use tests\olvlvl\EventDispatcher\SampleEventB;
use tests\olvlvl\EventDispatcher\SampleEventC;

use function assert;

final class ListenerProviderPassTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testDefaults(): void
    {
        $container = $this->makeContainer('config-with-defaults.yml');
        $container->compile();

        $provider = $container->get(ListenerProviderInterface::class);

        assert($provider instanceof ListenerProviderInterface);

        $this->assertSame([
            SampleListenerA1::class,
            SampleListenerA2::class,
            SampleListenerM::class,
        ], $this->collectClasses($provider->getListenersForEvent(new SampleEventA())));

        $this->assertSame([
            SampleListenerA1::class,
            SampleListenerA2::class,
            SampleListenerM::class,
            SampleListenerB::class,
        ], $this->collectClasses($provider->getListenersForEvent(new SampleEventB())));

        $this->assertSame([
            SampleListenerC::class,
            SampleListenerM::class,
        ], $this->collectClasses($provider->getListenersForEvent(new SampleEventC())));
    }

    /**
     * @throws Exception
     */
    public function testMultipleListenerProviders(): void
    {
        $container = $this->makeContainer('config-with-multiple-providers.yml');
        $container->compile();

        $providerA = $container->get('listener_provider_a');

        assert($providerA instanceof ListenerProviderInterface);

        $this->assertSame([
            SampleListenerA1::class,
            SampleListenerA2::class,
            SampleListenerM::class,
        ], $this->collectClasses($providerA->getListenersForEvent(new SampleEventA())));

        $this->assertSame([
            SampleListenerA1::class,
            SampleListenerA2::class,
            SampleListenerM::class,
        ], $this->collectClasses($providerA->getListenersForEvent(new SampleEventB())));

        $this->assertSame([
            SampleListenerM::class,
        ], $this->collectClasses($providerA->getListenersForEvent(new SampleEventC())));

        $providerB = $container->get('listener_provider_b');

        assert($providerB instanceof ListenerProviderInterface);

        $this->assertNotSame($providerA, $providerB);

        $this->assertSame([
            SampleListenerM::class,
        ], $this->collectClasses($providerB->getListenersForEvent(new SampleEventA())));

        $this->assertSame([
            SampleListenerB::class,
            SampleListenerM::class,
        ], $this->collectClasses($providerB->getListenersForEvent(new SampleEventB())));

        $this->assertSame([
            SampleListenerC::class,
            SampleListenerM::class,
        ], $this->collectClasses($providerB->getListenersForEvent(new SampleEventC())));
    }

    /**
     * @throws Exception
     */
    public function testCustomized(): void
    {
        $container = $this->makeContainer('config-with-customization.yml', new ListenerProviderPass(
            'my_listener_provider'
        ));
        $container->compile();

        $provider = $container->get('lp');

        assert($provider instanceof ListenerProviderInterface);

        $this->assertSame([
            SampleListenerA1::class,
            SampleListenerB::class,
        ], $this->collectClasses($provider->getListenersForEvent(new SampleEventB())));
    }

    /**
     * @throws Exception
     *
     * @dataProvider provideInvalidPriority
     */
    public function testInvalidPriority(string $config): void
    {
        $container = $this->makeContainer($config);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid priority value for listener/');
        $container->compile();
    }

    /**
     * @phpstan-ignore-next-line
     */
    public function provideInvalidPriority(): array
    {
        return [

            [ 'config-with-invalid-priority-1.yml' ],
            [ 'config-with-invalid-priority-2.yml' ],

        ];
    }

    /**
     * @throws Exception
     */
    public function testPriorities(): void
    {
        $container = $this->makeContainer('config-with-priorities.yml');
        $container->compile();

        $this->assertSame([
            SampleEventA::class => [
                'listener_i', // first 2nd
                'listener_e', // first 1st
                'listener_a', // 10 1st
                'listener_b', // 0 1st
                'listener_c', // 0 2nd
                'listener_j', // 0 3rd
                'listener_g', // -10 1st
                'listener_h', // -10 2nd
                'listener_d', // last 1st
                'listener_f', // last 2nd
            ],
            SampleEventB::class => [
                'listener_l',
                'listener_j',
                'listener_k',
            ]
        ], $container->getDefinition(ListenerProviderInterface::class)->getArgument(0));
    }

    /**
     * @throws Exception
     */
    public function testMissingListenerClass(): void
    {
        $container = $this->makeContainer('config-with-missing-listener-class.yml');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/Missing class for listener/");

        $container->compile();
    }

    /**
     * @throws Exception
     */
    public function testInvalidEventType(): void
    {
        $container = $this->makeContainer('config-with-invalid-event-type.yml');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/Unable to load event class or interface/");

        $container->compile();
    }

    /**
     * @throws Exception
     */
    public function testMissingEventType(): void
    {
        $container = $this->makeContainer('config-with-missing-event-type.yml');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/Missing event type for listener/");

        $container->compile();
    }

    /**
     * @throws Exception
     */
    private function makeContainer(string $config, ListenerProviderPass $pass = null): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass($pass ?? new ListenerProviderPass());
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__));
        $loader->load($config);

        return $container;
    }

    /**
     * @param object[] $objects
     *
     * @return class-string[]
     */
    private function collectClasses(iterable $objects): array
    {
        $ar = [];

        foreach ($objects as $object) {
            $ar[] = get_class($object);
        }

        return $ar;
    }
}
