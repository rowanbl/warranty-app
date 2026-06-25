import { useAuth } from '../hooks/useAuth';
import { Logo } from './Logo';
import { LoginForm } from './LoginForm';
import { Home } from './Home';

export function App() {
    const { user, loading } = useAuth();

    return (
        <main class="screen">
            <div class="auth-shell">
                <Logo />
                {loading ? <p class="text-muted">Loading…</p> : user ? <Home /> : <LoginForm />}
            </div>
        </main>
    );
}
