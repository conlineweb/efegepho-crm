<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reagendar Cita Manual</title>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Reagendar Cita Manual</h4>
                        <small>Permite registrar diferentes zonas horarias para vendedor y cliente</small>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <strong>Nota:</strong> Utilice esta opción cuando el cliente se encuentre en una zona horaria diferente a la del vendedor. 
                            Ambas horas se guardarán y mostrarán en el sistema.
                        </div>

                        <form id="reagendarForm">
                            <!-- ID de la cita (oculto) -->
                            <input type="hidden" id="citaId" name="id">
                            <input type="hidden" id="clienteId" name="cliente">
                            <input type="hidden" id="hasSeller" name="hasSeller" value="1">

                            <!-- Información del Cliente -->
                            <div class="mb-4">
                                <h5>Información de la Cita</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label"><strong>Cliente:</strong></label>
                                        <p id="clienteNombre" class="form-control-plaintext">-</p>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label"><strong>Vendedor:</strong></label>
                                        <select class="form-select" id="vendedor" name="vendedor" required>
                                            <option value="">Seleccione un vendedor</option>
                                            <?php
                                            include_once 'conn.php';
                                            $query = "SELECT id, nombre, apePat, apeMat FROM usuarios WHERE tipoUsu = 1 ORDER BY nombre";
                                            $result = $conn->query($query);
                                            if ($result) {
                                                while ($row = $result->fetch_assoc()) {
                                                    $nombreCompleto = $row['nombre'];
                                                    if ($row['apePat'] && $row['apePat'] != '.') $nombreCompleto .= ' ' . $row['apePat'];
                                                    if ($row['apeMat'] && $row['apeMat'] != '.') $nombreCompleto .= ' ' . $row['apeMat'];
                                                    echo "<option value='{$row['id']}'>$nombreCompleto</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <!-- Sección: Hora del Vendedor -->
                            <div class="mb-4 p-3 border border-primary rounded">
                                <div class="mb-3">
                                    <h5 class="text-primary">Zona Horaria del Vendedor</h5>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="fecha" class="form-label">Fecha (Vendedor) *</label>
                                        <input type="date" class="form-control" id="fecha" name="fecha" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="hora" class="form-label">Hora (Vendedor) *</label>
                                        <input type="time" class="form-control" id="hora" name="hora" required>
                                    </div>
                                </div>
                                <div class="text-muted mt-2" id="sellerTimePreview"></div>
                            </div>

                            <!-- Sección: Hora del Cliente -->
                            <div class="mb-4 p-3 border border-success rounded">
                                <div class="mb-3">
                                    <h5 class="text-success">Zona Horaria del Cliente</h5>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="fecha_cliente" class="form-label">Fecha (Cliente)</label>
                                        <input type="date" class="form-control" id="fecha_cliente" name="fecha_cliente">
                                        <small class="text-muted">Si no se especifica, se usará la misma fecha del vendedor</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="hora_cliente" class="form-label">Hora (Cliente)</label>
                                        <input type="time" class="form-control" id="hora_cliente" name="hora_cliente">
                                        <small class="text-muted">Si no se especifica, se usará la misma hora del vendedor</small>
                                    </div>
                                </div>
                                <div class="text-muted mt-2" id="clientTimePreview"></div>
                            </div>

                            <!-- Diferencia de Zona Horaria -->
                            <div class="alert alert-info" id="timeDifference" style="display:none;">
                                <strong>Diferencia de zona horaria:</strong> <span id="diffText"></span>
                            </div>

                            <!-- Botones -->
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                                    Cancelar
                                </button>
                                <button type="submit" class="btn btn-primary" id="btnSubmit">
                                    Guardar Reagenda
                                </button>
                            </div>
                        </form>

                        <!-- Mensajes -->
                        <div id="mensajeResultado" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    
    <script>
        // Obtener ID de la cita de la URL
        const urlParams = new URLSearchParams(window.location.search);
        const citaId = urlParams.get('id');

        if (!citaId) {
            alert('No se especificó una cita para reagendar');
            window.history.back();
        }

        // Cargar datos de la cita
        $(document).ready(function() {
            $('#citaId').val(citaId);
            cargarDatosCita(citaId);

            // Calcular diferencia de tiempo cuando cambian los inputs
            $('#fecha, #hora, #fecha_cliente, #hora_cliente').on('change', calcularDiferencia);
        });

        function cargarDatosCita(id) {
            $.ajax({
                url: 'consulta-calendario.php',
                type: 'POST',
                data: { id: id },
                success: function(response) {
                    const data = JSON.parse(response);
                    if (data && data.evento && data.cliente) {
                        // Llenar información
                        $('#clienteNombre').text(data.cliente.names || 'Sin nombre');
                        $('#clienteId').val(data.cliente.id);
                        $('#vendedor').val(data.vendedor.id);
                        
                        // Prellenar fechas y horas actuales
                        $('#fecha').val(data.evento.fecha);
                        $('#hora').val(data.evento.hora);
                        
                        if (data.evento.fecha_cliente && data.evento.fecha_cliente != '0000-00-00') {
                            $('#fecha_cliente').val(data.evento.fecha_cliente);
                        }
                        
                        if (data.evento.hora_cliente && data.evento.hora_cliente != '00:00:00') {
                            $('#hora_cliente').val(data.evento.hora_cliente);
                        }
                        
                        calcularDiferencia();
                    }
                },
                error: function() {
                    alert('Error al cargar los datos de la cita');
                }
            });
        }

        function calcularDiferencia() {
            const fechaVendedor = $('#fecha').val();
            const horaVendedor = $('#hora').val();
            const fechaCliente = $('#fecha_cliente').val() || fechaVendedor;
            const horaCliente = $('#hora_cliente').val() || horaVendedor;

            if (fechaVendedor && horaVendedor) {
                const momentVendedor = moment(`${fechaVendedor} ${horaVendedor}`);
                const momentCliente = moment(`${fechaCliente} ${horaCliente}`);

                $('#sellerTimePreview').html(`Vista previa: ${momentVendedor.format('DD/MM/YYYY hh:mm A')}`);
                $('#clientTimePreview').html(`Vista previa: ${momentCliente.format('DD/MM/YYYY hh:mm A')}`);

                const diffHours = momentCliente.diff(momentVendedor, 'hours', true);
                
                if (Math.abs(diffHours) > 0.1) {
                    let diffText = '';
                    if (diffHours > 0) {
                        diffText = `El cliente está ${Math.abs(diffHours).toFixed(1)} horas adelante`;
                    } else {
                        diffText = `El cliente está ${Math.abs(diffHours).toFixed(1)} horas atrás`;
                    }
                    $('#diffText').text(diffText);
                    $('#timeDifference').show();
                } else {
                    $('#timeDifference').hide();
                }
            }
        }

        $('#reagendarForm').on('submit', function(e) {
            e.preventDefault();
            
            const btnSubmit = $('#btnSubmit');
            btnSubmit.prop('disabled', true).html('Guardando...');

            $.ajax({
                url: 'reagendar.php',
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    $('#mensajeResultado').html(`
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <strong>¡Éxito!</strong> La cita ha sido reagendada correctamente.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);
                    
                    setTimeout(function() {
                        window.location.href = 'index.php';
                    }, 2000);
                },
                error: function() {
                    $('#mensajeResultado').html(`
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong>Error:</strong> No se pudo reagendar la cita. Intente nuevamente.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);
                    btnSubmit.prop('disabled', false).html('Guardar Reagenda');
                }
            });
        });
    </script>
</body>
</html>
