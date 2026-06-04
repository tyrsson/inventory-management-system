<?php

declare(strict_types=1);

namespace Webware\Navigation;

use ArrayIterator;
use IteratorAggregate;
use Override;
use Traversable;
use Webware\Navigation\Renderer\RendererInterface;

use function htmlspecialchars;
use function sprintf;

use const ENT_QUOTES;

/**
 * Resolved, ACL-filtered navigation tree for one nav identifier.
 *
 * Returned by Navigation::__invoke(). Callers render it via:
 *   ->menu()        Bootstrap nav markup
 *   ->breadcrumbs() Bootstrap breadcrumb markup
 *   ->sitemap()     Plain nested list
 *
 * Each method falls back to inline rendering when no RendererInterface is
 * injected. Future: inject MenuRenderer, BreadcrumbRenderer, SitemapRenderer
 * via NavigationFactory from a RendererPluginManager.
 *
 * @implements IteratorAggregate<int, NavigationItem>
 */
final class NavigationContainer implements IteratorAggregate
{
    /**
     * @param list<NavigationItem> $topLevel
     */
    public function __construct(
        private readonly array $topLevel,
        private readonly ?string $activeRouteName,
        private readonly ?RendererInterface $menuRenderer = null,
        private readonly ?RendererInterface $breadcrumbRenderer = null,
        private readonly ?RendererInterface $sitemapRenderer = null,
    ) {}

    #[Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->topLevel);
    }

    /** @return list<NavigationItem> */
    public function getItems(): array
    {
        return $this->topLevel;
    }

    public function getActiveRouteName(): ?string
    {
        return $this->activeRouteName;
    }

    public function isActive(NavigationItem $item): bool
    {
        if ($item->route->getName() === $this->activeRouteName) {
            return true;
        }

        foreach ($item->getChildren() as $child) {
            if ($this->isActive($child)) {
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $options */
    public function menu(array $options = []): string
    {
        if ($this->menuRenderer !== null) {
            return $this->menuRenderer->render($this, $options);
        }

        return $this->renderMenuInline($options);
    }

    /** @param array<string, mixed> $options */
    public function breadcrumbs(array $options = []): string
    {
        if ($this->breadcrumbRenderer !== null) {
            return $this->breadcrumbRenderer->render($this, $options);
        }

        return $this->renderBreadcrumbsInline($options);
    }

    /** @param array<string, mixed> $options */
    public function sitemap(array $options = []): string
    {
        if ($this->sitemapRenderer !== null) {
            return $this->sitemapRenderer->render($this, $options);
        }

        return $this->renderSitemapInline($options);
    }

    // -------------------------------------------------------------------------
    // Inline fallback renderers
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $options */
    private function renderMenuInline(array $options): string
    {
        $type    = (string) ($options['type'] ?? 'sidebar');
        $ulClass = $type === 'horizontal' ? 'navbar-nav' : 'nav flex-column gap-1';

        $html = sprintf('<ul class="%s">', $this->e($ulClass));

        foreach ($this->topLevel as $item) {
            $html .= $this->renderMenuItemInline($item);
        }

        return $html . '</ul>';
    }

    private function renderMenuItemInline(NavigationItem $item): string
    {
        $active = $this->isActive($item) ? ' active' : '';
        $icon   = $item->icon !== '' ? sprintf('<i class="bi %s me-2"></i>', $this->e($item->icon)) : '';
        $path   = $item->route->getPath();
        $label  = $this->e($item->label);

        $html = sprintf(
            '<li class="nav-item"><a class="nav-link%s" href="%s">%s%s</a>',
            $active,
            $this->e($path),
            $icon,
            $label,
        );

        if ($item->hasChildren()) {
            $html .= '<ul class="nav flex-column ms-3">';
            foreach ($item->getChildren() as $child) {
                $html .= $this->renderMenuItemInline($child);
            }
            $html .= '</ul>';
        }

        return $html . '</li>';
    }

    /** @param array<string, mixed> $options */
    private function renderBreadcrumbsInline(array $options): string
    {
        $trail = $this->resolveBreadcrumbTrail();

        if ($trail === []) {
            return '';
        }

        $html = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
        $last = array_key_last($trail);

        foreach ($trail as $index => $item) {
            if ($index === $last) {
                $html .= sprintf(
                    '<li class="breadcrumb-item active" aria-current="page">%s</li>',
                    $this->e($item->label),
                );
            } else {
                $html .= sprintf(
                    '<li class="breadcrumb-item"><a href="%s">%s</a></li>',
                    $this->e($item->route->getPath()),
                    $this->e($item->label),
                );
            }
        }

        return $html . '</ol></nav>';
    }

    /**
     * Walks the tree to find the active item and collect its ancestor chain.
     *
     * @return list<NavigationItem>
     */
    private function resolveBreadcrumbTrail(): array
    {
        foreach ($this->topLevel as $item) {
            $trail = $this->findTrail($item);

            if ($trail !== null) {
                return $trail;
            }
        }

        return [];
    }

    /** @return list<NavigationItem>|null */
    private function findTrail(NavigationItem $item): ?array
    {
        if ($item->route->getName() === $this->activeRouteName) {
            return [$item];
        }

        foreach ($item->getChildren() as $child) {
            $childTrail = $this->findTrail($child);

            if ($childTrail !== null) {
                return [$item, ...$childTrail];
            }
        }

        return null;
    }

    /** @param array<string, mixed> $options */
    private function renderSitemapInline(array $options): string
    {
        $html = '<ul class="ims-sitemap">';

        foreach ($this->topLevel as $item) {
            $html .= $this->renderSitemapItemInline($item);
        }

        return $html . '</ul>';
    }

    private function renderSitemapItemInline(NavigationItem $item): string
    {
        $html = sprintf(
            '<li><a href="%s">%s</a>',
            $this->e($item->route->getPath()),
            $this->e($item->label),
        );

        if ($item->hasChildren()) {
            $html .= '<ul>';
            foreach ($item->getChildren() as $child) {
                $html .= $this->renderSitemapItemInline($child);
            }
            $html .= '</ul>';
        }

        return $html . '</li>';
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
