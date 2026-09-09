{{--
    A signed-in page that both surfaces reach.

    The completeness matrix is the case: a member state representative opens
    it from the extranet's REGION group and the Secretariat opens it from
    the back office, and it is one URL. Rendering it in the public chrome
    put a signed-in reader back on the public site mid-task; rendering it in
    either shell's own navigation would give half the readers the wrong one.

    So the navigation is chosen the same way LoginController::destinationFor
    chooses a landing page, from what the reader can actually reach. One
    question, one answer, in one place.
--}}
@extends('layouts.shell', [
    'groups' => \App\Support\Workspace::isBackOffice(auth()->user())
        ? \App\Support\ShellNavigation::administration()
        : \App\Support\ShellNavigation::extranet(),
    'brand' => \App\Support\Workspace::isBackOffice(auth()->user())
        ? __('admin.back_office')
        : __('extranet.title'),
    'home' => \App\Support\Workspace::isBackOffice(auth()->user())
        ? route('admin.dashboard', ['locale' => app()->getLocale()])
        : route('extranet.dashboard', ['locale' => app()->getLocale()]),
])
