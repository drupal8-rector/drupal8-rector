<?php

namespace Drupal8Rector\Rector\Deprecation;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\RectorDefinition;

/**
 * Replaces deprecated drupal_render() and drupal_render_root() calls.
 */
final class DrupalRenderRector extends AbstractRector
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
        // Ignore those complex cases when function name specified by a variable.
        if ($node->name instanceof Node\Name && in_array((string) $node->name, ['drupal_render', 'drupal_render_root'])) {
            // Use this format \Drupal::service('renderer')->render($elements, $is_recursive_call).
            $renderer_service = new Node\Expr\StaticCall(new Node\Name\FullyQualified('Drupal'), 'service', [new Node\Scalar\String_('renderer')]);
            $function_method_name_mapping = [
                'drupal_render' => 'render',
                'drupal_render_root' => 'renderRoot',
            ];
            $node = new Node\Expr\MethodCall($renderer_service, new Node\Identifier($function_method_name_mapping[(string) $node->name]), $node->args);
        }
        return $node;
    }
    /**
     * @inheritdoc
     */
    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(sprintf('Fixes deprecated drupal_render() and drupal_render_root() calls'));
    }
}
