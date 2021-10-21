<?php
namespace Tms\Select;

use Neos\ContentRepository\Domain\Model\Node;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Package\Package as BasePackage;
use Neos\Neos\Service\PublishingService;

class Package extends BasePackage
{
    /**
     * Invokes custom PHP code directly after the package manager has been initialized.
     *
     * @param Bootstrap $bootstrap The current bootstrap
     * @return void
     */
    public function boot(Bootstrap $bootstrap)
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();
        $dispatcher->connect(Node::class, 'nodeUpdated', NodeSignalHandler::class, 'nodeUpdated');
        $dispatcher->connect(Node::class, 'nodeRemoved', NodeSignalHandler::class, 'nodeRemoved');
        $dispatcher->connect(PublishingService::class, 'nodePublished', NodeSignalHandler::class, 'nodePublished');
        $dispatcher->connect(PublishingService::class, 'nodeDiscarded', NodeSignalHandler::class, 'nodeDiscarded');
    }
}
