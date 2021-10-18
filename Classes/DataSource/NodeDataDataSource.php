<?php
namespace Tms\Select\DataSource;

use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Media\Domain\Model\ImageInterface;
use Neos\Media\Domain\Model\ThumbnailConfiguration;
use Neos\Media\Domain\Service\AssetService;
use Neos\Neos\Service\DataSource\AbstractDataSource;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;

class NodeDataDataSource extends AbstractDataSource
{
    /**
     * @var string
     */
    static protected $identifier = 'tms-select-nodedata';

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
        if (isset($arguments['startingPoint']))
            $rootNode = $node->getContext()->getNode($arguments['startingPoint']);
        else
            $rootNode = $node->getContext()->getRootNode();

        $q = new FlowQuery(array($rootNode));
        $q = $q->context(['invisibleContentShown' => true, 'removedContentShown' => true, 'inaccessibleContentShown' => true]);
        $nodes = array();

        if (!isset($arguments['nodeType']) && !isset($arguments['nodeTypes']))
            return array();
        if (isset($arguments['nodeType']))
            $nodeTypes = array($arguments['nodeType']);
        if (isset($arguments['nodeTypes']))
            $nodeTypes = $arguments['nodeTypes'];

        $setLabelPrefixByNodeContext = false;
        if (isset($arguments['setLabelPrefixByNodeContext']) && $arguments['setLabelPrefixByNodeContext'] == true)
            $setLabelPrefixByNodeContext = true;
        $labelPropertyName = null;
        if (isset($arguments['labelPropertyName']))
            $labelPropertyName = $arguments['labelPropertyName'];
        $previewPropertyName = null;
        if (isset($arguments['previewPropertyName']))
            $previewPropertyName = $arguments['previewPropertyName'];

        if (isset($arguments['groupBy'])) {
            foreach ($q->find('[instanceof ' . $arguments['groupBy'] . ']')->get() as $parentNode) {
                $nodes = array_merge($nodes, $this->getNodes($parentNode, $nodeTypes, $labelPropertyName, $previewPropertyName, $setLabelPrefixByNodeContext, $arguments['groupBy']));
            }
        } else {
            $nodes = $this->getNodes($rootNode, $nodeTypes, $labelPropertyName, $previewPropertyName, $setLabelPrefixByNodeContext);
        }

        return $nodes;
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
        $q = new FlowQuery(array($parentNode));
        $q = $q->context(['invisibleContentShown' => true, 'removedContentShown' => true, 'inaccessibleContentShown' => true]);
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

                $label = $labelPropertyName ? $node->getProperty($labelPropertyName) : $node->getLabel();
                $groupLabel = $parentNode->getLabel();

                if ($setLabelPrefixByNodeContext) {
                    $label = $this->getLabelPrefixByNodeContext($node, $label);
                    $groupLabel = $this->getLabelPrefixByNodeContext($parentNode, $groupLabel);
                }

                $nodes[] = array(
                    'value' => $node->getIdentifier(),
                    'label' => $label,
                    'group' => ($groupBy !== null ? $groupLabel : null),
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
        if ($node->isHidden())
            $label = '[HIDDEN] ' . $label;
        if ($node->isRemoved())
            $label = '[REMOVED] ' . $label;
        if ($node->isHiddenInIndex())
            $label = '[NOT IN MENUS] ' . $label;

        $q = new FlowQuery(array($node));
        $nodeInLiveWorkspace = $q->context(['workspaceName' => 'live'])->get(0);
        if (!$nodeInLiveWorkspace instanceof NodeInterface)
            $label = '[NOT LIVE] ' . $label;

        return $label;
    }
}
