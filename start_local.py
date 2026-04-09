from __future__ import annotations

import argparse
import re
import socket
import subprocess
import sys
import time
import webbrowser
from pathlib import Path


PROJECT_ROOT = Path(__file__).resolve().parent
XAMPP_ROOT = PROJECT_ROOT.parent.parent
APACHE_EXE = XAMPP_ROOT / "apache" / "bin" / "httpd.exe"
MYSQLD_EXE = XAMPP_ROOT / "mysql" / "bin" / "mysqld.exe"
MYSQL_CLI = XAMPP_ROOT / "mysql" / "bin" / "mysql.exe"
MYSQL_INI = XAMPP_ROOT / "mysql" / "bin" / "my.ini"
SQL_FILE = PROJECT_ROOT / "database" / "cardapio_digital.sql"
PROJECT_SLUG = PROJECT_ROOT.name
FRONTEND_URL = f"http://localhost/{PROJECT_SLUG}/"
ADMIN_URL = f"http://localhost/{PROJECT_SLUG}/admin/login.php"
PHP_MY_ADMIN_URL = "http://localhost/phpmyadmin/"

CREATE_NO_WINDOW = getattr(subprocess, "CREATE_NO_WINDOW", 0)
DETACHED_PROCESS = getattr(subprocess, "DETACHED_PROCESS", 0)
CREATE_NEW_PROCESS_GROUP = getattr(subprocess, "CREATE_NEW_PROCESS_GROUP", 0)
CREATION_FLAGS = CREATE_NO_WINDOW | DETACHED_PROCESS | CREATE_NEW_PROCESS_GROUP


def read_db_config() -> dict[str, str]:
    config = {
        "DB_HOST": "127.0.0.1",
        "DB_PORT": "3306",
        "DB_NAME": "cardapio_digital",
        "DB_USER": "root",
        "DB_PASS": "",
    }
    config_file = PROJECT_ROOT / "config" / "database.php"

    if not config_file.is_file():
        return config

    content = config_file.read_text(encoding="utf-8", errors="ignore")

    for key in config:
        match = re.search(rf"const\s+{key}\s*=\s*'([^']*)';", content)
        if match:
            config[key] = match.group(1)

    return config


def is_port_open(port: int, host: str = "127.0.0.1", timeout: float = 0.5) -> bool:
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as sock:
        sock.settimeout(timeout)
        return sock.connect_ex((host, port)) == 0


def wait_for_port(port: int, timeout: float = 20.0) -> bool:
    deadline = time.time() + timeout

    while time.time() < deadline:
        if is_port_open(port):
            return True
        time.sleep(0.5)

    return False


def launch_background(command: list[str], cwd: Path) -> None:
    subprocess.Popen(
        command,
        cwd=str(cwd),
        stdin=subprocess.DEVNULL,
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
        creationflags=CREATION_FLAGS,
    )


def start_apache() -> str:
    if is_port_open(80):
        return "Apache ja estava em execucao."

    if not APACHE_EXE.is_file():
        raise FileNotFoundError(f"Apache nao encontrado em {APACHE_EXE}")

    launch_background([str(APACHE_EXE)], APACHE_EXE.parent)

    if not wait_for_port(80):
        raise RuntimeError("Apache nao respondeu na porta 80.")

    return "Apache iniciado com sucesso."


def start_mysql() -> str:
    if is_port_open(3306):
        return "MySQL ja estava em execucao."

    if not MYSQLD_EXE.is_file():
        raise FileNotFoundError(f"MySQL nao encontrado em {MYSQLD_EXE}")

    launch_background(
        [str(MYSQLD_EXE), f"--defaults-file={MYSQL_INI}", "--standalone"],
        MYSQLD_EXE.parent,
    )

    if not wait_for_port(3306):
        raise RuntimeError("MySQL nao respondeu na porta 3306.")

    return "MySQL iniciado com sucesso."


def mysql_base_command(config: dict[str, str]) -> list[str]:
    command = [
        str(MYSQL_CLI),
        "-h",
        config["DB_HOST"],
        "-P",
        config["DB_PORT"],
        "-u",
        config["DB_USER"],
        "--default-character-set=utf8mb4",
    ]

    if config["DB_PASS"] != "":
        command.append(f"-p{config['DB_PASS']}")

    return command


def database_exists(config: dict[str, str]) -> bool:
    query = f"SHOW DATABASES LIKE '{config['DB_NAME']}';"
    result = subprocess.run(
        mysql_base_command(config) + ["-N", "-e", query],
        capture_output=True,
        text=True,
        check=False,
    )

    return result.returncode == 0 and result.stdout.strip() == config["DB_NAME"]


def import_database(config: dict[str, str]) -> str:
    if not SQL_FILE.is_file():
        raise FileNotFoundError(f"SQL nao encontrado em {SQL_FILE}")

    with SQL_FILE.open("rb") as sql_handle:
        result = subprocess.run(
            mysql_base_command(config),
            stdin=sql_handle,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            check=False,
        )

    if result.returncode != 0:
        error_text = result.stderr.decode("utf-8", errors="ignore").strip()
        raise RuntimeError(f"Falha ao importar o banco: {error_text or 'erro desconhecido'}")

    return f"Banco {config['DB_NAME']} importado com sucesso."


def ensure_database_ready(config: dict[str, str], force_import: bool) -> str:
    if force_import:
        return import_database(config)

    if database_exists(config):
        return f"Banco {config['DB_NAME']} ja existe."

    return import_database(config)


def open_target_url(target: str) -> str:
    if target == "admin":
        url = ADMIN_URL
    elif target == "phpmyadmin":
        url = PHP_MY_ADMIN_URL
    else:
        url = FRONTEND_URL

    webbrowser.open(url)
    return url


def print_status(config: dict[str, str]) -> int:
    apache = "online" if is_port_open(80) else "offline"
    mysql = "online" if is_port_open(3306) else "offline"
    database = "presente" if database_exists(config) else "ausente"

    print(f"Apache: {apache}")
    print(f"MySQL: {mysql}")
    print(f"Banco {config['DB_NAME']}: {database}")
    print(f"Frontend: {FRONTEND_URL}")
    print(f"Admin: {ADMIN_URL}")
    return 0


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        description="Liga Apache/MySQL do XAMPP para o projeto cardapio-digital."
    )
    parser.add_argument(
        "action",
        nargs="?",
        default="start",
        choices=["start", "status"],
        help="start para subir o ambiente ou status para conferir o estado atual.",
    )
    parser.add_argument(
        "--admin",
        action="store_true",
        help="Abre a tela de login do admin em vez da vitrine publica.",
    )
    parser.add_argument(
        "--phpmyadmin",
        action="store_true",
        help="Abre o phpMyAdmin em vez da vitrine publica.",
    )
    parser.add_argument(
        "--no-browser",
        action="store_true",
        help="Nao abre o navegador ao final.",
    )
    parser.add_argument(
        "--import-db",
        action="store_true",
        help="Reimporta o SQL do projeto mesmo que o banco ja exista.",
    )
    return parser


def main() -> int:
    parser = build_parser()
    args = parser.parse_args()
    config = read_db_config()

    if args.action == "status":
        return print_status(config)

    print(start_apache())
    print(start_mysql())
    print(ensure_database_ready(config, force_import=args.import_db))

    if args.no_browser:
        print("Ambiente pronto. Navegador nao foi aberto.")
        return 0

    target = "frontend"
    if args.phpmyadmin:
        target = "phpmyadmin"
    elif args.admin:
        target = "admin"

    opened_url = open_target_url(target)
    print(f"Abrindo: {opened_url}")
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except KeyboardInterrupt:
        print("Execucao interrompida.")
        sys.exit(1)
    except Exception as exc:
        print(f"Erro: {exc}")
        sys.exit(1)
