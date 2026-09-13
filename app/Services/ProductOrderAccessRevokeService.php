<?php

namespace App\Services;

use App\Models\FormOrder;
use App\Models\OnlineCourseEnrollment;
use App\Models\OrderFulfillment;
use App\Models\OrderItem;
use App\Models\OrderItemRecipient;
use Illuminate\Support\Facades\DB;

class ProductOrderAccessRevokeService
{
    /**
     * @return array{success: bool, revoked: int, skipped: int, failed: int, message: string, error?: string}
     */
    public function revoke(FormOrder $order, ?int $recipientId = null): array
    {
        if (! $order->isProductOrder()) {
            return $this->result(false, 0, 0, 1, 'To nie jest zamówienie z katalogu produktów.');
        }

        $order->loadMissing('orderItems.recipients.fulfillments');

        $recipients = $order->orderItems
            ->flatMap(fn (OrderItem $item) => $item->recipients)
            ->when($recipientId, fn ($collection) => $collection->where('id', $recipientId)->values())
            ->values();

        if ($recipientId !== null && $recipients->isEmpty()) {
            return $this->result(false, 0, 0, 1, 'Nie znaleziono wskazanego uczestnika w tym zamówieniu.');
        }

        $revoked = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($recipients as $recipient) {
            $outcome = $this->revokeRecipient($recipient);
            match ($outcome) {
                'revoked' => $revoked++,
                'skipped' => $skipped++,
                default => $failed++,
            };
        }

        $this->syncProvisionedAt($order->fresh(['orderItems.recipients.fulfillments']) ?? $order);

        $success = $failed === 0 && ($revoked > 0 || $skipped > 0);
        if ($revoked === 0 && $skipped === 0 && $failed === 0) {
            return $this->result(false, 0, 0, 1, 'Brak uczestników do wycofania dostępu.');
        }

        return $this->result(
            $success,
            $revoked,
            $skipped,
            $failed,
            $failed === 0
                ? "Wycofano dostęp: {$revoked}; bez nadanego dostępu: {$skipped}."
                : "Wycofano dostęp: {$revoked}; pominięto: {$skipped}; błędy: {$failed}."
        );
    }

    public function revokeRecipient(OrderItemRecipient $recipient): string
    {
        try {
            return DB::transaction(function () use ($recipient) {
                $locked = OrderItemRecipient::query()->lockForUpdate()->findOrFail($recipient->id);
                $fulfillment = OrderFulfillment::query()
                    ->where('order_item_recipient_id', $locked->id)
                    ->where('type', OrderFulfillment::TYPE_ONLINE_COURSE_ACCESS)
                    ->lockForUpdate()
                    ->first();

                if (! $fulfillment || $fulfillment->status !== OrderFulfillment::STATUS_SUCCEEDED) {
                    $locked->update(['status' => OrderItemRecipient::STATUS_PENDING]);

                    return 'skipped';
                }

                $enrollmentId = $fulfillment->online_course_enrollment_id
                    ? (int) $fulfillment->online_course_enrollment_id
                    : null;

                $this->resetFulfillment($fulfillment);
                $locked->update(['status' => OrderItemRecipient::STATUS_PENDING]);

                if ($enrollmentId) {
                    $this->deleteEnrollmentIfExclusive($enrollmentId, (int) $fulfillment->id);
                }

                return 'revoked';
            });
        } catch (\Throwable) {
            return 'failed';
        }
    }

    private function resetFulfillment(OrderFulfillment $fulfillment): void
    {
        $fulfillment->update([
            'status' => OrderFulfillment::STATUS_PENDING,
            'pnedu_user_id' => null,
            'online_course_enrollment_id' => null,
            'granted_at' => null,
            'notified_at' => null,
            'failed_at' => null,
            'previous_access_expires_at' => null,
            'new_access_expires_at' => null,
            'error_message' => null,
            'payload' => array_merge($fulfillment->payload ?? [], [
                'revoked_at' => now('UTC')->toIso8601String(),
            ]),
        ]);
    }

    private function deleteEnrollmentIfExclusive(int $enrollmentId, int $exceptFulfillmentId): void
    {
        $shared = OrderFulfillment::query()
            ->where('online_course_enrollment_id', $enrollmentId)
            ->where('status', OrderFulfillment::STATUS_SUCCEEDED)
            ->where('id', '!=', $exceptFulfillmentId)
            ->exists();

        if ($shared) {
            return;
        }

        OnlineCourseEnrollment::query()->whereKey($enrollmentId)->delete();
    }

    public function syncProvisionedAt(FormOrder $order): void
    {
        $recipients = $order->orderItems
            ->flatMap(fn (OrderItem $item) => $item->recipients)
            ->filter(fn (OrderItemRecipient $recipient) => trim((string) $recipient->email) !== '')
            ->values();

        $allSucceeded = $recipients->isNotEmpty() && $recipients->every(
            fn (OrderItemRecipient $recipient) => $recipient->fulfillments->contains(
                fn (OrderFulfillment $fulfillment) => $fulfillment->status === OrderFulfillment::STATUS_SUCCEEDED
            )
        );

        if ($allSucceeded) {
            return;
        }

        if ($order->pnedu_provisioned_at !== null) {
            $order->pnedu_provisioned_at = null;
            $order->save();
        }
    }

    /**
     * @return array{success: bool, revoked: int, skipped: int, failed: int, message: string, error?: string}
     */
    private function result(bool $success, int $revoked, int $skipped, int $failed, string $message): array
    {
        $payload = compact('success', 'revoked', 'skipped', 'failed', 'message');
        if (! $success) {
            $payload['error'] = $message;
        }

        return $payload;
    }
}
