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
use Symfony\Component\DependencyInjection\TypedReference;

use function array_filter;
use function array_flip;
use function array_values;
use function class_exists;
use function interface_exists;
use function is_int;
use function max;
use function min;
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
    public const PRIORITY_FIRST = 'first';
    public const PRIORITY_LAST = 'last';

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
        $handlers = $container->findTaggedServiceIds($listenerTag, true);
        $mapping = [];
        $refMap = [];
        $prioritiesByEvent = [];

        foreach ($handlers as $id => $tags) {
            foreach ($tags as $tag) {
                $class = $container->getDefinition($id)->getClass();

                if (!$class) {
                    throw new InvalidArgumentException("Missing class for listener '$id'.");
                }

                $event = $this->extractEvent($tag, $id);
                $mapping[$event][] = $id;
                $refMap[$id] = new TypedReference($id, $class);
                $prioritiesByEvent[$event][$id] = $this->extractPriority($tag, $id);
            }
        }

        return [
            $this->sortMapping($mapping, $prioritiesByEvent),
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
     * @param array<string, mixed> $tag
     *
     * @return int|string
     */
    private function extractPriority(array $tag, string $id)
    {
        $priority = $tag[self::ATTRIBUTE_PRIORITY] ?? self::DEFAULT_PRIORITY;

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
     * @param array<class-string, array<string, int|string>> $prioritiesByEvent
     *
     * @return array<class-string, string[]>
     */
    private function sortMapping(array $mapping, array $prioritiesByEvent): array
    {
        foreach ($mapping as $event => &$ids) {
            $ids = $this->sortListeners(
                $ids,
                $this->resolvePriorities($prioritiesByEvent[$event])
            );
        }

        return $mapping;
    }

    /**
     * @param array<string, string|int> $priorities
     *
     * @return array<string, int>
     */
    private function resolvePriorities(array $priorities): array
    {
        $numericPriorities = array_filter($priorities, 'is_int');
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
     * @param string[] $ids
     * @param int[] $priorities
     *
     * @return string[]
     */
    private function sortListeners(array $ids, array $priorities): array
    {
        $positions = array_flip($ids);

        usort(
            $ids,
            function (string $a, string $b) use ($positions, $priorities): int {
                $pa = $priorities[$a];
                $pb = $priorities[$b];

                if ($pa === $pb) {
                    // Same priority. Let's compare the original orders, which are ascending.
                    return $positions[$a] <=> $positions[$b];
                }

                // Priorities are descending.
                return $pb <=> $pa;
            }
        );

        return array_values($ids);
    }
}
