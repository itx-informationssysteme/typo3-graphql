<?php

namespace Itx\Typo3GraphQL\Types;

use Exception;
use GraphQL\Error\Error;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\StringValueNode;
use Itx\Typo3GraphQL\Exception\NotImplementedException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class LinkType extends \GraphQL\Type\Definition\ScalarType
{
    public $name = 'Link';

    public $description = 'The `Link` scalar type represents a link to a page, image or an external URL. It is a TypoLink internally, but it is exposed through this GraphQL API as an absolute HTTP Link.';

    /**
     * @inheritDoc
     */
    public function serialize($value)
    {
        $instructions = [
            'parameter' => $value,
            'forceAbsoluteUrl' => true,
            'language' => $args['language'] ?? 0,
        ];

        return GeneralUtility::makeInstance(ContentObjectRenderer::class)->typoLink_URL($instructions);
    }

    /**
     * @inheritDoc
     */
    public function parseValue($value)
    {
        throw new NotImplementedException('Can not parse value of type Link');
    }

    /**
     * @inheritDoc
     */
    public function parseLiteral(Node $valueNode, ?array $variables = null)
    {
        return $valueNode->value;
    }
}
