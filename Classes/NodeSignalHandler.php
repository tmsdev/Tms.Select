<?php
namespace Tms\Select;

use Neos\Cache\Frontend\VariableFrontend;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\Utility\LogEnvironment;
use Psr\Log\LoggerInterface;
use Tms\Select\Service\CachingService;

class NodeSignalHandler
{
    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Flow\Inject
     * @var CachingService
     */
    protected $cachingService;

    /**
     * @var VariableFrontend
     */
    protected $cache;

    /**
     * @param NodeInterface $node
     */
    protected function flushDataSourceCaches(NodeInterface $node)
    {
        $tag = $this->cachingService->nodeTypeTag($node->getNodeType(), $node);
        $flushedCacheEntries = $this->cache->flushByTag($tag);
        if ($flushedCacheEntries) {
            $this->logger->debug(
                sprintf('Flushed %s data source cache(s) tagged with: "%s"', $flushedCacheEntries, $tag),
                LogEnvironment::fromMethodName(__METHOD__)
            );
        }
    }

    /**
     * @param NodeInterface $node
     */
    public function nodeAdded(NodeInterface $node)
    {
        $this->flushDataSourceCaches($node);
    }

    /**
     * @param NodeInterface $node
     */
    public function nodeUpdated(NodeInterface $node)
    {
        $this->flushDataSourceCaches($node);
    }

    /**
     * @param NodeInterface $node
     */
    public function nodeRemoved(NodeInterface $node)
    {
        $this->flushDataSourceCaches($node);
    }

    /**
     * @param NodeInterface $node
     * @param Workspace|null $node
     */
    public function nodePublished(NodeInterface $node, $targetWorkspace = null)
    {
        $this->flushDataSourceCaches($node);
    }

    /**
     * @param NodeInterface $node
     */
    public function nodeDiscarded(NodeInterface $node)
    {
        $this->flushDataSourceCaches($node);
    }
}
