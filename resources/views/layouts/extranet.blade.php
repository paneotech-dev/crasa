{{--
    The extranet, in the shared shell.

    Screen 04 draws a navy left rail 220 to 280px wide with the mark, an
    "Extranet" label, flat links with Lucide icons and the reader's own
    clearance at the bottom. That is this shell at 264px, so the extranet
    stops carrying a second implementation of it.

    Two departures from the screen, both stated. The links are grouped
    rather than flat, because the rail now carries a row per committee the
    reader sits on and seven ungrouped items is where a reader starts
    scanning. And the clearance note moved from the rail to the dashboard,
    where it sits beside the folders it describes rather than under a list
    of links.
--}}
@extends('layouts.shell', [
    'groups' => \App\Support\ShellNavigation::extranet(),
    'brand' => __('extranet.title'),
    'home' => route('extranet.dashboard', ['locale' => app()->getLocale()]),
])
