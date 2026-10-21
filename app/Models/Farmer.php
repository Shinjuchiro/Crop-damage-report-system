<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletable;
use App\Support\MembershipApplication;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Farmer extends Model
{
    use SoftDeletable;

    /**
     * How many consecutive months with no qualifying activity make a
     * farmer Inactive.
     *
     * SEVEN, NOT THREE (Oct 2026)
     *
     * The proposal document's section 22 says three. The client and the
     * adviser agreed on seven, so seven is what the system does. A farming
     * calendar is the reason: three months of quiet is a normal gap between
     * a harvest and the next planting, so a three-month rule was marking
     * farmers Inactive for doing nothing unusual. Seven months covers that
     * gap and still catches somebody who has genuinely stopped farming.
     *
     * Kept as a constant so the number only exists in one place, and the
     * screens that explain the rule to a farmer or an officer read it from
     * here rather than spelling it out, so wording can never drift away
     * from behaviour again. If the office changes the rule, this line is
     * the only edit.
     *
     * What counts as an activity is important. Section 21 says logging in,
     * opening pages and browsing do NOT count, and it also says we must not
     * mark a farmer inactive just because they have no damage report,
     * because a farmer with no damage is doing well. So right now only a
     * crop planting record counts.
     */
    public const INACTIVITY_MONTHS = 7;

    protected $table = 'farmers';

    protected $fillable = [
        'user_id', 'association_id', 'barangay_id',
        'first_name', 'middle_name', 'last_name', 'date_of_birth', 'sex',
        'ownership_type', 'landowner_name', 'landowner_contact', 'landowner_location',
        'barangay_certificate_path', 'address', 'farm_size_hectares',
        'activity_status', 'last_activity_date', 'approval_welcome_shown_at',
    ];

    /* THE MEMBERSHIP WORKFLOW COLUMNS ARE NOT IN $fillable, ON PURPOSE
       (revision step 6, migration 2024_01_21_000001)

       membership_stage and the audit columns beside it decide who may act on
       an application next, so nothing arriving in a request may ever reach
       them, the same reasoning that keeps User::$role out of its own
       fillable list. The four controllers that move an application write
       them with forceFill() through the helpers below, which is a line you
       have to mean to write.

       Where each stage can go, and who may send it there, is in
       App\Support\MembershipApplication. */

    // Turns these columns into Carbon date objects automatically, so we can
    // call ->format() and ->diffInMonths() on them.
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'last_activity_date' => 'date',
            'deleted_at' => 'datetime',
            'approval_welcome_shown_at' => 'datetime',
            'association_review_started_at' => 'datetime',
            'forwarded_at' => 'datetime',
            'returned_at' => 'datetime',
            /* Added with the column (migration 2024_01_23_000001). Not
               optional: every screen that shows a decision calls
               ->format() on this, and without the cast it is a plain
               string and that is a fatal error, not a formatting quirk. */
            'decided_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function association()
    {
        return $this->belongsTo(Association::class);
    }

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public function mainCrops()
    {
        return $this->hasMany(FarmerMainCrop::class);
    }

    public function plantingRecords()
    {
        return $this->hasMany(CropPlantingRecord::class);
    }

    public function damageReports()
    {
        return $this->hasMany(DamageReport::class);
    }

    public function assistanceDistributions()
    {
        return $this->hasMany(AssistanceDistribution::class);
    }

    /*
     | THE MOST RECENT ONE OF EACH, FOR A LIST
     |
     | The association's Members list expands a row in place to show a
     | member at a glance, and the two facts that answer "what has this
     | member been doing" are their last planting record and their last
     | damage report. Loading all of either, for fifteen members at once,
     | to then throw away everything but the newest would be hundreds of
     | rows read to display thirty.
     |
     | ofMany() builds a subquery that picks one row per farmer, so these
     | eager-load in a single extra query each no matter how many members
     | are on the page.
     |
     | The constraint goes INSIDE the subquery, which is the whole reason
     | for the longer ofMany() form rather than latestOfMany(). With the
     | archived check written as an ordinary where() afterwards, the
     | subquery would still pick the archived record as the latest and the
     | outer where would then reject it, so the row would show nothing at
     | all rather than the most recent record that still counts.
     |
     | id as the tie-breaker because date_submitted is a date, not a
     | timestamp: two records filed on the same day are ordinary, and
     | without it MySQL is free to return either one.
     */
    public function latestPlantingRecord()
    {
        return $this->hasOne(CropPlantingRecord::class)->ofMany(
            ['date_submitted' => 'max', 'id' => 'max'],
            fn ($query) => $query->whereNull('archived_at')->whereNull('deleted_at')
        );
    }

    public function latestDamageReport()
    {
        return $this->hasOne(DamageReport::class)->ofMany(
            ['created_at' => 'max', 'id' => 'max'],
            fn ($query) => $query->whereNull('archived_at')->whereNull('deleted_at')
        );
    }

    // Lets us write $farmer->full_name anywhere instead of gluing the
    // three name columns together in every view.
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    /**
     * Where to link the barangay certificate, or null when there is none.
     *
     * An authorised route, not a path under /storage (revision section 3.4,
     * Oct 2026). This used to be written inline as
     * asset('storage/' . $farmer->barangay_certificate_path) in five views,
     * which handed the file to anybody with the link, signed in or not. The
     * URL is the same shape on every one of those screens, so it belongs
     * here: one accessor, and AttachmentController decides who may follow
     * it.
     *
     * Returns null rather than a dead link when the column is empty, so a
     * view can test this one value instead of testing the path and then
     * building a URL from it.
     */
    public function getBarangayCertificateUrlAttribute(): ?string
    {
        return $this->barangay_certificate_path
            ? route('attachments.certificate', $this)
            : null;
    }

    /* ==================================================================
     | The membership application (revision step 6)
     |
     | The four stage moves, each one a single method so that the columns
     | that decide who may act next are written in one place per move and
     | nowhere else. forceFill() because they are deliberately not
     | mass assignable; see the note above $fillable.
     |
     | None of these decide whether the move is allowed. That is
     | App\Support\MembershipApplication::isAssociationsTurn() and
     | isOfficesTurn(), checked by the controller before it gets here, so
     | the rule lives with the stages rather than being half here and half
     | there.
     ================================================================== */

    public function associationReviewer()
    {
        return $this->belongsTo(User::class, 'association_reviewed_by');
    }

    public function forwardedBy()
    {
        return $this->belongsTo(User::class, 'forwarded_by');
    }

    public function returnedBy()
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    /**
     * The MAO officer who approved or rejected this application.
     *
     * Null on every application decided before October 2026: the column was
     * added then and deliberately not backfilled, because who decided an old
     * application is not knowable and an invented audit entry is worse than
     * a missing one. See migration 2024_01_23_000001.
     */
    public function decidedBy()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** An officer opened the application. Records who, so the office can see. */
    public function startAssociationReview(int $officerId): void
    {
        $this->forceFill([
            'membership_stage'              => MembershipApplication::UNDER_REVIEW,
            'association_reviewed_by'       => $officerId,
            'association_review_started_at' => now(),
        ])->save();
    }

    /**
     * The association vouched for this member and sent it to the office.
     *
     * $note is the officer's verification remarks, and the office reads them
     * beside the application. Optional: an officer with nothing to add
     * should not be made to type something, because a note nobody needed to
     * write is a note that says "ok" and teaches the next reader to skip the
     * field. Null means no remarks, and the office's screen says so rather
     * than showing an empty box.
     */
    public function forwardToMao(int $officerId, ?string $note = null): void
    {
        $this->forceFill([
            'membership_stage' => MembershipApplication::FORWARDED,
            'forwarded_by'     => $officerId,
            'forwarded_at'     => now(),
            'forward_note'     => $note,
        ])->save();
    }

    /**
     * The office decided. Records who and when, and what they said.
     *
     * DOES NOT TOUCH users.status, AND THAT IS THE POINT
     *
     * Whether the account may be used is users.status, and it is the only
     * thing that decides approval (App\Support\MembershipApplication reads it
     * first, precisely so the two can never disagree). This method records
     * the decision; the caller flips the account in the same transaction.
     * Splitting it this way keeps one writer per fact instead of two methods
     * that both half-approve somebody.
     *
     * membership_stage is deliberately left alone, as everywhere else in this
     * workflow: it keeps saying 'forwarded_to_mao', which is the true record
     * of how the application reached its outcome.
     */
    public function recordDecision(int $byUserId, ?string $note = null): void
    {
        $this->forceFill([
            'decided_by'    => $byUserId,
            'decided_at'    => now(),
            'decision_note' => $note,
        ])->save();
    }

    /**
     * Sent back to the farmer with something to fix.
     *
     * $role is stored rather than read off the user, because a user's role
     * can change later and this has to keep saying who sent it back at the
     * time.
     */
    public function returnForCorrection(int $byUserId, string $role, string $note): void
    {
        $this->forceFill([
            'membership_stage' => MembershipApplication::RETURNED,
            'returned_by'      => $byUserId,
            'returned_by_role' => $role,
            'return_note'      => $note,
            'returned_at'      => now(),
        ])->save();
    }

    /**
     * The farmer corrected it and sent it back.
     *
     * Always to the association, whoever returned it, which is the decision
     * the office made: one path back in means an officer cannot be handed an
     * application whose corrections they never saw, and the office never
     * re-reads a version the association has not vouched for.
     *
     * The previous return note is deliberately kept. It is the only record
     * of what was wrong, the resubmission count beside it says how many
     * times this has happened, and an officer opening the application needs
     * both to judge whether the fix actually answers the question.
     */
    public function resubmitToAssociation(): void
    {
        $this->forceFill([
            'membership_stage'              => MembershipApplication::SUBMITTED,
            'resubmission_count'            => ($this->resubmission_count ?? 0) + 1,
            // The next officer to open it starts a fresh review.
            'association_reviewed_by'       => null,
            'association_review_started_at' => null,
        ])->save();
    }

    /* ==================================================================
     | Active / Inactive status (proposal sections 21 and 22)
     ================================================================== */

    /**
     * How many whole months since the last activity.
     *
     * We work this out from last_activity_date every time instead of saving
     * a "months_inactive" number in the database. If we saved it, it would
     * be wrong the next day unless something updated it, and section 22
     * says the status must be based on real dates and not on values set
     * by hand.
     */
    public function getMonthsInactiveAttribute(): int
    {
        return (int) $this->inactivity_measured_from->diffInMonths(Carbon::now());
    }

    /**
     * The date the inactivity clock is counted from.
     *
     * THE REGISTRATION-DATE FALLBACK (revision section 15, Oct 2026)
     *
     * Section 15: "If the farmer has never submitted a valid Crop Planted
     * Report, use the registration date as the starting point."
     *
     * last_activity_date is now the date of the farmer's most recent Crop
     * Planted Report and nothing else, so it is NULL for a farmer who has
     * never filed one (migration 2024_01_24_000001 made the column nullable
     * and rebuilt it from the real records). This is the one place that knows
     * what to do about that.
     *
     * WHAT THIS REPLACED, AND WHY IT WAS WRONG
     *
     * getMonthsInactiveAttribute() used to return 0 when the date was null,
     * which meant a farmer who had never filed anything was permanently
     * 0 months inactive and could never go Inactive at all - the exact case
     * the rule exists to catch. It was hidden for a long time because
     * RegisterController wrote a date at registration, so the null branch was
     * nearly unreachable; what the system actually did was measure from
     * whenever the office approved the account, which is not what section 15
     * says and is not a farmer activity at all.
     *
     * startOfDay() IS NOT COSMETIC
     *
     * last_activity_date is cast to 'date', so it is already midnight.
     * created_at is a full timestamp. Without normalising it, a farmer
     * registered at 14:30 would measure one fewer month than the SQL in
     * sweepInactive() computes for the same farmer (that uses
     * DATE(created_at)), and the two must agree to the month - the docblock
     * on sweepInactive() promises exactly that, and a farmer whose own
     * profile disagreed with the office's list would be a genuinely
     * confusing bug to chase. Covered by the harness.
     */
    public function getInactivityMeasuredFromAttribute(): Carbon
    {
        return $this->last_activity_date
            ?? Carbon::parse($this->created_at)->startOfDay();
    }

    /**
     * Has this farmer ever filed a Crop Planted Report?
     *
     * For the screens: "Last Activity" is a date when there is one and a
     * sentence when there is not, because showing a registration date under
     * that label is how the old column came to read as though every farmer
     * had done something.
     */
    public function getHasFiledPlantingReportAttribute(): bool
    {
        return $this->last_activity_date !== null;
    }

    /**
     * Call this after the farmer does something that counts.
     *
     * Section 22: the timer restarts and an Inactive farmer becomes
     * Active again. At the moment only PlantingController calls this.
     */
    public function recordQualifyingActivity(?Carbon $on = null): void
    {
        $this->forceFill([
            'last_activity_date' => ($on ?? Carbon::now())->toDateString(),
            'activity_status'    => 'active',
        ])->save();
    }

    /**
     * Flip an Active farmer to Inactive if they went past INACTIVITY_MONTHS.
     *
     * We call this when a page loads rather than running a scheduled job,
     * so there is nothing extra to set up on the server. The downside is
     * the status only updates when somebody looks at it, which is fine
     * here because the status is only ever read on a page anyway.
     *
     * Returns true if it actually changed something.
     */
    public function refreshActivityStatus(): bool
    {
        if ($this->activity_status !== 'active') {
            return false;
        }

        if ($this->months_inactive < self::INACTIVITY_MONTHS) {
            return false;
        }

        $this->forceFill(['activity_status' => 'inactive'])->save();

        return true;
    }

    /**
     * The office-wide version of refreshActivityStatus(): flips EVERY Active
     * farmer who has gone past INACTIVITY_MONTHS with no qualifying activity
     * to Inactive, in one query.
     *
     * refreshActivityStatus() only ever fixes the one farmer calling it, and
     * for a long time the only two places that called it were the farmer's
     * own Dashboard and Profile page - so a farmer who genuinely stopped
     * being active (the exact case this feature exists for) kept showing
     * Active everywhere else, forever, because nothing ever checked them
     * again unless they themselves logged in and looked. There is also no
     * scheduled job in this project that could have done it in the
     * background (see routes/console.php - only the SMS alert dispatcher is
     * scheduled), so the only fix that does not require a server cron job is
     * to sweep before any screen that displays, filters, or counts by
     * activity_status: MAO's Farmer Directory and Crop Planting Monitor,
     * the Association Dashboard and Member list, and the Monthly Report
     * (both MAO's and every association's).
     *
     * TIMESTAMPDIFF(MONTH, ...) mirrors getMonthsInactiveAttribute()'s own
     * Carbon::diffInMonths() exactly, so a farmer's status here can never
     * disagree with what their own profile page would compute for them.
     *
     * BOTH DIRECTIONS, SINCE THE LIMIT CHANGED (Oct 2026)
     *
     * This used to sweep one way only, because the reverse happens by
     * itself: recordQualifyingActivity() sets a farmer Active the moment
     * they file a planting record. That held while INACTIVITY_MONTHS never
     * moved. The day it went from 3 to 7, every farmer already sitting at
     * Inactive on 3, 4, 5 or 6 months of quiet was wrong by the new rule,
     * and nothing would ever have corrected them: a one-way sweep cannot
     * walk a farmer back, so they would have shown Inactive until they
     * happened to plant again, and the Inactive counts on four screens
     * would have been reading the old rule.
     *
     * So the second statement puts back anybody whose last activity is now
     * inside the limit. It is a no-op on a settled database (an Inactive
     * farmer's last_activity_date does not move on its own), it costs one
     * indexed UPDATE, and it means the limit can be changed again later by
     * editing the constant and nothing else, which is what the constant is
     * there to promise.
     *
     * A FARMER WHO NEVER FILED ANYTHING IS NOW SWEPT TOO (revision section 15)
     *
     * Both statements used to carry whereNotNull('last_activity_date'), on
     * the reasoning that a farmer with no date has nothing to measure from.
     * Section 15 disagrees and says what to measure from instead: "If the
     * farmer has never submitted a valid Crop Planted Report, use the
     * registration date as the starting point."
     *
     * So the measuring point is COALESCE(last_activity_date, DATE(created_at))
     * and the whereNotNull is gone. This is the half of the rule that was
     * missing: a farmer who registered, was approved, and never filed a
     * planting report could not go Inactive by any path, which is precisely
     * the farmer the rule is about.
     *
     * DATE(created_at) rather than created_at, to match
     * getInactivityMeasuredFromAttribute()'s startOfDay(). created_at carries
     * a time; TIMESTAMPDIFF would otherwise count one fewer month for anybody
     * who registered after midnight, and this method's whole promise is that
     * it cannot disagree with what a farmer's own profile page computes.
     * Covered by the harness.
     *
     * Returns how many farmers were flipped, in either direction.
     */
    public static function sweepInactive(): int
    {
        $measuredFrom = 'COALESCE(last_activity_date, DATE(created_at))';

        $gone = static::query()
            ->notDeleted()
            ->where('activity_status', 'active')
            ->whereRaw(
                "TIMESTAMPDIFF(MONTH, {$measuredFrom}, ?) >= ?",
                [now()->toDateString(), self::INACTIVITY_MONTHS]
            )
            ->update(['activity_status' => 'inactive']);

        $back = static::query()
            ->notDeleted()
            ->where('activity_status', 'inactive')
            ->whereRaw(
                "TIMESTAMPDIFF(MONTH, {$measuredFrom}, ?) < ?",
                [now()->toDateString(), self::INACTIVITY_MONTHS]
            )
            ->update(['activity_status' => 'active']);

        return $gone + $back;
    }
}
