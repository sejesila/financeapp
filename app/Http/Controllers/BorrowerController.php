<?php

namespace App\Http\Controllers;

use App\Models\Borrower;
use App\Models\LoanGiven;
use App\Services\BorrowerStatsService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;

// Routes (put 'borrowers-search' before the {borrower} routes):
//   Route::get('borrowers-search', [BorrowerController::class, 'search'])->name('borrowers.search');
//   Route::get('borrowers', [BorrowerController::class, 'index'])->name('borrowers.index');
//   Route::get('borrowers/{borrower}', [BorrowerController::class, 'show'])->name('borrowers.show');
//   Route::post('borrowers/{borrower}/merge', [BorrowerController::class, 'merge'])->name('borrowers.merge');
class BorrowerController extends Controller implements HasMiddleware
{
    use AuthorizesRequests;

    public function __construct(protected BorrowerStatsService $stats) {}

    public static function middleware(): array
    {
        return ['auth'];
    }

    public function index(Request $request)
    {
        $sort = $request->get('sort', 'interest_earned'); // interest_earned | risk_score | total_borrowed | outstanding | name

        $rows = $this->stats->forAll();
        $rows = $sort === 'name'
            ? $rows->sortBy(fn ($r) => $r['borrower']->name)
            : $rows->sortByDesc(fn ($r) => $r[$sort] ?? 0);

        return view('borrowers.index', ['rows' => $rows->values(), 'sort' => $sort]);
    }

    public function show(Borrower $borrower)
    {
        $borrower->load(['loans' => fn ($q) => $q->with(['payments', 'referrer'])->orderByDesc('disbursed_date')]);

        return view('borrowers.show', [
            'borrower' => $borrower,
            'stats'    => $this->stats->forBorrower($borrower),
            'loans'    => $borrower->loans,
            'others'   => Borrower::where('id', '!=', $borrower->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }
    /** Autocomplete for the "new loan" form. */
    public function search(Request $request)
    {
        return Borrower::where('name', 'like', '%' . $request->get('q') . '%')
            ->orderBy('name')->limit(10)->get(['id', 'name', 'contact']);
    }

    /** Merge a duplicate: moves source's loans onto this borrower, then deletes source. */
    public function merge(Request $request, Borrower $borrower)
    {
        $data = $request->validate([
            'source_id' => 'required|integer',
        ]);

        // findOrFail applies the user-ownership global scope, so another user's
        // borrower ID returns a 404 instead of being merged.
        $source = Borrower::findOrFail($data['source_id']);

        if ($source->id === $borrower->id) {
            return back()->with('error', "Can't merge a borrower into itself.");
        }

        DB::transaction(function () use ($borrower, $source) {
            LoanGiven::where('borrower_id', $source->id)->update(['borrower_id' => $borrower->id]);
            $borrower->update([
                'contact' => $borrower->contact ?: $source->contact,
                'notes'   => trim(($borrower->notes ?? '') . "\n" . ($source->notes ?? '')) ?: null,
            ]);
            $source->delete();
        });

        return redirect()->route('borrowers.show', $borrower)
            ->with('success', "Merged {$source->name} into {$borrower->name}.");
    }
}
