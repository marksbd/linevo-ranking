<?php

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function readProperties($path)
{
    $properties = [];
    if (!is_file($path)) {
        return $properties;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $properties[trim($parts[0])] = trim($parts[1]);
        }
    }
    return $properties;
}

function dbSettings($server)
{
    $url = isset($server['URL']) ? $server['URL'] : 'jdbc:mysql://localhost/l1jdb?useUnicode=True&characterEncoding=UTF-8';
    $host = 'localhost';
    $port = '3306';
    $database = 'l1jdb';

    if (preg_match('#jdbc:mysql://([^/:?]+)(?::([0-9]+))?/([^?]+)#', $url, $matches)) {
        $host = $matches[1];
        $port = isset($matches[2]) ? $matches[2] : '3306';
        $database = $matches[3];
    }

    return [
        'host' => $host,
        'port' => $port,
        'database' => $database,
        'user' => isset($server['Login']) ? $server['Login'] : 'root',
        'password' => isset($server['Password']) ? $server['Password'] : '',
    ];
}

function connectDb($settings)
{
    try {
        $dsn = 'mysql:host=' . $settings['host'] . ';port=' . $settings['port']
            . ';dbname=' . $settings['database'] . ';charset=utf8';
        return new PDO($dsn, $settings['user'], $settings['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Exception $exception) {
        return null;
    }
}

function fetchAllSafe($pdo, $sql)
{
    if ($pdo === null) {
        return [];
    }
    try {
        return $pdo->query($sql)->fetchAll();
    } catch (Exception $exception) {
        return [];
    }
}

function fetchValueSafe($pdo, $sql, $default = 0)
{
    if ($pdo === null) {
        return $default;
    }
    try {
        $value = $pdo->query($sql)->fetchColumn();
        return $value === false ? $default : $value;
    } catch (Exception $exception) {
        return $default;
    }
}

function isPortOpen($host, $port)
{
    $connection = @fsockopen($host, $port, $errno, $errstr, 0.25);
    if ($connection) {
        fclose($connection);
        return true;
    }
    return false;
}

function className($type)
{
    $classes = [
        0 => 'Royal',
        1 => 'Knight',
        2 => 'Elf',
        3 => 'Wizard',
        4 => 'Dark Elf',
        5 => 'Dragon Knight',
        6 => 'Illusionist',
    ];
    $key = (int) $type;
    return isset($classes[$key]) ? $classes[$key] : 'Unknown';
}

function sexName($sex)
{
    return ((int) $sex) === 1 ? 'Female' : 'Male';
}

$root = dirname(__DIR__);
$serverConfig = readProperties($root . '/config/server.properties');
$altConfig = readProperties($root . '/config/altsettings.properties');
$db = dbSettings($serverConfig);
$pdo = connectDb($db);
$gamePort = (int) (isset($serverConfig['GameserverPort']) ? $serverConfig['GameserverPort'] : 2000);
$gameHost = $db['host'] === 'localhost' ? '127.0.0.1' : $db['host'];
$isGameOnline = isPortOpen($gameHost, $gamePort);
$page = isset($_GET['page']) ? $_GET['page'] : 'inicio';
$allowedPages = ['inicio', 'ranking', 'ayuda'];
$page = in_array($page, $allowedPages, true) ? $page : 'inicio';

$onlinePlayers = (int) fetchValueSafe($pdo, "SELECT COUNT(*) FROM characters WHERE OnlineStatus = 1", 0);
$totalCharacters = (int) fetchValueSafe($pdo, "SELECT COUNT(*) FROM characters", 0);
$topPlayers = fetchAllSafe($pdo, "
    SELECT char_name, level, Type, Sex
    FROM characters
    WHERE AccessLevel = 0 AND Banned = 0 AND char_name <> ''
    ORDER BY level DESC, Exp DESC
    LIMIT 20
");
$topWeapons = fetchAllSafe($pdo, "
    SELECT ci.item_name, ci.enchantlvl, w.type, c.char_name
    FROM character_items ci
    INNER JOIN weapon w ON w.item_id = ci.item_id
    LEFT JOIN characters c ON c.objid = ci.char_id
    WHERE ci.enchantlvl IS NOT NULL AND ci.enchantlvl > 0
    ORDER BY ci.enchantlvl DESC, ci.item_name ASC
    LIMIT 20
");
$publicCommands = fetchAllSafe($pdo, "
    SELECT name
    FROM commands
    WHERE access_level = 0
    ORDER BY name ASC
");

$manualCommands = [
    '-help' => 'Muestra la ayuda de comandos de jugador.',
    '-warp 1-7' => 'Teletransportes rapidos a zonas principales si el servidor lo permite.',
    '-karma' => 'Consulta tu karma actual.',
    '-buff' => 'Buffs basicos si esta habilitado y cumples nivel.',
    '-pbuff' => 'Buff avanzado si esta habilitado.',
    '-bug texto' => 'Reporta un bug indicando tu mapa y coordenadas.',
];

$evoCommands = [
    '.evohunt' => 'Entra en EVO Hunting Grounds desde el EVO Keeper.',
    '.evostatus' => 'Consulta tu estado de EVO Coin.',
    '.daily' => 'Muestra misiones diarias EVO.',
    '.weekly' => 'Muestra misiones semanales EVO.',
    '.pass / .battlepass' => 'Consulta el Battle Pass.',
    '.hotzone' => 'Muestra la hot zone activa.',
    '.streak' => 'Consulta tu racha de login.',
    '.ranking' => 'Ranking dentro del juego.',
    '.boss' => 'Informacion de jefes/eventos disponibles.',
    '.item / .mob' => 'Busca drops de items o monstruos.',
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>M7 Lineage Server</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <div class="page-shell">
        <header class="hero">
            <nav class="topbar">
                <a class="brand" href="?page=inicio">M7</a>
                <div class="nav-links">
                    <a class="<?= $page === 'inicio' ? 'active' : '' ?>" href="?page=inicio">Inicio</a>
                    <a class="<?= $page === 'ranking' ? 'active' : '' ?>" href="?page=ranking">Ranking</a>
                    <a class="<?= $page === 'ayuda' ? 'active' : '' ?>" href="?page=ayuda">Ayuda</a>
                </div>
            </nav>
            <div class="hero-grid">
                <div>
                    <p class="eyebrow">Lineage Private Server</p>
                    <h1>M7 Evolution</h1>
                    <p class="hero-copy">
                        Un servidor clasico con economia EVO, nuevas progresiones, eventos automatizados y zonas de caza renovadas.
                    </p>
                </div>
                <div class="status-card">
                    <span class="status-dot <?= $isGameOnline ? 'online' : 'offline' ?>"></span>
                    <strong><?= $isGameOnline ? 'Servidor online' : 'Servidor offline' ?></strong>
                    <small>Puerto <?= e($gamePort) ?> · <?= $pdo ? 'DB conectada' : 'DB no disponible' ?></small>
                    <div class="status-stats">
                        <span><b><?= e($onlinePlayers) ?></b> online</span>
                        <span><b><?= e($totalCharacters) ?></b> personajes</span>
                    </div>
                </div>
            </div>
        </header>

        <main>
            <?php if ($page === 'inicio'): ?>
                <section class="section-grid">
                    <article class="panel wide">
                        <h2>Estado del servidor</h2>
                        <p>
                            Estado detectado desde la web: <?= $isGameOnline ? 'el puerto del servidor responde' : 'el puerto del servidor no responde' ?>.
                            La base de datos <?= $pdo ? 'esta disponible para rankings' : 'no esta disponible ahora mismo' ?>.
                        </p>
                        <div class="metric-row">
                            <div><span><?= e($onlinePlayers) ?></span><small>jugadores online</small></div>
                            <div><span><?= e($totalCharacters) ?></span><small>personajes creados</small></div>
                            <div><span><?= e(isset($altConfig['EvoCoinRewardAmount']) ? $altConfig['EvoCoinRewardAmount'] : 100) ?></span><small>EVO Coin / tick</small></div>
                        </div>
                    </article>
                    <article class="panel">
                        <h2>EVO Coin</h2>
                        <p>
                            Todos los jugadores online reciben EVO Coin cada
                            <?= e(isset($altConfig['EvoCoinRewardIntervalMinutes']) ? $altConfig['EvoCoinRewardIntervalMinutes'] : 10) ?> minutos.
                            La moneda se usa en GM Shops y sistemas EVO.
                        </p>
                    </article>
                    <article class="panel">
                        <h2>GM Shops</h2>
                        <p>
                            Las tiendas GM cobran EVO Coin para separar la economia premium de la Adena tradicional.
                        </p>
                    </article>
                    <article class="panel">
                        <h2>EVO Hunting Grounds</h2>
                        <p>
                            Zona especial accesible desde el EVO Keeper. Requiere nivel
                            <?= e(isset($altConfig['EvoHuntingGroundsMinLevel']) ? $altConfig['EvoHuntingGroundsMinLevel'] : 52) ?> y cuesta
                            <?= e(isset($altConfig['EvoHuntingGroundsCost']) ? $altConfig['EvoHuntingGroundsCost'] : 100) ?> EVO Coin.
                        </p>
                    </article>
                    <article class="panel">
                        <h2>Eventos</h2>
                        <p>
                            Hot zones, misiones diarias/semanales, login streak, Battle Pass, pity drops y multiplicadores temporales de EVO Coin.
                        </p>
                    </article>
                </section>
            <?php elseif ($page === 'ranking'): ?>
                <section class="ranking-grid">
                    <article class="panel table-panel">
                        <div class="panel-heading">
                            <h2>Top personajes</h2>
                            <span>por nivel y experiencia</span>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Nombre</th>
                                        <th>Nivel</th>
                                        <th>Clase</th>
                                        <th>Sexo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$topPlayers): ?>
                                        <tr><td colspan="5" class="empty">No hay datos disponibles.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($topPlayers as $index => $player): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= e($player['char_name']) ?></td>
                                            <td><?= e($player['level']) ?></td>
                                            <td><?= e(className($player['Type'])) ?></td>
                                            <td><?= e(sexName($player['Sex'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </article>

                    <article class="panel table-panel">
                        <div class="panel-heading">
                            <h2>Armas mas encantadas</h2>
                            <span>top enchant del servidor</span>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Arma</th>
                                        <th>Tipo</th>
                                        <th>Enchant</th>
                                        <th>Dueño</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$topWeapons): ?>
                                        <tr><td colspan="5" class="empty">No hay armas encantadas registradas.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($topWeapons as $index => $weapon): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= e($weapon['item_name']) ?></td>
                                            <td><?= e($weapon['type']) ?></td>
                                            <td class="enchant">+<?= e($weapon['enchantlvl']) ?></td>
                                            <td><?= e($weapon['char_name'] ?: 'Desconocido') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </article>
                </section>
            <?php else: ?>
                <section class="section-grid">
                    <article class="panel wide">
                        <h2>Comandos para jugadores</h2>
                        <p>Estos comandos no requieren permisos GM. Los comandos con punto son comandos globales; los comandos con guion pertenecen al sistema de jugador.</p>
                        <div class="command-grid">
                            <?php foreach ($manualCommands as $command => $description): ?>
                                <div><code><?= e($command) ?></code><span><?= e($description) ?></span></div>
                            <?php endforeach; ?>
                            <?php foreach ($evoCommands as $command => $description): ?>
                                <div><code><?= e($command) ?></code><span><?= e($description) ?></span></div>
                            <?php endforeach; ?>
                            <?php foreach ($publicCommands as $command): ?>
                                <div><code>.<?= e($command['name']) ?></code><span>Comando publico registrado en base de datos.</span></div>
                            <?php endforeach; ?>
                        </div>
                    </article>
                    <article class="panel">
                        <h2>Nuevas areas de caza</h2>
                        <p>
                            EVO Hunting Grounds usa el mapa <?= e(isset($altConfig['EvoHuntingGroundsMapId']) ? $altConfig['EvoHuntingGroundsMapId'] : 34) ?>,
                            con spawns especiales y drops de EVO Coin configurables. Se entra desde el EVO Keeper en Giran.
                        </p>
                    </article>
                    <article class="panel">
                        <h2>Tipos de evento</h2>
                        <p>
                            Hot Zone, Battle Pass, Login Streak, misiones diarias/semanales, eventos temporales de multiplicador EVO y pity drops.
                        </p>
                    </article>
                    <article class="panel">
                        <h2>Consejos</h2>
                        <p>
                            Mantente online para generar EVO Coin, revisa la hot zone activa y usa las GM Shops para progresar sin romper la economia de Adena.
                        </p>
                    </article>
                </section>
            <?php endif; ?>
        </main>

        <footer>
            <span>M7 Evolution · Rankings en tiempo real desde MySQL</span>
            <span>Actualizado: <?= date('d/m/Y H:i') ?></span>
        </footer>
    </div>
</body>
</html>
