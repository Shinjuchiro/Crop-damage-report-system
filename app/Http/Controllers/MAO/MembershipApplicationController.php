<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Farmer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * MAO reviews farmer registrations and approves or rejects them.
 * A farmer cannot use the system properly until approved.
 */
class MembershipApplicationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');

        $applications = Farmer::query()
            ->notDeleted()
            ->with(['user', 'association', 'barangay'])
            ->when(in_array($status, ['pending', 'active', 'rejected', 'inactive'], true),
                fn ($query) => $query->whereHas('user', fn ($user) => $user->where('status', $status)))
            ->when($request->filled('barangay_id'),
                fn ($query) => $query->where('barangay_id', $request->barangay_id))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%' . $request->search . '%';

                $query->where(function ($sub) use ($term) {
                    $sub->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhereHas('user', fn ($user) => $user->where('username', 'like', $term));
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('mao.membership-applications.index', [
            'applications' => $applications,
            'status'       => $status,
            'barangays'    => Barangay::orderBy('name')->get(),
        ]);
    }

    public function show(Farmer $farmer)
    {
        $farmer->load(['user', 'association', 'barangay', 'mainCrops.crop']);

        return view('mao.membership-applications.show', compact('farmer'));
    }

    public function approve(Farmer $farmer)
    {
        DB::transaction(function () use ($farmer) {
            $farmer->user->update(['status' => 'active']);

            $farmer->update([
                'activity_status'    => 'active',
                'last_activity_date' => now()->toDateString(),
            ]);

            $this->log('Approved farmer registration: ' . $farmer->full_name, $farmer->id);
        });

        return redirect()->route('mao.membership-applications.index')
            ->with('status', 'Farmer registration approved successfully.');
    }

    public function reject(Request $request, Farmer $farmer)
    {
        $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($farmer, $request) {
            $farmer->user->update(['status' => 'rejected']);

            $this->log(
                'Rejected farmer registration: ' . $farmer->full_name
                    . ($request->filled('rejection_reason') ? ' - ' . $request->rejection_reason : ''),
                $farmer->id
            );
        });

        return redirect()->route('mao.membership-applications.index')
            ->with('status', 'Farmer registration rejected.');
    }

    /**
     * Archiving keeps the record but takes the application out of the queue.
     * Nothing is deleted.
     */
    public function archive(Farmer $farmer)
    {
        $farmer->user->update(['status' => 'inactive']);

        $this->log('Archived farmer application: ' . $farmer->full_name, $farmer->id);

        return back()->with('status', 'Application archived.');
    }

    /**
     * Bring an archived farmer back. Used from the MAO Archive page.
     *
     * Deliberately not the same as approve(): approve() is for a brand new
     * application and also resets activity_status and last_activity_date,
     * which would be wrong here. Restoring only undoes the archive.
     */
    public function restore(Farmer $farmer)
    {
        if ($farmer->is_deleted) {
            return back()->withErrors([
                'farmer' => 'This farmer was permanently deleted and can no longer be restored.',
            ]);
        }

        $farmer->user->update(['status' => 'active']);

        $this->log('Restored farmer: ' . $farmer->full_name, $farmer->id);

        return back()->with('status', 'Farmer restored.');
    }

    /**
     * Take the farmer out of the working system for good. Not a database
     * delete: markDeleted() only stamps deleted_at/deleted_by, so every field
     * on the farmer's profile - and the login account it belongs to - is kept
     * exactly as it was. It just stops appearing anywhere in the ordinary
     * system (the Membership Applications queue, the Farmer Directory, the
     * Archive page's Archived tab) and moves to the Archive page's Deleted
     * tab instead, read-only, together with who deleted it and when.
     */
    public function destroy(Farmer $farmer)
    {
        $farmer->markDeleted(Auth::id());

        $this->log('Deleted farmer: ' . $farmer->full_name, $farmer->id);

        return back()->with('status', 'Farmer deleted. Their information is kept for audit purposes.');
    }

    private function log(string $action, ?int $farmerId): void
    {
        AuditLog::create([
            'user_id'      => Auth::id(),
            'action'       => $action,
            'target_table' => 'farmers',
            'target_id'    => $farmerId,
            'created_at'   => now(),
        ]);
    }
}
