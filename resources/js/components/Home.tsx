import { useAuth } from '../hooks/useAuth';

export function Home() {
    const { user, logout } = useAuth();

    if (!user) {
        return null;
    }

    return (
        <div class="card auth-card">
            <h1 class="subheading">Welcome back, {user.name}</h1>
            <p class="text-muted-ink">
                Signed in as {user.email}
                <span class="chip">{user.account_type}</span>
            </p>

            <button class="btn btn-secondary-ink btn-block" type="button" onClick={() => logout()}>
                Sign out
            </button>
        </div>
    );
}
