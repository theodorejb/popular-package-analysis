<?php

use PackageAnalyzer\Analyzer;
use PhpParser\{NodeVisitorAbstract, ParserFactory};
use PhpParser\Node;

require 'vendor/autoload.php';

$analyzer = new Analyzer();
$parser = (new ParserFactory())->createForHostVersion();

// Parser for the newest PHP version supported by the PHP-Parser library.
$parser = (new ParserFactory())->createForNewestSupportedVersion();

$visitor = new class extends NodeVisitorAbstract {
    public string $path = '';
    public string $code = '';
    public array $tokens = [];
    public int $totalIntegerCasts = 0;
    public int $totalDoubleCasts = 0;
    public int $totalBooleanCasts = 0;
    public int $totalBinaryCasts = 0;
    public int $totalNonStandardCasts = 0;

    public function enterNode(Node $node): void
    {
        if (
            !$node instanceof Node\Expr\Cast\Int_
            && !$node instanceof Node\Expr\Cast\Double
            && !$node instanceof Node\Expr\Cast\Bool_
            && !$node instanceof Node\Expr\Cast\String_
        ) {
            return;
        }

        $text = strtolower($this->tokens[$node->getStartTokenPos()]->text);

        if ($node instanceof Node\Expr\Cast\Int_) {
            if (!str_contains($text, 'integer')) {
                return;
            }
            $this->totalIntegerCasts++;
        }
        if ($node instanceof Node\Expr\Cast\Double) {
            if (!str_contains($text, 'double')) {
                return;
            }
            $this->totalDoubleCasts++;
        }
        if ($node instanceof Node\Expr\Cast\Bool_) {
            if (!str_contains($text, 'boolean')) {
                return;
            }
            $this->totalBooleanCasts++;
        }
        if ($node instanceof Node\Expr\Cast\String_) {
            if (!str_contains($text, 'binary')) {
                return;
            }
            $this->totalBinaryCasts++;
        }

        $this->totalNonStandardCasts++;

        echo "{$this->path}:{$node->getStartLine()}\n";
        echo "    {$this->getCode($node)}\n";
    }

    private function getCode(Node $node): string
    {
        $startPos = $node->getStartFilePos();
        $endPos = $node->getEndFilePos();
        return substr($this->code, $startPos, $endPos - $startPos + 1);
    }
};

$traverser = new PhpParser\NodeTraverser;
$traverser->addVisitor($visitor);

$i = 0;
foreach ($analyzer->getPhpFiles(__DIR__ . '/sources') as $path) {
    if (++$i % 1000 == 0) {
        echo $i . "\n";
    }

    $code = file_get_contents($path);

    if ($code === false) {
        echo "Failed to read $path\n";
        continue;
    }

    try {
        $stmts = $parser->parse($code);
    } catch (PhpParser\Error $e) {
        echo "{$path}\nParse error: {$e->getMessage()}\n";
        continue;
    }

    $visitor->path = $path;
    $visitor->code = $code;
    $visitor->tokens = $parser->getTokens();
    $traverser->traverse($stmts);
}

echo "Total integer casts: {$visitor->totalIntegerCasts}\n";
echo "Total double casts: {$visitor->totalDoubleCasts}\n";
echo "Total boolean casts: {$visitor->totalBooleanCasts}\n";
echo "Total binary casts: {$visitor->totalBinaryCasts}\n";
echo "Total non-standard casts: {$visitor->totalNonStandardCasts}\n";
