<?php

namespace Itx\Typo3GraphQL\Utility;

class NamingUtility
{
    public static function generateName(string $name, bool $isMultiple): string
    {
        $name = trim($name);
        $name = preg_replace('/\.|\s/', '_', $name);
        $name = preg_replace('/-|:|\(|\)|__/', '', $name);

        if ($isMultiple) {
            $name = self::appendMultiple($name);
        }

        return $name;
    }

    public static function generateNameFromClassPath(string $classPath, bool $isMultiple): string
    {
        $explodedPath = explode('\\', $classPath);

        $name = $explodedPath[array_key_last($explodedPath)];

        if ($isMultiple) {
            $name = self::appendMultiple($name);
        }

        return strtolower($name);
    }

    private static function appendMultiple(string $name): string
    {
        if ($name === '') {
            return $name;
        }

        if ($name[strlen($name) - 1] !== 's') {
            if ($name[strlen($name) - 1] === 'y') {
                $name = substr($name, 0, -1) . 'ies';
            } else {
                $name .= 's';
            }
        }

        return $name;
    }
}
