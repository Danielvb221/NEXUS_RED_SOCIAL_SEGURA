<?php
require __DIR__.'/config/database.php';
session_start();

function esc($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$layer = max(1, min(7, (int)($_GET['layer'] ?? 1)));

$db_error = null;
$users = [];
$acts = [];
$sessions = [];
$total = 0;
$high = 0;
$riskCount = [];
$dominant = null;
$duration = 0;
$views = 0;

if (!isset($_SESSION['staging_validation'])) {
    $_SESSION['staging_validation'] = null;
}

if ($pdo_oltp) {

    try {

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $action = $_POST['action'] ?? '';

            /*
            =====================================================
            STAGING AREA - VALIDACIÓN REAL
            =====================================================
            */

            if ($action === 'validate_staging') {

                $validation = [
                    'fecha' => date('Y-m-d H:i:s'),
                    'total_usuarios' => 0,
                    'total_interacciones' => 0,
                    'usuarios_validos' => 0,
                    'usuarios_invalidos' => 0,
                    'errores' => [],
                    'estado' => 'Completado'
                ];

                try {

                    $usuariosValidacion = $pdo_oltp
                        ->query("SELECT * FROM dbo.usuarios")
                        ->fetchAll(PDO::FETCH_ASSOC);

                    $interaccionesValidacion = $pdo_oltp
                        ->query("SELECT * FROM dbo.interacciones")
                        ->fetchAll(PDO::FETCH_ASSOC);

                    $validation['total_usuarios'] =
                        count($usuariosValidacion);

                    $validation['total_interacciones'] =
                        count($interaccionesValidacion);

                    /*
                    -------------------------------------------------
                    DETECTAR DNI DUPLICADOS
                    -------------------------------------------------
                    */

                    $dniContador = [];

                    foreach ($usuariosValidacion as $usuario) {

                        $dni = trim((string)($usuario['dni'] ?? ''));

                        if ($dni !== '') {
                            $dniContador[$dni] =
                                ($dniContador[$dni] ?? 0) + 1;
                        }
                    }

                    /*
                    -------------------------------------------------
                    VALIDAR USUARIOS
                    -------------------------------------------------
                    */

                    foreach ($usuariosValidacion as $usuario) {

                        $erroresUsuario = [];

                        $camposRequeridos = [
                            'nombre',
                            'apellidos',
                            'dni',
                            'genero',
                            'correo',
                            'pais',
                            'departamento',
                            'provincia',
                            'distrito',
                            'domicilio'
                        ];

                        foreach ($camposRequeridos as $campo) {

                            if (
                                !isset($usuario[$campo]) ||
                                trim((string)$usuario[$campo]) === ''
                            ) {

                                $erroresUsuario[] =
                                    "Campo vacío: $campo";
                            }
                        }

                        /*
                        VALIDAR DNI
                        */

                        $dni = trim(
                            (string)($usuario['dni'] ?? '')
                        );

                        if (!preg_match('/^[0-9]{8}$/', $dni)) {

                            $erroresUsuario[] =
                                'DNI inválido: debe tener 8 dígitos';
                        }

                        /*
                        DNI DUPLICADO
                        */

                        if (($dniContador[$dni] ?? 0) > 1) {

                            $erroresUsuario[] =
                                'DNI duplicado';
                        }

                        /*
                        VALIDAR RIESGO
                        */

                        $riesgo = (int)(
                            $usuario['riesgo'] ?? 0
                        );

                        if ($riesgo < 1 || $riesgo > 5) {

                            $erroresUsuario[] =
                                'Nivel de riesgo fuera de rango';
                        }

                        /*
                        VALIDAR CORREO
                        */

                        $correo = trim(
                            (string)($usuario['correo'] ?? '')
                        );

                        if (
                            !filter_var(
                                $correo,
                                FILTER_VALIDATE_EMAIL
                            )
                        ) {

                            $erroresUsuario[] =
                                'Correo inválido';
                        }

                        /*
                        RESULTADO
                        */

                        if (empty($erroresUsuario)) {

                            $validation['usuarios_validos']++;

                        } else {

                            $validation['usuarios_invalidos']++;

                            $validation['errores'][] = [
                                'id' => $usuario['id'] ?? null,
                                'nombre' =>
                                    ($usuario['nombre'] ?? '') .
                                    ' ' .
                                    ($usuario['apellidos'] ?? ''),
                                'dni' => $dni,
                                'errores' => $erroresUsuario
                            ];
                        }
                    }

                    /*
                    -------------------------------------------------
                    VALIDAR RELACIONES USUARIO - INTERACCIÓN
                    -------------------------------------------------
                    */

                    $idsUsuarios = array_map(
                        'strval',
                        array_column($usuariosValidacion, 'id')
                    );

                    foreach ($interaccionesValidacion as $interaccion) {

                        $usuarioId = (string)(
                            $interaccion['usuario_id'] ?? ''
                        );

                        if (!in_array(
                            $usuarioId,
                            $idsUsuarios,
                            true
                        )) {

                            $validation['errores'][] = [
                                'id' => $interaccion['id'] ?? null,
                                'nombre' => 'Interacción',
                                'dni' => '—',
                                'errores' => [
                                    'Usuario relacionado inexistente'
                                ]
                            ];
                        }
                    }

                    /*
                    -------------------------------------------------
                    ESTADO FINAL
                    -------------------------------------------------
                    */

                    $hayErrores =
                        $validation['usuarios_invalidos'] > 0 ||
                        count($validation['errores']) >
                            $validation['usuarios_invalidos'];

                    $validation['estado'] = $hayErrores
                        ? 'Observaciones'
                        : 'Validado';

                    $_SESSION['staging_validation'] = $validation;

                } catch (Throwable $e) {

                    $_SESSION['staging_validation'] = [
                        'fecha' => date('Y-m-d H:i:s'),
                        'total_usuarios' => 0,
                        'total_interacciones' => 0,
                        'usuarios_validos' => 0,
                        'usuarios_invalidos' => 0,
                        'estado' => 'Error',
                        'errores' => [
                            [
                                'id' => '—',
                                'nombre' => 'Error de validación',
                                'dni' => '—',
                                'errores' => [
                                    $e->getMessage()
                                ]
                            ]
                        ]
                    ];
                }

                header('Location: index.php?layer=2');
                exit;
            }

            /*
            =====================================================
            INVALIDAR RESULTADOS ANTERIORES
            =====================================================
            */

            if (in_array(
                $action,
                [
                    'save_user',
                    'delete_user',
                    'save_act',
                    'delete_act',
                    'generate'
                ],
                true
            )) {

                $_SESSION['staging_validation'] = null;
            }

            /*
            =====================================================
            REGISTRAR / EDITAR USUARIO
            =====================================================
            */

            if ($action === 'save_user') {

                $id = (int)($_POST['id'] ?? 0);

                $fields = [
                    'nombre',
                    'apellidos',
                    'dni',
                    'genero',
                    'fecha_nacimiento',
                    'correo',
                    'telefono',
                    'pais',
                    'departamento',
                    'provincia',
                    'distrito',
                    'domicilio',
                    'estado',
                    'riesgo'
                ];

                $data = [];

                foreach ($fields as $f) {
                    $data[$f] = $_POST[$f] ?? '';
                }

                $data['riesgo'] = max(
                    1,
                    min(5, (int)$data['riesgo'])
                );

                if ($id) {

                    $sets = implode(
                        ',',
                        array_map(
                            fn($f) => "[$f]=?",
                            $fields
                        )
                    );

                    $st = $pdo_oltp->prepare(
                        "UPDATE dbo.usuarios
                         SET $sets
                         WHERE id=?"
                    );

                    $st->execute([
                        ...array_values($data),
                        $id
                    ]);

                } else {

                    $cols = implode(
                        ',',
                        array_map(
                            fn($f) => "[$f]",
                            $fields
                        )
                    );

                    $marks = implode(
                        ',',
                        array_fill(0, count($fields), '?')
                    );

                    $st = $pdo_oltp->prepare(
                        "INSERT INTO dbo.usuarios($cols)
                         VALUES($marks)"
                    );

                    $st->execute(array_values($data));
                }
            }

            /*
            =====================================================
            ELIMINAR USUARIO
            =====================================================
            */

            if ($action === 'delete_user') {

                $pdo_oltp->beginTransaction();

                try {

                    $uid = (int)$_POST['id'];

                    $pdo_oltp
                        ->prepare(
                            'DELETE FROM dbo.interacciones
                             WHERE usuario_id=?'
                        )
                        ->execute([$uid]);

                    $pdo_oltp
                        ->prepare(
                            'DELETE FROM dbo.sesiones
                             WHERE usuario_id=?'
                        )
                        ->execute([$uid]);

                    $pdo_oltp
                        ->prepare(
                            'DELETE FROM dbo.usuarios
                             WHERE id=?'
                        )
                        ->execute([$uid]);

                    $pdo_oltp->commit();

                } catch (Throwable $e) {

                    $pdo_oltp->rollBack();

                    throw $e;
                }
            }

            /*
            =====================================================
            REGISTRAR / EDITAR INTERACCIÓN
            =====================================================
            */

            if ($action === 'save_act') {

                $id = (int)($_POST['id'] ?? 0);

                $fields = [
                    'usuario_id',
                    'tipo',
                    'categoria',
                    'formato',
                    'fecha',
                    'dispositivo',
                    'sistema_operativo',
                    'tiempo_segundos',
                    'vistas'
                ];

                $data = [];

                foreach ($fields as $f) {
                    $data[$f] = $_POST[$f] ?? '';
                }

                if ($id) {

                    $sets = implode(
                        ',',
                        array_map(
                            fn($f) => "[$f]=?",
                            $fields
                        )
                    );

                    $st = $pdo_oltp->prepare(
                        "UPDATE dbo.interacciones
                         SET $sets
                         WHERE id=?"
                    );

                    $st->execute([
                        ...array_values($data),
                        $id
                    ]);

                } else {

                    $cols = implode(
                        ',',
                        array_map(
                            fn($f) => "[$f]",
                            $fields
                        )
                    );

                    $st = $pdo_oltp->prepare(
                        "INSERT INTO dbo.interacciones($cols)
                         VALUES(" .
                        implode(
                            ',',
                            array_fill(0, count($fields), '?')
                        ) .
                        ")"
                    );

                    $st->execute(array_values($data));
                }
            }

            /*
            =====================================================
            ELIMINAR INTERACCIÓN
            =====================================================
            */

            if ($action === 'delete_act') {

                $pdo_oltp
                    ->prepare(
                        'DELETE FROM dbo.interacciones
                         WHERE id=?'
                    )
                    ->execute([(int)$_POST['id']]);
            }

            /*
            =====================================================
            GENERAR DATOS FICTICIOS
            =====================================================
            */

            if ($action === 'generate') {

                // Cantidad segura: el generador siempre se ejecuta desde XAMPP.
                $n = max(1, min(1000, (int)($_POST['cantidad'] ?? 25)));

                $names = ['Alex','Valeria','Mateo','Camila','Diego','Lucía','Gabriel','Sofía','Daniel','Andrea','Luis','Paola','Javier','Mariana'];
                $last = ['Torres','Rojas','Vargas','Castillo','Flores','Mendoza','García','Cruz','Ramírez','Paredes'];
                $district = ['Miraflores','San Isidro','Los Olivos','Surco','Comas','Callao','Cercado'];
                $types = ['Me gusta','Comentario','Compartir','Guardar','Reportar'];
                $cats = ['Tecnología','Ciberseguridad','Educación','Noticias','Entretenimiento','Deportes'];
                $devices = ['Móvil','Desktop','Tablet'];
                $oses = ['Android','iOS','Windows','macOS','Linux'];
                $formats = ['Texto','Imagen','Video'];
                $generos = ['Femenino','Masculino','Otro'];

                $pdo_oltp->beginTransaction();

                try {
                    // Evitar DNI repetidos contra registros existentes y durante esta generación.
                    $existingDni = $pdo_oltp->query(
                        "SELECT dni FROM dbo.usuarios WHERE dni IS NOT NULL"
                    )->fetchAll(PDO::FETCH_COLUMN);
                    $dniUsed = array_fill_keys(array_map('strval', $existingDni), true);

                    $sqlUser = '
                        INSERT INTO dbo.usuarios(
                            nombre, apellidos, dni, genero, correo, telefono,
                            pais, departamento, provincia, distrito, domicilio,
                            estado, riesgo, fecha_registro
                        )
                        OUTPUT INSERTED.id
                        VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                    ';
                    $userStmt = $pdo_oltp->prepare($sqlUser);

                    $sqlAct = '
                        INSERT INTO dbo.interacciones(
                            usuario_id, tipo, categoria, formato, fecha,
                            dispositivo, sistema_operativo, tiempo_segundos, vistas
                        ) VALUES(?,?,?,?,?,?,?,?,?)
                    ';
                    $actStmt = $pdo_oltp->prepare($sqlAct);

                    $sqlSession = '
                        INSERT INTO dbo.sesiones(usuario_id, fecha, duracion_min, vistas)
                        VALUES(?,?,?,?)
                    ';
                    $sessionStmt = $pdo_oltp->prepare($sqlSession);

                    for ($i = 0; $i < $n; $i++) {
                        $name = $names[array_rand($names)];
                        $surname = $last[array_rand($last)];

                        do {
                            $dni = (string)random_int(10000000, 99999999);
                        } while (isset($dniUsed[$dni]));
                        $dniUsed[$dni] = true;

                        $risk = random_int(1, 5);
                        $email = strtolower($name . '.' . $surname . '.' . bin2hex(random_bytes(3)) . '@nexus.demo');

                        $userStmt->execute([
                            $name, $surname, $dni, $generos[array_rand($generos)],
                            $email, '9' . random_int(10000000, 99999999),
                            'Perú', 'Lima', 'Lima', $district[array_rand($district)],
                            'Dirección ficticia ' . ($i + 1),
                            $risk >= 4 ? 'En revisión' : 'Activa',
                            $risk,
                            date('Y-m-d', time() - random_int(0, 120) * 86400)
                        ]);

                        $uid = $userStmt->fetchColumn();
                        if (!$uid) {
                            throw new RuntimeException('No se pudo recuperar el ID del usuario generado.');
                        }

                        // Cada usuario recibe entre 1 y 4 interacciones ficticias.
                        $numActs = random_int(1, 4);
                        for ($j = 0; $j < $numActs; $j++) {
                            $actStmt->execute([
                                $uid,
                                $types[array_rand($types)],
                                $cats[array_rand($cats)],
                                $formats[array_rand($formats)],
                                date('Y-m-d', time() - random_int(0, 90) * 86400),
                                $devices[array_rand($devices)],
                                $oses[array_rand($oses)],
                                random_int(20, 900),
                                random_int(1, 25)
                            ]);
                        }

                        // Una sesión ficticia por usuario.
                        $sessionStmt->execute([
                            $uid,
                            date('Y-m-d', time() - random_int(0, 90) * 86400),
                            random_int(2, 40),
                            random_int(1, 60)
                        ]);
                    }

                    $pdo_oltp->commit();

                } catch (Throwable $e) {
                    if ($pdo_oltp->inTransaction()) {
                        $pdo_oltp->rollBack();
                    }
                    throw $e;
                }
            }

            /*
            =====================================================
            REDIRECCIÓN DESPUÉS DE POST
            =====================================================
            */

            header('Location: index.php?layer=' . $layer);
            exit;
        }

        /*
        =====================================================
        CARGAR DATOS
        =====================================================
        */

        // Carga selectiva: las capas DW y BI no necesitan traer todas las filas OLTP.
        if ($layer !== 4 && $layer !== 7) {
            $users = $pdo_oltp
                ->query('SELECT * FROM dbo.usuarios ORDER BY id DESC')
                ->fetchAll(PDO::FETCH_ASSOC);

            $acts = $pdo_oltp
                ->query('SELECT i.*,u.nombre,u.apellidos FROM dbo.interacciones i JOIN dbo.usuarios u ON i.usuario_id=u.id ORDER BY i.id DESC')
                ->fetchAll(PDO::FETCH_ASSOC);

            if ($layer === 5) {
                $sessions = $pdo_oltp
                    ->query('SELECT * FROM dbo.sesiones')
                    ->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        $total = count($users);

        $high = count(
            array_filter(
                $users,
                fn($u) => (int)$u['riesgo'] >= 4
            )
        );

        $riskCount = array_count_values(
            array_map(
                fn($u) => (int)$u['riesgo'],
                $users
            )
        );

        $dominant = $total
            ? array_search(max($riskCount), $riskCount)
            : null;

        $duration = count($acts)
            ? array_sum(
                array_column($acts, 'tiempo_segundos')
            ) / count($acts) / 60
            : 0;

        $views = array_sum(
            array_column($acts, 'vistas')
        );

    } catch (Throwable $e) {

        $db_error = $e->getMessage();
    }
}

$title = [
    'Fuentes de datos',
    'Staging Area',
    'Proceso ETL',
    'Data Warehouse',
    'Predicciones IA',
    'Capa semántica · KPI',
    'Power BI'
];
/* ============================================================
   DATA WAREHOUSE: METADATA REAL OLTP Y DW
   ============================================================ */
function nexusInspectDatabase(?PDO $pdo, array $onlyTables=[]): array {
    if (!$pdo) return ['connected'=>false,'tables'=>[],'total_rows'=>0,'error'=>'Sin conexión'];
    $tables=[]; $totalRows=0;
    try {
        $list=$pdo->query("SELECT TABLE_SCHEMA, TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE='BASE TABLE' ORDER BY TABLE_SCHEMA,TABLE_NAME")->fetchAll(PDO::FETCH_ASSOC);
        foreach($list as $item){
            $schema=(string)$item['TABLE_SCHEMA']; $name=(string)$item['TABLE_NAME'];
            if ($onlyTables && !in_array($name, $onlyTables, true)) continue;
            $safeSchema=str_replace(']', ']]', $schema); $safeName=str_replace(']', ']]', $name);
            $full='['.$safeSchema.'].['.$safeName.']';
            $count=0; $countError=null;
            try { $count=(int)$pdo->query("SELECT COUNT_BIG(*) AS total FROM $full")->fetchColumn(); $totalRows+=$count; }
            catch(Throwable $e){$countError='Conteo no disponible';}
            $columns=[];
            $st=$pdo->prepare("SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? ORDER BY ORDINAL_POSITION");
            $st->execute([$schema,$name]);
            foreach($st->fetchAll(PDO::FETCH_ASSOC) as $c){
                $type=$c['DATA_TYPE']; if($c['CHARACTER_MAXIMUM_LENGTH']!==null && in_array(strtolower($type),['varchar','nvarchar','char','nchar','varbinary','binary'])) $type.='('.($c['CHARACTER_MAXIMUM_LENGTH']==-1?'MAX':$c['CHARACTER_MAXIMUM_LENGTH']).')';
                $columns[]=['name'=>$c['COLUMN_NAME'],'type'=>$type,'nullable'=>$c['IS_NULLABLE']];
            }
            $tables[]=['schema'=>$schema,'name'=>$name,'full'=>$schema.'.'.$name,'rows'=>$count,'count_error'=>$countError,'columns'=>$columns];
        }
        return ['connected'=>true,'tables'=>$tables,'total_rows'=>$totalRows,'error'=>null];
    } catch(Throwable $e){ return ['connected'=>true,'tables'=>[],'total_rows'=>0,'error'=>'No se pudo consultar la metadata']; }
}
// Capa 4: mostrar únicamente dimensiones y hechos usados por el modelo analítico.
$dw_used_tables = ['NX_DimDate','NX_DimDevice','NX_DimInteraction','NX_DimLocation','NX_DimUser','NX_FactInteraction','NX_FactSession'];
$empty_db_info = ['connected'=>false,'tables'=>[],'total_rows'=>0,'error'=>null];
$dw_oltp_info = ($layer === 4) ? nexusInspectDatabase($pdo_oltp) : $empty_db_info; // OLTP: todas sus tablas, como en la vista original.
$dw_target_info = ($layer === 4) ? nexusInspectDatabase($pdo_dw, $dw_used_tables) : $empty_db_info;
function nexusMaskDbValue(string $column, $value): string {
    if ($value === null) return 'NULL';
    $name = strtolower($column);
    foreach (['dni','documento','correo','email','telefono','phone','celular','domicilio','direccion','address'] as $needle) {
        if (strpos($name, $needle) !== false) {
            $text = (string)$value;
            if (strlen($text) <= 4) return str_repeat('*', strlen($text));
            return substr($text, 0, 2) . str_repeat('*', max(3, strlen($text)-4)) . substr($text, -2);
        }
    }
    return (string)$value;
}
function nexusFetchTablePreview(?PDO $pdo, array $table, int $limit=10): array {
    if (!$pdo || empty($table['columns'])) return ['rows'=>[], 'error'=>'Vista previa no disponible'];
    try {
        $schema=str_replace(']', ']]', (string)$table['schema']);
        $name=str_replace(']', ']]', (string)$table['name']);
        $qualified='['.$schema.'].['.$name.']';
        $limit=max(1,min(25,$limit));
        $stmt=$pdo->query('SELECT TOP '.$limit.' * FROM '.$qualified);
        return ['rows'=>$stmt->fetchAll(PDO::FETCH_ASSOC), 'error'=>null];
    } catch(Throwable $e) { return ['rows'=>[], 'error'=>'No se pudo cargar la vista previa de registros.']; }
}
function nexusRenderDbPanel(array $info,string $title,string $subtitle,string $icon,?PDO $pdo=null): void {
    ?>
    <section class="dw-panel">
      <header class="dw-panel-head"><div class="dw-icon"><?=esc($icon)?></div><div class="dw-panel-title"><h3><?=esc($title)?></h3><small><?=esc($subtitle)?></small></div><span class="dw-connection <?=!empty($info['connected'])?'online':'offline'?>"><?=!empty($info['connected'])?'Conectado':'Desconectado'?></span></header>
      <div class="dw-summary"><div><small>Tablas</small><strong><?=count($info['tables'])?></strong></div><div><small>Registros totales</small><strong><?=number_format((int)$info['total_rows'])?></strong></div><div><small>Base de datos</small><strong><?=!empty($info['connected'])?'Disponible':'Sin conexión'?></strong></div></div>
      <?php if(!empty($info['error'])): ?><div class="notice dw-error"><?=esc($info['error'])?></div><?php endif ?>
      <div class="dw-table-list">
      <?php if(empty($info['tables'])): ?><div class="tablebox">No se encontraron tablas disponibles.</div><?php endif ?>
      <?php foreach($info['tables'] as $i=>$table): $preview=nexusFetchTablePreview($pdo,$table,10); ?>
        <details class="dw-table" id="dw-<?=esc($title)?>-<?=esc($table['name'])?>"><summary><span class="dw-table-name"><b><?=esc($table['schema'])?>.<?=esc($table['name'])?></b><small><?=count($table['columns'])?> columnas</small></span><span class="dw-table-count"><?=number_format((int)$table['rows'])?> registros</span></summary>
        <div class="dw-table-details">
          <details class="dw-subdetails"><summary>Ver estructura de columnas</summary><div class="tablewrap"><table class="table dw-columns"><thead><tr><th>Columna</th><th>Tipo de dato</th><th>Null</th></tr></thead><tbody><?php foreach($table['columns'] as $col): ?><tr><td><?=esc($col['name'])?></td><td><?=esc($col['type'])?></td><td><?=esc($col['nullable'])?></td></tr><?php endforeach ?></tbody></table></div></details>
          <details class="dw-subdetails"><summary>Ver registros (primeros 10)</summary><?php if($preview['error']): ?><div class="dw-preview-error"><?=esc($preview['error'])?></div><?php elseif(empty($preview['rows'])): ?><div class="dw-preview-empty">Esta tabla no contiene registros.</div><?php else: ?><div class="dw-preview-meta">Vista previa de <?=count($preview['rows'])?> registros · datos reales · campos personales parcialmente ocultos</div><div class="tablewrap dw-records-wrap"><table class="table dw-records"><thead><tr><?php foreach(array_keys($preview['rows'][0]) as $colName): ?><th><?=esc($colName)?></th><?php endforeach ?></tr></thead><tbody><?php foreach($preview['rows'] as $row): ?><tr><?php foreach($row as $colName=>$value): ?><td><?=esc(nexusMaskDbValue((string)$colName,$value))?></td><?php endforeach ?></tr><?php endforeach ?></tbody></table></div><?php endif ?></details>
        </div></details>
      <?php endforeach ?>
      </div>
    </section>
    <?php
}


?>
<!doctype html>
<html lang="es">

<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<title>NEXUS | Red Social Segura</title>

<link rel="stylesheet" href="assets/css/nexus.css">
<link rel="stylesheet" href="assets/css/nexus-dw.css">

</head>

<body>

<?php if (!$pdo_oltp): ?>

<div class="db-alert">
    <h2>Conexión a Azure SQL Server no disponible</h2>
    <p>
        Revisa config/database.php,
        las extensiones PDO_SQLSRV y los permisos del servidor Azure.
    </p>
    <small><?=esc($db_error ?? '')?></small>
</div>

<?php else: ?>

<div class="app">

<aside class="side">

    <div class="brand">
        <img src="assets/nexus_logo_claro.png">
    </div>
    
    <div class="navlabel">ARQUITECTURA BI</div>

    <?php foreach ($title as $i => $t): ?>

    <a class="nav <?=($layer === $i + 1 ? 'active' : '')?>"
       href="?layer=<?=$i+1?>">

        <span><?=str_pad($i+1,2,'0',STR_PAD_LEFT)?></span>
        <?=$t?>

    </a>

    <?php endforeach ?>

    <div class="sidefoot">
        NEXUS BI · XAMPP + Azure SQL Server
    </div>

</aside>

<main class="main">

<header class="top">

    <div>
        <h2>
            <?=str_pad($layer,2,'0',STR_PAD_LEFT)?>
            · <?=$title[$layer-1]?>
        </h2>

        <small>
            Flujo de información NEXUS · Azure SQL · PHP local
        </small>
    </div>

    <span class="status">● AZURE SQL · PHP</span>

</header>

<?php if ($layer === 1): ?>

<h2>Fuentes de datos</h2>

<p class="sub">
    Usuarios e interacciones administrados desde Azure SQL Server.
</p>

<div class="stats">

    <div class="stat">
        <small>Usuarios</small>
        <strong><?=$total?></strong>
        <em>Registros fuente</em>
    </div>

    <div class="stat">
        <small>Interacciones</small>
        <strong><?=count($acts)?></strong>
        <em>Actividad capturada</em>
    </div>

    <div class="stat">
        <small>Sesiones</small>
        <strong><?=count($sessions)?></strong>
        <em>Actividad asociada</em>
    </div>

    <div class="stat">
        <small>Riesgo alto</small>
        <strong><?=$high?></strong>
        <em>Nivel 4–5</em>
    </div>

</div>

<div class="grid2">

    <div class="card">

        <h3>Generación ficticia</h3>

        <p>
            Crear usuarios con interacciones y sesiones de demostración.
        </p>

        <form class="toolbar" method="post">

            <input type="hidden" name="action" value="generate">

            <label>
                Cantidad

                <input
                    type="number"
                    name="cantidad"
                    value="25"
                    min="1"
                    max="5000"
                >
            </label>

            <button class="primary">
                Generar datos
            </button>

        </form>

    </div>

    <div class="card">

        <h3>Registro manual</h3>

        <p>Crear registros individualmente.</p>

        <button class="primary" onclick="openUser()">
            ＋ Registrar usuario
        </button>

        <button class="secondary" onclick="openAct()">
            ＋ Registrar interacción
        </button>

    </div>

</div>

<div class="card">

    <div class="toolbar">

        <h3>Registro de usuarios</h3>

        <input
            id="userSearch"
            placeholder="Buscar usuario, ID o DNI"
            oninput="filterTable('users')"
        >

    </div>

    <div class="tablewrap">

        <table class="table" id="users">

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>DNI</th>
                    <th>Género</th>
                    <th>Ubicación</th>
                    <th>Riesgo</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($users as $u): ?>

                <tr>

                    <td><?=$u['id']?></td>

                    <td>
                        <?=esc($u['nombre'].' '.$u['apellidos'])?>
                    </td>

                    <td><?=esc($u['dni'])?></td>

                    <td><?=esc($u['genero'])?></td>

                    <td>
                        <?=esc($u['distrito'].', '.$u['departamento'])?>
                    </td>

                    <td>
                        <span class="tag"><?=esc($u['riesgo'])?></span>
                    </td>

                    <td><?=esc($u['estado'])?></td>

                    <td>

                        <button
                            class="secondary"
                            onclick='editUser(<?=json_encode(
                                $u,
                                JSON_HEX_TAG|
                                JSON_HEX_APOS|
                                JSON_HEX_QUOT|
                                JSON_HEX_AMP
                            )?>)'
                        >
                            Editar
                        </button>

                        <form
                            class="inline"
                            method="post"
                            onsubmit="return confirm('¿Eliminar usuario y registros relacionados?')"
                        >

                            <input type="hidden" name="action" value="delete_user">
                            <input type="hidden" name="id" value="<?=$u['id']?>">

                            <button class="danger">
                                Eliminar
                            </button>

                        </form>

                    </td>

                </tr>

            <?php endforeach ?>

            </tbody>

        </table>

    </div>

</div>

<div class="card">

    <div class="toolbar">

        <h3>Interacciones</h3>

        <input
            id="actSearch"
            placeholder="Buscar interacción / usuario"
            oninput="filterTable('acts')"
        >

        <button class="primary" onclick="openAct()">
            ＋ Nueva interacción
        </button>

    </div>

    <div class="tablewrap">

        <table class="table" id="acts">

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Usuario</th>
                    <th>Tipo</th>
                    <th>Categoría</th>
                    <th>Fecha</th>
                    <th>Dispositivo</th>
                    <th>Segundos</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($acts as $a): ?>

                <tr>

                    <td><?=$a['id']?></td>

                    <td>
                        <?=esc($a['nombre'].' '.$a['apellidos'])?>
                    </td>

                    <td><?=esc($a['tipo'])?></td>

                    <td><?=esc($a['categoria'])?></td>

                    <td><?=$a['fecha']?></td>

                    <td><?=esc($a['dispositivo'])?></td>

                    <td><?=$a['tiempo_segundos']?></td>

                    <td>

                        <button
                            class="secondary"
                            onclick='editAct(<?=json_encode(
                                $a,
                                JSON_HEX_TAG|
                                JSON_HEX_APOS|
                                JSON_HEX_QUOT|
                                JSON_HEX_AMP
                            )?>)'
                        >
                            Editar
                        </button>

                        <form
                            class="inline"
                            method="post"
                            onsubmit="return confirm('¿Eliminar interacción?')"
                        >

                            <input type="hidden" name="action" value="delete_act">
                            <input type="hidden" name="id" value="<?=$a['id']?>">

                            <button class="danger">
                                Eliminar
                            </button>

                        </form>

                    </td>

                </tr>

            <?php endforeach ?>

            </tbody>

        </table>

    </div>

</div>

<?php elseif ($layer === 2): ?>

<h2>Staging Area</h2>

<p class="sub">
    Área de recepción y validación de datos previa a ETL.
</p>

<?php
$validation = $_SESSION['staging_validation'] ?? null;

$validationState = 'Pendiente';

if ($validation) {

    if (($validation['estado'] ?? '') === 'Error') {
        $validationState = 'Error';
    } elseif (
        ($validation['usuarios_invalidos'] ?? 0) > 0 ||
        (($validation['estado'] ?? '') === 'Observaciones')
    ) {
        $validationState = 'Observaciones';
    } else {
        $validationState = 'Validado';
    }
}
?>

<div class="stats">

    <div class="stat">
        <small>Registros recibidos</small>
        <strong><?=$total+count($acts)?></strong>
    </div>

    <div class="stat">
        <small>Usuarios</small>
        <strong><?=$total?></strong>
    </div>

    <div class="stat">
        <small>Interacciones</small>
        <strong><?=count($acts)?></strong>
    </div>

    <div class="stat">
        <small>Validación</small>
        <strong><?=esc($validationState)?></strong>
    </div>

</div>

<div class="card">

    <h3>Validaciones de datos</h3>

    <div class="cards">

        <div class="card">
            <b>Campos requeridos</b>
            <p>
                <?=($total
                    ? 'Usuarios disponibles para revisar'
                    : 'No hay registros')?>
            </p>
        </div>

        <div class="card">
            <b>DNI duplicados</b>
            <p>
                Se revisan los duplicados en los registros recibidos.
            </p>
        </div>

        <div class="card">
            <b>Relación usuario-interacción</b>
            <p>
                Se verifica que cada interacción tenga un usuario existente.
            </p>
        </div>

    </div>

    <form method="post" class="inline">

        <input
            type="hidden"
            name="action"
            value="validate_staging"
        >

        <button class="primary" type="submit">
            Ejecutar validación
    </button>

    </form>

    <a class="secondary" href="?layer=3">
        Continuar ETL →
    </a>

</div>

<?php if ($validation): ?>

<div class="card">

    <h3>Resultado de la validación</h3>

    <div class="stats">

        <div class="stat">
            <small>Usuarios válidos</small>
            <strong>
                <?=esc($validation['usuarios_validos'] ?? 0)?>
            </strong>
        </div>

        <div class="stat">
            <small>Usuarios inválidos</small>
            <strong>
                <?=esc($validation['usuarios_invalidos'] ?? 0)?>
            </strong>
        </div>

        <div class="stat">
            <small>Usuarios evaluados</small>
            <strong>
                <?=esc($validation['total_usuarios'] ?? 0)?>
            </strong>
        </div>

        <div class="stat">
            <small>Estado</small>
            <strong>
                <?=esc($validation['estado'] ?? 'Desconocido')?>
            </strong>
        </div>

    </div>

    <p class="sub">
        Última validación:
        <?=esc($validation['fecha'] ?? 'No disponible')?>
    </p>

    <?php if (!empty($validation['errores'])): ?>

    <h3>Observaciones detectadas</h3>

    <div class="tablewrap">

        <table class="table">

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Registro</th>
                    <th>DNI</th>
                    <th>Detalle de errores</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($validation['errores'] as $error): ?>

                <tr>

                    <td><?=esc($error['id'] ?? '—')?></td>

                    <td>
                        <?=esc($error['nombre'] ?? 'Registro')?>
                    </td>

                    <td>
                        <?=esc($error['dni'] ?? '—')?>
                    </td>

                    <td>
                        <?=esc(implode(
                            ', ',
                            $error['errores'] ?? []
                        ))?>
                    </td>

                </tr>

            <?php endforeach ?>

            </tbody>

        </table>

    </div>

    <?php else: ?>

        <p>
            No se detectaron observaciones en los registros evaluados.
        </p>

    <?php endif ?>

</div>

<?php endif ?>

<div class="card">

    <h3>Muestra de registros</h3>

    <div class="tablewrap">

        <table class="table">

            <tr>
                <th>Tipo</th>
                <th>ID</th>
                <th>Detalle</th>
            </tr>

            <?php foreach (array_slice($users,0,10) as $u): ?>

            <tr>
                <td>Usuario</td>
                <td><?=$u['id']?></td>
                <td><?=esc($u['nombre'].' '.$u['apellidos'])?></td>
            </tr>

            <?php endforeach ?>

            <?php foreach (array_slice($acts,0,10) as $a): ?>

            <tr>
                <td>Interacción</td>
                <td><?=$a['id']?></td>
                <td><?=esc($a['tipo'].' · '.$a['nombre'])?></td>
            </tr>

            <?php endforeach ?>

        </table>

    </div>

</div>
<?php elseif ($layer === 3): ?>
<h2>Proceso ETL</h2><p class="sub">Extract · Transform · Load. Ejecuta cada etapa independientemente desde el motor Python/Colab.</p>
<div class="stepbar"><span class="step">1 · EXTRACT</span><span class="step">2 · TRANSFORM</span><span class="step">3 · LOAD</span></div>
<div class="etl-grid">
<section class="card etl-card"><div class="etl-head"><span class="etl-number">01</span><span class="etl-state" id="etl-extract-state">Pendiente</span></div><h3>Extract · Extracción</h3><p>Obtiene usuarios, interacciones y sesiones desde NEXUS_OLTP.</p><div class="etl-metrics" id="etl-extract-metrics"><div><small>Usuarios</small><strong>—</strong></div><div><small>Interacciones</small><strong>—</strong></div><div><small>Sesiones</small><strong>—</strong></div></div><button class="primary etl-button" onclick="runETL('extract')">Ejecutar extracción</button><div class="etl-result" id="etl-extract-result">Aún no ejecutado.</div></section>
<section class="card etl-card"><div class="etl-head"><span class="etl-number">02</span><span class="etl-state" id="etl-transform-state">Pendiente</span></div><h3>Transform · Transformación</h3><p>Limpieza, normalización y validación de datos para el modelo analítico.</p><div class="etl-metrics" id="etl-transform-metrics"><div><small>Procesados</small><strong>—</strong></div><div><small>Válidos</small><strong>—</strong></div><div><small>Observaciones</small><strong>—</strong></div></div><button class="primary etl-button" onclick="runETL('transform')">Transformar datos</button><div class="etl-result" id="etl-transform-result">Ejecuta Extract primero.</div></section>
<section class="card etl-card"><div class="etl-head"><span class="etl-number">03</span><span class="etl-state" id="etl-load-state">Pendiente</span></div><h3>Load · Carga</h3><p>Inserta el resultado transformado en el Data Warehouse de NEXUS.</p><div class="etl-metrics" id="etl-load-metrics"><div><small>Cargados</small><strong>—</strong></div><div><small>Destino</small><strong>NEXUS</strong></div><div><small>Proceso</small><strong>ETL</strong></div></div><button class="primary etl-button" onclick="runETL('load')">Cargar Data Warehouse</button><div class="etl-result" id="etl-load-result">Ejecuta Transform primero.</div></section></div>
<div class="card etl-log-card"><h3>Registro de ejecución</h3><div id="etl-log" class="etl-log">Los resultados se mostrarán aquí, sin alertas emergentes.</div><div class="toolbar"><a class="secondary" href="?layer=4">Ver Data Warehouse →</a><button class="secondary" type="button" onclick="clearETLLog()">Limpiar registro</button></div></div>
<div class="notice">Para ejecutar el procesamiento real, configura la URL HTTPS y el token del servicio Colab en config/etl.php. Esta interfaz no simula resultados.</div>
<?php elseif ($layer === 4): ?>
<h2>Data Warehouse · Comparación OLTP y DW</h2>
<p class="sub">Exploración del Data Warehouse. Se muestran únicamente las dimensiones y tablas de hechos utilizadas por el modelo; expande una tabla para revisar sus columnas y registros.</p>
<div class="dw-hero"><div class="dw-flow"><div class="dw-flow-node source"><span>01</span><b>NEXUS OLTP</b><small>Origen transaccional</small></div><div class="dw-flow-arrow"><i>Extract · Transform · Load</i><span>⟶</span></div><div class="dw-flow-node target"><span>02</span><b>NEXUS DW</b><small>Destino analítico</small></div></div></div>
<section class="dw-diagram card">
  <h3>Diagramas del modelo de datos</h3>
  <p class="sub">Modelo relacional de interacciones y sesiones.</p>
  <img src="assets/images/diagramas.png" alt="Diagrama del modelo relacional NEXUS: dimensiones, hechos de interacciones y sesiones" loading="lazy">
</section>
<div class="dwgrid dw-live-grid">
  <?php nexusRenderDbPanel($dw_oltp_info,'NEXUS OLTP','Base de datos transaccional de origen · todas las tablas','OLTP',$pdo_oltp); ?>
  <?php nexusRenderDbPanel($dw_target_info,'NEXUS DW','Dimensiones y hechos utilizados en el modelo analítico','DW',$pdo_dw); ?>
</div>
<div class="notice dw-note"><b>Modelo dimensional Snowflake</b><br>El OLTP conserva la operación de la aplicación. El Data Warehouse organiza la información para análisis mediante dimensiones y tablas de hechos que se alimentan desde el proceso ETL.</div>
<div class="toolbar"><button class="secondary" type="button" onclick="document.querySelectorAll('.dw-table').forEach(e=>e.open=true)">Expandir todas las tablas</button><button class="secondary" type="button" onclick="document.querySelectorAll('.dw-table').forEach(e=>e.open=false)">Contraer todas</button><a class="primary" href="?layer=5">Continuar a Predicciones →</a></div>
<?php elseif ($layer === 5): ?>

<?php
// Predicciones heurísticas basadas en datos actuales de Azure SQL (30 días).
$predictionNow = new DateTimeImmutable('now');
$predictionCutoff = $predictionNow->modify('-30 days');
$userActivity30 = [];
$categoryActivity30 = [];
$recentInteractions30 = 0;
$recentSessions30 = 0;
foreach ($acts as $act) {
    if (empty($act['fecha'])) continue;
    try { $date = new DateTimeImmutable((string)$act['fecha']); } catch (Throwable $e) { continue; }
    if ($date < $predictionCutoff || $date > $predictionNow) continue;
    $uid = (string)($act['usuario_id'] ?? '');
    if ($uid !== '') $userActivity30[$uid] = ($userActivity30[$uid] ?? 0) + 1;
    $category = trim((string)($act['categoria'] ?? 'Sin categoría')) ?: 'Sin categoría';
    $categoryActivity30[$category] = ($categoryActivity30[$category] ?? 0) + 1;
    $recentInteractions30++;
}
foreach ($sessions as $session) {
    if (empty($session['fecha'])) continue;
    try { $date = new DateTimeImmutable((string)$session['fecha']); } catch (Throwable $e) { continue; }
    if ($date >= $predictionCutoff && $date <= $predictionNow) $recentSessions30++;
}
$highRiskPercent30 = $total ? round($high / $total * 100) : 0;
$inactiveUsers30 = max(0, $total - count($userActivity30));
$inactivePercent30 = $total ? round($inactiveUsers30 / $total * 100) : 0;
$lowActivityUsers30 = 0;
foreach ($users as $user) {
    $uid = (string)($user['id'] ?? '');
    if (($userActivity30[$uid] ?? 0) <= 1) $lowActivityUsers30++;
}
$lowActivityPercent30 = $total ? round($lowActivityUsers30 / $total * 100) : 0;
$topCategory30 = $categoryActivity30 ? array_search(max($categoryActivity30), $categoryActivity30) : 'Sin datos recientes';
$topCategoryCount30 = $categoryActivity30 ? max($categoryActivity30) : 0;
$topCategoryPercent30 = $recentInteractions30 ? round($topCategoryCount30 / $recentInteractions30 * 100) : 0;
?>
<h2>Predicciones IA · Horizonte 30 días</h2>
<p class="sub">Indicadores estimados que se actualizan con los registros actuales de Azure SQL. Son cálculos heurísticos, no modelos de IA entrenados.</p>
<div class="cards">
    <div class="card">
        <span class="tag">01 · RIESGO</span>
        <h3>Predicción del nivel de riesgo del usuario</h3>
        <strong class="predvalue"><?=$highRiskPercent30?>% · <?=$high?> usuarios</strong>
        <p>Usuarios clasificados actualmente en riesgo 4–5, identificados como segmento prioritario para revisión durante los próximos 30 días.</p>
        <div class="bar"><span style="width:<?=min(100,max(0,$highRiskPercent30))?>%"></span></div>
        <small class="small"><?=$high?> de <?=$total?> usuarios · Nivel de riesgo 4–5</small>
    </div>
    <div class="card">
        <span class="tag">02 · ACTIVIDAD</span>
        <h3>Predicción de disminución de actividad</h3>
        <strong class="predvalue"><?=$inactivePercent30?>% · <?=$inactiveUsers30?> usuarios</strong>
        <p>Usuarios sin interacciones en los últimos 30 días. Si el patrón continúa, podrían reducir su participación.</p>
        <div class="bar"><span style="width:<?=min(100,max(0,$inactivePercent30))?>%"></span></div>
        <small class="small">Interacciones registradas en el periodo: <?=$recentInteractions30?></small>
    </div>
    <div class="card">
        <span class="tag">03 · CONTENIDO</span>
        <h3>Predicción de interacción con contenidos</h3>
        <strong class="predvalue"><?=esc($topCategory30)?> · <?=$topCategoryPercent30?>%</strong>
        <p>La categoría con más interacciones en los últimos 30 días podría mantener una mayor participación si la tendencia continúa.</p>
        <div class="bar"><span style="width:<?=min(100,max(0,$topCategoryPercent30))?>%"></span></div>
        <small class="small"><?=$topCategoryCount30?> de <?=$recentInteractions30?> interacciones recientes</small>
    </div>
    <div class="card">
        <span class="tag">04 · BAJA ACTIVIDAD</span>
        <h3>Predicción del riesgo de baja actividad</h3>
        <strong class="predvalue"><?=$lowActivityPercent30?>% · <?=$lowActivityUsers30?> usuarios</strong>
        <p>Usuarios con una o ninguna interacción en el último mes, agrupados como segmento que podría necesitar seguimiento de actividad.</p>
        <div class="bar"><span style="width:<?=min(100,max(0,$lowActivityPercent30))?>%"></span></div>
        <small class="small">Criterio: 0–1 interacciones en 30 días</small>
    </div>
    <div class="card">
        <span class="tag">05 · PROYECCIÓN</span>
        <h3>Volumen estimado para los próximos 30 días</h3>
        <strong class="predvalue">≈ <?=$recentInteractions30?> interacciones</strong>
        <p>Proyección de referencia que asume que el ritmo de interacciones del último mes se mantiene durante el próximo mes.</p>
        <div class="bar"><span style="width:<?=($recentInteractions30 > 0 ? 50 : 0)?>%"></span></div>
        <small class="small">Sesiones observadas en 30 días: <?=$recentSessions30?></small>
    </div>
</div>
<div class="notice">Las predicciones se recalculan con los datos actuales al abrir esta capa. Para obtener predicciones de IA propiamente dichas, será necesario entrenar y evaluar modelos con datos históricos en Colab.</div>
<a class="primary" href="?layer=6">Ver KPI →</a>

<?php elseif ($layer === 6): ?>

<h2>Capa semántica · KPI</h2>

<p class="sub">
    Indicadores calculados directamente a partir de Azure SQL Server.
</p>

<div class="stats">

    <div class="stat">
        <small>Usuarios</small>
        <strong><?=$total?></strong>
    </div>

    <div class="stat">
        <small>Riesgo alto (4–5)</small>
        <strong><?=$high?></strong>
    </div>

    <div class="stat">
        <small>Porcentaje riesgo alto</small>
        <strong><?=$total ? round($high/$total*100) : 0?>%</strong>
    </div>

    <div class="stat">
        <small>Duración promedio</small>
        <strong><?=number_format($duration,1)?> min</strong>
    </div>

</div>

<div class="cards">

    <div class="card kpi">
        <h3>Interacciones</h3>
        <strong><?=count($acts)?></strong>
    </div>

    <div class="card kpi">
        <h3>Vistas</h3>
        <strong><?=$views?></strong>
    </div>

    <div class="card kpi">
        <h3>Riesgo predominante</h3>
        <strong>
            <?=$dominant !== null ? 'Nivel '.$dominant : '—'?>
        </strong>
    </div>

    <div class="card kpi">
        <h3>Usuarios en revisión</h3>
        <strong>
            <?=count(
                array_filter(
                    $users,
                    fn($u) => $u['estado'] === 'En revisión'
                )
            )?>
        </strong>
    </div>

    <div class="card kpi">
        <h3>Usuarios suspendidos</h3>
        <strong><?=count(array_filter($users, fn($u) => in_array(strtolower(trim((string)($u['estado'] ?? ''))), ['suspendido','suspendida','suspended','suspension'], true)))?></strong>
    </div>

    <div class="card kpi">
        <h3>Usuarios activos</h3>
        <strong>
            <?=count(
                array_unique(
                    array_column($acts,'usuario_id')
                )
            )?>
        </strong>
    </div>

</div>

<a class="primary" href="?layer=7">
    Ir a Power BI →
</a>

<?php else: ?>

<h2>Visualización en Power BI</h2>

<p class="sub">
    Dashboard estratégico de NEXUS.
</p>

<div class="card center">

    <div class="brand center">

        <div class="mark">▥</div>

        <b>POWER BI</b>

    </div>

    <h2>Dashboard de Red Social Segura</h2>

    <p>
        KPIs, tendencias, sesiones, interacciones
        y segmentación de riesgo.
    </p>

    <a
        class="primary"
        href="https://app.powerbi.com/groups/me/reports/c26fe833-1f41-4e56-aaa3-a2a5a93ba671/f0adf5d481993197158e?experience=power-bi"
        target="_blank"
    >
        Abrir Power BI ↗
    </a>

<a
      class="primary"
      href="https://datastudio.google.com/reporting/c26fa154-8c20-4b1c-b480-9c4d0c2be385"
      target="_blank"
      rel="noopener noreferrer"
      style="margin-left: 10px; background-color: #4B1E78;"
    >
      Abrir Looker Studio ↗
    </a>




    <p class="small">
        Dashboard conectado en vivo a Azure SQL (DirectQuery).
    </p>

</div>

<a class="secondary" href="?layer=1">
    Volver a Fuentes de datos
</a>

<?php endif ?>

<div class="footer">
    NEXUS BI · PHP + Azure SQL Server · Prototipo local en XAMPP
</div>

</main>
</div>

<div class="modal" id="modal">

    <div class="modalbox">

        <div class="modalhead">

            <h3 id="modalTitle">Registro</h3>

            <button class="close" onclick="closeModal()">×</button>

        </div>

        <form method="post" id="modalForm">

            <input
                type="hidden"
                name="action"
                id="formAction"
            >

            <input
                type="hidden"
                name="id"
                id="recordId"
            >

            <div id="modalBody"></div>

            <div class="toolbar">

                <button class="primary">
                    Guardar
                </button>

                <button
                    type="button"
                    class="secondary"
                    onclick="closeModal()"
                >
                    Cancelar
                </button>

            </div>

        </form>

    </div>

</div>

<script>
const USERS = <?=json_encode(
    $users,
    JSON_HEX_TAG|
    JSON_HEX_APOS|
    JSON_HEX_QUOT|
    JSON_HEX_AMP
)?>;

const LAYER = <?=$layer?>;
</script>

<script src="assets/js/nexus.js"></script>

<?php endif ?>

</body>
</html>