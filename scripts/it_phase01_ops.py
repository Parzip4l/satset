"""Bounded Docker operator tasks for Satset phase 0/1. Sensitive artifacts stay private.

The restore database is named satset_it_verify_*; this tool never restores over the source.
"""
import argparse
import datetime
import gzip
import hashlib
import json
import os
from pathlib import Path
import subprocess
import tarfile

ROOT = Path(__file__).resolve().parents[1]
BACKUPS = ROOT / "storage/app/private/it-phase01"


def docker(service, *args, stdin=None, env=None):
    command = ["docker", "compose", "exec", "-T"]
    for key, value in (env or {}).items():
        command.extend(["-e", f"{key}={value}"])
    command.extend([service, *args])
    return subprocess.run(command, cwd=ROOT, input=stdin, stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True).stdout


def private_write(path, data):
    with open(path, "xb") as output:
        os.chmod(path, 0o600)
        output.write(data)


def snapshot(projection=None, database=None):
    return docker("app", "php", "scripts/it_phase01_snapshot.php", *([projection] if projection else []),
                  env={"DB_DATABASE": database, "APP_CONFIG_CACHE": "/tmp/satset-it-no-config-cache.php"} if database else None)


def mysql(sql):
    return docker("db", "sh", "-c", 'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; exec mysql -uroot --batch --skip-column-names', stdin=sql.encode())


def backup():
    BACKUPS.mkdir(parents=True, exist_ok=True)
    os.chmod(BACKUPS, 0o700)
    folder = BACKUPS / datetime.datetime.now(datetime.timezone.utc).strftime("baseline-%Y%m%dT%H%M%SZ")
    folder.mkdir(mode=0o700)
    baseline = snapshot()
    parsed_baseline = json.loads(baseline)
    if not parsed_baseline.get('tables'):
        raise RuntimeError('Backup requires a valid table snapshot')
    private_write(folder / "baseline.json", baseline)
    # Excludes CREATE DATABASE / USE so restore can only target the separately named verification DB.
    dump = docker("db", "sh", "-c", 'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; exec mysqldump -uroot --single-transaction --routines --triggers --events --hex-blob --no-tablespaces --set-gtid-purged=OFF "$MYSQL_DATABASE"')
    private_write(folder / "database.sql.gz", gzip.compress(dump))
    manifest = {}
    files_path = folder / "files-and-config.tar.gz"
    with tarfile.open(files_path, "x:gz") as archive:
        os.chmod(files_path, 0o600)
        roots = [ROOT / "storage/app", ROOT / ".env", ROOT / "bootstrap/cache"]
        files = []
        for source in roots:
            files.extend([source] if source.is_file() else [p for p in source.rglob("*") if p.is_file() and not p.is_symlink() and BACKUPS not in p.parents])
        for path in sorted(set(files)):
            rel = path.relative_to(ROOT).as_posix()
            archive.add(path, arcname=rel, recursive=False)
            manifest[rel] = hashlib.sha256(path.read_bytes()).hexdigest()
    private_write(folder / "file-manifest.json", json.dumps(manifest, indent=2).encode())
    # Confirm no data changed during the snapshot/dump window.
    projection = "/var/www/" + (folder / "baseline.json").relative_to(ROOT).as_posix()
    after = json.loads(snapshot(projection))
    before = json.loads(baseline)
    if before["tables"] != after["tables"]:
        raise RuntimeError("Source changed during backup: retain evidence and retry a consistent baseline.")
    print(json.dumps({"backup": str(folder.relative_to(ROOT)), "tables": len(before["tables"]), "files": len(manifest), "source_stable": True}))


def restore_verify(folder):
    folder = folder.resolve()
    if folder.parent != BACKUPS.resolve():
        raise ValueError("Backup must be inside the private phase01 folder")
    db = "satset_it_verify_" + datetime.datetime.now(datetime.timezone.utc).strftime("%Y%m%d%H%M%S")
    mysql(f"CREATE DATABASE `{db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;")
    docker("db", "sh", "-c", 'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; exec mysql -uroot "$1"', "restore", db,
           stdin=gzip.decompress((folder / "database.sql.gz").read_bytes()))
    projection = "/var/www/" + (folder / "baseline.json").relative_to(ROOT).as_posix()
    actual = json.loads(snapshot(projection, db))
    baseline = json.loads((folder / "baseline.json").read_text())
    if actual["tables"] != baseline["tables"]:
        raise RuntimeError("Restored database differs from baseline")
    # Extract only regular files to a new isolated directory and verify their contents.
    restore_path = folder / "restore-files"
    restore_path.mkdir(mode=0o700)
    manifest = json.loads((folder / "file-manifest.json").read_text())
    with tarfile.open(folder / "files-and-config.tar.gz", "r:gz") as archive:
        for member in archive:
            destination = (restore_path / member.name).resolve()
            if not member.isfile() or not destination.is_relative_to(restore_path.resolve()):
                raise RuntimeError("Unexpected archive member")
            destination.parent.mkdir(parents=True, exist_ok=True, mode=0o700)
            private_write(destination, archive.extractfile(member).read())
    verified = all(hashlib.sha256((restore_path / name).read_bytes()).hexdigest() == digest for name, digest in manifest.items())
    if not verified:
        raise RuntimeError("Restored file digest mismatch")
    report = {"verification_database": db, "tables_verified": len(actual["tables"]), "files_verified": len(manifest), "database_equal": True, "files_equal": True}
    private_write(folder / "restore-verification.json", json.dumps(report, indent=2).encode())
    print(json.dumps(report))


def migrate_verify(folder):
    folder = folder.resolve()
    if folder.parent != BACKUPS.resolve():
        raise ValueError("Invalid backup location")
    verified = json.loads((folder / "restore-verification.json").read_text())
    database = verified["verification_database"]
    if not database.startswith("satset_it_verify_"):
        raise ValueError("Only the separately restored verification database may be changed here")
    env = {"DB_DATABASE": database, "APP_CONFIG_CACHE": "/tmp/satset-it-no-config-cache.php"}
    commands = [
        ["migrate", "--force"],
        ["satset:review-legacy-domains", "--reviewer=1"],
        ["satset:review-legacy-domains", "--reviewer=1", "--apply"],
        ["db:seed", "--class=ItFoundationSeeder", "--force"],
        ["satset:it-capability", "1", "manage_it_configuration", "--reason=Phase 01 configuration custodian chosen by user"],
        ["satset:it-capability", "1", "publish_it_configuration", "--reason=Phase 01 configuration publisher chosen by user"],
    ]
    for args in commands:
        output = docker("app", "php", "artisan", *args, env=env).decode()
        if "ERROR" in output or "Exception" in output:
            raise RuntimeError(output[-2500:])
        print(output.strip())
    projection = "/var/www/" + (folder / "baseline.json").relative_to(ROOT).as_posix()
    actual = json.loads(snapshot(projection, database))
    baseline = json.loads((folder / "baseline.json").read_text())
    changed = [name for name, data in baseline["tables"].items() if name != "migrations" and actual["tables"][name] != data]
    if changed:
        raise RuntimeError("Unexpected changes in original columns: " + ", ".join(changed))
    result = docker("app", "php", "scripts/it_phase01_verify.php", env=env)
    private_write(folder / "mysql-verification.json", result)
    print(result.decode())


def apply_source(folder, apply):
    if not apply:
        raise ValueError("Source deployment requires --apply")
    folder = folder.resolve()
    if folder.parent != BACKUPS.resolve():
        raise ValueError("Invalid backup location")
    verified = json.loads((folder / "mysql-verification.json").read_text())
    if verified.get("database_driver") != "mysql" or not verified.get("checks") or not all(verified["checks"].values()):
        raise RuntimeError("Isolated MySQL verification must pass before deployment")
    projection = "/var/www/" + (folder / "baseline.json").relative_to(ROOT).as_posix()
    baseline = json.loads((folder / "baseline.json").read_text())
    current = json.loads(snapshot(projection))
    changed_before = [name for name, data in baseline["tables"].items() if current["tables"][name] != data]
    # Active web requests refresh session payload/last_activity. They are not business migration inputs.
    if set(changed_before) - {"sessions"}:
        raise RuntimeError("Business source changed since backup; take and verify a new backup before deployment")
    for args in [
        ["migrate", "--force"],
        ["satset:review-legacy-domains", "--reviewer=1", "--apply"],
        ["db:seed", "--class=ItFoundationSeeder", "--force"],
        ["satset:it-capability", "1", "manage_it_configuration", "--reason=User selected Muhamad Sobirin ID 1 for phase 01 configuration"],
        ["satset:it-capability", "1", "publish_it_configuration", "--reason=User selected Muhamad Sobirin ID 1 for phase 01 publication"],
    ]:
        output = docker("app", "php", "artisan", *args).decode()
        if "ERROR" in output or "Exception" in output:
            raise RuntimeError(output[-2500:])
        print(output.strip())
    actual = json.loads(snapshot(projection))
    changed = [name for name, data in baseline["tables"].items() if name not in {"migrations", "sessions"} and actual["tables"][name] != data]
    if changed:
        raise RuntimeError("Unexpected change in original columns after migration: " + ", ".join(changed))
    audit = json.loads(docker("app", "php", "artisan", "satset:it-audit", "--json"))
    report = {"deployed": True, "original_tables_unchanged_except_migration_log_and_active_sessions": len(baseline["tables"]) - 2,
              "allowed_pre_deployment_drift": changed_before,
              "tickets": audit["tables"]["requests"]["rows"], "reviewed": audit["reviewed_count"],
              "unreviewed": audit["review_count"], "retained_legacy_exceptions": audit["legacy_exception_count"],
              "it_details": audit["tables"]["it_ticket_details"]["rows"]}
    private_write(folder / "source-deployment.json", json.dumps(report, indent=2).encode())
    print(json.dumps(report))


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("stage", choices=["backup", "restore-verify", "migrate-verify", "apply-source", "drift", "inspect"])
    parser.add_argument("--backup", type=Path)
    parser.add_argument("--apply", action="store_true")
    args = parser.parse_args()
    if args.stage == "backup":
        backup()
    elif args.stage == "restore-verify":
        restore_verify(args.backup)
    elif args.stage == "migrate-verify":
        migrate_verify(args.backup)
    elif args.stage == "apply-source":
        apply_source(args.backup, args.apply)
    elif args.stage == "drift":
        folder = args.backup.resolve()
        if folder.parent != BACKUPS.resolve():
            raise ValueError("Invalid backup location")
        before = json.loads((folder / "baseline.json").read_text())["tables"]
        projection = "/var/www/" + (folder / "baseline.json").relative_to(ROOT).as_posix()
        after = json.loads(snapshot(projection))["tables"]
        print(json.dumps({name: {"before_count": data["count"], "after_count": after[name]["count"]} for name, data in before.items() if after[name] != data}))
    else:
        report = json.loads(snapshot())
        print(json.dumps({key: value for key, value in report.items() if key != "tables"}, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
