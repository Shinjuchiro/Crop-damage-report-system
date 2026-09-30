# Undoing the restyle

Everything that changed the *look* of the system on 29 and 30 September is
listed here, with the commands to put any of it back. Nothing below touches
features, data or routes: the restyle was deliberately kept to shared styling
values plus four dashboard headers and the bottom bar.

## 1. Mark the point before the restyle (do this once)

Find the commit immediately before the first restyle commit:

```powershell
git log --oneline -15
```

The first restyle commit is the one whose message begins **"Restyle:"**. Tag
its parent, so the tag names the last commit of the old look:

```powershell
$before = git log --format=%H --grep="Restyle:" -n 1
git tag pre-restyle "$before^"
git push origin pre-restyle
```

Check it landed on the right commit:

```powershell
git show --stat pre-restyle
```

It should be the registration draft commit, not a styling one.

## 2. What the restyle actually changed

| File | What it holds |
| --- | --- |
| `resources/css/app.css` | Corner radius (10px → 20px on cards), softer and wider shadows, the fixed 16px root text size |
| `resources/views/components/ui/greeting.blade.php` | New. The pale green greeting banner |
| `resources/views/layouts/partials/mobile-nav.blade.php` | Floating bottom bar, active tab in a filled circle, the More tab and its sheet |
| `resources/views/farmer/dashboard.blade.php` | Banner, count tiles moved above the standing card, Record Planting button removed |
| `resources/views/technician/dashboard.blade.php` | Banner, old heading line hidden |
| `resources/views/association/dashboard.blade.php` | Banner, old heading line hidden |
| `resources/views/dashboards/mao.blade.php` | Banner, old heading line hidden |

The colours were never changed in the end: a cream page ground was tried and
taken back out the same day, so the palette in `app.css` is the original dark
agricultural green on light blue-grey.

## 3. Put back one piece

Restore a single file to how it was, leaving everything else alone:

```powershell
git checkout pre-restyle -- resources/views/layouts/partials/mobile-nav.blade.php
git commit -m "Revert the bottom bar to the flat four-tab version"
git push
```

Useful combinations:

**Old bottom bar only** (flat bar, four tabs, no More sheet):

```powershell
git checkout pre-restyle -- resources/views/layouts/partials/mobile-nav.blade.php
```

**Old dashboards only** (heading line back, no greeting banner):

```powershell
git checkout pre-restyle -- resources/views/farmer/dashboard.blade.php resources/views/technician/dashboard.blade.php resources/views/association/dashboard.blade.php resources/views/dashboards/mao.blade.php
git rm resources/views/components/ui/greeting.blade.php
```

**Old corner radius and shadows, keeping the fixed text size**: edit
`app.css` by hand rather than restoring it, and set `--radius` back to
`0.625rem`. The `--shadow-*` block and the `html { font-size: 16px }` rule can
stay; they are independent of each other.

## 4. Put all of it back

```powershell
git checkout pre-restyle -- resources/css/app.css resources/views/layouts/partials/mobile-nav.blade.php resources/views/farmer/dashboard.blade.php resources/views/technician/dashboard.blade.php resources/views/association/dashboard.blade.php resources/views/dashboards/mao.blade.php
git rm resources/views/components/ui/greeting.blade.php
git commit -m "Revert the restyle"
git push
```

This rewinds only those seven files. Everything committed after the restyle —
the registration step validation, the typed-answer draft, the farmer
onboarding slides, the availability endpoint — is untouched, because none of
it lives in these files.

## 5. If you would rather keep both and switch between them

```powershell
git branch old-look pre-restyle
git push origin old-look
```

`old-look` is then a full working copy of the system as it was before the
restyle, to check a screen against or to demo from. It does not move as `main`
moves, so it stays a faithful snapshot.

---

# The AniAgapay pass (second batch)

A later round took the look closer to the AniAgapay mockups. It has its own
mark, so the two batches can be undone separately.

## Mark the point before it

Run this **before committing that batch**, so the tag names the last commit of
the previous look:

```powershell
git tag before-aniagapay
git push origin before-aniagapay
```

## What that batch changed

| File | What it holds |
| --- | --- |
| `resources/css/app.css` | The light lime page ground (`--background: #f0f6ec`) in place of the blue-grey |
| `resources/views/components/ui/card.blade.php` | Card headings in bold sentence case instead of small capitals, and the rule under the header removed |
| `resources/views/components/ui/button.blade.php` | Every button is a pill (`rounded-full`) |
| `resources/views/layouts/partials/mobile-nav.blade.php` | The farmer's first Reports tab renamed History, with the clock icon |

## Put it back

All of it:

```powershell
git checkout before-aniagapay -- resources/css/app.css resources/views/components/ui/card.blade.php resources/views/components/ui/button.blade.php resources/views/layouts/partials/mobile-nav.blade.php
git commit -m "Revert the AniAgapay pass"
git push
```

One piece at a time, which is usually what you want:

```powershell
# rectangular buttons again
git checkout before-aniagapay -- resources/views/components/ui/button.blade.php

# small-capital card headings with the rule under them
git checkout before-aniagapay -- resources/views/components/ui/card.blade.php

# blue-grey page ground again
git checkout before-aniagapay -- resources/css/app.css
```

Careful with that last one: `app.css` also carries the corner radius, the
shadows and the fixed text size from the first batch, so restoring the whole
file takes those with it. To change only the ground, edit the single line
`--background: #f0f6ec;` by hand instead.

## Two tags, two batches

| Tag | Everything after it |
| --- | --- |
| `pre-restyle` | Rounder cards, soft shadows, fixed text size, greeting banners, floating nav with the More sheet |
| `before-aniagapay` | Lime ground, pill buttons, sentence-case card headings, the History tab rename |

Neither tag moves as `main` moves, so both stay faithful snapshots. To see one
in full rather than picking files out of it:

```powershell
git switch --detach before-aniagapay   # look around
git switch main                        # come back
```
