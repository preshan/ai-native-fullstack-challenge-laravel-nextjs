'use client';

import { useInfiniteQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useEffect, useRef, useState } from 'react';
import {
  classifyMessage,
  createRequest,
  fetchRequests,
  REQUEST_STATUSES,
  RequestStatus,
  updateRequestStatus,
} from '@/lib/api';

type Feedback = { kind: 'success' | 'error'; text: string };

export default function HomePage() {
  const queryClient = useQueryClient();
  const [draft, setDraft] = useState('');
  const [feedback, setFeedback] = useState<Feedback | null>(null);
  // useMutation only keeps the latest variables, so track every in-flight row ourselves.
  const [pendingStatuses, setPendingStatuses] = useState<Record<string, RequestStatus>>({});
  const [pendingClassifies, setPendingClassifies] = useState<Record<string, true>>({});

  const requestsQuery = useInfiniteQuery({
    queryKey: ['requests'],
    queryFn: ({ pageParam }) => fetchRequests(pageParam),
    initialPageParam: 1,
    getNextPageParam: ({ meta }) =>
      meta.current_page < meta.last_page ? meta.current_page + 1 : undefined,
  });

  const { hasNextPage, isFetching, isFetchingNextPage, fetchNextPage } = requestsQuery;
  const loadMoreRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const sentinel = loadMoreRef.current;
    // Gate on any fetch (including invalidate refreshes). fetchNextPage would otherwise
    // cancel that refresh by default and leave the table stale after a successful action.
    if (!sentinel || !hasNextPage || isFetching) {
      return;
    }
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          fetchNextPage({ cancelRefetch: false });
        }
      },
      { rootMargin: '200px' },
    );
    observer.observe(sentinel);
    return () => observer.disconnect();
  }, [hasNextPage, isFetching, fetchNextPage]);

  const createMutation = useMutation({
    mutationFn: (message: string) => createRequest(message),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['requests'] });
      setDraft('');
    },
  });

  // Returning the invalidation keeps the mutation pending until the table has refetched.
  const statusMutation = useMutation({
    mutationFn: ({ id, status }: { id: string; status: RequestStatus }) =>
      updateRequestStatus(id, status),
    onMutate: ({ id, status }) => {
      setFeedback(null);
      setPendingStatuses((current) => ({ ...current, [id]: status }));
    },
    onSuccess: (_data, { status }) => {
      setFeedback({ kind: 'success', text: `Status updated to ${status}.` });
      return queryClient.invalidateQueries({ queryKey: ['requests'] });
    },
    onError: (error) => setFeedback({ kind: 'error', text: error.message }),
    onSettled: (_data, _error, { id }) => {
      setPendingStatuses((current) => {
        const next = { ...current };
        delete next[id];
        return next;
      });
    },
  });

  // Classify can also move the request from open to in_progress, so refetch rather than patch the row.
  const classifyMutation = useMutation({
    mutationFn: ({ id, message }: { id: string; message: string }) =>
      classifyMessage(message, id),
    onMutate: ({ id }) => {
      setFeedback(null);
      setPendingClassifies((current) => ({ ...current, [id]: true }));
    },
    onSuccess: ({ category, confidence }) => {
      setFeedback({
        kind: 'success',
        text: `Classified as ${category} (${confidence.toFixed(2)}).`,
      });
      return queryClient.invalidateQueries({ queryKey: ['requests'] });
    },
    onError: (error) => setFeedback({ kind: 'error', text: error.message }),
    onSettled: (_data, _error, { id }) => {
      setPendingClassifies((current) => {
        const next = { ...current };
        delete next[id];
        return next;
      });
    },
  });

  const isUpdatingStatus = (id: string) => pendingStatuses[id] !== undefined;
  const isClassifying = (id: string) => pendingClassifies[id] !== undefined;

  if (requestsQuery.isLoading) {
    return <p className="text-slate-600">Loading requests…</p>;
  }

  if (requestsQuery.isError) {
    return (
      <p className="text-red-700">
        Could not load requests. Is the API running at{' '}
        {process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:3001'}?
      </p>
    );
  }

  const pages = requestsQuery.data?.pages ?? [];
  const requests = pages.flatMap((page) => page.data);
  const total = pages[0]?.meta.total ?? 0;

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-xl font-semibold">Open requests</h2>
        <p className="mt-1 text-sm text-slate-600">
          Update status or run classification. Seeded volume is intentional — watch API
          behaviour under load.
        </p>
      </div>

      <form
        className="flex flex-wrap items-center gap-2"
        onSubmit={(e) => {
          e.preventDefault();
          const message = draft.trim();
          if (message) {
            createMutation.mutate(message);
          }
        }}
      >
        <input
          className="min-w-0 flex-1 rounded border border-slate-300 px-3 py-2 text-sm"
          value={draft}
          onChange={(e) => setDraft(e.target.value)}
          placeholder="Log a new customer request…"
        />
        <button
          type="submit"
          disabled={createMutation.isPending}
          className="rounded bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50"
        >
          {createMutation.isPending ? 'Adding…' : 'Add request'}
        </button>
      </form>

      <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
        <p className="text-slate-600" data-testid="requests-count">
          Showing {requests.length} of {total} requests
        </p>
        <p
          role="status"
          aria-live="polite"
          data-testid="action-feedback"
          className={feedback?.kind === 'error' ? 'text-red-700' : 'text-emerald-700'}
        >
          {feedback?.text}
        </p>
      </div>

      <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <table className="min-w-full divide-y divide-slate-200 text-sm">
          <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
              <th className="px-4 py-3">Message</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3">Notes</th>
              <th className="px-4 py-3">Category</th>
              <th className="px-4 py-3">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {requests.map((row) => (
              <tr key={row.id}>
                <td className="max-w-md px-4 py-3">
                  <div className="font-medium text-slate-900">{row.message}</div>
                  {row.latestNotePreview ? (
                    <div className="mt-1 text-xs text-slate-500">
                      Latest note: {row.latestNotePreview}
                    </div>
                  ) : null}
                </td>
                <td className="px-4 py-3">
                  <select
                    className="rounded border border-slate-300 bg-white px-2 py-1 disabled:opacity-50"
                    value={pendingStatuses[row.id] ?? row.status}
                    disabled={isUpdatingStatus(row.id) || isClassifying(row.id)}
                    onChange={(e) =>
                      statusMutation.mutate({
                        id: row.id,
                        status: e.target.value as RequestStatus,
                      })
                    }
                  >
                    {REQUEST_STATUSES.map((status) => (
                      <option key={status} value={status}>
                        {status}
                      </option>
                    ))}
                  </select>
                </td>
                <td className="px-4 py-3 tabular-nums">{row.noteCount}</td>
                <td className="px-4 py-3">
                  {row.category ?? '—'}
                  {row.confidence != null ? (
                    <span className="ml-1 text-xs text-slate-500">
                      ({row.confidence.toFixed(2)})
                    </span>
                  ) : null}
                </td>
                <td className="px-4 py-3">
                  <button
                    type="button"
                    className="rounded bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-700 disabled:opacity-50"
                    disabled={isUpdatingStatus(row.id) || isClassifying(row.id)}
                    onClick={() =>
                      classifyMutation.mutate({ id: row.id, message: row.message })
                    }
                  >
                    {isClassifying(row.id) ? 'Classifying…' : 'Classify'}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div ref={loadMoreRef} className="text-center text-sm text-slate-500">
        {isFetchingNextPage
          ? 'Loading more…'
          : hasNextPage
            ? null
            : `All ${total} requests loaded`}
      </div>
    </div>
  );
}
