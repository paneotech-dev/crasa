<?php

namespace App\Http\Controllers;

use App\Enums\BlockType;
use App\Models\Page;
use App\Models\PageBlock;
use App\Support\BlockData;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The homepage comparison's table, on its own.
 *
 * The block's selectors are a GET form on the homepage, and that stays the
 * floor: with no script, choosing an indicator or a member state reloads
 * the homepage at the block. With a script, the block asks this endpoint
 * for the table alone and swaps it in, so a change costs one small
 * response rather than the whole homepage.
 *
 * The parameters are the form's own (cmp_indicator, cmp_a, cmp_b) and are
 * resolved by the same code that renders the block, so the fragment and
 * the full page can never disagree about what a selection shows.
 */
class HomeComparisonController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $page = Page::published()->where('slug', 'home')->first();
        abort_if($page === null, 404);

        $block = PageBlock::query()
            ->where('page_id', $page->id)
            ->where('block_type', BlockType::FeaturedComparison->value)
            ->where('is_visible', true)
            ->with('translations')
            ->orderBy('position')
            ->first();
        abort_if($block === null, 404);

        $data = BlockData::comparison($block);
        abort_if($data['rows']->isEmpty(), 404);

        return response()
            ->view('blocks._comparison-result', ['block' => $block] + $data)
            // The fragment is never a page a reader lands on.
            ->header('X-Robots-Tag', 'noindex')
            ->header('Vary', 'X-Requested-With');
    }
}
