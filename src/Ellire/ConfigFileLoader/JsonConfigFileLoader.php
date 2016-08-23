<?php

namespace Ellire\ConfigFileLoader;

use Exception;

class JsonConfigFileLoader extends ConfigFileLoader
{
    public function loadFile($resource, $type = null)
    {
        $configValues = json_decode(file_get_contents($resource), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception(json_last_error_msg());
        }

        return $configValues;

    }

    public function getType()
    {
        return 'json';
    }
}