{{ $greeting }}

@foreach ($lines as $line)
{{ $line }}

@endforeach
{{ $action }}: {{ $url }}

@foreach ($after as $line)
{{ $line }}

@endforeach
{{ $signature }}
