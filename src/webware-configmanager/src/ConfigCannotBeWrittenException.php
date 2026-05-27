<?php

declare(strict_types=1);

namespace Webware\ConfigManager;

use Brick\VarExporter\ExportException;
use RuntimeException;

use function sprintf;

class ConfigCannotBeWrittenException extends RuntimeException
{
    /**
     * @return self
     */
    public static function fromExporterException(ExportException $exportException)
    {
        return new self(
            sprintf(
                'Cannot export config to file. Config contains invalid entries: %s',
                $exportException->getMessage()
            ),
            $exportException->getCode(),
            $exportException
        );
    }
}
