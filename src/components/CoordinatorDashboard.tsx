import React, { useState } from "react";
import { 
  CheckCircle, 
  XCircle, 
  AlertCircle, 
  FileText, 
  Users, 
  MessageSquare, 
  TrendingUp, 
  ClipboardCheck,
  Award
} from "lucide-react";
import { 
  LoteRequerimiento, 
  MatrizItem, 
  Necesidad, 
  CodigoUnspsc, 
  Usuario 
} from "../types";

interface CoordinatorDashboardProps {
  usuarios: Usuario[];
  lotes: LoteRequerimiento[];
  matrizItems: MatrizItem[];
  necesidades: Necesidad[];
  codigosUnspsc: CodigoUnspsc[];
  onUpdateLotes: (updated: LoteRequerimiento[]) => void;
}

export default function CoordinatorDashboard({
  usuarios,
  lotes,
  matrizItems,
  necesidades,
  codigosUnspsc,
  onUpdateLotes
}: CoordinatorDashboardProps) {
  const [selectedLoteId, setSelectedLoteId] = useState<number | null>(
    lotes.find(l => l.ESTADO_TRAMITE === "ENVIADO_A_COORDINADOR")?.ID_LOTE || lotes[0]?.ID_LOTE || null
  );
  const [comentarios, setComentarios] = useState("");

  const selectedLote = lotes.find((l) => l.ID_LOTE === selectedLoteId);
  const filteredItems = matrizItems.filter((i) => i.ID_LOTE === selectedLoteId);

  // Filter lots that are waiting for review
  const pendingLots = lotes.filter((l) => l.ESTADO_TRAMITE === "ENVIADO_A_COORDINADOR");
  const processedLots = lotes.filter((l) => l.ESTADO_TRAMITE !== "BORRADOR" && l.ESTADO_TRAMITE !== "ENVIADO_A_COORDINADOR");

  const handleApprove = () => {
    if (!selectedLoteId) return;
    const updated = lotes.map((l) => {
      if (l.ID_LOTE === selectedLoteId) {
        return {
          ...l,
          ESTADO_TRAMITE: "APROBADO_COORDINADOR" as const,
          COMENTARIOS_COORDINADOR: comentarios.trim() || "Aprobado por Coordinación Académica. Cumple con los requisitos y lineamientos de formación."
        };
      }
      return l;
    });
    onUpdateLotes(updated);
    setComentarios("");
  };

  const handleReject = () => {
    if (!selectedLoteId) return;
    if (!comentarios.trim()) {
      alert("Por favor, ingrese un comentario de justificación para rechazar el lote.");
      return;
    }
    const updated = lotes.map((l) => {
      if (l.ID_LOTE === selectedLoteId) {
        return {
          ...l,
          ESTADO_TRAMITE: "RECHAZADO_COORDINADOR" as const,
          COMENTARIOS_COORDINADOR: comentarios
        };
      }
      return l;
    });
    onUpdateLotes(updated);
    setComentarios("");
  };

  const calculateLotTotal = () => {
    return filteredItems.reduce((sum, item) => sum + item.VALOR_TORAL_PROMEDIO, 0);
  };

  return (
    <div id="coordinator-workspace" className="space-y-6">
      {/* Top Banner */}
      <div className="bg-white rounded-xl shadow-sm border border-emerald-100 p-6">
        <div className="flex items-center gap-3 pb-5 border-b border-slate-100">
          <div className="p-3 bg-emerald-50 text-emerald-700 rounded-lg">
            <ClipboardCheck className="h-6 w-6" />
          </div>
          <div>
            <h2 className="text-xl font-bold text-gray-900">Portal de Coordinación Académica</h2>
            <p className="text-xs text-gray-500 mt-0.5">
              Supervisa la pertinencia de las solicitudes de pre-compra, revisa las cotizaciones de referencia (Estudio de Mercado) y autoriza el trámite ante el Almacén.
            </p>
          </div>
        </div>

        {/* Acciones e Instrucciones de Rol */}
        <div className="mt-5">
          <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-3">Acciones y Guía del Coordinador Académico</p>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div className="p-3 bg-slate-50/50 border border-slate-150 rounded-xl">
              <span className="text-xs font-bold text-slate-700 block">1. Revisar Solicitudes</span>
              <p className="text-[10px] text-slate-500 mt-1">Monitorea y selecciona los lotes que han sido radicados y enviados por el instructor líder.</p>
            </div>
            <div className="p-3 bg-slate-50/50 border border-slate-150 rounded-xl">
              <span className="text-xs font-bold text-slate-700 block">2. Verificar Pertinencia</span>
              <p className="text-[10px] text-slate-500 mt-1">Evalúa técnicamente si los materiales coinciden con los programas de formación y las metas del centro.</p>
            </div>
            <div className="p-3 bg-slate-50/50 border border-slate-150 rounded-xl">
              <span className="text-xs font-bold text-slate-700 block">3. Emitir Aprobación</span>
              <p className="text-[10px] text-slate-500 mt-1">Autoriza y transfiere la solicitud aprobada al portal de almacén para verificar stock físico.</p>
            </div>
            <div className="p-3 bg-slate-50/50 border border-slate-150 rounded-xl">
              <span className="text-xs font-bold text-slate-700 block">4. Devolver con Glosas</span>
              <p className="text-[10px] text-slate-500 mt-1">Rechaza de forma constructiva con un comentario formal de mejora, regresando el lote al instructor.</p>
            </div>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left column: Lots pending review */}
        <div className="lg:col-span-1 space-y-4">
          <div className="bg-white rounded-xl shadow-sm border border-emerald-100 p-4">
            <h3 className="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Solicitudes Pendientes ({pendingLots.length})</h3>
            {pendingLots.length === 0 ? (
              <div className="p-4 text-center bg-slate-50 border border-slate-100 rounded-lg text-slate-400 text-xs font-medium">
                No hay trámites pendientes de aprobación.
              </div>
            ) : (
              <div className="space-y-2">
                {pendingLots.map((lote) => {
                  const itemsCount = matrizItems.filter((i) => i.ID_LOTE === lote.ID_LOTE).length;
                  const instructor = usuarios.find((u) => u.ID_USUARIO === lote.ID_SOLICITANTE);
                  return (
                    <button
                      key={lote.ID_LOTE}
                      id={`pending-lote-${lote.ID_LOTE}`}
                      onClick={() => setSelectedLoteId(lote.ID_LOTE)}
                      className={`w-full text-left p-3 rounded-lg border transition-all ${
                        selectedLoteId === lote.ID_LOTE
                          ? "bg-emerald-50 border-emerald-500 text-emerald-950 shadow-xs"
                          : "bg-slate-50 border-slate-200 hover:bg-slate-100 text-gray-700"
                      }`}
                    >
                      <h4 className="font-bold text-sm line-clamp-1">{lote.LOTE_NOMBRE}</h4>
                      <div className="flex justify-between items-center mt-2 text-[10px] text-gray-500">
                        <span>Por: {instructor?.NOMBRE} {instructor?.APELLIDO}</span>
                        <span className="bg-white border border-gray-100 px-1 rounded font-mono font-bold">
                          {itemsCount} {itemsCount === 1 ? "ítem" : "ítems"}
                        </span>
                      </div>
                    </button>
                  );
                })}
              </div>
            )}
          </div>

          {/* Processed lots list */}
          <div className="bg-white rounded-xl shadow-sm border border-emerald-100 p-4">
            <h3 className="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Historial de Decisiones ({processedLots.length})</h3>
            <div className="space-y-2 max-h-[250px] overflow-y-auto">
              {processedLots.map((lote) => {
                const itemsCount = matrizItems.filter((i) => i.ID_LOTE === lote.ID_LOTE).length;
                return (
                  <button
                    key={lote.ID_LOTE}
                    id={`processed-lote-${lote.ID_LOTE}`}
                    onClick={() => setSelectedLoteId(lote.ID_LOTE)}
                    className={`w-full text-left p-3 rounded-lg border transition-all text-xs flex justify-between items-center ${
                      selectedLoteId === lote.ID_LOTE
                        ? "bg-slate-100 border-slate-400 text-slate-900"
                        : "bg-white border-gray-150 hover:bg-slate-50 text-gray-600"
                    }`}
                  >
                    <div className="max-w-[70%]">
                      <h4 className="font-semibold line-clamp-1">{lote.LOTE_NOMBRE}</h4>
                      <span className="text-[9px] text-gray-400 font-mono">ID: {lote.ID_LOTE}</span>
                    </div>
                    <span className={`text-[9px] font-black px-1.5 py-0.5 rounded-full ${
                      lote.ESTADO_TRAMITE === "RECHAZADO_COORDINADOR" ? "bg-red-100 text-red-800" :
                      lote.ESTADO_TRAMITE === "APROBADO_COORDINADOR" ? "bg-purple-100 text-purple-800" :
                      "bg-emerald-100 text-emerald-800"
                    }`}>
                      {lote.ESTADO_TRAMITE.replace(/_/g, " ").replace("COORDINADOR", "")}
                    </span>
                  </button>
                );
              })}
            </div>
          </div>
        </div>

        {/* Right column: Lot Detail inspection & actions */}
        <div className="lg:col-span-2 space-y-6">
          {selectedLote ? (
            <div className="bg-white rounded-xl shadow-sm border border-emerald-100 p-6">
              {/* Lot overview info */}
              <div className="pb-4 border-b border-gray-100 flex flex-col sm:flex-row justify-between sm:items-center gap-4">
                <div>
                  <div className="flex items-center gap-2">
                    <span className={`text-[10px] font-black px-2 py-0.5 rounded ${
                      selectedLote.ESTADO_TRAMITE === "ENVIADO_A_COORDINADOR" ? "bg-blue-100 text-blue-800 animate-pulse" :
                      selectedLote.ESTADO_TRAMITE === "APROBADO_COORDINADOR" ? "bg-emerald-100 text-emerald-800" :
                      selectedLote.ESTADO_TRAMITE === "RECHAZADO_COORDINADOR" ? "bg-red-100 text-red-800" :
                      "bg-purple-100 text-purple-800"
                    }`}>
                      {selectedLote.ESTADO_TRAMITE.replace(/_/g, " ")}
                    </span>
                    <span className="text-xs text-gray-400 font-mono">Iniciado: {selectedLote.FECHA_CREACIÓN}</span>
                  </div>
                  <h3 className="text-lg font-black text-gray-900 mt-1">{selectedLote.LOTE_NOMBRE}</h3>
                  <p className="text-xs text-gray-500 mt-1">
                    Instructor Lider: <strong className="text-gray-700 font-semibold">{usuarios.find(u => u.ID_USUARIO === selectedLote.ID_SOLICITANTE)?.NOMBRE} {usuarios.find(u => u.ID_USUARIO === selectedLote.ID_SOLICITANTE)?.APELLIDO}</strong>
                  </p>
                </div>
                <div className="text-left sm:text-right bg-emerald-50/50 border border-emerald-100 px-4 py-2 rounded-xl">
                  <span className="text-[10px] text-gray-400 uppercase tracking-widest block font-bold">Estudio Presupuestal Promedio</span>
                  <span className="text-xl font-black text-emerald-800">${calculateLotTotal().toLocaleString()} COP</span>
                </div>
              </div>

              {/* Items in the Lot */}
              <div className="mt-6">
                <h4 className="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Ítems de la Matriz de Bienes</h4>
                <div className="overflow-x-auto">
                  <table className="w-full text-left border-collapse">
                    <thead>
                      <tr className="border-b border-gray-100 text-gray-400 text-[10px] font-bold uppercase font-mono">
                        <th className="py-2 px-1">Material / Bien</th>
                        <th className="py-2 px-1 text-center">Unidad</th>
                        <th className="py-2 px-1 text-right">Cant. Total</th>
                        <th className="py-2 px-1 text-right">Est. Prom Unit</th>
                        <th className="py-2 px-1 text-right">Est. Prom Total</th>
                        <th className="py-2 px-1 text-center">Clasificación UNSPSC</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 text-xs">
                      {filteredItems.map((item) => {
                        const code = codigosUnspsc.find((c) => c.ID_MATRIZ_ITEM === item.ID_MATRIZ_ITEM);
                        const need = necesidades.find((n) => n.ID_MATRIZ === item.ID_MATRIZ_ITEM);
                        return (
                          <React.Fragment key={item.ID_MATRIZ_ITEM}>
                            <tr className="hover:bg-slate-50/30">
                              <td className="py-3 px-1">
                                <span className="font-bold text-gray-800 block">{item.DESCRIPCIÓN_BIEN}</span>
                              </td>
                              <td className="py-3 px-1 text-center text-gray-500 font-semibold">{item.UNIDAD_MEDIDA}</td>
                              <td className="py-3 px-1 text-right font-bold text-gray-800">{item.CANTIDAD_REGULAR}</td>
                              <td className="py-3 px-1 text-right text-gray-600">${item.VALOR_UNITARIO_PROMEDIO.toLocaleString()}</td>
                              <td className="py-3 px-1 text-right font-semibold text-emerald-700">${item.VALOR_TORAL_PROMEDIO.toLocaleString()}</td>
                              <td className="py-3 px-1 text-center font-mono text-[10px] text-gray-500">
                                {code ? code.CLASE : <span className="text-red-500">No asig.</span>}
                              </td>
                            </tr>
                            {/* Nested Population Needs list */}
                            {need && (
                              <tr>
                                <td colSpan={6} className="bg-slate-50/50 p-2 text-[10px] border-b border-gray-100">
                                  <div className="flex items-center gap-1.5 text-gray-400 mb-1 font-bold">
                                    <Users className="w-3.5 h-3.5 text-emerald-600" /> JUSTIFICACIÓN DE NECESIDAD (POBLACIÓN):
                                  </div>
                                  <div className="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-9 gap-1 text-center font-mono">
                                    {need.CANTIDAD_REGULAR > 0 && (
                                      <div className="bg-white border rounded p-1">
                                        <span className="block text-[8px] text-gray-400 truncate">Regular</span>
                                        <span className="font-bold text-gray-700">{need.CANTIDAD_REGULAR}</span>
                                      </div>
                                    )}
                                    {need.CANTIDAD_CAMPESINA_COMPLEMENTARIA > 0 && (
                                      <div className="bg-white border rounded p-1">
                                        <span className="block text-[8px] text-gray-400 truncate" title="CampeSENA Complementaria">Camp Comp</span>
                                        <span className="font-bold text-gray-700">{need.CANTIDAD_CAMPESINA_COMPLEMENTARIA}</span>
                                      </div>
                                    )}
                                    {need.CANTIDAD_CAMPESINA_TITULADA > 0 && (
                                      <div className="bg-white border rounded p-1">
                                        <span className="block text-[8px] text-gray-400 truncate" title="CampeSENA Titulada">Camp Tit</span>
                                        <span className="font-bold text-gray-700">{need.CANTIDAD_CAMPESINA_TITULADA}</span>
                                      </div>
                                    )}
                                    {need.CANTIDAD_VULNERABLE > 0 && (
                                      <div className="bg-white border rounded p-1">
                                        <span className="block text-[8px] text-gray-400 truncate">Vulnerab</span>
                                        <span className="font-bold text-gray-700">{need.CANTIDAD_VULNERABLE}</span>
                                      </div>
                                    )}
                                    {need.CANTIDAD_MEDIA_TECNICA > 0 && (
                                      <div className="bg-white border rounded p-1">
                                        <span className="block text-[8px] text-gray-400 truncate">M.Téc</span>
                                        <span className="font-bold text-gray-700">{need.CANTIDAD_MEDIA_TECNICA}</span>
                                      </div>
                                    )}
                                    {need.CANTIDAD_FIC > 0 && (
                                      <div className="bg-white border rounded p-1">
                                        <span className="block text-[8px] text-gray-400 truncate">FIC</span>
                                        <span className="font-bold text-gray-700">{need.CANTIDAD_FIC}</span>
                                      </div>
                                    )}
                                    {need.CANTIDAD_ECONOMIA_POPULAR > 0 && (
                                      <div className="bg-white border rounded p-1">
                                        <span className="block text-[8px] text-gray-400 truncate">Econ.Pop</span>
                                        <span className="font-bold text-gray-700">{need.CANTIDAD_ECONOMIA_POPULAR}</span>
                                      </div>
                                    )}
                                    {need.CANTIDAD_ENI > 0 && (
                                      <div className="bg-white border rounded p-1">
                                        <span className="block text-[8px] text-gray-400 truncate">ENI</span>
                                        <span className="font-bold text-gray-700">{need.CANTIDAD_ENI}</span>
                                      </div>
                                    )}
                                    {need.CANTIDAD_FC_CAMPESINA > 0 && (
                                      <div className="bg-white border rounded p-1">
                                        <span className="block text-[8px] text-gray-400 truncate">FC.Camp</span>
                                        <span className="font-bold text-gray-700">{need.CANTIDAD_FC_CAMPESINA}</span>
                                      </div>
                                    )}
                                  </div>
                                </td>
                              </tr>
                            )}
                          </React.Fragment>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              </div>

              {/* Approval/Rejection action container */}
              {selectedLote.ESTADO_TRAMITE === "ENVIADO_A_COORDINADOR" ? (
                <div className="mt-8 p-4 bg-slate-50 border border-gray-200 rounded-xl space-y-4">
                  <h4 className="text-xs font-bold text-gray-600 uppercase tracking-wider flex items-center gap-1.5">
                    <MessageSquare className="w-4 h-4 text-emerald-600" />
                    Concepto de Viabilidad de la Coordinación Académica
                  </h4>
                  <textarea
                    rows={3}
                    placeholder="Escriba aquí los comentarios, justificación o las razones técnicas de la aprobación o rechazo del lote..."
                    value={comentarios}
                    onChange={(e) => setComentarios(e.target.value)}
                    className="w-full bg-white border border-gray-300 rounded-lg p-3 text-xs focus:outline-emerald-500 font-sans"
                  />
                  <div className="flex justify-end gap-2.5">
                    <button
                      id="btn-reject-lote"
                      onClick={handleReject}
                      className="inline-flex items-center gap-1.5 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-xs font-bold transition-all shadow-sm"
                    >
                      <XCircle className="w-4 h-4" /> Rechazar y Devolver
                    </button>
                    <button
                      id="btn-approve-lote"
                      onClick={handleApprove}
                      className="inline-flex items-center gap-1.5 bg-emerald-700 hover:bg-emerald-800 text-white px-4 py-2 rounded-lg text-xs font-bold transition-all shadow-sm"
                    >
                      <CheckCircle className="w-4 h-4" /> Aprobar Trámite
                    </button>
                  </div>
                </div>
              ) : (
                <div className="mt-8 p-4 bg-emerald-50 border border-emerald-200 rounded-xl">
                  <h4 className="text-xs font-bold text-emerald-950 flex items-center gap-1.5">
                    <Award className="w-4 h-4 text-emerald-600" /> Trámite de Pre-Compra Procesado
                  </h4>
                  <p className="text-xs text-emerald-900 mt-1 font-semibold">
                    Comentario registrado por coordinación:
                  </p>
                  <p className="text-xs text-emerald-800 mt-1 italic bg-white/70 p-3 rounded border border-emerald-100">
                    "{selectedLote.COMENTARIOS_COORDINADOR || "Sin comentarios adicionales registrados."}"
                  </p>
                </div>
              )}
            </div>
          ) : (
            <div className="bg-white border border-gray-150 rounded-xl p-12 text-center text-gray-400">
              <AlertCircle className="w-12 h-12 text-gray-300 mx-auto mb-3 animate-bounce" />
              <h3 className="text-base font-bold text-gray-700">No hay Lote Seleccionado</h3>
              <p className="text-xs text-gray-500 max-w-sm mx-auto mt-1">
                Haz clic en una de las solicitudes de la barra lateral para abrir su panel de auditoría, revisar ítems y firmar el concepto técnico.
              </p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
