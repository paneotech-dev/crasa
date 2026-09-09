<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Page;
use App\Support\Locales;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

/**
 * The navigation builder, screen 14.
 *
 * Until 9 September 2026 the header read a config array and the footer's
 * columns were written into a Blade template, so the Secretariat could not
 * rename a menu item, reorder one or hide one without a deploy. That
 * contradicts the contract's stated core requirement and the editability
 * target screen 14 itself draws on the same page.
 *
 * A DESTINATION IS CHOSEN, NOT TYPED. Route names come from the router and
 * page slugs from the page records, so an editor picks from what exists
 * rather than typing a path that will 404 after a rename. The free-text
 * option is there for external links, which is what free text is for.
 */
class MenuController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize(Permission::ManageContent->value);

        $menu = in_array($request->query('menu'), MenuItem::MENUS, true)
            ? $request->query('menu')
            : 'header';

        return view('pages.admin.menus.index', [
            'menu' => $menu,
            'menus' => MenuItem::MENUS,
            'items' => MenuItem::inMenu($menu)
                ->whereNull('parent_id')
                ->with(['translations', 'children.translations'])
                ->orderBy('position')
                ->get(),
        ] + $this->options());
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::ManageContent->value);

        $data = $this->validated($request);

        $item = MenuItem::create([
            'menu' => $data['menu'],
            'parent_id' => $data['parent_id'] ?? null,
            'destination_type' => $data['destination_type'],
            'destination' => $data['destination'] ?? null,
            'is_visible' => true,
            'is_sample' => false,
            // Last, so a new item never displaces an existing one.
            'position' => (int) MenuItem::inMenu($data['menu'])
                ->where('parent_id', $data['parent_id'] ?? null)
                ->max('position') + 1,
        ]);

        $this->saveLabels($item, $request);

        return redirect()
            ->route('admin.menus', ['locale' => app()->getLocale(), 'menu' => $item->menu])
            ->with('status', __('admin.menu_added'));
    }

    public function update(Request $request, MenuItem $item): RedirectResponse
    {
        Gate::authorize(Permission::ManageContent->value);

        $data = $this->validated($request, $item);

        $item->update([
            'destination_type' => $data['destination_type'],
            'destination' => $data['destination'] ?? null,
        ]);

        $this->saveLabels($item, $request);

        return redirect()
            ->route('admin.menus', ['locale' => app()->getLocale(), 'menu' => $item->menu])
            ->with('status', __('admin.menu_saved'));
    }

    /**
     * Up or down among its siblings.
     *
     * Positions are rewritten for the whole sibling set rather than swapped
     * in place, so a set that has drifted out of sequence — by a deletion,
     * a bad import, two editors at once — comes back in order rather than
     * staying broken in a new way.
     */
    public function move(Request $request, MenuItem $item): RedirectResponse
    {
        Gate::authorize(Permission::ManageContent->value);

        $direction = $request->string('direction')->value() === 'up' ? -1 : 1;

        $siblings = MenuItem::inMenu($item->menu)
            ->where('parent_id', $item->parent_id)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->values();

        $index = $siblings->search(fn (MenuItem $s) => $s->is($item));
        $target = $index + $direction;

        if ($index !== false && $target >= 0 && $target < $siblings->count()) {
            $ordered = $siblings->all();
            [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

            foreach ($ordered as $position => $sibling) {
                $sibling->update(['position' => $position]);
            }
        }

        return back()->with('status', __('admin.menu_moved'));
    }

    public function toggle(MenuItem $item): RedirectResponse
    {
        Gate::authorize(Permission::ManageContent->value);

        $item->update(['is_visible' => ! $item->is_visible]);

        return back()->with('status', $item->is_visible ? __('admin.menu_shown') : __('admin.menu_hidden'));
    }

    public function destroy(MenuItem $item): RedirectResponse
    {
        Gate::authorize(Permission::ManageContent->value);

        $menu = $item->menu;
        // Children go with it, by the foreign key. A column heading whose
        // links survived it would be a set of orphans in the footer.
        $item->delete();

        return redirect()
            ->route('admin.menus', ['locale' => app()->getLocale(), 'menu' => $menu])
            ->with('status', __('admin.menu_removed'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?MenuItem $item = null): array
    {
        return $request->validate([
            'menu' => [$item ? 'nullable' : 'required', Rule::in(MenuItem::MENUS)],
            'parent_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'destination_type' => ['required', Rule::in(MenuItem::TYPES)],
            'destination' => ['nullable', 'string', 'max:255'],
        ] + $this->labelRules());
    }

    /** @return array<string, mixed> */
    private function labelRules(): array
    {
        $rules = [];

        foreach (Locales::SUPPORTED as $locale) {
            // English is required because it is the fallback: an item with
            // no English label has nothing to show when a translation is
            // missing, and would render as a blank link.
            $rules['label_'.$locale] = [$locale === Locales::FALLBACK ? 'required' : 'nullable', 'string', 'max:120'];
        }

        return $rules;
    }

    private function saveLabels(MenuItem $item, Request $request): void
    {
        foreach (Locales::SUPPORTED as $locale) {
            $label = $request->string('label_'.$locale)->value();

            if ($label !== '') {
                $item->setTranslations($locale, ['label' => $label]);
            }
        }
    }

    /**
     * What an editor can point an item at.
     *
     * Named public routes only. The back office, the API-ish endpoints and
     * anything taking a parameter other than the locale are excluded: a
     * menu item pointing at admin.users.suspend is not a menu item.
     *
     * @return array<string, mixed>
     */
    private function options(): array
    {
        $routes = collect(Route::getRoutes()->getRoutesByName())
            ->reject(fn ($route, string $name) => str_starts_with($name, 'admin.')
                || str_contains($name, '.')
                || $route->parameterNames() !== ['locale'])
            ->keys()
            ->sort()
            ->values()
            ->all();

        return [
            'routes' => $routes,
            'pages' => Page::orderBy('slug')->get(['id', 'slug']),
        ];
    }
}
