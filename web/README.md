# M7 Web

Web PHP sencilla para el servidor M7.

## Requisitos

- PHP con extension `pdo_mysql`.
- Apache/XAMPP/WAMP o cualquier servidor que ejecute PHP.
- La carpeta `web/` debe estar dentro del proyecto o debe poder leer `../config/server.properties`.

## Uso

1. Copia o publica la carpeta `web/` en tu servidor web.
2. Asegurate de que `config/server.properties` tiene los datos correctos de MySQL.
3. Abre `index.php`.

La web lee automaticamente:

- Estado del puerto `GameserverPort`.
- Jugadores online desde `characters.OnlineStatus`.
- Ranking de personajes desde `characters`.
- Ranking de armas desde `character_items` + `weapon`.
- Comandos publicos desde `commands`.

No escribe en la base de datos.
