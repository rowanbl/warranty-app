import { getToken } from './token';

// Thrown for any non-2xx response. Carries the field errors Laravel sends back
// on a 422 so a form can show them inline.
export class ApiError extends Error {
    constructor(
        public status: number,
        message: string,
        public errors: Record<string, string[]> = {},
    ) {
        super(message);
    }
}

type Options = {
    method?: string;
    body?: unknown;
};

// One place that talks to the API. Adds the bearer token when we have one and
// turns failures into an ApiError.
export async function api<T>(path: string, options: Options = {}): Promise<T> {
    const headers: Record<string, string> = { Accept: 'application/json' };

    const token = getToken();
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }
    if (options.body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(`/api${path}`, {
        method: options.method ?? 'GET',
        headers,
        body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
    });

    const data = response.status === 204 ? null : await response.json().catch(() => null);

    if (!response.ok) {
        throw new ApiError(response.status, data?.message ?? 'Something went wrong.', data?.errors ?? {});
    }

    return data as T;
}
