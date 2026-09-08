<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Support\CompletenessMatrix;
use Illuminate\Http\Request;

/**
 * BUILD_SPEC 6.1. Visible to the Secretariat and to members.
 */
class CompletenessController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(
            $request->user()?->hasPermission(Permission::ViewPublishedIndicators),
            403
        );

        $period = $request->string('period')->value() ?: null;

        return view('pages.completeness.index', [
            'matrix' => CompletenessMatrix::build($period),
            'indicators' => Indicator::active()->with('sector')->orderBy('display_order')->get(),
            'periods' => IndicatorValue::query()
                ->distinct()->orderByDesc('period')->pluck('period'),
            'period' => $period,
        ]);
    }
}
