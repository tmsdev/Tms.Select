<?php

declare(strict_types=1);

namespace Tms\Select\CommandHook;

use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Core\CommandHandler\CommandHookInterface;
use Neos\ContentRepository\Core\Factory\CommandHookFactoryInterface;
use Neos\ContentRepository\Core\Factory\CommandHooksFactoryDependencies;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Cache\CacheManager;
use Psr\Log\LoggerInterface;
use Tms\Select\Service\CachingService;

final class FlushCacheHookFactory implements CommandHookFactoryInterface
{
    #[Flow\Inject]
    protected LoggerInterface $logger;

    #[Flow\Inject]
    protected CachingService $cachingService;

    #[Flow\Inject]
    protected CacheManager $cacheManager;

    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    public function build(CommandHooksFactoryDependencies $dependencies): CommandHookInterface
    {
        $cache = $this->cacheManager->getCache('Tms_Select_DataSourceCache');

        return new FlushCacheHook(
            $this->logger,
            $this->cachingService,
            $cache,
            $this->contentRepositoryRegistry,
            $dependencies->contentGraphReadModel
        );
    }
}
