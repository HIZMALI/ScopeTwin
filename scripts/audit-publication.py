"""Scan tracked source and reachable Git history without displaying secret values."""
import json
import pathlib
import re
import subprocess

root = pathlib.Path(__file__).resolve().parent.parent
out = root / 'artifacts' / 'submission-20261020'
out.mkdir(parents=True, exist_ok=True)
names = {'APP_KEY', 'BACKBOARD_API_KEY', 'BACKBOARD_ASSISTANT_ID', 'DB_PASSWORD', 'RELEASE_DB_OWNER_PASSWORD', 'MAIL_PASSWORD'}
literals = set()
for relative in ['.env', 'storage/.env.rehearsal', 'storage/.env.build035-rehearsal']:
    for line in (root / relative).read_text(encoding='utf-8-sig').splitlines():
        key, separator, value = line.partition('=')
        value = value.strip().strip('"').strip("'")
        if separator and key in names and len(value) >= 12:
            literals.add(value.encode())
patterns = [re.compile(rb'APP_KEY=base64:[A-Za-z0-9+/=]{40,}'), re.compile(rb'st_[a-f0-9]{16}_[a-f0-9]{64}'), re.compile(rb'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----'), re.compile(rb'AKIA[0-9A-Z]{16}')]
findings = []

def inspect(data, identity):
    if b'\x00' in data:
        return
    if any(secret in data for secret in literals):
        findings.append({'location': identity, 'class': 'CURRENT_PRIVATE_LITERAL'})
    elif any(pattern.search(data) for pattern in patterns):
        findings.append({'location': identity, 'class': 'CREDENTIAL_PATTERN'})

paths = subprocess.check_output(['git', 'ls-files', '--cached', '--others', '--exclude-standard', '-z'], cwd=root).decode().split('\0')
source_count = 0
for name in filter(None, paths):
    path = root / name
    if path.is_file():
        inspect(path.read_bytes(), f'source:{name}')
        source_count += 1

objects = subprocess.check_output(['git', 'rev-list', '--objects', '--all'], cwd=root).decode().splitlines()
reader = subprocess.Popen(['git', 'cat-file', '--batch'], cwd=root, stdin=subprocess.PIPE, stdout=subprocess.PIPE)
blob_count = 0
for record in objects:
    oid = record.split(' ', 1)[0]
    reader.stdin.write((oid+'\n').encode())
    reader.stdin.flush()
    header = reader.stdout.readline().decode().split()
    size = int(header[2])
    data = reader.stdout.read(size)
    assert reader.stdout.read(1) == b'\n'
    if header[1] == 'blob':
        inspect(data, f'history-object:{oid}')
        blob_count += 1
reader.stdin.close()
assert reader.wait() == 0
report = {'source_paths': source_count, 'reachable_history_blobs': blob_count, 'private_literal_and_pattern_findings': findings, 'scope': 'Current private literals and configured credential patterns; not an independent security audit.'}
(out / 'publication-secret-audit.json').write_text(json.dumps(report, indent=2))
print(json.dumps({'source_paths': source_count, 'history_blobs': blob_count, 'findings': len(findings)}))
raise SystemExit(1 if findings else 0)
