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
    public int $totalSleepMethods = 0;
    public int $totalWakeupMethods = 0;
    public array $packages = [];

    public function enterNode(Node $node): void
    {
        if (!$node instanceof Node\Stmt\ClassMethod) {
            return;
        }

        $methodName = strtolower($node->name);

        if ($methodName !== '__sleep' && $methodName !== '__wakeup') {
            return;
        }

        $package = explode('\\', $this->path);
        $package = implode('\\', array_slice($package, 0, 2));

        if (!isset($this->packages[$package])) {
            $this->packages[$package] = [
                'Total sleep' => 0,
                'Total wakeup' => 0,
                'Empty sleep' => 0,
                'Empty wakeup' => 0,
                'Throw-only sleep' => 0,
                'Throw-only wakeup' => 0,
                'Other sleep' => 0,
                'Other wakeup' => 0,
            ];
        }

        $stmts = $node->stmts ?? [];
        $stmts = array_filter($stmts, fn($node) => !$node instanceof Node\Stmt\Nop);
        $stmts = array_values($stmts);
        $numStmts = count($stmts);
        $singleThrow = false;

        if ($numStmts === 1) {
            $stmt = $stmts[0];
            if ($stmt instanceof Node\Stmt\Expression && $stmt->expr instanceof Node\Expr\Throw_) {
                $singleThrow = true;
            }
        }

        if ($methodName === '__sleep') {
            $this->totalSleepMethods++;
            $this->packages[$package]['Total sleep']++;

            if ($numStmts === 0) {
                $this->packages[$package]['Empty sleep']++;
            } elseif ($singleThrow) {
                $this->packages[$package]['Throw-only sleep']++;
            } else {
                $this->packages[$package]['Other sleep']++;
            }
        } else {
            $this->totalWakeupMethods++;
            $this->packages[$package]['Total wakeup']++;

            if ($numStmts === 0) {
                $this->packages[$package]['Empty wakeup']++;
            } elseif ($singleThrow) {
                $this->packages[$package]['Throw-only wakeup']++;
            } else {
                $this->packages[$package]['Other wakeup']++;
            }
        }

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

$directory = __DIR__ . '/sources';
$directoryLen = strlen($directory) + 1;

$i = 0;
foreach ($analyzer->getPhpFiles($directory) as $path) {
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

    $visitor->path = substr($path, $directoryLen);
    $visitor->code = $code;
    $traverser->traverse($stmts);
}

echo "Total __sleep methods: {$visitor->totalSleepMethods}\n";
echo "Total __wakeup methods: {$visitor->totalWakeupMethods}\n";
echo count($visitor->packages) . " distinct packages\n";

$firstPackage = array_key_first($visitor->packages);
$columns = array_keys($visitor->packages[$firstPackage]);

echo "<table>
<thead>
<tr>
    <th>Package</th>";

foreach ($columns as $column) {
    echo "    <th>{$column}</th>\n";
}

echo "
</tr>
</thead>
<tbody>";

foreach ($visitor->packages as $package => $info) {
    echo "    <tr><td>" . htmlspecialchars($package) . "</td>";
    foreach ($info as $value) {
        echo "        <td>{$value}</td>\n";
    }
    echo "</tr>\n";
}

echo "
</tbody></table>
";
