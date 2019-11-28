<?php

namespace Drupal8Rector\Rector\Deprecation;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\RectorDefinition;

/**
 * Replaces deprecated file_prepare_directory() calls.
 */
final class FilePrepareDirectoryRector extends AbstractRector
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
        if ($node->name instanceof Node\Name && 'file_prepare_directory' === (string) $node->name) {
            // Use this format \Drupal::service('file_system')->prepareDirectory($directory, $options).
            $file_system_service = new Node\Expr\StaticCall(new Node\Name\FullyQualified('Drupal'), 'service', [new Node\Scalar\String_('file_system')]);
            $node = new Node\Expr\MethodCall($file_system_service, new Node\Identifier('prepareDirectory'), $node->args);
        }

        return $node;
    }

    /**
     * @inheritdoc
     */
    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(sprintf('Fixes deprecated file_prepare_directory() calls'));
    }
}
