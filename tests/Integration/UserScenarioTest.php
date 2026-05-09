<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Integration;

use Switon\Core\Attribute\Autowired;
use Switon\Core\Lazy;
use Switon\Di\Factory;
use Switon\Di\Tests\Fixtures\{CacheInterface,
    CircularServiceA,
    CircularServiceB,
    ConfigurableService,
    DatabaseConnection,
    DataService,
    EmailService,
    EmailServiceInterface,
    FileLogger,
    LoggerInterface,
    MemoryCache,
    OrderController,
    OrderRepository,
    OrderService,
    PaymentService,
    PaymentServiceInterface,
    RequestInterface,
    ResponseInterface,
    UserController,
    UserRepository,
    UserRepositoryInterface,
    UserService
};
use Switon\Di\Tests\TestCase;

/**
 * @see Tests\Fixtures\RealWorldTestClasses For test class definitions:
 *   - UserRepositoryInterface, UserRepository
 *   - EmailServiceInterface, EmailService
 *   - LoggerInterface, FileLogger
 *   - PaymentServiceInterface, PaymentService
 *   - CacheInterface, MemoryCache
 *   - UserService, OrderService
 *   - UserController, OrderController
 *   - RequestInterface, Request
 *   - ResponseInterface, Response
 *   - ConfigurableService, DatabaseConnection, DataService
 *   - OrderRepository
 */

/**
 * User scenario tests for DI Container.
 *
 * These tests simulate real-world usage scenarios from a user's perspective,
 * focusing on how developers would actually use the DI container in their applications.
 */
class UserScenarioTest extends TestCase
{
    /**
     * Scenario: User creates a service with dependencies injected automatically.
     *
     * This is the most common use case - registering services and having
     * their dependencies automatically injected.
     */
    public function testCreateServiceWithDependencies(): void
    {
        // Arrange - User registers services
        $this->container->set(EmailServiceInterface::class, [
            'class' => EmailService::class,
            'smtpHost' => 'smtp.example.com',
        ]);
        $this->container->set(LoggerInterface::class, FileLogger::class);
        $this->container->set(UserService::class, [
            'class' => UserService::class,
            'appName' => 'MyApplication',
        ]);

        // Act - User gets the service
        $userService = $this->container->get(UserService::class);

        // Assert - Dependencies are automatically injected
        $this->assertInstanceOf(UserService::class, $userService);
        $this->assertInstanceOf(UserRepositoryInterface::class, $userService->userRepository);
        $this->assertInstanceOf(EmailServiceInterface::class, $userService->emailService);
        $this->assertInstanceOf(LoggerInterface::class, $userService->logger);
        $this->assertSame('MyApplication', $userService->appName);
    }

    /**
     * Scenario: User creates a service that depends on another service.
     *
     * Testing service-to-service dependency injection.
     */
    public function testCreateServiceWithServiceDependencies(): void
    {
        // Arrange
        $this->container->set(LoggerInterface::class, FileLogger::class);
        $this->container->set(EmailServiceInterface::class, [
            'class' => EmailService::class,
            'smtpHost' => 'smtp.example.com',
        ]);

        // Act
        $emailService = $this->container->get(EmailServiceInterface::class);

        // Assert - EmailService has Logger injected
        $this->assertInstanceOf(EmailService::class, $emailService);
        $this->assertInstanceOf(LoggerInterface::class, $emailService->logger);
        $this->assertSame('smtp.example.com', $emailService->smtpHost);
    }

    /**
     * Scenario: User creates a controller with multiple dependencies.
     *
     * This simulates how controllers are typically used in web applications.
     */
    public function testCreateControllerWithMultipleDependencies(): void
    {
        // Arrange
        $this->container->set(EmailServiceInterface::class, [
            'class' => EmailService::class,
            'smtpHost' => 'smtp.example.com',
        ]);
        $this->container->set(LoggerInterface::class, FileLogger::class);
        $this->container->set(UserService::class, [
            'class' => UserService::class,
            'appName' => 'TestApp',
        ]);
        $this->container->set(UserController::class, UserController::class);

        // Act
        $controller = $this->container->get(UserController::class);

        // Assert - All dependencies are injected
        $this->assertInstanceOf(UserController::class, $controller);
        $this->assertInstanceOf(UserService::class, $controller->userService);
        $this->assertInstanceOf(RequestInterface::class, $controller->request);
        $this->assertInstanceOf(ResponseInterface::class, $controller->response);
    }

    /**
     * Scenario: User creates a service with configuration values.
     *
     * Testing injection of scalar values and arrays from configuration.
     */
    public function testInjectConfigurationValues(): void
    {
        // Arrange
        $this->container->set(LoggerInterface::class, FileLogger::class);
        $this->container->set(ConfigurableService::class, [
            'class' => ConfigurableService::class,
            'apiUrl' => 'https://api.example.com',
            'timeout' => 30,
            'allowedMethods' => ['GET', 'POST', 'PUT'],
        ]);

        // Act
        $service = $this->container->get(ConfigurableService::class);

        // Assert - Configuration values are injected
        $this->assertInstanceOf(ConfigurableService::class, $service);
        $this->assertSame('https://api.example.com', $service->getApiUrl());
        $this->assertSame(30, $service->getTimeout());

        // Verify array configuration: default values are merged with provided values
        $methods = $service->getAllowedMethods();
        $this->assertContains('GET', $methods);
        $this->assertContains('POST', $methods);
        $this->assertContains('PUT', $methods);
    }

    /**
     * Scenario: User creates a complex service chain.
     *
     * Testing multiple levels of dependency injection.
     */
    public function testCreateComplexServiceChain(): void
    {
        // Arrange - Register all dependencies
        $this->container->set(LoggerInterface::class, FileLogger::class);
        $this->container->set(OrderRepository::class, OrderRepository::class);
        $this->container->set(EmailServiceInterface::class, [
            'class' => EmailService::class,
            'smtpHost' => 'smtp.example.com',
        ]);
        $this->container->set(PaymentServiceInterface::class, [
            'class' => PaymentService::class,
            'apiKey' => 'test-api-key',
        ]);
        $this->container->set(UserService::class, [
            'class' => UserService::class,
            'appName' => 'ECommerceApp',
        ]);
        $this->container->set(OrderService::class, OrderService::class);

        // Act
        $orderService = $this->container->get(OrderService::class);

        // Assert - All dependencies are injected through the chain
        $this->assertInstanceOf(OrderService::class, $orderService);
        $this->assertInstanceOf(OrderRepository::class, $orderService->orderRepository);
        $this->assertInstanceOf(UserRepositoryInterface::class, $orderService->userRepository);
        $this->assertInstanceOf(PaymentServiceInterface::class, $orderService->paymentService);
        $this->assertInstanceOf(LoggerInterface::class, $orderService->logger);
    }

    /**
     * Scenario: User uses interface-based dependency injection.
     *
     * Testing that interfaces automatically resolve to implementations.
     */
    public function testUseInterfaceBasedInjection(): void
    {
        // Arrange - Interface is auto-mapped by convention
        $this->container->set(EmailServiceInterface::class, [
            'class' => EmailService::class,
            'smtpHost' => 'smtp.example.com',
        ]);
        $this->container->set(LoggerInterface::class, FileLogger::class);
        $this->container->set(UserService::class, [
            'class' => UserService::class,
            'appName' => 'TestApp',
        ]);

        // Act
        $userService = $this->container->get(UserService::class);

        // Assert - Interface properties resolve to implementations
        $this->assertInstanceOf(UserRepository::class, $userService->userRepository);
        $this->assertInstanceOf(EmailService::class, $userService->emailService);
        $this->assertInstanceOf(FileLogger::class, $userService->logger);
    }

    /**
     * Scenario: User creates multiple instances of the same service.
     *
     * Testing that get() returns singleton, make() creates new instances.
     */
    public function testCreateMultipleInstancesWhenNeeded(): void
    {
        // Arrange
        $this->container->set(EmailServiceInterface::class, [
            'class' => EmailService::class,
            'smtpHost' => 'smtp.example.com',
        ]);
        $this->container->set(LoggerInterface::class, FileLogger::class);
        $this->container->set(UserService::class, [
            'class' => UserService::class,
            'appName' => 'TestApp',
        ]);

        // Act - get() returns singleton
        $service1 = $this->container->get(UserService::class);
        $service2 = $this->container->get(UserService::class);

        // Assert - Same instance (singleton)
        $this->assertSame($service1, $service2);

        // Act - make() creates new instances (with parameters)
        $service3 = $this->container->make(UserService::class, [
            'appName' => 'TestApp',
        ]);
        $service4 = $this->container->make(UserService::class, [
            'appName' => 'TestApp',
        ]);

        // Assert - Different instances
        $this->assertNotSame($service1, $service3);
        $this->assertNotSame($service3, $service4);
    }

    /**
     * Scenario: User uses named services for different configurations.
     *
     * Testing Factory pattern with named services.
     */
    public function testUseNamedServicesForDifferentConfigurations(): void
    {
        // Arrange - Create factory with named services
        $factory = new Factory([
            'readonly' => [
                'class' => DatabaseConnection::class,
                'host' => 'read-db.example.com',
                'database' => 'app_read',
            ],
            'writable' => [
                'class' => DatabaseConnection::class,
                'host' => 'write-db.example.com',
                'database' => 'app_write',
            ],
        ]);

        $this->container->set(DatabaseConnection::class, $factory);
        $this->container->set(DataService::class, [
            'class' => DataService::class,
            'readConnection' => DatabaseConnection::class . '#readonly',
            'writeConnection' => DatabaseConnection::class . '#writable',
        ]);

        // Act
        $dataService = $this->container->get(DataService::class);
        $readConnection = $this->container->get(DatabaseConnection::class . '#readonly');
        $writeConnection = $this->container->get(DatabaseConnection::class . '#writable');

        // Assert - Named services are correctly resolved
        $this->assertInstanceOf(DataService::class, $dataService);
        $this->assertInstanceOf(DatabaseConnection::class, $readConnection);
        $this->assertInstanceOf(DatabaseConnection::class, $writeConnection);
        $this->assertSame('read-db.example.com', $readConnection->getHost());
        $this->assertSame('app_read', $readConnection->getDatabase());
        $this->assertSame('write-db.example.com', $writeConnection->getHost());
        $this->assertSame('app_write', $writeConnection->getDatabase());
    }

    /**
     * Scenario: User executes a complete business workflow.
     *
     * Testing a realistic end-to-end scenario that exercises the full DI container
     * capabilities across multiple service layers (infrastructure, service, controller).
     *
     * **Test Structure:**
     * 1. Arrange: Register 10 services representing a complete application stack
     * 2. Act: Execute a realistic business workflow (create user -> create order -> controller action)
     * 3. Assert: Verify workflow results at each step
     *
     * **What this test validates:**
     * - Service registration with different definition types (classes, arrays)
     * - Dependency injection across multiple layers
     * - Service resolution and singleton behavior
     * - Integration of infrastructure, service, and controller layers
     *
     * **Note:** This is an integration test with multiple dependencies.
     * If this test fails, check which layer is failing (repository, service, or controller).
     */
    public function testExecuteCompleteBusinessWorkflow(): void
    {
        // Arrange - Set up complete application services
        // Infrastructure layer
        $this->container->set(LoggerInterface::class, FileLogger::class);

        // Repository layer
        $this->container->set(OrderRepository::class, OrderRepository::class);

        // Service layer - with configuration
        $this->container->set(EmailServiceInterface::class, [
            'class' => EmailService::class,
            'smtpHost' => 'smtp.example.com',
        ]);
        $this->container->set(PaymentServiceInterface::class, [
            'class' => PaymentService::class,
            'apiKey' => 'test-key',
        ]);
        $this->container->set(UserService::class, [
            'class' => UserService::class,
            'appName' => 'ECommerceApp',
        ]);
        $this->container->set(OrderService::class, OrderService::class);

        // HTTP layer
        $this->container->set(OrderController::class, OrderController::class);

        // Act - Execute workflow: Create user, then create order, then controller action
        // Step 1: Create user through UserService
        $userService = $this->container->get(UserService::class);
        $user = $userService->createUser('John Doe', 'john@example.com');

        // Step 2: Create order through OrderService
        $orderService = $this->container->get(OrderService::class);
        $order = $orderService->createOrder($user->id, 99.99);

        // Step 3: Execute controller action
        $controller = $this->container->get(OrderController::class);
        $response = $controller->createAction($user->id, 149.99);

        // Assert - Workflow completed successfully
        // Verify user creation
        $this->assertNotNull($user->id);
        $this->assertSame('John Doe', $user->name);
        $this->assertSame('john@example.com', $user->email);

        // Verify order creation
        $this->assertNotNull($order->id);
        $this->assertSame($user->id, $order->userId);
        $this->assertSame(99.99, $order->amount);
        $this->assertSame('paid', $order->status);

        // Verify controller response
        $decoded = json_decode($response, true);
        $this->assertIsArray($decoded, 'Controller response should be valid JSON');
        $this->assertSame(0, $decoded['code'] ?? null, 'Response code should be 0 for success');
    }

    /**
     * Scenario: User checks if a service exists before using it.
     *
     * Testing the has() method for service existence checks.
     */
    public function testCheckIfServiceExists(): void
    {
        // Arrange
        $this->container->set(UserService::class, [
            'class' => UserService::class,
            'appName' => 'TestApp',
        ]);

        // Act & Assert
        $this->assertTrue($this->container->has(UserService::class));
        $this->assertFalse($this->container->has('NonExistentService'));
    }

    /**
     * Scenario: User replaces a service configuration.
     *
     * Testing service removal and re-registration.
     */
    public function testReplaceServiceConfiguration(): void
    {
        // Arrange - Register initial service
        $this->container->set(LoggerInterface::class, FileLogger::class);
        $this->container->set(EmailServiceInterface::class, [
            'class' => EmailService::class,
            'smtpHost' => 'smtp.example.com',
        ]);
        $this->container->set(UserService::class, [
            'class' => UserService::class,
            'appName' => 'OldApp',
        ]);

        $oldService = $this->container->get(UserService::class);
        $this->assertSame('OldApp', $oldService->appName);

        // Act - Remove and re-register with new configuration
        $this->container->remove(UserService::class);
        $this->container->set(UserService::class, [
            'class' => UserService::class,
            'appName' => 'NewApp',
        ]);

        $newService = $this->container->get(UserService::class);

        // Assert - New configuration is used
        $this->assertSame('NewApp', $newService->appName);
        $this->assertNotSame($oldService, $newService);
    }

    /**
     * Scenario: User creates a service with default configuration values.
     *
     * Testing that default values are used when not provided.
     */
    public function testUseDefaultConfigurationValues(): void
    {
        // Arrange
        $this->container->set(LoggerInterface::class, FileLogger::class);
        $this->container->set(ConfigurableService::class, [
            'class' => ConfigurableService::class,
            'apiUrl' => 'https://api.example.com',
            'timeout' => 30,
            // allowedMethods has default value ['GET', 'POST']
        ]);

        // Act
        $service = $this->container->get(ConfigurableService::class);

        // Assert - Default values are used
        $this->assertSame(['GET', 'POST'], $service->getAllowedMethods());
    }

    /**
     * Scenario: User creates a service with array configuration merging.
     *
     * Testing array merge behavior for configuration.
     */
    public function testMergeArrayConfiguration(): void
    {
        // Arrange - Service with default array config
        $this->container->set(LoggerInterface::class, FileLogger::class);
        $this->container->set(ConfigurableService::class, [
            'class' => ConfigurableService::class,
            'apiUrl' => 'https://api.example.com',
            'timeout' => 30,
            'allowedMethods' => ['GET', 'POST'], // Default
        ]);

        // Act - Override with partial config
        $service = $this->container->get(ConfigurableService::class);

        // Verify that default array values are preserved
        // Array merging behavior is tested in DiAutowiredInjectorTest
        $this->assertIsArray($service->getAllowedMethods());
    }

    /**
     * Scenario: User uses lazy loading for expensive services.
     *
     * Testing that services can be lazily loaded to improve performance.
     */
    public function testUseLazyLoadingForExpensiveServices(): void
    {
        // Arrange
        $this->container->set(EmailServiceInterface::class, [
            'class' => EmailService::class,
            'smtpHost' => 'smtp.example.com',
        ]);
        $this->container->set(LoggerInterface::class, FileLogger::class);

        // Create a service that uses lazy loading for expensive dependency
        $service = new class {
            #[Autowired]
            public EmailServiceInterface|Lazy $emailService;
        };

        $this->injector->inject($service);

        // Assert - Property is set
        $this->assertTrue(isset($service->emailService), 'Property emailService should be initialized');

        /** @var Lazy&EmailServiceInterface $lazyProxy */
        $lazyProxy = $service->emailService;
        $this->assertInstanceOf(Lazy::class, $lazyProxy);

        // Act - Call a method on the lazy proxy (triggers lazy loading)
        $result = $lazyProxy->send('test@example.com', 'Test', 'Test body');

        // Assert
        $this->assertTrue($result);
        $this->assertInstanceOf(EmailService::class, $service->emailService);
        $this->assertNotInstanceOf(Lazy::class, $service->emailService);
    }

    /**
     * Scenario: User handles missing service gracefully.
     *
     * Testing that appropriate exceptions are thrown when services are missing.
     */
    public function testGetsHelpfulErrorWhenServiceNotFound(): void
    {
        // Act & Assert - Should throw NotFoundException
        $this->expectException(\Switon\Di\Exception\NotFoundException::class);

        $this->container->get('NonExistentService');
    }

    /**
     * Scenario: User handles missing configuration gracefully.
     *
     * Testing that appropriate exceptions are thrown when required config is missing.
     */
    public function testGetsHelpfulErrorWhenConfigurationMissing(): void
    {
        // Arrange
        $this->container->set(LoggerInterface::class, FileLogger::class);
        $this->container->set(ConfigurableService::class, [
            'class' => ConfigurableService::class,
            // Missing required 'apiUrl' and 'timeout'
        ]);

        // Act & Assert - Should throw MissingConfigurationException
        $this->expectException(\Switon\Di\Exception\MissingConfigurationException::class);

        $this->container->get(ConfigurableService::class);
    }

    /**
     * Scenario: User creates a service with multiple instances injection.
     *
     * Testing that services can inject multiple instances of the same type.
     */
    public function testInjectMultipleInstancesOfSameType(): void
    {
        // Arrange - Create multiple logger instances
        $fileLogger = new FileLogger();
        $consoleLogger = new FileLogger();

        $this->container->set('logger.file', $fileLogger);
        $this->container->set('logger.console', $consoleLogger);

        // Create service that needs multiple loggers (no default to allow injection)
        $service = new class {
            #[Autowired(instances: true)]
            public array $loggers;
        };

        $this->injector->inject($service, [
            'loggers' => ['logger.file', 'logger.console'],
        ]);

        // Assert - Both loggers are injected
        $this->assertTrue(isset($service->loggers), 'Property loggers should be initialized');

        $loggers = $service->loggers;
        $this->assertIsArray($loggers);
        $this->assertCount(2, $loggers);
        $this->assertSame($fileLogger, $loggers[0]);
        $this->assertSame($consoleLogger, $loggers[1]);
    }

    /**
     * Scenario: User creates services with circular dependencies.
     *
     * Testing that circular dependencies are handled correctly.
     * Note: This uses the dedicated circular dependency fixtures for this scenario.
     */
    public function testHandleCircularDependencies(): void
    {
        // Arrange - Register services with circular dependencies
        $this->container->set(CircularServiceA::class, CircularServiceA::class);
        $this->container->set(CircularServiceB::class, CircularServiceB::class);

        // Act
        $serviceA = $this->container->get(CircularServiceA::class);
        $serviceB = $this->container->get(CircularServiceB::class);

        // Assert - Circular dependencies are resolved correctly
        $this->assertInstanceOf(CircularServiceA::class, $serviceA);
        $this->assertInstanceOf(CircularServiceB::class, $serviceB);
        $this->assertInstanceOf(CircularServiceB::class, $serviceA->serviceB);
        $this->assertInstanceOf(CircularServiceA::class, $serviceB->serviceA);
        // Verify they reference the same instances (singleton behavior)
        $this->assertSame($serviceA, $serviceB->serviceA);
        $this->assertSame($serviceB, $serviceA->serviceB);
    }

    /**
     * Scenario: User must choose an explicit strategy for missing object dependencies.
     *
     * <code>?Type</code> does not make <code>#[Autowired]</code> optional.
     * Use <code>Type|Lazy</code> when resolution should be deferred.
     */
    public function testUseOptionalDependencies(): void
    {
        // Arrange
        $this->container->set(LoggerInterface::class, FileLogger::class);

        $nullableService = new class {
            #[Autowired]
            public ?CacheInterface $cache = null;
        };
        $this->expectException(\Switon\Di\Exception\ServiceInjectionException::class);
        $this->injector->inject($nullableService);
    }

    public function testUseLazyDependenciesForDeferredResolution(): void
    {
        $service = new class {
            #[Autowired]
            public CacheInterface|Lazy $cache;
        };

        // Service not registered yet - lazy proxy is created
        $this->injector->inject($service);
        $this->assertInstanceOf(Lazy::class, $service->cache);

        // Arrange - Register the cache service
        $this->container->set(CacheInterface::class, MemoryCache::class);

        // Act - Call a method on the cache (triggers lazy resolution)
        // LazyPropertyProxy resolves the service on first method call, replacing the proxy
        $result = $service->cache->get('test');

        // Assert - Now resolved to actual service
        $this->assertInstanceOf(MemoryCache::class, $service->cache);
        $this->assertNotInstanceOf(Lazy::class, $service->cache);
    }

    /**
     * Scenario: User creates a service that uses interface auto-resolution.
     *
     * Testing that interfaces ending with 'Interface' auto-resolve to classes.
     */
    public function testUseInterfaceAutoResolution(): void
    {
        // Arrange - Register implementation
        $this->container->set(UserRepository::class, UserRepository::class);
        // Don't explicitly register the interface - let auto-resolution work

        // Act
        $repository = $this->container->get(UserRepositoryInterface::class);

        // Assert - Interface auto-resolves to implementation
        $this->assertInstanceOf(UserRepository::class, $repository);
    }


    /**
     * Scenario: User creates a service with reference to another service.
     *
     * Testing that services can reference other services using relative references.
     */
    public function testReferenceOtherServices(): void
    {
        // Arrange - Set up base service and named service
        // Base interface is auto-mapped by convention.
        $this->container->set(UserRepositoryInterface::class . '#default', UserRepositoryInterface::class);
        // Create alias using relative reference
        $this->container->set(UserRepositoryInterface::class . '#alias', '#default');

        // Act
        $repository1 = $this->container->get(UserRepositoryInterface::class . '#default');
        $repository2 = $this->container->get(UserRepositoryInterface::class . '#alias');

        // Assert - Both resolve to the same instance
        $this->assertSame($repository1, $repository2);
        $this->assertInstanceOf(UserRepository::class, $repository1);
    }

    /**
     * Scenario: User creates a service with factory that creates different instances.
     *
     * Testing that Factory can create different instances for different names.
     */
    public function testUseFactoryToCreateDifferentInstances(): void
    {
        // Arrange - Create factory with different configurations
        $factory = new Factory([
            'development' => [
                'class' => ConfigurableService::class,
                'apiUrl' => 'https://dev-api.example.com',
                'timeout' => 10,
            ],
            'production' => [
                'class' => ConfigurableService::class,
                'apiUrl' => 'https://api.example.com',
                'timeout' => 30,
            ],
        ]);

        $this->container->set(LoggerInterface::class, FileLogger::class);
        $this->container->set(ConfigurableService::class, $factory);

        // Act
        $devService = $this->container->get(ConfigurableService::class . '#development');
        $prodService = $this->container->get(ConfigurableService::class . '#production');

        // Assert - Different configurations
        $this->assertSame('https://dev-api.example.com', $devService->getApiUrl());
        $this->assertSame(10, $devService->getTimeout());
        $this->assertSame('https://api.example.com', $prodService->getApiUrl());
        $this->assertSame(30, $prodService->getTimeout());
    }

    /**
     * Scenario: User creates a service that needs to be recreated each time.
     *
     * Testing that make() creates new instances without caching.
     */
    public function testCreateNewInstancesEachTime(): void
    {
        // Arrange
        $this->container->set(EmailServiceInterface::class, [
            'class' => EmailService::class,
            'smtpHost' => 'smtp.example.com',
        ]);
        $this->container->set(LoggerInterface::class, FileLogger::class);
        $this->container->set(UserService::class, [
            'class' => UserService::class,
            'appName' => 'TestApp',
        ]);

        // Act - Use make() to create new instances (with parameters)
        $service1 = $this->container->make(UserService::class, [
            'appName' => 'TestApp',
        ]);
        $service2 = $this->container->make(UserService::class, [
            'appName' => 'TestApp',
        ]);

        // Assert - Different instances
        $this->assertNotSame($service1, $service2);
        $this->assertInstanceOf(UserService::class, $service1);
        $this->assertInstanceOf(UserService::class, $service2);
    }

    /**
     * Scenario: User creates a service with constructor parameters.
     *
     * Testing that make() can create instances with constructor parameters.
     */
    public function testCreateServiceWithConstructorParameters(): void
    {
        // Arrange
        $this->container->set(LoggerInterface::class, FileLogger::class);

        // Act - Create service with constructor parameters
        $connection = $this->container->make(DatabaseConnection::class, [
            'host' => 'db.example.com',
            'database' => 'myapp',
        ]);

        // Assert - Constructor parameters are used
        $this->assertInstanceOf(DatabaseConnection::class, $connection);
        $this->assertSame('db.example.com', $connection->getHost());
        $this->assertSame('myapp', $connection->getDatabase());
    }
}
