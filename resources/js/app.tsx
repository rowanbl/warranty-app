import { render } from 'preact';
import '../css/app.css';
import { AuthProvider } from './hooks/useAuth';
import { App } from './components/App';

const root = document.getElementById('app');

if (root) {
    render(
        <AuthProvider>
            <App />
        </AuthProvider>,
        root,
    );
}
