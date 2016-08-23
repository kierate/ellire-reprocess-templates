<?php

namespace Ellire;

use Exception;
use Ellire\Command;
use Ellire\ConfigFileLoader;
use Ellire\FileGenerator;
use Ellire\Macro\MacroExtractor;
use Ellire\Macro\MacroResolver;
use Ellire\Template\Finder\TemplateFinder;
use Ellire\Template\Processor\TemplateStringProcessor;
use Ellire\Template\Processor\TemplateFileProcessor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    private $macroExtractor;
    private $macroProcessor;
    private $macroResolver;
    private $templateFinder;
    private $templateFileProcessor;

    /**
     * Constructor.
     *
     * @param string $name    The name of the application
     * @param string $version The version of the application
     */
    public function __construct($name = 'UNKNOWN', $version = 'UNKNOWN')
    {
        parent::__construct('Ellire', $this->getVersion());

        $this->setDefaultCommand('reprocess-templates');
    }

    /**
     * Get all available commands
     *
     * @return array
     */
    protected function getDefaultCommands()
    {
        $commands = array_merge(parent::getDefaultCommands(), array(
            new Command\GetProfileCommand(),
            new Command\ListMacrosCommand(),
            new Command\ReprocessTemplatesCommand(),
            new Command\TemplateMacrosCommand(),
            new Command\InstallDefaultSystemConfigCommand(),
        ));

        return $commands;
    }

    /**
     * Get the version
     *
     * The version is updated in composer.json with each tag.
     * @return string
     * @throws Exception
     */
    public function getVersion()
    {
        if (false === $composerJsonAsString = @file_get_contents(__DIR__ . '/../../composer.json')) {
            throw new Exception('Could not read composer.json');
        }

        $composerJson = json_decode($composerJsonAsString);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception(json_last_error_msg());
        }

        return $composerJson->version;
    }

    public function getLocalPath($relative = null)
    {
        $path = __DIR__ . '/../../';
        if (isset($relative)) {
            $path .= DIRECTORY_SEPARATOR . $relative;
        }

        return realpath($path);
    }

    public function isGloballyInstalled()
    {
        if (file_exists($this->getLocalPath('../../autoload.php')) &&
            !file_exists($this->getLocalPath('vendor/autoload.php'))) {
            return true;
        }

        return false;
    }

    public function getSystemConfigFilePath()
    {
        return '/etc/ellire.json';
    }

    public function getUserConfigFilePath()
    {
        return getenv('HOME') . '/.ellire/ellire.json';
    }

    /**
     * @return MacroResolver
     */
    public function getMacroResolver()
    {
        if (isset($this->macroResolver)) {
            return $this->macroResolver;
        }

        $locator = new FileLocator();
        $loaderResolver = new LoaderResolver(array(
            new ConfigFileLoader\JsonConfigFileLoader($locator),
            new ConfigFileLoader\YamlConfigFileLoader($locator),
            new ConfigFileLoader\XmlConfigFileLoader($locator),
            new ConfigFileLoader\IniConfigFileLoader($locator),
        ));

        $loader = new DelegatingLoader($loaderResolver);

        $this->macroResolver = new MacroResolver(
            $loader,
            $this->getSystemConfigFilePath(),
            $this->getUserConfigFilePath()
        );
        return $this->macroResolver;
    }

    /**
     * @return TemplateFinder
     */
    public function getTemplateFinder()
    {
        if (isset($this->templateFinder)) {
            return $this->templateFinder;
        }

        $macros = $this->getMacroResolver()->getRawMacroValues();
        $this->templateFinder = new TemplateFinder(
            $macros['deploy_path'],
            $macros['dist_file_extension'],
            array_key_exists('template_exclude_paths', $macros) ? $macros['template_exclude_paths'] : null
        );

        return $this->templateFinder;
    }

    /**
     * @return TemplateFileProcessor
     */
    public function getTemplateFileProcessor()
    {
        if (isset($this->templateFileProcessor)) {
            return $this->templateFileProcessor;
        }

        $this->templateFileProcessor = new TemplateFileProcessor($this->getMacroResolver()->getRawMacroValues());

        return $this->templateFileProcessor;
    }

    /**
     * @return TemplateStringProcessor
     */
    public function getTemplateStringProcessor()
    {
        if (isset($this->macroProcessor)) {
            return $this->macroProcessor;
        }

        $this->macroProcessor = new TemplateStringProcessor($this->getMacroResolver()->getRawMacroValues());

        return $this->macroProcessor;
    }

    /**
     * @return MacroExtractor
     */
    public function getMacroExtractor()
    {
        if (isset($this->macroExtractor)) {
            return $this->macroExtractor;
        }

        $this->macroExtractor = new MacroExtractor($this->getTemplateFinder(), $this->getTemplateFileProcessor());

        return $this->macroExtractor;
    }
    
    /**
     * @return FileGenerator
     */
    public function getFileGenerator()
    {
        if (isset($this->fileWriter)) {
            return $this->fileWriter;
        }

        $this->fileWriter = new FileGenerator(
            $this->getTemplateFinder(),
            $this->getTemplateFileProcessor(),
            $this->getMacroResolver()->getProcessedMacroValues()
        );

        return $this->fileWriter;
    }
}