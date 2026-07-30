#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Analiza archivos PHP con un AST real (nikic/php-parser, ya instalado como
 * dependencia transitiva del proyecto) y emite JSON por stdout con, para
 * cada método/función con cuerpo: complejidad ciclomática real, anidación
 * máxima de estructuras de control, y señales usadas por
 * architecture_check.py (llamadas de persistencia, SQL crudo, validación
 * inline, response()->json(), relaciones Eloquent, tipos de parámetros y de
 * retorno).
 *
 * A diferencia de la v1 basada en regex (ver git history / KNOWN_ERRORS.md),
 * esto entiende de verdad match, ternarios, heredoc/nowdoc, y cuenta la
 * complejidad/anidación de closures y arrow functions como parte del método
 * que los contiene (porque ejecutarlos es parte de ejecutar el método).
 *
 * Uso: php ast_dump.php <archivo.php> [<archivo2.php> ...]
 * Sale 0 si pudo parsear todo, 1 si algún archivo tuvo un error de sintaxis
 * (se reporta igual el resto del JSON, con "parseError" en ese item), 2 si
 * no se pudo cargar el autoloader de Composer.
 */

$repoRoot = dirname(__DIR__, 4);
$autoload = $repoRoot.'/vendor/autoload.php';
if (! is_file($autoload)) {
    fwrite(STDERR, "ERROR: no se encontró vendor/autoload.php en '$repoRoot'. Corre 'composer install'.\n");
    exit(2);
}
require $autoload;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

final class ComplexityVisitor extends NodeVisitorAbstract
{
    public int $complexity = 1;

    public int $maxDepth = 0;

    /** @var string[] */
    public array $persistenceCalls = [];

    /** @var string[] */
    public array $rawSqlCalls = [];

    /** @var string[] llamadas de SQL crudo cuyo primer argumento NO es un
     * string literal estático — señal de posible inyección SQL. */
    public array $dynamicRawSqlCalls = [];

    public bool $inlineValidate = false;

    /** @var string[] */
    public array $jsonResponseCalls = [];

    public bool $relationshipCall = false;

    public bool $authorizationCall = false;

    private int $depth = 0;

    private const PERSISTENCE_METHODS = ['save', 'delete', 'update'];

    private const PERSISTENCE_STATIC = ['create', 'update', 'delete'];

    private const DB_RAW_METHODS = ['select', 'statement', 'raw'];

    private const DB_PERSISTENCE_METHODS = ['table', 'insert', 'update', 'delete'];

    private const RAW_SQL_METHODS = ['whereRaw', 'havingRaw', 'orWhereRaw'];

    private const RELATIONSHIP_METHODS = [
        'hasMany', 'belongsTo', 'hasOne', 'belongsToMany', 'morphMany', 'morphTo',
        'morphOne', 'hasManyThrough', 'hasOneThrough', 'morphToMany', 'morphedByMany',
    ];

    private const DEPTH_NODE_CLASSES = [
        Node\Stmt\If_::class,
        Node\Stmt\For_::class,
        Node\Stmt\Foreach_::class,
        Node\Stmt\While_::class,
        Node\Stmt\Do_::class,
        Node\Stmt\Switch_::class,
        Node\Stmt\TryCatch::class,
    ];

    public function enterNode(Node $node)
    {
        foreach (self::DEPTH_NODE_CLASSES as $cls) {
            if ($node instanceof $cls) {
                $this->depth++;
                $this->maxDepth = max($this->maxDepth, $this->depth);
                break;
            }
        }

        if ($node instanceof Node\Stmt\If_ || $node instanceof Node\Stmt\ElseIf_
            || $node instanceof Node\Stmt\For_ || $node instanceof Node\Stmt\Foreach_
            || $node instanceof Node\Stmt\While_ || $node instanceof Node\Stmt\Do_
            || $node instanceof Node\Stmt\Catch_) {
            $this->complexity++;
        } elseif ($node instanceof Node\Stmt\Case_ && $node->cond !== null) {
            $this->complexity++;
        } elseif ($node instanceof Node\Expr\Ternary) {
            $this->complexity++;
        } elseif ($node instanceof Node\Expr\BinaryOp\BooleanAnd || $node instanceof Node\Expr\BinaryOp\BooleanOr
            || $node instanceof Node\Expr\BinaryOp\LogicalAnd || $node instanceof Node\Expr\BinaryOp\LogicalOr) {
            $this->complexity++;
        } elseif ($node instanceof Node\Expr\Match_) {
            $this->complexity += max(count($node->arms) - 1, 0);
        }

        if ($node instanceof Node\Expr\StaticCall && $node->class instanceof Node\Name
            && $node->name instanceof Node\Identifier) {
            $className = $node->class->getLast();
            $methodName = $node->name->toString();
            if ($className === 'DB' && in_array($methodName, self::DB_RAW_METHODS, true)) {
                $this->rawSqlCalls[] = "DB::$methodName";
                if (self::hasDynamicFirstArg($node->args)) {
                    $this->dynamicRawSqlCalls[] = "DB::$methodName";
                }
            } elseif ($className === 'DB' && in_array($methodName, self::DB_PERSISTENCE_METHODS, true)) {
                $this->persistenceCalls[] = "DB::$methodName";
            } elseif (in_array($methodName, self::PERSISTENCE_STATIC, true)) {
                $this->persistenceCalls[] = "$className::$methodName";
            }
            if ($className === 'Gate') {
                $this->authorizationCall = true;
            }
        }

        if ($node instanceof Node\Expr\MethodCall && $node->name instanceof Node\Identifier) {
            $methodName = $node->name->toString();
            if (in_array($methodName, self::PERSISTENCE_METHODS, true)) {
                $this->persistenceCalls[] = "->$methodName";
            }
            if (in_array($methodName, self::RAW_SQL_METHODS, true)) {
                $this->rawSqlCalls[] = "->$methodName";
                if (self::hasDynamicFirstArg($node->args)) {
                    $this->dynamicRawSqlCalls[] = "->$methodName";
                }
            }
            if ($methodName === 'validate') {
                $this->inlineValidate = true;
            }
            if ($methodName === 'json' && $node->var instanceof Node\Expr\FuncCall
                && $node->var->name instanceof Node\Name && $node->var->name->toString() === 'response') {
                $this->jsonResponseCalls[] = 'response()->json()';
            }
            if (in_array($methodName, self::RELATIONSHIP_METHODS, true)
                && $node->var instanceof Node\Expr\Variable && $node->var->name === 'this') {
                $this->relationshipCall = true;
            }
            if ($methodName === 'authorize' || $methodName === 'can' || $methodName === 'cannot') {
                $this->authorizationCall = true;
            }
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        foreach (self::DEPTH_NODE_CLASSES as $cls) {
            if ($node instanceof $cls) {
                $this->depth--;
                break;
            }
        }

        return null;
    }

    /**
     * true si el primer argumento NO es un string literal estático (ej. una
     * variable, una concatenación, una interpolación) — señal de que el SQL
     * crudo podría estar armándose con input externo sin parametrizar.
     *
     * @param  Node\Arg[]  $args
     */
    private static function hasDynamicFirstArg(array $args): bool
    {
        foreach ($args as $arg) {
            if (! $arg instanceof Node\Arg) {
                continue;
            }

            return ! $arg->value instanceof Node\Scalar\String_;
        }

        return false;
    }
}

final class MethodCollector extends NodeVisitorAbstract
{
    /** @var array<int, array<string, mixed>> */
    public array $methods = [];

    public ?string $className = null;

    /** @var string[]|null */
    public ?array $fillable = null;

    /** @var string[]|null */
    public ?array $guarded = null;

    /** @var string[]|null */
    public ?array $hidden = null;

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\ClassLike && $node->name !== null) {
            $this->className = $node->name->toString();
            $this->extractClassAttributes($node);
        }

        if ($node instanceof Node\Stmt\Property) {
            $this->extractProperty($node);
        }

        if (($node instanceof Node\Stmt\ClassMethod || $node instanceof Node\Stmt\Function_)
            && $node->getStmts() !== null) {
            $this->methods[] = $this->analyzeFunctionLike($node);
        }

        return null;
    }

    /**
     * Detecta #[Fillable([...])] / #[Guarded([...])] / #[Hidden([...])] a nivel
     * de clase (sintaxis de atributos de Laravel 11+/12 para Eloquent).
     */
    private function extractClassAttributes(Node\Stmt\ClassLike $class): void
    {
        foreach ($class->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                $name = $attr->name->toString();
                $values = null;
                foreach ($attr->args as $arg) {
                    $values = self::arrayExprToStrings($arg->value);
                    break;
                }
                if ($values === null) {
                    continue;
                }
                if ($name === 'Fillable' || str_ends_with($name, '\\Fillable')) {
                    $this->fillable = $values;
                } elseif ($name === 'Guarded' || str_ends_with($name, '\\Guarded')) {
                    $this->guarded = $values;
                } elseif ($name === 'Hidden' || str_ends_with($name, '\\Hidden')) {
                    $this->hidden = $values;
                }
            }
        }
    }

    /**
     * Detecta `protected $fillable = [...]` / `$guarded = [...]` / `$hidden = [...]`
     * como propiedad clásica (la otra forma de declarar esto en Eloquent).
     */
    private function extractProperty(Node\Stmt\Property $prop): void
    {
        if (count($prop->props) !== 1 || $prop->props[0]->default === null) {
            return;
        }
        $propName = $prop->props[0]->name->toString();
        $values = self::arrayExprToStrings($prop->props[0]->default);
        if ($values === null) {
            return;
        }
        if ($propName === 'fillable') {
            $this->fillable = $values;
        } elseif ($propName === 'guarded') {
            $this->guarded = $values;
        } elseif ($propName === 'hidden') {
            $this->hidden = $values;
        }
    }

    /** @return string[]|null */
    private static function arrayExprToStrings(Node $expr): ?array
    {
        if (! $expr instanceof Node\Expr\Array_) {
            return null;
        }
        $out = [];
        foreach ($expr->items as $item) {
            if ($item instanceof Node\Expr\ArrayItem && $item->value instanceof Node\Scalar\String_) {
                $out[] = $item->value->value;
            }
        }

        return $out;
    }

    /**
     * @param  Node\Stmt\ClassMethod|Node\Stmt\Function_  $node
     */
    private function analyzeFunctionLike(Node $node): array
    {
        $visibility = 'public';
        $isStatic = false;
        if ($node instanceof Node\Stmt\ClassMethod) {
            if ($node->isPrivate()) {
                $visibility = 'private';
            } elseif ($node->isProtected()) {
                $visibility = 'protected';
            }
            $isStatic = $node->isStatic();
        }

        $analyzer = new ComplexityVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($analyzer);
        $traverser->traverse($node->getStmts());

        $paramTypes = [];
        foreach ($node->getParams() as $param) {
            if ($param->type !== null) {
                $paramTypes[] = self::typeToString($param->type);
            }
        }
        $returnType = $node->getReturnType() !== null ? self::typeToString($node->getReturnType()) : null;

        return [
            'name' => $node->name->toString(),
            'visibility' => $visibility,
            'static' => $isStatic,
            'startLine' => $node->getStartLine(),
            'endLine' => $node->getEndLine(),
            'complexity' => $analyzer->complexity,
            'maxNesting' => $analyzer->maxDepth,
            'persistenceCalls' => $analyzer->persistenceCalls,
            'rawSqlCalls' => $analyzer->rawSqlCalls,
            'dynamicRawSqlCalls' => $analyzer->dynamicRawSqlCalls,
            'inlineValidate' => $analyzer->inlineValidate,
            'jsonResponseCalls' => $analyzer->jsonResponseCalls,
            'relationshipCall' => $analyzer->relationshipCall,
            'authorizationCall' => $analyzer->authorizationCall,
            'paramTypes' => $paramTypes,
            'returnType' => $returnType,
        ];
    }

    private static function typeToString(Node $type): string
    {
        if ($type instanceof Node\NullableType) {
            return '?'.self::typeToString($type->type);
        }
        if ($type instanceof Node\UnionType || $type instanceof Node\IntersectionType) {
            return implode('|', array_map([self::class, 'typeToString'], $type->types));
        }
        if ($type instanceof Node\Name || $type instanceof Node\Identifier) {
            return $type->toString();
        }

        return (string) $type;
    }
}

$files = array_slice($argv, 1);
if ($files === []) {
    fwrite(STDERR, "Uso: php ast_dump.php <archivo.php> [<archivo2.php> ...]\n");
    exit(2);
}

$parser = (new ParserFactory())->createForNewestSupportedVersion();
$output = [];
$hadError = false;

foreach ($files as $file) {
    if (! is_file($file)) {
        $output[] = ['file' => $file, 'parseError' => 'archivo no encontrado'];
        $hadError = true;

        continue;
    }

    $code = file_get_contents($file);

    try {
        $ast = $parser->parse($code);
    } catch (Error $e) {
        $output[] = ['file' => $file, 'parseError' => $e->getMessage()];
        $hadError = true;

        continue;
    }

    $collector = new MethodCollector();
    $traverser = new NodeTraverser();
    $traverser->addVisitor($collector);
    $traverser->traverse($ast ?? []);

    $output[] = [
        'file' => $file,
        'class' => $collector->className,
        'fillable' => $collector->fillable,
        'guarded' => $collector->guarded,
        'hidden' => $collector->hidden,
        'methods' => $collector->methods,
    ];
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo "\n";
exit($hadError ? 1 : 0);
