<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\DonationChannel;
use App\Enums\DonationStatus;
use App\Enums\MessageStatus;
use App\Models\Donation;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\DonationService;
use App\Services\ReceiptService;
use DomainException;

/**
 * The three messages a donor gets about a donation (FR-RCP-01, FR-NTF-01/02):
 *
 *   thankYou()      → receipt issued, then "thank you + receipt" with the PDF (every paid donation)
 *   paymentFailed() → online payment failed or expired
 *   proofRejected() → Finance rejected the bank transfer proof
 *
 * Automatic sends happen once per donation (a retried job won't message twice);
 * resend: true is the admin "Resend" button.
 */
class DonationMessages
{
    public function __construct(
        private readonly ReceiptService $receipts,
        private readonly NotificationService $notifications,
        private readonly TemplateRenderer $renderer,
    ) {}

    /**
     * @return list<MessageLog>
     */
    public function thankYou(Donation $donation, bool $resend = false, ?User $by = null): array
    {
        if (! in_array($donation->status, [DonationStatus::Paid, DonationStatus::PartiallyRefunded], true)) {
            throw new DomainException("Donation {$donation->donation_no} is {$donation->status->label()}, so there is no receipt to send.");
        }

        if (! $resend && $this->alreadySent($donation, MessageTemplate::THANK_YOU_RECEIPT)) {
            return [];
        }

        $receipt = $this->receipts->issue($donation, $by);

        return $this->notifications->send(
            $donation->donor,
            MessageTemplate::THANK_YOU_RECEIPT,
            $this->renderer->varsFor($donation, $receipt),
            $donation,
            $this->receipts->file($receipt),
        );
    }

    /**
     * @return list<MessageLog>
     */
    public function paymentFailed(Donation $donation): array
    {
        // Only donors who paid online themselves; staff entries have nobody waiting.
        if ($donation->channel === DonationChannel::Admin || $this->alreadySent($donation, MessageTemplate::PAYMENT_FAILED)) {
            return [];
        }

        return $this->notifications->send($donation->donor, MessageTemplate::PAYMENT_FAILED, $this->renderer->varsFor($donation), $donation);
    }

    /**
     * @return list<MessageLog>
     */
    public function proofRejected(Donation $donation, string $reason): array
    {
        if ($this->alreadySent($donation, MessageTemplate::PROOF_REJECTED)) {
            return [];
        }

        return $this->notifications->send($donation->donor, MessageTemplate::PROOF_REJECTED, $this->renderer->varsFor($donation, reason: $reason), $donation);
    }

    /** Tells the donor about a failed or cancelled donation, when it concerns them. */
    public function closed(Donation $donation, DonationStatus $status, ?string $reason): void
    {
        if ($status === DonationStatus::Failed) {
            $this->paymentFailed($donation);

            return;
        }

        if ($reason !== null && str_starts_with($reason, DonationService::PROOF_REJECTED_PREFIX)) {
            $this->proofRejected($donation, mb_substr($reason, mb_strlen(DonationService::PROOF_REJECTED_PREFIX)));
        }
    }

    private function alreadySent(Donation $donation, string $template): bool
    {
        return MessageLog::query()
            ->where('subject_type', $donation->getMorphClass())
            ->where('subject_id', $donation->id)
            ->where('template', $template)
            ->whereIn('status', [MessageStatus::Queued->value, MessageStatus::Sent->value])
            ->exists();
    }
}
