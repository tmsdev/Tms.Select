<?php
namespace Tms\Select\Service;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\Flow\Annotations as Flow;

/**
 * CachingService provides utility functions for caching
 *
 * We don't use Neos\Neos\Fusion\Helper\CachingHelper because it does not match the correct tag pattern
 * neither it supports content dimensions yet.
 *
 * @Flow\Scope("singleton")
 */
class CachingService
{
    /**
     * Get valid cache tags for the given nodetype(s)
     *
     * @param string|NodeType|string[]|NodeType[] $nodeType
     * @param NodeInterface|null $contextNode If set, cache tags include the workspace and dimensions
     * @return string|string[]
     */
    public function nodeTypeTag($nodeType, $contextNode = null)
    {
        if (!is_array($nodeType) && !($nodeType instanceof \Traversable))
            return $this->getNodeTypeTagFor($nodeType, $contextNode);

        $tags = [];
        foreach ($nodeType as $singleNodeType)
            $tags[] = $this->getNodeTypeTagFor($singleNodeType, $contextNode);

        return array_filter($tags);
    }

    /**
     * @param string|NodeType $nodeType
     * @param NodeInterface|null $contextNode
     * @return string
     */
    protected function getNodeTypeTagFor($nodeType, $contextNode = null)
    {
        $nodeTypeName = '';
        $workspaceTag = '';
        $dimensionsTag = '';

        if (is_string($nodeType))
            $nodeTypeName .= $nodeType;

        if ($nodeType instanceof NodeType)
            $nodeTypeName .= $nodeType->getName();

        if ($nodeTypeName === '')
            return '';

        // Replace dots and colons to match the expected tag name pattern
        $nodeTypeName = str_replace(['.',':'], ['_','-'], $nodeTypeName);

        if ($contextNode instanceof NodeInterface) {
            $workspaceTag = '%' . md5($contextNode->getContext()->getWorkspace()->getName()) .'%_';
            $dimensionsTag = '%' . md5(json_encode($contextNode->getContext()->getDimensions())) .'%_';
        }

        return 'NodeType_' . $workspaceTag . $dimensionsTag . $nodeTypeName;
    }
}
