<?php

namespace Drupal8Rector\Rector\Deprecation;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\RectorDefinition;

/**
 * Replaces deprecated l() calls.
 */
final class DrupalSetLinkRectorFromD7 extends AbstractRector
{
  /**
   * @inheritdoc
   */
  public function getNodeTypes(): array
  {
    return [
      Node\Expr\FuncCall::class,
    ];
  }

  /**
   * @inheritdoc
   */
  public function refactor(Node $node): ?Node
  {
    /** @var Node\Expr\FuncCall $node */
    if ($node->name instanceof Node\Name && 'l' === (string)$node->name) {
      // Getting getComments() error here.
      $uriArgs = [$node->args[1]->value->value];
      $node = new Node\Expr\StaticCall(new Node\Name('Url'), 'fromUri', $uriArgs);
      $methodArgs = [$node->args[0]];
      $node = new Node\Expr\StaticCall(new Node\Name('Link'), 'fromTextAndUrl', $methodArgs);
    }

    return $node;
  }

  /**
   * @inheritdoc
   */
  public function getDefinition(): RectorDefinition
  {
    return new RectorDefinition(sprintf('Converts and Fixes deprecated l() calls'));
  }
}
