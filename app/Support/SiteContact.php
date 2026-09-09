<?php

namespace App\Support;

use App\Models\SiteSetting;

/**
 * The Secretariat's own contact details.
 *
 * These were four strings in lang/{en,fr,pt}/footer.php, which meant that
 * changing the Secretariat's telephone number was a deploy in three files.
 * An address, an email address and a telephone number are the same in all
 * three languages, so they are settings rather than translations, per
 * DATA_MODEL section 5.
 *
 * The lang strings remain as the fallback. A database with no settings rows
 * still renders a complete footer rather than a gap where the address was.
 */
final class SiteContact
{
    public static function addressLine1(): string
    {
        return SiteSetting::get('contact_address_1') ?? __('footer.address_line_1');
    }

    public static function addressLine2(): string
    {
        return SiteSetting::get('contact_address_2') ?? __('footer.address_line_2');
    }

    public static function email(): string
    {
        return SiteSetting::get('contact_email') ?? __('footer.email');
    }

    public static function phone(): string
    {
        return SiteSetting::get('contact_phone') ?? __('footer.telephone');
    }
}
