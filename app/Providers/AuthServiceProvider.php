<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\Announcement;
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
use App\Policies\AnnouncementPolicy;
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
        Announcement::class => AnnouncementPolicy::class,
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
        \App\Models\Approval::class => \App\Policies\ApprovalPolicy::class,
        \App\Models\SocialPost::class => \App\Policies\SocialPostPolicy::class,
        \App\Models\Project::class => \App\Policies\ProjectPolicy::class,
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
        /*
         * SubmitCountryReturn is not on this list, and was until
         * 9 September 2026.
         *
         * A member state representative's work is at /en/submissions and in
         * the extranet, neither of which is behind this gate. Having the
         * permission here let a representative open screen 09's dashboard,
         * which counts the Secretariat's content and tells them nothing
         * they can act on. Nothing sensitive leaked -- every section behind
         * the door re-checks its own permission and none of them opened --
         * but a door that opens on a room with nothing in it for you is
         * still the wrong door.
         */
        Gate::define('enter-back-office', fn (User $user) => $user->hasAnyPermission([
            Permission::ManageContent,
            Permission::ReadContent,
            Permission::ApproveContent,
            Permission::ManageUsers,
            Permission::ReadAuditLog,
            Permission::ValidateSubmission,
            Permission::ManageSettings,
        ]));

        /*
         * The member area (PM-01). Whoever holds the committee workspace
         * permission: as shipped, administrators, editors, member state
         * representatives and committee members. Viewers, moderators and
         * auditors read what is public (and moderators and auditors work in
         * the back office); the server refuses them the member area, on
         * every route in it, with the friendly 403 page. A role made on the
         * roles screen enters if it is granted that permission.
         */
        Gate::define('enter-member-area', fn (User $user) => $user->hasPermission(Permission::ReachCommitteeWorkspace));

        /*
         * A member state's own pages in the member area: its contributions
         * and its authority record. Read by anyone in the member area who
         * belongs to a member state (a committee member included); written
         * only with SubmitCountryReturn, which the controllers check on
         * every write. The sidebar asks this same gate.
         */
        Gate::define('read-own-country-record', fn (User $user) => $user->country_id !== null && $user->can('enter-member-area'));

        // Day 3: auditdeny. The ability a gate refused, for the audit entry.
        \App\Support\AccessRefusals::listen();

        /**
         * No Gate::before superuser shortcut. An administrator's power comes
         * from the matrix, which lets the two-factor withholding in
         * User::hasPermission() actually bite. A before() returning true for
         * administrators would silently defeat it.
         */
    }
}
