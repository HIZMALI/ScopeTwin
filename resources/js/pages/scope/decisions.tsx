import { DecisionRecord, DecisionsTable, Pagination, ScopeLayout } from '@/components/scope';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { router } from '@inertiajs/react';
import { useState } from 'react';

export default function Decisions({
    decisions,
    filters,
}: {
    decisions: {
        data: DecisionRecord[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { action?: string; decision?: string };
}) {
    const [action, setAction] = useState(filters.action ?? '');
    const [decision, setDecision] = useState(filters.decision ?? '');
    return (
        <ScopeLayout title="Decisions" subtitle="Inspect the policy, reason, and normalized input behind every result.">
            <section className="scope-panel">
                <form
                    className="scope-filters"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get('/decisions', { ...(action ? { action } : {}), ...(decision ? { decision } : {}) });
                    }}
                >
                    <div>
                        <Label htmlFor="filter-action">Action name</Label>
                        <Input
                            id="filter-action"
                            value={action}
                            maxLength={128}
                            placeholder="All actions"
                            onChange={(e) => setAction(e.target.value)}
                        />
                    </div>
                    <div>
                        <Label htmlFor="filter-decision">Decision</Label>
                        <select id="filter-decision" value={decision} onChange={(e) => setDecision(e.target.value)}>
                            <option value="">All decisions</option>
                            <option>ALLOW</option>
                            <option>REVIEW</option>
                            <option>DENY</option>
                        </select>
                    </div>
                    <Button variant="outline" type="submit">
                        Apply filters
                    </Button>
                    <span>{decisions.total} results</span>
                </form>
                <DecisionsTable rows={decisions.data} />
                <Pagination
                    current={decisions.current_page}
                    last={decisions.last_page}
                    prev={decisions.prev_page_url}
                    next={decisions.next_page_url}
                />
            </section>
        </ScopeLayout>
    );
}
