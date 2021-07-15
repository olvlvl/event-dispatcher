# olvlvl/event-dispatcher

[![Release](https://img.shields.io/packagist/v/olvlvl/event-dispatcher.svg)](https://packagist.org/packages/olvlvl/event-dispatcher)
[![Packagist](https://img.shields.io/packagist/dt/olvlvl/event-dispatcher.svg)](https://packagist.org/packages/olvlvl/event-dispatcher)
[![Code Quality](https://img.shields.io/scrutinizer/g/olvlvl/event-dispatcher.svg)](https://scrutinizer-ci.com/g/olvlvl/event-dispatcher)
[![Code Coverage](https://img.shields.io/coveralls/olvlvl/event-dispatcher.svg)](https://coveralls.io/r/olvlvl/event-dispatcher)

`olvlvl/event-dispatcher` provides an implementation of [psr/event-dispatcher][], which establishes a common mechanism
for event-based extension and collaboration.

#### Package highlights

- Supports Event inheritance, including interfaces.
- Supports stoppable Events.
- Provides a collection of composable Event Dispatchers and Listener Providers.
- Introduces Mutable Listener Providers.
- Provides a compiler pass for [symfony/dependency-injection][], with priorities and relatives.

#### Installation

```bash
composer require olvlvl/event-dispatcher
```



## Event Dispatcher

An Event Dispatcher is a service object that is given an Event object by an Emitter. The Dispatcher is responsible for
ensuring that the Event is passed to all relevant Listeners, but MUST defer determining the responsible listeners to a
Listener Provider.



### Basic Event Dispatcher

`BasicEventDispatcher` is a basic implementation of an Event Dispatcher, that complies
with the [requirements for Dispatchers][dispatcher].

```php
<?php

use olvlvl\EventDispatcher\BasicEventDispatcher;

/* @var Psr\EventDispatcher\ListenerProviderInterface $listenerProvider */
/* @var object $event */

$dispatcher = new BasicEventDispatcher($listenerProvider);
$dispatcher->dispatch($event);
```



### Buffered Event Dispatcher

In some situations, it can be desired to defer the dispatching of events. For instance, an application that's presenting
an API to create recipes, and needs to index created recipes and run additional time-consuming calculations, would want
to defer dispatching the events, to reply as soon as possible to the user.

`BufferedEventDispatcher` decorates an Event Dispatcher and buffers events that can be dispatched at a later time. The
user can provide a discriminator that decides whether an event should be buffered or dispatched immediately.

**Careful using this type of Dispatcher!** Because event dispatching is delayed, it will cause issues for users that
expect Events to be modified.

**Note:** In accordance with [Dispatchers requirements][dispatcher], stopped Events are discarded and not be buffered.

```php
<?php

use olvlvl\EventDispatcher\BufferedEventDispatcher;

/* @var Psr\EventDispatcher\EventDispatcherInterface $decorated */
/* @var object $eventA */
/* @var SampleInterface $eventB */
/* @var object $eventC */

$dispatcher = new BufferedEventDispatcher(
    $decorated,
    // An optional discriminator
    function (object $event): bool {
        return !$event instanceof SampleInterface;
    }
);

$dispatcher->dispatch($eventA);
// $eventB is dispatched immediately
$dispatcher->dispatch($eventB);
$dispatcher->dispatch($eventC);

// ... Some code here, maybe reply to a request.

$dispatchedEvents = $dispatcher->dispatchBufferedEvents();
```



## Listener Provider

A Listener Provider is responsible for determining what Listeners are relevant to and should be called for a given
Event. `olvlvl/event-dispatcher` provides a few Listener Provider implementations, that comply with
the [requirements and recommendations for Listener Providers][listener-provider].



### Listener Provider with a map

`ListenerProviderWithMap` is a Listener Provider that uses an array of Event/Listeners pairs.

```php
<?php

use olvlvl\EventDispatcher\ListenerProviderWithMap;

/* @var callable $callableA */
/* @var callable $callableB */
/* @var callable $callableC */

$provider = new ListenerProviderWithMap([

    MyEventA::class => [ $callableA ],
    MyEventInterfaceA::class => [ $callableB, $callableC ],

]);
```



### Listener Provider with a container

`ListenerProviderWithContainer` is a Listener Provider that uses an array of Event/service id pairs and retrieves
Listeners from a [PSR container][psr/container].

**Note:** `olvlvl/event-dispatcher` provides [a compiler pass][#compiler-pass] for [symfony/dependency-injection][] that
is very handy to collect Event Listeners and build Listener Providers.

```php
<?php

use olvlvl\EventDispatcher\ListenerProviderWithContainer;

/* @var Psr\Container\ContainerInterface $container */

$provider = new ListenerProviderWithContainer([

    SampleEventA::class => [ 'serviceA' ],
    SampleEventInterfaceA::class => [ 'serviceA', 'serviceB' ],

], $container);
```



### Mutable Listener Provider

`MutableListenerProvider` is a mutable Listener Provider, that is, listeners can be added and removed. To this effect,
the Provider has no constructor arguments so that any Listener it contains can also be removed.

The Listener Provider implements `MutableListenerProviderInterface`, which extends `ListenerProviderInterface`. The
interface can be used to distinguish a mutable Listener Provider from a non-mutable one.

```php
<?php

use olvlvl\EventDispatcher\MutableListenerProviderInterface;

/* @var Psr\EventDispatcher\ListenerProviderInterface $provider */

if ($provider instanceof MutableListenerProviderInterface) {
    // ... we can add or remove Listeners.
}
```

A Listener for an Event can be added to the end of the list with the method `appendListenerForEvent()`, or to the
beginning of the list with the method `prependListenerForEvent()`. Both methods return a callable that can be used to
remove the Listener.

**Note:** A `LogicException` is thrown if a Listener is added twice for an Event type. The call is not failing silently
because a Listener can have very different and unpredictable outcomes whether it was prepended or appended.

The following example demonstrates how a Listener can be appended for an Event to a mutable Listener Provider. In the
example, the "remove" callable is used by the Listener to remove itself once it has been called. This is how one would
set up a "once" Listener. Of course, this is just an example of application.

```php
<?php

use olvlvl\EventDispatcher\MutableListenerProvider;

$provider = new MutableListenerProvider();
$remove = $provider->appendListenerForEvent(
    SampleEvent::class,
    function (SampleEvent $event) use (&$remove): void {
        // This is how one can implement a "once" listener.
        // The listener is removed when it's called.
        $remove();
        // ... do something with the event here.
    }
);
```




### Listener Provider Chain

With `ListenerProviderChain`, multiple Listener Providers can be combined to act like one. They are called in succession
to provide Listeners for an Event.

The chain is mutable, Listener Providers can be added to the end of the chain using the `appendListenerProviders()`
method, or to the beginning of the chain using the `prependListenerProviders()` method.

**Note:** Since `ListenerProviderChain` is a Provider Listener like any other, creating a chain of chains is a
possibility.

The following example demonstrates how to create a chain of Listener Providers, and modify that chain by appending and
prepending others.

```php
<?php

use olvlvl\EventDispatcher\ListenerProviderChain;

// Compose a Listener Provider from a number of Listener Providers.

/* @var $providerA olvlvl\EventDispatcher\MutableListenerProvider */
/* @var $providerB olvlvl\EventDispatcher\ListenerProviderWithMap */
/* @var $providerC olvlvl\EventDispatcher\ListenerProviderWithContainer */

$provider = new ListenerProviderChain([ $providerA, $providerB, $providerC ]);

// Listener Providers can be added to the end of the chain.

/* @var $providerD Psr\EventDispatcher\ListenerProviderInterface */
/* @var $providerE Psr\EventDispatcher\ListenerProviderInterface */

$provider->appendListenerProviders($providerD, $providerE);

// Listener Providers can be added to the beginning of the chain.

/* @var $providerF Psr\EventDispatcher\ListenerProviderInterface */
/* @var $providerG Psr\EventDispatcher\ListenerProviderInterface */

$provider->prependListenerProviders($providerF, $providerG);

// Obtain the Listeners for an event

/* @var object $event */

foreach ($provider->getListenersForEvent($event) as $listener) {
    // ... do something with the listeners
}
```



### Listener Provider Filter

`ListenerProviderFilter` decorates a Listener Provider to filter Listeners according to a user specified discriminator.
The filter can be used to implement some form of access control so that certain Listeners will only be called if the
current user has a certain permission.

The following example demonstrates how the filter can be used to discard `$listener_1` for `SampleEventA`
and `$listener_2` for `SampleEventC`.

```php
<?php

use olvlvl\EventDispatcher\ListenerProviderFilter;
use olvlvl\EventDispatcher\ListenerProviderWithMap;

/* @var callable $listener_1 */
/* @var callable $listener_2 */

$provider = new ListenerProviderFilter(
    new ListenerProviderWithMap([
        SampleEventA::class => [ $listener_1, $listener_2 ],
        SampleEventC::class => [ $listener_1, $listener_2 ],
    ]),
    function (object $event, callable $listener) use ($listener_1, $listener_2): bool {
        if ($event instanceof SampleEventA && $listener === $listener_1) {
            return false;
        }

        if ($event instanceof SampleEventC && $listener === $listener_2) {
            return false;
        }

        return true;
    }
);
```



## Compiler pass for symfony/dependency-injection

The package provides a compiler pass for [symfony/dependency-injection][] that builds one or many Listener Providers
automatically.

Basically, the compiler pass searches for the tagged services, collect their Event Listeners, creates a mapping with
their events, and overwrite a few attributes to complete the definition of the service.

If Listeners are spread over multiple files, or if it's not practical to keep them ordered, priorities and relatives can
be specified for each Event/Listener pairs, and Listeners will be sorted accordingly.



### Adding the compiler pass

```php
<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use olvlvl\EventDispatcher\Symfony\ListenerProviderPass;

$container = new ContainerBuilder();
$container->addCompilerPass(new ListenerProviderPass());
```

By default, the tag used to identify the Listener Providers to build is `listener_provider`, but it can be configured:

```php
<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use olvlvl\EventDispatcher\Symfony\ListenerProviderPass;

$container = new ContainerBuilder();
$container->addCompilerPass(new ListenerProviderPass('my_listener_provider_tag'));
```



### Defining the services

The following example uses the PSR interface as service identifier, but a name such as `my_listener_provider` can be
used just the same, as we'll see later when building multiple Listener Providers. Also, it is not required to specify
the `synthetic` attribute, but it is recommended to indicate to fellow developers that the service definition is a stub.

**Note:** To complete the service definition, the compiler pass overwrites the attributes `synthetic`, `class`,
and `arguments`, but leaves intact any other attribute.

```yaml
services:
  Psr\EventDispatcher\ListenerProviderInterface:
    synthetic: true
    tags: [ listener_provider ]
```

By default, the tag for the Listener services is `event_listener` but it can be configured, which is required when
building multiple Listener Providers.

```yaml
services:
  Psr\EventDispatcher\ListenerProviderInterface:
    synthetic: true
    tags:
    - { name: listener_provider, listener_tag: event_listener }
```

The following example demonstrates own Listener services are attached to a Listener Provider. They are tagged
with `event_listener`, which is the default tag. A listener can listen to multiple events, as is demonstrated
by `ListenerC`.

```yaml
services:
   Psr\EventDispatcher\ListenerProviderInterface:
     synthetic: true
     tags: [ listener_provider ]

   Acme\MyApp\ListenerA:
     tags:
     - { name: event_listener, event: Acme\MyApp\EventA }

   Acme\MyApp\ListenerB:
     tags:
     - { name: event_listener, event: Acme\MyApp\EventB }

   # ListenerC listens to EventA and EventC
   Acme\MyApp\ListenerC:
     tags:
     - { name: event_listener, event: Acme\MyApp\EventA }
     - { name: event_listener, event: Acme\MyApp\EventC }
```



### Building multiple Listener Providers

It is possible to build multiple Listener Providers, you just need to specify which Listener tag to use for each of
them:

```yaml
services:
  listener_provider_a:
    class: Psr\EventDispatcher\ListenerProviderInterface
    synthetic: true
    tags:
    - { name: listener_provider, listener_tag: event_listener_for_a }

  listener_provider_b:
    class: Psr\EventDispatcher\ListenerProviderInterface
    synthetic: true
    tags:
    - { name: listener_provider, listener_tag: event_listener_for_b }

  Acme\MyApp\ListenerA1:
    tags:
    - { name: event_listener_for_a, event: Acme\MyApp\EventA }

  Acme\MyApp\ListenerA2:
    tags:
    - { name: event_listener_for_a, event: Acme\MyApp\EventA }

  Acme\MyApp\ListenerB:
    tags:
    - { name: event_listener_for_b, event: Acme\MyApp\EventB }

  # ListenerM is used by both Providers A and B,
  # but it will only receive EventC from Provider B
  Acme\MyApp\ListenerM:
    tags:
    - { name: event_listener_for_a, event: Acme\MyApp\EventA }
    - { name: event_listener_for_b, event: Acme\MyApp\EventA }
    - { name: event_listener_for_b, event: Acme\MyApp\EventC }
```



### Specify priorities

The compiler pass can use priorities to sort listeners. Valid priorities are integers, positive or negative, or one of
the special values `first` and `last`. With these special values, the Listener is placed first or last, no matter the
other priorities. Multiple Listeners can use these special values, in which case, the effect stacks. In the case of
equal priorities, the original order is preserved.

**Note:** If not specified, the priority defaults to 0, unless the attributes `before` or `after` are defined.

The following example demonstrates how the `priority` attribute can be used to specify the order of Listeners. The final
order will be as follows:

- For `SampleEventA`: `listener_e`, `listener_d`, `listener_c`, `listener_a`, `listener_b`.
- For `SampleEventB`: `listener_d`, `listener_b`.

```yaml
services:
  Psr\EventDispatcher\ListenerProviderInterface:
    synthetic: true
    tags: [ listener_provider ]

  listener_a:
    class: SampleListener
    tags:
    - name: event_listener
      event: SampleEventA
      priority: -10

  listener_b:
    class: SampleListener
    tags:
    - name: event_listener
      event: SampleEventA
      priority: last
    - name: event_listener
      event: SampleEventB

  listener_c:
    class: SampleListener
    tags:
    - name: event_listener
      event: SampleEventA

  listener_d:
    class: SampleListener
    tags:
    - name: event_listener
      event: SampleEventA
      priority: first
    - name: event_listener
      event: SampleEventB
      priority: 10

  listener_e:
    class: SampleListener
    tags:
    - name: event_listener
      event: SampleEventA
      priority: first
```



### Specify relatives

The compiler pass can sort Listeners relatively to others. The `before` attribute allows a Listener to be placed before
another Listener, while the `after` attribute allows a Listener to be placed after another Listener.

**Note:** Only one of `priority`, `before`, or `after` can be used at once.

The following example demonstrates how priorities and relatives can be used to order Listeners. The final order will be
as follows: `listener_e`, `listener_d`, `listener_c`, `listener_b`, `listener_a`.

```yaml
services:
  Psr\EventDispatcher\ListenerProviderInterface:
    synthetic: true
    tags: [ listener_provider ]

  listener_a:
    class: SampleListener
    tags:
    - name: event_listener
      event: SampleEventA

  listener_b:
    class: SampleListener
    tags:
    - name: event_listener
      event: SampleEventA
      before: listener_a

  listener_c:
    class: SampleListener
    tags:
    - name: event_listener
      event: SampleEventA
      after: listener_d

  listener_d:
    class: SampleListener
    tags:
    - name: event_listener
      event: SampleEventA
      after: listener_e

  listener_e:
    class: SampleListener
    tags:
    - name: event_listener
      event: SampleEventA
      priority: first
```



----------



## Continuous Integration

The project is continuously tested by [GitHub actions](https://github.com/olvlvl/event-dispatcher/actions).

[![Tests](https://github.com/olvlvl/event-dispatcher/workflows/test/badge.svg?branch=main)](https://github.com/olvlvl/event-dispatcher/actions?query=workflow%3Atest)
[![Static Analysis](https://github.com/olvlvl/event-dispatcher/workflows/static-analysis/badge.svg?branch=main)](https://github.com/olvlvl/event-dispatcher/actions?query=workflow%3Astatic-analysis)
[![Code Style](https://github.com/olvlvl/event-dispatcher/workflows/code-style/badge.svg?branch=main)](https://github.com/olvlvl/event-dispatcher/actions?query=workflow%3Acode-style)



## Code of Conduct

This project adheres to a [Contributor Code of Conduct](CODE_OF_CONDUCT.md). By participating in this project and its
community, you are expected to uphold this code.



## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.



## License

**olvlvl/event-dispatcher** is released under the [BSD-3-Clause](LICENSE).



[#compiler-pass]: #compiler-pass-for-symfonydependency-injection
[listener-provider]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-14-event-dispatcher.md#listener-provider
[dispatcher]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-14-event-dispatcher.md#dispatcher
[psr/container]: https://www.php-fig.org/psr/psr-11/
[psr/event-dispatcher]: https://www.php-fig.org/psr/psr-14/
[symfony/dependency-injection]: https://github.com/symfony/dependency-injection
