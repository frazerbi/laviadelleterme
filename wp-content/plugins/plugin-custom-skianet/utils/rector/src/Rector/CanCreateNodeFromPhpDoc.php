<?php

declare(strict_types=1);

namespace Utils\Rector\Rector;

use PhpParser\Node\Stmt;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;

trait CanCreateNodeFromPhpDoc
{
    public function phpDocCreateFromNode(Stmt $stmt): ?PhpDocNode
    {
        $lexer = new Lexer();
        $constExprParser = new ConstExprParser();
        $typeParser = new TypeParser($constExprParser);
        $phpDocParser = new PhpDocParser($typeParser, $constExprParser);

        if ($stmt->getDocComment() === null) {
            return null;
        }

        $tokens = new TokenIterator(
            $lexer->tokenize(
                $stmt->getDocComment()
                    ->getText()
            )
        );

        return $phpDocParser->parse($tokens);
    }
}
