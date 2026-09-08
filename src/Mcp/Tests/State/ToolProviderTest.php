<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiPlatform\Mcp\Tests\State;

use ApiPlatform\Mcp\State\ToolProvider;
use ApiPlatform\Metadata\McpTool;
use Mcp\Schema\Request\CallToolRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

class ToolProviderTest extends TestCase
{
    /**
     * The provider is wired as the default provider of every MCP operation, so it
     * has to stay inert when it is reached outside of an MCP request.
     */
    public function testProvideReturnsNullWithoutAnMcpRequest(): void
    {
        $objectMapper = $this->createMock(ObjectMapperInterface::class);
        $objectMapper->expects($this->never())->method('map');

        $provider = new ToolProvider($objectMapper);

        $this->assertNull($provider->provide(new McpTool(name: 'createDummy', class: \stdClass::class)));
    }

    public function testProvideMapsTheToolArgumentsToTheResourceClass(): void
    {
        $mapped = new \stdClass();

        $objectMapper = $this->createMock(ObjectMapperInterface::class);
        $objectMapper->expects($this->once())
            ->method('map')
            ->with(
                $this->callback(function (object $source): bool {
                    // The arguments come in as an array and must be handed over as an object.
                    $this->assertInstanceOf(\stdClass::class, $source);
                    $this->assertSame(['name' => 'foo', 'count' => 2], (array) $source);

                    return true;
                }),
                \stdClass::class,
            )
            ->willReturn($mapped);

        $provider = new ToolProvider($objectMapper);

        $operation = new McpTool(name: 'createDummy', class: \stdClass::class);
        $result = $provider->provide($operation, [], [
            'mcp_request' => new CallToolRequest('createDummy', ['name' => 'foo', 'count' => 2]),
            'mcp_data' => ['name' => 'foo', 'count' => 2],
        ]);

        $this->assertSame($mapped, $result);
    }

    /**
     * A declared input DTO is what the arguments must be denormalized into, not the
     * resource class itself.
     */
    public function testProvideMapsToTheDeclaredInputClass(): void
    {
        $mapped = new \stdClass();

        $objectMapper = $this->createMock(ObjectMapperInterface::class);
        $objectMapper->expects($this->once())
            ->method('map')
            ->with($this->isInstanceOf(\stdClass::class), \ArrayObject::class)
            ->willReturn($mapped);

        $provider = new ToolProvider($objectMapper);

        $operation = new McpTool(name: 'createDummy', class: \stdClass::class, input: ['class' => \ArrayObject::class]);
        $result = $provider->provide($operation, [], [
            'mcp_request' => new CallToolRequest('createDummy', []),
            'mcp_data' => [],
        ]);

        $this->assertSame($mapped, $result);
    }

    public function testProvideMapsAnEmptyArgumentListToAnEmptyObject(): void
    {
        $mapped = new \stdClass();

        $objectMapper = $this->createMock(ObjectMapperInterface::class);
        $objectMapper->expects($this->once())
            ->method('map')
            ->with(
                $this->callback(function (object $source): bool {
                    $this->assertSame([], (array) $source);

                    return true;
                }),
                \stdClass::class,
            )
            ->willReturn($mapped);

        $provider = new ToolProvider($objectMapper);

        $operation = new McpTool(name: 'createDummy', class: \stdClass::class);
        $result = $provider->provide($operation, [], [
            'mcp_request' => new CallToolRequest('createDummy', []),
            'mcp_data' => [],
        ]);

        $this->assertSame($mapped, $result);
    }
}
