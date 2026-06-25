import logoUrl from '../assets/ww-logo.svg';

// The Warranty Wise wordmark. This is the light version (white text, orange
// mark), so it's for the navy background, not on a white card.
export function Logo() {
    return <img class="logo" src={logoUrl} alt="Warranty Wise" />;
}
