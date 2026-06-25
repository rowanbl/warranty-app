import { useState } from 'preact/hooks';
import { ApiError } from '../lib/api';
import { useAuth } from '../hooks/useAuth';

export function LoginForm() {
    const { login } = useAuth();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);

    async function onSubmit(event: Event) {
        event.preventDefault();
        setError(null);
        setSubmitting(true);

        try {
            await login(email, password);
        } catch (err) {
            // 403 means the email isn't verified yet, 422 means wrong details.
            setError(err instanceof ApiError ? err.message : 'Something went wrong.');
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <form class="card auth-card" onSubmit={onSubmit}>
            <h1 class="subheading">Sign in</h1>

            <label class="field">
                <span class="field-label">Email</span>
                <input
                    class="field-input"
                    type="email"
                    value={email}
                    onInput={(e) => setEmail((e.target as HTMLInputElement).value)}
                    autocomplete="email"
                    required
                />
            </label>

            <label class="field">
                <span class="field-label">Password</span>
                <input
                    class="field-input"
                    type="password"
                    value={password}
                    onInput={(e) => setPassword((e.target as HTMLInputElement).value)}
                    autocomplete="current-password"
                    required
                />
            </label>

            {error && <p class="field-error">{error}</p>}

            <button class="btn btn-block" type="submit" disabled={submitting}>
                {submitting ? 'Signing in' : 'Sign in'}
            </button>
        </form>
    );
}
