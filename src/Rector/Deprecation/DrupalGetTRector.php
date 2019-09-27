<?php

namespace Mxr576\Rector\Deprecation;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;

/**
 * Replaces deprecated get_t() calls to Drupal 8 t() classes.
 */
final class DrupalGetTRector extends AbstractRector
{
  /**
   * @inheritdoc
   * The Node Types list can be found here
   * https://github.com/rectorphp/rector/blob/master/docs/NodesOverview.md
   */
  public function getNodeTypes(): array
  {
    // Initially return the function(eg: hook_theme()) along with its body.
    return [
      Node\Stmt\Function_::class,
    ];
  }

  /**
   * @inheritdoc
   */
  public function refactor(Node $node): ?Node
  {
    if ($node instanceof Node\Stmt\Function_) {
      // Iterate through each statement of the fetched function body.
      // Find 'get_t' occurances in body.
      foreach ($node->stmts as $key => $stmt) {
        // Check if the statement is Expression statement or not.
        if ($stmt instanceof Node\Stmt\Expression) {
          if (isset($stmt->expr->expr->name)) {
            // Check if the statement expression conatains 'get_t' function.
            if ($stmt->expr->expr->name instanceof Node\Name\FullyQualified && $stmt->expr->expr->name->parts[0] === 'get_t') {
              // Store the 'get_t' assignment variables into an array.
              // This is done so that these variables can be searched in below statements.
              $expr_var[] = $stmt->expr->var->name;
              // Removing the found function from the code.
              unset($node->stmts[$key]);
            }
            // Find the 'get_t' assignment variable in below statements. 
            if ($stmt->expr->expr->name instanceof Node\Expr\Variable && in_array($stmt->expr->expr->name->name, $expr_var)) {
              // Initialise variable to store arguments of get_t() function.
              $t_args = [];
              foreach ($stmt->expr->expr->args as $arg) {
                // Store function arguments into array.
                $t_args[] = $arg;
              }
              // Create the replacement function 't' along with arguments.
              $t_call = new Node\Expr\FuncCall(new Node\Name('t'), $t_args);
              // Assign the created function at the same statement location
              // as that of get() function call.
              $node->stmts[$key] = new Node\Stmt\Expression($t_call);
            }
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
    // Returns rector rule definition.
    // It takes description and CodeSample object as arguments.
    // The CodeSample constructor takes BeforeCode and AfterCode as arguments.
    return new RectorDefinition('Converts and Fixes deprecated get_t() calls in Drupal 7 to Drupal 8 t() calls.', [
      new CodeSample(
        <<<'CODE_SAMPLE'
  $account = 'test_name';
  $t = get_t();
  $translated = $t("@name's blog", array(
    '@name' => format_username($account),
  ));

CODE_SAMPLE
        ,
        <<<'CODE_SAMPLE'
 t("@name's blog", array(
     '@name' => format_username($account),
   ));

CODE_SAMPLE
      ),
    ]);
  }
}
