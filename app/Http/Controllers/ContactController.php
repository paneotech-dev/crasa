<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnquiryRequest;
use App\Mail\EnquiryReceived;
use App\Models\Country;
use App\Models\Enquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ContactController extends Controller
{
    public function show(Request $request)
    {
        return view('pages.contact', [
            'countries' => Country::orderBy('name')->get(),
        ]);
    }

    public function store(StoreEnquiryRequest $request): RedirectResponse
    {
        // Stored first. If the mail fails, the enquiry is still on the
        // platform and findable through Enquiry::undelivered().
        $enquiry = Enquiry::create([
            'name' => $request->string('name')->trim()->value(),
            'email' => $request->string('email')->trim()->value(),
            'organisation' => $request->string('organisation')->trim()->value() ?: null,
            'country_id' => $request->integer('country_id') ?: null,
            'subject' => $request->string('subject')->trim()->value(),
            'message' => $request->string('message')->trim()->value(),
            'locale' => app()->getLocale(),
            'ip_address' => $request->ip(),
        ]);

        try {
            Mail::to(config('crasa.enquiry_recipient'))
                ->queue(new EnquiryReceived($enquiry));

            $enquiry->markDelivered();
        } catch (Throwable $e) {
            // The sender is told their enquiry was received, because it was.
            // The undelivered notification is our problem, not theirs.
            Log::error('An enquiry was stored but its notification could not be queued.', [
                'enquiry' => $enquiry->id,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('contact', ['locale' => app()->getLocale()])
            ->with('enquiry_received', true);
    }
}
