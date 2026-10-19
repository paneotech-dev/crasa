{{-- One account mail with a single link: the invitation and the password
     reset. Plain HTML in the language the notification was sent in. --}}
<p>{{ $greeting }}</p>

@foreach ($lines as $line)
    <p>{{ $line }}</p>
@endforeach

<p><a href="{{ $url }}">{{ $action }}</a></p>

@foreach ($after as $line)
    <p>{{ $line }}</p>
@endforeach

<p>{{ $signature }}</p>

<p style="font-size: 12px; color: #4a5560;">{{ $fallback }}<br>{{ $url }}</p>
