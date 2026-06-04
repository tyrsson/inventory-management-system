<?php

declare(strict_types=1);

namespace App\View\Helper;

use Axleus\Message\MessageLevel;
use Axleus\Message\SystemMessenger;
use Laminas\View\Helper\StatefulHelperInterface;

use function sprintf;

final class ImsMessenger implements StatefulHelperInterface
{
    private const TOAST_TEMPLATE = <<<'HTML'
        <div class="toast ims-pending-toast align-items-center text-bg-%s border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="4000">
            <div class="d-flex">
                <div class="toast-body">%s</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    HTML;

    private ?SystemMessenger $messenger = null;

    public function __invoke(): string
    {
        if ($this->messenger === null || ! $this->messenger->hasMessages()) {
            return '';
        }

        $toasts = '';
        foreach (MessageLevel::cases() as $level) {
            $levelMessages = $this->messenger->getMessage($level);
            foreach ($levelMessages as $message) {
                $toasts .= sprintf(self::TOAST_TEMPLATE, $level->value, $message);
            }
        }

        return '<div class="toast-container position-fixed bottom-0 end-0 p-4">' . $toasts . '</div>';
    }

    public function setMessenger(SystemMessenger $messenger): void
    {
        $this->messenger = $messenger;
    }

    public function getMessenger(): ?SystemMessenger
    {
        return $this->messenger;
    }

    public function resetState(): void
    {
        $this->messenger?->clearMessages();
        $this->messenger = null;
    }
}
