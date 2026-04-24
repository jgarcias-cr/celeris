# Database Engine Configuration (API/MVC Apps)

This guide explains how to switch database engines in your API or MVC app.

## Default in your API or MVC project.

The API and MVC `.env` templates use PostgreSQL by default:

```dotenv
DB_DEFAULT=pgsql
PGSQL_HOST=127.0.0.1
PGSQL_PORT=5432
PGSQL_DATABASE=app
PGSQL_USERNAME=postgres
PGSQL_PASSWORD=
```

## How to switch engine

1. Set `DB_DEFAULT` to the connection name you want.
2. Add only the env keys required for that engine.
3. Ensure the corresponding PDO extension is installed.

`DB_DEFAULT` connection names:

- `main` (SQLite)
- `mysql`
- `mariadb`
- `pgsql`
- `sqlsrv`
- `firebird`
- `ibm`
- `oci`

## Engine key sets

### SQLite (`DB_DEFAULT=main`)

```dotenv
DB_DEFAULT=main
DB_SQLITE_PATH=var/database.sqlite
```

### MySQL (`DB_DEFAULT=mysql`)

```dotenv
DB_DEFAULT=mysql
MYSQL_HOST=127.0.0.1
MYSQL_PORT=3306
MYSQL_DATABASE=app
MYSQL_USERNAME=root
MYSQL_PASSWORD=
```

### MariaDB (`DB_DEFAULT=mariadb`)

```dotenv
DB_DEFAULT=mariadb
MARIADB_HOST=127.0.0.1
MARIADB_PORT=3306
MARIADB_DATABASE=app
MARIADB_USERNAME=root
MARIADB_PASSWORD=
```

### PostgreSQL (`DB_DEFAULT=pgsql`)

```dotenv
DB_DEFAULT=pgsql
PGSQL_HOST=127.0.0.1
PGSQL_PORT=5432
PGSQL_DATABASE=app
PGSQL_USERNAME=postgres
PGSQL_PASSWORD=
```

### SQL Server (`DB_DEFAULT=sqlsrv`)

```dotenv
DB_DEFAULT=sqlsrv
SQLSRV_HOST=127.0.0.1
SQLSRV_PORT=1433
SQLSRV_DATABASE=app
SQLSRV_USERNAME=sa
SQLSRV_PASSWORD=
```

### Firebird (`DB_DEFAULT=firebird`)

```dotenv
DB_DEFAULT=firebird
FIREBIRD_HOST=127.0.0.1
FIREBIRD_PORT=3050
FIREBIRD_DATABASE=/var/lib/firebird/data/app.fdb
FIREBIRD_USERNAME=SYSDBA
FIREBIRD_PASSWORD=masterkey
FIREBIRD_CHARSET=UTF8
FIREBIRD_DSN=
FIREBIRD_ID_STRATEGY=auto
FIREBIRD_ID_SEQUENCE=
FIREBIRD_ID_SEQUENCE_PATTERN=
```

### IBM DB2 (`DB_DEFAULT=ibm`)

```dotenv
DB_DEFAULT=ibm
IBM_HOST=127.0.0.1
IBM_PORT=50000
IBM_DATABASE=SAMPLE
IBM_USERNAME=db2inst1
IBM_PASSWORD=
IBM_PROTOCOL=TCPIP
IBM_DSN=
IBM_ID_STRATEGY=auto
IBM_ID_SEQUENCE=
IBM_ID_SEQUENCE_PATTERN=
```

### Oracle OCI (`DB_DEFAULT=oci`)

```dotenv
DB_DEFAULT=oci
OCI_HOST=127.0.0.1
OCI_PORT=1521
OCI_DATABASE=XE
OCI_SERVICE_NAME=
OCI_SID=
OCI_USERNAME=system
OCI_PASSWORD=
OCI_CHARSET=AL32UTF8
OCI_DSN=
OCI_ID_STRATEGY=auto
OCI_ID_SEQUENCE=
OCI_ID_SEQUENCE_PATTERN=
```

## Source of truth

Connection names and exact mapping are defined in:

- `packages/api-stub/config/database.php`
- `packages/mvc-stub/config/database.php`
