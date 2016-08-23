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

    public function __construct(LoaderInterface $loader)
    {
        $this->loader = $loader;
    }

    private function getCoreMacroValues()
    {
        return array(
            'profile' => 'dev',
            'config_extension' => 'json', //system config is installed as json, so will not be affected by this
            'deploy_path' => getcwd(),
            'dist_file_extension' => 'template',
            'generated_files_writable' => 'false',
            'macro_opening_string' => '@',
            'macro_closing_string' => '@',
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

    public function resolveRawMacroValuesFromConfigAndEnvironment()
    {
        $profile = null;
        $macros = $this->getCoreMacroValues();

        $macros = $this->loadMacrosFromSystemConfigFile($macros, $profile);
        $macros = $this->loadMacrosFromUserConfigFile($macros, $profile);
        $macros = $this->loadMacrosFromLocalConfigFile($macros, $profile);
        $macros = $this->loadMacrosFromInstanceConfigFile($macros);

        $macros = $this->loadMacrosFromEnvironment($macros);
        $macros = $this->loadMacrosFromOverrides($macros);

        $this->rawMacros = $macros;

        return $this;
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
        return $this->getSystemConfigDirectory() . 'ellire.json';
    }

    private function loadMacrosFromSystemConfigFile($macros, &$profile)
    {
        $macrosFromConfigFile = $this->load($this->getSystemConfigFilePath());

        if (!array_key_exists('global', $macrosFromConfigFile) || !is_array(($macrosFromConfigFile['global']))) {
            throw new RuntimeException("Missing global section is system config file");
        }

        $macros = $this->mergeProfileMacrosFromConfigFile($macros, $macrosFromConfigFile, 'global');

        if (!$profile = $this->getProfileFromOverridesOrEnv()) {
            $profile = $macros['profile'];
        }

        $macros = $this->mergeProfileMacrosFromConfigFile($macros, $macrosFromConfigFile, $profile);

        return $macros;
    }

    private function getHomeConfigFilePath($configExtension)
    {
        return $this->getUserConfigDirectory() . 'ellire.' . $configExtension;
    }

    private function loadMacrosFromUserConfigFile($macros, $profile)
    {
        try {
            $macrosFromConfigFile = $this->load($this->getHomeConfigFilePath($macros['config_extension']));
        } catch (ConfigFileNotReadableException $e) {
            return $macros;
        }

        $macros = $this->mergeProfileMacrosFromConfigFile($macros, $macrosFromConfigFile, 'global');
        $macros = $this->mergeProfileMacrosFromConfigFile($macros, $macrosFromConfigFile, $profile);

        return $macros;
    }

    private function getLocalConfigFilePath($configExtension)
    {
        return $this->getCurrentDirectory() . 'ellire.' . $configExtension;
    }

    private function loadMacrosFromLocalConfigFile($macros, $profile)
    {
        try {
            $macrosFromConfigFile = $this->load($this->getLocalConfigFilePath($macros['config_extension']));
        } catch (ConfigFileNotReadableException $e) {
            return $macros;
        }

        $macros = $this->mergeProfileMacrosFromConfigFile($macros, $macrosFromConfigFile, 'global');
        $macros = $this->mergeProfileMacrosFromConfigFile($macros, $macrosFromConfigFile, $profile);

        return $macros;
    }

    private function getInstanceConfigFilePath($configExtension)
    {
        return $this->getCurrentDirectory() . '.ellire-instance.' . $configExtension;
    }

    private function loadMacrosFromInstanceConfigFile($macros)
    {
        try {
            $macrosFromConfigFile = $this->load($this->getInstanceConfigFilePath($macros['config_extension']));
        } catch (ConfigFileNotReadableException $e) {
            return $macros;
        }

        $macros = $this->mergeAllMacrosFromConfigFile($macros, $macrosFromConfigFile);

        return $macros;
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

        $this->loadMacrosFromSystemConfigFile($this->getCoreMacroValues(), $profile);

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

    private function loadMacrosFromEnvironment(array $macros)
    {
        foreach (array_keys($macros) as $macroName) {
            if (false !== $value = $this->getValueFromEnvVariable($macroName)) {
                $macros[$macroName] = $value;
            }
        }

        return $macros;
    }

    private function loadMacrosFromOverrides(array $macros)
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

    private function getSystemConfigDirectory()
    {
        return '/etc/';
    }

    private function getUserConfigDirectory()
    {
        return getenv('HOME') . '/.ellire/';
    }

    private function getCurrentDirectory()
    {
        return getcwd() . '/';
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
}