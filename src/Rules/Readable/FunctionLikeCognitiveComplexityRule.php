<?php

declare(strict_types=1);

namespace Symplify\CodingStandard\Rules\Readable;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use Symplify\CodingStandard\CognitiveComplexity\AstCognitiveComplexityAnalyzer;
use Symplify\CodingStandard\Exception\ShouldNotHappenException;

/**
 * Based on https://www.sonarsource.com/docs/CognitiveComplexity.pdf
 *
 * A Cognitive Complexity score has 3 rules:
 * - B1. Ignore structures that allow multiple statements to be readably shorthanded into one
 * - B2. Increment (add one) for each break in the linear flow of the code
 * - B3. Increment when flow-breaking structures are nested
 *
 * @see https://www.tomasvotruba.com/blog/2018/05/21/is-your-code-readable-by-humans-cognitive-complexity-tells-you/
 */
final class FunctionLikeCognitiveComplexityRule implements Rule
{
    /**
     * @var int
     */
    private $maximumCognitiveComplexity;

    /**
     * @var AstCognitiveComplexityAnalyzer
     */
    private $astCognitiveComplexityAnalyzer;

    public function __construct(
        AstCognitiveComplexityAnalyzer $astCognitiveComplexityAnalyzer,
        int $maximumCognitiveComplexity = 8
    ) {
        $this->maximumCognitiveComplexity = $maximumCognitiveComplexity;
        $this->astCognitiveComplexityAnalyzer = $astCognitiveComplexityAnalyzer;
    }

    public function getNodeType(): string
    {
        return FunctionLike::class;
    }

    /**
     * @param FunctionLike $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $functionLikeCognitiveComplexity = $this->astCognitiveComplexityAnalyzer->analyzeFunctionLike($node);
        if ($functionLikeCognitiveComplexity <= $this->maximumCognitiveComplexity) {
            return [];
        }

        $functionLikeName = $this->resolveFunctionName($node);

        $message = sprintf(
            'Cognitive complexity for "%s" is %d, keep it under %d',
            $functionLikeName,
            $functionLikeCognitiveComplexity,
            $this->maximumCognitiveComplexity
        );

        return [$message];
    }

    private function resolveFunctionName(FunctionLike $functionLike): string
    {
        if ($functionLike instanceof Function_ || $functionLike instanceof ClassMethod) {
            return (string) $functionLike->name . '()';
        }

        if ($functionLike instanceof Closure) {
            return 'closure';
        }

        if ($functionLike instanceof ArrowFunction) {
            return 'arrow function';
        }

        throw new ShouldNotHappenException();
    }
}
