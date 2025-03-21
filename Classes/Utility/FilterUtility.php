<?php

namespace Itx\Typo3GraphQL\Utility;

class FilterUtility
{
    public static function resetAlias(): void
    {
        global $joinedTables;

        $joinedTables = [];
    }

    public static function handleAlias($baseAlias): string
    {
        global $joinedTables;

        if (!isset($joinedTables)) {
            $joinedTables = [];
        }

        //extract the base name
        if (preg_match('/^(.*?)(\d+)?$/', $baseAlias, $matches)) {
            $namePart = $matches[1];
            $currentNumber = isset($matches[2]) ? (int)$matches[2] : 0; //current number at the end
        } else {
            $namePart = $baseAlias;
            $currentNumber = 0;
        }

        $maxNumber = $currentNumber;

        //check alias in joined tables array
        foreach ($joinedTables as $item) {
            if (preg_match('/^' . preg_quote($namePart, '/') . '(\d+)?$/', $item, $matches)) {
                $existingNumber = isset($matches[1]) ? (int)$matches[1] : 0;
                $maxNumber = max($maxNumber, $existingNumber);
            }
        }

        //if name or number does not exist, use this
        if (!in_array($baseAlias, $joinedTables, true)) {
            $joinedTables[] = $baseAlias;
            return $baseAlias;
        }

        //otherwise the highest number + 1
        $newAlias = $namePart . ($maxNumber + 1);
        $joinedTables[] = $newAlias;
        return $newAlias;
    }
}
