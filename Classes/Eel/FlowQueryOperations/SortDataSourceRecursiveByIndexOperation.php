<?php

namespace Tms\Select\Eel\FlowQueryOperations;

/*                                                                        *
 * This script is copied from "Flowpack.Listable".                        *
 *                                                                        */

use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindChildNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\NodeType\NodeTypeCriteria;
use Neos\ContentRepository\Core\Projection\ContentGraph\Nodes;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Eel\FlowQuery\Operations\AbstractOperation;

/**
 * Sort Nodes by their position in the node tree.
 *
 * Use it like this:
 *
 *    ${q(node).children().sortDataSourceRecursiveByIndex(['ASC'|'DESC'])}
 */
class SortDataSourceRecursiveByIndexOperation extends AbstractOperation
{
    /**
     * {@inheritdoc}
     */
    protected static $shortName = 'sortDataSourceRecursiveByIndex';

    /**
     * {@inheritdoc}
     */
    protected static $priority = 100;
    #[\Neos\Flow\Annotations\Inject]
    protected \Neos\ContentRepositoryRegistry\ContentRepositoryRegistry $contentRepositoryRegistry;

    /**
     * {@inheritdoc}
     *
     * We can only handle CR Nodes.
     */
    public function canEvaluate($context)
    {
        return count($context) === 0 || (is_array($context) === true && (current($context) instanceof \Neos\ContentRepository\Core\Projection\ContentGraph\Node));
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function evaluate(FlowQuery $flowQuery, array $arguments)
    {
        $sortOrder = 'ASC';
        if (!empty($arguments[0]) && in_array($arguments[0], ['ASC', 'DESC'], true)) {
            $sortOrder = $arguments[0];
        }

        $nodes = $flowQuery->getContext();

        $indexPathCache = [];

        /** @var \Neos\ContentRepository\Core\Projection\ContentGraph\Node $node */
        foreach ($nodes as $node) {
            // Collect the list of sorting indices for all parents of the node and the node itself
            $nodeIdentifier = $node->aggregateId->value;
            $indexPath = [];
            $subgraph = $this->contentRepositoryRegistry->subgraphForNode($node);
            $siblingsFilter = FindChildNodesFilter::create(
                NodeTypeCriteria::fromFilterString($node->nodeTypeName->value)
            );
            $siblings = $subgraph->findChildNodes($node->aggregateId, $siblingsFilter);
            $nodeIndex = $this->getNodeIndex($siblings, $node);
            $indexPath[] = $nodeIndex;

            // Traverse through parent nodes
            while ($node = $subgraph->findParentNode($node->aggregateId)) {
                // Pass the filter as the second argument to findParentNode
                $siblings = $subgraph->findChildNodes($node->aggregateId, $siblingsFilter);
                $nodeIndex = $this->getNodeIndex($siblings, $node);
                $indexPath[] = $nodeIndex;
            }

            $indexPathCache[$nodeIdentifier] = $indexPath;
        }

        $flip = $sortOrder === 'DESC' ? -1 : 1;

        usort($nodes, function (\Neos\ContentRepository\Core\Projection\ContentGraph\Node $a, \Neos\ContentRepository\Core\Projection\ContentGraph\Node $b) use ($indexPathCache, $flip) {
            if ($a === $b) {
                return 0;
            }

            // Compare index path starting from the site root until a difference is found
            $aIndexPath = $indexPathCache[$a->aggregateId->value];
            $bIndexPath = $indexPathCache[$b->aggregateId->value];

            while (count($aIndexPath) > 0 && count($bIndexPath) > 0) {
                $diff = (array_pop($aIndexPath) - array_pop($bIndexPath));
                if ($diff !== 0) {
                    return $flip * $diff < 0 ? -1 : 1;
                }
            }

            return 0;
        });

        $flowQuery->setContext($nodes);
    }

    private function getNodeIndex(Nodes $siblings, \Neos\ContentRepository\Core\Projection\ContentGraph\Node $node)
    {
        $index = 0;

        // Loop through the siblings to find the index of the node
        foreach ($siblings as $key => $sibling) {
            if ($sibling->aggregateId->value == $node->aggregateId->value) {
                $index = $key;
                break;
            }
        }

        return $index;
    }
}
