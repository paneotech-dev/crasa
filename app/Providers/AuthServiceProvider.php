<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\Authority;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Document;
use App\Models\Enquiry;
use App\Models\Folder;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Instrument;
use App\Models\MediaAsset;
use App\Models\Meeting;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Publication;
use App\Models\Report;
use App\Models\Sector;
use App\Models\Submission;
use App\Models\Tag;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\Policies\AuthorityPolicy;
use App\Policies\CommitteePolicy;
use App\Policies\CountryPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\EnquiryPolicy;
use App\Policies\FolderPolicy;
use App\Policies\IndicatorPolicy;
use App\Policies\IndicatorValuePolicy;
use App\Policies\InstrumentPolicy;
use App\Policies\MediaAssetPolicy;
use App\Policies\MeetingPolicy;
use App\Policies\NewsItemPolicy;
use App\Policies\PageBlockPolicy;
use App\Policies\PagePolicy;
use App\Policies\PublicationPolicy;
use App\Policies\ReportPolicy;
use App\Policies\SectorPolicy;
use App\Policies\SubmissionPolicy;
use App\Policies\TagPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    private array $policies = [
        AuditLog::class => AuditLogPolicy::class,
        Authority::class => AuthorityPolicy::class,
        Committee::class => CommitteePolicy::class,
        Country::class => CountryPolicy::class,
        Document::class => DocumentPolicy::class,
        Instrument::class => InstrumentPolicy::class,
        MediaAsset::class => MediaAssetPolicy::class,
        Enquiry::class => EnquiryPolicy::class,
        Folder::class => FolderPolicy::class,
        Indicator::class => IndicatorPolicy::class,
        Meeting::class => MeetingPolicy::class,
        IndicatorValue::class => IndicatorValuePolicy::class,
        NewsItem::class => NewsItemPolicy::class,
        Publication::class => PublicationPolicy::class,
        Report::class => ReportPolicy::class,
        Tag::class => TagPolicy::class,
        Page::class => PagePolicy::class,
        PageBlock::class => PageBlockPolicy::class,
        Sector::class => SectorPolicy::class,
        Submission::class => SubmissionPolicy::class,
        User::class => UserPolicy::class,
    ];

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Every permission on screen 13's matrix is a gate, so a route or a
        // Blade view asks the same question the policies ask.
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => $user->hasPermission($permission));
        }

        /**
         * The country gate. Answers whether this account may act on a row
         * belonging to a given member state. Null means a regional row.
         */
        Gate::define('act-on-country', fn (User $user, ?int $countryId) => $user->mayActOnCountry($countryId));

        /**
         * The named bypass, as an ability rather than a role check, so a
         * caller asks "may I read across all member states" rather than
         * "is this an administrator".
         */
        Gate::define('read-across-all-countries', fn (User $user) => $user->seesAllCountries());

        /*
         * The back office door.
         *
         * Screen 09 is a working surface, not a reading one: everything on
         * it either administers something or reports on something being
         * administered. A viewer has nothing to do there, and a committee
         * member's work is in the extranet. Both are refused at the door
         * rather than shown an empty shell.
         *
         * This gate opens the door. It grants nothing behind it: every
         * section re-checks its own permission.
         */
        Gate::define('enter-back-office', fn (User $user) => $user->hasAnyPermission([
            Permission::ManageContent,
            Permission::ManageUsers,
            Permission::ReadAuditLog,
            Permission::SubmitCountryReturn,
            Permission::ValidateSubmission,
            Permission::ManageSettings,
        ]));

        /**
         * No Gate::before superuser shortcut. An administrator's power comes
         * from the matrix, which lets the two-factor withholding in
         * User::hasPermission() actually bite. A before() returning true for
         * administrators would silently defeat it.
         */
    }
}
