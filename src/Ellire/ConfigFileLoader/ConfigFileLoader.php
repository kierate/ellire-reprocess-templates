<?php

namespace Ellire\ConfigFileLoader;

use Exception;
use Symfony\Component\Config\Loader\FileLoader;

abstract class ConfigFileLoader extends FileLoader
{
    abstract protected function loadFile($resource, $type = null);

    abstract protected function getType();

    public function load($resource, $type = null)
    {
        if (!is_readable($resource)) {
            throw new ConfigFileNotReadableException(
                sprintf('The file (%s) is either not readable or cannot be found', $resource)
            );
        }

        try {
            return $this->loadFile($resource, $type);
        } catch (Exception $e) {
           throw new ConfigFileParseException(
               sprintf('File (%s) could not be parsed as %s', $resource, $this->getTypeLabel())
           );
       }
    }

    public function supports($resource, $type = null)
    {
        return is_string($resource) && $this->getType() === pathinfo($resource, PATHINFO_EXTENSION);
    }

    public function getTypeLabel()
    {
        return strtoupper($this->getType());
    }
}