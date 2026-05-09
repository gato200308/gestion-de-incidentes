document.addEventListener('DOMContentLoaded', () => {
    // Auth Elements
    const authContainer = document.getElementById('authContainer');
    const dashboardContainer = document.getElementById('dashboardContainer');
    const loginSection = document.getElementById('loginSection');
    const registerSection = document.getElementById('registerSection');
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    
    // User Elements
    const userGreeting = document.getElementById('userGreeting');
    const userRole = document.getElementById('userRole');
    const userCompany = document.getElementById('userCompany');
    const logoutBtn = document.getElementById('logoutBtn');
    const adminBtn = document.getElementById('adminBtn');
    
    const incidentsTable = document.getElementById('incidentsTable');
    
    const allSections = document.querySelectorAll('.view-section');
    const navItems = document.querySelectorAll('.nav-item');
    const currentSectionTitle = document.getElementById('currentSectionTitle');
    
    // Elementos de Incidentes (Restaurados)
    const dashView = document.getElementById('dashView');
    const listView = document.getElementById('listView');
    const searchBar = document.getElementById('searchBar');
    const filterStatus = document.getElementById('filterStatus');
    const filterRisk = document.getElementById('filterRisk');
    const incidentsBody = document.getElementById('incidentsBody');
    const tableEmpty = document.getElementById('tableEmpty');
    const incidentForm = document.getElementById('incidentForm');
    const refreshBtn = document.getElementById('refreshBtn');
    
    window.showSection = (sectionId) => {
        const target = document.getElementById(sectionId);
        if (target) {
            allSections.forEach(sec => sec.classList.remove('active'));
            navItems.forEach(item => item.classList.remove('active'));
            
            target.classList.add('active');
            
            // Actualizar título y estado de navegación
            const btn = Array.from(navItems).find(i => i.getAttribute('onclick')?.includes(sectionId));
            if (btn) {
                btn.classList.add('active');
                if (currentSectionTitle) currentSectionTitle.textContent = btn.innerText.trim();
            }

            // Cargar datos de forma asíncrona pero sin bloquear la UI
            setTimeout(() => {
                try {
                    if (sectionId === 'incidentsView') fetchIncidents();
                    if (sectionId === 'implementationView') loadImplementationList();
                    if (sectionId === 'auditView') loadAuditHistory();
                    if (sectionId === 'trainingView') loadTrainingProgress();
                } catch (e) { console.error("Error en sección:", sectionId, e); }
            }, 50);
        }
    };
    
    // KPI Elements
    const kpiTotal = document.getElementById('kpiTotal');
    const kpiCritical = document.getElementById('kpiCritical');
    const kpiResolvedPercent = document.getElementById('kpiResolvedPercent');
    const kpiAvgTime = document.getElementById('kpiAvgTime');
    const aiInsightText = document.getElementById('aiInsightText');
    const insightTrend = document.getElementById('insightTrend');
    
    // Admin Elements
    const adminContainer = document.getElementById('adminContainer');
    const backToDashBtn = document.getElementById('backToDashBtn');
    const usersBody = document.getElementById('usersBody');
    
    // Modal Elements
    const incidentModal = document.getElementById('incidentModal');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const modalTitle = document.getElementById('modalTitle');
    const modalIdBadge = document.getElementById('modalIdBadge');
    const modalDesc = document.getElementById('modalDesc');
    const modalClass = document.getElementById('modalClass');
    const modalProbImp = document.getElementById('modalProbImp');
    const modalRisk = document.getElementById('modalRisk');
    const modalReporter = document.getElementById('modalReporter');
    const modalMitigation = document.getElementById('modalMitigation');
    const modalStatusSelect = document.getElementById('modalStatusSelect');
    const modalTimeline = document.getElementById('modalTimeline');
    const saveUpdateBtn = document.getElementById('saveUpdateBtn');

    // State
    const API_URL = 'api.php';
    let currentIncidents = [];
    let currentModalIncidentId = null;
    let charts = {}; // Store Chart instances

    // --- UTILS --- //
    const showToast = (message, type = 'success') => {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        const icon = type === 'success' ? '<i class="bi bi-check-circle-fill text-green"></i>' : '<i class="bi bi-exclamation-triangle-fill text-red"></i>';
        toast.innerHTML = `${icon} <span>${message}</span>`;
        
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.animation = 'slideInRight 0.3s ease reverse forwards';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    };

    const toggleView = (showAuth, isLogin = true, showAdmin = false) => {
        if (showAdmin) {
            authContainer.style.display = 'none';
            dashboardContainer.style.display = 'none';
            adminContainer.style.display = 'block';
        } else if (showAuth) {
            authContainer.style.display = 'flex';
            dashboardContainer.style.display = 'none';
            adminContainer.style.display = 'none';
            loginSection.style.display = isLogin ? 'block' : 'none';
            registerSection.style.display = isLogin ? 'none' : 'block';
        } else {
            authContainer.style.display = 'none';
            adminContainer.style.display = 'none';
            dashboardContainer.style.display = 'flex'; // CORRECCIÓN: Usar flex para que el sidebar esté al lado
        }
    };

    // --- AUTH --- //
    const checkSession = async () => {
        try {
            const res = await fetch('auth/check_session.php');
            const data = await res.json();
            if (data.success && data.logged_in) {
                // Sidebar Profile
                const sidebarUsername = document.getElementById('sidebarUsername');
                const sidebarRole = document.getElementById('sidebarRole');
                const userInitial = document.getElementById('userInitial');
                if (sidebarUsername) sidebarUsername.textContent = data.username;
                if (sidebarRole) {
                    const roleMap = {
                        'super_admin': 'Owner / Super Admin',
                        'admin': 'Administrador',
                        'analyst': 'Analista de Riesgos',
                        'capacitador': 'Capacitador ISO',
                        'implementador': 'Implementador SGSI',
                        'auditor': 'Auditor Interno',
                        'user': 'Usuario Consulta'
                    };
                    let roleDisplay = data.role;
                    try {
                        if (typeof data.role === 'string' && data.role.startsWith('[')) {
                            const parsedRoles = JSON.parse(data.role);
                            if (Array.isArray(parsedRoles)) {
                                roleDisplay = parsedRoles.map(r => roleMap[r] || r).join(', ');
                            }
                        } else if (Array.isArray(data.role)) {
                            roleDisplay = data.role.map(r => roleMap[r] || r).join(', ');
                        } else {
                            roleDisplay = roleMap[data.role] || data.role;
                        }
                    } catch(e) {
                         roleDisplay = roleMap[data.role] || data.role;
                    }
                    sidebarRole.textContent = roleDisplay;
                }
                if (userInitial) userInitial.textContent = data.username.charAt(0).toUpperCase();

                if (data.empresa) {
                    const companyBadge = document.getElementById('userCompanyBadge');
                    if (companyBadge) {
                        companyBadge.textContent = `🏢 ${data.empresa}`;
                        companyBadge.style.display = 'inline-block';
                    }
                }

                // Soporte multi-rol: el rol puede ser string simple o JSON array
                let userRoles = [];
                try {
                    userRoles = Array.isArray(data.role) ? data.role
                        : (typeof data.role === 'string' && data.role.startsWith('['))
                            ? JSON.parse(data.role)
                            : [data.role];
                } catch(e) { userRoles = [data.role]; }

                const hasRole = (r) => userRoles.includes(r);
                const isAdminLevel = hasRole('super_admin') || hasRole('admin');

                // Mostrar botón Admin si es admin o super_admin
                if (adminBtn) adminBtn.style.display = isAdminLevel ? 'inline-flex' : 'none';

                // Control de Visibilidad del Menú según Roles (Multi-rol)
                const menuItems = {
                    'menuTraining':       ['super_admin', 'admin', 'capacitador'],
                    'menuImplementation': ['super_admin', 'admin', 'implementador'],
                    'menuAudit':          ['super_admin', 'admin', 'auditor'],
                    'menuIncidents':      ['super_admin', 'admin', 'analyst']
                };

                for (const [id, allowedRoles] of Object.entries(menuItems)) {
                    const item = document.getElementById(id);
                    if (item) {
                        const canSee = userRoles.some(r => allowedRoles.includes(r));
                        item.style.display = canSee ? 'flex' : 'none';
                    }
                }

                try {
                    toggleView(false);
                    // Redirección Inteligente: va al primer módulo disponible según roles
                    if      (hasRole('capacitador'))    showSection('trainingView');
                    else if (hasRole('implementador'))  showSection('implementationView');
                    else if (hasRole('auditor'))        showSection('auditView');
                    else if (hasRole('analyst'))        showSection('incidentsView');
                    else if (isAdminLevel)              showSection('trainingView');
                    else                                showSection('trainingView');
                } catch (e) {
                    console.warn("Error en redirección post-login:", e);
                    if (dashboardContainer) dashboardContainer.style.display = 'flex';
                }
            } else {
                toggleView(true, true);
            }
        } catch (error) {
            toggleView(true, true);
        }
    };

    document.getElementById('showRegister').addEventListener('click', (e) => { e.preventDefault(); toggleView(true, false); });
    document.getElementById('showLogin').addEventListener('click', (e) => { e.preventDefault(); toggleView(true, true); });

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const username = document.getElementById('loginUsername').value;
        const password = document.getElementById('loginPassword').value;
        try {
            const res = await fetch('auth/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password })
            });
            const data = await res.json();
            if (res.ok && data.success) {
                loginForm.reset();
                checkSession();
                showToast(`¡Bienvenido de vuelta, ${data.username}!`);
            } else {
                showToast(data.message || 'Error en login', 'error');
            }
        } catch (error) { showToast('Error de conexión', 'error'); }
    });

    registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const username = document.getElementById('regUsername').value;
        const email = document.getElementById('regEmail').value;
        const password = document.getElementById('regPassword').value;
        const invite_code = document.getElementById('regInviteCode').value;

        try {
            const res = await fetch('auth/register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, email, password, invite_code })
            });
            const data = await res.json();
            if (res.ok && data.success) {
                showToast('Registro exitoso. Iniciando sesión...', 'success');
                registerForm.reset();
                // Auto-login or manually trigger login
                document.getElementById('loginUsername').value = username;
                document.getElementById('loginPassword').value = password;
                loginForm.dispatchEvent(new Event('submit'));
            } else {
                showToast(data.message || 'Error al registrar', 'error');
            }
        } catch (error) { showToast('Error de conexión', 'error'); }
    });

    logoutBtn.addEventListener('click', async () => {
        await fetch('auth/logout.php');
        toggleView(true, true);
    });

    // --- MODULE: INCIDENTS SUB-VIEWS --- //
    window.switchIncidentView = (target) => {
        const dView = document.getElementById('dashView');
        const lView = document.getElementById('listView');
        if (target === 'dash') {
            dView.style.display = 'block';
            lView.style.display = 'none';
            loadDashboardData();
        } else {
            dView.style.display = 'none';
            lView.style.display = 'block';
            fetchIncidents();
        }
    };


    const loadTrainingProgress = async () => {
        try {
            const res = await fetch(`${API_URL}?module=training`);
            const data = await res.json();
            ['check_video','check_policy','check_assets','check_incidents','check_access'].forEach(id => {
                const el = document.getElementById(id);
                if (el && data[id]) el.checked = true;
            });
        } catch (e) {}
        loadTrainingSessions();
    };

    window.saveTrainingProgress = async () => {
        const state = {};
        ['check_video','check_policy','check_assets','check_incidents','check_access'].forEach(id => {
            const el = document.getElementById(id);
            if (el) state[id] = el.checked;
        });
        try {
            const res = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ module: 'training', state, admin_id: document.body.getAttribute('data-user-id') })
            });
            if (res.ok) showToast('Progreso de capacitación guardado', 'success');
        } catch (e) { showToast('Error al guardar', 'error'); }
    };

    const loadTrainingSessions = async () => {
        const log = document.getElementById('trainingSessionLog');
        if (!log) return;
        try {
            const res = await fetch(`${API_URL}?module=training_sessions`);
            const data = await res.json();
            if (!Array.isArray(data) || data.length === 0) {
                log.innerHTML = '<p class="text-muted small text-center">No hay sesiones registradas aún.</p>';
                return;
            }
            log.innerHTML = data.map(s => `
                <div class="timeline-item">
                    <span class="timeline-date">${s.timestamp}</span>
                    <span class="timeline-user"><i class="bi bi-person-video3"></i> ${s.title}</span>
                    <span class="timeline-action">Instructor: ${s.instructor} &bull; Asistentes: ${s.attendees}</span>
                    <span class="timeline-action text-muted">${s.topics}</span>
                </div>
            `).join('');
        } catch (e) { if (log) log.innerHTML = '<p class="text-muted small">Error al cargar.</p>'; }
    };

    const trainingSessionForm = document.getElementById('trainingSessionForm');
    if (trainingSessionForm) {
        trainingSessionForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const title = document.getElementById('sessionTitle').value.trim();
            if (!title) return showToast('Ingresa el título de la sesión', 'error');
            try {
                await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        module: 'training_session',
                        title,
                        instructor: document.getElementById('sessionInstructor').value,
                        attendees: document.getElementById('sessionAttendees').value,
                        topics: document.getElementById('sessionTopics').value,
                        admin_id: document.body.getAttribute('data-user-id')
                    })
                });
                trainingSessionForm.reset();
                showToast('Sesión de capacitación registrada', 'success');
                loadTrainingSessions();
            } catch (e) { showToast('Error al registrar sesión', 'error'); }
        });
    }

    // --- MODULE: IMPLEMENTACIÓN --- //
    const loadImplementationList = async () => {
        const body = document.getElementById('implementationBody');
        body.innerHTML = '<tr><td colspan="4" class="text-center">Cargando controles...</td></tr>';
        const categories = [
            "Documentos Generales", "Control de documentos", "Valoración y tratamiento de riesgos",
            "Concientización y Comunicación", "Auditoría interna", "Acciones correctivas",
            "Políticas de Seguridad", "Organización de la Información", "Recursos humanos",
            "Gestión de activos", "Control de acceso", "Encriptación", "Seguridad física",
            "Seguridad en la operación", "Comunicaciones", "Desarrollo de sistemas",
            "Relación con proveedores", "Gestión de incidentes", "Continuidad del negocio", "Cumplimiento"
        ];
        try {
            const res = await fetch(`${API_URL}?module=implementation`);
            const savedState = await res.json();
            const dates = savedState._dates || {};
            body.innerHTML = categories.map(cat => {
                const status = savedState[cat] || 'Pendiente';
                const date = dates[cat] ? dates[cat].substring(0,10) : '-';
                return `<tr>
                    <td><strong>${cat}</strong></td>
                    <td><span class="status-badge status-${status.toLowerCase().replace(' ','-')}">${status}</span></td>
                    <td>${date}</td>
                    <td><select onchange="updateImplStatus('${cat}', this.value)" class="filter-select">
                        <option value="Pendiente" ${status==='Pendiente'?'selected':''}>Pendiente</option>
                        <option value="En Proceso" ${status==='En Proceso'?'selected':''}>En Proceso</option>
                        <option value="Cumplido" ${status==='Cumplido'?'selected':''}>Cumplido</option>
                    </select></td>
                </tr>`;
            }).join('');
        } catch (e) { showToast('Error cargando implementación', 'error'); }
        loadImplMeetings();
    };

    window.updateImplStatus = async (cat, status) => {
        try {
            await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ module: 'implementation', state: { [cat]: status } })
            });
            showToast(`"${cat}" → ${status}`, 'success');
            loadImplementationList();
        } catch (e) { showToast('Error al actualizar', 'error'); }
    };

    const loadImplMeetings = async () => {
        const log = document.getElementById('implMeetingLog');
        if (!log) return;
        try {
            const res = await fetch(`${API_URL}?module=impl_meetings`);
            const data = await res.json();
            if (!Array.isArray(data) || data.length === 0) {
                log.innerHTML = '<p class="text-muted small text-center">No hay reuniones registradas aún.</p>';
                return;
            }
            log.innerHTML = data.map(m => `
                <div class="timeline-item">
                    <span class="timeline-date">${m.timestamp}</span>
                    <span class="timeline-user"><i class="bi bi-people"></i> ${m.title} &bull; <span class="status-badge status-${m.status.toLowerCase().replace(' ','-')}">${m.status}</span></span>
                    <span class="timeline-action">Responsable: ${m.responsible} &bull; Controles: ${m.controls}</span>
                    <span class="timeline-action text-muted">${m.notes}</span>
                </div>
            `).join('');
        } catch(e) { if(log) log.innerHTML = '<p class="text-muted small">Error al cargar.</p>'; }
    };

    const implMeetingForm = document.getElementById('implMeetingForm');
    if (implMeetingForm) {
        implMeetingForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const title = document.getElementById('implMeetingTitle').value.trim();
            if (!title) return showToast('Ingresa el título de la reunión', 'error');
            try {
                await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        module: 'impl_meeting',
                        title,
                        responsible: document.getElementById('implMeetingResp').value,
                        controls: document.getElementById('implMeetingControls').value,
                        status: document.getElementById('implMeetingStatus').value,
                        notes: document.getElementById('implMeetingNotes').value,
                        admin_id: document.body.getAttribute('data-user-id')
                    })
                });
                implMeetingForm.reset();
                showToast('Reunión de implementación registrada', 'success');
                loadImplMeetings();
            } catch(e) { showToast('Error al registrar reunión', 'error'); }
        });
    }

    // --- MODULE: AUDITORÍA --- //
    const auditMeetingForm = document.getElementById('auditMeetingForm');
    if (auditMeetingForm) {
        auditMeetingForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const type = document.getElementById('auditType').value;
            const topics = document.getElementById('auditTopics').value.trim();
            const findings = document.getElementById('auditFindings')?.value.trim() || '';
            const status = document.getElementById('auditStatus')?.value || 'Planificada';
            const responsible = document.getElementById('auditResponsible')?.value.trim() || '';
            if (!topics) return showToast('Describa los temas tratados', 'error');
            try {
                await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        module: 'audit', type, topics, findings, status, responsible,
                        admin_id: document.body.getAttribute('data-user-id')
                    })
                });
                auditMeetingForm.reset();
                showToast(`Acta de ${type} registrada con éxito.`, 'success');
                loadAuditHistory();
            } catch (e) { showToast('Error al registrar acta', 'error'); }
        });
    }

    const loadAuditHistory = async () => {
        const log = document.getElementById('auditLog');
        if (!log) return;
        log.innerHTML = '<p class="text-muted small text-center">Cargando historial...</p>';
        try {
            const res = await fetch(`${API_URL}?module=audit`);
            const data = await res.json();
            if (!Array.isArray(data) || data.length === 0) {
                log.innerHTML = '<p class="text-muted small text-center">No hay actas registradas aún.</p>';
                return;
            }
            log.innerHTML = data.map(ev => `
                <div class="timeline-item">
                    <span class="timeline-date">${ev.timestamp}</span>
                    <span class="timeline-user"><i class="bi bi-file-earmark-text"></i> Acta de ${ev.type} &bull; <strong>${ev.status || ''}</strong></span>
                    <span class="timeline-action">${ev.topics}</span>
                    ${ev.findings ? `<span class="timeline-action text-muted"><i class="bi bi-exclamation-triangle"></i> Hallazgos: ${ev.findings}</span>` : ''}
                    ${ev.responsible ? `<span class="timeline-action text-muted"><i class="bi bi-person"></i> Auditor: ${ev.responsible}</span>` : ''}
                </div>
            `).join('');
        } catch (e) { if(log) log.innerHTML = '<p class="text-muted small">Error al cargar actas.</p>'; }
    };

    // --- DASHBOARD ANALYTICS --- //
    const loadDashboardData = async () => {
        try {
            const res = await fetch(`${API_URL}?stats=true`);
            const json = await res.json();
            if (json.success && json.data) {
                renderDashboard(json.data);
            }
        } catch (error) {
            showToast('Error cargando analíticas', 'error');
        }
    };

    const renderDashboard = (stats) => {
        const els = {
            'totalIncidents': stats.total,
            'criticalIncidents': stats.kpis.critical_count,
            'avgResolution': stats.kpis.avg_resolution_hours + 'h',
            'resolvedPercent': stats.kpis.resolved_percent + '%'
        };

        for (const [id, val] of Object.entries(els)) {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        }

        // Actualizar barra de progreso de efectividad si existe
        const effBar = document.getElementById('effectivenessBar');
        if (effBar) effBar.style.width = stats.kpis.resolved_percent + '%';

        generateInsights(stats);
        renderCharts(stats);
    };

    const generateInsights = (stats) => {
        let insight = "";
        let trendColor = "text-muted";
        
        if (stats.kpis.critical_count > 0) {
            insight = `⚠️ Atención inmediata: Hay ${stats.kpis.critical_count} incidentes críticos sin resolver. Prioriza la mitigación según ISO 27001.`;
            trendColor = "text-red";
        } else if (stats.kpis.resolved_percent > 80) {
            insight = "✅ Excelente desempeño: La tasa de resolución es óptima. Mantén el monitoreo preventivo.";
            trendColor = "text-green";
        } else if (stats.total === 0) {
            insight = "ℹ️ No hay incidentes activos reportados. El sistema está operando en condiciones normales.";
        } else {
            insight = "📊 Análisis de flujo: Se recomienda revisar los incidentes 'En Proceso' para reducir el tiempo promedio de respuesta.";
        }

        const aiInsightText = document.getElementById('aiInsightText');
        const insightTrend = document.getElementById('insightTrend');
        
        if (aiInsightText) aiInsightText.textContent = insight;
        if (insightTrend) {
            insightTrend.textContent = stats.kpis.resolved_percent > 50 ? "Tendencia: Positiva" : "Tendencia: Revisión Requerida";
            insightTrend.className = `text-muted fw-bold ${trendColor}`;
        }
    };

    const renderCharts = (stats) => {
        const ctxStatus = document.getElementById('statusChart').getContext('2d');
        const ctxClass = document.getElementById('classChart').getContext('2d');
        const ctxTrend = document.getElementById('trendChart').getContext('2d');

        // Destroy existing charts to avoid memory leaks/overlay
        Object.values(charts).forEach(c => c.destroy());

        // Status Chart (Donut)
        charts.status = new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: Object.keys(stats.by_status),
                datasets: [{
                    data: Object.values(stats.by_status),
                    backgroundColor: ['#ef4444', '#f59e0b', '#10b981', '#64748b'],
                    borderWidth: 0
                }]
            },
            options: { cutout: '70%', plugins: { legend: { position: 'bottom' } } }
        });

        // Classification Chart (Bar)
        charts.class = new Chart(ctxClass, {
            type: 'bar',
            data: {
                labels: Object.keys(stats.by_class),
                datasets: [{
                    label: 'Incidentes',
                    data: Object.values(stats.by_class),
                    backgroundColor: '#6366f1',
                    borderRadius: 6
                }]
            },
            options: { 
                indexAxis: 'y', 
                plugins: { legend: { display: false } },
                scales: { x: { grid: { display: false } }, y: { grid: { display: false } } }
            }
        });

        // Trend Chart (Line)
        charts.trend = new Chart(ctxTrend, {
            type: 'line',
            data: {
                labels: Object.keys(stats.by_day).map(d => d.split('-').slice(1).reverse().join('/')),
                datasets: [{
                    label: 'Volumen Diario',
                    data: Object.values(stats.by_day),
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { 
                    y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                    x: { grid: { display: false } }
                }
            }
        });
    };
    const fetchIncidents = async () => {
        try {
            const res = await fetch(API_URL);
            const data = await res.json();
            
            if (data && data.success === false) {
                const errMsg = data.debug_error || data.message || 'Error desconocido';
                throw new Error(errMsg);
            }
            
            allIncidents = Array.isArray(data) ? data : [];
            renderTable();
        } catch (error) {
            console.error("Error Fetch:", error);
            showToast(`API: ${error.message}`, 'error');
            allIncidents = [];
            renderTable();
        }
    };

    const updateDashboard = () => {
        renderTable();
        // Solo cargar analíticas si el contenedor de gráficas existe y es visible
        const dashContainer = document.getElementById('dashView');
        if (dashContainer && dashContainer.style.display !== 'none') {
            loadDashboardData();
        }
    };

    const renderTable = () => {
        const searchTerm = searchBar.value ? searchBar.value.toLowerCase() : '';
        const statusF = filterStatus.value;
        const riskF = filterRisk.value;

        const filtered = allIncidents.filter(inc => {
            const matchesSearch = inc.title.toLowerCase().includes(searchTerm) || inc.id.toLowerCase().includes(searchTerm);
            const matchesStatus = statusF === 'Todos' || inc.status === statusF;
            const matchesRisk = riskF === 'Todos' || inc.risk === riskF;
            return matchesSearch && matchesStatus && matchesRisk;
        });

        incidentsBody.innerHTML = '';
        
        if (filtered.length === 0) {
            incidentsTable.style.display = 'none';
            
            // UX Improvement: Differenciate between "no results" and "no access/tenant"
            const userRoleEl = document.getElementById('sidebarRole');
            const userCompanyEl = document.getElementById('userCompanyBadge');
            
            if (userCompanyEl && userRoleEl && !userCompanyEl.textContent.trim() && !userRoleEl.textContent.includes('Admin')) {
                tableEmpty.innerHTML = `
                    <i class="bi bi-shield-lock text-red" style="font-size: 2.5rem;"></i>
                    <p class="fw-bold text-red mt-2">Acceso Restringido</p>
                    <p class="text-muted small">Tu cuenta aún no tiene una empresa asociada. <br> No puedes visualizar ni reportar incidentes hasta ser asignado.</p>
                `;
                incidentForm.querySelectorAll('input, select, textarea, button').forEach(el => el.disabled = true);
            } else {
                tableEmpty.innerHTML = `
                    <i class="bi bi-inbox text-muted" style="font-size: 2rem;"></i>
                    <p>No se encontraron incidentes para los filtros aplicados.</p>
                `;
                incidentForm.querySelectorAll('input, select, textarea, button').forEach(el => el.disabled = false);
            }
            
            tableEmpty.style.display = 'block';
            return;
        }

        incidentsTable.style.display = 'table';
        tableEmpty.style.display = 'none';

        filtered.forEach(inc => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <strong>${inc.id}</strong><br>
                    <span class="text-indigo fw-bold">${inc.classification}</span> - ${inc.title}
                </td>
                <td><span class="badge risk-${inc.risk.toLowerCase()}">${inc.risk}</span></td>
                <td><span class="status-badge status-${inc.status.toLowerCase()}">${inc.status}</span></td>
                <td>
                    <button class="btn-outline btn-sm action-view" data-id="${inc.id}"><i class="bi bi-eye"></i> Ver Detalle</button>
                </td>
            `;
            tr.querySelector('.action-view').addEventListener('click', () => openModal(inc));
            incidentsBody.appendChild(tr);
        });
    };

    [searchBar, filterStatus, filterRisk].forEach(el => {
        el.addEventListener('input', renderTable);
    });

    // Live Risk Calculation
    const probSelect = document.getElementById('probability');
    const impSelect = document.getElementById('impact');
    const riskEstimation = document.getElementById('riskEstimation');
    const estimatedRiskBadge = document.getElementById('estimatedRiskBadge');

    const updateRiskEstimation = () => {
        const pMap = { 'Baja': 1, 'Media': 2, 'Alta': 3 };
        const iMap = { 'Bajo': 1, 'Medio': 2, 'Alto': 3 };
        const score = pMap[probSelect.value] * iMap[impSelect.value];
        
        let risk = 'Bajo';
        if (score >= 6) risk = 'Alto';
        else if (score >= 3) risk = 'Medio';

        if (estimatedRiskBadge) {
            estimatedRiskBadge.textContent = risk;
            estimatedRiskBadge.className = `badge risk-${risk.toLowerCase()}`;
        }
        if (riskEstimation) riskEstimation.style.display = 'block';
    };

    [probSelect, impSelect].forEach(el => el.addEventListener('change', updateRiskEstimation));
    updateRiskEstimation();

    refreshBtn.addEventListener('click', fetchIncidents);

    // --- INCIDENT CREATION --- //
    incidentForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = incidentForm.querySelector('button');
        btn.disabled = true;
        
        const payload = {
            title: document.getElementById('title').value.trim(),
            description: document.getElementById('description').value.trim(),
            classification: document.getElementById('classification').value,
            probability: document.getElementById('probability').value,
            impact: document.getElementById('impact').value
        };

        try {
            const res = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            
            if (res.status === 401) { checkSession(); return; }
            
            const data = await res.json();
            if (res.ok && data.success) {
                showToast(`¡Incidente reportado con éxito! ID: ${data.data.id}`, 'success');
                incidentForm.reset();
                fetchIncidents();
            } else {
                showToast(data.message || 'Error al guardar', 'error');
            }
        } catch (err) {
            showToast('Fallo de conexión al enviar', 'error');
        } finally {
            btn.disabled = false;
        }
    });

    // --- MODAL & AUDIT TIMELINE --- //
    const openModal = (incident) => {
        currentModalIncidentId = incident.id;
        if (modalTitle) modalTitle.textContent = incident.title;
        if (modalIdBadge) modalIdBadge.textContent = incident.id;
        if (modalDesc) modalDesc.textContent = incident.description;
        if (modalClass) modalClass.textContent = incident.classification;
        if (modalProbImp) modalProbImp.textContent = `${incident.probability} / ${incident.impact}`;
        
        if (modalRisk) modalRisk.innerHTML = `<span class="badge risk-${incident.risk.toLowerCase()}">${incident.risk}</span>`;
        if (modalReporter) modalReporter.textContent = incident.reporter;
        
        modalMitigation.value = incident.mitigation_plan || '';
        modalStatusSelect.value = incident.status;

        // Render Timeline
        modalTimeline.innerHTML = '';
        if (incident.history && incident.history.length > 0) {
            // Mostrar últimos eventos arriba
            [...incident.history].reverse().forEach(ev => {
                const dv = document.createElement('div');
                dv.className = 'timeline-item';
                dv.innerHTML = `
                    <span class="timeline-date"><i class="bi bi-calendar-event"></i> ${ev.timestamp}</span>
                    <span class="timeline-user"><i class="bi bi-person-fill"></i> ${ev.user}</span>
                    <span class="timeline-action">${ev.action}</span>
                `;
                modalTimeline.appendChild(dv);
            });
        }
        
        incidentModal.style.display = 'flex';
    };

    closeModalBtn.addEventListener('click', () => { incidentModal.style.display = 'none'; });
    
    // Close modal on outside click
    incidentModal.addEventListener('click', (e) => {
        if(e.target === incidentModal) incidentModal.style.display = 'none';
    });

    saveUpdateBtn.addEventListener('click', async () => {
        if (!currentModalIncidentId) return;
        const btn = saveUpdateBtn;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = 'Guardando...';
        btn.disabled = true;

        const payload = {
            id: currentModalIncidentId,
            status: modalStatusSelect.value,
            mitigation_plan: modalMitigation.value.trim()
        };

        try {
            const res = await fetch(API_URL, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            if (res.status === 401) { checkSession(); return; }

            const data = await res.json();
            if (res.ok && data.success) {
                showToast('Actualizado correctamente y auditoría grabada', 'success');
                incidentModal.style.display = 'none';
                fetchIncidents();
            } else {
                showToast(data.message || 'Error al actualizar', 'error');
            }
        } catch (err) {
             showToast('Fallo al actualizar', 'error');
        } finally {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }
    });

    // --- ADMIN PANEL --- //
    adminBtn.addEventListener('click', () => {
        toggleView(false, false, true);
        loadAdminUsers();
    });

    backToDashBtn.addEventListener('click', () => {
        toggleView(false);
        fetchIncidents();
    });

    const loadAdminUsers = async () => {
        const myUserId = document.body.getAttribute('data-user-id');
        const myCode = document.body.getAttribute('data-admin-code');
        const userRoleEl = document.getElementById('sidebarRole');
        const isSuper = userRoleEl && (userRoleEl.textContent.includes('Owner') || userRoleEl.textContent.includes('Admin Global'));
        
        // Mostrar código de invitación en el header
        const adminInviteInfo = document.getElementById('adminInviteInfo');
        if (adminInviteInfo) {
            if (myCode && myCode !== 'null') {
                adminInviteInfo.innerHTML = `Su código de invitación: <strong class="text-indigo">${myCode}</strong> <button class="btn-icon btn-sm" onclick="navigator.clipboard.writeText('${myCode}'); showToast('Código copiado', 'success')"><i class="bi bi-clipboard"></i></button>`;
            } else {
                adminInviteInfo.innerHTML = `<span class="text-red small">Sin código asignado. Contacte a soporte.</span>`;
            }
        }
        
        usersBody.innerHTML = '<tr><td colspan="4" class="text-center py-4">Cargando usuarios del sistema...</td></tr>';
        try {
            const res = await fetch('auth/admin_users.php');
            if (!res.ok) throw new Error('Error al obtener lista de usuarios');
            const data = await res.json();
            
            if (data.success) {
                usersBody.innerHTML = '';
                if (data.data.length === 0) {
                    usersBody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No hay otros usuarios registrados en el sistema.</td></tr>';
                    return;
                }

                data.data.forEach(u => {
                    const tr = document.createElement('tr');
                    const isMyReferral = (u.vinculado_a_admin_id == myUserId);
                    const referralBadge = (isMyReferral && !u.empresa) ? '<span class="badge risk-bajo" style="font-size:0.6rem;padding:2px 4px;border:1px dashed var(--green-500)">NUEVO REFERIDO</span>' : '';

                    // Multi-rol: parsear roles actuales del usuario
                    let currentRoles = [];
                    try {
                        currentRoles = Array.isArray(u.role) ? u.role
                            : (typeof u.role === 'string' && u.role.startsWith('['))
                                ? JSON.parse(u.role) : [u.role];
                    } catch(e) { currentRoles = [u.role]; }

                    const isSuperAdminUser = currentRoles.includes('super_admin');
                    const isAdminUser = currentRoles.includes('admin');

                    let roleBlock = '';
                    if (isSuperAdminUser) {
                        roleBlock = `<span class="badge risk-alto">Dueño / Super Admin</span>`;
                    } else if (isAdminUser) {
                        roleBlock = `<span class="badge risk-medio">Administrador</span>`;
                    } else {
                        const modulos = [
                            { key: 'capacitador',   label: 'Capacitador' },
                            { key: 'implementador', label: 'Implementador' },
                            { key: 'auditor',       label: 'Auditor' },
                            { key: 'analyst',       label: 'Incidentes' }
                        ];
                        roleBlock = `<div class="role-checkboxes" data-id="${u.id}">`
                            + modulos.map(m => `
                                <label style="display:flex;align-items:center;gap:4px;font-size:0.78rem;cursor:pointer;">
                                    <input type="checkbox" value="${m.key}" ${currentRoles.includes(m.key)?'checked':''}>
                                    ${m.label}
                                </label>`).join('')
                            + `</div>`;
                    }

                    tr.innerHTML = `
                        <td>
                            <strong>${u.username}</strong> ${referralBadge}
                            <br><small class="text-muted">${u.email}</small>
                        </td>
                        <td>${roleBlock}</td>
                        <td>
                            <input type="text" class="form-control admin-emp-input" data-id="${u.id}" value="${u.empresa || ''}"
                                ${(isSuper || isMyReferral) ? '' : 'disabled'} placeholder="Ej. Empresa SaaS">
                        </td>
                        <td>
                            <button class="btn-primary btn-sm btn-save-user" data-id="${u.id}" ${(!isSuper && !isMyReferral) ? 'disabled' : ''}>
                                <i class="bi bi-save"></i>
                            </button>
                        </td>
                    `;
                    tr.querySelector('.btn-save-user').addEventListener('click', (e) => saveUserAdmin(e.currentTarget));
                    usersBody.appendChild(tr);
                });
            } else {
                showToast(data.message, 'error');
            }
        } catch(e) { 
            console.error(e);
            showToast('Error cargando gestión de usuarios', 'error'); 
        }
    };

    const saveUserAdmin = async (btn) => {
        const id = btn.getAttribute('data-id');
        const empresa = document.querySelector(`.admin-emp-input[data-id="${id}"]`).value;

        // Leer checkboxes multi-rol
        const checkboxContainer = document.querySelector(`.role-checkboxes[data-id="${id}"]`);
        let role;
        if (checkboxContainer) {
            const checked = [...checkboxContainer.querySelectorAll('input[type=checkbox]:checked')].map(c => c.value);
            role = checked.length > 0 ? JSON.stringify(checked) : '[]';
        } else {
            // Es admin o super_admin — no cambiar su rol
            role = null;
        }

        btn.disabled = true;
        btn.innerHTML = '...';

        try {
            const res = await fetch('auth/admin_users.php', {
                method: 'PUT',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id, role, empresa})
            });
            const data = await res.json();
            if (res.ok && data.success) {
                showToast('Usuario actualizado correctamente', 'success');
                
                // Visual Feedback: Highlight row
                const row = btn.closest('tr');
                const originalBg = row.style.backgroundColor;
                row.style.transition = 'background 0.4s ease';
                row.style.backgroundColor = 'rgba(16, 185, 129, 0.15)'; // Light green
                setTimeout(() => {
                    row.style.backgroundColor = originalBg;
                }, 1500);

            } else {
                showToast(data.message, 'error');
            }
        } catch(e) {
            showToast('Error de conexión', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-save"></i> Guardar';
        }
    };

    // Boot
    checkSession();
});
