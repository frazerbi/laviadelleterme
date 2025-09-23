<?php

declare(strict_types=1);

namespace Utils\Rector\Rector;

use PhpParser\Node;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Interface_;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocChildNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
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

use function count;
use function in_array;

final class ConvertClassMethodsParametersPhpDocsToTypeHints extends AbstractRector
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
        return new RuleDefinition('Add param types where needed', [
            new CodeSample(
                <<<'CODE_SAMPLE'
class SomeClass
{
    /**
     * @param string $name
     */
    public function process($name)
    {
    }
}
CODE_SAMPLE,
                <<<'CODE_SAMPLE'
class SomeClass
{
    public function process(string $name)
    {
    }
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

        $this->refactorClassMethodsParameters($node);

        return $this->hasChanged ? $node : null;
    }

    private function refactorClassMethodsParameters(Node $node): void
    {
        foreach ($node->getMethods() as $classMethod) {
            if ($classMethod->params === null) {
                continue;
            }

            $phpDocNode = $this->phpDocCreateFromNode($classMethod);

            if (! $phpDocNode instanceof PhpDocNode) {
                continue;
            }

            $phpDocNode->children = array_values(
                array_filter($phpDocNode->children, static fn (PhpDocChildNode $phpDocChildNode): bool => in_array($phpDocChildNode->name ?? '', ['@param', '@var'], true))
            );

            if (count($phpDocNode->children) !== count($classMethod->params)) {
                continue;
            }

            foreach ($classMethod->params as $position => $param) {
                if ($param->type !== null) {
                    continue;
                }

                $phpDocChild = $phpDocNode->children[$position] ?? null;

                if (! $phpDocChild instanceof PhpDocTagNode) {
                    continue;
                }

                if (! $phpDocChild->value instanceof ParamTagValueNode && ! $phpDocChild->value instanceof VarTagValueNode) {
                    continue;
                }

                $type = $phpDocChild->value->type->name;
                if (empty($type)) {
                    continue;
                }

                $this->refactorMethodWithParamType($param, $phpDocChild->value->type);
            }
        }
    }

    private function refactorMethodWithParamType(Param $param, TypeNode $typeNode): void
    {
        $phpStanType = $this->staticTypeMapper->mapPHPStanPhpDocTypeNodeToPHPStanType($typeNode, $param);
        $paramTypeNode = $this->staticTypeMapper->mapPHPStanTypeToPhpParserNode($phpStanType, TypeKind::PARAM);

        if (! $paramTypeNode instanceof Node) {
            return;
        }

        if ($paramTypeNode->getType() instanceof MixedType && ! $this->phpVersionProvider->isAtLeastPhpVersion(PhpVersionFeature::MIXED_TYPE)) {
            $this->hasChanged = true;
            $param->type = null;

            return;
        }

        $this->hasChanged = true;
        $param->type = $paramTypeNode;
    }
}
