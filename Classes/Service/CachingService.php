<?php

namespace Tms\Select\Service;

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
     * @var array
     */
    protected $tags = [];
    #[\Neos\Flow\Annotations\Inject]
    protected \Neos\ContentRepositoryRegistry\ContentRepositoryRegistry $contentRepositoryRegistry;

    /**
     * Get valid cache tags for the given nodetype(s)
     *
     * @param string|\Neos\ContentRepository\Core\NodeType\NodeType|string[]|\Neos\ContentRepository\Core\NodeType\NodeType[] $nodeType
     * @param \Neos\ContentRepository\Core\Projection\ContentGraph\Node|null $contextNode If set, cache tags include the workspace and dimensions
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
     * @param string|\Neos\ContentRepository\Core\NodeType\NodeType $nodeType
     * @param \Neos\ContentRepository\Core\Projection\ContentGraph\Node|null $contextNode
     * @return string|void
     */
    protected function getNodeTypeTagFor($nodeType, $contextNode = null)
    {
        $nodeTypeObject = $nodeType;
        if (is_string($nodeType)) {
            // TODO 9.0 migration: Make this code aware of multiple Content Repositories.
            $contentRepository = $this->contentRepositoryRegistry->get(\Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId::fromString('default'));
            $nodeTypeObject = $contentRepository->getNodeTypeManager()->getNodeType($nodeType);
        }
        if (!$nodeTypeObject instanceof \Neos\ContentRepository\Core\NodeType\NodeType)
            return;

        if ($nodeTypeObject->isAbstract()) {
            $nonAbstractNodeTypes = [];
            // TODO 9.0 migration: Make this code aware of multiple Content Repositories.
            $contentRepository = $this->contentRepositoryRegistry->get(\Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId::fromString('default'));
            foreach ($contentRepository->getNodeTypeManager()->getNodeTypes() as $nonAbstractNodeType) {
                if (
                    isset($nonAbstractNodeType->getConfiguration('superTypes')[$nodeTypeObject->name->value]) &&
                    $nonAbstractNodeType->getConfiguration('superTypes')[$nodeTypeObject->name->value]
                ) {
                    $nonAbstractNodeTypes[] = $nonAbstractNodeType->name->value;
                    $this->getNodeTypeTagFor($nonAbstractNodeType, $contextNode);
                }
            }
            $this->logger->debug(
                sprintf('Abstract NodeType "%s" gets tagged with: %s', $nodeTypeObject->name->value, json_encode($nonAbstractNodeTypes)),
                LogEnvironment::fromMethodName(__METHOD__)
            );
            return;
        }

        $nodeTypeName = $nodeTypeObject->name->value;
        if ($nodeTypeName === '')
            return;

        $workspaceTag = '';
        $dimensionsTag = '';
        if ($contextNode instanceof \Neos\ContentRepository\Core\Projection\ContentGraph\Node) {
            // Taxonomies only exist in 'live' workspace
            if ($nodeTypeName !== 'Sitegeist.Taxonomy:Taxonomy') {
                $contentRepository = $this->contentRepositoryRegistry->get($contextNode->contentRepositoryId);
                $workspaceTag = '%' . md5($contentRepository->findWorkspaceByName($contextNode->workspaceName)->workspaceName->value) . '%_';
            }
            $dimensionsTag = '%' . md5($contextNode->dimensionSpacePoint->toJson()) . '%_';
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
