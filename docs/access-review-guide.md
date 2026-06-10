# Access Review Feature — Beginner's Guide

This document explains every decision made while building the Access Review feature into Snipe-IT.
It is written for a beginner CS student who knows basic PHP/OOP but has not worked with Laravel
or a production codebase before.

---

## 1. What is the feature and why does it exist?

Many companies assign software licenses to employees and never review them.
Someone leaves, changes roles, or no longer needs a tool — but the license seat stays
checked out to them and the company keeps paying for it.

Access Review solves this with a three-actor workflow:

1. **Admin** creates a *campaign* (a named review round), then *launches* it.
2. Launching takes a **snapshot** of every active license assignment and groups those
   assignments by the employee's direct manager.
3. Each **manager** logs in, sees their team's licenses, and marks each one
   Keep / Modify / Remove. When done, they submit.
4. The **admin** sees all managers' decisions, then *executes* the Remove decisions —
   which actually checks the license seat back in through Snipe-IT's existing checkout engine.

The entire chain is audit-safe: even if a license or user is deleted after the campaign
launches, the review record survives because the snapshot froze the relevant data.

---

## 2. Bird's-eye architecture

```
database (2 tables)
  access_review_campaigns   ← one row per review round
  access_review_items       ← one row per (user, license_seat) pair in a campaign

app/Models/
  AccessReviewCampaign.php  ← status helpers (isDraft / isActive / isClosed)
  AccessReviewItem.php      ← decision helpers (isReviewed / isCompleted / isExecuted)

app/Actions/AccessReview/
  SnapshotCampaignItemsAction.php   ← fills access_review_items at launch time

app/Http/Controllers/AccessReview/
  CampaignsController.php           ← admin CRUD + launch/close/results/execute
  ManagerReviewController.php       ← manager list / show / AJAX save / complete

app/Http/Controllers/Api/AccessReview/
  CampaignsController.php           ← JSON feed for the admin datatable

app/Http/Transformers/
  AccessReviewCampaignsTransformer.php  ← shapes the JSON + pre-renders action buttons

app/Presenters/
  AccessReviewCampaignPresenter.php     ← declares datatable columns

resources/views/access-review/
  campaigns/index.blade.php     ← admin list (bootstrap-table)
  campaigns/edit.blade.php      ← create/edit form
  campaigns/results.blade.php   ← admin results + execute buttons
  my-reviews/index.blade.php    ← manager list
  my-reviews/show.blade.php     ← manager review page (AJAX)

resources/lang/en-US/admin/access-review/general.php  ← all UI strings

routes/web.php   ← UI routes (with breadcrumbs)
routes/api.php   ← API routes for datatables
```

---

## 3. The database schema — start here

Before writing a single line of PHP, think about the data.

### access_review_campaigns

| column        | type              | meaning                                   |
|---------------|-------------------|-------------------------------------------|
| id            | bigint PK         |                                           |
| name          | string            | "Q2 2026 License Audit"                   |
| description   | text nullable     |                                           |
| status        | string(16)        | `draft` → `active` → `closed`             |
| launched_at   | timestamp null    | set when admin clicks Launch              |
| closed_at     | timestamp null    | set when admin clicks Close               |
| created_by    | integer           | FK to users.id                            |
| deleted_at    | timestamp null    | soft-delete (added in a later migration)  |
| timestamps    | created_at/updated_at |                                       |

Key insight: **status is a state machine with exactly three states**.
A campaign always starts as `draft` and can only move forward, never backwards.
This makes the logic simple: every action just checks "are we in the right state?"

### access_review_items

| column                  | type              | meaning                                         |
|-------------------------|-------------------|-------------------------------------------------|
| id                      | bigint PK         |                                                 |
| campaign_id             | integer           | which campaign this belongs to                  |
| user_id                 | integer           | employee who holds the license seat             |
| manager_id              | integer           | their manager at snapshot time (frozen)         |
| license_id              | integer           | which license (for reference)                   |
| license_seat_id         | integer           | the specific seat assignment                    |
| license_name_snapshot   | string            | license name **frozen at launch**               |
| cost_per_seat_snapshot  | decimal(20,2) null| cost/seats **frozen at launch**                 |
| manager_status          | string(16) null   | keep / modify / delete — null means undecided   |
| manager_comment         | text null         | optional note                                   |
| manager_completed_at    | timestamp null    | set when manager hits "Submit My Review"        |
| admin_executed_at       | timestamp null    | set when admin executes the decision            |
| admin_executed_by       | integer null      | which admin ran the execution                   |

**Why snapshot the license name and cost?**
Because a license could be renamed or deleted after the campaign launches.
Without the snapshot, the manager would see "Unknown License" in their review.
This is a general principle called *denormalization for audit integrity*.

**Why a UNIQUE index on (campaign_id, license_seat_id)?**
Because `SnapshotCampaignItemsAction` uses a bulk `INSERT` (not `insertOrIgnore`).
The unique index is a database-level guarantee that no bug can ever create
duplicate review rows for the same seat in the same campaign.

---

## 4. Migrations — the right way to do incremental changes

The migrations were written in **three separate files**, not one big one.
This is the correct professional approach:

1. `create_access_review_campaigns_table.php` — initial table
2. `create_access_review_items_table.php` — initial table
3. `add_soft_deletes_to_access_review_campaigns.php` — adds `deleted_at` column
4. `add_unique_index_to_access_review_items.php` — adds the unique constraint

**Why not edit the original migration?**
Once a migration has been run by anyone else (on staging, production, or another dev's machine),
editing it would break their ability to run `php artisan migrate`.
Always add new migrations for schema changes rather than editing existing ones.

---

## 5. Models — thin, status-aware

Both models are intentionally thin. They don't contain business logic.
They only have:

- `$fillable` — the columns Laravel is allowed to mass-assign via `create()` / `update()`
- `$casts` — tells Laravel to convert columns to the right PHP type (datetime, decimal, integer)
- Relationships (`belongsTo`, `hasMany`) — how to load related records
- Simple status methods (`isDraft()`, `isReviewed()`, etc.)

```php
// AccessReviewCampaign.php
public function isDraft(): bool
{
    return $this->status === self::STATUS_DRAFT;
}
```

These one-liners look trivial but they matter because:
- They give the condition a name (`isDraft()` vs `$campaign->status === 'draft'`)
- Refactoring the status string only touches one place
- You can use them in Blade: `@if($campaign->isDraft())`

**`withTrashed()` on relationships in AccessReviewItem:**
```php
public function user(): BelongsTo
{
    return $this->belongsTo(User::class, 'user_id')->withTrashed();
}
```
Snipe-IT uses "soft deletes" — deleted records stay in the database with a `deleted_at` timestamp.
By default Laravel hides soft-deleted records.
`withTrashed()` overrides that so a review item can still show the user's name even if they
were later deactivated.

---

## 6. The Action class — single-responsibility business logic

`SnapshotCampaignItemsAction` is the most technically interesting piece.

**What is an Action class?**
It is a plain PHP class with a single static `run()` method that does exactly one thing.
Laravel doesn't enforce this pattern — it is a convention borrowed from the "action pattern"
to keep controllers thin.
The rule is: if a controller method would contain more than ~5 lines of business logic,
extract it to an Action.

**What does the snapshot action do?**

```php
public static function run(AccessReviewCampaign $campaign): int
{
    // 1. One SQL query joins three tables and collects all active assignments
    //    whose user has a manager (users without managers are excluded).
    $rows = DB::table('license_seats')
        ->join('users', ...)
        ->join('licenses', ...)
        ->whereNull('license_seats.deleted_at')   // skip soft-deleted seats
        ->whereNull('users.deleted_at')           // skip soft-deleted users
        ->whereNull('licenses.deleted_at')        // skip soft-deleted licenses
        ->whereNotNull('users.manager_id')        // only managed users
        ->get();

    // 2. Transform each row into an insert array, computing cost_per_seat.
    ->map(function ($row) use ($campaign, $now) {
        $costPerSeat = $row->purchase_cost !== null && $totalSeats > 0
            ? round($row->purchase_cost / $totalSeats, 2)
            : null;
        // return array for bulk insert ...
    });

    // 3. Bulk insert in chunks of 500 to avoid hitting MySQL's max packet size.
    foreach (array_chunk($rows, 500) as $chunk) {
        AccessReviewItem::insert($chunk);
    }

    return count($rows);  // tells the caller how many items were created
}
```

**Why `DB::table()` (query builder) instead of Eloquent models?**
Eloquent would load each `LicenseSeat` as a PHP object, fire events, apply global scopes.
For a bulk read-then-insert that touches thousands of rows, raw query builder is faster
and uses less memory. Snipe-IT uses the same pattern in its reports.

**Why chunk the inserts at 500?**
MySQL has a maximum packet size for a single SQL statement.
If you try to `INSERT` 10,000 rows in one call, you might exceed it.
Chunking at 500 rows is a safe middle ground: big enough to be fast, small enough to be safe.

---

## 7. Controllers — the request→response pipeline

Laravel controllers handle incoming HTTP requests and return a response (a View or a Redirect
or JSON). Every controller method follows the same pattern:

```
1. Authorize — can this user do this?
2. Validate — is the request data well-formed?
3. Act — run the business logic
4. Respond — redirect with a flash message, or return a view, or return JSON
```

### CampaignsController (admin)

The admin controller grew across three commits:

**Commit 3 (CRUD):**
```
index()    — list campaigns with pagination
create()   — show blank form
store()    — validate + insert, redirect to index
edit()     — show form; redirect if not draft
update()   — validate + update; redirect if not draft
destroy()  — soft-delete; redirect if not draft
```

**Commit 4 (state machine):**
```
launch()   — draft→active inside a DB transaction
close()    — active→closed
```

**Commit 9 (results + execute):**
```
results()      — load all items grouped for display
executeItem()  — run one manager decision
```

**The `launch()` method shows a critical pattern — transaction wrapping:**
```php
public function launch(AccessReviewCampaign $campaign): RedirectResponse
{
    $this->authorize('admin');

    if (! $campaign->isDraft()) {
        return redirect()->route(...)->with('error', '...');
    }

    $count = DB::transaction(function () use ($campaign) {
        $count = SnapshotCampaignItemsAction::run($campaign);
        $campaign->update(['status' => 'active', 'launched_at' => now()]);
        return $count;
    });

    return redirect()->route(...)->with('success', "Launched with $count items");
}
```

**Why wrap in a transaction?**
If `SnapshotCampaignItemsAction::run()` succeeds but the `update()` throws (rare: disk full,
connection dropped), you would have snapshot rows with no active campaign.
The transaction ensures both succeed or both are rolled back — the DB is never in a
half-updated state.

### ManagerReviewController

The manager controller is simpler. It never calls `$this->authorize('admin')` — 
instead it uses **data-scoped authorization**: every query is filtered by `manager_id = auth()->id()`.
No manager can see another manager's items because the query simply never returns them.

```php
$items = AccessReviewItem::where('campaign_id', $campaign->id)
    ->where('manager_id', auth()->id())   // scope to current user
    ->get();

if ($items->isEmpty()) {
    abort(403);   // if somehow they hit a campaign they have no items in
}
```

The AJAX save endpoint (`saveItem`) shows three guard layers:
1. Ownership: `$item->manager_id !== auth()->id()` → 403
2. Campaign state: not active → 422 with JSON error
3. Already completed: completed review can't change → 422

---

## 8. The API controller and Transformer pattern

Snipe-IT lists data using **bootstrap-table**, a JavaScript library that calls a JSON API
to get rows rather than embedding them in the HTML. This means every list page needs two controllers:

| Controller | Returns | Used by |
|---|---|---|
| `AccessReview\CampaignsController` | HTML views | the browser navigating to a URL |
| `Api\AccessReview\CampaignsController` | JSON | bootstrap-table's AJAX call |

The API controller always passes its query results through a **Transformer**:

```php
// Api\AccessReview\CampaignsController
public function index(Request $request): array
{
    $campaigns = AccessReviewCampaign::withTrashed()...->paginate($request->limit);
    return (new AccessReviewCampaignsTransformer)->transformAccessReviewCampaigns($campaigns, $campaigns->total());
}
```

The transformer does two things:
1. Shapes each row into the exact JSON fields bootstrap-table expects
2. Renders the per-row action buttons as HTML strings

```php
// AccessReviewCampaignsTransformer
public function transformAccessReviewCampaign(AccessReviewCampaign $campaign): array
{
    $actions = '';
    if ($campaign->isDraft()) {
        $actions .= '<a href="'.route('access-review.campaigns.edit', $campaign->id).'">Edit</a>';
        $actions .= '<form ...><button>Launch</button></form>';
    }
    if ($campaign->isActive()) {
        $actions .= '<form ...><button>Close</button></form>';
    }
    ...
    return [
        'id'         => $campaign->id,
        'name'       => e($campaign->name),   // e() escapes HTML to prevent XSS
        'status'     => $campaign->status,
        'actions'    => $actions,
    ];
}
```

**Why render HTML in the transformer instead of a Blade template?**
Because bootstrap-table populates a cell with raw HTML returned by the JSON API.
It can't call a Blade template. This is the established pattern in Snipe-IT.

---

## 9. Routes — explicit beats magic

The initial attempt used `Route::resource()` which auto-generates 7 routes from one line.
That was replaced with explicit individual routes because the access review routes include
non-standard actions (`launch`, `close`, `results`, `executeItem`) that `resource()` does not cover,
and mixing resource routes with custom routes is confusing to read later.

```php
Route::group(['prefix' => 'access-review', 'as' => 'access-review.'], function () {
    // Admin campaign management
    Route::get('campaigns',                  [CampaignsController::class, 'index'])  ->name('campaigns.index');
    Route::get('campaigns/create',           [CampaignsController::class, 'create']) ->name('campaigns.create');
    Route::post('campaigns',                 [CampaignsController::class, 'store'])  ->name('campaigns.store');
    Route::get('campaigns/{campaign}/edit',  [CampaignsController::class, 'edit'])   ->name('campaigns.edit');
    Route::put('campaigns/{campaign}',       [CampaignsController::class, 'update']) ->name('campaigns.update');
    Route::delete('campaigns/{campaign}',    [CampaignsController::class, 'destroy'])->name('campaigns.destroy');
    Route::post('campaigns/{campaign}/launch',[CampaignsController::class, 'launch'])->name('campaigns.launch');
    Route::post('campaigns/{campaign}/close', [CampaignsController::class, 'close']) ->name('campaigns.close');
    Route::get('campaigns/{campaign}/results',[CampaignsController::class, 'results'])->name('campaigns.results');
    ...
});
```

**Named routes** (`->name(...)`) are important. They let you write `route('access-review.campaigns.index')`
in Blade and PHP. If the URL changes later, you only fix the route definition — not every
link and redirect in the codebase.

**Breadcrumbs** are defined inline using a closure on each route:
```php
Route::get('campaigns', [CampaignsController::class, 'index'])
    ->name('campaigns.index')
    ->breadcrumbs(fn (Trail $trail) =>
        $trail->parent('home')->push(trans('admin/access-review/general.campaigns'))
    );
```
`tabuna/breadcrumbs` looks up the current route name and renders the trail automatically.

---

## 10. The manager review Blade view — AJAX auto-save

The manager view is the most frontend-heavy part.
Instead of a form submit per decision, it uses JavaScript to save decisions automatically
when the manager clicks a button.

**The core JavaScript pattern (vanilla JS, no framework):**

```javascript
document.querySelectorAll('.decision-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var row       = this.closest('tr');
        var itemId    = row.dataset.itemId;
        var status    = this.dataset.status;
        var saveUrl   = '/access-review/campaigns/{id}/items/' + itemId;

        fetch(saveUrl, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            },
            body: JSON.stringify({ manager_status: status, manager_comment: commentValue }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // highlight the active button, update progress bar
            }
        });
    });
});
```

**Why AJAX instead of a regular form submit?**
A normal form submit reloads the whole page. With 30 licenses to review, 30 page reloads
would be unbearably slow. AJAX saves each decision in the background so the manager
never leaves the page.

**The CSRF token** (`X-CSRF-TOKEN`) is required by Laravel on all POST/PATCH/PUT/DELETE
requests. It prevents Cross-Site Request Forgery — another website tricking a logged-in user
into performing actions. The token is embedded in the page layout's `<meta>` tag.

---

## 11. Security hardening — what the security review found

After the manager UI was built, a security review was run.
The findings and fixes are a crash course in web security:

| Finding | Problem | Fix |
|---|---|---|
| H-4 (high) | JavaScript constructed the AJAX URL using the item's ID from a data attribute — if an attacker injected a different `data-item-id`, they could target another manager's item | Route the PATCH through `{campaign}/{item}` and check `$item->manager_id === auth()->id()` in the controller. Pre-compute the URL server-side in Blade using `route()` |
| M-3 (medium) | Two requests to save the same item at the same time could both pass the "not completed" check and both write | Wrap the save in a DB transaction with `lockForUpdate()` |
| M-5 (medium) | Datatable sort column was accepted from user input without validation, allowing SQL injection via `ORDER BY` | Whitelist allowed sort columns |
| M-6 (medium) | A test assertion was inverted — it was asserting the wrong thing and passing | Fix the assertion |
| M-7 (medium) | No `max` length on the description field — could store 1 MB strings | Add `'max:65535'` to validation |

**The key lesson from H-4:**
Never trust the browser to provide security-relevant IDs.
The client sends `POST /items/42` and the server should verify that item 42 belongs
to the current user — not trust that the browser only put in IDs the user owns.

**The key lesson from M-3 (optimistic locking):**
```php
DB::transaction(function () use ($item) {
    $lockedItem = AccessReviewItem::lockForUpdate()->find($item->id);
    if ($lockedItem->isExecuted()) {
        return;  // already done, bail out silently
    }
    // ... do the work
});
```
`lockForUpdate()` acquires a row-level write lock.
If two requests arrive simultaneously, one gets the lock and runs;
the other waits, then sees `isExecuted() = true` and returns.
Without this, you could check-in the same seat twice.

---

## 12. Tests — write them before you forget what "correct" means

Every feature had tests written in the same commit.
Tests live in `tests/Feature/AccessReview/`:

| Test file | What it covers |
|---|---|
| `AdminCampaignCrudTest.php` | Create, edit, delete; 403 for non-admins; redirect if not draft |
| `LaunchAndCloseCampaignTest.php` | State transitions; blocked transitions; snapshot integration |
| `ManagerReviewTest.php` | Data isolation; AJAX save; mark-complete flow |
| `AdminResultsTest.php` | Results page rendering; correct counts |
| `AdminExecuteActionsTest.php` | Delete executes checkin; idempotent re-execute |
| `SnapshotCampaignItemsActionTest.php` | Unit tests for the snapshot logic in isolation |

**The typical test structure:**
```php
public function test_non_admin_cannot_create_campaign(): void
{
    $user = User::factory()->create();  // regular user, no admin flag
    $this->actingAs($user)
         ->post(route('access-review.campaigns.store'), ['name' => 'Test'])
         ->assertForbidden();           // expect 403
}

public function test_admin_can_create_campaign(): void
{
    $admin = User::factory()->superuser()->create();
    $this->actingAs($admin)
         ->post(route('access-review.campaigns.store'), ['name' => 'Q2 Review'])
         ->assertRedirect(route('access-review.campaigns.index'));

    $this->assertDatabaseHas('access_review_campaigns', ['name' => 'Q2 Review']);
}
```

**What `actingAs()` does:** tells Laravel to treat this test request as if the given
user is logged in, without going through the real login flow.

**What `assertDatabaseHas()` does:** checks that a row with these values exists in the DB.
It is the most common way to verify that something was actually saved.

---

## 13. Translations — never hardcode English strings

Every user-facing string in this feature lives in:
```
resources/lang/en-US/admin/access-review/general.php
```

```php
return [
    'campaigns'   => 'Access Review Campaigns',
    'new_campaign'=> 'New Campaign',
    'created'     => 'Campaign created successfully.',
    'launched'    => 'Campaign launched. :count items created.',
    ...
];
```

In Blade: `{{ trans('admin/access-review/general.campaigns') }}`  
In PHP: `trans('admin/access-review/general.launched', ['count' => $count])`

The `:count` placeholder gets replaced with the actual number.
If you ever need to translate the app to French, you create
`resources/lang/fr/admin/access-review/general.php` and all strings update automatically.

---

## 14. Step-by-step order to recreate this feature

If you wanted to build something like this from scratch in Laravel, do it in this order.
Each step is testable before moving to the next.

### Step 1 — Design the data model
Draw the tables on paper. For each table decide:
- What columns are needed?
- What is nullable vs required?
- What foreign keys exist?
- What indexes are needed for the queries you plan to write?

### Step 2 — Write migrations
```bash
php artisan make:migration create_access_review_campaigns_table
php artisan make:migration create_access_review_items_table
php artisan migrate
```

### Step 3 — Write models and factories
```bash
php artisan make:model AccessReviewCampaign --factory
php artisan make:model AccessReviewItem --factory
```
Add `$fillable`, `$casts`, relationships, and status helper methods.
Write factory states so tests can create campaigns in any state easily.

### Step 4 — Write the Action class and unit tests
Create `app/Actions/AccessReview/SnapshotCampaignItemsAction.php`.
Write `tests/Feature/AccessReview/SnapshotCampaignItemsActionTest.php` with 9 cases.
Run: `php artisan test tests/Feature/AccessReview/SnapshotCampaignItemsActionTest.php`
Make them all pass before moving on.

### Step 5 — Admin CRUD (draft campaigns only)
Create the admin controller, the API controller, the transformer, and the presenter.
Add routes. Create the index and edit Blade views.
Write `AdminCampaignCrudTest.php`. Make them pass.

### Step 6 — Launch and close (state machine)
Add `launch()` and `close()` methods to the admin controller.
Wrap `launch()` in a transaction that calls the snapshot action.
Write `LaunchAndCloseCampaignTest.php`. Make them pass.

### Step 7 — Soft deletes and DB constraints
Add the soft-delete migration. Add the unique index migration.
Run migrations. Update the model to use `SoftDeletes`.
No new tests needed — the unique index is enforced by the DB layer.

### Step 8 — Manager review UI
Create `ManagerReviewController` with `index`, `show`, `saveItem`, `complete`.
Add manager routes. Write both Blade views (index and show).
Write `ManagerReviewTest.php`. Make them pass.

### Step 9 — Admin results and execute actions
Add `results()` and `executeItem()` to the admin controller.
Write the results Blade view.
Write `AdminResultsTest.php` and `AdminExecuteActionsTest.php`. Make them pass.

### Step 10 — Security review and hardening
Run (or simulate) a security review. Fix:
- AJAX URL construction (move to server-side `route()`)
- Optimistic locking on concurrent writes
- Input validation gaps
- Assertion correctness in tests

### Step 11 — Polish
Add breadcrumbs to all routes.
Add the sidebar link (gated by `@can('admin')`).
Run `npm run prod` to build minified assets.
Run the full test suite: `php artisan test`.

---

## 15. Key patterns to internalize

These patterns appear throughout Snipe-IT and throughout most Laravel applications.
Internalize them and you can navigate any part of the codebase.

| Pattern | Where used | Why |
|---|---|---|
| State machine with constants | `AccessReviewCampaign::STATUS_*` | Prevents typos; makes transitions traceable |
| Action class | `SnapshotCampaignItemsAction` | Keeps controllers thin; makes business logic testable in isolation |
| DB transaction | `launch()`, `executeItem()` | Ensures atomicity — partial writes never persist |
| Snapshot / denormalization | `license_name_snapshot`, `cost_per_seat_snapshot` | Audit integrity — data frozen at review time |
| `withTrashed()` on relationships | `AccessReviewItem` | Soft-deleted records stay visible in historical views |
| Data-scoped authorization | `ManagerReviewController` | Users can only see their own data without explicit permission checks |
| Named routes | everywhere | Decouples URLs from logic; renaming a route only changes one place |
| Transformer pattern | `AccessReviewCampaignsTransformer` | Keeps API JSON shape separate from model structure |
| CSRF token in AJAX | `show.blade.php` JS | Required by Laravel; prevents request forgery |
| `lockForUpdate()` in transactions | `executeItem()` | Prevents race conditions on concurrent requests |
| Translation keys | `resources/lang/...` | Every UI string is localizable from day one |

---

## 16. Reading the existing codebase — navigation tips

When you need to understand a feature you didn't write:

1. **Start with the route** — `routes/web.php` tells you what URLs exist and which controller method handles each one.
2. **Read the controller method** — it shows you what gets authorized, what gets validated, and what model operations happen.
3. **Read the model** — understand the relationships and any business methods.
4. **Read the Blade view** — understand what the user actually sees and what forms/AJAX calls they make.
5. **Read the tests** — the test file is the fastest way to understand edge cases and what the intended behavior is.

To find where a class is defined: `Ctrl+P` in VS Code, type the class name.  
To find all usages of a method: `Ctrl+Shift+F` and search the codebase.  
To trace a request: start from the route, follow to the controller, follow to the model.

---

*Built on top of [Snipe-IT](https://github.com/snipe/snipe-it), a Laravel-based IT asset management system.*
