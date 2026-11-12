# AI usage

**Tools:** Cursor (agent mode) for diagnosis, implementation, tests, and browser checks. GitHub
CLI for the pull request and Actions runs on my private fork. The app itself uses the keyword
classifier only.

**How I worked:** for each task I gave the agent the goal and a few hard constraints (same seed,
preserve behaviour, prove claims with real commands). I reran the checks myself before accepting
anything, and I changed proposals when the first answer was incomplete.

| Checkpoint | What I delegated and the constraint I set | What AI proposed | My check and what it showed | My decision and why |
| --- | --- | --- | --- | --- |
| 1. List performance | Fix the slow `GET /requests` on the seeded data. Keep note counts and the latest note preview correct. Measure before and after on the same seed. | Load note counts with `withCount('notes')` and the latest note with a correlated subquery, then paginate 25 rows by default. On the web, use `useInfiniteQuery` and load the next page on scroll. | Before: about 0.52s to 0.64s and 1201 SQL queries per request. After: about 0.01s to 0.08s and 2 queries per page. A SHA-256 over every seeded row's `id`, `noteCount` and `latestNotePreview` matched before and after (`2a7795fe...84b0`). `RequestListTest` fails if the query count grows with rows. | **Accepted.** The approach was right, and the checksum plus the query-count test made it safe to keep. Commit `8e5dcb6` (API) and `6b74992` (web). |
| 2. CI | Diagnose the GitHub Actions failure on a clean checkout. Keep the existing checks. Link a real Actions run if possible. | First: stop Migrate from targeting `request_desk_app`, which the workflow never creates, and use `request_desk` instead (`dff087e`). | I reproduced the access-denied error locally. The first Actions run still failed: tests used `request_desk` because the job-level `DB_DATABASE` overrides `phpunit.xml` ([run 43241458898](https://github.com/preshan/ai-native-fullstack-challenge-laravel-nextjs/actions/runs/43241458898)). | **Changed.** The job now exports `request_desk_test`, and only Migrate sets `request_desk` (`7224eb4`). The next run passed end to end ([run 43241803622](https://github.com/preshan/ai-native-fullstack-challenge-laravel-nextjs/actions/runs/43241803622)). A local check with `DB_DATABASE=request_desk_test` confirmed the suite name. |

**Checks I reran myself**

```bash
# List timing and query count (before / after Task 1)
curl -s -o /dev/null -w '%{time_total}s\n' http://localhost:3001/requests
docker compose logs --since 10s api | grep -c 'query: '

# CI env override (a fake name should fail; without -e, tests pass)
docker compose exec -e DB_DATABASE=request_desk_test api php artisan test

# Current suite
docker compose exec api php artisan test
docker compose exec api vendor/bin/pint --test
docker compose exec web npm run typecheck
docker compose exec web npm run lint
```

Latest local result: `php artisan test` = 39 passed (133 assertions). Pint, typecheck and lint all
pass. Latest PR check: [Actions run 43270445582](https://github.com/preshan/ai-native-fullstack-challenge-laravel-nextjs/actions/runs/43270445582) (build-test SUCCESS).
