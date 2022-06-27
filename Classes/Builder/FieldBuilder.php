<?php

namespace Itx\Typo3GraphQL\Builder;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Itx\Typo3GraphQL\Exception\UnsupportedTypeException;
use JetBrains\PhpStorm\ArrayShape;

class FieldBuilder
{
    private string $name;

    private ?Type $type = null;

    private string|null $description = null;

    private string|null $deprecationReason = null;

    /** @psalm-var callable(mixed, array, mixed, ResolveInfo) : mixed|null */
    private $resolve;

    /** @psalm-var array<string, array<string, mixed>>|null */
    private array|null $args = null;

    final private function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @param string $name
     *
     * @return FieldBuilder
     */
    public static function create(string $name): self
    {
        return new static($name);
    }

    /**
     * @return $this
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return $this
     */
    public function addArgument(string $name, Type $type, ?string $description = null, mixed $defaultValue = null): self
    {
        if ($this->args === null) {
            $this->args = [];
        }

        $value = ['type' => $type];

        if ($description !== null) {
            $value['description'] = $description;
        }

        if ($defaultValue !== null) {
            $value['defaultValue'] = $defaultValue;
        }

        $this->args[$name] = $value;

        return $this;
    }

    /**
     * @see ResolveInfo
     *
     * @param callable(mixed, array, mixed, ResolveInfo) : mixed $resolver
     *
     * @return $this
     */
    public function setResolver(callable $resolver): self
    {
        $this->resolve = $resolver;

        return $this;
    }

    /**
     * @return $this
     */
    public function setDeprecationReason(string $reason): self
    {
        $this->deprecationReason = $reason;

        return $this;
    }

    /**
     * @param Type $type
     *
     * @return FieldBuilder
     */
    public function setType(Type $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function hasType(): bool
    {
        return $this->type !== null;
    }

    public function getType(): ?Type
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     * @throws UnsupportedTypeException
     */
    #[ArrayShape([
        'args' => "\mixed[][]|null",
        'name' => "string",
        'description' => "null|string",
        'deprecationReason' => "null|string",
        'resolve' => "callable",
        'type' => Type::class,
    ])] public function build(): array
    {
        if ($this->type === null) {
            throw new UnsupportedTypeException('Type must be set', 1589716096);
        }

        return [
            'args' => $this->args,
            'name' => $this->name,
            'description' => $this->description,
            'deprecationReason' => $this->deprecationReason,
            'resolve' => $this->resolve,
            'type' => $this->type,
        ];
    }
}
