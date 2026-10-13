{{--
    The back office, in the shared shell.

    Everything that was in this file -- the head, the alerts, the page
    header -- is in layouts.shell now, because the extranet needs the same
    of it and two copies drift. What is left is the three things that
    differ: which navigation, what the wordmark says, and where the mark
    points.
--}}
@extends('layouts.shell', [
    'groups' => \App\Support\ShellNavigation::administration(),
    // The submission pages use this layout for member-state representatives
    // too, who cannot enter the back office: their wordmark is the extranet's.
    'brand' => \App\Support\Workspace::isBackOffice(auth()->user())
        ? __('admin.back_office')
        : __('extranet.title'),
    'home' => \App\Support\Workspace::isBackOffice(auth()->user())
        ? route('admin.dashboard', ['locale' => app()->getLocale()])
        : route('extranet.dashboard', ['locale' => app()->getLocale()]),
])
