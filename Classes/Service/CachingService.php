<?php
namespace Tms\Select\Service;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\Utility\LogEnvironment;
use Psr\Log\LoggerInterface;

/**
 * CachingService provides utility functions for caching
 *
 * - adds node context (workspace + content dimensions)
 * - skip workspace context when nodetype is "Sitegeist.Taxonomy:Taxonomy"
 * - works with abstract nodetypes (mixins)
 * - sanitize tag names
 *
 * @Flow\Scope("singleton")
 */
class CachingService
{
    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Flow\Inject
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * @var array
     */
    protected $tags = [];

    /**
     * Get valid cache tags for the given nodetype(s)
     *
     * @param string|NodeType|string[]|NodeType[] $nodeType
     * @param NodeInterface|null $contextNode If set, cache tags include the workspace and dimensions
     * @return string|string[]
     */
    public function nodeTypeTag($nodeType, $contextNode = null)
    {
        if (!is_array($nodeType) && !($nodeType instanceof \Traversable)) {
            $this->getNodeTypeTagFor($nodeType, $contextNode);
            if (count($this->tags) === 1)
                return array_shift($this->tags);
            return array_filter($this->tags);
        }

        foreach ($nodeType as $singleNodeType)
            $this->getNodeTypeTagFor($singleNodeType, $contextNode);
        return array_filter($this->tags);
    }

    /**
     * @param string|NodeType $nodeType
     * @param NodeInterface|null $contextNode
     * @return string|void
     */
    protected function getNodeTypeTagFor($nodeType, $contextNode = null)
    {
        $nodeTypeObject = $nodeType;
        if (is_string($nodeType))
            $nodeTypeObject = $this->nodeTypeManager->getNodeType($nodeType);
        if (!$nodeTypeObject instanceof NodeType)
            return;

        if ($nodeTypeObject->isAbstract()) {
            $nonAbstractNodeTypes = [];
            foreach ($this->nodeTypeManager->getNodeTypes() as $nonAbstractNodeType) {
                if (
                    isset($nonAbstractNodeType->getConfiguration('superTypes')[$nodeTypeObject->getName()]) &&
                    $nonAbstractNodeType->getConfiguration('superTypes')[$nodeTypeObject->getName()]
                ) {
                    $nonAbstractNodeTypes[] = $nonAbstractNodeType->getName();
                    $this->getNodeTypeTagFor($nonAbstractNodeType, $contextNode);
                }
            }
            $this->logger->debug(
                sprintf('Abstract NodeType "%s" gets tagged with: %s', $nodeTypeObject->getName(), json_encode($nonAbstractNodeTypes)),
                LogEnvironment::fromMethodName(__METHOD__)
            );
            return;
        }

        $nodeTypeName = $nodeTypeObject->getName();
        if ($nodeTypeName === '')
            return;

        $workspaceTag = '';
        $dimensionsTag = '';
        if ($contextNode instanceof NodeInterface) {
            // Taxonomies only exist in 'live' workspace
            if ($nodeTypeName !== 'Sitegeist.Taxonomy:Taxonomy')
                $workspaceTag = '%' . md5($contextNode->getContext()->getWorkspace()->getName()) .'%_';
            $dimensionsTag = '%' . md5(json_encode($contextNode->getContext()->getDimensions())) .'%_';
        }

        $nodeTypeName = $this->sanitizeTag($nodeTypeName);
        $this->tags[] = 'NodeType_' . $workspaceTag . $dimensionsTag . $nodeTypeName;
    }

    /**
     * Replace dots and colons to match the expected tag name pattern
     *
     * @param string $tag
     * @return string
     */
    protected function sanitizeTag($tag)
    {
        return strtr($tag, '.:', '_-');
    }
}
