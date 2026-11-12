const API_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:3001';

export const REQUEST_STATUSES = ['open', 'in_progress', 'resolved'] as const;
export type RequestStatus = (typeof REQUEST_STATUSES)[number];

export type RequestListItem = {
  id: string;
  message: string;
  status: RequestStatus;
  category: string | null;
  confidence: number | null;
  noteCount: number;
  latestNotePreview: string | null;
  createdAt: string;
  updatedAt: string;
};

export type Paginated<T> = {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

export async function fetchRequests(page = 1): Promise<Paginated<RequestListItem>> {
  const res = await fetch(`${API_URL}/requests?page=${page}`, {
    headers: { Accept: 'application/json' },
  });
  if (!res.ok) {
    throw new Error(`Failed to load requests (${res.status})`);
  }
  return res.json();
}

export type CreatedRequest = {
  id: string;
  message: string;
  status: RequestStatus;
  createdAt: string;
  updatedAt: string;
};

export async function createRequest(message: string): Promise<CreatedRequest> {
  const res = await fetch(`${API_URL}/requests`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ message }),
  });
  if (!res.ok) {
    throw new Error(`Failed to create request (${res.status})`);
  }
  return res.json();
}

// PATCH returns the stored model, so keys are snake_case unlike the list rows.
export type UpdatedRequest = {
  id: string;
  message: string;
  status: RequestStatus;
  category: string | null;
  confidence: number | null;
  created_at: string;
  updated_at: string;
};

export async function updateRequestStatus(
  id: string,
  status: RequestStatus,
): Promise<UpdatedRequest> {
  const res = await fetch(`${API_URL}/requests/${id}/status`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ status }),
  });
  if (!res.ok) {
    throw new Error(`Failed to update status (${res.status})`);
  }
  return res.json();
}

export type ClassificationResult = {
  category: string;
  confidence: number;
  requestId: string | null;
};

export async function classifyMessage(
  message: string,
  requestId?: string,
): Promise<ClassificationResult> {
  const res = await fetch(`${API_URL}/requests/classify`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ message, requestId }),
  });
  if (!res.ok) {
    throw new Error(`Failed to classify (${res.status})`);
  }
  return res.json();
}

export const CLASSIFICATION_CATEGORIES = ['billing', 'sales', 'support', 'unknown'] as const;
export type ClassificationCategory = (typeof CLASSIFICATION_CATEGORIES)[number];

export type ClassificationHistoryItem = {
  id: string;
  requestId: string | null;
  requestMessage: string | null;
  category: ClassificationCategory;
  confidence: number;
  source: string;
  createdAt: string;
};

export async function fetchHistory(
  category?: ClassificationCategory,
): Promise<Paginated<ClassificationHistoryItem>> {
  const qs = category ? `?category=${encodeURIComponent(category)}` : '';
  const res = await fetch(`${API_URL}/requests/history${qs}`, {
    headers: { Accept: 'application/json' },
  });
  if (!res.ok) {
    throw new Error(`Failed to load history (${res.status})`);
  }
  return res.json();
}
