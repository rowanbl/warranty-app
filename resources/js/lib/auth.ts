import { api } from './api';
import { clearToken, setToken } from './token';

export type AccountType = 'customer' | 'dealer' | 'garage' | 'admin';

export type User = {
    id: number;
    name: string;
    email: string;
    account_type: AccountType;
    email_verified: boolean;
    profile: Record<string, unknown> | null;
};

// Sign in with a password and keep the token so the session survives reloads.
export async function login(email: string, password: string): Promise<User> {
    const { token, user } = await api<{ token: string; user: User }>('/login', {
        method: 'POST',
        body: { email, password },
    });

    setToken(token);

    return user;
}

// Who the stored token belongs to. Used to restore a session on page load.
export async function fetchMe(): Promise<User> {
    const { user } = await api<{ user: User }>('/me');

    return user;
}

// Drop the token server-side and locally. Clears locally even if the request
// fails, so the user is always signed out on their device.
export async function logout(): Promise<void> {
    try {
        await api('/logout', { method: 'POST' });
    } finally {
        clearToken();
    }
}
