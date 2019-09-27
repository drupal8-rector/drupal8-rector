<?php

namespace Mxr576\Rector\Deprecation;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;
use PhpParser\NodeDumper;

/**
 * Replaces deprecated l() calls to Drupal 8 Link calls.
 */
final class DrupalSetLinkRectorFromD7 extends AbstractRector
{
  /**
   * @inheritdoc
   * The Node Types list can be found here
   * https://github.com/rectorphp/rector/blob/master/docs/NodesOverview.md
   */
  public function getNodeTypes(): array
  {
    // Initially get all the function calls present in the code.
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
      $dumper = new NodeDumper();
      // Check if the function call is 'l' function or not.
      if ($node->name instanceof Node\Name\FullyQualified && $node->name->parts[0] === 'l') {
        // attach URL prefix in case of external or internal url
        if (strpos($node->args[1]->value->value, 'http') > -1) {
          $url_pre = '';
        }
        else {
          $url_pre = 'internal:/';
        }

        // Prepare arguments for URL:fromUri() function.
        $url_args[0] = new Node\Arg(new Node\Scalar\String_($url_pre . $node->args[1]->value->value));
        if (isset($node->args[2])) {
          // Take option arguments.
          $url_args[1] = $node->args[2];
        }

        // Prepare Url namespace.
        $url_namespace = new Node\Name\FullyQualified('Drupal\Core\Url');

        // Prepare fromUri function call along with arguments.
        $url_call = new Node\Expr\StaticCall($url_namespace, 'fromUri', $url_args);

        // Prepare link arguments.
        // Get link title.
        $link_args[0] = $node->args[0];

        // Get link Url along with options.
        $link_args[1] = new Node\Arg($url_call);

        // Get link namespace.
        $link_namespace = new Node\Name\FullyQualified('Drupal\Core\Link');

        // Get Link object.
        // Prepare fromTextAndUrl function call along with arguments.
        $link_static = new Node\Expr\StaticCall($link_namespace, 'fromTextAndUrl', $link_args);

        // Convert link object to String.
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
    // Returns rector rule definition.
    // It takes description and CodeSample object as arguments.
    // The CodeSample constructor takes BeforeCode and AfterCode as arguments.
    return new RectorDefinition('Converts and Fixes deprecated l() calls', [
      new CodeSample(
        <<<'CODE_SAMPLE'
  l(t('About rector test.'), 'https://google.com', array('attributes' => array('class' => 'about-link')));
CODE_SAMPLE
        ,
        <<<'CODE_SAMPLE'
 \Drupal\Core\Link::fromTextAndUrl(t('About rector test.'), \Drupal\Core\Url::fromUri('internal:/about-us', array('attributes' => array('class' => 'about-link'))))->toString();
CODE_SAMPLE
      ),
    ]);
  }
}
