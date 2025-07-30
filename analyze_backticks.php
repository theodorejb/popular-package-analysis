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
    public int $totalShellExpressions = 0;

    public function enterNode(Node $node): void
    {
        if (!$node instanceof Node\Expr\ShellExec) {
            return;
        }

        $this->totalShellExpressions++;

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
    $traverser->traverse($stmts);
}

echo "Total shell expressions: {$visitor->totalShellExpressions}\n";
