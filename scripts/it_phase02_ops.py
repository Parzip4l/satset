"""Phase 2 deployment: verify on the restored MySQL copy before changing the source."""
import argparse
import json
from pathlib import Path
import it_phase01_ops as ops

parser = argparse.ArgumentParser()
parser.add_argument('stage', choices=['verify', 'apply'])
parser.add_argument('--backup', type=Path, required=True)
parser.add_argument('--apply', action='store_true')
args = parser.parse_args()
folder = args.backup.resolve()
if folder.parent != ops.BACKUPS.resolve():
    raise ValueError('Backup must be in the private operator folder')
baseline = json.loads((folder / 'baseline.json').read_text())
projection = '/var/www/' + (folder / 'baseline.json').relative_to(ops.ROOT).as_posix()
commands = [['migrate', '--path=database/migrations/2026_10_28_000003_create_it_configuration_versions.php', '--force'], ['db:seed', '--class=ItConfigurationSeeder', '--force']]
if args.stage == 'verify':
    database = json.loads((folder / 'restore-verification.json').read_text())['verification_database']
    if not database.startswith('satset_it_verify_'):
        raise ValueError('Only isolated verification databases may be used')
    env = {'DB_DATABASE': database, 'APP_CONFIG_CACHE': '/tmp/satset-it-phase02-no-cache.php'}
    for command in commands:
        print(ops.docker('app', 'php', 'artisan', *command, env=env).decode().strip())
    result = ops.docker('app', 'php', 'scripts/it_phase02_verify.php', env=env)
    report = json.loads(result)
    if not report['checks'] or not all(report['checks'].values()):
        raise RuntimeError('Isolated verification failed')
    ops.private_write(folder / 'phase02-mysql-verification.json', result)
    current = json.loads(ops.snapshot(projection, database))
    changed = [name for name, value in baseline['tables'].items() if name not in {'sessions', 'migrations'} and current['tables'][name] != value]
    if changed:
        raise RuntimeError('Original data changed in verification: ' + ', '.join(changed))
    print(result.decode())
else:
    verified = json.loads((folder / 'phase02-mysql-verification.json').read_text())
    if not args.apply or not all(verified['checks'].values()):
        raise ValueError('Deployment requires --apply and passing isolated verification')
    before = json.loads(ops.snapshot(projection))
    changed = [name for name, value in baseline['tables'].items() if name != 'sessions' and before['tables'][name] != value]
    if changed:
        raise RuntimeError('Source changed since backup: ' + ', '.join(changed))
    for command in commands:
        output = ops.docker('app', 'php', 'artisan', *command).decode()
        if 'Exception' in output or 'ERROR' in output:
            raise RuntimeError(output[-2000:])
        print(output.strip())
    after = json.loads(ops.snapshot(projection))
    changed = [name for name, value in baseline['tables'].items() if name not in {'sessions', 'migrations'} and after['tables'][name] != value]
    if changed:
        raise RuntimeError('Original data changed after deployment: ' + ', '.join(changed))
    report = {'deployed': True, 'original_tables_preserved': len(baseline['tables']) - 2, 'seeded_draft_only': True, 'submission_enabled': False}
    ops.private_write(folder / 'phase02-deployment.json', json.dumps(report, indent=2).encode())
    print(json.dumps(report))
