import { ActionAmount, OutcomeCard, ReplayResultRecord, ReplayRunRecord } from '@/components/replay';
import { ScopeLayout } from '@/components/scope';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';

export default function ReplayResult({ run, result: r }: { run: ReplayRunRecord; result: ReplayResultRecord }) {
    const match = r.candidate_outcome.match;
    return (
        <ScopeLayout
            title="Request comparison"
            subtitle={`${r.input_snapshot.action_name} · ${r.changed ? 'Decision changed' : 'Decision unchanged'} under the candidate`}
            action={
                <Button asChild variant="outline">
                    <Link href={`/replays/${run.id}`}>Back to report</Link>
                </Button>
            }
        >
            <div className="scope-three-col">
                <OutcomeCard title="Original recorded" detail="Historical decision, preserved exactly" outcome={r.original_outcome} />
                <OutcomeCard title="Baseline simulated" detail="Today's frozen published policy set" outcome={r.baseline_outcome} />
                <OutcomeCard title="Candidate simulated" detail="Frozen baseline with the selected draft" outcome={r.candidate_outcome} />
            </div>
            <section className="scope-panel scope-form-panel">
                <h2>Why this result?</h2>
                <p>
                    {r.changed
                        ? `The candidate changes the decision from ${r.baseline_effect} to ${r.candidate_effect}.`
                        : `The candidate keeps the baseline decision ${r.baseline_effect}.`}{' '}
                    {match
                        ? `It matched a ${match.source === 'DRAFT' ? 'frozen unpublished draft' : 'published'} rule with reason ${r.candidate_outcome.reason_code}.`
                        : 'No rule matched, so DEFAULT_DENY applied.'}{' '}
                    {r.candidate_effect === 'DENY' && match
                        ? 'A matching DENY takes precedence over REVIEW and ALLOW.'
                        : r.candidate_effect === 'REVIEW'
                          ? 'A matching REVIEW takes precedence over ALLOW when no DENY matches.'
                          : ''}
                </p>
                <p>
                    {r.original_effect !== r.baseline_effect
                        ? `The historical ${r.original_effect} differs from baseline ${r.baseline_effect}; that difference predates this candidate comparison.`
                        : 'The recorded decision and baseline have the same effect.'}{' '}
                    No live decision or policy was changed by this replay.
                </p>
            </section>
            <div className="scope-two-col">
                <section className="scope-panel scope-form-panel">
                    <h2>Frozen normalized input</h2>
                    <p>
                        <ActionAmount action={r.input_snapshot} />
                    </p>
                    <pre>{JSON.stringify(r.input_snapshot, null, 2)}</pre>
                    <Link className="scope-text-link" href={`/decisions/${r.historical_policy_decision_id}`}>
                        Open original decision
                    </Link>
                </section>
                <section className="scope-panel scope-form-panel">
                    <h2>Matched candidate definition</h2>
                    <pre>
                        {JSON.stringify(
                            match?.source === 'DRAFT'
                                ? run.candidate_snapshot
                                : (run.baseline_manifest.find((v) => v.id === match?.policy_version_id) ?? { reason_code: 'DEFAULT_DENY' }),
                            null,
                            2,
                        )}
                    </pre>
                    <p>Draft references include a fingerprint and source draft ID. They are never presented as published version IDs.</p>
                </section>
            </div>
        </ScopeLayout>
    );
}
