<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\Association;
use App\Models\AssociationOfficer;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * MAO-only. Technicians and Association officers do NOT self-register -
 * the MAO creates their accounts here.
 */
class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->notDeleted()
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->role))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%' . $request->search . '%';

                $query->where(function ($sub) use ($term) {
                    $sub->where('username', 'like', $term)
                        ->orWhere('full_name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->with(['farmer', 'associationOfficer.association'])
            ->orderByRaw("FIELD(role, 'mao', 'technician', 'association', 'farmer')")
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        $summary = [
            'total'    => User::notDeleted()->count(),
            'active'   => User::notDeleted()->where('status', 'active')->count(),
            'pending'  => User::notDeleted()->where('status', 'pending')->count(),
            'inactive' => User::notDeleted()->where('status', 'inactive')->count(),
        ];

        return view('mao.users.index', compact('users', 'summary'));
    }

    public function createTechnician()
    {
        return view('mao.users.create-technician');
    }

    public function storeTechnician(Request $request)
    {
        $data = $request->validate([
            'full_name'    => ['required', 'string', 'max:255'],
            'username'     => ['required', 'string', 'max:100', 'unique:users,username'],
            'email'        => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone_number' => ['required', 'string', 'max:20'],
            'password'     => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user = User::create([
            'username'     => $data['username'],
            'full_name'    => $data['full_name'],
            'email'        => $data['email'],
            'password'     => $data['password'],
            'phone_number' => $data['phone_number'],
            'role'         => 'technician',
            'status'       => 'active', // MAO-created accounts are active immediately
        ]);

        $this->log('Created technician account: ' . $data['full_name'], $user->id);

        return redirect()->route('mao.users.index')
            ->with('status', 'Technician account created successfully.');
    }

    public function createAssociationOfficer()
    {
        return view('mao.users.create-association-officer', [
            // A new officer account should not be attached to an archived association
            'associations' => Association::active()->orderBy('name')->get(),
        ]);
    }

    public function storeAssociationOfficer(Request $request)
    {
        $data = $request->validate([
            'full_name'      => ['required', 'string', 'max:255'],
            'association_id' => ['required', 'exists:associations,id'],
            'username'       => ['required', 'string', 'max:100', 'unique:users,username'],
            'email'          => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone_number'   => ['required', 'string', 'max:20'],
            'password'       => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $created = DB::transaction(function () use ($data) {
            $user = User::create([
                'username'     => $data['username'],
                'full_name'    => $data['full_name'],
                'email'        => $data['email'],
                'password'     => $data['password'],
                'phone_number' => $data['phone_number'],
                'role'         => 'association',
                'status'       => 'active',
            ]);

            AssociationOfficer::create([
                'user_id'        => $user->id,
                'association_id' => $data['association_id'],
            ]);

            return $user;
        });

        $this->log('Created association officer account: ' . $data['full_name'], $created->id);

        return redirect()->route('mao.users.index')
            ->with('status', 'Association officer account created successfully.');
    }

    /**
     * Edit the account details MAO is allowed to change. Roles are never
     * switched here, and passwords are only replaced when a new one is typed.
     */
    public function edit(User $user)
    {
        abort_if($user->role === 'mao' && $user->id !== Auth::id(), 403);

        $user->load('associationOfficer');

        // Active associations to pick from, plus the officer's own
        // association even if it has since been archived, so the dropdown
        // still shows their current assignment instead of silently dropping it.
        $associations = Association::active()->orderBy('name')->get();
        $current      = $user->associationOfficer?->association;

        if ($current && $current->is_archived && ! $associations->contains('id', $current->id)) {
            $associations->push($current);
        }

        return view('mao.users.edit', [
            'user'         => $user,
            'associations' => $associations->sortBy('name')->values(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        abort_if($user->role === 'mao' && $user->id !== Auth::id(), 403);

        $data = $request->validate([
            'full_name'      => ['nullable', 'string', 'max:255'],
            'username'       => ['required', 'string', 'max:100', Rule::unique('users', 'username')->ignore($user->id)],
            'email'          => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone_number'   => ['required', 'string', 'max:20'],
            'password'       => ['nullable', 'confirmed', Password::min(8)->letters()->numbers()],
            'association_id' => [
                Rule::requiredIf($user->role === 'association'),
                'nullable',
                'exists:associations,id',
            ],
        ]);

        DB::transaction(function () use ($data, $user) {
            $user->update([
                'full_name'    => $data['full_name'] ?: null,
                'username'     => $data['username'],
                'email'        => $data['email'],
                'phone_number' => $data['phone_number'],
            ]);

            if (! empty($data['password'])) {
                $user->update(['password' => $data['password']]);
            }

            if ($user->role === 'association' && ! empty($data['association_id'])) {
                AssociationOfficer::updateOrCreate(
                    ['user_id' => $user->id],
                    ['association_id' => $data['association_id']]
                );
            }
        });

        $this->log('Updated user account: ' . $user->username, $user->id);

        return redirect()->route('mao.users.index')
            ->with('status', 'User account updated successfully.');
    }

    /**
     * Archive / restore instead of deleting - historical records are preserved.
     */
    public function updateStatus(Request $request, User $user)
    {
        $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        if ($user->id === Auth::id()) {
            return back()->withErrors(['status' => 'You cannot change your own account status.']);
        }

        if ($user->role === 'mao') {
            return back()->withErrors(['status' => 'MAO accounts cannot be archived here.']);
        }

        if ($user->is_deleted) {
            return back()->withErrors(['status' => 'This account was permanently deleted and can no longer be restored.']);
        }

        $user->update(['status' => $request->status]);

        $this->log('Set user status to ' . $request->status . ': ' . $user->username, $user->id);

        return back()->with('status',
            $request->status === 'inactive' ? 'User archived.' : 'User restored.');
    }

    /**
     * Take a technician or association officer account out of the working
     * system for good. Not a database delete: markDeleted() only stamps
     * deleted_at/deleted_by, so the account and everything it ever did
     * (inspections, allocations, distributions, its own login history) is
     * kept exactly as it was. MAO accounts and the caller's own account are
     * never deletable here.
     */
    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        if ($user->role === 'mao') {
            return back()->withErrors(['user' => 'MAO accounts cannot be deleted here.']);
        }

        $name = $user->display_name;
        $user->markDeleted(Auth::id());

        $this->log('Deleted user account: ' . $name, $user->id);

        return back()->with('status', 'User account deleted. Its information is kept for audit purposes.');
    }

    private function log(string $action, ?int $targetId): void
    {
        AuditLog::create([
            'user_id'      => Auth::id(),
            'action'       => $action,
            'target_table' => 'users',
            'target_id'    => $targetId,
            'created_at'   => now(),
        ]);
    }
}
