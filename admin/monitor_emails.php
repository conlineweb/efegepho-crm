<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor de Envío de Correos</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .controls {
            padding: 20px 30px;
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4); }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 30px;
            background: #f8f9fa;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        
        .stat-card .number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-card.success .number { color: #28a745; }
        .stat-card.error .number { color: #dc3545; }
        .stat-card.pending .number { color: #ffc107; }
        
        .progress-container {
            padding: 20px 30px;
        }
        
        .progress-bar {
            width: 100%;
            height: 30px;
            background: #e9ecef;
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .current-email {
            padding: 20px 30px;
            background: #fff3cd;
            border-left: 5px solid #ffc107;
            margin: 20px 30px;
            border-radius: 8px;
        }
        
        .current-email h3 {
            color: #856404;
            margin-bottom: 10px;
        }
        
        .current-email .details {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 10px;
            font-size: 0.95rem;
        }
        
        .current-email .label {
            font-weight: bold;
            color: #856404;
        }
        
        .log-container {
            padding: 30px;
            max-height: 500px;
            overflow-y: auto;
        }
        
        .log-container h3 {
            margin-bottom: 20px;
            color: #495057;
        }
        
        .log-entry {
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .log-entry.success {
            background: #d4edda;
            border-left: 5px solid #28a745;
        }
        
        .log-entry.error {
            background: #f8d7da;
            border-left: 5px solid #dc3545;
        }
        
        .log-entry .email-info {
            flex: 1;
        }
        
        .log-entry .email-info strong {
            display: block;
            margin-bottom: 5px;
        }
        
        .log-entry .email-info small {
            color: #6c757d;
        }
        
        .log-entry .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .badge.success { background: #28a745; color: white; }
        .badge.error { background: #dc3545; color: white; }
        .badge.dry { background: #17a2b8; color: white; }
        
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-indicator.running { background: #28a745; animation: pulse 1.5s infinite; }
        .status-indicator.stopped { background: #dc3545; }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .next-email {
            padding: 20px 30px;
            background: #d1ecf1;
            border-left: 5px solid #17a2b8;
            margin: 20px 30px;
            border-radius: 8px;
        }
        
        .next-email h3 {
            color: #0c5460;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 Monitor de Envío de Correos</h1>
            <p>Seguimiento en tiempo real del envío automático de emails a leads</p>
        </div>
        
        <div class="controls">
            <button id="startBtn" class="btn btn-primary" onclick="startSending()">
                <span id="startSpinner" class="spinner" style="display: none;"></span>
                Iniciar Envío
            </button>
            <button id="dryRunBtn" class="btn btn-secondary" onclick="startDryRun()">
                Modo Prueba (Dry Run)
            </button>
            <div style="margin-left: auto; display: flex; align-items: center;">
                <span class="status-indicator stopped" id="statusIndicator"></span>
                <span id="statusText">Detenido</span>
            </div>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <h3>Total en Cola</h3>
                <div class="number" id="totalCount">0</div>
            </div>
            <div class="stat-card success">
                <h3>Enviados</h3>
                <div class="number" id="sentCount">0</div>
            </div>
            <div class="stat-card error">
                <h3>Errores</h3>
                <div class="number" id="errorCount">0</div>
            </div>
            <div class="stat-card pending">
                <h3>Pendientes</h3>
                <div class="number" id="remainingCount">0</div>
            </div>
        </div>
        
        <div class="progress-container">
            <div class="progress-bar">
                <div class="progress-fill" id="progressBar" style="width: 0%">
                    <span id="progressText">0%</span>
                </div>
            </div>
        </div>
        
        <div id="currentEmailBox" class="current-email" style="display: none;">
            <h3>📤 Enviando Ahora</h3>
            <div class="details">
                <span class="label">Email:</span>
                <span id="currentEmail">-</span>
                <span class="label">Tipo:</span>
                <span id="currentType">-</span>
                <span class="label">Tabla:</span>
                <span id="currentTable">-</span>
                <span class="label">ID:</span>
                <span id="currentId">-</span>
            </div>
        </div>
        
        <div id="nextEmailBox" class="next-email" style="display: none;">
            <h3>⏭️ Siguiente en Cola</h3>
            <div class="details">
                <span class="label">Email:</span>
                <span id="nextEmail">-</span>
            </div>
        </div>
        
        <div class="log-container">
            <h3>📋 Historial de Envíos</h3>
            <div id="logList"></div>
        </div>
    </div>

    <script>
        let eventSource = null;
        let totalEmails = 0;
        let sentEmails = 0;
        let errorEmails = 0;
        
        function startSending() {
            if (eventSource) {
                alert('Ya hay un proceso en ejecución');
                return;
            }
            startProcess(false);
        }
        
        function startDryRun() {
            if (eventSource) {
                alert('Ya hay un proceso en ejecución');
                return;
            }
            startProcess(true);
        }
        
        function startProcess(dryRun) {
            // Reset stats
            totalEmails = 0;
            sentEmails = 0;
            errorEmails = 0;
            document.getElementById('logList').innerHTML = '';
            
            // Update UI
            document.getElementById('startBtn').disabled = true;
            document.getElementById('dryRunBtn').disabled = true;
            document.getElementById('startSpinner').style.display = 'inline-block';
            document.getElementById('statusIndicator').classList.remove('stopped');
            document.getElementById('statusIndicator').classList.add('running');
            document.getElementById('statusText').textContent = dryRun ? 'Modo Prueba Activo' : 'Enviando...';
            
            // Start SSE connection
            const url = 'cron_send_leads_emails.php?realtime=1' + (dryRun ? '&dry=1' : '');
            eventSource = new EventSource(url);
            
            eventSource.onmessage = function(event) {
                const data = JSON.parse(event.data);
                handleUpdate(data);
            };
            
            eventSource.onerror = function(error) {
                console.error('EventSource error:', error);
                stopProcess();
            };
        }
        
        function handleUpdate(data) {
            switch(data.type) {
                case 'init':
                    totalEmails = data.total;
                    document.getElementById('totalCount').textContent = data.total;
                    document.getElementById('remainingCount').textContent = data.total;
                    if (data.dry) {
                        document.getElementById('statusText').textContent = 'Modo Prueba Activo';
                    }
                    break;
                    
                case 'sending':
                    document.getElementById('currentEmailBox').style.display = 'block';
                    document.getElementById('currentEmail').textContent = data.current.email;
                    const ct = data.current.type === 'primer' ? '1er Correo' : '2do Correo';
                    document.getElementById('currentType').textContent = ct + (data.dry ? ' (DRY)' : '');
                    document.getElementById('currentTable').textContent = data.current.table;
                    document.getElementById('currentId').textContent = data.current.id;
                    
                    // Update stats
                    sentEmails = data.stats.sent;
                    errorEmails = data.stats.errors;
                    document.getElementById('sentCount').textContent = sentEmails;
                    document.getElementById('errorCount').textContent = errorEmails;
                    document.getElementById('remainingCount').textContent = data.stats.remaining;
                    
                    // Update progress
                    // Avoid division by zero when there are no emails in the queue
                    const progress = (totalEmails > 0)
                        ? ((sentEmails + errorEmails) / totalEmails * 100).toFixed(1)
                        : '0';
                    document.getElementById('progressBar').style.width = progress + '%';
                    document.getElementById('progressText').textContent = progress + '%';
                    break;
                    
                case 'success':
                    addLogEntry(data.email, data.emailType, data.table, data.id, true, null, data.info || null, data.dry || false);
                    break;
                    
                case 'error':
                    addLogEntry(data.email, data.emailType, data.table, data.id, false, data.error, data.info || null, data.dry || false);
                    break;
                    
                case 'complete':
                    document.getElementById('currentEmailBox').style.display = 'none';
                    stopProcess();
                    alert(`Proceso completado!\n\nEnviados: ${data.stats.sent}\nErrores: ${data.stats.errors}`);
                    break;
            }
        }
        
        function addLogEntry(email, type, table, id, success, error, info = null, isDry = false) {
            const logList = document.getElementById('logList');
            const entry = document.createElement('div');
            entry.className = 'log-entry ' + (success ? 'success' : 'error');
            
            const typeText = type === 'primer' ? '1er Correo' : '2do Correo';
            
            entry.innerHTML = `
                <div class="email-info">
                    <strong>${email}</strong>
                    <small>${typeText} - Tabla: ${table} - ID: ${id}</small>
                    ${error ? `<small style="color: #dc3545; display: block;">Error: ${error}</small>` : ''}
                    ${info ? `<small style="color: #6c757d; display: block;">${info}</small>` : ''}
                </div>
                <span class="badge ${success ? 'success' : 'error'}">
                    ${success ? '✓ Enviado' : '✗ Error'}
                </span>
                ${isDry ? `<span class="badge dry" style="margin-left:8px; background:#17a2b8;">DRY</span>` : ''}
            `;
            
            logList.insertBefore(entry, logList.firstChild);
            
            // Limit to 50 entries
            if (logList.children.length > 50) {
                logList.removeChild(logList.lastChild);
            }
        }
        
        function stopProcess() {
            if (eventSource) {
                eventSource.close();
                eventSource = null;
            }
            
            document.getElementById('startBtn').disabled = false;
            document.getElementById('dryRunBtn').disabled = false;
            document.getElementById('startSpinner').style.display = 'none';
            document.getElementById('statusIndicator').classList.remove('running');
            document.getElementById('statusIndicator').classList.add('stopped');
            document.getElementById('statusText').textContent = 'Detenido';
        }
    </script>
</body>
</html>