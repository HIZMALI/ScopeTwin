import { Button } from '@/components/ui/button';
import { Check, Copy, KeyRound } from 'lucide-react';
import { useState } from 'react';

export default function OneTimeKey({ token, dismiss }: { token: string; dismiss: () => void }) {
    const [copied, setCopied] = useState(false);
    const [error, setError] = useState('');
    return (
        <section className="scope-secret" aria-label="One-time API key">
            <div>
                <KeyRound size={19} />
                <strong>Save your API key now</strong>
            </div>
            <p>This key is shown once. Store it securely; it cannot be retrieved after leaving this page.</p>
            <code data-testid="issued-token">{token}</code>
            <div>
                <Button
                    size="sm"
                    onClick={async () => {
                        try {
                            await navigator.clipboard.writeText(token);
                            setCopied(true);
                        } catch {
                            setError('Clipboard unavailable. Select and copy the key manually.');
                        }
                    }}
                >
                    {copied ? <Check size={15} /> : <Copy size={15} />}
                    {copied ? 'Copied' : 'Copy key'}
                </Button>
                <Button variant="outline" size="sm" onClick={dismiss}>
                    I saved the key
                </Button>
            </div>
            {error && <p role="alert">{error}</p>}
        </section>
    );
}
