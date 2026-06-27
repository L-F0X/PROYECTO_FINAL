import React, { useState, useEffect } from "react";
import { 
  Users, 
  TrendingUp, 
  ShieldAlert, 
  HelpCircle, 
  Building2, 
  ClipboardCheck, 
  BookOpen, 
  Layers,
  ArrowRight,
  Info,
  RefreshCw,
  Clock,
  Briefcase,
  LogOut,
  LogIn,
  Lock,
  Mail,
  UserCheck,
  CheckCircle2,
  Database
} from "lucide-react";

// Types
import { 
  RolNombre, 
  Usuario, 
  LoteRequerimiento, 
  MatrizItem, 
  Necesidad, 
  CodigoUnspsc, 
  Proveedor, 
  CertificadoExistencia, 
  Cotizacion, 
  IvaCotizacion, 
  FichaTecnica 
} from "./types";

// Mock Data & Storage helpers
import { 
  mockRoles,
  mockUsuarios, 
  mockLotes, 
  mockMatrizItems, 
  mockNecesidades, 
  mockCodigosUnspsc, 
  mockProveedores, 
  mockCertificados, 
  mockCotizaciones, 
  mockIvas, 
  mockFichasTecnicas,
  loadFromStorage, 
  saveToStorage,
  clearAllStorage
} from "./mockData";

// Components
import InstructorDashboard from "./components/InstructorDashboard";
import CoordinatorDashboard from "./components/CoordinatorDashboard";
import StorekeeperDashboard from "./components/StorekeeperDashboard";
import SupplierDashboard from "./components/SupplierDashboard";
import DatabaseExplorer from "./components/DatabaseExplorer";

export default function App() {
  // Core App States (Synced with Storage)
  const [roles, setRoles] = useState<{ ID_ROL: number; NOMBRE_ROL: RolNombre }[]>(() => loadFromStorage("sena_roles", mockRoles));
  const [usuarios, setUsuarios] = useState<Usuario[]>(() => loadFromStorage("sena_usuarios", mockUsuarios));
  const [proveedores, setProveedores] = useState<Proveedor[]>(() => loadFromStorage("sena_proveedores", mockProveedores));
  const [lotes, setLotes] = useState<LoteRequerimiento[]>(() => loadFromStorage("sena_lotes", mockLotes));
  const [matrizItems, setMatrizItems] = useState<MatrizItem[]>(() => loadFromStorage("sena_matriz_items", mockMatrizItems));
  const [necesidades, setNecesidades] = useState<Necesidad[]>(() => loadFromStorage("sena_necesidades", mockNecesidades));
  const [codigosUnspsc, setCodigosUnspsc] = useState<CodigoUnspsc[]>(() => loadFromStorage("sena_codigos_unspsc", mockCodigosUnspsc));
  const [certificados, setCertificados] = useState<CertificadoExistencia[]>(() => loadFromStorage("sena_certificados", mockCertificados));
  const [cotizaciones, setCotizaciones] = useState<Cotizacion[]>(() => loadFromStorage("sena_cotizaciones", mockCotizaciones));
  const [ivas, setIvas] = useState<IvaCotizacion[]>(() => loadFromStorage("sena_ivas", mockIvas));
  const [fichasTecnicas, setFichasTecnicas] = useState<FichaTecnica[]>(() => loadFromStorage("sena_fichas_tecnicas", mockFichasTecnicas));
  const [auditLogs, setAuditLogs] = useState<any[]>(() => loadFromStorage("sena_audit_logs", [
    { id: 1, usuario: "Carlos Gómez", accion: "CREAR LOTE", tabla: "LOTE_REQUERIMIENTO", detalle: "Creado Lote de Gastronomía", fecha: "2026-06-15 10:15:22" },
    { id: 2, usuario: "Esperanza Castro", accion: "APROBAR LOTE", tabla: "LOTE_REQUERIMIENTO", detalle: "Lote de EPP aprobado con comentarios", fecha: "2026-06-22 15:44:10" },
    { id: 3, usuario: "Humberto López", accion: "EXPEDIR CERTIFICADO", tabla: "CERTIFICADO_EXISTENCIA", detalle: "Expedido certificado CNE-2026-0089", fecha: "2026-06-24 14:30:00" },
    { id: 4, usuario: "TecnoSuministros SAS", accion: "CARGAR COTIZACION", tabla: "COTIZACION", detalle: "Cargada cotización para placa Arduino Uno", fecha: "2026-06-26 11:22:15" },
  ]));

  // Navigation State
  const [activeTab, setActiveTab] = useState<"roles" | "metrics" | "tables">("roles");
  const [loggedInUser, setLoggedInUser] = useState<Usuario | null>(null);
  const [activeRole, setActiveRole] = useState<RolNombre>(RolNombre.INSTRUCTOR);

  // Sync active role dynamically based on logged-in user
  useEffect(() => {
    if (loggedInUser) {
      if (loggedInUser.ID_ROL === 1) setActiveRole(RolNombre.INSTRUCTOR);
      else if (loggedInUser.ID_ROL === 2) setActiveRole(RolNombre.COORDINADOR);
      else if (loggedInUser.ID_ROL === 3) setActiveRole(RolNombre.ALMACENISTA);
      else if (loggedInUser.ID_ROL === 4) setActiveRole(RolNombre.PROVEEDOR);
    }
  }, [loggedInUser]);

  // Update & Sync Helpers
  const handleUpdateRoles = (updated: { ID_ROL: number; NOMBRE_ROL: RolNombre }[]) => {
    setRoles(updated);
    saveToStorage("sena_roles", updated);
  };

  const handleUpdateUsuarios = (updated: Usuario[]) => {
    setUsuarios(updated);
    saveToStorage("sena_usuarios", updated);
  };

  const handleUpdateProveedores = (updated: Proveedor[]) => {
    setProveedores(updated);
    saveToStorage("sena_proveedores", updated);
  };

  const handleUpdateAuditLogs = (updated: any[]) => {
    setAuditLogs(updated);
    saveToStorage("sena_audit_logs", updated);
  };

  const handleUpdateLotes = (updated: LoteRequerimiento[]) => {
    setLotes(updated);
    saveToStorage("sena_lotes", updated);
  };

  const handleUpdateMatrizItems = (updated: MatrizItem[]) => {
    setMatrizItems(updated);
    saveToStorage("sena_matriz_items", updated);
  };

  const handleUpdateNecesidades = (updated: Necesidad[]) => {
    setNecesidades(updated);
    saveToStorage("sena_necesidades", updated);
  };

  const handleUpdateCodigosUnspsc = (updated: CodigoUnspsc[]) => {
    setCodigosUnspsc(updated);
    saveToStorage("sena_codigos_unspsc", updated);
  };

  const handleUpdateCertificados = (updated: CertificadoExistencia[]) => {
    setCertificados(updated);
    saveToStorage("sena_certificados", updated);
  };

  const handleUpdateCotizaciones = (updated: Cotizacion[]) => {
    setCotizaciones(updated);
    saveToStorage("sena_cotizaciones", updated);
  };

  const handleUpdateIvas = (updated: IvaCotizacion[]) => {
    setIvas(updated);
    saveToStorage("sena_ivas", updated);
  };

  const handleUpdateFichasTecnicas = (updated: FichaTecnica[]) => {
    setFichasTecnicas(updated);
    saveToStorage("sena_fichas_tecnicas", updated);
  };

  // Stats Calculations
  const totalPresupuestado = matrizItems.reduce((acc, item) => acc + item.VALOR_TORAL_PROMEDIO, 0);
  const itemsCountTotal = matrizItems.length;
  const certsCountTotal = certificados.length;
  const bidsCountTotal = cotizaciones.length;

  // 1. LOGIN WALL UI
  if (!loggedInUser) {
    return (
      <div id="login-container" className="min-h-screen bg-[#f8fafc] flex flex-col items-center justify-center p-4 sm:p-6 font-sans text-slate-800 relative overflow-hidden">
        
        {/* Abstract background decorative blobs for elegance */}
        <div className="absolute top-[-20%] left-[-10%] w-[500px] h-[500px] rounded-full bg-emerald-500/5 blur-3xl -z-10"></div>
        <div className="absolute bottom-[-20%] right-[-10%] w-[500px] h-[500px] rounded-full bg-emerald-600/5 blur-3xl -z-10"></div>

        <div className="w-full max-w-lg bg-white border border-slate-200/80 shadow-2xl rounded-3xl overflow-hidden flex flex-col transition-all duration-300">
          
          {/* Top colored accent border */}
          <div className="h-2 bg-[#39A900]"></div>
          
          <div className="p-6 sm:p-10 flex flex-col items-center">
            {/* Logo SENA */}
            <div className="w-16 h-16 bg-[#39A900] rounded-2xl flex items-center justify-center shadow-md shadow-emerald-700/10 mb-5">
              <span className="text-white font-black text-4xl select-none">S</span>
            </div>
            
            <h1 className="text-2xl font-black tracking-tight text-slate-900 text-center">
              Gestión de Materiales SENA
            </h1>
            
            <p className="text-xs text-[#39A900] uppercase font-extrabold tracking-widest text-center mt-1 flex items-center gap-1.5 justify-center">
              <span>Proceso de Pre-Compra</span>
              <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
              <span>SOFIA v2.8</span>
            </p>
            
            <p className="text-slate-500 text-xs text-center mt-2.5 max-w-xs leading-relaxed">
              Planificación, viabilidad y cotizaciones comerciales integradas para materiales de formación.
            </p>

            {/* Standard Login Helper (Subtle & clean) */}
            <div className="w-full mt-6 bg-slate-50 border border-slate-150 rounded-2xl p-4 text-xs text-slate-600">
              <p className="font-bold text-slate-700 mb-1 flex items-center gap-1.5">
                <Info className="w-4 h-4 text-[#39A900]" />
                <span>Usuarios de Demostración:</span>
              </p>
              <div className="grid grid-cols-2 gap-x-4 gap-y-1.5 font-mono text-[10px] mt-2 text-slate-500">
                <div>
                  <span className="font-bold text-slate-700">Instructor:</span>
                  <p>carlos.gomez@sena.edu.co</p>
                </div>
                <div>
                  <span className="font-bold text-slate-700">Coordinador:</span>
                  <p>lucia.fernandez@sena.edu.co</p>
                </div>
                <div>
                  <span className="font-bold text-slate-700">Almacenista:</span>
                  <p>mario.alvarez@sena.edu.co</p>
                </div>
                <div>
                  <span className="font-bold text-slate-700">Proveedor:</span>
                  <p>contacto@ferreteria.com</p>
                </div>
              </div>
            </div>

            <div className="w-full h-px bg-slate-100 my-6"></div>

            {/* Manual Form */}
            <div className="w-full space-y-3.5">
              <div>
                <label className="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1 px-1">
                  Correo Institucional
                </label>
                <div className="relative">
                  <span className="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                    <Mail className="w-4 h-4" />
                  </span>
                  <input
                    id="input-login-email"
                    type="email"
                    placeholder="ejemplo@sena.edu.co"
                    defaultValue="carlos.gomez@sena.edu.co"
                    className="w-full text-xs pl-10 pr-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-1 focus:ring-[#39A900] focus:border-[#39A900] outline-none transition-all font-mono"
                  />
                </div>
              </div>

              <div>
                <label className="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1 px-1">
                  Contraseña de Acceso
                </label>
                <div className="relative">
                  <span className="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                    <Lock className="w-4 h-4" />
                  </span>
                  <input
                    id="input-login-password"
                    type="password"
                    placeholder="••••••••"
                    defaultValue="sena123"
                    className="w-full text-xs pl-10 pr-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-1 focus:ring-[#39A900] focus:border-[#39A900] outline-none transition-all"
                  />
                </div>
              </div>

              <button
                onClick={() => {
                  const emailInput = document.getElementById("input-login-email") as HTMLInputElement;
                  const email = emailInput?.value || "carlos.gomez@sena.edu.co";
                  const matched = usuarios.find(u => u.EMAIL.toLowerCase() === email.toLowerCase());
                  if (matched) {
                    setLoggedInUser(matched);
                  } else {
                    setLoggedInUser(usuarios[0]);
                  }
                }}
                className="w-full py-2.5 bg-[#39A900] hover:bg-[#2e8800] active:scale-[0.99] text-white font-extrabold text-xs rounded-xl shadow-xs transition-all flex items-center justify-center gap-1.5 cursor-pointer mt-2"
              >
                <LogIn className="w-4 h-4" />
                <span>Ingresar al Sistema</span>
              </button>
            </div>

          </div>

          {/* Footer inside Card */}
          <div className="bg-slate-50 px-8 py-4 border-t border-slate-150 flex items-center justify-between text-[10px] text-slate-400">
            <span className="font-semibold text-slate-400 flex items-center gap-1">
              <span className="w-1.5 h-1.5 bg-[#39A900] rounded-full"></span> SENA ADSO © 2026
            </span>
            <button
              onClick={() => { localStorage.clear(); window.location.reload(); }}
              className="hover:text-red-500 font-bold flex items-center gap-1 transition-colors cursor-pointer"
            >
              <RefreshCw className="w-3 h-3 animate-spin-slow" /> Reiniciar BD
            </button>
          </div>

        </div>

      </div>
    );
  }

  // 2. MAIN LOGGED IN SCREEN
  return (
    <div id="root-container" className="min-h-screen bg-[#f8fafc] flex flex-col font-sans text-slate-800">
      
      {/* 1. Sleek Header Banner */}
      <header id="sena-main-header" className="flex flex-col sm:flex-row items-center justify-between px-6 py-4 bg-white border-b border-slate-200 shadow-xs gap-4 z-10">
        <div className="flex items-center gap-4">
          <div className="w-12 h-12 bg-[#39A900] rounded-xl flex items-center justify-center shadow-xs">
            <span className="text-white font-extrabold text-2xl">S</span>
          </div>
          <div>
            <h1 className="text-xl font-bold tracking-tight text-slate-900">Gestión de Materiales SENA</h1>
            <p className="text-xs text-slate-500 uppercase font-semibold tracking-widest flex items-center gap-2">
              <span>Proceso de Pre-Compra</span>
              <span className="h-1 w-1 rounded-full bg-slate-300"></span>
              <span className="text-emerald-600 font-bold">SOFIA v2.8</span>
            </p>
          </div>
        </div>

        {/* Lock / Logged In Capsule inside header */}
        <div className="flex items-center bg-slate-50 p-1.5 rounded-xl border border-slate-200/60 gap-3 shadow-2xs">
          <div className="flex items-center gap-1.5 px-3 py-1 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-700">
            <span className="w-2 h-2 rounded-full bg-[#39A900] animate-pulse"></span>
            <span>Sesión: {
              activeRole === RolNombre.INSTRUCTOR ? "Instructor Líder" :
              activeRole === RolNombre.COORDINADOR ? "Coord. Académica" :
              activeRole === RolNombre.ALMACENISTA ? "Almacén General" :
              "Proveedor Externo"
            }</span>
          </div>
          
          <button 
            onClick={() => setLoggedInUser(null)}
            className="flex items-center gap-1.5 px-3 py-1 bg-red-50 hover:bg-red-100 text-red-600 font-bold text-xs rounded-lg border border-red-100/50 transition-all cursor-pointer"
            title="Cerrar la sesión actual"
          >
            <LogOut className="w-3.5 h-3.5" />
            <span>Cerrar Sesión</span>
          </button>
        </div>

        {/* Dynamic User Block */}
        <div className="flex items-center gap-3">
          <div className="text-right hidden sm:block">
            <p className="text-sm font-semibold text-slate-800">{loggedInUser.NOMBRE} {loggedInUser.APELLIDO}</p>
            <p className="text-[10px] text-slate-400 font-mono">{loggedInUser.EMAIL}</p>
          </div>
          <div className="w-10 h-10 rounded-full bg-emerald-50 border border-emerald-100 flex items-center justify-center shadow-2xs font-black text-sm text-[#39A900]">
            {loggedInUser.NOMBRE[0]}
          </div>
        </div>
      </header>

      {/* 2. Side navigation & content layout */}
      <div className="flex flex-1 flex-col md:flex-row overflow-hidden bg-[#f8fafc]">
        
        {/* Navigation Sidebar */}
        <aside className="w-full md:w-64 bg-white border-b md:border-b-0 md:border-r border-slate-200 flex flex-col p-6 flex-shrink-0">
          <nav className="space-y-1.5 flex-1">
            <div className="text-[10px] uppercase tracking-widest text-slate-400 font-extrabold mb-4 px-2 font-mono">Menú Principal</div>
            
            <button
              id="tab-btn-roles"
              onClick={() => setActiveTab("roles")}
              className={`w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-bold text-sm transition-all cursor-pointer ${
                activeTab === "roles"
                  ? "bg-emerald-50/50 text-[#39A900]"
                  : "text-slate-600 hover:bg-slate-50 hover:text-slate-900"
              }`}
            >
              <Users className="w-4 h-4" />
              Gestión de Requerimientos
            </button>

            <button
              id="tab-btn-metrics"
              onClick={() => setActiveTab("metrics")}
              className={`w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-bold text-sm transition-all cursor-pointer ${
                activeTab === "metrics"
                  ? "bg-emerald-50/50 text-[#39A900]"
                  : "text-slate-600 hover:bg-slate-50 hover:text-slate-900"
              }`}
            >
              <TrendingUp className="w-4 h-4" />
              Estadísticas Globales
            </button>

            <button
              id="tab-btn-tables"
              onClick={() => setActiveTab("tables")}
              className={`w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-bold text-sm transition-all cursor-pointer ${
                activeTab === "tables"
                  ? "bg-emerald-50/50 text-[#39A900]"
                  : "text-slate-600 hover:bg-slate-50 hover:text-slate-900"
              }`}
            >
              <Database className="w-4 h-4" />
              Consola Base de Datos
            </button>

            {/* Acciones de tu Rol - Dynamic contextual helper to fill space and guide user */}
            {activeTab === "roles" && (
              <div className="mt-6 pt-6 border-t border-slate-150 animate-fadeIn">
                <div className="text-[10px] uppercase tracking-widest text-slate-400 font-extrabold mb-3 px-2 font-mono flex items-center justify-between">
                  <span>Acciones de tu Rol</span>
                  <span className="text-[9px] bg-emerald-50 text-[#39A900] px-1.5 py-0.5 rounded font-black uppercase">
                    {activeRole === RolNombre.INSTRUCTOR ? "Instructor" :
                     activeRole === RolNombre.COORDINADOR ? "Coordinador" :
                     activeRole === RolNombre.ALMACENISTA ? "Almacenista" :
                     "Proveedor"}
                  </span>
                </div>
                
                <div className="space-y-2 bg-slate-50/80 p-3 rounded-2xl border border-slate-150">
                  {/* Instructor Tasks */}
                  {activeRole === RolNombre.INSTRUCTOR && [
                    { label: "Crear Lote de Solicitud", done: lotes.length > 0 },
                    { label: "Planificar Matriz de Bienes", done: matrizItems.length > 0 },
                    { label: "Distribuir Necesidad", done: necesidades.some(n => n.CANTIDAD_NESECIDAD > 0) },
                    { label: "Clasificación UNSPSC", done: codigosUnspsc.some(c => c.SEGMENTO !== "N/A" && c.SEGMENTO !== "") },
                    { label: "Enviar a Coordinación", done: lotes.some(l => l.ESTADO_TRAMITE !== "BORRADOR") }
                  ].map((task, idx) => (
                    <div key={idx} className="flex items-start gap-2 text-xs">
                      {task.done ? (
                        <CheckCircle2 className="w-3.5 h-3.5 text-[#39A900] flex-shrink-0 mt-0.5" />
                      ) : (
                        <Clock className="w-3.5 h-3.5 text-slate-400 flex-shrink-0 mt-0.5" />
                      )}
                      <span className={`leading-snug ${task.done ? "text-slate-700 font-semibold line-through decoration-slate-300" : "text-slate-500 font-medium"}`}>
                        {task.label}
                      </span>
                    </div>
                  ))}

                  {/* Coordinador Tasks */}
                  {activeRole === RolNombre.COORDINADOR && [
                    { label: "Revisar Lotes Pendientes", done: lotes.some(l => l.ESTADO_TRAMITE === "ENVIADO_A_COORDINADOR") },
                    { label: "Verificar Pertinencia", done: lotes.some(l => l.ESTADO_TRAMITE === "APROBADO_COORDINADOR" || l.ESTADO_TRAMITE === "RECHAZADO_COORDINADOR" || l.ESTADO_TRAMITE === "CON_CERTIFICADO_NO_EXISTENCIA") },
                    { label: "Aprobar o Devolver Lote", done: lotes.some(l => l.ESTADO_TRAMITE === "APROBADO_COORDINADOR" || l.ESTADO_TRAMITE === "RECHAZADO_COORDINADOR" || l.ESTADO_TRAMITE === "CON_CERTIFICADO_NO_EXISTENCIA" || l.ESTADO_TRAMITE === "COTIZADO") }
                  ].map((task, idx) => (
                    <div key={idx} className="flex items-start gap-2 text-xs">
                      {task.done ? (
                        <CheckCircle2 className="w-3.5 h-3.5 text-[#39A900] flex-shrink-0 mt-0.5" />
                      ) : (
                        <Clock className="w-3.5 h-3.5 text-slate-400 flex-shrink-0 mt-0.5" />
                      )}
                      <span className={`leading-snug ${task.done ? "text-slate-700 font-semibold line-through decoration-slate-300" : "text-slate-500 font-medium"}`}>
                        {task.label}
                      </span>
                    </div>
                  ))}

                  {/* Almacenista Tasks */}
                  {activeRole === RolNombre.ALMACENISTA && [
                    { label: "Inspeccionar Stock Físico", done: lotes.some(l => l.ESTADO_TRAMITE === "APROBADO_COORDINADOR") },
                    { label: "Expedir Certificado", done: certificados.length > 0 },
                    { label: "Liberar para Cotización", done: lotes.some(l => l.ESTADO_TRAMITE === "CON_CERTIFICADO_NO_EXISTENCIA" || l.ESTADO_TRAMITE === "COTIZADO") }
                  ].map((task, idx) => (
                    <div key={idx} className="flex items-start gap-2 text-xs">
                      {task.done ? (
                        <CheckCircle2 className="w-3.5 h-3.5 text-[#39A900] flex-shrink-0 mt-0.5" />
                      ) : (
                        <Clock className="w-3.5 h-3.5 text-slate-400 flex-shrink-0 mt-0.5" />
                      )}
                      <span className={`leading-snug ${task.done ? "text-slate-700 font-semibold line-through decoration-slate-300" : "text-slate-500 font-medium"}`}>
                        {task.label}
                      </span>
                    </div>
                  ))}

                  {/* Proveedor Tasks */}
                  {activeRole === RolNombre.PROVEEDOR && [
                    { label: "Cargar Cotización", done: cotizaciones.length > 0 },
                    { label: "Calcular Desglose del IVA", done: ivas.length > 0 },
                    { label: "Diligenciar Ficha Técnica", done: fichasTecnicas.length > 0 },
                    { label: "Firmar como Proponente", done: fichasTecnicas.some(f => f.FIRMA_PROPONENTE !== "") }
                  ].map((task, idx) => (
                    <div key={idx} className="flex items-start gap-2 text-xs">
                      {task.done ? (
                        <CheckCircle2 className="w-3.5 h-3.5 text-[#39A900] flex-shrink-0 mt-0.5" />
                      ) : (
                        <Clock className="w-3.5 h-3.5 text-slate-400 flex-shrink-0 mt-0.5" />
                      )}
                      <span className={`leading-snug ${task.done ? "text-slate-700 font-semibold line-through decoration-slate-300" : "text-slate-500 font-medium"}`}>
                        {task.label}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </nav>
          
          {/* System status widget matches Sleek design perfectly */}
          <div className="mt-6 md:mt-auto bg-slate-900 rounded-2xl p-5 text-white shadow-lg">
            <p className="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest">Estado del Sistema</p>
            <p className="text-xs mt-1.5 font-medium">Presupuesto Estimado: <span className="text-[#39A900] font-bold">{(totalPresupuestado > 0 ? "84%" : "0%")}</span></p>
            <div className="w-full bg-slate-700 h-1.5 rounded-full mt-2.5 overflow-hidden">
              <div 
                className="bg-[#39A900] h-full rounded-full shadow-[0_0_8px_#39A900] transition-all duration-500" 
                style={{ width: totalPresupuestado > 0 ? "84%" : "0%" }}
              ></div>
            </div>
            
            {/* Database Refresh action inside sidebar status widget */}
            <button
              id="btn-clear-storage"
              onClick={() => { localStorage.clear(); window.location.reload(); }}
              className="w-full mt-4 inline-flex items-center justify-center gap-1.5 bg-slate-850 hover:bg-slate-800 text-slate-300 hover:text-white px-3 py-2 rounded-xl text-xs font-bold transition-all border border-slate-700/50 shadow-xs cursor-pointer"
              title="Restablecer base de datos a valores originales"
            >
              <RefreshCw className="w-3.5 h-3.5" />
              Reiniciar Base de Datos
            </button>
          </div>
        </aside>

        {/* 3. Main Body Content Area */}
        <main id="main-content" className="flex-1 p-6 md:p-8 overflow-y-auto">

          {activeTab === "roles" && (
            <div className="space-y-6">
              
              {/* Workspace Header Title */}
              <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                  <h2 className="text-2xl font-black text-slate-900 tracking-tight">
                    Panel de {
                      activeRole === RolNombre.INSTRUCTOR ? "Instructor Líder" :
                      activeRole === RolNombre.COORDINADOR ? "Coordinación Académica" :
                      activeRole === RolNombre.ALMACENISTA ? "Almacén General" :
                      "Proveedor Externo"
                    }
                  </h2>
                  <p className="text-slate-500 text-sm mt-0.5">
                    {activeRole === RolNombre.INSTRUCTOR && "Planificación de la matriz de bienes, clasificación UNSPSC y detalle de necesidades."}
                    {activeRole === RolNombre.COORDINADOR && "Revisión, validación técnica y aprobación o devolución de los lotes."}
                    {activeRole === RolNombre.ALMACENISTA && "Control de stock en inventario físico de bodega y emisión de Certificados de No Existencia."}
                    {activeRole === RolNombre.PROVEEDOR && "Carga de ofertas comerciales, desglose automático del IVA y registro de fichas técnicas."}
                  </p>
                </div>

                <div className="flex bg-emerald-50 px-3.5 py-1.5 rounded-xl border border-emerald-200/50 self-start md:self-center">
                  <span className="text-[10px] font-black text-[#39A900] tracking-wider uppercase">VISTA EXCLUSIVA DE ROL</span>
                </div>
              </div>

              {/* Steps Workflow Banner (Enforced Read-only Progress Visualization) */}
              <div className="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs">
                <div className="flex items-center gap-2">
                  <span className="text-[10px] bg-emerald-50 text-[#39A900] font-black px-2.5 py-1 rounded-full uppercase tracking-wider">
                    Ciclo de Vida de Pre-Compra
                  </span>
                  <span className="text-xs text-slate-400 font-mono">• Visualizador de Proceso</span>
                </div>
                <h3 className="text-lg font-bold text-slate-900 mt-2">Sincronización Secuencial del Trámite</h3>
                <p className="text-xs text-slate-500 mt-0.5">
                  Cada etapa del flujo se activa según el rol autenticado. No se permite saltar etapas sin iniciar sesión como el respectivo funcionario.
                </p>

                {/* Steps grid switcher (Static read-only visualization representing role locks) */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-5">
                  {/* Paso 1: Instructor */}
                  <div
                    className={`p-4 rounded-xl border text-left transition-all ${
                      activeRole === RolNombre.INSTRUCTOR
                        ? "bg-emerald-50/20 border-[#39A900] shadow-sm ring-1 ring-emerald-200"
                        : "bg-slate-50/40 border-slate-100 text-slate-400"
                    }`}
                  >
                    <div className="flex justify-between items-start">
                      <span className={`text-[10px] font-extrabold px-1.5 py-0.5 rounded ${activeRole === RolNombre.INSTRUCTOR ? "bg-[#39A900]/10 text-[#39A900]" : "bg-slate-150 text-slate-500"} font-mono`}>PASO 1</span>
                      <BookOpen className={`w-4 h-4 ${activeRole === RolNombre.INSTRUCTOR ? "text-[#39A900]" : "text-slate-400"}`} />
                    </div>
                    <h4 className="font-extrabold text-sm mt-3 text-slate-900">1. Instructor Líder</h4>
                    <p className="text-[11px] text-slate-500 mt-1 leading-relaxed">
                      Planifica la matriz de bienes, clasifica UNSPSC y asigna necesidades.
                    </p>
                  </div>

                  {/* Paso 2: Coordinador */}
                  <div
                    className={`p-4 rounded-xl border text-left transition-all ${
                      activeRole === RolNombre.COORDINADOR
                        ? "bg-emerald-50/20 border-[#39A900] shadow-sm ring-1 ring-emerald-200"
                        : "bg-slate-50/40 border-slate-100 text-slate-400"
                    }`}
                  >
                    <div className="flex justify-between items-start">
                      <span className={`text-[10px] font-extrabold px-1.5 py-0.5 rounded ${activeRole === RolNombre.COORDINADOR ? "bg-[#39A900]/10 text-[#39A900]" : "bg-slate-150 text-slate-500"} font-mono`}>PASO 2</span>
                      <ClipboardCheck className={`w-4 h-4 ${activeRole === RolNombre.COORDINADOR ? "text-[#39A900]" : "text-slate-400"}`} />
                    </div>
                    <h4 className="font-extrabold text-sm mt-3 text-slate-900">2. Coord. Académica</h4>
                    <p className="text-[11px] text-slate-500 mt-1 leading-relaxed">
                      Valida la viabilidad técnica y aprueba/devuelve el lote.
                    </p>
                  </div>

                  {/* Paso 3: Almacenista */}
                  <div
                    className={`p-4 rounded-xl border text-left transition-all ${
                      activeRole === RolNombre.ALMACENISTA
                        ? "bg-emerald-50/20 border-[#39A900] shadow-sm ring-1 ring-emerald-200"
                        : "bg-slate-50/40 border-slate-100 text-slate-400"
                    }`}
                  >
                    <div className="flex justify-between items-start">
                      <span className={`text-[10px] font-extrabold px-1.5 py-0.5 rounded ${activeRole === RolNombre.ALMACENISTA ? "bg-[#39A900]/10 text-[#39A900]" : "bg-slate-150 text-slate-500"} font-mono`}>PASO 3</span>
                      <Layers className={`w-4 h-4 ${activeRole === RolNombre.ALMACENISTA ? "text-[#39A900]" : "text-slate-400"}`} />
                    </div>
                    <h4 className="font-extrabold text-sm mt-3 text-slate-900">3. Almacén General</h4>
                    <p className="text-[11px] text-slate-500 mt-1 leading-relaxed">
                      Verifica el stock físico y expide Certificado de No Existencia.
                    </p>
                  </div>

                  {/* Paso 4: Proveedor */}
                  <div
                    className={`p-4 rounded-xl border text-left transition-all ${
                      activeRole === RolNombre.PROVEEDOR
                        ? "bg-emerald-50/20 border-[#39A900] shadow-sm ring-1 ring-emerald-200"
                        : "bg-slate-50/40 border-slate-100 text-slate-400"
                    }`}
                  >
                    <div className="flex justify-between items-start">
                      <span className={`text-[10px] font-extrabold px-1.5 py-0.5 rounded ${activeRole === RolNombre.PROVEEDOR ? "bg-[#39A900]/10 text-[#39A900]" : "bg-slate-150 text-slate-500"} font-mono`}>PASO 4</span>
                      <Building2 className={`w-4 h-4 ${activeRole === RolNombre.PROVEEDOR ? "text-[#39A900]" : "text-slate-400"}`} />
                    </div>
                    <h4 className="font-extrabold text-sm mt-3 text-slate-900">4. Proveedor Externo</h4>
                    <p className="text-[11px] text-slate-500 mt-1 leading-relaxed">
                      Carga ofertas comerciales formales, IVA y fichas técnicas de marca.
                    </p>
                  </div>
                </div>
              </div>

              {/* Current Selected Role Workspace */}
              <div id="role-workspace-container" className="animate-fadeIn">
                {activeRole === RolNombre.INSTRUCTOR && (
                  <InstructorDashboard
                    usuarios={usuarios}
                    lotes={lotes}
                    matrizItems={matrizItems}
                    necesidades={necesidades}
                    codigosUnspsc={codigosUnspsc}
                    onUpdateLotes={handleUpdateLotes}
                    onUpdateMatrizItems={handleUpdateMatrizItems}
                    onUpdateNecesidades={handleUpdateNecesidades}
                    onUpdateCodigosUnspsc={handleUpdateCodigosUnspsc}
                  />
                )}

                {activeRole === RolNombre.COORDINADOR && (
                  <CoordinatorDashboard
                    usuarios={usuarios}
                    lotes={lotes}
                    matrizItems={matrizItems}
                    necesidades={necesidades}
                    codigosUnspsc={codigosUnspsc}
                    onUpdateLotes={handleUpdateLotes}
                  />
                )}

                {activeRole === RolNombre.ALMACENISTA && (
                  <StorekeeperDashboard
                    usuarios={usuarios}
                    lotes={lotes}
                    matrizItems={matrizItems}
                    certificados={certificados}
                    onUpdateLotes={handleUpdateLotes}
                    onUpdateCertificados={handleUpdateCertificados}
                  />
                )}

                {activeRole === RolNombre.PROVEEDOR && (
                  <SupplierDashboard
                    proveedores={proveedores}
                    lotes={lotes}
                    matrizItems={matrizItems}
                    cotizaciones={cotizaciones}
                    ivas={ivas}
                    fichasTecnicas={fichasTecnicas}
                    onUpdateLotes={handleUpdateLotes}
                    onUpdateMatrizItems={handleUpdateMatrizItems}
                    onUpdateCotizaciones={handleUpdateCotizaciones}
                    onUpdateIvas={handleUpdateIvas}
                    onUpdateFichasTecnicas={handleUpdateFichasTecnicas}
                  />
                )}
              </div>
            </div>
          )}

          {/* Global Statistics metrics */}
          {activeTab === "metrics" && (
            <div className="animate-fadeIn space-y-6">
              <div className="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h3 className="text-xl font-bold text-slate-900">Métricas y Resumen Financiero Consolidador</h3>
                <p className="text-xs text-slate-500 mt-1">
                  Visualización consolidada de los presupuestos promedio calculados por los estudios de mercado activos, comparativa de estados y volumen de datos relacionales cargados.
                </p>

                {/* Metrics cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mt-6">
                  <div className="p-5 rounded-2xl border border-slate-200 bg-white shadow-sm hover:border-emerald-500/30 transition-all duration-200">
                    <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Presupuesto Estimado Consolidado</p>
                    <div className="flex items-end justify-between mt-2">
                      <p className="text-2xl font-black text-slate-900">${totalPresupuestado.toLocaleString()} COP</p>
                      <span className="text-[10px] px-2 py-0.5 bg-emerald-50 text-[#39A900] rounded-md font-bold">ESTUDIO PROMEDIO</span>
                    </div>
                  </div>

                  <div className="p-5 rounded-2xl border border-slate-200 bg-white shadow-sm hover:border-blue-500/30 transition-all duration-200">
                    <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Materiales de Formación</p>
                    <div className="flex items-end justify-between mt-2">
                      <p className="text-2xl font-black text-slate-900">{itemsCountTotal} Ítems</p>
                      <span className="text-[10px] px-2 py-0.5 bg-blue-50 text-blue-600 rounded-md font-bold">MATRIZ BIENES</span>
                    </div>
                  </div>

                  <div className="p-5 rounded-2xl border border-slate-200 bg-white shadow-sm hover:border-purple-500/30 transition-all duration-200">
                    <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Certificados Emitidos</p>
                    <div className="flex items-end justify-between mt-2">
                      <p className="text-2xl font-black text-slate-900">{certsCountTotal}</p>
                      <span className="text-[10px] px-2 py-0.5 bg-purple-50 text-purple-600 rounded-md font-bold">NO EXISTENCIA</span>
                    </div>
                  </div>

                  <div className="p-5 rounded-2xl border border-slate-200 bg-white shadow-sm hover:border-amber-500/30 transition-all duration-200">
                    <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Cotizaciones Oficiales</p>
                    <div className="flex items-end justify-between mt-2">
                      <p className="text-2xl font-black text-slate-900">{bidsCountTotal} Recibidas</p>
                      <span className="text-[10px] px-2 py-0.5 bg-amber-50 text-amber-600 rounded-md font-bold">PROVEEDORES</span>
                    </div>
                  </div>
                </div>

                {/* Chart simulation / detailed status overview */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6 pt-6 border-t border-slate-150">
                  
                  {/* Lot State break down */}
                  <div className="border border-slate-200 rounded-2xl p-6 bg-slate-50/50">
                    <h4 className="font-bold text-sm text-slate-800 mb-4 flex items-center gap-2">
                      <Clock className="w-4 h-4 text-[#39A900]" />
                      Distribución de Lotes por Estado del Trámite
                    </h4>
                    <div className="space-y-2 text-xs">
                      {[
                        { label: "Borradores del Instructor", count: lotes.filter(l => l.ESTADO_TRAMITE === "BORRADOR").length, color: "bg-amber-500" },
                        { label: "Pendientes Coordinación Académica", count: lotes.filter(l => l.ESTADO_TRAMITE === "ENVIADO_A_COORDINADOR").length, color: "bg-blue-500" },
                        { label: "Aprobados sin Certificado Almacén", count: lotes.filter(l => l.ESTADO_TRAMITE === "APROBADO_COORDINADOR").length, color: "bg-purple-500" },
                        { label: "Con Certificado (Listos para Cotizar)", count: lotes.filter(l => l.ESTADO_TRAMITE === "CON_CERTIFICADO_NO_EXISTENCIA").length, color: "bg-orange-500" },
                        { label: "Cotizados (Estudio de Mercado Activo)", count: lotes.filter(l => l.ESTADO_TRAMITE === "COTIZADO").length, color: "bg-emerald-600" }
                      ].map((st) => (
                        <div key={st.label} className="bg-white p-3.5 rounded-xl border border-slate-200 flex items-center justify-between shadow-2xs">
                          <div className="flex items-center gap-2.5">
                            <span className={`w-2.5 h-2.5 rounded-full ${st.color}`} />
                            <span className="font-bold text-slate-700">{st.label}</span>
                          </div>
                          <span className="font-bold text-slate-900 bg-slate-100 px-2.5 py-1 rounded-lg text-[11px] font-mono">
                            {st.count} {st.count === 1 ? "lote" : "lotes"}
                          </span>
                        </div>
                      ))}
                    </div>
                  </div>

                  {/* Database counts metrics */}
                  <div className="border border-slate-200 rounded-2xl p-6 bg-slate-50/50 flex flex-col justify-between">
                    <div>
                      <h4 className="font-bold text-sm text-slate-800 mb-4 flex items-center gap-2">
                        <Briefcase className="w-4 h-4 text-[#39A900]" />
                        Resumen del Repositorio de Datos (Relaciones)
                      </h4>
                      <p className="text-xs text-slate-500 mb-4 leading-relaxed">
                        Número total de registros cargados en memoria y sincronizados con localStorage de acuerdo al dominio de base de datos de pre-compra:
                      </p>
                      <div className="grid grid-cols-2 gap-3 text-xs">
                        <div className="bg-white border border-slate-200 rounded-xl p-3.5 text-center shadow-2xs">
                          <span className="block text-[10px] text-slate-400 font-extrabold tracking-widest uppercase">USUARIOS</span>
                          <span className="text-xl font-black text-slate-800 mt-1 block">{usuarios.length}</span>
                        </div>
                        <div className="bg-white border border-slate-200 rounded-xl p-3.5 text-center shadow-2xs">
                          <span className="block text-[10px] text-slate-400 font-extrabold tracking-widest uppercase">MATRIZ_ITEM</span>
                          <span className="text-xl font-black text-slate-800 mt-1 block">{matrizItems.length}</span>
                        </div>
                        <div className="bg-white border border-slate-200 rounded-xl p-3.5 text-center shadow-2xs">
                          <span className="block text-[10px] text-slate-400 font-extrabold tracking-widest uppercase">NECESIDAD</span>
                          <span className="text-xl font-black text-slate-800 mt-1 block">{necesidades.length}</span>
                        </div>
                        <div className="bg-white border border-slate-200 rounded-xl p-3.5 text-center shadow-2xs">
                          <span className="block text-[10px] text-slate-400 font-extrabold tracking-widest uppercase">FICHA_TECNICA</span>
                          <span className="text-xl font-black text-slate-800 mt-1 block">{fichasTecnicas.length}</span>
                        </div>
                      </div>
                    </div>

                    <div className="mt-6 p-4 bg-emerald-50 rounded-xl border border-emerald-100 flex items-start gap-2.5 text-xs text-emerald-800 leading-relaxed">
                      <Info className="w-4 h-4 text-[#39A900] flex-shrink-0 mt-0.5" />
                      <span>
                        La base de datos relacional se recalcula dinámicamente cada vez que operas la pre-compra, recalculando promedios y modificando los estados de lote de forma interactiva.
                      </span>
                    </div>
                  </div>

                </div>
              </div>
            </div>
          )}

          {/* Database Explorer Tab */}
          {activeTab === "tables" && (
            <DatabaseExplorer
              roles={roles}
              onUpdateRoles={handleUpdateRoles}
              usuarios={usuarios}
              onUpdateUsuarios={handleUpdateUsuarios}
              lotes={lotes}
              onUpdateLotes={handleUpdateLotes}
              matrizItems={matrizItems}
              onUpdateMatrizItems={handleUpdateMatrizItems}
              necesidades={necesidades}
              onUpdateNecesidades={handleUpdateNecesidades}
              codigosUnspsc={codigosUnspsc}
              onUpdateCodigosUnspsc={handleUpdateCodigosUnspsc}
              proveedores={proveedores}
              onUpdateProveedores={handleUpdateProveedores}
              certificados={certificados}
              onUpdateCertificados={handleUpdateCertificados}
              cotizaciones={cotizaciones}
              onUpdateCotizaciones={handleUpdateCotizaciones}
              ivas={ivas}
              onUpdateIvas={handleUpdateIvas}
              fichasTecnicas={fichasTecnicas}
              onUpdateFichasTecnicas={handleUpdateFichasTecnicas}
              auditLogs={auditLogs}
              onUpdateAuditLogs={handleUpdateAuditLogs}
            />
          )}

        </main>
      </div>

      {/* 4. Footer Info Bar */}
      <footer id="sena-footer" className="bg-slate-900 border-t border-slate-800 flex flex-col sm:flex-row items-center px-8 py-4 sm:h-12 justify-between gap-2 z-10 text-xs text-slate-400">
        <div className="flex flex-col sm:flex-row items-center gap-4 text-center sm:text-left">
          <span className="flex items-center gap-1.5 text-[10px] text-slate-400">
            <span className="w-2.5 h-2.5 bg-[#39A900] rounded-full"></span> Sistema Operativo SOFIA
          </span>
          <span className="text-[10px] text-slate-500">V 2.8.2 (Stable Build)</span>
        </div>
        <div className="text-[10px] text-slate-500 italic text-center sm:text-right text-slate-400">
          SENA - Servicio Nacional de Aprendizaje © 2026
        </div>
      </footer>

    </div>
  );
}
