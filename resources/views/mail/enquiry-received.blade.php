<p>{{ __('d3_i18n.enquiry_intro') }}</p>

<table>
    <tr><td><strong>{{ __('d3_i18n.enquiry_name') }}</strong></td><td>{{ $enquiry->name }}</td></tr>
    <tr><td><strong>{{ __('d3_i18n.enquiry_email') }}</strong></td><td>{{ $enquiry->email }}</td></tr>
    @if ($enquiry->organisation)
        <tr><td><strong>{{ __('d3_i18n.enquiry_organisation') }}</strong></td><td>{{ $enquiry->organisation }}</td></tr>
    @endif
    @if ($enquiry->country)
        <tr><td><strong>{{ __('d3_i18n.enquiry_country') }}</strong></td><td>{{ $enquiry->country->name }}</td></tr>
    @endif
    <tr><td><strong>{{ __('d3_i18n.enquiry_topic') }}</strong></td><td>{{ $enquiry->subject }}</td></tr>
    <tr><td><strong>{{ __('d3_i18n.enquiry_language') }}</strong></td><td>{{ strtoupper($enquiry->locale) }}</td></tr>
    <tr><td><strong>{{ __('d3_i18n.enquiry_received') }}</strong></td><td>{{ $enquiry->created_at->translatedFormat('j F Y, H:i') }}</td></tr>
</table>

<p><strong>{{ __('d3_i18n.enquiry_message') }}</strong></p>
<p>{{ $enquiry->message }}</p>

<p>{{ __('d3_i18n.enquiry_reply') }}</p>
