<?php

use Ellire\ConfigFileLoader\JsonConfigFileLoader;
use Ellire\ConfigFileLoader\ConfigFileNotReadableException;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\FileLocatorInterface;

class JsonConfigFileLoaderTest extends PHPUnit_Framework_TestCase
{
    public function testImplementsInterface()
    {
        $configFileLoader = new JsonConfigFileLoader($this->getMock(FileLocatorInterface::class));

        $this->assertInstanceOf(LoaderInterface::class, $configFileLoader);
    }

    public function testReturnsCorrectType()
    {
        $configFileLoader = new JsonConfigFileLoader($this->getMock(FileLocatorInterface::class));

        $this->assertEquals('json', $configFileLoader->getType());
    }

    public function testSupportsCorrectFile()
    {
        $configFileLoader = new JsonConfigFileLoader($this->getMock(FileLocatorInterface::class));

        $this->assertTrue($configFileLoader->supports('/some/path/to/file.json'));
    }

    public function testThrowsExceptionOnUnreadableFile()
    {
        $configFileLoader = new JsonConfigFileLoader($this->getMock(FileLocatorInterface::class));
        $this->setExpectedException(ConfigFileNotReadableException::class);

        $this->assertTrue($configFileLoader->load('/some/path/to/file.json'));
    }
}