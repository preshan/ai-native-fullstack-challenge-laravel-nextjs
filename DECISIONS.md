# Decisions

## Priorities and trade-offs

I followed the README order: list performance, UI freshness, CI, classification structure, then
classification history. Tasks 1 to 4 were the priority. Task 5 is the thin slice described below.

- **List performance.** The list was unbounded (1200 rows, about 374 KB) and ran one notes query
  per row (1201 queries). I fixed the queries with `withCount` and a latest-note subquery, and I
  paginated the response to 25 rows by default (`config/pagination.php`, max 100). The UI only
  ever showed 25 rows, so returning everything was wasted work. Row fields stayed the same.
- **UI freshness.** Status and classify mutations invalidate the `['requests']` infinite query on
  success and return that promise, so the row stays pending until the refetch lands. Classify also
  invalidates `['history']`, so the history page is not stuck on a 10s stale cache after a new
  classification. I chose invalidation over writing the cache by hand because classify can also
  move `open` to `in_progress`, and the classify response does not return the full row. Pending
  status and classify work is tracked per row id, because `useMutation` only keeps the latest call.
  Load-more waits while any fetch is running, and uses `cancelRefetch: false`, so scrolling cannot
  cancel a refresh after an action. A failed load-more keeps the rows already on screen and shows a
  retry button. Write helpers send `Accept: application/json` so Laravel returns 422 JSON on bad
  input instead of a redirect.
- **Classification structure.** Each concern has one home:
  - input validation in `ClassifyRequest`
  - keyword scoring in `KeywordClassifier`, behind the `Classifier` interface
  - short-message softening and the 0.55 threshold in `ClassificationService`
  - typed results via a `Category` enum and a `Classification` value object
  - a thin controller that returns a `ClassificationResource`

  Binding lives on `AppServiceProvider::$bindings`, so a later model-backed classifier can swap in
  without touching the controller.
- **Scope.** I did not rewrite `store` or `updateStatus` into Form Requests. They still use the
  starter checks. That was outside the task list and would have mixed concerns in the same
  commits.

Deliberate behaviour changes:

- `GET /requests` returns Laravel's paginated shape `{data, links, meta}` instead of a plain array
  of every row. Invalid `page` / `per_page` return 422. Covered by `tests/Feature/RequestListTest.php`.
- Invalid `POST /requests/classify` input returns 422 with Laravel's `{message, errors}` instead of
  200 `{"error": "..."}`. Success JSON is unchanged. Covered by `tests/Feature/ClassifyRequestTest.php`.
- Classifying writes a row to `classifications`, and `GET /requests/history` serves that log instead
  of the "not implemented yet" placeholder. Covered by `tests/Feature/ClassificationHistoryTest.php`.

## Classification history

Smallest useful release, which is what I shipped:

- **Store:** one append-only row per classify in a `classifications` table: `id`, nullable
  `customer_request_id`, a snapshot of the classified `message`, `category`, `confidence`,
  `source` (for example `keyword`), and `created_at`. Re-classifying adds a row instead of
  overwriting. The request row still holds the current status and category; the table is the log
  behind it. The message snapshot means history shows the text that was classified, even when it
  differs from the linked request or there is no request id.
- **When:** inside `ClassificationService::classify()`, in the same transaction as the request
  update. Every classify path goes through that method, so the write cannot be skipped. A classify
  with no `requestId` still records a row (null request link).
- **Filter:** `GET /requests/history?category=billing`, validated against the `Category` enum
  (unknown values return 422), newest first, same pagination limits and `{data, links, meta}` shape
  as the list. The `/history` page uses a category dropdown built from the same enum.
- **Provider boundary:** the `Classifier` interface owns "message in, `Classification` out" and a
  `source()` name. History storage sits in the service, outside the interface, so swapping the
  keyword classifier later does not change the table or the query. The `source` column records which
  provider wrote each row.

The app runs locally on the keyword classifier. No live model or API key is needed.

History still shows the first page only (25 rows). Load-more or next/previous on `/history` is a
follow-up if the log grows past one page.

## Verification and remaining work

Baseline before any change (same seed: 1200 requests, 4800 notes):

| Check | Result |
| --- | --- |
| `php artisan test` | 2 passed (3 assertions), unit tests only |
| `npm run typecheck` | exit 0 |
| `GET /requests` time | 0.639s, 0.520s, 0.526s |
| SQL queries per `GET /requests` | 1201 |

After the work, locally:

- `docker compose exec api php artisan test`: 39 passed (133 assertions)
- `docker compose exec api vendor/bin/pint --test`: clean
- `docker compose exec web npm run typecheck` and `npm run lint`: clean
- Browser: list scrolls and shows totals, status and classify update without a reload, `/history`
  lists and filters by category

CI on pull request 1 (private fork):

- First migrate/test fix path is documented in `AI_USAGE.md` checkpoint 2.
- Later runs also cover Pint and ESLint. Latest green run:
  https://github.com/preshan/ai-native-fullstack-challenge-laravel-nextjs/actions/runs/43270445582

Remaining / unfinished:

- Offset paging can duplicate or skip a row if another user inserts while you scroll. Cursor
  pagination (or dedupe by id) is the follow-up.
- Status and classify refetch every loaded page, so cost grows with scroll depth.
- History UI still has no next-page controls (API already paginates).
- No frontend test runner in the starter. Frontend changes are covered by typecheck, lint, and
  recorded browser checks.
- `store` / `updateStatus` still use the starter's ad hoc validation.
- Add-request still has no error message (starter behaviour). `/favicon.ico` 404 is harmless.
