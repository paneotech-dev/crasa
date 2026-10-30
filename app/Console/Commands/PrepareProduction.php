<?php

namespace App\Console\Commands;

use App\Enums\PageStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\DelegateRegistration;
use App\Models\Enquiry;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\User;
use App\Support\FormerUser;
use App\Support\SampleData;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Takes the workshop's demonstration accounts out of a copy of staging, so
 * the copy can serve as the production database.
 *
 * What goes: every account at @crasadev.invalid and the shared demonstration
 * administrator admin@crasa.org. Each is made a "Former user" the way the
 * back office deletes an account (App\Support\FormerUser), so what they
 * authored keeps its author link and the audit log keeps their actions.
 * Their sessions, remember tokens and reset links go with them, and so do
 * the workshop rows tied to them: their delegate registrations and
 * enquiries (and any filed from a demonstration address), notifications
 * addressed to them and approvals they sent or decided. Nothing tied to
 * anyone else is touched.
 *
 * What stays: the sample data. Whether the comparator figures and the other
 * flagged rows go before launch is CRASA's decision, so this lists them and
 * names the command that strips them.
 *
 * The two-administrator rule of the back office does not apply here (the
 * demonstration administrators are exactly what is being removed), but the
 * command refuses to finish if no administrator would remain. Create the
 * real one first with crasa:create-admin.
 *
 * It refuses to run on any host but crasa.africa unless --force-host is
 * given, so it cannot be pointed at staging by mistake.
 */
class PrepareProduction extends Command
{
    public const PRODUCTION_HOST = 'crasa.africa';

    /** The shared demonstration administrator, outside the .invalid domain. */
    public const DEMO_ADMIN = 'admin@crasa.org';

    public const DEMO_DOMAIN = '@crasadev.invalid';

    protected $signature = 'crasa:prepare-production
        {--dry-run : Print what would be done and change nothing}
        {--force-host : Run although APP_URL is not on crasa.africa}';

    protected $description = 'Remove the demonstration accounts and their workshop rows from a copy of staging before it serves production';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        if ($host !== self::PRODUCTION_HOST && ! $this->option('force-host')) {
            $this->error('APP_URL is on "'.$host.'", not '.self::PRODUCTION_HOST.'. Nothing was changed.');
            $this->error('Pass --force-host to run on this host anyway.');

            return self::FAILURE;
        }

        $accounts = self::demoAccounts()->get();
        $ids = $accounts->pluck('id')->all();
        $emails = $accounts->pluck('email')->map(fn ($e) => mb_strtolower($e))->all();

        $remaining = self::remainingAdministrators($ids);

        $counts = [
            'accounts' => count($ids),
            'administrators_among_them' => $accounts->filter(fn (User $u) => $u->role === Role::Administrator)->count(),
            'sessions' => $ids === [] ? 0 : DB::table('sessions')->whereIn('user_id', $ids)->count(),
            'remember_tokens' => $accounts->filter(fn (User $u) => $u->getRememberToken() !== null)->count(),
            'reset_links' => $emails === [] ? 0 : DB::table('password_reset_tokens')->whereIn(DB::raw('LOWER(email)'), $emails)->count(),
            'delegate_registrations' => $this->registrations($ids, $emails)->count(),
            'enquiries' => $this->enquiries($emails)->count(),
            'notifications' => $ids === [] ? 0 : DB::table('notifications')
                ->where('notifiable_type', (new User)->getMorphClass())->whereIn('notifiable_id', $ids)->count(),
            'approvals' => $this->approvals($ids)->count(),
            'administrators_remaining' => $remaining->count(),
        ];

        $this->line($dry ? 'Dry run: nothing is changed.' : 'Preparing this database for production.');
        $this->newLine();
        $this->table(['Demonstration accounts and their rows', $dry ? 'Would be removed' : 'Removed'], [
            ['Accounts made "Former user"', $counts['accounts']],
            ['  of which administrators', $counts['administrators_among_them']],
            ['Sessions', $counts['sessions']],
            ['Remember tokens', $counts['remember_tokens']],
            ['Password reset and invitation links', $counts['reset_links']],
            ['Delegate registrations', $counts['delegate_registrations']],
            ['Enquiries', $counts['enquiries']],
            ['Notifications', $counts['notifications']],
            ['Approvals', $counts['approvals']],
        ]);

        $this->table(['Administrators that remain', 'Count'], [
            ['Active', $remaining->where('status', UserStatus::Active)->count()],
            ['Invited, password not yet set', $remaining->where('status', UserStatus::Invited)->count()],
        ]);

        $samples = SampleData::counts();
        $this->line('Sample data is left in place. Rows flagged is_sample, per record type:');
        $this->table(['Record type', 'Rows flagged'], collect($samples)->map(fn ($n, $type) => [$type, $n])->values()->all() ?: [['none', 0]]);
        $this->line('Whether to remove it is CRASA\'s decision. The command that does: content:strip-samples (try --dry-run first).');
        $this->newLine();

        if ($remaining->isEmpty()) {
            $this->error('No administrator would remain. Nothing was changed.');
            $this->error('Create the real administrator first: php artisan crasa:create-admin --name="..." --email="..."');

            return self::FAILURE;
        }

        if ($remaining->where('status', UserStatus::Active)->isEmpty()) {
            $this->warn('Every remaining administrator is still invited: one of them must set a password before anyone can sign in to the back office.');
        }

        if ($dry) {
            $this->info('Dry run complete. Run again without --dry-run to apply.');

            return self::SUCCESS;
        }

        if ($counts['accounts'] === 0) {
            $this->info('No demonstration accounts. Nothing to remove.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($accounts, $ids, $emails, $counts) {
            // Checked again inside the transaction: an administrator could
            // have been suspended between the count above and now.
            if (self::remainingAdministrators($ids)->isEmpty()) {
                throw new RuntimeException('No administrator would remain.');
            }

            $this->registrations($ids, $emails)->delete();
            $this->enquiries($emails)->delete();

            foreach ($this->approvals($ids)->get() as $approval) {
                $this->closeApproval($approval);
            }

            DB::table('sessions')->whereIn('user_id', $ids)->delete();
            DB::table('password_reset_tokens')->whereIn(DB::raw('LOWER(email)'), $emails)->delete();

            foreach ($accounts as $user) {
                FormerUser::anonymise($user);

                // Suspended, as every former user is, and no longer flagged
                // as sample: content:strip-samples deletes sample accounts
                // outright, which would take the "Former user" line out of
                // the audit log along with the row.
                $user->forceFill(['status' => UserStatus::Suspended, 'is_sample' => false])->saveQuietly();
            }

            // Notifications addressed to them: FormerUser deletes these, and
            // this catches any left on a row the relation does not reach.
            DB::table('notifications')->where('notifiable_type', (new User)->getMorphClass())
                ->whereIn('notifiable_id', $ids)->delete();

            AuditLog::record('production_prepared', null, [
                'former_user_ids' => $ids,
                'counts' => $counts,
                'sample_rows_left' => SampleData::total(),
            ], null);
        });

        $this->info('Done. '.$counts['accounts'].' demonstration account(s) removed. One audit entry written.');

        return self::SUCCESS;
    }

    /** Every account the command removes. */
    public static function demoAccounts(): Builder
    {
        return User::query()->withoutGlobalScopes()
            ->whereNull('anonymised_at')
            ->where(fn ($q) => $q
                ->whereRaw('LOWER(email) LIKE ?', ['%'.self::DEMO_DOMAIN])
                ->orWhereRaw('LOWER(email) = ?', [self::DEMO_ADMIN]))
            ->orderBy('id');
    }

    /**
     * Administrators left once these accounts are gone: active ones, and
     * invited ones (which is what crasa:create-admin makes). A suspended
     * administrator cannot sign in and does not count.
     *
     * @param  list<int>  $removed
     * @return Collection<int, User>
     */
    public static function remainingAdministrators(array $removed): Collection
    {
        return User::query()->withoutGlobalScopes()
            ->where('role', Role::Administrator->value)
            ->whereNull('custom_role_id')
            ->whereNull('anonymised_at')
            ->whereIn('status', [UserStatus::Active->value, UserStatus::Invited->value])
            ->when($removed !== [], fn ($q) => $q->whereNotIn('id', $removed))
            ->get();
    }

    private function registrations(array $ids, array $emails): Builder
    {
        return DelegateRegistration::query()->withoutGlobalScopes()
            ->where(fn ($q) => $q
                ->whereIn('user_id', $ids ?: [0])
                ->orWhere(fn ($e) => self::demoAddress($e, 'delegate_email', $emails)));
    }

    private function enquiries(array $emails): Builder
    {
        return Enquiry::query()->withoutGlobalScopes()
            ->where(fn ($e) => self::demoAddress($e, 'email', $emails));
    }

    /**
     * An address of a removed account, or any demonstration address: a
     * registration or an enquiry filed from one in the room is a workshop
     * row even where no account carries that address.
     */
    private static function demoAddress($query, string $column, array $emails): void
    {
        $query->whereIn(DB::raw('LOWER('.$column.')'), $emails ?: [''])
            ->orWhereRaw('LOWER('.$column.') LIKE ?', ['%'.self::DEMO_DOMAIN])
            ->orWhereRaw('LOWER('.$column.') = ?', [self::DEMO_ADMIN]);
    }

    private function approvals(array $ids): Builder
    {
        return Approval::query()
            ->where(fn ($q) => $q
                ->whereIn('submitted_by', $ids ?: [0])
                ->orWhereIn('decided_by', $ids ?: [0]));
    }

    /**
     * An approval still open leaves its page or news item "in review" and
     * locked; it goes back to draft first, as a withdrawal would do it.
     */
    private function closeApproval(Approval $approval): void
    {
        if ($approval->isOpen()) {
            $model = $approval->approvable;

            if ($model instanceof Page && $model->status === PageStatus::Review) {
                $model->update(['status' => PageStatus::Draft]);
            }

            if ($model instanceof NewsItem && $model->status === 'review') {
                $model->update(['status' => 'draft']);
            }
        }

        $approval->delete();
    }
}
