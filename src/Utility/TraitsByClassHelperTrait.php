<?php

namespace Drupal8Rector\Utility;

/**
 * Helps to figure out which traits are used by a class.
 */
trait TraitsByClassHelperTrait
{
    /**
     * Cached associative array where keys are class FQCNs and values are trait FQCNs.
     *
     * TODO Maybe this should not be cached or should be cached in a shared storage that could be
     * flushed when a trait gets added or removed to a class.
     *
     * @var string[][]
     */
    protected $_traitsByClasses = [];

    /**
     * Returns traits used by a class (and its parents).
     *
     * @param string $class
     *   The FQCN of a class.
     *
     * @return string[]
     *   Array of trait FQCNs implemented by a class and its parents.
     */
    final protected function getTraitsByClass(string $class)
    {
        if (!array_key_exists($class, $this->_traitsByClasses)) {
            $this->_traitsByClasses[$class] = [];
            $rc = new \ReflectionClass($class);
            do {
                $traitFqcns = array_keys($rc->getTraits());
                $this->_traitsByClasses[$class] += array_combine($traitFqcns, $traitFqcns);
            } while ($rc = $rc->getParentClass());
        }

        return $this->_traitsByClasses[$class];
    }
}
