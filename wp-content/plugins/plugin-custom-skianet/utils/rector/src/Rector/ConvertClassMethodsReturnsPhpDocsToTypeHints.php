<?php

declare(strict_types=1);

namespace Utils\Rector\Rector;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\Type\MixedType;
use Rector\Php\PhpVersionProvider;
use Rector\PHPStanStaticTypeMapper\Enum\TypeKind;
use Rector\Rector\AbstractRector;
use Rector\StaticTypeMapper\StaticTypeMapper;
use Rector\ValueObject\PhpVersionFeature;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class ConvertClassMethodsReturnsPhpDocsToTypeHints extends AbstractRector
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
        return new RuleDefinition('Add method return types where needed', [
            new CodeSample(
                <<<'CODE_SAMPLE'
class SomeClass
{
    /**
     * @return string
     */
    public function process()
    {
        return '';
    }
}
CODE_SAMPLE,
                <<<'CODE_SAMPLE'
class SomeClass
{
    /**
     * @return string
     */
    public function process(): string
    {
        return '';
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

        $this->refactorClassMethodsReturns($node);

        return $this->hasChanged ? $node : null;
    }

    private function refactorClassMethodsReturns(Node $node): void
    {
        foreach ($node->getMethods() as $classMethod) {
            if ($classMethod->getDocComment() === null) {
                continue;
            }

            $phpDocNode = $this->phpDocCreateFromNode($classMethod);

            if (! $phpDocNode instanceof PhpDocNode) {
                continue;
            }

            foreach ($phpDocNode->children as $child) {
                if (! $child->value instanceof ReturnTagValueNode) {
                    continue;
                }

                $type = $child->value->type->name;
                if (empty($type)) {
                    continue;
                }

                $this->refactorMethodWithReturnType($classMethod, $child->value->type, $node);
            }
        }
    }

    private function refactorMethodWithReturnType(ClassMethod $classMethod, TypeNode $typeNode, Node $node): void
    {
        $phpStanType = $this->staticTypeMapper->mapPHPStanPhpDocTypeNodeToPHPStanType($typeNode, $node);
        $returnTypeNode = $this->staticTypeMapper->mapPHPStanTypeToPhpParserNode($phpStanType, TypeKind::RETURN);

        if (! $returnTypeNode instanceof Node) {
            return;
        }

        if ($returnTypeNode->getType() instanceof MixedType && ! $this->phpVersionProvider->isAtLeastPhpVersion(PhpVersionFeature::MIXED_TYPE)) {
            $this->hasChanged = true;
            $classMethod->type = null;

            return;
        }

        $this->hasChanged = true;
        $classMethod->returnType = $returnTypeNode;
    }
}
