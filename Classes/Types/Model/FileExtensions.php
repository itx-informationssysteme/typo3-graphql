<?php

namespace Itx\Typo3GraphQL\Types\Model;

use GraphQL\Type\Definition\EnumType;
use Itx\Typo3GraphQL\Utility\NamingUtility;
use SimPod\GraphQLUtils\Builder\EnumBuilder;
use SimPod\GraphQLUtils\Exception\InvalidArgument;

class FileExtensions extends EnumType implements TypeNameInterface
{
    public $description = 'Available file extensions for image manipulation, when file is an image';

    /**
     * @throws InvalidArgument
     */
    public function __construct()
    {
        $objectBuilder = EnumBuilder::create(self::getTypeName());

        $fileExtensions = explode(',', $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']);

        foreach ($fileExtensions as $fileExtension) {
            $objectBuilder->addValue(trim($fileExtension), NamingUtility::generateName($fileExtension, false));
        }
        
        parent::__construct($objectBuilder->build());
    }

    public static function getTypeName(): string
    {
        return 'FileExtensions';
    }
}
