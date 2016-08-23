<?php

namespace Ellire\Macro;

use Ellire\ConfigFileLoader\ConfigFileNotReadableException;
use RuntimeException;
use Ellire\Template\Processor\TemplateProcessorInterface;
use Symfony\Component\Config\Loader\LoaderInterface;

class MacroResolver
{
    private $loader;
    private $rawMacros = null;
    private $macroOverrides = null;
    private $processedMacros = null;
    private $macrosResolutionPath = array();
    private $configFilesUsed = array();
    private $envVariablesUsed = array();
    private $systemConfigFilePath;
    private $userConfigFilePath;

    private $macrosFromSystemConfigFile = array();
    private $macrosFromUserConfigFile = array();
    private $macrosFromLocalConfigFile = array();
    private $macrosFromInstanceConfigFile = array();


    public function __construct(LoaderInterface $loader, $systemConfigFilePath, $userConfigFilePath)
    {
        $this->loader = $loader;
        $this->systemConfigFilePath = $systemConfigFilePath;
        $this->userConfigFilePath = $userConfigFilePath;
    }

    private function getCoreMacroValues()
    {
        return array(
            //these 3 have an impact on how Ellire resolves the macros from files,
            //they can only be set and will only be read from the system and/or user config
            'profile' => 'dev',
            'config_extension' => 'json', //system and user config will not be affected by this
            'deploy_path' => getcwd(),
            //these 4 control macro processing and reading/writing from/to template files
            'macro_opening_string' => '@',
            'macro_closing_string' => '@',
            'dist_file_extension' => 'template',
            'generated_files_writable' => 'false',
        );
    }

    private function getMacrosThatCannotBeProcessed()
    {
        return array(
            'macro_opening_string',
            'macro_closing_string',
        );
    }

    public function setMacroOverrides(array $macros)
    {
        $this->macroOverrides = $macros;
    }

    public function getRawMacroValues()
    {
        return $this->rawMacros;
    }

    private function resolveCoreConfigMacroValue($macroName)
    {
        if (isset($this->macroOverrides) && array_key_exists($macroName, $this->macroOverrides)) {
            return $this->macroOverrides[$macroName];
        }

        if (false !== $profile = $this->getValueFromEnvVariable($macroName)) {
            return $profile;
        }

        if (!empty($this->macrosFromUserConfigFile) &&
            isset($this->macrosFromUserConfigFile['globals'][$macroName])) {
            return $this->macrosFromUserConfigFile['globals'][$macroName];
        }

        if (!empty($this->macrosFromSystemConfigFile) &&
            isset($this->macrosFromSystemConfigFile['globals'][$macroName])) {
            return $this->macrosFromSystemConfigFile['globals'][$macroName];
        }

        $coreMacros = $this->getCoreMacroValues();

        return $coreMacros[$macroName];
    }
    
    public function resolveProfile()
    {
        return $this->resolveCoreConfigMacroValue('profile');
    }

    public function resolveRawMacroValuesFromConfigAndEnvironment()
    {
        $this->loadSystemConfigFile();
        $this->loadUserConfigFile();

        $profile = $this->resolveCoreConfigMacroValue('profile');
        $deploymentPath = $this->getRealPath($this->resolveCoreConfigMacroValue('deploy_path'));
        $configExtension = $this->resolveCoreConfigMacroValue('config_extension');

        $this->loadLocalConfigFile($deploymentPath, $configExtension);
        $this->loadInstanceConfigFile($deploymentPath, $configExtension);

        $macros = $this->getMergedProfileMacrosFromConfigFiles($profile);
        $macros = $this->updateFromEnvironment($macros);
        $macros = $this->updateFromOverrides($macros);

        $macros['profile'] = $profile;
        $macros['deploy_path'] = $deploymentPath;
        $macros['config_extension'] = $configExtension;

        $this->rawMacros = $macros;

        return $this;
    }

    private function getMergedProfileMacrosFromConfigFiles($profile)
    {
        //bare minimum
        $macros = $this->getCoreMacroValues();

        //system-wide config
        $macros = $this->mergeProfileMacrosFromConfigFile($macros, $this->macrosFromSystemConfigFile, 'globals');
        $macros = $this->mergeProfileMacrosFromConfigFile($macros, $this->macrosFromSystemConfigFile, $profile);

        //user-level config
        $macros = $this->mergeProfileMacrosFromConfigFile($macros, $this->macrosFromUserConfigFile, 'globals');
        $macros = $this->mergeProfileMacrosFromConfigFile($macros, $this->macrosFromUserConfigFile, $profile);

        //local config for the application being processed
        $macros = $this->mergeProfileMacrosFromConfigFile($macros, $this->macrosFromLocalConfigFile, 'globals');
        $macros = $this->mergeProfileMacrosFromConfigFile($macros, $this->macrosFromLocalConfigFile, $profile);

        //instance config for the application being processed
        $macros = $this->mergeAllMacrosFromConfigFile($macros, $this->macrosFromInstanceConfigFile);

        return $macros;
    }

    public function getProcessedMacroValues()
    {
        return $this->processedMacros;
    }

    public function processAllMacroValues(TemplateProcessorInterface $templateProcessor, array $allNeededMacros)
    {
        //for macros that are used in templates but we don't know about yet
        //if they have a value in the environment then add it in.
        //past this point anything that's missing will cause errors while generating the final files
        $macros = $this->addAnyMissingMacrosUsedInTemplates($allNeededMacros);

        $changed = true;
        $this->macrosResolutionPath = array();
        while ($changed) {
            $previousMacros = $macros;

            foreach ($macros as $macroName => $macroValue) {
                if (in_array($macroName, $this->getMacrosThatCannotBeProcessed())) {
                    continue;
                }

                $template = $templateProcessor->loadTemplate($macroName);
                $knownMacros = $template->getAllKnownMacros();
                $this->throwExceptionOnMacrosResolutionInfiniteLoop($macroName, $knownMacros);

                $macros[$macroName] = $template->render($macros);
            }

            //run again if there was any change to the macros
            $changed = $previousMacros != $macros;
        }

        $this->processedMacros = $macros;

        return $this;
    }

    private function mergeProfileMacrosFromConfigFile($allMacros, $macrosFromConfigFile, $profile)
    {
        if (array_key_exists($profile, $macrosFromConfigFile)) {
            $allMacros = array_merge($allMacros, $macrosFromConfigFile[$profile]);
        }

        return $allMacros;
    }

    private function mergeAllMacrosFromConfigFile($allMacros, $macrosFromConfigFile)
    {
        return array_merge($allMacros, $macrosFromConfigFile);
    }

    private function getSystemConfigFilePath()
    {
        return $this->systemConfigFilePath;
    }

    private function loadSystemConfigFile()
    {
        try {
            $macrosFromConfigFile = $this->load($this->getSystemConfigFilePath());
        } catch (ConfigFileNotReadableException $e) {
            //allow installations without a system-wide config
            return;
        }

        if (!array_key_exists('globals', $macrosFromConfigFile) || !is_array(($macrosFromConfigFile['globals']))) {
            throw new RuntimeException("Missing globals section is system config file");
        }

        $this->macrosFromSystemConfigFile = $macrosFromConfigFile;
    }

    private function getHomeConfigFilePath()
    {
        return $this->userConfigFilePath;
    }

    private function loadUserConfigFile()
    {
        try {
            $macrosFromConfigFile = $this->load($this->getHomeConfigFilePath());
        } catch (ConfigFileNotReadableException $e) {
            //allow installations without a system-wide config
            return;
        }

        $this->macrosFromUserConfigFile = $macrosFromConfigFile;
    }

    private function getLocalConfigFilePath($deploymentPath, $configExtension)
    {
        return $deploymentPath . DIRECTORY_SEPARATOR . 'ellire.' . $configExtension;
    }

    private function loadLocalConfigFile($deploymentPath, $configExtension)
    {
        try {
            $macrosFromConfigFile = $this->load($this->getLocalConfigFilePath($deploymentPath, $configExtension));
        } catch (ConfigFileNotReadableException $e) {
            //possible not to have the deployment (local) config
            return;
        }

        $this->macrosFromLocalConfigFile = $macrosFromConfigFile;
    }

    private function getInstanceConfigFilePath($deploymentPath, $configExtension)
    {
        return $deploymentPath . DIRECTORY_SEPARATOR . '.ellire-instance.' . $configExtension;
    }

    private function loadInstanceConfigFile($deploymentPath, $configExtension)
    {
        try {
            $macrosFromConfigFile = $this->load($this->getInstanceConfigFilePath($deploymentPath, $configExtension));
        } catch (ConfigFileNotReadableException $e) {
            //possible not to have any instance config
            return;
        }

        $this->macrosFromInstanceConfigFile = $macrosFromConfigFile;
    }

    private function load($file)
    {
        $data = $this->loader->load($file);

        if (!is_array($data)) {
            return array();
        }

        $this->configFilesUsed[] = $file;

        return $data;
    }

    public function getConfigFilesUsed()
    {
        return $this->configFilesUsed;
    }

    public function getProfile()
    {
        if ($profile = $this->getProfileFromOverridesOrEnv()) {
            return $profile;
        }

        $this->loadSystemConfigFile($this->getCoreMacroValues(), $profile);

        return $profile;
    }

    private function getProfileFromOverridesOrEnv()
    {
        if (isset($this->macroOverrides) && array_key_exists('profile', $this->macroOverrides)) {
            return $this->macroOverrides['profile'];
        }

        if (false !== $profile = $this->getValueFromEnvVariable('profile')) {
            return $profile;
        }

        return false;
    }

    private function updateFromEnvironment(array $macros)
    {
        foreach (array_keys($macros) as $macroName) {
            if (false !== $value = $this->getValueFromEnvVariable($macroName)) {
                $macros[$macroName] = $value;
            }
        }

        return $macros;
    }

    private function updateFromOverrides(array $macros)
    {
        if (empty($this->macroOverrides)) {
            return $macros;
        }

        foreach (array_keys($macros) as $macroName) {
            if (array_key_exists($macroName, $this->macroOverrides)) {
                $macros[$macroName] = $this->macroOverrides[$macroName];
            }
        }

        return $macros;
    }

    private function getValueFromEnvVariable($macroName)
    {
        $envVariableName = 'ELLIRE_' . strtoupper(preg_replace('[^a-zA-Z0-9]', '_', $macroName));

        if (false !== $value = getenv($envVariableName)) {
            $this->envVariablesUsed[$macroName] = $envVariableName;
        }

        return $value;
    }

    public function getEnvVariablesUsed()
    {
        return $this->envVariablesUsed;
    }

    private function addAnyMissingMacrosUsedInTemplates(array $allNeededMacros)
    {
        $macros = $this->getRawMacroValues();
        $missing = array_diff($allNeededMacros, array_keys($macros));

        foreach ($missing as $missingMacroName) {
            if (false !== $value = $this->getValueFromEnvVariable($missingMacroName)) {
                $macros[$missingMacroName] = $value;
            }
        }

        return $macros;
    }

    private function throwExceptionOnMacrosResolutionInfiniteLoop($parentMacro, $childMacros)
    {
        if (is_string($childMacros)) {
            $childMacros = array($childMacros);
        }

        if (count($childMacros) == 0) {
            return;
        }

        foreach ($childMacros as $childMacro) {
            if (!array_key_exists($parentMacro, $this->macrosResolutionPath)) {
                $this->macrosResolutionPath[$parentMacro] = array($parentMacro, $childMacro);
            }

            //go through all items and append the new child to this parent wherever the parent exists
            foreach ($this->macrosResolutionPath as $root => $children) {
                //only check further if the last macro equals the current parent
                if (end($children) != $parentMacro) {
                    continue;
                }

                //with the new child in place check that it is not the same as the root
                if (reset($children) == $childMacro) {
                    throw new RuntimeException("Infinite loop with macro resolution in $parentMacro ($parentMacro=>" . implode('=>', $children) . ")");
                }

                //append the current child
                $this->macrosResolutionPath[$root] = array_merge($children, array($childMacro));
            }
        }
    }

    private function getRealPath($path)
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        return realpath($path);
    }
}