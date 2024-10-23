<?php

declare(strict_types=1);

namespace Shel\Neos\Terminal\Command;

/**
 * This file is part of the Shel.Neos.Terminal package.
 *
 * (c) 2021 Sebastian Helzle
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindChildNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cache\CacheManager;
use Neos\Flow\I18n\Translator;
use Neos\Flow\Mvc\Controller\ControllerContext;
use Neos\Neos\Service\LinkingService;
use Shel\Neos\Terminal\Domain\CommandContext;
use Shel\Neos\Terminal\Domain\CommandInvocationResult;
use Shel\Neos\Terminal\Domain\Dto\NodeResult;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;

class SearchCommand implements TerminalCommandInterface
{

    /**
     * @Flow\Inject
     * @var ContentRepositoryRegistry
     */
    protected $contentRepositoryRegistry;

    public function __construct(
        protected Translator $translator,
        protected LinkingService $linkingService,
        protected CacheManager $cacheManager,
    ) {
    }

    public static function getCommandName(): string
    {
        return 'search';
    }

    public static function getCommandDescription(): string
    {
        return 'Shel.Neos.Terminal:Main:command.search.description';
    }

    public static function getCommandUsage(): string
    {
        return 'search ' . self::getInputDefinition()->getSynopsis();
    }

    public static function getInputDefinition(): InputDefinition
    {
        return new InputDefinition([
            new InputArgument('searchword', InputArgument::REQUIRED),
            new InputOption('contextNode', 'c', InputOption::VALUE_OPTIONAL),
            new InputOption('nodeTypes', 'n', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL),
        ]);
    }

    public function invokeCommand(string $argument, CommandContext $commandContext): CommandInvocationResult
    {
        $input = new StringInput($argument);
        $input->bind(self::getInputDefinition());

        try {
            $input->validate();
        } catch (RuntimeException $e) {
            return new CommandInvocationResult(false, $e->getMessage());
        }

        $siteNode = $commandContext->getSiteNode();
        $documentNode = $commandContext->getDocumentNode();

        switch ($input->getOption('contextNode')) {
            case 'node':
            case 'focusedNode':
                $contextNode = $commandContext->getFocusedNode();
                break;
            case 'document':
            case 'documentNode':
                $contextNode = $commandContext->getDocumentNode();
                break;
            default:
                $contextNode = $siteNode;
        }

        if (!$contextNode) {
            return new CommandInvocationResult(
                false,
                $this->translator->translateById(
                    'command.search.noContext',
                    [],
                    null,
                    null,
                    'Main',
                    'Shel.Neos.Terminal'
                )
            );
        }

        $nodes = $this->contentRepositoryRegistry->subgraphForNode($contextNode)->findChildNodes(
            $contextNode->aggregateId,
            FindChildNodesFilter::create(nodeTypes: $input->getOption('nodeTypes'))
        )->getIterator();

        $results = array_map(function ($node) use ($documentNode, $commandContext) {
            return NodeResult::fromNode(
                $this->contentRepositoryRegistry,
                $node,
                $this->getUriForNode($commandContext->getControllerContext(), $documentNode, $documentNode)
            );
        }, iterator_to_array($nodes));

        return new CommandInvocationResult(true, $results);
    }

    protected function getUriForNode(
        ControllerContext $controllerContext,
        Node $node,
        Node $baseNode
    ): string {
        try {
            return $this->linkingService->createNodeUri(
                $controllerContext,
                $node,
                $baseNode,
                $controllerContext->getRequest()->getFormat(),
                true
            );
        } catch (\Exception) {
        }
        return '';
    }
}
