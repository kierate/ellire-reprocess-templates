<?php

use Ellire\ConfigFileLoader\XmlConfigFileLoader;
use Ellire\ConfigFileLoader\ConfigFileNotReadableException;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\FileLocatorInterface;

class XmlConfigFileLoaderTest extends PHPUnit_Framework_TestCase
{
    public function testImplementsInterface()
    {
        $configFileLoader = new XmlConfigFileLoader($this->getMock(FileLocatorInterface::class));

        $this->assertInstanceOf(LoaderInterface::class, $configFileLoader);
    }

    public function testReturnsCorrectType()
    {
        $configFileLoader = new XmlConfigFileLoader($this->getMock(FileLocatorInterface::class));

        $this->assertEquals('xml', $configFileLoader->getType());
    }

    public function testSupportsCorrectFile()
    {
        $configFileLoader = new XmlConfigFileLoader($this->getMock(FileLocatorInterface::class));

        $this->assertTrue($configFileLoader->supports('/some/path/to/file.xml'));
    }

    public function testThrowsExceptionOnUnreadableFile()
    {
        $configFileLoader = new XmlConfigFileLoader($this->getMock(FileLocatorInterface::class));
        $this->setExpectedException(ConfigFileNotReadableException::class);

        $this->assertTrue($configFileLoader->load('/some/path/to/file.xml'));
    }
}