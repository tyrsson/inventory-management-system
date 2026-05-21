<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\Middleware;

use Axleus\Message\SystemMessengerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\Acl\Admin\Command\ProtectRouteCommand;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandBusInterface;
use Webware\Core\HttpMethodProcessorTrait;

use function array_filter;
use function array_map;
use function array_values;
use function in_array;
use function is_array;
use function strval;

final class ProcessProtectRouteMiddleware implements MiddlewareInterface
{
    use HttpMethodProcessorTrait;

    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {}

    public function processPost(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $body      = (array) $request->getParsedBody();
        $messenger = $request->getAttribute(SystemMessengerInterface::class);

        $routeName     = (string) ($body['route_name']       ?? '');
        $grantMode     = (string) ($body['grant_mode']       ?? 'explicit');
        $ruleType      = (string) ($body['rule_type']        ?? 'allow');
        $roleId        = (string) ($body['role_id']          ?? '');
        $assertionFqcn = (string) ($body['assertion_fqcn']   ?? '');
        $assertionMode = (string) ($body['assertion_mode']   ?? 'none');

        // Sanitise rule_type — only 'allow' and 'deny' are valid
        if (! in_array($ruleType, ['allow', 'deny'], true)) {
            $ruleType = 'allow';
        }

        // Gather privilege names from privileges[] + optional custom_privilege
        $rawPrivs = is_array($body['privileges'] ?? null) ? $body['privileges'] : [];
        $privileges = array_values(array_filter(array_map(strval(...), $rawPrivs)));
        $customPriv = (string) ($body['custom_privilege'] ?? '');
        if ($customPriv !== '') {
            $privileges[] = $customPriv;
        }

        $result = $this->commandBus->handle(
            new ProtectRouteCommand(
                $routeName,
                $grantMode,
                $ruleType,
                $roleId,
                $privileges,
                $assertionFqcn,
                $assertionMode,
            )
        );

        if ($result->getStatus() === CommandStatus::Success) {
            $messenger?->success("Route '{$routeName}' is now protected.", hops: 0, now: true);
        } else {
            $messenger?->error("Failed to protect route '{$routeName}'.", hops: 0, now: true);
        }

        return $handler->handle($request->withAttribute(CommandResult::class, $result));
    }
}
