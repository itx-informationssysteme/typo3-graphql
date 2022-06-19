<?php

namespace Itx\Typo3GraphQL\Types;

use GraphQL\Type\Definition\Type;
use SimPod\GraphQLUtils\Builder\FieldBuilder;
use SimPod\GraphQLUtils\Builder\ObjectBuilder;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;

class FileType extends \GraphQL\Type\Definition\ObjectType
{
    public $name = 'File';

    public $description = 'A file object with some additional information including a publicly accessible URL';

    public function __construct()
    {
        /** @var ImageService $imageService */
        $imageService =  GeneralUtility::makeInstance(ImageService::class);
        // TODO: API for image processing

        $objectBuilder = ObjectBuilder::create($this->name);

        $fields = [];
        $fields[] = FieldBuilder::create('fileName', Type::string())
                                ->setResolver(fn(FileInterface $root) => $root->getName())
                                ->setDescription('Filename with extension')
                                ->build();
        $fields[] = FieldBuilder::create('extension', Type::string())
                                ->setResolver(fn(FileInterface $root) => $root->getExtension())
                                ->setDescription('File extension')
                                ->build();
        $fields[] = FieldBuilder::create('url', Type::string())
                                ->setResolver(fn(FileInterface $root) => $imageService->getImageUri($root, true))
                                ->setDescription('Absolute URL to file')
                                ->build();
        $fields[] = FieldBuilder::create('fileSize', Type::string())
                                ->setResolver(fn(FileInterface $root) => $root->getSize())
                                ->setDescription('Filesize in Bytes')
                                ->build();

        $objectBuilder->setFields($fields);

        parent::__construct($objectBuilder->build());
    }
}
