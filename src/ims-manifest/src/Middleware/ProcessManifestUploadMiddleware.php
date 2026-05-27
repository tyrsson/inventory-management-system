<?php

declare(strict_types=1);

/**
 * This file is part of the Webware Farmers Store Inventory package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ims\Manifest\Middleware;

use Axleus\Message\SystemMessengerInterface;
use DateTimeImmutable;
use Ims\Manifest\Command\UploadManifestCommand;
use Ims\Manifest\Csv\ManifestCsvParser;
use Webware\UserManager\UserInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandBusInterface;
use Webware\Core\HttpMethodProcessorTrait;
use Webware\UserManager\Entity\User;

use function count;
use function file_exists;
use function is_string;
use function rename;
use function sprintf;
use function uniqid;
use function unlink;

use const UPLOAD_ERR_OK;

final class ProcessManifestUploadMiddleware implements MiddlewareInterface
{
    use HttpMethodProcessorTrait;

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly ManifestCsvParser $parser,
    ) {}

    public function processPost(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        /** @var UserInterface&User $user */
        $user = $request->getAttribute(UserInterface::class);

        /** @var SystemMessengerInterface|null $messenger */
        $messenger = $request->getAttribute(SystemMessengerInterface::class);

        $uploadedFiles = $request->getUploadedFiles();
        $file          = $uploadedFiles['manifest_csv'] ?? null;

        if (! $file instanceof UploadedFileInterface || $file->getError() !== UPLOAD_ERR_OK) {
            $messenger?->danger('No file was uploaded or the upload failed. Please try again.');
            return $handler->handle($request);
        }

        // Optional received date override supplied by the user
        $body            = $request->getParsedBody();
        $receivedDateRaw = is_string($body['received_date'] ?? null) ? $body['received_date'] : '';
        $receivedDate    = $receivedDateRaw !== ''
            ? (DateTimeImmutable::createFromFormat('Y-m-d', $receivedDateRaw) ?: null)
            : null;

        $tmpPath   = sprintf('data/manifest/%s.csv', uniqid('manifest_', true));
        $finalPath = null;
        $result    = null;

        try {
            $file->moveTo($tmpPath);
            $parsed = $this->parser->parse($tmpPath, $receivedDate);

            // Rename to store-prefixed filename now that we know the store number
            $finalPath = sprintf(
                'data/manifest/store%d_%s.csv',
                $parsed->storeId,
                uniqid('', true)
            );
            rename($tmpPath, $finalPath);

            if ($parsed->items === []) {
                $this->cleanupFile($finalPath);
                $messenger?->danger(
                    'The CSV contained no importable items. '
                    . 'Check that the file is a DC truck manifest and is not empty.'
                );
                return $handler->handle($request);
            }

            $result = $this->commandBus->handle(new UploadManifestCommand($parsed, (int) $user->getDetail('id'), $finalPath));

            if ($result->getStatus() === CommandStatus::Success) {
                $messenger?->success(
                    sprintf('Manifest imported — %d items added.', count($parsed->items))
                );
            }
        } catch (Throwable $e) {
            $this->cleanupFile($finalPath ?? $tmpPath);
            $logger = $request->getAttribute(LoggerInterface::class);
            $logger?->error($e->getMessage(), ['exception' => $e]);
            $messenger?->danger('The manifest could not be imported. Please try again or contact support.');
        }

        return $handler->handle(
            $result !== null
                ? $request->withAttribute(CommandResult::class, $result)
                : $request
        );
    }

    private function cleanupFile(?string $path): void
    {
        if (is_string($path) && file_exists($path)) {
            unlink($path);
        }
    }
}
