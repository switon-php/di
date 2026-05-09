<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Integration;

use Switon\Core\App;
use Switon\Core\AppInterface;
use Switon\Core\SceneManagerInterface;
use Switon\Core\SceneManager;
use Switon\Di\ServiceProvider;
use Switon\Di\Tests\TestCase;

final class ServiceProviderIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $provider = new ServiceProvider();
        $provider->register($this->container);
    }

    public function testServiceProviderResolvesDefaultAppBindingThroughDi(): void
    {
        $app = $this->container->get(AppInterface::class);

        $this->assertInstanceOf(App::class, $app);
        $this->assertSame('switon', $app->id());
        $this->assertSame('Switon Application', $app->name());
        $this->assertSame('prod', $app->env());
    }

    public function testServiceProviderAppliesConfiguredAppDefinitionThroughDi(): void
    {
        $this->container->set(AppInterface::class, [
            'id' => 'admin',
            'name' => 'Admin Panel',
            'version' => '2.0.0',
            'env' => 'dev',
            'debug' => true,
            'timezone' => 'Asia/Shanghai',
        ]);

        $app = $this->container->get(AppInterface::class);

        $this->assertInstanceOf(App::class, $app);
        $this->assertSame('admin', $app->id());
        $this->assertSame('Admin Panel', $app->name());
        $this->assertSame('2.0.0', $app->version());
        $this->assertSame('dev', $app->env());
        $this->assertTrue($app->isDebug());
        $this->assertSame('Asia/Shanghai', $app->timezone());
    }

    public function testServiceProviderResolvesDedicatedSceneManagerServiceThroughDi(): void
    {
        $sceneManager = $this->container->get(SceneManagerInterface::class);
        $app = $this->container->get(AppInterface::class);

        $this->assertInstanceOf(SceneManager::class, $sceneManager);
        $this->assertNotSame($app, $sceneManager);
        $this->assertSame('default', $sceneManager->getScene());
    }
}
