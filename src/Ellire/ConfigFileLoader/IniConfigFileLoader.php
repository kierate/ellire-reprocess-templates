<?php

namespace Ellire\ConfigFileLoader;

use Exception;

class IniConfigFileLoader extends ConfigFileLoader
{
    public function loadFile($resource, $type = null)
    {
        $configValues = @parse_ini_file($resource, true, INI_SCANNER_RAW);

        if (false === $configValues) {
            throw new Exception();
        }

        return $configValues;
    }

    public function getType()
    {
        return 'ini';
    }
}