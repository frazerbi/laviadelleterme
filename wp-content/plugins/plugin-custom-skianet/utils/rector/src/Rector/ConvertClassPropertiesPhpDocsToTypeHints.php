<?php

declare(strict_types=1);

namespace Utils\Rector\Rector;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Property;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\Type\MixedType;
use Rector\Php\PhpVersionProvider;
use Rector\PHPStanStaticTypeMapper\Enum\TypeKind;
use Rector\Rector\AbstractRector;
use Rector\StaticTypeMapper\StaticTypeMapper;
use Rector\ValueObject\PhpVersionFeature;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class ConvertClassPropertiesPhpDocsToTypeHints extends AbstractRector
{
    use CanCreateNodeFromPhpDoc;

    private bool $hasChanged = false;

    /**
     * @readonly
     */
    private PhpVersionProvider $phpVersionProvider;

    /**
     * @readonly
     */
    private StaticTypeMapper $staticTypeMapper;

    public function __construct(PhpVersionProvider $phpVersionProvider, StaticTypeMapper $staticTypeMapper)
    {
        $this->phpVersionProvider = $phpVersionProvider;
        $this->staticTypeMapper = $staticTypeMapper;
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Class_::class, Interface_::class];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Add properties types where needed', [
            new CodeSample(
                <<<'CODE_SAMPLE'
class SomeClass
{
    /**
     * @var string $name
     */
    private $name;
}
CODE_SAMPLE,
                <<<'CODE_SAMPLE'
class SomeClass
{
    /**
     * @var string $name
     */
    private string $name;
}
CODE_SAMPLE
            ),
        ]);
    }

    public function refactor(Node $node): ?Node
    {
        if ($node->stmts === null) {
            return null;
        }

        $this->hasChanged = false;

        $this->refactorClassProperties($node);

        return $this->hasChanged ? $node : null;
    }

    private function refactorClassProperties(Node $node): void
    {
        foreach ($node->getProperties() as $property) {
            if ($property->getDocComment() === null) {
                continue;
            }

            $phpDocNode = $this->phpDocCreateFromNode($property);

            if (! $phpDocNode instanceof PhpDocNode) {
                continue;
            }

            foreach ($phpDocNode->children as $child) {
                if (! $child->value instanceof ParamTagValueNode && ! $child->value instanceof VarTagValueNode) {
                    continue;
                }

                $type = $child->value->type->name;
                if (empty($type)) {
                    continue;
                }

                $this->refactorPropertyWithVarType($property, $child->value->type, $node);
            }
        }
    }

    private function refactorPropertyWithVarType(Property $property, TypeNode $typeNode, Node $node): void
    {
        $phpStanType = $this->staticTypeMapper->mapPHPStanPhpDocTypeNodeToPHPStanType($typeNode, $node);
        $propertyTypeNode = $this->staticTypeMapper->mapPHPStanTypeToPhpParserNode($phpStanType, TypeKind::PROPERTY);

        if (! $propertyTypeNode instanceof Node) {
            return;
        }

        if ($propertyTypeNode->getType() instanceof MixedType && ! $this->phpVersionProvider->isAtLeastPhpVersion(PhpVersionFeature::MIXED_TYPE)) {
            $this->hasChanged = true;
            $property->type = null;

            return;
        }

        $this->hasChanged = true;
        $property->type = $propertyTypeNode;
    }
}
