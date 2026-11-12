'use client';

import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import {
  CLASSIFICATION_CATEGORIES,
  fetchHistory,
  type ClassificationCategory,
} from '@/lib/api';

export default function HistoryPage() {
  const [category, setCategory] = useState<ClassificationCategory | ''>('');
  const historyQuery = useQuery({
    queryKey: ['history', category],
    queryFn: () => fetchHistory(category || undefined),
  });

  const items = historyQuery.data?.data ?? [];
  const total = historyQuery.data?.meta.total ?? 0;

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-xl font-semibold">Classification history</h2>
        <p className="mt-1 text-sm text-slate-600">
          Every classification is recorded. Filter by category to see what was classified and when.
        </p>
      </div>

      <label className="flex max-w-sm flex-col gap-1 text-sm">
        <span className="font-medium text-slate-700">Filter by category</span>
        <select
          className="rounded border border-slate-300 px-3 py-2"
          value={category}
          onChange={(e) => setCategory(e.target.value as ClassificationCategory | '')}
        >
          <option value="">All categories</option>
          {CLASSIFICATION_CATEGORIES.map((value) => (
            <option key={value} value={value}>
              {value}
            </option>
          ))}
        </select>
      </label>

      {historyQuery.isLoading ? (
        <p className="text-sm text-slate-600">Loading...</p>
      ) : historyQuery.isError ? (
        <p className="text-sm text-red-700">Failed to load history.</p>
      ) : items.length === 0 ? (
        <p className="text-sm text-slate-600">No classifications yet.</p>
      ) : (
        <div className="space-y-2">
          <p className="text-sm text-slate-600">
            Showing {items.length} of {total} classifications
          </p>
          <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-slate-200 text-slate-500">
                <tr>
                  <th className="px-4 py-2 font-medium">Category</th>
                  <th className="px-4 py-2 font-medium">Confidence</th>
                  <th className="px-4 py-2 font-medium">Message</th>
                  <th className="px-4 py-2 font-medium">Source</th>
                  <th className="px-4 py-2 font-medium">When</th>
                </tr>
              </thead>
              <tbody>
                {items.map((item) => (
                  <tr key={item.id} className="border-b border-slate-100 last:border-0">
                    <td className="px-4 py-2">{item.category}</td>
                    <td className="px-4 py-2">{item.confidence.toFixed(2)}</td>
                    <td className="px-4 py-2 text-slate-600">
                      {item.message ?? '(no message)'}
                    </td>
                    <td className="px-4 py-2 text-slate-600">{item.source}</td>
                    <td className="px-4 py-2 text-slate-600">
                      {new Date(item.createdAt).toLocaleString()}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
}
