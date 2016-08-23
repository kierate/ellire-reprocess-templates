<?php

namespace Ellire\ConfigFileLoader;

use Symfony\Component\Yaml\Yaml;

class YamlConfigFileLoader extends ConfigFileLoader
{
    public function loadFile($resource, $type = null)
    {
        return Yaml::parse($resource);
    }

    public function getType()
    {
        return 'yml';
    }
}