<?php

declare(strict_types=1);

use App\Application\Actions\Pds\XrpcException;
use App\Application\Handlers\HttpErrorHandler;
use App\Application\Handlers\ShutdownHandler;
use App\Application\Handlers\XrpcErrorHandler;
use App\Application\ResponseEmitter\ResponseEmitter;
use App\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;

require __DIR__ . '/../vendor/autoload.php';

// Loading environment variables from .env file, if it exists
$envPath = __DIR__ . '/../.env';
if (is_file($envPath)) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->safeLoad();
}

// Instantiate PHP-DI ContainerBuilder
$containerBuilder = new ContainerBuilder();

// @phpstan-ignore if.alwaysFalse (toggle for production)
if (false) { // Should be set to true in production
    $containerBuilder->enableCompilation(__DIR__ . '/../var/cache');
}

// Set up settings
/** @var callable(\DI\ContainerBuilder<\DI\Container>): void $settings */
$settings = require __DIR__ . '/../app/settings.php';
$settings($containerBuilder);

// Set up dependencies
/** @var callable(\DI\ContainerBuilder<\DI\Container>): void $dependencies */
$dependencies = require __DIR__ . '/../app/dependencies.php';
$dependencies($containerBuilder);

// Set up repositories
/** @var callable(\DI\ContainerBuilder<\DI\Container>): void $repositories */
$repositories = require __DIR__ . '/../app/repositories.php';
$repositories($containerBuilder);

// Build PHP-DI Container instance
$container = $containerBuilder->build();

// Instantiate the app
AppFactory::setContainer($container);
$app = AppFactory::create();
$callableResolver = $app->getCallableResolver();

// Register middleware
/** @var callable(\Slim\App<\Psr\Container\ContainerInterface|null>): void $middleware */
$middleware = require __DIR__ . '/../app/middleware.php';
$middleware($app);

// Register routes
/** @var callable(\Slim\App<\Psr\Container\ContainerInterface|null>): void $routes */
$routes = require __DIR__ . '/../app/routes.php';
$routes($app);

/** @var SettingsInterface $settings */
$settings = $container->get(SettingsInterface::class);

/** @var bool $displayErrorDetails */
$displayErrorDetails = $settings->get('displayErrorDetails');
/** @var bool $logError */
$logError = $settings->get('logError');
/** @var bool $logErrorDetails */
$logErrorDetails = $settings->get('logErrorDetails');

// Create Request object from globals
$serverRequestCreator = ServerRequestCreatorFactory::create();
$request = $serverRequestCreator->createServerRequestFromGlobals();

// Create Error Handler
$responseFactory = $app->getResponseFactory();
$errorHandler = new HttpErrorHandler($callableResolver, $responseFactory);

// Create Shutdown Handler
$shutdownHandler = new ShutdownHandler($request, $errorHandler, $displayErrorDetails);
register_shutdown_function($shutdownHandler);

// Add Routing Middleware
$app->addRoutingMiddleware();

// Add Body Parsing Middleware
$app->addBodyParsingMiddleware();

// Add Error Middleware
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, $logError, $logErrorDetails);
$errorMiddleware->setDefaultErrorHandler($errorHandler);

// Render atproto XRPC errors in the {"error": "...", "message": "..."} format
$xrpcErrorHandler = new XrpcErrorHandler($callableResolver, $responseFactory);
$errorMiddleware->setErrorHandler(XrpcException::class, $xrpcErrorHandler);

// Run App & Emit Response
$response = $app->handle($request);
$responseEmitter = new ResponseEmitter();
$responseEmitter->emit($response);
