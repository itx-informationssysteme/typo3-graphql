<?php

namespace Itx\Typo3GraphQL\Types\Model;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Itx\Typo3GraphQL\Exception\BadInputException;
use Itx\Typo3GraphQL\Exception\NameNotFoundException;
use Itx\Typo3GraphQL\Resolver\ResolverContext;
use Itx\Typo3GraphQL\Types\TypeRegistry;
use SimPod\GraphQLUtils\Builder\FieldBuilder;
use SimPod\GraphQLUtils\Builder\ObjectBuilder;
use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Service\ImageService;

class FileType extends \GraphQL\Type\Definition\ObjectType implements TypeNameInterface
{
    public const ARGUMENT_WIDTH = 'width';
    public const ARGUMENT_HEIGHT = 'height';
    public const ARGUMENT_MIN_WIDTH = 'minWidth';
    public const ARGUMENT_MIN_HEIGHT = 'minHeight';
    public const ARGUMENT_MAX_WIDTH = 'maxWidth';
    public const ARGUMENT_MAX_HEIGHT = 'maxHeight';
    public const ARGUMENT_CROP = 'crop';
    public const FILE_EXTENSION = 'fileExtension';

    public ?string $description = 'A file object with some additional information including a publicly accessible URL';

    /**
     * @throws NameNotFoundException
     */
    public function __construct()
    {
        /** @var ImageService $imageService */
        $imageService = GeneralUtility::makeInstance(ImageService::class);

        $objectBuilder = ObjectBuilder::create(self::getTypeName());

        $fields = [];
        $fields[] = FieldBuilder::create('fileName', Type::nonNull(Type::string()))
                                ->setResolver(fn(FileReference $root) => $root->getOriginalResource()?->getName() ?? '')
                                ->setDescription('Filename with extension')
                                ->build();
        $fields[] = FieldBuilder::create('extension', Type::nonNull(Type::string()))
                                ->setResolver(fn(FileReference $root) => $root->getOriginalResource()?->getExtension() ?? '')
                                ->setDescription('File extension')
                                ->build();
        $fields[] = FieldBuilder::create('url', Type::nonNull(Type::string()))->setResolver(function(FileReference $root,
                                                                                                     array         $args,
                                                                                                                   $context,
                                                                                                     ResolveInfo   $resolveInfo) use
        (
            $imageService
        ) {
            if ($root->getOriginalResource() === null) {
                return '';
            }

            return $imageService->getImageUri($root->getOriginalResource(), true);
        })->setDescription('Absolute URL to file')->build();

        $fields[] = FieldBuilder::create('fileSize', Type::nonNull(Type::int()))
                                ->setResolver(fn(FileReference $root) => $root->getOriginalResource()?->getSize() ?? 0)
                                ->setDescription('Filesize in Bytes')
                                ->build();

        $fields[] = FieldBuilder::create('derivative', Type::string())
                                ->addArgument(self::ARGUMENT_WIDTH, Type::int())
                                ->addArgument(self::ARGUMENT_HEIGHT, Type::int())
                                ->addArgument(self::ARGUMENT_MIN_WIDTH, Type::int())
                                ->addArgument(self::ARGUMENT_MAX_WIDTH, Type::int())
                                ->addArgument(self::ARGUMENT_MIN_HEIGHT, Type::int())
                                ->addArgument(self::ARGUMENT_MAX_HEIGHT, Type::int())
                                ->addArgument(self::ARGUMENT_CROP, Type::string())
                                ->addArgument(self::FILE_EXTENSION, TypeRegistry::fileExtensions())
                                ->setDescription('Derivative of the file. Returns the absolute URL to the processed file.')
                                ->setResolver(function(FileReference   $root,
                                                       array           $args,
                                                       ResolverContext $context,
                                                       ResolveInfo     $resolveInfo) use ($imageService) {
                                    if ($root->getOriginalResource() === null) {
                                        return '';
                                    }

                                    $image = $root->getOriginalResource();

                                    $cropString = $image->getProperty('crop') ?? '';
                                    $cropVariantCollection = CropVariantCollection::create((string)$cropString);
                                    $cropVariant = $args[self::ARGUMENT_CROP] ?? 'default';
                                    $cropArea = $cropVariantCollection->getCropArea($cropVariant);

                                    $processingInstructions = [
                                        'width' => $this->checkIfSizeIsAllowed($args[self::ARGUMENT_WIDTH] ?? null, $context),
                                        'height' => $this->checkIfSizeIsAllowed($args[self::ARGUMENT_HEIGHT] ?? null, $context),
                                        'minWidth' => $this->checkIfSizeIsAllowed($args[self::ARGUMENT_MIN_WIDTH] ?? null,
                                                                                  $context),
                                        'minHeight' => $this->checkIfSizeIsAllowed($args[self::ARGUMENT_MIN_HEIGHT] ?? null,
                                                                                   $context),
                                        'maxWidth' => $this->checkIfSizeIsAllowed($args[self::ARGUMENT_MAX_WIDTH] ?? null,
                                                                                  $context),
                                        'maxHeight' => $this->checkIfSizeIsAllowed($args[self::ARGUMENT_MAX_HEIGHT] ?? null,
                                                                                   $context),
                                        'crop' => $cropArea->isEmpty() ? null : $cropArea->makeAbsoluteBasedOnFile($image),
                                    ];

                                    if (isset($args[self::FILE_EXTENSION])) {
                                        $processingInstructions['fileExtension'] =
                                            $this->checkIfFileTypeIsAllowed($args[self::FILE_EXTENSION], $context);
                                    }

                                    $processedFile = $imageService->applyProcessingInstructions($image, $processingInstructions);

                                    return $imageService->getImageUri($processedFile, true);
                                })
                                ->build();

        $fields[] = FieldBuilder::create('alternative', Type::string())
                                ->setDescription('Returns the alternative text for this file')
                                ->setResolver(function(FileReference   $root,
                                                       array           $args,
                                                       ResolverContext $context,
                                                       ResolveInfo     $resolveInfo) {
                                    if ($root->getOriginalResource() === null) {
                                        return '';
                                    }

                                    return $root->getOriginalResource()->getAlternative();
                                })
                                ->build();

        $fields[] = FieldBuilder::create('link', Type::string())
                                ->setDescription('Returns the link for this file reference. This is the link that can be edited in the background. Not the link to the file.')
                                ->setResolver(function(FileReference   $root,
                                                       array           $args,
                                                       ResolverContext $context,
                                                       ResolveInfo     $resolveInfo) {
                                    if ($root->getOriginalResource() === null) {
                                        return '';
                                    }

                                    return $root->getOriginalResource()->getLink();
                                })
                                ->build();

        $objectBuilder->setFields($fields);

        parent::__construct($objectBuilder->build());
    }

    /**
     * @throws BadInputException
     */
    protected function checkIfFileTypeIsAllowed(string|null $fileExtension, ResolverContext $resolverContext): string|null
    {
        if ($fileExtension === null) {
            return null;
        }

        $result = in_array($fileExtension,
                           $resolverContext->getConfigurationService()->getSettings()['imageManipulation']['allowedImageTypes'] ??
                               [],
                           true);

        if (!$result) {
            throw new BadInputException("File type '$fileExtension' not allowed", 1610612736);
        }

        return $fileExtension;
    }

    /**
     * @throws BadInputException
     */
    protected function checkIfSizeIsAllowed(int|null $size, ResolverContext $resolverContext): int|null
    {
        if ($size === null) {
            return null;
        }

        $result = in_array($size,
                           $resolverContext->getConfigurationService()->getSettings()['imageManipulation']['allowedImageSizes'] ??
                               [],
                           true);

        if (!$result) {
            throw new BadInputException("Image size '$size' not allowed", 1610612737);
        }

        return $size;
    }

    public static function getTypeName(): string
    {
        return 'File';
    }
}
