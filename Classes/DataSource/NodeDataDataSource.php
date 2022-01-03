<?php
namespace Tms\Select\DataSource;

use Neos\Cache\Frontend\VariableFrontend;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Media\Domain\Model\ImageInterface;
use Neos\Media\Domain\Model\ThumbnailConfiguration;
use Neos\Media\Domain\Service\AssetService;
use Neos\Neos\Service\DataSource\AbstractDataSource;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Psr\Log\LoggerInterface;
use Tms\Select\Service\CachingService;

class NodeDataDataSource extends AbstractDataSource
{
    /**
     * @var string
     */
    static protected $identifier = 'tms-select-nodedata';

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
     * @var array
     */
    protected $labelCache;

    /**
     * @Flow\Inject
     * @var AssetService
     */
    protected $assetService;

    /**
     * Get data
     *
     * @param NodeInterface $node The node that is currently edited (optional)
     * @param array $arguments Additional arguments (key / value)
     * @return array JSON serializable data
     */
    public function getData(NodeInterface $node = null, array $arguments = [])
    {
        $result = [];

        // Validate required parameters and arguments
        if (!$node instanceof NodeInterface)
            return [];
        if (!isset($arguments['nodeType']) && !isset($arguments['nodeTypes']))
            return [];
        if (isset($arguments['nodeType']))
            $nodeTypes = array($arguments['nodeType']);
        if (isset($arguments['nodeTypes']))
            $nodeTypes = $arguments['nodeTypes'];

        // Context variables
        $workspaceName = $node->getContext()->getWorkspaceName();
        $dimensions = $node->getContext()->getDimensions();
        if (isset($arguments['startingPoint'])) {
            $rootNode = $node->getContext()->getNode($arguments['startingPoint']);
        } else {
            $rootNode = $node->getContext()->getCurrentSiteNode();
        }
        $rootNodePath = $rootNode->getPath();

        // Check for an existing cache entry
        $cacheEntryIdentifier = md5(json_encode([$workspaceName, $dimensions, $rootNodePath, $arguments]));
        if ($this->cache->has($cacheEntryIdentifier)) {
            $this->logger->debug(
                sprintf('Retrieve cached data source for "%s" in [Workspace: %s] [Dimensions: %s] [Root: %s] [Label: %s]', json_encode($arguments), $workspaceName, json_encode($dimensions), $rootNodePath, $node->getLabel()),
                LogEnvironment::fromMethodName(__METHOD__)
            );
            $result = $this->cache->get($cacheEntryIdentifier);
            return $result;
        }

        // Build data source result
        $setLabelPrefixByNodeContext = false;
        if (isset($arguments['setLabelPrefixByNodeContext']) && $arguments['setLabelPrefixByNodeContext'] == true)
            $setLabelPrefixByNodeContext = true;

        $labelPropertyName = null;
        if (isset($arguments['labelPropertyName']))
            $labelPropertyName = $arguments['labelPropertyName'];

        $previewPropertyName = null;
        if (isset($arguments['previewPropertyName']))
            $previewPropertyName = $arguments['previewPropertyName'];

        $groupByNodeType = null;
        if (isset($arguments['groupBy'])) {
            $groupByNodeType = $arguments['groupBy'];
            $q = new FlowQuery([$rootNode]);
            $q = $q->context(['invisibleContentShown' => true, 'removedContentShown' => true, 'inaccessibleContentShown' => true]);
            $parentNodes = $q->find('[instanceof ' . $groupByNodeType . ']')->sortDataSourceRecursiveByIndex()->get();
            foreach ($parentNodes as $parentNode) {
                $result = array_merge($result, $this->getNodes($parentNode, $nodeTypes, $labelPropertyName, $previewPropertyName, $setLabelPrefixByNodeContext, $groupByNodeType));
            }
        } else {
            $result = $this->getNodes($rootNode, $nodeTypes, $labelPropertyName, $previewPropertyName, $setLabelPrefixByNodeContext);
        }

        // Whenever a node referenced in the data source changes, the cache entry gets flushed
        if ($groupByNodeType)
            array_push($nodeTypes, $groupByNodeType);
        $cacheEntryTags = $this->cachingService->nodeTypeTag($nodeTypes, $node);
        $this->cache->set($cacheEntryIdentifier, $result, $cacheEntryTags);

        $this->logger->debug(
            sprintf('Build new data source for "%s" in [Workspace: %s] [Dimensions: %s] [Root: %s] [Label: %s]', json_encode($arguments), $workspaceName, json_encode($dimensions), $rootNodePath, $node->getLabel()),
            LogEnvironment::fromMethodName(__METHOD__)
        );

        return $result;
    }

    /**
     * @param NodeInterface $parentNode
     * @param array $nodeTypes
     * @param string|null $labelPropertyName
     * @param string|null $previewPropertyName
     * @param boolean $labelPrefixNodeContext
     * @param string|null $groupBy
     *
     * @return array
     */
    protected function getNodes(NodeInterface $parentNode, $nodeTypes, $labelPropertyName = null, $previewPropertyName = null, $setLabelPrefixByNodeContext = false, $groupBy = null)
    {
        $nodes = [];
        $q = new FlowQuery([$parentNode]);
        $q = $q->context(['invisibleContentShown' => true, 'removedContentShown' => true, 'inaccessibleContentShown' => true]);

        $filter = [];
        foreach ($nodeTypes as $nodeType)
            $filter[] = '[instanceof ' . $nodeType . ']';
        $filterString = implode(',', $filter);

        foreach ($q->find($filterString)->sortDataSourceRecursiveByIndex()->get() as $node) {
            if ($node instanceof NodeInterface) {
                $icon = null;
                $preview = null;
                if ($previewPropertyName) {
                    $image = $node->getProperty($previewPropertyName);
                    if ($image instanceof ImageInterface) {
                        $thumbnailConfiguration = new ThumbnailConfiguration(null, 74, null, 56);
                        $thumbnail = $this->assetService->getThumbnailUriAndSizeForAsset($image, $thumbnailConfiguration);
                        if (isset($thumbnail['src']))
                            $preview = $thumbnail['src'];
                    }
                }
                if (is_null($preview) && $node->getNodeType()->hasConfiguration('ui.icon')) {
                    $icon = $node->getNodeType()->getConfiguration('ui.icon');
                }

                $label = $labelPropertyName ? $node->getProperty($labelPropertyName) : $node->getLabel();
                $label = $this->sanitiseLabel($label);
                $groupLabel = $parentNode->getLabel();

                if ($setLabelPrefixByNodeContext) {
                    $label = $this->getLabelPrefixByNodeContext($node, $label);
                    $groupLabel = $this->getLabelPrefixByNodeContext($parentNode, $groupLabel);
                }

                $nodes[] = array(
                    'value' => $node->getIdentifier(),
                    'label' => $label,
                    'group' => ($groupBy !== null ? $groupLabel : null),
                    'icon' => $icon,
                    'preview' => $preview
                );
            }
        }
        return $nodes;
    }

    /**
     * @param NodeInterface $node
     * @param string $label
     *
     * @return string
     */
    protected function getLabelPrefixByNodeContext(NodeInterface $node, string $label)
    {
        $nodeHash = md5($node);
        if (isset($this->labelCache[$nodeHash]))
            return $this->labelCache[$nodeHash];

        if ($node->isHidden())
            $label = '[HIDDEN] ' . $label;
        if ($node->isRemoved())
            $label = '[REMOVED] ' . $label;
        if ($node->isHiddenInIndex())
            $label = '[NOT IN MENUS] ' . $label;

        $q = new FlowQuery([$node]);
        $nodeInLiveWorkspace = $q->context(['workspaceName' => 'live'])->get(0);
        if (!$nodeInLiveWorkspace instanceof NodeInterface)
            $label = '[NOT LIVE] ' . $label;

        $this->labelCache[$nodeHash] = $label;
        return $label;
    }

    /**
     * @param string $label
     * @return string
     */
    protected function sanitiseLabel(string $label)
    {
        $label = str_replace('&nbsp;', ' ', $label);
        $label = preg_replace('/<br\\W*?\\/?>|\\x{00a0}|[^[:print:]]|\\s+/u', ' ', $label);
        $label = strip_tags($label);
        $label = trim($label);
        return $label;
    }
}
