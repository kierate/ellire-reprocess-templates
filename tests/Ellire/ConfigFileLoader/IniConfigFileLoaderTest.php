<?php

use Ellire\ConfigFileLoader\IniConfigFileLoader;
use Ellire\ConfigFileLoader\ConfigFileNotReadableException;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\FileLocatorInterface;

class IniConfigFileLoaderTest extends PHPUnit_Framework_TestCase
{
    public function testImplementsInterface()
    {
        $configFileLoader = new IniConfigFileLoader($this->getMock(FileLocatorInterface::class));

        $this->assertInstanceOf(LoaderInterface::class, $configFileLoader);
    }

    public function testReturnsCorrectType()
    {
        $configFileLoader = new IniConfigFileLoader($this->getMock(FileLocatorInterface::class));

        $this->assertEquals('ini', $configFileLoader->getType());
    }

    public function testSupportsCorrectFile()
    {
        $configFileLoader = new IniConfigFileLoader($this->getMock(FileLocatorInterface::class));

        $this->assertTrue($configFileLoader->supports('/some/path/to/file.ini'));
    }

    public function testThrowsExceptionOnUnreadableFile()
    {
        $configFileLoader = new IniConfigFileLoader($this->getMock(FileLocatorInterface::class));
        $this->setExpectedException(ConfigFileNotReadableException::class);

        $this->assertTrue($configFileLoader->load('/some/path/to/file.ini'));
    }
}