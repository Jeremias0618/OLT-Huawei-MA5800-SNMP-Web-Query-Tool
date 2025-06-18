<?php
function conectarDB() {
    $host = '10.80.80.106';
    $port = 5432;
    $dbname = 'fiberprodata';
    $user = 'fiberproadmin';
    $pass = 'noc12363';
    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return $pdo;
    } catch (PDOException $e) {
        echo "<div style='color:red'>Error de conexión a la base de datos: " . $e->getMessage() . "</div>";
        exit;
    }
}

function formatearPotencia($tipo, $valor) {
    if (!is_numeric($valor)) return 'Valor inválido';
    $dbm = ($tipo === 'retorno') ? ($valor / 100 - 100) : ($valor / 100);
    $color = 'black';
    if ($dbm >= -17.99) $color = 'red';
    elseif ($dbm >= -24.99 && $dbm <= -18.00) $color = 'green';
    elseif ($dbm >= -27.99 && $dbm <= -25.00) $color = 'orange';
    elseif ($dbm <= -28.00) $color = 'red';
    return "<span style='color:$color'>{$dbm} dBm</span>";
}

function limpiarPlan($cadena) {
    if (preg_match('/(\d+)_Mbps/', $cadena, $match)) {
        return "Internet {$match[1]} Mbps";
    }
    if (preg_match('/1_?Gbps/i', $cadena)) {
        return "Internet 1 Gbps";
    }
    return "Plan desconocido";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['snmp'])) {
    $tipo = $_POST['tipo'];
    $ip = $_POST['ip'];
    $index = $_POST['index'];
    $comunidad = "FiberPro2021";
    $oids = [
        'retorno'    => '1.3.6.1.4.1.2011.6.128.1.1.2.51.1.6.',
        'recepcion'  => '1.3.6.1.4.1.2011.6.128.1.1.2.51.1.4.',
        'desconexion'=> '1.3.6.1.4.1.2011.6.128.1.1.2.101.1.7.',
        'estado'     => '1.3.6.1.4.1.2011.6.128.1.1.2.46.1.15.',
        'plan'       => '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.7.'
    ];
    if (!isset($oids[$tipo])) {
        echo "<span style='color:red'>Tipo de consulta SNMP no válido.</span>";
        exit;
    }
    $oid = $oids[$tipo] . $index;
    if ($tipo === 'desconexion') {
        // Buscar última conexión de 9 a 0
        $resultado = '';
        for ($i = 9; $i >= 0; $i--) {
            $oid_ult = $oid . '.' . $i;
            $snmp = @snmpget($ip, $comunidad, $oid_ult, 1000000, 1);
            if ($snmp !== false && preg_match('/"([^"]+)"/', $snmp, $match) && !empty($match[1])) {
                $fecha_original = str_replace('Z', '', $match[1]);
                $dt = DateTime::createFromFormat('Y-m-d H:i:s', $fecha_original, new DateTimeZone('UTC'));
                if ($dt) {
                    $dt->modify('+8 hours');
                    $resultado = $dt->format('Y-m-d H:i:s');
                } else {
                    $resultado = $fecha_original;
                }
                break;
            }
        }
        echo $resultado ? "<b>Última conexión:</b> $resultado" : "No disponible";
        exit;
    }
    $snmp = @snmpget($ip, $comunidad, $oid, 1000000, 1);
    if ($snmp === false) {
        echo "<span style='color:red'>Sin respuesta SNMP.</span>";
        exit;
    }
    switch ($tipo) {
        case 'retorno':
            if (preg_match('/(-?\d+\.?\d*)/', $snmp, $match)) {
                echo "<b>Potencia de Retorno:</b> " . formatearPotencia('retorno', $match[1]);
            } else {
                echo "Sin datos";
            }
            break;
        case 'recepcion':
            if (preg_match('/(-?\d+\.?\d*)/', $snmp, $match)) {
                echo "<b>Potencia de Recepción:</b> " . formatearPotencia('recepcion', $match[1]);
            } else {
                echo "Sin datos";
            }
            break;
        case 'estado':
            if (preg_match('/INTEGER: (\d+)/', $snmp, $match)) {
                $estado = ($match[1] == '1') ? 'Online' : (($match[1] == '2') ? 'Offline' : 'Desconocido');
                echo "<b>Estado:</b> $estado";
            } else {
                echo "Desconocido";
            }
            break;
        case 'plan':
            if (preg_match('/"(.*?)"/', $snmp, $plan_match)) {
                echo "<b>Plan actual:</b> " . limpiarPlan($plan_match[1]);
            } else {
                echo "No definido";
            }
            break;
        default:
            echo "Consulta SNMP no soportada.";
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Consulta OLT Huawei MA5800</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root {
            --primary: #1976d2;
            --primary-dark: #115293;
            --accent: #43a047;
            --danger: #e53935;
            --warning: #fbc02d;
            --bg: #f4f6fb;
            --white: #fff;
            --gray: #757575;
            --border: #e0e0e0;
        }
        body {
            font-family: 'Roboto', Arial, sans-serif;
            background: var(--bg);
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 480px;
            margin: 40px auto;
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 4px 24px #0001;
            padding: 2.5rem 2rem 2rem 2rem;
        }
        h2 {
            color: var(--primary-dark);
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        form {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            justify-content: center;
        }
        input[type="text"] {
            flex: 1;
            padding: 0.7rem 1rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            background: #f9f9f9;
            transition: border 0.2s;
        }
        input[type="text"]:focus {
            border-color: var(--primary);
            outline: none;
        }
        button[type="submit"], .snmp-btn {
            background: var(--primary);
            color: var(--white);
            border: none;
            border-radius: 8px;
            padding: 0.7rem 1.2rem;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        button[type="submit"]:hover, .snmp-btn:hover {
            background: var(--primary-dark);
        }
        .resultado {
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 1.5rem 1rem 1rem 1rem;
            margin-top: 1.5rem;
            box-shadow: 0 2px 8px #0001;
        }
        .resultado h3 {
            margin-top: 0;
            color: var(--primary-dark);
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }
        .datos-cliente {
            margin-bottom: 1.2rem;
        }
        .datos-cliente span {
            display: inline-block;
            min-width: 110px;
            color: var(--gray);
            font-weight: 500;
        }
        .estado-activo {
            color: var(--accent);
            font-weight: bold;
        }
        .estado-suspendido {
            color: var(--danger);
            font-weight: bold;
        }
        .estado-desconocido {
            color: var(--warning);
            font-weight: bold;
        }
        .snmp-btns {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .snmp-btn {
            flex: 1 1 40%;
            min-width: 140px;
            justify-content: center;
        }
        .respuesta {
            background: #e3f2fd;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 0.5rem;
            min-height: 40px;
            color: var(--primary-dark);
            font-size: 1rem;
            border: 1px solid var(--primary);
            user-select: text;
        }
        .comando-snmp-copiable {
            background: #fffbe7;
            color: #333;
            border: 1px solid #fbc02d;
            margin-top: 1rem;
            border-radius: 8px;
            padding: 0.7rem 1rem;
            font-size: 0.98em;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
            user-select: text;
        }
        .comando-snmp-copiable.copiado {
            background: #c8e6c9;
            color: #256029;
            border-color: #43a047;
        }
        @media (max-width: 600px) {
            .container {
                max-width: 98vw;
                padding: 1rem 0.5rem;
            }
            .snmp-btns {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <h2><span class="material-icons" style="vertical-align:middle;">router</span> Consulta OLT Huawei MA5800</h2>
    <form method="POST" style="margin-bottom:1.5rem;">
        <label>DNI/RUC: <input type="text" name="dni" required></label>
        <button type="submit" class="snmp-btn"><span class="material-icons">search</span> Consultar</button>
    </form>
    <?php
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['dni'])) {
        $dni = trim($_POST['dni']);
        $pdo = conectarDB();
        $stmt = $pdo->prepare("SELECT * FROM onu_datos WHERE onudesc = :dni LIMIT 1");
        $stmt->execute(['dni' => $dni]);
        $row = $stmt->fetch();
        if ($row) {
            // Obtener IP Host y snmpindex
            $ip_host = '';
            $comunidad = 'FiberPro2021';
            // Puedes mapear aquí según tu config.php si lo deseas
            if ($row['host'] === 'SD-1') $ip_host = '10.20.70.10';
            if ($row['host'] === 'SD-2') $ip_host = '10.20.70.21';
            if ($row['host'] === 'SD-3') $ip_host = '10.20.70.30';
            if ($row['host'] === 'SD-4') $ip_host = '10.20.70.46';
            if ($row['host'] === 'INC-5') $ip_host = '10.5.5.2';
            if ($row['host'] === 'SD-7') $ip_host = '10.20.70.72';
            if ($row['host'] === 'JIC-8') $ip_host = '172.16.2.2';
            if ($row['host'] === 'NEW_JIC-8') $ip_host = '172.17.2.2';
            if ($row['host'] === 'ATE-9') $ip_host = '172.99.99.2';
            if ($row['host'] === 'SMP-10') $ip_host = '10.170.7.2';
            if ($row['host'] === 'CAMP-11') $ip_host = '10.111.11.2';
            if ($row['host'] === 'CAMP2-11') $ip_host = '10.112.25.2';
            if ($row['host'] === 'PTP-12') $ip_host = '20.20.5.1';
            if ($row['host'] === 'ANC-13') $ip_host = '10.13.13.2';
            if ($row['host'] === 'CHO-14') $ip_host = '172.18.2.2';
            if ($row['host'] === 'LO-15') $ip_host = '10.70.7.2';
            if ($row['host'] === 'LO2-15') $ip_host = '10.70.8.2';
            if ($row['host'] === 'VIR-16') $ip_host = '30.150.130.2';
            if ($row['host'] === 'PTP-17') $ip_host = '10.17.7.2';
            if ($row['host'] === 'VENT-18') $ip_host = '18.18.1.2';
            $snmpindex = $row['snmpindexonu'];
            echo "<div class='resultado'>";
            echo "<h3><span class='material-icons' style='vertical-align:middle;'>person</span> Datos del Cliente</h3>";
            echo "<div class='datos-cliente'>";
            echo "<span><span class='material-icons' style='font-size:1.1em;'>badge</span> DNI/RUC:</span> {$row['onudesc']}<br>";
            echo "<span><span class='material-icons' style='font-size:1.1em;'>verified_user</span> Estado:</span> {$row['act_susp']}<br>";
            echo "<span><span class='material-icons' style='font-size:1.1em;'>dns</span> OLT:</span> {$row['host']}<br>";
            echo "<span><span class='material-icons' style='font-size:1.1em;'>lan</span> PON Lógico:</span> {$row['host']}/{$row['slotportonu']}/{$row['onulogico']}<br>";
            echo "<span><span class='material-icons' style='font-size:1.1em;'>history</span> Última conexión:</span> {$row['fecha']}<br>";
            echo "</div>";
            // NUEVO: Mostrar Host, IP Host y snmpindexonu en un bloque extra
            echo "<div class='extra-info'>";
            echo "<b>Host:</b> {$row['host']}<br>";
            echo "<b>IP Host:</b> $ip_host<br>";
            echo "<b>snmpindexonu:</b> {$row['snmpindexonu']}<br>";
            echo "</div>";
            echo "<h4 style='margin-bottom:0.7rem;'><span class='material-icons' style='vertical-align:middle;'>settings_ethernet</span> Consultas SNMP</h4>";
            echo "<div class='snmp-btns'>";
            echo "<button class='snmp-btn' onclick=\"consultarSNMP('retorno', '$ip_host', '$snmpindex');return false;\"><span class='material-icons'>arrow_upward</span> Potencia de Retorno</button>";
            echo "<button class='snmp-btn' onclick=\"consultarSNMP('recepcion', '$ip_host', '$snmpindex');return false;\"><span class='material-icons'>arrow_downward</span> Potencia de Recepción</button>";
            echo "<button class='snmp-btn' onclick=\"consultarSNMP('desconexion', '$ip_host', '$snmpindex');return false;\"><span class='material-icons'>history</span> Última Conexión</button>";
            echo "<button class='snmp-btn' onclick=\"consultarSNMP('estado', '$ip_host', '$snmpindex');return false;\"><span class='material-icons'>power_settings_new</span> Estado Online/Offline</button>";
            echo "<button class='snmp-btn' onclick=\"consultarSNMP('plan', '$ip_host', '$snmpindex');return false;\"><span class='material-icons'>wifi</span> Plan Actual</button>";
            echo "</div>";
            // NUEVO: Cuadro para mostrar el comando SNMP usado y copiar al hacer click
            echo "<div id='comando-snmp' class='comando-snmp-copiable' title='Haz clic para copiar el comando SNMP' onclick='copiarComandoSNMP(this)' style='display:none;'></div>";
            echo "<div id='respuesta' class='respuesta'></div>";
            echo "</div>";
        } else {
            echo "<div style='color:red'>No se encontró información para el DNI/RUC ingresado.</div>";
        }
    }
    ?>
</div>
<script>
function consultarSNMP(tipo, ip, index) {
    const respuesta = document.getElementById('respuesta');
    const comandoDiv = document.getElementById('comando-snmp');
    respuesta.innerHTML = '<span class="material-icons" style="vertical-align:middle;">hourglass_empty</span> Consultando...';

    // Construir el comando SNMP mostrado al usuario
    let oid = '';
    let comando = '';
    let comunidad = 'FiberPro2021';
    switch(tipo) {
        case 'retorno':
            oid = '1.3.6.1.4.1.2011.6.128.1.1.2.51.1.6.' + index;
            comando = `snmpget -v2c -c ${comunidad} ${ip} ${oid}`;
            break;
        case 'recepcion':
            oid = '1.3.6.1.4.1.2011.6.128.1.1.2.51.1.4.' + index;
            comando = `snmpget -v2c -c ${comunidad} ${ip} ${oid}`;
            break;
        case 'desconexion':
            // Usar snmpwalk para última desconexión
            oid = '1.3.6.1.4.1.2011.6.128.1.1.2.101.1.7.' + index;
            comando = `snmpwalk -v2c -c ${comunidad} ${ip} ${oid}`;
            break;
        case 'estado':
            oid = '1.3.6.1.4.1.2011.6.128.1.1.2.46.1.15.' + index;
            comando = `snmpget -v2c -c ${comunidad} ${ip} ${oid}`;
            break;
        case 'plan':
            oid = '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.7.' + index;
            comando = `snmpget -v2c -c ${comunidad} ${ip} ${oid}`;
            break;
        default:
            comando = '';
    }
    if (comando) {
        comandoDiv.innerHTML = `<b>Comando SNMP usado:</b><br><code style="font-size:0.95em">${comando}</code>`;
        comandoDiv.style.display = 'block';
        comandoDiv.classList.remove('copiado');
    } else {
        comandoDiv.innerHTML = '';
        comandoDiv.style.display = 'none';
    }

    const formData = new FormData();
    formData.append('snmp', '1');
    formData.append('tipo', tipo);
    formData.append('ip', ip);
    formData.append('index', index);
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(resp => resp.text())
    .then(data => {
        respuesta.innerHTML = data;
    })
    .catch(err => {
        respuesta.innerHTML = '<span class="material-icons" style="vertical-align:middle;">error</span> Error en la consulta SNMP.';
    });
}

// Copiar comando SNMP al hacer click
function copiarComandoSNMP(div) {
    const code = div.querySelector('code');
    if (!code) return;
    const texto = code.innerText;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(texto).then(() => {
            div.classList.add('copiado');
            div.title = '¡Comando copiado!';
            setTimeout(() => {
                div.classList.remove('copiado');
                div.title = 'Haz clic para copiar el comando SNMP';
            }, 1200);
        });
    } else {
        // Fallback para navegadores antiguos
        const temp = document.createElement('textarea');
        temp.value = texto;
        document.body.appendChild(temp);
        temp.select();
        document.execCommand('copy');
        document.body.removeChild(temp);
        div.classList.add('copiado');
        div.title = '¡Comando copiado!';
        setTimeout(() => {
            div.classList.remove('copiado');
            div.title = 'Haz clic para copiar el comando SNMP';
        }, 1200);
    }
}
</script>
</body>
</html>