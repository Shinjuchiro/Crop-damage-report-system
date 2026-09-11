<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Disaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * MAO records the disaster events farmers can attach to a damage report.
 * A disaster already cited by a report is kept, never deleted.
 */
class DisasterController extends Controller
{
    public const TYPES = ['typhoon', 'flood', 'drought', 'strong_winds', 'other'];

    public function index(Request $request)
    {
        // Archived events move to the Archive page (proposal section 72).
        $disasters = Disaster::active()
            ->when($request->filled('search'),
                fn ($query) => $query->where('name', 'like', '%' . $request->search . '%'))
            ->when($request->filled('type'),
                fn ($query) => $query->where('type', $request->type))
            ->withCount('damageReports')
            ->orderByDesc('date_start')
            ->paginate(15)
            ->withQueryString();

        return view('mao.disasters.index', [
            'disasters' => $disasters,
            'types'     => self::TYPES,
        ]);
    }

    public function create()
    {
        return view('mao.disasters.form', ['disaster' => null, 'types' => self::TYPES]);
    }

    public function store(Request $request)
    {
        $disaster = Disaster::create($this->validatedData($request));

        $this->log('Created disaster event: ' . $disaster->name, $disaster->id);

        return redirect()->route('mao.disasters.index')
            ->with('status', 'Disaster event added successfully.');
    }

    public function edit(Disaster $disaster)
    {
        return view('mao.disasters.form', ['disaster' => $disaster, 'types' => self::TYPES]);
    }

    public function update(Request $request, Disaster $disaster)
    {
        $disaster->update($this->validatedData($request));

        $this->log('Updated disaster event: ' . $disaster->name, $disaster->id);

        return redirect()->route('mao.disasters.index')
            ->with('status', 'Disaster event updated successfully.');
    }

    public function destroy(Disaster $disaster)
    {
        if ($this->timesUsed($disaster) > 0) {
            return back()->withErrors([
                'disaster' => 'This disaster event is already cited by damage reports or assistance records, so it cannot be deleted. Archive it instead.',
            ]);
        }

        $name = $disaster->name;
        $disaster->delete();

        $this->log('Deleted disaster event: ' . $name, null);

        return redirect()->route('mao.disasters.index')
            ->with('status', 'Disaster event deleted.');
    }

    /**
     * Close out an event once its season is over, without breaking any
     * report or assistance record that already cites it.
     */
    public function archive(Disaster $disaster)
    {
        $disaster->archive(Auth::id());

        $this->log('Archived disaster event: ' . $disaster->name, $disaster->id);

        return back()->with('status', 'Disaster event archived. Farmers can no longer link new reports to it.');
    }

    public function restore(Disaster $disaster)
    {
        $disaster->unarchive();

        $this->log('Restored disaster event: ' . $disaster->name, $disaster->id);

        return back()->with('status', 'Disaster event restored.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'type'       => ['required', Rule::in(self::TYPES)],
            'name'       => ['required', 'string', 'max:255'],
            'date_start' => ['required', 'date'],
            'date_end'   => ['nullable', 'date', 'after_or_equal:date_start'],
        ], [], [
            'date_start' => 'start date',
            'date_end'   => 'end date',
        ]);
    }

    private function timesUsed(Disaster $disaster): int
    {
        return DB::table('damage_report_disasters')->where('disaster_id', $disaster->id)->count()
            + DB::table('assistances')->where('disaster_id', $disaster->id)->count()
            + DB::table('assistance_allocations')->where('disaster_id', $disaster->id)->count();
    }

    private function log(string $action, ?int $targetId): void
    {
        AuditLog::create([
            'user_id'      => Auth::id(),
            'action'       => $action,
            'target_table' => 'disasters',
            'target_id'    => $targetId,
            'created_at'   => now(),
        ]);
    }
}
