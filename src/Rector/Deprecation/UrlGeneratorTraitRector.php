<?php

namespace Drupal8Rector\Rector\Deprecation;

use Drupal8Rector\Utility\TraitsByClassHelperTrait;
use PhpParser\BuilderFactory;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use Rector\CodingStyle\Rector\Namespace_\ImportFullyQualifiedNamesRector;
use Rector\Exception\ShouldNotHappenException;
use Rector\PhpParser\Node\Manipulator\ClassManipulator;
use Rector\PhpParser\NodeTraverser\CallableNodeTraverser;
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
     * @var \Rector\PhpParser\NodeTraverser\CallableNodeTraverser
     */
    private $callableNodeTraverser;

    /**
     * @var bool
     */
    private $isAnythingUsedFromTrait = false;

    /**
     * @var \Rector\CodingStyle\Rector\Namespace_\ImportFullyQualifiedNamesRector
     */
    private $importFullyQualifiedNamesRector;

    /**
     * UrlGeneratorTraitRector constructor.
     *
     * @param \Rector\PhpParser\Node\Manipulator\ClassManipulator $classManipulator
     *   The class manipulator.
     * @param \Rector\PhpParser\NodeTraverser\CallableNodeTraverser $callableNodeTraverser
     *   The callable node traversal.
     * @param \PhpParser\BuilderFactory $builderFactory
     *   The builder factory.
     * @param bool $addUrlGeneratorProperty
     *   Add urlGenerator property to classes that used UrlGeneratorTrait.
     *   Disabled by default because it requires more deep clean-up.
     */
    public function __construct(ClassManipulator $classManipulator, CallableNodeTraverser $callableNodeTraverser, BuilderFactory $builderFactory, ImportFullyQualifiedNamesRector $importFullyQualifiedNamesRector, bool $addUrlGeneratorProperty = false)
    {
        $this->classManipulator = $classManipulator;
        $this->builderFactory = $builderFactory;
        $this->replacementClassesNames = [
            self::URL_CLASS_FQCN => new Node\Name\FullyQualified(self::URL_CLASS_FQCN),
            self::REDIRECT_RESPONSE_FQCN => new Node\Name\FullyQualified(self::REDIRECT_RESPONSE_FQCN),
        ];
        foreach ($this->replacementClassesNames as $name) {
            // Required by \Rector\CodingStyle\Rector\Namespace_\ImportFullyQualifiedNamesRector::importNamesAndCollectNewUseStatements().
            $name->setAttribute('originalName', clone $name);
        }
        $this->addUrlGeneratorProperty = $addUrlGeneratorProperty;
        $this->callableNodeTraverser = $callableNodeTraverser;
        $this->importFullyQualifiedNamesRector = $importFullyQualifiedNamesRector;
    }

    /**
     * @inheritdoc
     */
    public function getNodeTypes(): array
    {
        return [
            Node\Stmt\Namespace_::class,
        ];
    }

    /**
     * @inheritdoc
     */
    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            /** @var \PhpParser\Node\Stmt\Class_|null $class */
            $class = $this->betterNodeFinder->findFirstInstanceOf($node, Node\Stmt\Class_::class);
            if (null === $class || !$this->isTraitInUse($class)) {
                return null;
            }

            $this->callableNodeTraverser->traverseNodesWithCallable([$class], function (Node $node) {
                if ($node instanceof Node\Expr\MethodCall) {
                    return $this->processMethodCall($node);
                }

                return null;
            });

            if ($this->isAnythingUsedFromTrait) {
                // If the ImportFullyQualifiedNamesRector rector gets called earlier than this rector then it won't fix
                // the FQCNs.
                $this->importFullyQualifiedNamesRector->refactor($node);

                if ($this->addUrlGeneratorProperty && null === $this->classManipulator->getProperty($class, 'urlGenerator')) {
                    $property = $this->builderFactory->property('urlGenerator')
                        ->makeProtected()
                        ->setDocComment(new Doc(sprintf('/**%s * The url generator.%s * %s * @var \Drupal\Core\Routing\UrlGeneratorInterface%s */', PHP_EOL, PHP_EOL, PHP_EOL, PHP_EOL)))
                        ->getNode();
                    $this->classManipulator->addAsFirstMethod($class, $property);
                }
            }

            return $node;
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
        $method_name = $this->getName($node);
        if (in_array($method_name, $this->getMethodsByTrait(), true)) {
            $this->isAnythingUsedFromTrait = true;

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

            return $result;
        }

        return null;
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
