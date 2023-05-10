<?php

namespace Itx\Typo3GraphQL\Types\Model;

use GraphQL\Language\AST\Node;
use Itx\Typo3GraphQL\Exception\NotImplementedException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class RangeInputType extends \GraphQL\Type\Definition\ScalarType implements TypeNameInterface
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
        //TODO ask BJR how to implement function
        return GeneralUtility::makeInstance(ContentObjectRenderer::class);
    }

    public function parseValue($value)
    {
        throw new NotImplementedException('Can not parse value of type RangeInput');
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null)
    {
        // TODO: Implement parseLiteral() method.
    }

    public static function getTypeName(): string
    {
        return 'RangeInput';
    }
}
