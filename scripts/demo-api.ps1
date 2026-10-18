param([string]$BaseUrl = 'http://127.0.0.1:18731')
$ErrorActionPreference = 'Stop'
$secret = Read-Host 'Paste the one-time agent key' -AsSecureString
$credential = [System.Net.NetworkCredential]::new('', $secret)
$headers = @{ Authorization = "Bearer $($credential.Password)"; 'Idempotency-Key' = [guid]::NewGuid().ToString(); Accept = 'application/json' }
try {
    $body = @{ action_name = 'refund.issue'; amount_minor = 12000; currency = 'USD'; resource_type = 'refund' } | ConvertTo-Json
    Invoke-RestMethod -Uri "$BaseUrl/api/v1/agent/actions/evaluate" -Method Post -Headers $headers -ContentType 'application/json' -Body $body | ConvertTo-Json
} finally {
    $headers.Clear()
    $credential = $null
    $secret.Dispose()
}
