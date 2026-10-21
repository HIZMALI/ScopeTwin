# Agent API

The API evaluates policy and reports documentary review state. It does not execute an external action. Use your own authenticated workspace to create an agent, save its one-time key and publish policies. Never include a raw key in logs, screenshots or Git.

## Evaluate an action

`POST /api/v1/agent/actions/evaluate`

Required headers: `Authorization: Bearer <agent-key>`, `Idempotency-Key: <opaque-key>`, `Content-Type: application/json` and `Accept: application/json`.

```json
{
    "action_name": "refund.issue",
    "amount_minor": 12000,
    "currency": "USD",
    "resource_type": "refund"
}
```

The response below illustrates a workspace with a matching published ALLOW rule. UUIDs are illustrative, not credentials.

```json
{
    "decision_id": "00000000-0000-4000-8000-000000000001",
    "decision": "ALLOW",
    "reason_code": "REFUND_WITHIN_LIMIT",
    "matched_policy_version_id": "00000000-0000-4000-8000-000000000002",
    "approval_required": false
}
```

ALLOW, REVIEW and DENY are successful policy evaluations and use HTTP 200. With no matching published rule the result is DENY with `DEFAULT_DENY`; draft policies have no effect. Policy precedence is `DENY > REVIEW > ALLOW`.

| Status | Meaning                                                   |
| ------ | --------------------------------------------------------- |
| 200    | New evaluation or identical idempotent retry              |
| 401    | Missing, invalid or revoked credential, or disabled agent |
| 409    | Idempotency key reused with different normalized input    |
| 422    | Invalid body or idempotency key                           |
| 429    | Ingress or agent rate limit exceeded                      |

Idempotency is scoped to organization and agent. An identical retry returns the original decision, even if policies changed, without another request, decision or approval row. Use a new key for a new operation. The agent evaluation limit is 60/minute; independent IP ingress protection also applies.

## Input contract

JSON bodies are bounded to 8 KiB. Organization and agent identity come from the bearer credential; tenant override fields are rejected.

| Field                | Contract                                                                                         |
| -------------------- | ------------------------------------------------------------------------------------------------ |
| `action_name`        | Required; at most 128 bytes; lowercase action identifier                                         |
| `amount_minor`       | Optional JSON integer, 0–100000000000; requires currency; no floating point                      |
| `currency`           | Optional uppercase code in the explicit supported list                                           |
| `destination_domain` | Optional DNS-like hostname, at most 253 bytes; normalized lowercase                              |
| `resource_type`      | Optional lowercase identifier, at most 64 bytes                                                  |
| `resource_label`     | Optional string up to 80 bytes; discarded before evaluation/storage to avoid retaining free text |

Unknown fields and invalid types produce predictable JSON validation errors. Exact identifier patterns and the 27 supported currencies are defined in [`ActionSchema`](../app/Domain/ActionSchema.php). A USD amount of `12000` means $120.00; currency display respects the supported currency's minor-unit precision.

## Approval lookup

`GET /api/v1/agent/decisions/{decision}/approval`, with the originating agent's bearer key and `Accept: application/json`, returns documentary status, review metadata and `execution_performed: false`. It does not require an idempotency key and cannot resolve a review or authorize execution. Foreign organization/agent decisions return 404. Legacy REVIEW decisions without a captured approval require reevaluation and are not silently backfilled.

Authenticated browser routes resolve reviews using session/CSRF and OWNER/ADMIN authorization. Reviews expire after 24 hours and become stale when the published policy context or originating credential changes. The original REVIEW decision remains immutable at the application boundary.

## Try it locally

Use the [README curl example](../README.md#api-usage) or [`scripts/demo-api.ps1`](../scripts/demo-api.ps1). With the sample published rules, USD refunds up to 50000 minor units are ALLOW, larger refunds are REVIEW, and unknown actions are DENY. These rules must be installed/published in your own workspace; they are not global defaults.
