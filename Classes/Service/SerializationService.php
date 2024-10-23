<?php
declare(strict_types=1);

namespace Shel\Neos\Terminal\Service;

use Neos\ContentRepository\Core\Projection\ContentGraph\Node;

/**
 * This file is part of the Shel.Neos.Terminal package.
 *
 * (c) 2021 Sebastian Helzle
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

class SerializationService
{
    /**
     * Unwraps certain object types in the evaluation result.
     * This makes it easier to view them when displayed in the terminal.
     *
     * @param mixed $result
     */
    public static function serialize($result): string
    {
        if (is_array($result)) {
            $result = array_map(static function ($item) {
                if ($item instanceof Node) {
                    return self::serializeNode($item);
                }
                return $item;
            }, $result);
        }
        if ($result instanceof Node) {
            $result = self::serializeNode($result);
        }
        return json_encode($result);
    }

    /**
     * Serialises a node into an array with its properties and attributes
     * to improve readability in the terminal output
     */
    public static function serializeNode(Node $node): array
    {
        $result = [
            '_identifier' => $node->aggregateId->value,
            '_nodeType' => $node->nodeTypeName->value,
            '_name' => $node->name->value,
            '_workspace' => $node->workspaceName->value,
            '_path' => $node->getPath(),
        ];

        try {
            foreach ($node->properties->getIterator() as $key => $property) {
                if (is_object($property)) {
                    $property = get_class($property);
                }
                if (is_array($property)) {
                    $property = '[…]';
                }
                $result[$key] = $property;
            }
        } catch (\Exception $e) {
        }

        ksort($result);

        return $result;
    }
}
