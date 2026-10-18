import { Button } from '@/components/ui/button';
import { useState } from 'react';

export type AdvisorState = { state: string; fallback: string };
type Answer = {
    status: string;
    cached?: boolean;
    cleanup_confirmed?: boolean;
    advice?: { explanation: string; questions: string[] } | null;
};

export function ReplayAdvisor({ runId, advisor, canManage }: { runId: string; advisor: AdvisorState; canManage: boolean }) {
    const [busy, setBusy] = useState(false);
    const [answer, setAnswer] = useState<Answer | null>(null);
    const ready = advisor.state === 'ready';
    const messages: Record<string, string> = {
        disabled: 'AI explanation is disabled. The deterministic replay remains available.',
        not_configured: 'AI explanation is not configured. The deterministic replay remains available.',
        quota_exhausted: 'The AI call quota has been reached. Try again after the quota window resets.',
        in_progress: 'An explanation is already being generated. Check again shortly; no duplicate call was started.',
        unavailable: 'AI explanation is unavailable. No policy or decision was changed.',
    };
    async function explain() {
        if (busy) return;
        setBusy(true);
        try {
            const response = await fetch(`/replays/${runId}/advice`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
                body: '{}',
            });
            const data: Answer = await response.json();
            setAnswer(data && typeof data.status === 'string' ? data : { status: 'unavailable' });
        } catch {
            setAnswer({ status: 'unavailable' });
        } finally {
            setBusy(false);
        }
    }
    return (
        <section className="scope-panel scope-form-panel" aria-labelledby="advisor-title">
            <h2 id="advisor-title">AI Replay Advisor</h2>
            <p>{advisor.fallback}</p>
            <p className="text-muted-foreground text-sm">
                Optional explanation of simulated aggregate counts. The advisor cannot change policies, approvals or decisions.
            </p>
            {canManage && ready && (
                <Button type="button" variant="outline" disabled={busy} onClick={explain}>
                    {busy ? 'Generating explanation…' : 'Explain with AI'}
                </Button>
            )}
            <div role="status" aria-live="polite" aria-busy={busy}>
                {!ready && <p>{messages[advisor.state] ?? messages.unavailable}</p>}
                {ready && !canManage && <p>Only workspace owners and admins can request an AI explanation.</p>}
                {answer?.status === 'completed' && answer.advice ? (
                    <div>
                        <p>
                            <strong>AI-generated explanation (Backboard), not a policy decision</strong>
                        </p>
                        <p>{answer.advice.explanation}</p>
                        {answer.advice.questions.length > 0 && (
                            <ul>
                                {answer.advice.questions.map((question, index) => (
                                    <li key={index}>{question}</li>
                                ))}
                            </ul>
                        )}
                        {answer.cached && <p className="text-muted-foreground text-sm">Cached explanation; no new provider call.</p>}
                        {answer.cleanup_confirmed === false && (
                            <p>Provider thread cleanup could not be confirmed. Contact your workspace operator.</p>
                        )}
                    </div>
                ) : (
                    answer && <p>{messages[answer.status] ?? messages.unavailable}</p>
                )}
            </div>
        </section>
    );
}
