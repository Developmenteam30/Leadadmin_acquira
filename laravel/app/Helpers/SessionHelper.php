<?php

namespace App\Helpers;

class SessionHelper
{
    // Access Level Constants
    const LEADS_SESSION_LEVEL_CALL_CENTER_HR_MANAGER = 0x2000;
    const LEADS_SESSION_LEVEL_CALL_CENTER_AGENT = 0x1000;
    const LEADS_SESSION_LEVEL_CALL_CENTER_QA = 0x800;
    const LEADS_SESSION_LEVEL_CALL_CENTER = 0x400;
    const LEADS_SESSION_LEVEL_SALESPERSON = 0x200;
    const LEADS_SESSION_LEVEL_ADMIN = 0x100;
    const LEADS_SESSION_LEVEL_MANAGER = 0x80;
    const LEADS_SESSION_LEVEL_STAFF = 0x40;
    const LEADS_SESSION_LEVEL_PPC = 0x20;
    const LEADS_SESSION_LEVEL_CRM = 0x10;
    const LEADS_SESSION_LEVEL_CLIENT_DASHBOARD = 0x8;
    const LEADS_SESSION_LEVEL_CLIENT_IMPORT = 0x4;
    const LEADS_SESSION_LEVEL_CLIENT_PHONE_LEADS = 0x2;
    const LEADS_SESSION_LEVEL_CLIENT_REPORTS = 0x1;

    // Email Bits Constants
    const EMAIL_BITS_DEVELOPER = 0x01;
    const EMAIL_BITS_DORMANT_URL = 0x02;
    const EMAIL_BITS_NEW_URL = 0x04;
    const EMAIL_BITS_LEAD_THRESHOLD = 0x08;
    const EMAIL_BITS_JOB_STATUS = 0x10;
    const EMAIL_BITS_NEW_USER = 0x20;
    const EMAIL_BITS_PAYROLL = 0x40;
    const EMAIL_BITS_ACCOUNTING = 0x80;
    const EMAIL_BITS_CRM = 0x100;
    const EMAIL_BITS_DIALER_LICENSING_REPORT = 0x200;
    const EMAIL_BITS_DIALER_STATS_REPORT = 0x400;
    const EMAIL_BITS_DIALER_BILLABLE_HOURS_REPORT = 0x800;
    const EMAIL_BITS_DIALER_WRITEUP_NOTIFICATION = 0x1000;

    public static function getAccessBits(): array
    {
        return [
            self::LEADS_SESSION_LEVEL_ADMIN => 'Administrator',
            self::LEADS_SESSION_LEVEL_STAFF => 'Staff Member',
            self::LEADS_SESSION_LEVEL_CLIENT_DASHBOARD => 'Publisher Dashboard Access',
            self::LEADS_SESSION_LEVEL_CLIENT_IMPORT => 'Publisher Import Access',
            self::LEADS_SESSION_LEVEL_CLIENT_PHONE_LEADS => 'Publisher Phone Leads Report',
            self::LEADS_SESSION_LEVEL_CLIENT_REPORTS => 'Publisher Reporting Access',
            self::LEADS_SESSION_LEVEL_CRM => 'CRM Access',
            self::LEADS_SESSION_LEVEL_PPC => 'PPC Feed Access',
            self::LEADS_SESSION_LEVEL_SALESPERSON => 'Show in salesperson list',
            self::LEADS_SESSION_LEVEL_CALL_CENTER => 'Call Center Reporting Access',
            self::LEADS_SESSION_LEVEL_CALL_CENTER_AGENT => 'Call Center Agent Access',
            self::LEADS_SESSION_LEVEL_CALL_CENTER_HR_MANAGER => 'Call Center HR Manager Access',
            self::LEADS_SESSION_LEVEL_CALL_CENTER_QA => 'Call Center QA Access',
        ];
    }

    public static function getAccessBitDescription($bit): string
    {
        $bits = self::getAccessBits();
        return $bits[$bit] ?? 'Unknown access level';
    }

    public static function getEmailBits(): array
    {
        return [
            self::EMAIL_BITS_DORMANT_URL => 'Dormant URL Notifications',
            self::EMAIL_BITS_LEAD_THRESHOLD => 'Lead Threshold Notifications',
            self::EMAIL_BITS_NEW_URL => 'New URL Notifications',
            self::EMAIL_BITS_NEW_USER => 'New User Added Notifications',
            self::EMAIL_BITS_ACCOUNTING => 'BCC Accounting Notifications',
            self::EMAIL_BITS_CRM => 'BCC CRM Followup Notifications',
            self::EMAIL_BITS_JOB_STATUS => 'BCC Job Status Notifications',
            self::EMAIL_BITS_DIALER_LICENSING_REPORT => 'Dialer Agent Licensing Report',
            self::EMAIL_BITS_DIALER_BILLABLE_HOURS_REPORT => 'Dialer Billable Hours Report',
            self::EMAIL_BITS_DIALER_STATS_REPORT => 'Dialer Stats Report',
            self::EMAIL_BITS_PAYROLL => 'Dialer Payroll Report',
            self::EMAIL_BITS_DIALER_WRITEUP_NOTIFICATION => 'Dialer Write-Up Notification',
            self::EMAIL_BITS_DEVELOPER => 'Developer Notifications',
        ];
    }

    public static function getEmailBitDescription($bit): string
    {
        $bits = self::getEmailBits();
        return $bits[$bit] ?? 'Unknown email notification';
    }

    public static function checkBit($bits, $bit): bool
    {
        return ($bits & $bit) === $bit;
    }

    public static function calculateBits(array $selectedBits): int
    {
        return array_reduce($selectedBits, function ($carry, $item) {
            return $carry + intval($item);
        }, 0);
    }
}
