<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace olvlvl\EventDispatcher\Symfony;

use olvlvl\EventDispatcher\ListenerProviderWithContainer;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\TypedReference;

use function array_fill_keys;
use function array_filter;
use function array_flip;
use function array_intersect;
use function array_intersect_key;
use function array_keys;
use function array_merge;
use function array_slice;
use function class_exists;
use function count;
use function implode;
use function interface_exists;
use function is_int;
use function max;
use function min;
use function sprintf;
use function usort;

/**
 * A compilation pass for event listeners.
 */
final class ListenerProviderPass implements CompilerPassInterface
{
    public const DEFAULT_PROVIDER_TAG = 'listener_provider';
    public const DEFAULT_LISTENER_TAG = 'event_listener';
    public const DEFAULT_PRIORITY = 0;
    public const ATTRIBUTE_LISTENER_TAG = 'listener_tag';
    public const ATTRIBUTE_EVENT = 'event';
    public const ATTRIBUTE_PRIORITY = 'priority';
    public const ATTRIBUTE_BEFORE = 'before';
    public const ATTRIBUTE_AFTER = 'after';
    public const PRIORITY_FIRST = 'first';
    public const PRIORITY_LAST = 'last';

    private const PLACEMENT_ATTRIBUTES = [
        self::ATTRIBUTE_PRIORITY,
        self::ATTRIBUTE_BEFORE,
        self::ATTRIBUTE_AFTER,
    ];

    /**
     * @var string
     */
    private $providerTag;

    /**
     * @param string $providerTag Tag identifying listener providers.
     */
    public function __construct(string $providerTag = self::DEFAULT_PROVIDER_TAG)
    {
        $this->providerTag = $providerTag;
    }

    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container): void
    {
        foreach ($this->providerIterator($container) as $id => $listenerTag) {
            [ $mapping, $refMap ] = $this->collectListeners($container, $listenerTag);

            $container
                ->getDefinition($id)
                ->setSynthetic(false)
                ->setClass(ListenerProviderWithContainer::class)
                ->setArguments(
                    [
                        $mapping,
                        ServiceLocatorTagPass::register($container, $refMap),
                    ]
                );
        }
    }

    /**
     * @return iterable<string, string>
     */
    private function providerIterator(ContainerBuilder $container): iterable
    {
        foreach ($container->findTaggedServiceIds($this->providerTag, true) as $id => $tags) {
            $listener_tag = $tags[0][self::ATTRIBUTE_LISTENER_TAG] ?? self::DEFAULT_LISTENER_TAG;

            yield $id => $listener_tag;
        }
    }

    /**
     * @return array{0: array<class-string, string[]>, 1: array<string, TypedReference>}
     */
    private function collectListeners(ContainerBuilder $container, string $listenerTag): array
    {
        $listeners = $container->findTaggedServiceIds($listenerTag, true);
        $mapping = [];
        $refMap = [];
        $prioritiesByEvent = [];
        $beforeByEvent = [];
        $afterByEvent = [];

        foreach ($listeners as $id => $tags) {
            foreach ($tags as $attributes) {
                $class = $container->getDefinition($id)->getClass();

                if (!$class) {
                    throw new InvalidArgumentException("Missing class for listener '$id'.");
                }

                $refMap[$id] = new TypedReference($id, $class);

                $event = $this->extractEvent($attributes, $id);
                $mapping[$event][] = $id;

                $this->assertPlacement($attributes, $id);

                if (isset($attributes[self::ATTRIBUTE_BEFORE])) {
                    $relative = $attributes[self::ATTRIBUTE_BEFORE];
                    $this->assertRelative($listeners, $relative, $id);
                    $beforeByEvent[$event][$id] = $relative;
                } elseif (isset($attributes[self::ATTRIBUTE_AFTER])) {
                    $relative = $attributes[self::ATTRIBUTE_AFTER];
                    $this->assertRelative($listeners, $relative, $id);
                    $afterByEvent[$event][$id] = $relative;
                } else {
                    $prioritiesByEvent[$event][$id] = $this->extractPriority($attributes, $id);
                }
            }
        }

        return [
            $this->sortMapping($mapping, $prioritiesByEvent, $beforeByEvent, $afterByEvent),
            $refMap,
        ];
    }

    /**
     * @param array<string, mixed> $tag
     *
     * @return class-string
     */
    private function extractEvent(array $tag, string $id): string
    {
        $event = $tag[self::ATTRIBUTE_EVENT] ?? null;

        if (!$event) {
            $attribute = self::ATTRIBUTE_EVENT;

            throw new InvalidArgumentException(
                "Missing event type for listener '$id'."
                . " Try to specify the event using the attribute '$attribute'."
            );
        }

        if (!class_exists($event) && !interface_exists($event)) {
            throw new InvalidArgumentException(
                "Unable to load event class or interface '$event' for listener '$id'."
            );
        }

        return $event;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function assertPlacement(array $attributes, string $id): void
    {
        $positions = array_intersect(self::PLACEMENT_ATTRIBUTES, array_keys($attributes));

        if (count($positions) > 1) {
            throw new LogicException(sprintf(
                "Invalid definition for listener '$id', can only specify one of: %s.",
                implode(', ', self::PLACEMENT_ATTRIBUTES)
            ));
        }
    }

    /**
     * @param array<string, mixed> $listeners
     */
    public function assertRelative(array $listeners, string $relative, string $id): void
    {
        if (!isset($listeners[$relative])) {
            throw new InvalidArgumentException("Undefined relative for listener '$id': $relative.");
        }
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @return int|string
     */
    private function extractPriority(array $attributes, string $id)
    {
        $priority = $attributes[self::ATTRIBUTE_PRIORITY] ?? self::DEFAULT_PRIORITY;

        if ($priority !== self::PRIORITY_FIRST && $priority !== self::PRIORITY_LAST && !is_int($priority)) {
            throw new InvalidArgumentException(
                "Invalid priority value for listener '$id': $priority."
                . " Valid values are 'first', 'last', or an integer."
            );
        }

        return $priority;
    }

    /**
     * @param array<class-string, string[]> $mapping
     *     Where _key_ is an Event and _value_ is an array of service identifiers.
     * @param array<class-string, array<string, int|string>> $prioritiesByEvent
     *     Where _key_ is an Event and _value_ is an array where _key_ is a service identifier and _value_ a priority.
     * @param array<class-string, array<string, string>> $beforeByEvent
     *     Where _key_ is an Event and _value_ is an array where _key_ and _value_ are both service identifiers.
     * @param array<class-string, array<string, string>> $afterByEvent
     *     Where _key_ is an Event and _value_ is an array where _key_ and _value_ are both service identifiers.
     *
     * @return array<class-string, string[]>
     *     Where _key_ is an Event and _value_ is an array of service identifiers, sorted.
     */
    private function sortMapping(
        array $mapping,
        array $prioritiesByEvent,
        array $beforeByEvent,
        array $afterByEvent
    ): array {
        foreach ($mapping as $event => &$listeners) {
            $listeners = $this->sortListeners(
                $listeners,
                $prioritiesByEvent[$event] ?? [],
                $beforeByEvent[$event] ?? [],
                $afterByEvent[$event] ?? []
            );
        }

        return $mapping;
    }

    /**
     * @param string[] $listeners
     *     An array of service identifiers.
     * @param array<string, int|string> $priorities
     *     Where _key_ is a service identifier and _value_ a priority.
     * @param array<string, string> $beforePositions
     *     Where _key_ and _value_ are both service identifiers.
     * @param array<string, string> $afterPositions
     *     Where _key_ and _value_ are both service identifiers.
     *
     * @return string[]
     *     An array of service identifiers, sorted.
     */
    private function sortListeners(
        array $listeners,
        array $priorities,
        array $beforePositions,
        array $afterPositions
    ): array {
        $priorities = $this->resolvePriorities($priorities);
        $listenersByPosition = array_flip($listeners);
        $listenersWithPriority = array_intersect_key($priorities, $listenersByPosition);
        $sortedListeners = array_fill_keys($this->sortListenersWithPriority($listenersWithPriority), 0);

        while ($beforePositions || $afterPositions) {
            $inserted = 0;

            foreach ($beforePositions as $id => $relative) {
                if (isset($sortedListeners[$relative])) {
                    $this->insertBeforeListener($sortedListeners, $id, $relative);
                    $inserted++;
                    unset($beforePositions[$id]);
                }
            }

            foreach ($afterPositions as $id => $relative) {
                if (isset($sortedListeners[$relative])) {
                    $this->insertAfterListener($sortedListeners, $id, $relative);
                    $inserted++;
                    unset($afterPositions[$id]);
                }
            }

            if (!$inserted) {
                throw new LogicException(sprintf(
                    "Unable to insert the following listeners: %s. Please check the position logic.",
                    implode(', ', array_merge(array_keys($beforePositions), array_keys($afterPositions)))
                ));
            }
        }

        // @phpstan-ignore-next-line
        return array_keys($sortedListeners);
    }

    /**
     * @param array<string, string|int> $priorities
     *
     * @return array<string, int>
     */
    private function resolvePriorities(array $priorities): array
    {
        $numericPriorities = array_filter($priorities, 'is_int');
        if (!$numericPriorities) {
            return [];
        }

        $min = min($numericPriorities);
        $max = max($numericPriorities);

        foreach ($priorities as &$priority) {
            if ($priority === self::PRIORITY_FIRST) {
                $priority = ++$max;
            } elseif ($priority === self::PRIORITY_LAST) {
                $priority = --$min;
            }
        }

        // @phpstan-ignore-next-line
        return $priorities;
    }

    /**
     * @param array<string, int> $listenersWithPriority
     *
     * @return string[]
     */
    private function sortListenersWithPriority(array $listenersWithPriority): array
    {
        $listeners = array_keys($listenersWithPriority);
        $positions = array_flip($listeners);

        usort(
            $listeners,
            function (string $a, string $b) use ($listenersWithPriority, $positions): int {
                $pa = $listenersWithPriority[$a];
                $pb = $listenersWithPriority[$b];

                if ($pa === $pb) {
                    // Same priority. Let's compare the original orders, which are ascending.
                    return $positions[$a] <=> $positions[$b];
                }

                // Priorities are descending.
                return $pb <=> $pa;
            }
        );

        return $listeners;
    }

    /**
     * @param array<string, mixed> $listeners Where _key_ is a service identifier.
     */
    public function insertBeforeListener(array &$listeners, string $id, string $relative): void
    {
        $positions = array_flip(array_keys($listeners));
        $p = $positions[$relative];

        if ($p === 0) {
            $listeners = [ $id => 0 ] + $listeners;

            return;
        }

        $listeners = array_slice($listeners, 0, $p)
            + [ $id => true ]
            + array_slice($listeners, $p);
    }

    /**
     * @param array<string, mixed> $listeners Where _key_ is a service identifier.
     */
    public function insertAfterListener(array &$listeners, string $id, string $relative): void
    {
        $positions = array_flip(array_keys($listeners));
        $p = $positions[$relative];

        if ($p + 1 === count($listeners)) {
            $listeners += [ $id => 0 ];

            return;
        }

        $listeners = array_slice($listeners, 0, $p + 1)
            + [ $id => true ]
            + array_slice($listeners, $p - 1);
    }
}
