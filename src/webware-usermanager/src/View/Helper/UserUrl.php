<?php

declare(strict_types=1);

namespace Webware\UserManager\View\Helper;

use Laminas\View\Helper\StatefulHelperInterface;
use Mezzio\Helper\UrlHelper;
use Override;

final readonly class UserUrl implements StatefulHelperInterface
{
    public function __construct(
        private UrlHelper $urlHelper,
        private string $routeNamePrefix,
    ) {}

    public function __invoke(
        string $routeName,
        array $routeParams = [],
        array $queryParams = [],
        ?string $fragmentIdentifier = null,
        array $options = [],
    ): string {
        return ($this->urlHelper)(
            $this->routeNamePrefix . $routeName,
            $routeParams,
            $queryParams,
            $fragmentIdentifier,
            $options
        );
    }

    #[Override]
    public function resetState(): void {}
}
