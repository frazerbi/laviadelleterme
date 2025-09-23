<?php

declare(strict_types=1);

namespace Utils\Rector\Rector;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocChildNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory;
use Rector\Comments\NodeDocBlock\DocBlockUpdater;
use Rector\Php\PhpVersionProvider;
use Rector\Rector\AbstractRector;
use Rector\StaticTypeMapper\StaticTypeMapper;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

use function in_array;

final class ConvertClassMethodsRemoveUselessPhpDocsFromTypeHints extends AbstractRector
{
    use CanCreateNodeFromPhpDoc;

    /**
     * @readonly
     */
    private DocBlockUpdater $docBlockUpdater;

    private bool $hasChanged = false;

    /**
     * @readonly
     */
    private PhpDocInfoFactory $phpDocInfoFactory;

    /**
     * @readonly
     */
    private PhpVersionProvider $phpVersionProvider;

    /**
     * @readonly
     */
    private StaticTypeMapper $staticTypeMapper;

    public function __construct(DocBlockUpdater $docBlockUpdater, PhpDocInfoFactory $phpDocInfoFactory, PhpVersionProvider $phpVersionProvider, StaticTypeMapper $staticTypeMapper)
    {
        $this->docBlockUpdater = $docBlockUpdater;
        $this->phpDocInfoFactory = $phpDocInfoFactory;
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
        return new RuleDefinition('Remove phpdoc types where not needed', [
            new CodeSample(
                <<<'CODE_SAMPLE'
class SomeClass
{
    /**
     * @param string $name
     */
    public function process(string $name)
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

        $this->refactorClassMethodsReturn($node);

        return $this->hasChanged ? $node : null;
    }

    private function refactorClassMethodsParameters(Node $node): void
    {
        $hasChanged = false;

        foreach ($node->getMethods() as $classMethod) {
            /** @var ClassMethod $classMethod */
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

            foreach ($classMethod->params as $position => $param) {
                $phpDocChild = $phpDocNode->children[$position] ?? null;

                if (! $phpDocChild instanceof PhpDocTagNode) {
                    continue;
                }

                if ($param->type === null) {
                    continue;
                }

                if ($phpDocChild->value->type->type === null) {
                    continue;
                }

                if ($phpDocChild->value->type->genericTypes !== null) {
                    continue;
                }

                if ($param->type->name !== $phpDocChild->value->type->type->name) {
                    continue;
                }

                $phpDocInfo = $this->phpDocInfoFactory->createFromNodeOrEmpty($classMethod);

                if ($phpDocChild->name === '@var') {
                    $hasChanged = true;
                    $phpDocInfo->removeByType(VarTagValueNode::class);
                }

                if ($phpDocChild->name === '@param') {
                    $hasChanged = true;
                    $phpDocInfo->removeByType(ParamTagValueNode::class);
                }
            }
        }

        if ($hasChanged) {
            $this->hasChanged = true;
            $this->docBlockUpdater->updateRefactoredNodeWithPhpDocInfo($classMethod);
        }
    }

    private function refactorClassMethodsReturn(Node $node): void
    {
        $hasChanged = false;

        foreach ($node->getMethods() as $classMethod) {
            /** @var ClassMethod $classMethod */
            if ($classMethod->params === null) {
                continue;
            }

            $phpDocNode = $this->phpDocCreateFromNode($classMethod);

            if (! $phpDocNode instanceof PhpDocNode) {
                continue;
            }

            $phpDocNode->children = array_values(
                array_filter($phpDocNode->children, static fn (PhpDocChildNode $phpDocChildNode): bool => ($phpDocChildNode->name ?? '') === '@return')
            );

            foreach ($phpDocNode->children as $child) {
                if ($child->name !== '@return') {
                    continue;
                }

                if ($child->value->type->type === null) {
                    continue;
                }

                if ($child->value->type->genericTypes !== null) {
                    continue;
                }

                if ($classMethod->returnType !== null && $classMethod->returnType->name === $child->value->type->type->name) {
                    $hasChanged = true;
                    $phpDocInfo = $this->phpDocInfoFactory->createFromNodeOrEmpty($classMethod);
                    $phpDocInfo->removeByType(ReturnTagValueNode::class);
                }
            }

            if ($hasChanged) {
                $this->hasChanged = true;
                $this->docBlockUpdater->updateRefactoredNodeWithPhpDocInfo($classMethod);
            }
        }
    }
}
