# Run Open Opus on a Synology NAS (DSM 7, Web Station)

This uses the web server and database DSM already offers. It was set up and tested on a Synology with DSM 7, PHP 8.4 and MariaDB 10.

Keep it on your home network. The old code puts web addresses straight into database queries, so never open its port on your router.

## What you need

- The `openopus_api` folder (this repository, with this `synology` folder in it)
- The `openopus_utils` folder from https://github.com/openopus-org/openopus_utils (tested with commit 64665cc2fc5e468657470dc45c83410337a6fbc4)
- A data file, `dump.json`
- SSH switched on in Control Panel, Terminal & SNMP

## 1. Copy the files

With File Station, put both folders straight into the shared folder `web`:

- `/volume1/web/openopus_api`
- `/volume1/web/openopus_utils`

Both must sit side by side in `web`. If `openopus_utils` is missing or somewhere else, every page fails with "Call to undefined function mysqlfetch()".

Put `dump.json` anywhere handy, for example `/volume1/web/dump.json`. It can be deleted after the import.

## 2. Packages

In Package Center install Web Station, PHP 8 (the newest 8.x offered), MariaDB 10 and Text Editor.

Open MariaDB 10 and note its port (it can be 3306 or 3307).

The MariaDB root password is not your DSM admin password. It was set when MariaDB 10 was first installed. If you do not know it, use the reset option in the MariaDB 10 app and choose a new one.

## 3. PHP profile

Web Station, Script Language Settings, PHP, Create a profile for PHP 8.x:

- Extensions: turn on `mysqli` and `iconv`
- Core: `short_open_tag` On (the code uses the short opening tag)
- open_basedir: unrestricted, or allow `/volume1/web` and `/tmp`

## 4. Settings file

Copy `synology/inc.php` to `lib/inc.php` and open it with Text Editor:

- `mysqli.default_port`: the port from step 2
- `DBPASS`: a new password for the database user, not your root or admin password. Note it exactly, including capitals and symbols.
- If the volume is not volume1, change `/volume1/web` near the top

## 5. Permissions

The web server runs as the NAS user `http`. Over SSH:

```
sudo chmod -R o+rX /volume1/web/openopus_api /volume1/web/openopus_utils
```

In File Station, give the `http` group Read & Write on `openopus_api/html` (folder, sub-folders and files). The API saves its answers there.

Note: editing a file with `sudo sed -i` resets its permissions so nobody else can read it. Run the `chmod` line above again after any such edit.

## 6. Website

1. Web Station, Web Service, Create, Native script language website. Pick the PHP profile from step 3 and Apache HTTP Server 2.4 as back end (not Nginx, the address rules in `.htaccess` need Apache).
2. Document root: `web/openopus_api/html` (the `html` folder).
3. Web Portal: port based, HTTP port 8080 or another free port (never 80, 443, 5000, 5001).

Optional nice name: Control Panel, Login Portal, Advanced, Reverse Proxy. Source HTTP `openopus.local` port 80, destination HTTP `localhost` and the port above.

## 7. Search settings

Search only works with two MariaDB settings. On Synology the file for your own settings is `/var/packages/MariaDB10/etc/my.cnf` (Synology rewrites the other files there). If it ends with a `[mysqld]` section, add the lines at the bottom:

```
sudo cp /var/packages/MariaDB10/etc/my.cnf /var/packages/MariaDB10/etc/my.cnf.before-openopus
sudo sh -c 'printf "innodb_ft_min_token_size = 1\ninnodb_ft_enable_stopword = 0\n" >> /var/packages/MariaDB10/etc/my.cnf'
sudo synopkg restart MariaDB10
```

## 8. Database

On Synology, root can usually only log in through MariaDB's socket file, not through 127.0.0.1 (the NAS has `skip-name-resolve` switched on). Check the socket name with `ls /run/mysqld/` (normally `mysqld10.sock`).

Each command asks for the MariaDB root password. Put the DBPASS password from step 4 in place of NEWPASSWORD:

```
/usr/local/mariadb10/bin/mysql -u root -p -S /run/mysqld/mysqld10.sock -e "create database if not exists openopus character set utf8mb4 collate utf8mb4_unicode_ci; create user if not exists 'openopus'@'localhost' identified by 'NEWPASSWORD'; create user if not exists 'openopus'@'127.0.0.1' identified by 'NEWPASSWORD'; grant all on openopus.* to 'openopus'@'localhost'; grant all on openopus.* to 'openopus'@'127.0.0.1';"
/usr/local/mariadb10/bin/mysql -u root -p -S /run/mysqld/mysqld10.sock openopus < /volume1/web/openopus_api/sql/openopus.sql
```

The second command may stall on its last statement (`ALTER TABLE composer MODIFY id ... AUTO_INCREMENT`). If it does, press Ctrl+C. The tables are already there and the import does not need that statement. Check with:

```
/usr/local/mariadb10/bin/mysql -u root -p -S /run/mysqld/mysqld10.sock openopus -e "show tables;"
```

You should see composer, omnisearch, performer, work and work_mbid.

Check the website's own login (type the DBPASS password):

```
/usr/local/mariadb10/bin/mysql -u openopus -p -h 127.0.0.1 -P 3306 openopus -e "select 'login works';"
```

## 9. Load the data

Use the PHP command that matches your profile (`ls /usr/local/bin/php8*` lists them):

```
php84 /volume1/web/openopus_api/synology/import.php /volume1/web/dump.json
```

It takes a few minutes. The MusicBrainz links at the end are the slow part. It ends with "Imported ... composers and ... works", "Search table rebuilt" and "Cache cleared". Running it again with a newer file replaces all data.

## 10. Test

- http://NAS:8080/composer/list/pop.json
- http://NAS:8080/work/list/composer/145/genre/all.json (Beethoven, works carry "mbids")
- http://NAS:8080/omnisearch/beethoven%20symphony/0.json

## When a page fails

Errors are hidden so they do not end up in the JSON, so a failing page is an empty "500". To see the real error, run the page as the web user, for example:

```
cd /volume1/web/openopus_api/html/dyn/composer && sudo -u http php84 -d log_errors=1 -d error_log=/dev/stderr list.phtml 2>&1 | head -20
```

Known problems:

- "Call to undefined function mysqlfetch()": `openopus_utils` is not at `/volume1/web/openopus_utils`, or `http` cannot read it (step 5).
- `mysqli_set_charset(): Argument #1 ... false given`: the database login failed. Check the port and that DBPASS matches the password used in step 8 exactly.
- Search gives 500 on PHP 8: needs the fix in `html/dyn/omnisearch/list.phtml` (`$featcomposers ?? Array ()`), included in this branch and offered upstream.
