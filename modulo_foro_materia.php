<?php
session_start();

$host = 'localhost';
$db   = 'ibbs';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Verificación de sesión
if (!isset($_SESSION['usuario'])) {
    $_SESSION['usuario'] = 'Enmanuel';
    $_SESSION['rol'] = 'superadmin';
}

$materia_id = isset($_GET['materia_id']) ? intval($_GET['materia_id']) : 0;

if ($materia_id === 0) {
    die("Error: No se ha especificado una materia válida.");
}

// Obtener nombre de la materia
$stmt_materia = $pdo->prepare("SELECT nombre FROM materias WHERE id = ?");
$stmt_materia->execute([$materia_id]);
$materia_info = $stmt_materia->fetch(PDO::FETCH_ASSOC);
$nombre_materia = $materia_info ? htmlspecialchars($materia_info['nombre']) : "Materia Desconocida";

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] == 'get_mensajes') {
        $stmt = $pdo->prepare("SELECT * FROM foro_mensajes WHERE materia_id = ? ORDER BY fecha ASC");
        $stmt->execute([$materia_id]);
        $mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($mensajes);
        exit;
    }

    if ($_GET['action'] == 'post_mensaje' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $mensaje = trim($data['mensaje']);
        $respuesta_a = !empty($data['respuesta_a']) ? intval($data['respuesta_a']) : null;

        if (!empty($mensaje)) {
            $stmt = $pdo->prepare("INSERT INTO foro_mensajes (materia_id, usuario_nombre, rol, mensaje, respuesta_a) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$materia_id, $_SESSION['usuario'], $_SESSION['rol'], $mensaje, $respuesta_a]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Mensaje vacío']);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foro - <?php echo $nombre_materia; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-serif-title { font-family: 'Playfair Display', serif; }
        .chat-container::-webkit-scrollbar { width: 6px; }
        .chat-container::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 4px; }
    </style>
</head>
<body class="bg-[#f7f5ef] h-screen flex overflow-hidden text-gray-800">

    <!-- Sidebar estilo IBBS -->
    <aside class="w-64 bg-[#143021] text-emerald-100 flex flex-col shadow-xl z-20 hidden md:flex">
        <div class="p-5 border-b border-emerald-900/50 flex items-center gap-3">
            <div class="bg-emerald-800 p-2 rounded-lg text-white font-bold text-lg">IB</div>
            <div>
                <h1 class="font-bold text-white tracking-wide">IBBS</h1>
                <p class="text-[10px] text-emerald-400 uppercase tracking-widest">Sistema Académico</p>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1 text-sm">
            <a href="modulo_materias.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-emerald-200 hover:bg-emerald-900/60 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span>Inicio</span>
            </a>
            <div class="pt-3 pb-1 px-3 text-[11px] uppercase tracking-wider text-emerald-500 font-semibold">Académico</div>
            <a href="modulo_materias.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-emerald-900 text-white font-medium shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                <span>Materias</span>
            </a>
            <a href="modulo_docentes.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-emerald-200 hover:bg-emerald-900/60 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <span>Docentes</span>
            </a>
            <a href="modulo_alumnos.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-emerald-200 hover:bg-emerald-900/60 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <span>Alumnos</span>
            </a>
        </nav>
        <div class="p-4 border-t border-emerald-900/50 text-xs text-emerald-400 text-center">
            IBBS v2.0 • Sistema
        </div>
    </aside>

    <!-- Contenedor Principal -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden">
        
        <!-- Top Bar -->
        <header class="bg-white border-b border-[#e2ded0] px-6 py-4 flex justify-between items-center shadow-sm">
            <div class="flex items-center gap-4">
                <a href="modulo_materias.php" class="p-2 rounded-lg bg-emerald-50 text-emerald-800 hover:bg-emerald-100 transition" title="Volver a Materias">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
                <div>
                    <h2 class="text-2xl font-serif-title font-bold text-[#143021]">Foro de Dudas</h2>
                    <p class="text-xs text-emerald-700 font-medium">Materia: <span class="font-semibold text-gray-900"><?php echo $nombre_materia; ?></span></p>
                </div>
            </div>
            
            <div class="flex items-center gap-3 bg-[#f7f5ef] border border-[#e2ded0] px-3.5 py-1.5 rounded-full shadow-sm">
                <div class="w-7 h-7 rounded-full bg-emerald-700 text-white flex items-center justify-center font-bold text-xs">
                    <?php echo strtoupper(substr($_SESSION['usuario'], 0, 1)); ?>
                </div>
                <span class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($_SESSION['usuario']); ?></span>
                <span class="text-[10px] bg-[#143021] text-emerald-100 px-2.5 py-0.5 rounded-full uppercase font-bold tracking-wider">
                    <?php echo htmlspecialchars($_SESSION['rol']); ?>
                </span>
            </div>
        </header>

        <!-- Cuerpo del Chat -->
        <main class="flex-1 max-w-5xl w-full mx-auto p-4 md:p-6 flex flex-col overflow-hidden">
            <div class="bg-white border border-[#e2ded0] shadow-md rounded-xl flex-1 flex flex-col overflow-hidden">
                
                <!-- Lista de Mensajes -->
                <div id="chat-box" class="flex-1 overflow-y-auto p-6 space-y-4 chat-container bg-[#fcfbfa]">
                    <div class="h-full flex items-center justify-center">
                        <p class="text-gray-400 font-medium text-sm animate-pulse">Cargando mensajes del foro...</p>
                    </div>
                </div>

                <!-- Indicador de Respuesta en Hilo -->
                <div id="reply-indicator" class="hidden bg-emerald-50 px-6 py-2.5 text-sm flex justify-between items-center border-t border-emerald-100">
                    <span class="text-emerald-900">Respondiendo a: <strong id="reply-to-name" class="font-bold text-emerald-950"></strong></span>
                    <button onclick="cancelReply()" class="text-red-500 hover:text-red-700 font-bold px-2 py-1 rounded hover:bg-red-50 transition">&times; Cancelar</button>
                </div>

                <!-- Formulario de Envío -->
                <div class="p-4 bg-white border-t border-[#e2ded0]">
                    <form id="chat-form" class="flex gap-3 items-end">
                        <input type="hidden" id="respuesta_a" value="">
                        <div class="flex-1 relative">
                            <textarea id="mensaje-input" rows="2" class="w-full border border-gray-300 rounded-xl p-3.5 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:border-emerald-600 resize-none transition text-sm shadow-inner" placeholder="Escribe tu duda o respuesta aquí... (Enter para enviar, Shift+Enter para nueva línea)"></textarea>
                        </div>
                        <button type="submit" class="bg-[#143021] hover:bg-[#1f4a33] text-white font-bold py-3.5 px-7 rounded-xl transition duration-200 shadow-sm flex items-center gap-2 h-full text-sm">
                            <span>Enviar</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                              <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" />
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        const materiaId = <?php echo $materia_id; ?>;
        const currentUser = "<?php echo addslashes($_SESSION['usuario']); ?>";
        const chatBox = document.getElementById('chat-box');
        const chatForm = document.getElementById('chat-form');
        const mensajeInput = document.getElementById('mensaje-input');
        const respuestaAInput = document.getElementById('respuesta_a');
        const replyIndicator = document.getElementById('reply-indicator');
        const replyToName = document.getElementById('reply-to-name');
        
        let lastMessageCount = -1;

        async function fetchMessages() {
            try {
                const response = await fetch(`?action=get_mensajes&materia_id=${materiaId}`);
                const mensajes = await response.json();
                
                if(mensajes.length !== lastMessageCount) {
                    lastMessageCount = mensajes.length;
                    renderMessages(mensajes);
                }
            } catch (error) {
                console.error("Error obteniendo mensajes:", error);
            }
        }

        function renderMessages(mensajes) {
            chatBox.innerHTML = ''; 

            if(mensajes.length === 0) {
                chatBox.innerHTML = '<div class="h-full flex items-center justify-center"><p class="text-gray-400 text-sm">No hay mensajes aún en esta materia. ¡Rompe el hielo!</p></div>';
                return;
            }

            const hilos = {};
            mensajes.forEach(m => {
                if (m.respuesta_a === null) {
                    hilos[m.id] = { ...m, respuestas: [] };
                }
            });
            mensajes.forEach(m => {
                if (m.respuesta_a !== null && hilos[m.respuesta_a]) {
                    hilos[m.respuesta_a].respuestas.push(m);
                }
            });

            for (const id in hilos) {
                const thread = hilos[id];
                chatBox.insertAdjacentHTML('beforeend', createMessageHTML(thread, false));

                if (thread.respuestas.length > 0) {
                    const repliesContainer = document.createElement('div');
                    repliesContainer.className = "ml-6 sm:ml-12 mt-2 pl-4 border-l-2 border-emerald-200 space-y-2.5";
                    thread.respuestas.forEach(r => {
                        repliesContainer.insertAdjacentHTML('beforeend', createMessageHTML(r, true));
                    });
                    chatBox.appendChild(repliesContainer);
                }
            }
            
            chatBox.scrollTop = chatBox.scrollHeight;
        }

        function createMessageHTML(msg, isReply) {
            const isMe = msg.usuario_nombre === currentUser;
            const alignClass = isMe ? 'bg-emerald-50/60 border-emerald-200/80' : 'bg-white border-gray-200';
            
            let roleBadge = '';
            if(msg.rol === 'profesor') {
                roleBadge = '<span class="text-[10px] bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full font-medium ml-2 border border-amber-200">Profesor</span>';
            } else if (msg.rol === 'admin' || msg.rol === 'superadmin') {
                roleBadge = '<span class="text-[10px] bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full font-medium ml-2 border border-emerald-200">Admin</span>';
            }
            
            const replyBtn = !isReply ? `<button onclick="setReply(${msg.id}, '${msg.usuario_nombre}')" class="text-xs text-emerald-700 hover:text-emerald-900 font-medium hover:underline mt-2 flex items-center gap-1 transition"><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.707 3.293a1 1 0 010 1.414L5.414 7H11a7 7 0 017 7v2a1 1 0 11-2 0v-2a5 5 0 00-5-5H5.414l2.293 2.293a1 1 0 11-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg> Responder</button>` : '';

            const dateObj = new Date(msg.fecha);
            const dateStr = dateObj.toLocaleDateString() + ' ' + dateObj.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

            return `
                <div class="p-4 rounded-xl border shadow-xs ${alignClass} hover:shadow-sm transition">
                    <div class="flex justify-between items-center mb-1.5">
                        <strong class="text-gray-900 text-sm flex items-center">${msg.usuario_nombre} ${roleBadge}</strong>
                        <span class="text-xs text-gray-400">${dateStr}</span>
                    </div>
                    <p class="text-gray-700 whitespace-pre-wrap text-sm leading-relaxed">${msg.mensaje}</p>
                    ${replyBtn}
                </div>
            `;
        }

        window.setReply = function(id, nombre) {
            respuestaAInput.value = id;
            replyToName.textContent = nombre;
            replyIndicator.classList.remove('hidden');
            mensajeInput.focus();
        };

        window.cancelReply = function() {
            respuestaAInput.value = '';
            replyIndicator.classList.add('hidden');
        };

        chatForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const mensaje = mensajeInput.value.trim();
            const respuesta_a = respuestaAInput.value;

            if (!mensaje) return;

            mensajeInput.value = '';
            cancelReply();

            try {
                const response = await fetch(`?action=post_mensaje&materia_id=${materiaId}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ mensaje, respuesta_a })
                });
                const result = await response.json();
                
                if(result.success) {
                    fetchMessages(); 
                }
            } catch (error) {
                console.error("Error enviando mensaje:", error);
            }
        });

        fetchMessages();
        setInterval(fetchMessages, 5000);

        mensajeInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                chatForm.dispatchEvent(new Event('submit'));
            }
        });
    </script>
</body>
</html>