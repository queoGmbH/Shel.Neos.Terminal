<?php

namespace Shel\Neos\Terminal\Domain\Dto;

use Neos\ContentRepository\Core\NodeType\NodeType;
use Neos\ContentRepository\Core\NodeType\NodeTypeManager;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Domain\Service\NodeTypeNameFactory;

#[Flow\Proxy(false)]
class NodeResult implements \JsonSerializable
{
    /**
     * @Flow\Inject
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;

    private function __construct(
        public readonly string $identifier,
        public readonly string $label,
        public readonly string $nodeType,
        public readonly string $icon,
        public readonly string $breadcrumb,
        public readonly string $uri,
        public readonly string $score = '',
    ) {
    }

    public static function fromNode(ContentRepositoryRegistry $contentRepositoryRegistry, Node $node, string $uri, mixed $score = ''): self
    {
        $breadcrumbs = [];
        $subgraph = $contentRepositoryRegistry->subgraphForNode($node);
        $parent = $subgraph->findParentNode($node->aggregateId);
        while ($parent !== null) {
            if ($parent->nodeTypeName->equals(NodeTypeName::fromString('Neos.Neos:Node'))) {
                $breadcrumbs[] = $parent->getLabel();
            }
            $parent = $subgraph->findParentNode($parent->aggregateId);
        }

        $contentRepository = $contentRepositoryRegistry->get($node->contentRepositoryId);
        $nodeType = $contentRepository->getNodeTypeManager()->getNodeType($node->nodeTypeName);

        return new self(
            $node->aggregateId,
            $node->getLabel(),
            $nodeType->getLabel(),
            $nodeType->getConfiguration('ui.icon') ?? 'question',
            implode(' / ', array_reverse($breadcrumbs)),
            $uri,
            $score,
        );
    }

    /**
     * @return array{__typename: string, identifier: string, label: string, nodeType: string, icon: string, breadcrumb: string, uri: string, score: string}
     */
    public function toArray(): array
    {
        return [
            '__typename' => 'NodeResult',
            'identifier' => $this->identifier,
            'label' => $this->label,
            'nodeType' => $this->nodeType,
            'icon' => $this->icon,
            'breadcrumb' => $this->breadcrumb,
            'uri' => $this->uri,
            'score' => $this->score,
        ];
    }

    /**
     * @return array{__typename: string, identifier: string, label: string, nodeType: string, icon: string, breadcrumb: string, uri: string, score: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
