<?php

namespace App\Http\Controllers\Association\Concerns;

use App\Models\Association;
use App\Models\AssociationOfficer;
use Illuminate\Support\Facades\Auth;

/**
 * Every Association controller needs the same two things, so they live here
 * instead of being copied into five files.
 *
 * The rule this trait enforces is the whole security model for this role:
 * an officer only ever sees their OWN association's members and records
 * (proposal section 61). Hiding a link is not enough. Every list is filtered
 * through the association returned here, and every page that opens a single
 * record calls assertBelongsToAssociation() before showing anything.
 */
trait ResolvesAssociation
{
    /**
     * The association this signed in officer belongs to.
     *
     * Cached for the request because several methods call it and there is no
     * reason to hit the database each time.
     */
    protected function association(): Association
    {
        static $association;

        if ($association) {
            return $association;
        }

        $officer = AssociationOfficer::with('association')
            ->where('user_id', Auth::id())
            ->first();

        // An account with the association role but no officer row is a setup
        // mistake by the office, not a bad request. Say so plainly rather
        // than throwing a 404 that looks like a broken link.
        abort_if(
            $officer === null || $officer->association === null,
            403,
            'Your account is not linked to a Farmers\' Association yet. Please ask the Municipal Agriculture Office to finish setting it up.'
        );

        return $association = $officer->association;
    }

    /**
     * Stop an officer opening a record that is not theirs.
     *
     * Called with the association_id of whatever is being opened: a farmer,
     * an allocation, a report's farmer. Typing another id into the URL gets
     * a 403, not somebody else's data.
     */
    protected function assertBelongsToAssociation(?int $associationId): void
    {
        abort_unless($associationId === $this->association()->id, 403);
    }
}
