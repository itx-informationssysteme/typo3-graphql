<?php

namespace Itx\Typo3GraphQL\Types\Model;

use GraphQL\Type\Definition\Type;
use SimPod\GraphQLUtils\Builder\FieldBuilder;
use SimPod\GraphQLUtils\Builder\ObjectBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Service\ImageService;

class FileType extends \GraphQL\Type\Definition\ObjectType implements TypeNameInterface
{
    public $description = 'A file object with some additional information including a publicly accessible URL';

    public function __construct()
    {
        /** @var ImageService $imageService */
        $imageService = GeneralUtility::makeInstance(ImageService::class);
        // TODO: API for image processing

        $objectBuilder = ObjectBuilder::create(self::getTypeName());

        $fields = [];
        $fields[] = FieldBuilder::create('fileName', Type::nonNull(Type::string()))->setResolver(fn(FileReference $root) => $root->getOriginalResource()?->getName() ?? '')->setDescription('Filename with extension')->build();
        $fields[] = FieldBuilder::create('extension', Type::nonNull(Type::string()))->setResolver(fn(FileReference $root) => $root->getOriginalResource()?->getExtension() ?? '')->setDescription('File extension')->build();
        $fields[] = FieldBuilder::create('url', Type::nonNull(Type::string()))->setResolver(function(FileReference $root) use ($imageService) {
            if ($root->getOriginalResource() === null) {
                return '';
            }

            return $imageService->getImageUri($root->getOriginalResource(), true);
        })->setDescription('Absolute URL to file')->build();
        $fields[] = FieldBuilder::create('fileSize', Type::nonNull(Type::int()))->setResolver(fn(FileReference $root) => $root->getOriginalResource()?->getSize() ?? 0)->setDescription('Filesize in Bytes')->build();

        $objectBuilder->setFields($fields);

        parent::__construct($objectBuilder->build());
    }

    public static function getTypeName(): string
    {
        return 'File';
    }
}
