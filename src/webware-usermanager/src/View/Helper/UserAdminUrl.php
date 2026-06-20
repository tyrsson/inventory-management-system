<?php

declare(strict_types=1);

namespace Webware\UserManager\View\Helper;

use Laminas\View\Helper\StatefulHelperInterface;
use Mezzio\Helper\UrlHelper;
use Override;

use function rtrim;

final readonly class UserAdminUrl implements StatefulHelperInterface
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
        $prefix = $routeName === '' ? rtrim($this->routeNamePrefix, '.') : $this->routeNamePrefix;

        return ($this->urlHelper)(
            $prefix . $routeName,
            $routeParams,
            $queryParams,
            $fragmentIdentifier,
            $options
        );
    }

    #[Override]
    public function resetState(): void {}
}
