<?php

use Ellire\ConfigFileLoader\YamlConfigFileLoader;
use Ellire\ConfigFileLoader\ConfigFileNotReadableException;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\FileLocatorInterface;

class YamlConfigFileLoaderTest extends PHPUnit_Framework_TestCase
{
    public function testImplementsInterface()
    {
        $configFileLoader = new YamlConfigFileLoader($this->getMock(FileLocatorInterface::class));

        $this->assertInstanceOf(LoaderInterface::class, $configFileLoader);
    }

    public function testReturnsCorrectType()
    {
        $configFileLoader = new YamlConfigFileLoader($this->getMock(FileLocatorInterface::class));

        $this->assertEquals('yml', $configFileLoader->getType());
    }

    public function testSupportsCorrectFile()
    {
        $configFileLoader = new YamlConfigFileLoader($this->getMock(FileLocatorInterface::class));

        $this->assertTrue($configFileLoader->supports('/some/path/to/file.yml'));
    }

    public function testThrowsExceptionOnUnreadableFile()
    {
        $configFileLoader = new YamlConfigFileLoader($this->getMock(FileLocatorInterface::class));
        $this->setExpectedException(ConfigFileNotReadableException::class);

        $this->assertTrue($configFileLoader->load('/some/path/to/file.yml'));
    }
}