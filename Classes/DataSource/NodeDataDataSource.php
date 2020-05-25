<?php
namespace Tms\Select\DataSource;

use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Neos\Service\DataSource\AbstractDataSource;
use Neos\ContentRepository\Domain\Model\NodeInterface;

class NodeDataDataSource extends AbstractDataSource {

    /**
     * @var string
     */
    static protected $identifier = 'tms-select-nodedata';

    /**
     * Get data
     *
     * @param NodeInterface $node The node that is currently edited (optional)
     * @param array $arguments Additional arguments (key / value)
     * @return array JSON serializable data
     */
    public function getData(NodeInterface $node = NULL, array $arguments)
    {
        $rootNode = $node->getContext()->getRootNode();
        $q = new FlowQuery(array($rootNode));
        $nodes = array();

        if (!isset($arguments['nodeType']))
            return array();

        if (isset($arguments['groupBy'])) {
            foreach ($q->find('[instanceof ' . $arguments['groupBy'] . ']')->get() as $parentNode) {
                $nodes = array_merge($nodes, $this->getNodes($parentNode, $arguments['nodeType'], $arguments['groupBy']));
            }
        } else {
            $nodes = $this->getNodes($rootNode, $arguments['nodeType']);
        }

        return $nodes;
    }

    /**
     * @param NodeInterface $parentNode
     * @param string $nodeType
     * @param string|null $groupBy
     *
     * @return array
     */
    protected function getNodes(NodeInterface $parentNode, $nodeType, $groupBy = null)
    {
        $q = new FlowQuery(array($parentNode));
        $nodes = array();

        foreach ($q->find('[instanceof ' . $nodeType . ']')->get() as $node) {
            if ($node instanceof NodeInterface) {
                $nodes[] = array(
                    'value' => $node->getIdentifier(),
                    'label' => $node->getLabel(),
                    'group' => ($groupBy !== null ? $parentNode->getLabel() : null)
                );
            }
        }

        return $nodes;
    }
}
