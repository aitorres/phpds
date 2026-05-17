<?php

declare(strict_types=1);

use App\Domain\Account\AccountRepository;
use App\Domain\Actor\ActorRepository;
use App\Infrastructure\Persistence\Account\InMemoryAccountRepository;
use App\Infrastructure\Persistence\Actor\InMemoryActorRepository;
use DI\ContainerBuilder;

use function DI\autowire;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        ActorRepository::class => autowire(InMemoryActorRepository::class),
        AccountRepository::class => autowire(InMemoryAccountRepository::class),
    ]);
};
