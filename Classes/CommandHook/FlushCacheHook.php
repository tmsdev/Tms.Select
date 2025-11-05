<?php

declare(strict_types=1);

namespace Tms\Select\CommandHook;

use Neos\Cache\Frontend\VariableFrontend;
use Neos\ContentRepository\Core\CommandHandler\CommandHookInterface;
use Neos\ContentRepository\Core\CommandHandler\CommandInterface;
use Neos\ContentRepository\Core\CommandHandler\Commands;
use Neos\ContentRepository\Core\EventStore\PublishedEvents;
use Neos\ContentRepository\Core\Feature\NodeCreation\Command\CreateNodeAggregateWithNode;
use Neos\ContentRepository\Core\Feature\NodeModification\Command\SetNodeProperties;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Command\TagSubtree;
use Neos\ContentRepository\Core\Feature\WorkspacePublication\Command\DiscardIndividualNodesFromWorkspace;
use Neos\ContentRepository\Core\Feature\WorkspacePublication\Command\DiscardWorkspace;
use Neos\ContentRepository\Core\Feature\WorkspacePublication\Command\PublishIndividualNodesFromWorkspace;
use Neos\ContentRepository\Core\Feature\WorkspacePublication\Command\PublishWorkspace;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphReadModelInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\Flow\Log\Utility\LogEnvironment;
use Psr\Log\LoggerInterface;
use Tms\Select\Service\CachingService;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;

class FlushCacheHook implements CommandHookInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly CachingService $cachingService,
        private readonly VariableFrontend $cache,
        private readonly ContentRepositoryRegistry $contentRepositoryRegistry,
        private readonly ContentGraphReadModelInterface $contentGraphReadModel
    ) {}

    public function onBeforeHandle(CommandInterface $command): CommandInterface
    {
        if ($command instanceof DiscardIndividualNodesFromWorkspace) {
            foreach ($command->nodesToDiscard as $id) {
                $this->flushForNodeAggregate($command->workspaceName, $id);
            }
        }

        if ($command instanceof DiscardWorkspace) {
            $this->logger->debug(
                sprintf('Flushing all cache entries for workspace "%s"', $command->workspaceName->value),
                LogEnvironment::fromMethodName(__METHOD__)
            );
            $this->cache->flush();
        }

        return $command;
    }

    public function onAfterHandle(CommandInterface $command, PublishedEvents $events): Commands
    {
        if (
            $command instanceof PublishWorkspace
            || $command instanceof PublishIndividualNodesFromWorkspace
            || $command instanceof CreateNodeAggregateWithNode
            || $command instanceof SetNodeProperties
            || $command instanceof TagSubtree
        ) {
            foreach ($events as $event) {
                if (!property_exists($event, 'nodeAggregateId')) {
                    continue;
                }

                $this->flushForNodeAggregate($command->workspaceName, $event->nodeAggregateId);
            }
        }

        return Commands::createEmpty();
    }

    private function flushForNodeAggregate(WorkspaceName $workspaceName, NodeAggregateId $id): void
    {
        $repo = $this->contentRepositoryRegistry->get(ContentRepositoryId::fromString('default'));
        $contentGraph = $this->contentGraphReadModel->getContentGraph($workspaceName);
        $nodeAggregate = $contentGraph->findNodeAggregateById($id);

        if ($nodeAggregate === null) {
            $this->logger->debug(
                sprintf('NodeAggregate %s not found in workspace %s (before removal/discard)', $id->value, $workspaceName->value),
                LogEnvironment::fromMethodName(__METHOD__)
            );
            return;
        }

        foreach ($nodeAggregate->coveredDimensionSpacePoints as $dsp) {
            $subgraph = $contentGraph->getSubgraph($dsp, VisibilityConstraints::createEmpty());
            $node = $subgraph->findNodeById($id);

            if ($node === null) {
                continue;
            }

            $nodeType = $repo->getNodeTypeManager()->getNodeType($node->nodeTypeName);
            $tags = (array)$this->cachingService->nodeTypeTag($nodeType, $node);

            $this->flushTags($tags);
        }
    }

    private function flushTags(array $tags): void
    {
        foreach ($tags as $tag) {
            $flushed = $this->cache->flushByTag($tag);

            if ($flushed) {
                $this->logger->debug(
                    sprintf('Flushed %s cache entries tagged with "%s"', $flushed, $tag),
                    LogEnvironment::fromMethodName(__METHOD__)
                );
            }
        }
    }
}
