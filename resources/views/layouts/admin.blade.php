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
    'brand' => __('admin.back_office'),
    'home' => route('admin.dashboard', ['locale' => app()->getLocale()]),
])
