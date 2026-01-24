<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Integration\Core;

use VelvetCMS\Core\ConfigRepository;
use VelvetCMS\Tests\Support\TestCase;

final class ConfigRepositoryTest extends TestCase
{
    private string $testConfigPath;
    private ConfigRepository $config;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testConfigPath = $this->tmpDir . '/config';
        mkdir($this->testConfigPath, 0755, true);
        
        // Create test config files
        file_put_contents($this->testConfigPath . '/app.php', "<?php\nreturn ['name' => 'TestApp', 'debug' => true];");
        file_put_contents($this->testConfigPath . '/database.php', "<?php\nreturn ['driver' => 'sqlite', 'connections' => ['test' => ['host' => 'localhost']]];");
        file_put_contents($this->testConfigPath . '/nested.php', "<?php\nreturn ['level1' => ['level2' => ['level3' => 'deep value']]];");
        
        $this->config = new ConfigRepository($this->testConfigPath);
    }

    public function testGetSimpleConfigValue(): void
    {
        $this->assertSame('TestApp', $this->config->get('app.name'));
    }
    
    public function testGetNestedConfigValueWithDotNotation(): void
    {
        $this->assertSame('deep value', $this->config->get('nested.level1.level2.level3'));
    }
    
    public function testGetReturnsDefaultWhenKeyNotFound(): void
    {
        $this->assertSame('default-value', $this->config->get('nonexistent.key', 'default-value'));
    }
    
    public function testGetEntireConfigFile(): void
    {
        $app = $this->config->get('app');
        
        $this->assertIsArray($app);
        $this->assertSame('TestApp', $app['name']);
        $this->assertTrue($app['debug']);
    }
    
    public function testSetSimpleValue(): void
    {
        $this->config->set('app.version', '1.0.0');
        $this->assertSame('1.0.0', $this->config->get('app.version'));
    }
    
    public function testSetNestedValueWithDotNotation(): void
    {
        $this->config->set('database.connections.test.port', 3306);
        $this->assertSame(3306, $this->config->get('database.connections.test.port'));
    }
}
