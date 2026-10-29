<?php

namespace App\Http\Requests;

use App\Support\Locales;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * No puzzle CAPTCHA. CLAUDE.md and WCAG 2.1 AA both point away from it: a
 * puzzle a screen reader user cannot solve is an accessibility failure, and
 * on a regulatory platform it excludes the exact people who need to make
 * contact. A honeypot and a rate limit do the job, with Cloudflare
 * Turnstile in front of the route when its keys are set (VerifyTurnstile).
 */
class StoreEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'string', 'email:rfc', 'max:160'],
            'organisation' => ['nullable', 'string', 'max:200'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'min:20', 'max:5000'],
            // The honeypot. A person never sees this field, so anything in
            // it came from a bot.
            'website' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'website.prohibited' => __('contact.rejected'),
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('contact.field_name'),
            'email' => __('contact.field_email'),
            'organisation' => __('contact.field_organisation'),
            'country_id' => __('contact.field_country'),
            'subject' => __('contact.field_subject'),
            'message' => __('contact.field_message'),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! Locales::isSupported($this->input('locale', app()->getLocale()))) {
                    $validator->errors()->add('locale', __('contact.unsupported_locale'));
                }
            },
        ];
    }
}
