<?php

namespace Tests\Feature {

    use App\Enums\Clearance;
    use App\Enums\CommitteeRole;
    use App\Enums\SubmissionStatus;
    use App\Events\DocumentAdded;
    use App\Events\RegistrationConfirmed;
    use App\Events\RegistrationReceived;
    use App\Models\Committee;
    use App\Models\Country;
    use App\Models\DelegateRegistration;
    use App\Models\Document;
    use App\Models\Folder;
    use App\Models\Meeting;
    use App\Models\NewsItem;
    use App\Models\Submission;
    use App\Models\User;
    use App\Support\Approvals;
    use Database\Seeders\CommitteeSeeder;
    use Database\Seeders\CountrySeeder;
    use Database\Seeders\IndicatorSeeder;
    use Database\Seeders\SectorSeeder;
    use Illuminate\Foundation\Testing\RefreshDatabase;
    use Illuminate\Http\UploadedFile;
    use Illuminate\Support\Facades\Storage;
    use Tests\TestCase;

    /**
     * Client brief item 7: every source reaches the bell through the one
     * notifier, only for people who may open the item, and never twice.
     */
    class NotifySourcesTest extends TestCase
    {
        use RefreshDatabase;

        private Country $botswana;

        private Country $mozambique;

        private User $admin;

        private User $editor;

        private User $moderator;

        private User $bwRep;

        private User $bwRep2;

        private User $mzRep;

        private User $viewer;

        protected function setUp(): void
        {
            parent::setUp();
            config(['crasa.two_factor_required' => false]);
            Storage::fake('local');
            Storage::fake('public');
            $this->seed(CountrySeeder::class);
            $this->seed(SectorSeeder::class);
            $this->seed(CommitteeSeeder::class);

            $this->botswana = Country::where('iso_code', 'bw')->sole();
            $this->mozambique = Country::where('iso_code', 'mz')->sole();
            $this->admin = User::factory()->administrator()->create();
            $this->editor = User::factory()->editor()->create();
            $this->moderator = User::factory()->moderator()->create();
            $this->bwRep = User::factory()->memberStateRep($this->botswana)->create();
            $this->bwRep2 = User::factory()->memberStateRep($this->botswana)->create();
            $this->mzRep = User::factory()->memberStateRep($this->mozambique)->create();
            $this->viewer = User::factory()->viewer()->create();
        }

        private function told(User $user, ?string $category = null): int
        {
            $query = $user->notifications();

            if ($category !== null) {
                $query->where('data->category', $category);
            }

            return $query->count();
        }

        private function folder(?Country $country, Clearance $clearance = Clearance::Member, ?Committee $committee = null): Folder
        {
            return Folder::create([
                'name' => 'Working papers '.($country?->iso_code ?? 'regional'),
                'slug' => 'working-papers-'.uniqid(),
                'country_id' => $country?->id,
                'committee_id' => $committee?->id,
                'clearance' => $clearance,
                'is_sample' => true,
            ]);
        }

        private function document(Folder $folder, ?User $uploader = null): Document
        {
            return Document::create([
                'documentable_type' => Folder::class,
                'documentable_id' => $folder->id,
                'folder_id' => $folder->id,
                'country_id' => $folder->country_id,
                'committee_id' => $folder->committee_id,
                'uploaded_by' => $uploader?->id,
                'file_path' => 'extranet/'.$folder->id.'/paper.pdf',
                'original_filename' => 'Spectrum paper.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 1024,
                'locale' => 'en',
                'clearance' => $folder->clearance,
                'is_sample' => true,
            ]);
        }

        /* Approvals ------------------------------------------------------ */

        public function test_an_approval_request_reaches_those_who_may_approve_and_the_decision_reaches_the_author(): void
        {
            $news = NewsItem::factory()->draft()->create();
            $news->setTranslations('en', ['title' => 'Spectrum workshop held in Lilongwe']);

            $approval = Approvals::submit($news, $this->editor);

            $this->assertSame(1, $this->told($this->moderator, 'approval_request'));
            $this->assertSame(1, $this->told($this->admin, 'approval_request'));
            $this->assertSame(0, $this->told($this->editor), 'The author does not approve their own item.');
            $this->assertSame(0, $this->told($this->bwRep));
            $this->assertSame(0, $this->told($this->viewer));

            $notice = $this->moderator->notifications()->sole();
            $this->assertStringContainsString('/admin/approvals/'.$approval->id, $notice->data['url']);
            $this->actingAs($this->moderator)->get('/en/notifications/'.$notice->id)->assertRedirect();
            $this->actingAs($this->moderator)->get(parse_url($notice->data['url'], PHP_URL_PATH))->assertOk();

            Approvals::return($approval, $this->moderator, 'Add the 1997 protocol reference.');
            $this->assertSame(1, $this->told($this->editor, 'decision'));

            $again = Approvals::submit($news->fresh(), $this->editor);
            Approvals::approve($again, $this->moderator);
            $this->assertSame(2, $this->told($this->editor, 'decision'));
        }

        /* Validation results -------------------------------------------- */

        public function test_a_validation_result_reaches_every_representative_of_that_member_state_only(): void
        {
            $this->seed(IndicatorSeeder::class);
            $submission = Submission::create([
                'country_id' => $this->botswana->id, 'submitted_by' => $this->bwRep->id,
                'period' => '2026', 'status' => SubmissionStatus::Submitted, 'submitted_at' => now(),
            ]);

            $this->actingAs($this->admin)
                ->post('/en/admin/submissions/'.$submission->id.'/decide', ['status' => 'returned', 'note' => 'Attach the operator data.'])
                ->assertRedirect();

            $this->assertSame(1, $this->told($this->bwRep, 'validation'));
            $this->assertSame(1, $this->told($this->bwRep2, 'validation'), 'Every representative of the state, not only the author.');
            $this->assertSame(0, $this->told($this->mzRep));

            $notice = $this->bwRep2->notifications()->sole();
            $this->assertStringContainsString('returned with comments', $notice->data['title']);
            $this->actingAs($this->bwRep2)->get(parse_url($notice->data['url'], PHP_URL_PATH))->assertOk();
        }

        public function test_a_filed_return_reaches_the_validators(): void
        {
            $this->seed(IndicatorSeeder::class);
            $submission = Submission::create([
                'country_id' => $this->botswana->id, 'submitted_by' => $this->bwRep->id,
                'period' => '2026', 'status' => SubmissionStatus::Submitted, 'submitted_at' => now(),
            ]);

            $sent = \App\Support\Notifier::send(
                \App\Support\Notifier::activeUsers(),
                new \App\Notifications\SubmissionStateChanged($submission),
                'approval_request',
                'submission:'.$submission->id.':submitted',
                fn (User $user) => $user->hasPermission(\App\Enums\Permission::ValidateSubmission),
            );

            $this->assertGreaterThan(0, $sent);
            $this->assertSame(1, $this->told($this->admin, 'approval_request'));
            $this->assertSame(0, $this->told($this->bwRep));
            $this->assertSame(0, $this->told($this->viewer));
        }

        /* Meetings ------------------------------------------------------- */

        public function test_a_meeting_moved_or_cancelled_reaches_the_committee_and_registered_people_once(): void
        {
            $committee = Committee::firstOrFail();
            $committee->users()->attach($this->bwRep->id, ['role' => CommitteeRole::Member->value, 'is_sample' => true]);
            $meeting = Meeting::factory()->create(['committee_id' => $committee->id, 'starts_on' => now()->addMonth(), 'clearance' => 'member']);

            // Registered for it, and also holding a seat: told once.
            DelegateRegistration::create([
                'meeting_id' => $meeting->id, 'user_id' => $this->bwRep->id, 'country_id' => $this->botswana->id,
                'delegate_name' => 'Botswana delegate', 'delegate_email' => 'bw@example.org', 'arrival_on' => now()->addMonth(),
            ]);
            // Registered, no seat.
            $committeeMember = User::factory()->committeeMember($this->mozambique)->create();
            DelegateRegistration::create([
                'meeting_id' => $meeting->id, 'user_id' => $committeeMember->id, 'country_id' => $this->mozambique->id,
                'delegate_name' => 'Mozambique delegate', 'delegate_email' => 'mz@example.org', 'arrival_on' => now()->addMonth(),
            ]);

            $meeting->update(['starts_on' => now()->addMonths(2)]);

            $this->assertSame(1, $this->told($this->bwRep, 'meeting'));
            $this->assertSame(1, $this->told($committeeMember, 'meeting'));
            $this->assertSame(0, $this->told($this->mzRep), 'No seat, no registration.');
            $this->assertSame(0, $this->told($this->viewer));

            $this->travel(2)->seconds();
            $meeting->update(['status' => 'cancelled']);
            $this->assertSame(2, $this->told($this->bwRep, 'meeting'));
            $this->assertStringContainsString('was cancelled', $this->bwRep->notifications()->first()->data['title']);

            // A change nobody needs to hear about notifies nobody.
            $meeting->update(['summary' => 'Agenda notes']);
            $this->assertSame(2, $this->told($this->bwRep));
        }

        public function test_a_confidential_meeting_is_not_announced_to_someone_who_cannot_open_it(): void
        {
            $committee = Committee::firstOrFail();
            $meeting = Meeting::factory()->create(['committee_id' => $committee->id, 'clearance' => 'secretariat']);
            DelegateRegistration::create([
                'meeting_id' => $meeting->id, 'user_id' => $this->bwRep->id, 'country_id' => $this->botswana->id,
                'delegate_name' => 'Botswana delegate', 'delegate_email' => 'bw@example.org', 'arrival_on' => now()->addMonth(),
            ]);

            $meeting->update(['venue' => 'Another room']);

            $this->assertSame(0, $this->told($this->bwRep));
        }

        /* Documents ------------------------------------------------------ */

        public function test_an_upload_tells_everyone_who_may_see_the_folder_except_the_uploader(): void
        {
            $folder = $this->folder($this->botswana);

            $this->actingAs($this->bwRep)
                ->post('/en/extranet/folders/'.$folder->id.'/upload', ['file' => UploadedFile::fake()->create('Spectrum paper.pdf', 10, 'application/pdf')])
                ->assertRedirect();

            $this->assertSame(0, $this->told($this->bwRep), 'The uploader is not told about their own upload.');
            $this->assertSame(1, $this->told($this->bwRep2, 'document'));
            $this->assertSame(0, $this->told($this->mzRep), 'Mozambique may not open a Botswana folder.');
            $this->assertSame(0, $this->told($this->viewer), 'A viewer has no member area.');

            $notice = $this->bwRep2->notifications()->sole();
            $this->actingAs($this->bwRep2)->get(parse_url($notice->data['url'], PHP_URL_PATH))->assertOk();

            // The same document reported again by the event is the same notice.
            event(new DocumentAdded(Document::sole(), $folder));
            $this->assertSame(1, $this->told($this->bwRep2));
        }

        public function test_the_document_added_listener_tells_the_folder_audience_except_the_uploader(): void
        {
            $folder = $this->folder($this->mozambique);
            $document = $this->document($folder, $this->mzRep);
            $other = User::factory()->memberStateRep($this->mozambique)->create();

            event(new DocumentAdded($document, $folder));
            event(new DocumentAdded($document, $folder));

            $this->assertSame(1, $this->told($other, 'document'), 'Told once, though the event came twice.');
            $this->assertSame(0, $this->told($this->mzRep), 'Not the uploader.');
            $this->assertSame(0, $this->told($this->bwRep), 'Not another member state.');
            $this->assertStringContainsString('Spectrum paper.pdf', $other->notifications()->sole()->data['title']);
        }

        public function test_a_secretariat_folder_is_not_announced_to_members(): void
        {
            $folder = $this->folder(null, Clearance::Secretariat);
            $document = $this->document($folder, $this->editor);

            event(new DocumentAdded($document, $folder));

            $this->assertSame(0, $this->told($this->bwRep));
            $this->assertSame(0, $this->told($this->mzRep));
        }

        /* Registrations -------------------------------------------------- */

        private function registration(?User $user = null): DelegateRegistration
        {
            $meeting = Meeting::factory()->create(['clearance' => 'public', 'starts_on' => now()->addMonth()]);

            return DelegateRegistration::create([
                'meeting_id' => $meeting->id, 'user_id' => $user?->id, 'country_id' => $this->botswana->id,
                'delegate_name' => 'Kagiso Molefe', 'delegate_email' => 'k@example.org', 'arrival_on' => now()->addMonth(),
                'status' => 'confirmed',
            ]);
        }

        public function test_the_registration_confirmed_listener_tells_the_person_and_their_representatives(): void
        {
            $delegate = User::factory()->committeeMember($this->botswana)->create();
            $registration = $this->registration($delegate);

            event(new RegistrationConfirmed($registration));
            event(new RegistrationConfirmed($registration));

            $this->assertSame(1, $this->told($delegate, 'registration'));
            $this->assertSame(1, $this->told($this->bwRep, 'registration'));
            $this->assertSame(1, $this->told($this->bwRep2, 'registration'));
            $this->assertSame(0, $this->told($this->mzRep));
            $this->assertStringContainsString('Kagiso Molefe', $this->bwRep->notifications()->sole()->data['title']);
        }

        public function test_a_registration_with_no_account_still_reaches_the_representatives(): void
        {
            event(new RegistrationConfirmed($this->registration()));

            $this->assertSame(1, $this->told($this->bwRep, 'registration'));
        }

        public function test_the_registration_received_listener_tells_the_person_and_the_secretariat(): void
        {
            $delegate = User::factory()->committeeMember($this->botswana)->create();
            $registration = $this->registration($delegate);

            event(new RegistrationReceived($registration));
            event(new RegistrationReceived($registration));

            $this->assertSame(1, $this->told($delegate, 'registration'));
            $this->assertStringContainsString('Your registration', $delegate->notifications()->sole()->data['title']);
            $this->assertSame(1, $this->told($this->admin, 'registration'), 'The Secretariat manages registrations.');
            $this->assertSame(0, $this->told($this->bwRep), 'Representatives hear when it is confirmed, not received.');
            $this->assertSame(0, $this->told($this->viewer));

            $url = $this->admin->notifications()->sole()->data['url'];
            $this->actingAs($this->admin)->get(parse_url($url, PHP_URL_PATH))->assertOk();
        }

        public function test_a_listener_ignores_an_event_without_its_model(): void
        {
            // Real events whose rows are gone by the time the listener runs:
            // a file moved to the bin, a file whose folder was deleted, and
            // a registration withdrawn.
            $folder = $this->folder($this->botswana);
            $binned = $this->document($folder, $this->bwRep);
            $binned->delete();
            event(new DocumentAdded($binned, $folder));

            $gone = $this->folder($this->botswana);
            $orphan = $this->document($gone, $this->bwRep);
            $gone->delete();
            event(new DocumentAdded($orphan, $gone));

            $registration = $this->registration(User::factory()->committeeMember($this->botswana)->create());
            $registration->delete();
            event(new RegistrationConfirmed($registration));
            event(new RegistrationReceived($registration));

            $this->assertSame(0, \Illuminate\Notifications\DatabaseNotification::count());
        }

        /* In-app only ----------------------------------------------------- */

        public function test_every_notice_is_in_app_only(): void
        {
            \Illuminate\Support\Facades\Mail::fake();

            event(new RegistrationConfirmed($this->registration()));

            \Illuminate\Support\Facades\Mail::assertNothingOutgoing();
            foreach (glob(app_path('Notifications/*.php')) as $file) {
                $class = 'App\\Notifications\\'.basename($file, '.php');
                if (in_array(\App\Notifications\Concerns\InApp::class, class_uses($class), true)) {
                    $this->assertSame(['database'], (new \ReflectionClass($class))->newInstanceWithoutConstructor()->via($this->bwRep), $class);
                }
            }
        }
    }
}
