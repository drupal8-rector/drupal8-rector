<?php

namespace Drupal8Rector\Rector\Deprecation;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\RectorDefinition;

/**
 * Replaces deprecated format_date() calls.
 */
final class FormatDateRector extends AbstractRector
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
        if ($node->name instanceof Node\Name && 'format_date' === (string) $node->name) {
            // Use this format \Drupal::service('date.formatter')->format($timestamp, $type, $format, $timezone, $langcode).
            $date_formatter_service = new Node\Expr\StaticCall(new Node\Name\FullyQualified('Drupal'), 'service', [new Node\Scalar\String_('date.formatter')]);
            $node = new Node\Expr\MethodCall($date_formatter_service, new Node\Identifier('format'), $node->args);
        }
        return $node;
    }
    /**
     * @inheritdoc
     */
    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(sprintf('Fixes deprecated format_date() calls'));
    }
}
