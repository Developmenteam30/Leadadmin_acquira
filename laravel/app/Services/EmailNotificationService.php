<?php

namespace App\Services;

use App\Helpers\SessionHelper;
use App\Models\User;

/**
 * Service for resolving email recipients based on email notification bits.
 * Laravel equivalent of legacy getEmailBitsAddresses() from includes/leads.php.
 */
class EmailNotificationService
{
    /**
     * Get comma-separated email addresses of users who have the given email bit set.
     *
     * @param  int  $bit  One of SessionHelper::EMAIL_BITS_* constants
     * @return string|null  Comma-separated emails, or null if none found
     */
    public static function getRecipientsForBit(int $bit): ?string
    {
        $emails = User::whereRaw('(emailBits & ?) > 0', [$bit])
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return empty($emails) ? null : implode(', ', $emails);
    }

    /**
     * Get array of email addresses of users who have the given email bit set.
     *
     * @param  int  $bit  One of SessionHelper::EMAIL_BITS_* constants
     * @return array<string>
     */
    public static function getRecipientsArrayForBit(int $bit): array
    {
        return User::whereRaw('(emailBits & ?) > 0', [$bit])
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
