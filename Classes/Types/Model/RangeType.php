<?php

namespace Itx\Typo3GraphQL\Types\Model;

use GraphQL\Language\AST\Node;
use Itx\Typo3GraphQL\Exception\NotImplementedException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class RangeType extends \GraphQL\Type\Definition\ScalarType implements TypeNameInterface
{
    /**
     * @var string
     */
    protected $min;

    /**
     * @var string
     */
    protected $max;

    public function __construct(array $config = [])
    {
        $this->name = self::getTypeName();
        parent::__construct($config);
    }

    public function serialize($value)
    {
        return GeneralUtility::makeInstance(ContentObjectRenderer::class);
    }

    public function parseValue($value)
    {
        throw new NotImplementedException('Can not parse value of type Range');
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null)
    {
        return $valueNode->value;
    }

    public static function getTypeName(): string
    {
        return 'Range';
    }
}
