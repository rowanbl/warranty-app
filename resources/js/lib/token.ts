// Where we keep the bearer token between visits. localStorage means the user
// stays signed in across reloads and restarts until they sign out.
const TOKEN_KEY = 'ww_token';

export function getToken(): string | null {
    return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token: string): void {
    localStorage.setItem(TOKEN_KEY, token);
}

export function clearToken(): void {
    localStorage.removeItem(TOKEN_KEY);
}
