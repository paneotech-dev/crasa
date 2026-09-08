<p>An enquiry was submitted through the CRASA website.</p>

<table>
    <tr><td><strong>Name</strong></td><td>{{ $enquiry->name }}</td></tr>
    <tr><td><strong>Email</strong></td><td>{{ $enquiry->email }}</td></tr>
    @if ($enquiry->organisation)
        <tr><td><strong>Organisation</strong></td><td>{{ $enquiry->organisation }}</td></tr>
    @endif
    @if ($enquiry->country)
        <tr><td><strong>Member state</strong></td><td>{{ $enquiry->country->name }}</td></tr>
    @endif
    <tr><td><strong>Subject</strong></td><td>{{ $enquiry->subject }}</td></tr>
    <tr><td><strong>Language</strong></td><td>{{ strtoupper($enquiry->locale) }}</td></tr>
    <tr><td><strong>Received</strong></td><td>{{ $enquiry->created_at->format('j F Y, H:i') }}</td></tr>
</table>

<p><strong>Message</strong></p>
<p>{{ $enquiry->message }}</p>

<p>Reply to this message to answer the sender directly.</p>
