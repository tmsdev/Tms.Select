<?php
namespace Tms\Select\DataSource;

use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Media\Domain\Model\ImageInterface;
use Neos\Media\Domain\Model\ThumbnailConfiguration;
use Neos\Media\Domain\Service\AssetService;
use Neos\Neos\Domain\Service\ContentContext;
use Neos\Neos\Domain\Service\ContentContextFactory;
use Neos\Neos\Service\DataSource\AbstractDataSource;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;

class NodeDataDataSource extends AbstractDataSource {

    /**
     * @var string
     */
    static protected $identifier = 'tms-select-nodedata';

    /**
     * @Flow\Inject
     * @var ContentContextFactory
     */
    protected $contentContextFactory;

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
    public function getData(NodeInterface $node = NULL, array $arguments = [])
    {
        /** @var ContentContext $contentContext */
        $contentContext = $this->contentContextFactory->create(array($node));

        if (isset($arguments['startingPoint']))
            $rootNode = $contentContext->getNode($arguments['startingPoint']);
        else
            $rootNode = $contentContext->getRootNode();

        $q = new FlowQuery(array($rootNode));
        $nodes = array();

        if (!isset($arguments['nodeType']) && !isset($arguments['nodeTypes']))
            return array();
        if (isset($arguments['nodeType']))
            $nodeTypes = array($arguments['nodeType']);
        if (isset($arguments['nodeTypes']))
            $nodeTypes = $arguments['nodeTypes'];

        $labelPropertyName = null;
        if (isset($arguments['labelPropertyName']))
            $labelPropertyName = $arguments['labelPropertyName'];
        $previewPropertyName = null;
        if (isset($arguments['previewPropertyName']))
            $previewPropertyName = $arguments['previewPropertyName'];

        if (isset($arguments['groupBy'])) {
            foreach ($q->find('[instanceof ' . $arguments['groupBy'] . ']')->get() as $parentNode) {
                $nodes = array_merge($nodes, $this->getNodes($parentNode, $nodeTypes, $labelPropertyName, $previewPropertyName, $arguments['groupBy']));
            }
        } else {
            $nodes = $this->getNodes($rootNode, $nodeTypes, $labelPropertyName, $previewPropertyName);
        }

        return $nodes;
    }

    /**
     * @param NodeInterface $parentNode
     * @param array $nodeTypes
     * @param string|null $labelPropertyName
     * @param string|null $previewPropertyName
     * @param string|null $groupBy
     *
     * @return array
     */
    protected function getNodes(NodeInterface $parentNode, $nodeTypes, $labelPropertyName = null, $previewPropertyName = null, $groupBy = null)
    {
        $q = new FlowQuery(array($parentNode));
        $nodes = array();

        $filter = [];
        foreach ($nodeTypes as $nodeType)
            $filter[] = '[instanceof ' . $nodeType . ']';
        $filterString = implode(',', $filter);

        foreach ($q->find($filterString)->get() as $node) {
            if ($node instanceof NodeInterface) {
                $preview = null;
                if ($previewPropertyName) {
                    $image = $node->getProperty($previewPropertyName);
                    if (!$image instanceof ImageInterface)
                        continue;
                    $thumbnailConfiguration = new ThumbnailConfiguration(null, 74, null, 56);
                    $thumbnail = $this->assetService->getThumbnailUriAndSizeForAsset($image, $thumbnailConfiguration);
                    if (!isset($thumbnail['src']))
                        continue;
                    $preview = $thumbnail['src'];
                }
                $nodes[] = array(
                    'value' => $node->getIdentifier(),
                    'label' => $labelPropertyName ? $node->getProperty($labelPropertyName) : $node->getLabel(),
                    'group' => ($groupBy !== null ? $parentNode->getLabel() : null),
                    'preview' => $preview
                );
            }
        }
        return $nodes;
    }
}
