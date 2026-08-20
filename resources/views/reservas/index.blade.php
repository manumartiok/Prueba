<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reservas — Prueba MSI</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f7f7f9; }
        .mesa-card { transition: transform .1s ease; }
        .mesa-card.libre { border-color: #198754; }
        .mesa-card.ocupada { border-color: #dc3545; opacity: .65; }
        .ubicacion-titulo { letter-spacing: .05em; }
    </style>
</head>
<body>
<div class="container py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <h1 class="h3 mb-0">Reservas — Restaurante</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalReserva">
            + Nueva reserva
        </button>
    </div>

    {{-- ===================== BUSCADOR DE DISPONIBILIDAD (consume /api/disponibilidad) ===================== --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label">Fecha</label>
                    <input type="date" id="fechaConsulta" class="form-control">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">Hora</label>
                    <input type="time" id="horaConsulta" class="form-control">
                </div>
                <div class="col-12 col-md-4">
                    <button class="btn btn-outline-primary w-100" onclick="cargarDisponibilidad()">
                        Ver disponibilidad
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="gridMesas"></div>

    <hr class="my-5">

    {{-- ===================== LISTADO PUNTO 4 (consume /api/reservas?fecha=) ===================== --}}
    <h2 class="h4 mb-3">Listado de reservas por fecha (punto 4)</h2>
    <p class="text-muted small">
        Esta sección consume <code>GET /api/reservas?fecha=</code>, que trae TODO
        (reserva + ubicación + mesas concatenadas) en una sola consulta SQL con
        <code>JOIN</code> + <code>GROUP_CONCAT</code>, sin problema N+1.
    </p>

    <div class="row g-3 align-items-end mb-3">
        <div class="col-12 col-md-4">
            <label class="form-label">Fecha a listar</label>
            <input type="date" id="fechaListado" class="form-control">
        </div>
        <div class="col-12 col-md-4">
            <button class="btn btn-outline-secondary w-100" onclick="cargarListado()">
                Buscar reservas
            </button>
        </div>
    </div>

    <div id="listadoReservas"></div>

    {{-- ===================== MODAL DE RESERVA ===================== --}}
    <div class="modal fade" id="modalReserva" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nueva reserva</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="alertaReserva"></div>
                    <form id="formReserva">
                        <div class="mb-3">
                            <label class="form-label">Fecha</label>
                            <input type="date" name="fecha" id="reservaFecha" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Hora</label>
                            <input type="time" name="hora_inicio" id="reservaHora" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cantidad de personas</label>
                            <input type="number" name="cantidad_personas" class="form-control" min="1" max="24" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nombre del cliente</label>
                            <input type="text" name="cliente_nombre" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="cliente_telefono" class="form-control">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="crearReserva()">Confirmar reserva</button>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script>
// La app no tiene login (fuera del alcance), asi que todas las llamadas son directas sin token.
const API_BASE = '/api';

function hoyISO() {
    return new Date().toISOString().slice(0, 10);
}

// Precarga fecha de hoy en todos los inputs de fecha para agilizar las pruebas manuales
document.getElementById('fechaConsulta').value = hoyISO();
document.getElementById('fechaListado').value = hoyISO();
document.getElementById('reservaFecha').value = hoyISO();

async function cargarDisponibilidad() {
    const fecha = document.getElementById('fechaConsulta').value;
    const hora = document.getElementById('horaConsulta').value;
    const grid = document.getElementById('gridMesas');

    if (!fecha || !hora) {
        grid.innerHTML = '<div class="alert alert-warning">Elegí fecha y hora para ver disponibilidad.</div>';
        return;
    }

    grid.innerHTML = '<p class="text-muted">Cargando...</p>';

    const res = await fetch(`${API_BASE}/disponibilidad?fecha=${fecha}&hora_inicio=${hora}`);
    const data = await res.json();

    if (!res.ok) {
        grid.innerHTML = `<div class="alert alert-danger">${data.message ?? 'Error al consultar disponibilidad.'}</div>`;
        return;
    }

    grid.innerHTML = data.ubicaciones.map(ub => `
        <h6 class="ubicacion-titulo text-uppercase text-secondary mt-3">Ubicación ${ub.ubicacion}</h6>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-2 mb-3">
            ${ub.mesas.map(m => `
                <div class="col">
                    <div class="card mesa-card text-center p-2 ${m.libre ? 'libre' : 'ocupada'}">
                        <div class="fw-bold">Mesa ${m.numero}</div>
                        <div class="small text-muted">${m.capacidad}p</div>
                        <span class="badge ${m.libre ? 'bg-success' : 'bg-danger'} mt-1">
                            ${m.libre ? 'Libre' : 'Ocupada'}
                        </span>
                    </div>
                </div>
            `).join('')}
        </div>
    `).join('');
}

async function crearReserva() {
    const form = document.getElementById('formReserva');
    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());
    const alerta = document.getElementById('alertaReserva');

    alerta.innerHTML = '';

    const res = await fetch(`${API_BASE}/reservas`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload),
    });
    const data = await res.json();

    if (!res.ok) {
        // 422 = validacion/horario invalido, 409 = sin disponibilidad
        const detalle = data.errors
            ? Object.values(data.errors).flat().join(' ')
            : data.message;
        alerta.innerHTML = `<div class="alert alert-danger">${detalle}</div>`;
        return;
    }

    alerta.innerHTML = `<div class="alert alert-success">
        Reserva confirmada: Ubicación ${data.reserva.ubicacion}, mesas ${data.reserva.mesas.join(', ')}.
    </div>`;

    form.reset();
    document.getElementById('reservaFecha').value = hoyISO();

    // Refresca el grid y el listado para reflejar la mesa recien ocupada
    cargarDisponibilidad();
    cargarListado();
}

async function cargarListado() {
    const fecha = document.getElementById('fechaListado').value;
    const contenedor = document.getElementById('listadoReservas');

    if (!fecha) return;

    contenedor.innerHTML = '<p class="text-muted">Cargando...</p>';

    const res = await fetch(`${API_BASE}/reservas?fecha=${fecha}`);
    const data = await res.json();

    const grupos = data.reservas_por_ubicacion;
    const nombresUbicacion = Object.keys(grupos ?? {});

    if (nombresUbicacion.length === 0) {
        contenedor.innerHTML = '<div class="alert alert-secondary">No hay reservas para esa fecha.</div>';
        return;
    }

    contenedor.innerHTML = nombresUbicacion.map(nombre => `
        <h6 class="text-secondary text-uppercase mt-3">Ubicación ${nombre}</h6>
        <div class="table-responsive">
            <table class="table table-sm table-striped bg-white">
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Cliente</th>
                        <th>Personas</th>
                        <th>Mesas</th>
                    </tr>
                </thead>
                <tbody>
                    ${grupos[nombre].map(r => `
                        <tr>
                            <td>${r.hora_inicio.slice(0, 5)} - ${r.hora_fin.slice(0, 5)}</td>
                            <td>${r.cliente_nombre ?? '—'}</td>
                            <td>${r.cantidad_personas}</td>
                            <td>${r.mesas}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `).join('');
}

// Carga inicial
cargarDisponibilidad();
cargarListado();
</script>
</body>
</html>
