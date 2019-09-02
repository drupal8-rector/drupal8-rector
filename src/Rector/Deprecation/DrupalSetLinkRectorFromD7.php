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
    if ($node instanceof Node\Expr\FuncCall) {
      if ($node->name instanceof Node\Name\FullyQualified && $node->name->parts[0] === 'l') {
        if (strpos($node->args[1]->value->value, 'http') > -1) {
          $url_pre = '';
        }
        else {
          $url_pre = 'internal:/';
        }
        $url_args[0] = new Node\Arg(new Node\Scalar\String_($url_pre . $node->args[1]->value->value));
        $url_namespace = new Node\Name\FullyQualified('Drupal\Core\Url');
        $url_call = new Node\Expr\StaticCall($url_namespace, 'fromUri', $url_args);
        $link_args[0] = $node->args[0];
        $link_args[1] = new Node\Arg($url_call);
        $link_namespace = new Node\Name\FullyQualified('Drupal\Core\Link');
        $link_static = new Node\Expr\StaticCall($link_namespace, 'fromTextAndUrl', $link_args);
        $node = new Node\Expr\MethodCall($link_static, 'toString');
      }
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
