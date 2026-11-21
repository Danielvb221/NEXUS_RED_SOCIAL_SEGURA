function closeModal() {
    stopBiometricCamera();
    document.getElementById('modal').classList.remove('open');
}

// ============================================================
// GENERADOR DE CAMPOS
// ============================================================

function escapeHTML(value) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    };

    return String(value ?? '').replace(/[&<>"']/g, ch => map[ch] || ch);
}

function field(name, label, value = '', type = 'text', opts = null, full = false) {
    let html = `<div class="field ${full ? 'full' : ''}">
        <label>${escapeHTML(label)}</label>`;

    if (opts) {
        html += `<select name="${escapeHTML(name)}" id="${escapeHTML(name)}">
            ${opts.map(o => `
                <option value="${escapeHTML(o)}"
                    ${String(o) === String(value) ? 'selected' : ''}>
                    ${escapeHTML(o)}
                </option>
            `).join('')}
        </select>`;
    } else {
        html += `<input
            type="${escapeHTML(type)}"
            name="${escapeHTML(name)}"
            id="${escapeHTML(name)}"
            value="${escapeHTML(value)}"
            ${type === 'number' ? 'min="0"' : ''}
        >`;
    }

    return html + '</div>';
}

// ============================================================
// NEXUS - VERIFICACIÓN BIOMÉTRICA SIMULADA
// ============================================================

let biometricStream = null;
let biometricTimer = null;
let biometricScanned = false;
let biometricStarting = false;
let biometricRequestId = 0;

function stopBiometricCamera() {
    biometricRequestId++;

    if (biometricStream) {
        biometricStream.getTracks().forEach(track => {
            track.stop();
        });

        biometricStream = null;
    }

    if (biometricTimer) {
        clearInterval(biometricTimer);
        biometricTimer = null;
    }

    biometricStarting = false;

    const video = document.getElementById('biometricVideo');

    if (video) {
        video.pause();
        video.srcObject = null;
    }

    const preview = document.querySelector('.biometric-preview');

    if (preview) {
        preview.classList.remove('camera-active');
    }

    const cameraStatus = document.getElementById('biometricCameraStatus');

    if (cameraStatus) {
        cameraStatus.textContent = 'Cámara detenida';
        cameraStatus.className = 'camera-status';
    }
}

function resetBiometricUI() {
    biometricScanned = false;

    const progress = document.getElementById('biometricProgress');
    const status = document.getElementById('biometricScanStatus');
    const result = document.getElementById('biometricResult');

    if (progress) {
        progress.style.width = '0%';
    }

    if (status) {
        status.textContent = 'Preparando cámara';
        status.className = 'biometric-status pending';
    }

    if (result) {
        result.textContent = 'Coloca tu rostro dentro del marco para iniciar la verificación.';
    }
}

function updateBiometricStatus(message, className = 'scanning') {
    const status = document.getElementById('biometricScanStatus');

    if (status) {
        status.textContent = message;
        status.className = 'biometric-status ' + className;
    }
}

// ============================================================
// INICIAR CÁMARA
// ============================================================

async function startBiometricCamera() {
    const video = document.getElementById('biometricVideo');
    const cameraStatus = document.getElementById('biometricCameraStatus');
    const result = document.getElementById('biometricResult');
    const preview = document.querySelector('.biometric-preview');

    if (!video || biometricStream || biometricStarting) {
        return;
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        if (cameraStatus) {
            cameraStatus.textContent = 'Navegador incompatible';
            cameraStatus.className = 'camera-status error';
        }

        if (result) {
            result.textContent =
                'Tu navegador no permite acceder a la cámara mediante esta función.';
        }

        updateBiometricStatus('No disponible', 'error');
        return;
    }

    biometricStarting = true;

    const requestId = ++biometricRequestId;

    if (cameraStatus) {
        cameraStatus.textContent = 'Solicitando permiso de cámara...';
        cameraStatus.className = 'camera-status';
    }

    if (result) {
        result.textContent = 'Esperando autorización para utilizar la cámara...';
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: 'user',
                width: { ideal: 640 },
                height: { ideal: 480 }
            },
            audio: false
        });

        // Si el modal se cerró durante la solicitud,
        // detener la cámara inmediatamente.
        if (
            requestId !== biometricRequestId ||
            !document.getElementById('modal')?.classList.contains('open') ||
            !document.getElementById('biometricVideo')
        ) {
            stream.getTracks().forEach(track => track.stop());
            return;
        }

        biometricStream = stream;

        video.srcObject = stream;
        video.muted = true;
        video.autoplay = true;
        video.playsInline = true;

        // Esperar a que el video tenga datos.
        await new Promise((resolve, reject) => {
            if (video.readyState >= 2) {
                resolve();
                return;
            }

            const timeout = setTimeout(() => {
                reject(new Error('La cámara se conectó, pero el video no inició.'));
            }, 10000);

            video.onloadeddata = () => {
                clearTimeout(timeout);
                resolve();
            };

            video.onerror = () => {
                clearTimeout(timeout);
                reject(new Error('No se pudo cargar el video de la cámara.'));
            };
        });

        await video.play();

        if (
            requestId !== biometricRequestId ||
            !document.getElementById('modal')?.classList.contains('open')
        ) {
            stopBiometricCamera();
            return;
        }

        if (preview) {
            preview.classList.add('camera-active');
        }

        if (cameraStatus) {
            cameraStatus.textContent = 'Cámara activa';
            cameraStatus.className = 'camera-status active';
        }

        if (result) {
            result.textContent =
                'Cámara conectada. Preparando verificación visual...';
        }

        resetBiometricUI();

        // La verificación se inicia manualmente con el botón.

        // Detectar si el usuario desconecta físicamente la cámara.
        stream.getVideoTracks().forEach(track => {
            track.addEventListener('ended', () => {
                if (biometricStream === stream) {
                    stopBiometricCamera();

                    if (cameraStatus) {
                        cameraStatus.textContent = 'Cámara desconectada';
                        cameraStatus.className = 'camera-status error';
                    }

                    if (result) {
                        result.textContent =
                            'La cámara se desconectó. Puedes cerrar y volver a abrir el formulario.';
                    }
                }
            });
        });

    } catch (error) {
        if (biometricStream) {
            biometricStream.getTracks().forEach(track => track.stop());
            biometricStream = null;
        }

        if (video) {
            video.pause();
            video.srcObject = null;
        }

        if (preview) {
            preview.classList.remove('camera-active');
        }

        if (cameraStatus) {
            cameraStatus.textContent = 'Cámara no disponible';
            cameraStatus.className = 'camera-status error';
        }

        if (result) {
            if (error.name === 'NotAllowedError') {
                result.textContent =
                    'Permiso de cámara denegado. Permite el acceso a la cámara desde el navegador.';
            } else if (error.name === 'NotFoundError') {
                result.textContent =
                    'No se encontró ninguna cámara conectada.';
            } else if (error.name === 'NotReadableError') {
                result.textContent =
                    'La cámara está siendo utilizada por otra aplicación.';
            } else {
                result.textContent =
                    error.message || 'No se pudo iniciar la cámara.';
            }
        }

        updateBiometricStatus('Cámara no disponible', 'error');

        console.error('Error de cámara NEXUS:', error);

    } finally {
        biometricStarting = false;
    }
}

// ============================================================
// ESCANEO AUTOMÁTICO SIMULADO
// ============================================================

function startAutomaticScan() {
    if (!biometricStream || biometricScanned) {
        return;
    }

    clearInterval(biometricTimer);

    const progress = document.getElementById('biometricProgress');
    const result = document.getElementById('biometricResult');

    let percentage = 0;

    const scanMessages = [
        'Inicializando verificación visual...',
        'Ajustando posición de cámara...',
        'Analizando imagen para la demostración...',
        'Verificando condiciones de iluminación...',
        'Procesando simulación...',
        'Finalizando verificación...'
    ];

    updateBiometricStatus('Iniciando');

    if (result) {
        result.textContent = scanMessages[0];
    }

    biometricTimer = setInterval(() => {
        if (!biometricStream) {
            clearInterval(biometricTimer);
            biometricTimer = null;
            return;
        }

        percentage += 2;

        if (progress) {
            progress.style.width = percentage + '%';
        }

        const messageIndex = Math.min(
            Math.floor(percentage / 20),
            scanMessages.length - 1
        );

        if (result) {
            result.textContent = scanMessages[messageIndex];
        }

        updateBiometricStatus(
            percentage < 100
                ? `Verificando ${percentage}%`
                : 'Completado'
        );

        if (percentage >= 100) {
            clearInterval(biometricTimer);
            biometricTimer = null;

            biometricScanned = true;

            const status = document.getElementById('biometricScanStatus');

            if (status) {
                status.textContent = 'Verificación completada';
                status.className = 'biometric-status success';
            }

            if (result) {
                result.textContent =
                    'Verificación visual simulada completada. No se reconoció ni almacenó información biométrica.';
            }

            const cameraStatus =
                document.getElementById('biometricCameraStatus');

            if (cameraStatus) {
                cameraStatus.textContent =
                    'Cámara activa · Verificación finalizada';
                cameraStatus.className = 'camera-status active';
            }
        }
    }, 100);
}

// ============================================================
// INICIAR VERIFICACIÓN MANUAL
// ============================================================

function startBiometricScan() {
    if (!biometricStream) {
        updateBiometricStatus('Primero inicia la cámara', 'error');

        const result = document.getElementById('biometricResult');

        if (result) {
            result.textContent =
                'Pulsa Iniciar cámara y luego Iniciar verificación.';
        }

        return;
    }

    if (biometricScanned) {
        resetBiometricUI();
    }

    startAutomaticScan();
}

// ============================================================
// COMPONENTE BIOMÉTRICO
// ============================================================

function biometricHTML() {
    return `
        <div class="biometric-box full">

            <div class="biometric-title">
                <div>
                    <h3>Verificación biométrica simulada</h3>
                    <p class="small">
                        Demostración visual con cámara en tiempo real.
                    </p>
                </div>

                <span class="tag">SIMULACIÓN</span>
            </div>

            <div class="camera-preview biometric-preview">

                <video
                    id="biometricVideo"
                    autoplay
                    muted
                    playsinline
                ></video>

                <div class="camera-placeholder" id="biometricPlaceholder">
                    <i>📷</i>
                    <span>Cámara apagada. Pulsa Iniciar cámara.</span>
                </div>

                <div class="biometric-overlay">
                    <div class="biometric-frame">
                        <div class="biometric-scan-line"></div>
                    </div>
                </div>
            </div>

            <div id="biometricCameraStatus" class="camera-status">
                Cámara apagada
            </div>

            <div class="biometric-actions">
                <button
                    type="button"
                    class="btn btn-primary"
                    onclick="startBiometricCamera()"
                >
                    Iniciar cámara
                </button>

                <button
                    type="button"
                    class="btn btn-secondary"
                    onclick="startBiometricScan()"
                >
                    Iniciar verificación
                </button>

                <button
                    type="button"
                    class="btn btn-secondary"
                    onclick="stopBiometricCamera()"
                >
                    Detener cámara
                </button>
            </div>

            <div class="biometric-scan-info">

                <div class="biometric-scan-header">
                    <strong>Estado de verificación</strong>

                    <span
                        id="biometricScanStatus"
                        class="biometric-status pending"
                    >
                        Preparando cámara
                    </span>
                </div>

                <div class="biometric-progress-container">
                    <div
                        id="biometricProgress"
                        class="biometric-progress"
                    ></div>
                </div>

                <div id="biometricResult" class="biometric-result">
                    Esperando conexión de cámara...
                </div>
            </div>

            <div class="notice">
                Esta demostración utiliza la cámara únicamente como elemento visual.
                No realiza reconocimiento facial real ni almacena información biométrica.
            </div>
        </div>
    `;
}

// ============================================================
// REGISTRAR / EDITAR USUARIO
// ============================================================

function openUser(data = {}) {
    stopBiometricCamera();

    document.getElementById('modalTitle').textContent =
        data.id ? 'Editar usuario' : 'Registrar usuario';

    document.getElementById('formAction').value = 'save_user';
    document.getElementById('recordId').value = data.id || '';

    let b = '';

    b += field('nombre', 'Nombre', data.nombre);
    b += field('apellidos', 'Apellidos', data.apellidos);
    b += field('dni', 'DNI ficticio', data.dni);

    b += field(
        'genero',
        'Género',
        data.genero || 'Prefiere no indicar',
        'text',
        ['Femenino', 'Masculino', 'Otro', 'Prefiere no indicar']
    );

    b += field(
        'fecha_nacimiento',
        'Fecha de nacimiento',
        data.fecha_nacimiento,
        'date'
    );

    b += field('correo', 'Correo', data.correo, 'email');
    b += field('telefono', 'Teléfono', data.telefono);

    b += field('pais', 'País', data.pais || 'Perú');
    b += field('departamento', 'Departamento', data.departamento || 'Lima');
    b += field('provincia', 'Provincia', data.provincia || 'Lima');
    b += field('distrito', 'Distrito', data.distrito);

    b += field(
        'domicilio',
        'Domicilio',
        data.domicilio,
        'text',
        null,
        true
    );

    b += field(
        'estado',
        'Estado',
        data.estado || 'Activa',
        'text',
        ['Activa', 'En revisión', 'Suspendida']
    );

    b += field(
        'riesgo',
        'Riesgo (1–5)',
        data.riesgo || 1,
        'text',
        ['1', '2', '3', '4', '5']
    );

    document.getElementById('modalBody').innerHTML = `
        <div class="formgrid">
            ${b}
            ${biometricHTML()}
        </div>
    `;

    document.getElementById('modal').classList.add('open');

    // Cámara y verificación se activan manualmente mediante botones.
}

// ============================================================
// INTERACCIONES
// ============================================================

function openAct(data = {}) {
    if (!USERS.length) {
        alert('Primero registra usuarios.');
        return;
    }

    stopBiometricCamera();

    document.getElementById('modalTitle').textContent =
        data.id ? 'Editar interacción' : 'Registrar interacción';

    document.getElementById('formAction').value = 'save_act';
    document.getElementById('recordId').value = data.id || '';

    let options = USERS.map(u => `
        <option value="${u.id}"
            ${String(u.id) === String(data.usuario_id) ? 'selected' : ''}>
            ${u.id} · ${u.nombre} ${u.apellidos}
        </option>
    `).join('');

    let b = `
        <div class="formgrid">
            <div class="field full">
                <label>Usuario</label>
                <select name="usuario_id">${options}</select>
            </div>
    `;

    b += field(
        'tipo',
        'Tipo',
        data.tipo || 'Me gusta',
        'text',
        ['Me gusta', 'Comentario', 'Compartir', 'Guardar', 'Reportar']
    );

    b += field(
        'categoria',
        'Categoría',
        data.categoria || 'Tecnología',
        'text',
        [
            'Tecnología',
            'Ciberseguridad',
            'Educación',
            'Noticias',
            'Entretenimiento',
            'Deportes'
        ]
    );

    b += field(
        'formato',
        'Formato',
        data.formato || 'Texto',
        'text',
        ['Texto', 'Imagen', 'Video']
    );

    b += field(
        'fecha',
        'Fecha',
        data.fecha || new Date().toISOString().slice(0, 10),
        'date'
    );

    b += field(
        'dispositivo',
        'Dispositivo',
        data.dispositivo || 'Móvil',
        'text',
        ['Móvil', 'Desktop', 'Tablet']
    );

    b += field(
        'sistema_operativo',
        'Sistema operativo',
        data.sistema_operativo || 'Android',
        'text',
        ['Android', 'iOS', 'Windows', 'macOS', 'Linux']
    );

    b += field(
        'tiempo_segundos',
        'Tiempo (segundos)',
        data.tiempo_segundos || 90,
        'number'
    );

    b += field(
        'vistas',
        'Vistas',
        data.vistas || 1,
        'number'
    );

    b += '</div>';

    document.getElementById('modalBody').innerHTML = b;
    document.getElementById('modal').classList.add('open');
}

function editUser(u) {
    openUser(u);
}

function editAct(a) {
    openAct(a);
}

// ============================================================
// FILTRADO
// ============================================================

function filterTable(id) {
    const searchElement = document.getElementById(
        id === 'users' ? 'userSearch' : 'actSearch'
    );

    if (!searchElement) return;

    const input = searchElement.value.toLowerCase();

    document.querySelectorAll(`#${id} tbody tr`).forEach(tr => {
        tr.style.display = tr.innerText.toLowerCase().includes(input)
            ? ''
            : 'none';
    });
}

// ============================================================
// NEXUS - MOTOR VISUAL ETL
// ============================================================

const ETL_API = 'api/etl.php';

function etlLog(msg) {
    const box = document.getElementById('etl-log');

    if (!box) return;

    const row = document.createElement('div');
    row.className = 'etl-log-entry';
    row.textContent = '[' + new Date().toLocaleString() + '] ' + msg;

    box.prepend(row);
}

function clearETLLog() {
    const el = document.getElementById('etl-log');

    if (el) {
        el.textContent = 'Registro limpiado.';
    }
}

function etlState(stage, cls, label) {
    const el = document.getElementById('etl-' + stage + '-state');

    if (!el) return;

    el.className = 'etl-state ' + cls;
    el.textContent = label;
}

function etlMetrics(stage, items) {
    const el = document.getElementById('etl-' + stage + '-metrics');

    if (!el || !Array.isArray(items)) return;

    el.innerHTML = items.map(m => `
        <div>
            <small>${String(m.label ?? '')}</small>
            <strong>${String(m.value ?? '—')}</strong>
        </div>
    `).join('');
}

function getResult(data) {
    return data?.result
        ?? data?.results
        ?? data?.data
        ?? data;
}

function unwrapResult(data, stage) {
    let result = data;

    for (let i = 0; i < 6; i++) {
        if (!result || typeof result !== 'object') break;

        if (stage === 'load' && result.load) {
            result = result.load;
            continue;
        }

        if (result.result) {
            result = result.result;
            continue;
        }

        if (result.results) {
            result = result.results;
            continue;
        }

        if (result.data) {
            result = result.data;
            continue;
        }

        break;
    }

    return result || {};
}

// ============================================================
// MÉTRICAS ETL
// ============================================================

function getMetrics(stage, data) {
    const result = unwrapResult(data, stage);

    if (stage === 'extract') {
        return [
            {
                label: 'Usuarios',
                value: result.users ?? result.usuarios ?? '—'
            },
            {
                label: 'Interacciones',
                value: result.interactions ?? result.interacciones ?? '—'
            },
            {
                label: 'Sesiones',
                value: result.sessions ?? result.sesiones ?? '—'
            }
        ];
    }

    if (stage === 'transform') {
        return [
            {
                label: 'Procesados',
                value: result.processed
                    ?? result.procesados
                    ?? result.total_processed
                    ?? result.users
                    ?? '—'
            },
            {
                label: 'Válidos',
                value: result.valid
                    ?? result.validos
                    ?? result.valid_records
                    ?? '—'
            },
            {
                label: 'Observaciones',
                value: result.observations
                    ?? result.observaciones
                    ?? result.invalid
                    ?? '—'
            }
        ];
    }

    if (stage === 'load') {
        const usersInserted = Number(
            result.users_inserted
            ?? result.usuarios_insertados
            ?? 0
        );

        const usersUpdated = Number(
            result.users_updated
            ?? result.usuarios_actualizados
            ?? 0
        );

        const interactionsInserted = Number(
            result.interactions_inserted
            ?? result.interacciones_insertadas
            ?? 0
        );

        const sessionsInserted = Number(
            result.sessions_inserted
            ?? result.sesiones_insertadas
            ?? 0
        );

        const total =
            usersInserted +
            usersUpdated +
            interactionsInserted +
            sessionsInserted;

        return [
            {
                label: 'Registros procesados',
                value: total.toLocaleString('es-PE')
            },
            {
                label: 'Usuarios nuevos / actualizados',
                value: `${usersInserted} / ${usersUpdated}`
            },
            {
                label: 'Interacciones / sesiones',
                value: `${interactionsInserted} / ${sessionsInserted}`
            }
        ];
    }

    return [];
}

function getETLMessage(stage, data) {
    const result = unwrapResult(data, stage);

    if (data?.message) {
        return data.message;
    }

    if (stage === 'extract') {
        return `Extracción completada. Usuarios: ${result.users ?? '—'}, interacciones: ${result.interactions ?? '—'}, sesiones: ${result.sessions ?? '—'}.`;
    }

    if (stage === 'transform') {
        return `Transformación completada. Procesados: ${result.processed ?? result.procesados ?? '—'}, válidos: ${result.valid ?? result.validos ?? '—'}, observaciones: ${result.observations ?? result.observaciones ?? '—'}.`;
    }

    if (stage === 'load') {
        return 'Carga completada correctamente en el Data Warehouse.';
    }

    return 'Etapa completada.';
}

// ============================================================
// EJECUTAR ETL
// ============================================================

async function runETL(stage) {

    // TRANSFORM requiere EXTRACT completado.
    if (
        stage === 'transform' &&
        document.getElementById('etl-extract-state')?.textContent !== 'Completado'
    ) {
        etlLog('Transform requiere Extract completado.');
        return;
    }

    // LOAD requiere TRANSFORM completado.
    if (
        stage === 'load' &&
        document.getElementById('etl-transform-state')?.textContent !== 'Completado'
    ) {
        etlLog('Load requiere Transform completado.');
        return;
    }

    const btn = document.querySelector(
        `button[onclick="runETL('${stage}')"]`
    );

    const box = document.getElementById('etl-' + stage + '-result');

    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Ejecutando…';
    }

    etlState(stage, 'running', 'Ejecutando');

    if (box) {
        box.className = 'etl-result';
        box.textContent = 'Procesando datos…';
    }

    etlLog(stage.toUpperCase() + ': iniciado');

    try {
        const controller = new AbortController();

        const timeoutId = setTimeout(() => {
            controller.abort();
        }, 120000);

        let response;
        let data;

        try {
            response = await fetch(ETL_API, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ stage }),
                signal: controller.signal
            });

            const raw = await response.text();

            try {
                data = raw ? JSON.parse(raw) : {};
            } catch (_) {
                throw new Error(
                    `La API ETL devolvió una respuesta no JSON (HTTP ${response.status}). ${raw.slice(0, 300)}`
                );
            }

        } finally {
            clearTimeout(timeoutId);
        }

        if (
            !response.ok ||
            data?.ok === false ||
            data?.success === false
        ) {
            throw new Error(
                data?.message
                || data?.error
                || `La etapa ETL falló (HTTP ${response.status}).`
            );
        }

        etlState(stage, 'success', 'Completado');

        if (box) {
            box.className = 'etl-result success';
            box.textContent = getETLMessage(stage, data);
        }

        etlMetrics(stage, getMetrics(stage, data));

        etlLog(stage.toUpperCase() + ': completado');

        // Si Extract se vuelve a ejecutar,
        // reiniciar los estados posteriores.
        if (stage === 'extract') {
            ['transform', 'load'].forEach(s => {
                etlState(s, '', 'Pendiente');
            });

            etlMetrics('transform', [
                {
                    label: 'Procesados',
                    value: '—'
                },
                {
                    label: 'Válidos',
                    value: '—'
                },
                {
                    label: 'Observaciones',
                    value: '—'
                }
            ]);

            etlMetrics('load', [
                {
                    label: 'Registros procesados',
                    value: '—'
                },
                {
                    label: 'Usuarios nuevos / actualizados',
                    value: '—'
                },
                {
                    label: 'Interacciones / sesiones',
                    value: '—'
                }
            ]);

            const transformResult =
                document.getElementById('etl-transform-result');

            const loadResult =
                document.getElementById('etl-load-result');

            if (transformResult) {
                transformResult.textContent =
                    'Ejecuta Transform después de Extract.';
            }

            if (loadResult) {
                loadResult.textContent =
                    'Ejecuta Load después de Transform.';
            }
        }

        // Si Transform se vuelve a ejecutar,
        // reiniciar el estado de Load.
        if (stage === 'transform') {
            etlState('load', '', 'Pendiente');

            etlMetrics('load', [
                {
                    label: 'Registros procesados',
                    value: '—'
                },
                {
                    label: 'Usuarios nuevos / actualizados',
                    value: '—'
                },
                {
                    label: 'Interacciones / sesiones',
                    value: '—'
                }
            ]);

            const loadResult =
                document.getElementById('etl-load-result');

            if (loadResult) {
                loadResult.textContent =
                    'Ejecuta Load después de Transform.';
            }
        }

    } catch (error) {
        const message =
            error?.name === 'AbortError'
                ? 'La operación ETL excedió los 120 segundos de espera.'
                : (error?.message || 'Error desconocido');

        etlState(stage, 'error', 'Error');

        if (box) {
            box.className = 'etl-result error';
            box.textContent = message;
        }

        etlLog(stage.toUpperCase() + ': ERROR — ' + message);

    } finally {
        if (btn) {
            btn.disabled = false;

            btn.textContent =
                stage === 'extract'
                    ? 'Ejecutar extracción'
                    : stage === 'transform'
                    ? 'Transformar datos'
                    : 'Cargar Data Warehouse';
        }
    }
}

// ============================================================
// EXPONER FUNCIONES
// ============================================================

window.runETL = runETL;
window.clearETLLog = clearETLLog;

window.openUser = openUser;
window.editUser = editUser;

window.openAct = openAct;
window.editAct = editAct;

window.closeModal = closeModal;
window.stopBiometricCamera = stopBiometricCamera;
window.startBiometricCamera = startBiometricCamera;
window.startBiometricScan = startBiometricScan;