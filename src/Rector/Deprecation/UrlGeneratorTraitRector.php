<?php

namespace Drupal8Rector\Rector\Deprecation;

use Drupal8Rector\Utility\TraitsByClassHelperTrait;
use PhpParser\BuilderFactory;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use Rector\Exception\ShouldNotHappenException;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\PhpParser\Node\Manipulator\ClassManipulator;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\RectorDefinition;

/**
 * Replaces deprecated UrlGeneratorTrait trait.
 */
final class UrlGeneratorTraitRector extends AbstractRector
{
    use TraitsByClassHelperTrait;

    private const REPLACED_TRAIT_FQN = 'Drupal\Core\Routing\UrlGeneratorTrait';

    private const URL_CLASS_FQCN = 'Drupal\Core\Url';

    private const REDIRECT_RESPONSE_FQCN = 'Symfony\Component\HttpFoundation\RedirectResponse';

    /**
     * Cached methods (method names) provided by the deprecated trait.
     *
     * Use getMethodsByTrait() instead.
     *
     * @var string[]|null
     */
    private $_methodsByTrait;

    /**
     * Array of name nodes keyed by classes based on $replaceWithFqn value.
     *
     * @var \PhpParser\Node\Name[]
     */
    private $replacementClassesNames = [];

    /**
     * Whether to replace methods by using FQN or not.
     *
     * @var bool
     */
    private $replaceWithFqn;

    /**
     * Add urlGenerator property to classes that used UrlGeneratorTrait.
     *
     * @var bool
     */
    private $addUrlGeneratorProperty;

    /**
     * @var \Rector\PhpParser\Node\Manipulator\ClassManipulator
     */
    private $classManipulator;

    /**
     * UrlGeneratorTraitRector constructor.
     *
     * @param \Rector\PhpParser\Node\Manipulator\ClassManipulator $classManipulator
     *   The class manipulator.
     * @param \PhpParser\BuilderFactory $builderFactory
     *   The builder factory.
     * @param bool $replaceWithFqn
     *   Whether to replace deprecated methods with fully qualified method
     *   names or not. If it is false this rector adds new imports to all
     *   classes that used the replaced trait - even if the trait method was
     *   in use in the class. An external tool (for example PHPCBF) should
     *   optimize and remove unnecessary imports
     * @param bool $addUrlGeneratorProperty
     *   Add urlGenerator property to classes that used UrlGeneratorTrait.
     *   Disabled by default because it requires more deep clean-up.
     */
    public function __construct(ClassManipulator $classManipulator, BuilderFactory $builderFactory, bool $replaceWithFqn = false, bool $addUrlGeneratorProperty = false)
    {
        $this->classManipulator = $classManipulator;
        $this->builderFactory = $builderFactory;
        $this->replacementClassesNames = [
            self::URL_CLASS_FQCN => $replaceWithFqn ? new Node\Name\FullyQualified(self::URL_CLASS_FQCN) : new Node\Name('Url'),
            self::REDIRECT_RESPONSE_FQCN => $replaceWithFqn ? new Node\Name\FullyQualified(self::REDIRECT_RESPONSE_FQCN) : new Node\Name('RedirectResponse'),
        ];
        $this->replaceWithFqn = $replaceWithFqn;
        $this->addUrlGeneratorProperty = $addUrlGeneratorProperty;
    }

    /**
     * @inheritdoc
     */
    public function getNodeTypes(): array
    {
        return [
            Node\Stmt\Namespace_::class,
            Node\Stmt\Class_::class,
            Node\Stmt\TraitUse::class,
            Node\Stmt\Return_::class,
            Node\Stmt\Expression::class,
            Node\Expr\Assign::class,
            Node\Expr\ArrayItem::class,
            Node\Expr\MethodCall::class,
        ];
    }

    /**
     * @inheritdoc
     */
    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Node\Stmt\Namespace_ && !$this->replaceWithFqn) {
            $classNode = null;
            $urlClassExists = false;
            $responseClassExists = false;
            $urlGeneratorTraitStmtId = null;
            // Probably the last stmt is the class.
            foreach (array_reverse($node->stmts, true) as $stmt_id => $stmt) {
                // Exit from loop as early as we can.
                if ($classNode && $urlClassExists && $responseClassExists) {
                    break;
                }

                if ($stmt instanceof Node\Stmt\Use_) {
                    foreach ($stmt->uses as $use) {
                        if (self::URL_CLASS_FQCN === (string) $use->name) {
                            $urlClassExists = true;
                        } elseif (self::REDIRECT_RESPONSE_FQCN === (string) $use->name) {
                            $responseClassExists = true;
                        } elseif (self::REPLACED_TRAIT_FQN === $this->getName($use)) {
                            $urlGeneratorTraitStmtId = $stmt_id;
                        }
                    }
                } elseif ($stmt instanceof Node\Stmt\Class_) {
                    $classNode = $stmt;
                }
            }
            // Ignore interfaces, etc.
            if ($classNode && null !== $urlGeneratorTraitStmtId) {
                unset($node->stmts[$urlGeneratorTraitStmtId]);
                if (!$urlClassExists) {
                    array_unshift($node->stmts, new Node\Stmt\Use_([new Node\Stmt\UseUse(new Node\Name(self::URL_CLASS_FQCN))]));
                }
                if (!$responseClassExists) {
                    array_unshift($node->stmts, new Node\Stmt\Use_([new Node\Stmt\UseUse(new Node\Name(self::REDIRECT_RESPONSE_FQCN))]));
                }
            }
        } elseif ($node instanceof Node\Stmt\Class_ && $this->addUrlGeneratorProperty) {
            if ($this->isTraitInUse($node) && null === $this->classManipulator->getProperty($node, 'urlGenerator')) {
                $property = $this->builderFactory->property('urlGenerator')
                    ->makeProtected()
                    ->setDocComment(new Doc(sprintf('/**%s * The url generator.%s * %s * @var \Drupal\Core\Routing\UrlGeneratorInterface%s */', PHP_EOL, PHP_EOL, PHP_EOL, PHP_EOL)))
                    ->getNode();
                $this->classManipulator->addAsFirstMethod($node, $property);
            }
        } elseif ($node instanceof Node\Stmt\TraitUse) {
            $rekey = false;
            foreach ($node->traits as $stmt_id => $trait) {
                if (self::REPLACED_TRAIT_FQN === (string) $trait) {
                    unset($node->traits[$stmt_id]);
                    $rekey = true;
                }
            }
            if ($rekey) {
                if (empty($node->traits)) {
                    $this->removeNode($node);
                } else {
                    $node->traits = array_values($node->traits);
                }
            }
        } elseif ($node instanceof Node\Stmt\Return_ && null !== $node->expr) {
            $node->expr = $this->refactor($node->expr);
        } elseif ($node instanceof Node\Stmt\Expression) {
            $node->expr = $this->refactor($node->expr);
        } elseif ($node instanceof Node\Expr\Assign) {
            $node->expr = $this->refactor($node->expr);
        } elseif ($node instanceof Node\Expr\ArrayItem && null !== $node->value) {
            $node->value = $this->refactor($node->value);
        }
        // Ignore non-trivial identifiers, like when method name is created with concatenation.
        // @see https://git.drupalcode.org/project/features/blob/8.x-3.8/modules/features_ui/src/Form/FeaturesEditForm.php#L643
        elseif ($node instanceof Node\Expr\MethodCall && $node->name instanceof Node\Identifier) {
            // Sanity check, single "$this->setUrlGenerator()" should be
            // removed.
            $parentNode = $node->getAttribute(AttributeKey::PARENT_NODE);
            if ('setUrlGenerator' === $node->name->name && $parentNode instanceof Node\Stmt\Expression && $parentNode->expr === $node) {
                $this->removeNode($node);
            } elseif ($processed = $this->processMethodCall($node)) {
                return $processed;
            }
        }

        return $node;
    }

    /**
     * @inheritdoc
     */
    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(sprintf('Removes usages of deprecated %s trait', self::REPLACED_TRAIT_FQN));
    }

    private function getMethodsByTrait(): array
    {
        if (null === $this->_methodsByTrait) {
            $rc = new \ReflectionClass(self::REPLACED_TRAIT_FQN);
            $this->_methodsByTrait = array_map(function (\ReflectionMethod $method) {
                return $method->getName();
            }, $rc->getMethods());
        }

        return $this->_methodsByTrait;
    }

    /**
     * Process method calls.
     *
     * @param \PhpParser\Node\Expr\MethodCall$node
     *   Method call that may or may not related to UrlGeneratorTrait trait.
     *
     * @throws \Rector\Exception\ShouldNotHappenException
     *   If method is related to UrlGeneratorTrait but it is not handled by
     *   this method.
     */
    private function processMethodCall(Node\Expr\MethodCall $node): ?Node\Expr
    {
        $result = null;
        $classNode = $node->getAttribute(AttributeKey::CLASS_NODE);
        // Ignore procedural code because traits can not be used there.
        if (null === $classNode || !$classNode instanceof Node\Stmt\Class_) {
            return $result;
        }
        if ($this->isTraitInUse($classNode)) {
            $method_name = $node->name->name;
            if (in_array($method_name, $this->getMethodsByTrait())) {
                if ('redirect' === $method_name) {
                    $urlFromRouteArgs = [
                        $node->args[0],
                    ];
                    if (array_key_exists(1, $node->args)) {
                        $urlFromRouteArgs[] = $node->args[1];
                    }
                    if (array_key_exists(2, $node->args)) {
                        $urlFromRouteArgs[] = $node->args[2];
                    }
                    $urlFromRouteExpr = new Node\Expr\StaticCall($this->replacementClassesNames[self::URL_CLASS_FQCN], 'fromRoute', $urlFromRouteArgs);
                    $redirectResponseArgs = [$urlFromRouteExpr];
                    if (array_key_exists(3, $node->args)) {
                        $redirectResponseArgs[] = $node->args[3];
                    }
                    $result = new Node\Expr\New_($this->replacementClassesNames[self::REDIRECT_RESPONSE_FQCN], $redirectResponseArgs);
                } elseif ('url' === $method_name) {
                    $result = new Node\Expr\StaticCall($this->replacementClassesNames[self::URL_CLASS_FQCN], 'fromRoute', $node->args);
                } elseif ('getUrlGenerator' === $method_name) {
                    $result = new Node\Expr\StaticCall(new Node\Name\FullyQualified('Drupal'), 'service', [new Node\Arg(new Node\Scalar\String_('url_generator'))]);
                } elseif ('setUrlGenerator' === $method_name) {
                    // It was a fluent setter.
                    $result = new Node\Expr\Variable('this');
                } else {
                    throw new ShouldNotHappenException("Unhandled {$method_name} method from UrlGeneratorTrait trait.");
                }
            }
        }

        return $result;
    }

    /**
     * @param string $node
     *
     * @return bool
     */
    private function isTraitInUse(Node\Stmt\Class_ $node): bool
    {
        return in_array(self::REPLACED_TRAIT_FQN, $this->getTraitsByClass($this->getName($node)));
    }
}
