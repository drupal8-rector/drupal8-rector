<?php

namespace Drupal8Rector\Rector\Deprecation;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\RectorDefinition;

/**
 * Replaces deprecated get_t() calls.
 */
final class DrupalGetTRector extends AbstractRector
{
  /**
   * @inheritdoc
   */
  public function getNodeTypes(): array
  {
    return [
      Node\Expr\FuncCall::class,
      Node\Expr\Variable::class,
      Node\Expr\Assign::class,
      Node\Stmt\Function_::class,
      Node\Stmt\Use_::class,
      Node\Stmt\UseUse::class,
      Node\Stmt\Nop::class,
    ];
  }

  /**
   * @inheritdoc
   */
  public function refactor(Node $node): ?Node
  {
    if ($node instanceof Node\Stmt\Function_) {
      foreach ($node->stmts as $key => $stmt) {
        if ($stmt instanceof Node\Stmt\Expression) {
          if ($stmt->expr->expr->name instanceof Node\Name\FullyQualified && $stmt->expr->expr->name->parts[0] === 'get_t') {
            $expr_var = $stmt->expr->var->name;
            unset($node->stmts[$key]);
          }
          if ($stmt->expr->expr->name instanceof Node\Expr\Variable && $stmt->expr->expr->name->name == $expr_var) {
            foreach ($stmt->expr->expr->args as $arg) {
              $t_args[] = $arg;
            }
            $t_call = new Node\Expr\FuncCall(new Node\Name('t'), $t_args);
            $changed_statement = new Node\Stmt\Expression($t_call);
            $node->stmts[$key] = $changed_statement;
          }
        }
      }
    }
    return $node;
  }

  /**
   * @inheritdoc
   */
  public function getDefinition(): RectorDefinition
  {
    return new RectorDefinition(sprintf('Converts and Fixes deprecated get_t() calls in Drupal 7.'));
  }
}
